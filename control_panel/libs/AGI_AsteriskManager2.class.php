<?php
if (file_exists("/var/lib/asterisk/agi-bin/phpagi-asmanager.php")) {
require_once "/var/lib/asterisk/agi-bin/phpagi-asmanager.php";
}

define('AMI_PORT', 5038);

class AGI_AsteriskManager2 extends AGI_AsteriskManager
{
    private $_txbuffer = '';
    private $_rxbuffer = '';
    private $_listaEventos = array();   // Eventos pendientes por procesar
    private $_response = NULL;          // Respuesta recibida del último comando
    private $_debug = FALSE;

    private function debug($s)
    {
        if ($this->_debug) {
            file_put_contents('/tmp/debug-ami-protocol.txt', $s, FILE_APPEND);
        }
    }
    
    function procesarActividad($iMaxTimeout = 1)
    {
        $bNuevosDatos = FALSE;
        $listoLeer = array();
        $listoEscribir = array();
        $listoErr = NULL;

        if (is_null($this->socket)) return FALSE;
        
        $listoLeer[] = $this->socket;
        if (strlen($this->_txbuffer) > 0) $listoEscribir[] = $this->socket;
        
        $iNumCambio = @stream_select($listoLeer, $listoEscribir, $listoErr, $iMaxTimeout);
        if ($iNumCambio === false) {
            // Interrupción, tal vez una señal
            $this->log("INFO: select() finaliza con fallo - señal pendiente?");
        } elseif ($iNumCambio > 0 || count($listoLeer) > 0 || count($listoEscribir) > 0) {
            if (in_array($this->socket, $listoEscribir)) {
                // Escribir lo más que se puede de los datos pendientes por mostrar
                $iBytesEscritos = fwrite($this->socket, $this->_txbuffer);
                if ($iBytesEscritos === FALSE) {
                    $this->log("ERR: error al escribir datos");
                    fclose($this->socket);
                    $this->socket = NULL;
                } else {
                    $this->_txbuffer = substr($this->_txbuffer, $iBytesEscritos);
                    $bNuevosDatos = TRUE;                        
                }
            }
            if (in_array($this->socket, $listoLeer)) {
                // Leer datos de la conexión lista para leer
                $sNuevaEntrada = fread($this->socket, 128 * 1024);
                if ($sNuevaEntrada == '') {
                    // Lectura de cadena vacía indica que se ha cerrado la conexión remotamente
                    fclose($this->socket);
                    $this->socket = NULL;
                } else {
                    $this->debug($sNuevaEntrada);
                    $this->_rxbuffer .= $sNuevaEntrada;
                    $iLongProcesado = $this->_parsearPaquetes($this->_rxbuffer);
                }
                
                $bNuevosDatos = TRUE;
            }
        }
        
        return $bNuevosDatos;
    }

    function procesarPaquetes()
    {
        $bHayProcesados = FALSE;
        if ($this->hayPaquetes()) {
            $bHayProcesados = TRUE;
            $this->procesarPaquete();
            //$this->vaciarBuferesEscritura();
        }
        return $bHayProcesados;
    }

    /**
     * Disconnect
     *
     * @example examples/sip_show_peer.php Get information about a sip peer
     */
    function disconnect($dontlogoff=NULL)
    {
        if (!$dontlogoff) $this->logoff();
        if (!is_null($this->socket)) fclose($this->socket);
    }


    /**************************************************************************/

    // Separar flujo de datos en paquetes, devuelve número de bytes de paquetes aceptados
    private function _parsearPaquetes(&$sDatos)
    {
        $iLongInicial = strlen($sDatos);

        // Encontrar los paquetes y determinar longitud de búfer procesado
        $listaPaquetes = $this->encontrarPaquetes($sDatos);
        $iLongFinal = strlen($sDatos);
        
        /* Paquetes Event se van a la lista de eventos. El paquete Response se
         * guarda individualmente. */ 
        foreach ($listaPaquetes as $paquete) {
            if (isset($paquete['Event'])) {
                $e = strtolower($paquete['Event']);
                if (isset($this->event_handlers[$e]) || isset($this->event_handlers['*'])) {
                    $paquete['local_timestamp_received'] = microtime(TRUE);
                    $this->_listaEventos[] = $paquete;
                }
            } elseif (isset($paquete['Response'])) {
                if (!is_null($this->_response)) {
                    $this->log("ERR: segundo Response sobreescribe primer Response no procesado: ".
                        print_r($this->_response, 1));
                }
                $this->_response = $paquete;
            } else {
                $this->log("ERR: el siguiente paquete no se reconoce como Event o Response: ".
                    print_r($paquete, 1));
            }
        }
        return $iLongInicial - $iLongFinal;
    }

    private function dividirLineas(&$sDatos)
    {
        /* Dividir el búfer por salto de línea. Si el último elemento es vacío, el
           búfer terminaba en \n. Luego se restaura el \n en cada línea para que se
           cumpla que implode("", $lineas) == $sDatos */
        $lineas = explode("\n", $sDatos);
        if (count($lineas) > 0) {
            for ($i = 0; $i < count($lineas) - 1; $i++) $lineas[$i] .= "\n";
            if($lineas[count($lineas) - 1] == '')
                array_pop($lineas);
        }
        assert('implode("", $lineas) == $sDatos');
        return $lineas;
    }

    /**
     * Procedimiento que intenta descomponer el búfer de lectura indicado por $sDatos
     * en una secuencia de paquetes de AMI (Asterisk Manager Interface). La lista de
     * paquetes obtenida se devuelve como una lista. Además el búfer de lectura se
     * modifica para eliminar los datos que fueron ya procesados como parte de los
     * paquetes. Esta función sólo devuelve paquetes completos, y deja cualquier
     * fracción de paquetes incompletos en el búfer.
     *
     * @param   string  $sDatos     Cadena de datos a procesar
     *
     * @return  array   Lista de paquetes que fueron extraídos del texto.
     */
    private function encontrarPaquetes(&$sDatos)
    {
        $lineas = $this->dividirLineas($sDatos);
    
        $listaPaquetes = array();
        $paquete = array();
        $bIncompleto = FALSE;
        $iLongPaquete = 0;
        while (!$bIncompleto && count($lineas) > 0) {
            $s = array_shift($lineas);
            $iLongPaquete += strlen($s);
            if (substr($s, strlen($s) - 1, 1) != "\n") {
                /* A la última línea le falta el salto de línea - búfer termina en 
                   medio de la línea */            
                $bIncompleto = TRUE;
            } else {
                $s = trim($s);  // Remover salto de línea al final
                $a = strpos($s, ':');
                if ($a) {
                    $sClave = substr($s, 0, $a);
                    $sValor = substr($s, $a + 2);
                    // Si hay una respuesta Follows, es la primera línea

                    if($sClave=='Message' && $sValor=="Command output follows") {
                        $paquete['data'] = '';
                        $paquete['Response']='Follows';
                        foreach($lineas as $s) {
                            $s = preg_replace("/^Output: /","",$s);
                            $paquete['data'] .= $s;
                        }
                    } else {

                        if (!count($paquete)) {
                            if ($sValor == 'Follows') {
                                $paquete['data'] = '';
                                while (!$bIncompleto && substr($s, 0, 6) != '--END ') {
                                    if (count($lineas) <= 0) {
                                        $bIncompleto = TRUE;
                                    } else {
                                        $s = array_shift($lineas);
                                        $iLongPaquete += strlen($s);
                                        if (substr($s, 0, 6) != '--END ') {
                                            $paquete['data'] .= $s;
                                        }
                                    }
                                }
                            }
                        }
                        $paquete[$sClave] = $sValor;
                    }
                } elseif ($s == "") {
                    // Se ha encontrado el final de un paquete
                    if (count($paquete)) $listaPaquetes[] = $paquete;
                    $paquete = array();
                    $sDatos = substr($sDatos, $iLongPaquete);
                    $iLongPaquete = 0;
                }
            }
        }
    
        return $listaPaquetes;
    }
    
    // Preguntar si hay paquetes pendientes de procesar
    private function hayPaquetes() { return (count($this->_listaEventos) > 0); }

    // Procesar un solo paquete de la cola de paquetes
    private function procesarPaquete()
    {
        $paquete = array_shift($this->_listaEventos);
        $this->process_event($paquete);
    }
    
    // Implementación de send_request para compatibilidad con phpagi-asmanager
    function send_request($action, $parameters=array(), $retry=true)
    {
        if (!is_null($this->socket)) {
            $req = "Action: $action\r\n";
            foreach($parameters as $var => $val) $req .= "$var: $val\r\n";
            $req .= "\r\n";
            $this->_txbuffer .= $req;

            $this->debug($req);

            return $this->wait_response();
        } else return NULL;
    }

    // Implementación de wait_response para compatibilidad con phpagi-asmanager
    function wait_response($allow_timeout = false, $return_on_event = false)
    {
        while (!is_null($this->socket) && is_null($this->_response)) {
            if (!$this->procesarActividad()) {
                usleep(100000);
            }
        }
        if (!is_null($this->_response)) {
            $r = $this->_response;
            $this->_response = NULL;
            return $r;
        }
        if (is_null($this->socket)) {
            $this->log("ERR: conexión AMI cerrada mientras se esperaba respuesta.");
            return NULL;
        }
    }
    
    function connect($server=NULL, $username=NULL, $secret=NULL, $events="on")
    {
        // Determinar servidor y puerto a usar
        $iPuerto = AMI_PORT;
        if(strpos($server, ':') !== false) {
            $c = explode(':', $server);
            $server = $c[0];
            $iPuerto = $c[1];
        }
        $this->server = $server;
        $this->port = $iPuerto;
        
        // Iniciar la conexión
        $errno = $errstr = NULL;
        $sUrlConexion = "tcp://$server:$iPuerto";
        $hConn = @stream_socket_client($sUrlConexion, $errno, $errstr);
        if (!$hConn) {
            $this->log("ERR: no se puede conectar a puerto AMI en $sUrlConexion: ($errno) $errstr");
            return FALSE;
        }
        
        // Leer la cabecera de Asterisk
        $str = fgets($hConn);
        if ($str == false) {
            $this->log("ERR: No se ha recibido la cabecera de Asterisk Manager");
            return false;
        }
        
        // Registrar el socket con el objeto de conexiones
        stream_set_blocking($hConn, 0);
        $this->socket = $hConn;

        // Iniciar login con Asterisk
        $res = $this->send_request('login', array('Username'=>$username, 'Secret'=>$secret));
        if($res['Response'] != 'Success') {
            $this->log("ERR: Fallo en login de AMI.");
            $this->disconnect();
            return false;
        }
        return true;
    }
    
   /**
    * Add event handler
    *
    * Known Events include ( http://www.voip-info.org/wiki-asterisk+manager+events )
    *   Link - Fired when two voice channels are linked together and voice data exchange commences.
    *   Unlink - Fired when a link between two voice channels is discontinued, for example, just before call completion.
    *   Newexten -
    *   Hangup -
    *   Newchannel -
    *   Newstate -
    *   Reload - Fired when the "RELOAD" console command is executed.
    *   Shutdown -
    *   ExtensionStatus -
    *   Rename -
    *   Newcallerid -
    *   Alarm -
    *   AlarmClear -
    *   Agentcallbacklogoff -
    *   Agentcallbacklogin -
    *   Agentlogoff -
    *   MeetmeJoin -
    *   MessageWaiting -
    *   join -
    *   leave -
    *   AgentCalled -
    *   ParkedCall - Fired after ParkedCalls
    *   Cdr -
    *   ParkedCallsComplete -
    *   QueueParams -
    *   QueueMember -
    *   QueueStatusEnd -
    *   Status -
    *   StatusComplete -
    *   ZapShowChannels - Fired after ZapShowChannels
    *   ZapShowChannelsComplete -
    *
    * @param string $event type or * for default handler
    * @param string $callback function
    * @return boolean sucess
    */
    function add_event_handler($event, $callback)
    {
      $event = strtolower($event);
      if(isset($this->event_handlers[$event]))
      {
        $this->log("WARN: $event handler is already defined, not over-writing.");
        return false;
      }
      $this->event_handlers[$event] = $callback;
      return true;
    }

    function remove_event_handler($event)
    {
        if (isset($this->event_handlers[$event])) {
            unset($this->event_handlers[$event]);
        }
    }

   /**
    * Process event
    *
    * @access private
    * @param array $parameters
    * @return mixed result of event handler or false if no handler was found
    */
    function process_event($parameters)
    {
      $ret = false;
      $e = strtolower($parameters['Event']);

      $handler = '';
      if(isset($this->event_handlers[$e])) $handler = $this->event_handlers[$e];
      elseif(isset($this->event_handlers['*'])) $handler = $this->event_handlers['*'];

      if ((is_array($handler) && count($handler) >= 2 && is_object($handler[0]) && 
        method_exists($handler[0], $handler[1])) || function_exists($handler))
      {
        $ret = call_user_func($handler, $e, $parameters, $this->server, $this->port);
      }
      return $ret;
    }

    function PJSIPShowEndpoints($actionid = NULL)
    {
    	return $this->send_request('PJSIPShowEndpoints', $actionid ? array('ActionID' => $actionid) : array());
    }

    function SIPPeers($actionid = NULL)
    {
    	return $this->send_request('SIPPeers', $actionid ? array('ActionID' => $actionid) : array());
    }

    function IAXpeerlist($actionid = NULL)
    {
        return $this->send_request('IAXpeerlist', $actionid ? array('ActionID' => $actionid) : array());
    }

    function Status($channel = NULL, $actionid = NULL)
    {
        $parameters = array();
        if ($channel) $parameters['Channel'] = $channel;
        if ($actionid) $parameters['ActionID'] = $actionid;
        return $this->send_request('Status', $parameters);
    }

    function MeetmeList($conference = NULL, $actionid = NULL)
    {
        $parameters = array();
        if ($conference) $parameters['Conference'] = $conference;
        if ($actionid) $parameters['ActionID'] = $actionid;
        return $this->send_request('MeetmeList', $parameters);
    }
    
    function DAHDIShowChannels($dahdichannel = NULL, $actionid = NULL)
    {
        $parameters = array();
        if ($dahdichannel) $parameters['DAHDIChannel'] = $dahdichannel;
        if ($actionid) $parameters['ActionID'] = $actionid;
        return $this->send_request('DAHDIShowChannels', $parameters);
    }

}
?>
