<?php
/*
  vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Issabel version 4.0                                                  |
  | http://www.issabel.org                                               |
  +----------------------------------------------------------------------+
  | Copyright (c) 2021 Issabel Foundation                                |
  | Copyright (c) 2006 Palosanto Solutions S. A.                         |
  +----------------------------------------------------------------------+
  | The contents of this file are subject to the General Public License  |
  | (GPL) Version 2 (the "License"); you may not use this file except in |
  | compliance with the License. You may obtain a copy of the License at |
  | http://www.opensource.org/licenses/gpl-license.php                   |
  |                                                                      |
  | Software distributed under the License is distributed on an "AS IS"  |
  | basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See  |
  | the License for the specific language governing rights and           |
  | limitations under the License.                                       |
  +----------------------------------------------------------------------+
  | Autores: Alex Villacís Lasso <a_villacis@palosanto.com>              |
  +----------------------------------------------------------------------+
  $Id: paloServerSentEvents.class.php, Thu 20 May 2021 08:46:04 AM EDT, nicolas@issabel.com
*/
require_once 'libs/misc.lib.php';
require_once 'libs/paloSantoJSON.class.php';

class paloServerSentEvents
{
    private $_implementation;
    private $_module_name;
    private $_debug = FALSE;
    
    private function debug($s)
    {
    	if ($this->_debug) {
    		file_put_contents('/tmp/debug-sse-core.txt', $s, FILE_APPEND);
    	}
    }
    
    function __construct($module_name, $implClass)
    {
        $this->_module_name = $module_name;
        if (is_object($implClass)) {
        	$this->_implementation = $implClass;
        } else {
        	$this->_implementation = new $implClass;
        }
    }
    
    function handle()
    {
        $jsonResponse = array();
    
    	ignore_user_abort(true);
        set_time_limit(0);

        // Estado del lado del cliente
        $estadoHash = getParameter('clientstatehash');
        if (!is_null($estadoHash)) {
            $estadoCliente = isset($_SESSION[$this->_module_name]['estadoCliente']) 
                ? $_SESSION[$this->_module_name]['estadoCliente'] 
                : array();        
        } else {
            $estadoCliente = getParameter('clientstate');
            if (!is_array($estadoCliente)) return;
        }
    
        // Modo a funcionar: Long-Polling, o Server-sent Events
        $sModoEventos = getParameter('serverevents');
        $bSSE = (!is_null($sModoEventos) && $sModoEventos); 
        if ($bSSE) {
            Header('Content-Type: text/event-stream');
            $this->_printflush("retry: 1\n");
        } else {
            Header('Content-Type: application/json');
        }
        
        // Verificar hash correcto
        if (!is_null($estadoHash) && $estadoHash != $_SESSION[$this->_module_name]['estadoClienteHash']) {
            $jsonResponse['estadoClienteHash'] = 'mismatch';
            $jsonResponse['hashRecibido'] = $estadoHash;
            if ($bSSE) $this->_printflush("retry: 5000\n");
            $this->_jsonflush($bSSE, $jsonResponse);
            return;
        }

        $this->debug("Estado inicial: ".print_r($estadoCliente, 1));                
        $jsonResponse = $this->_implementation->createEmptyResponse();
        $bKeepListening = $this->_implementation->findInitialStateDifferences($estadoCliente, $jsonResponse);
        if (!$bKeepListening) {
            $this->debug("Estado inicial aborta la escucha: ".print_r($estadoCliente, 1));
            $this->debug("Respuesta inicial aborta la escucha: ".print_r($jsonResponse, 1));
            $jsonResponse['estadoClienteHash'] = self::generarEstadoHash($this->_module_name, $estadoCliente);
            $this->_jsonflush($bSSE, $jsonResponse);
        } else {
            $this->_implementation->setupBeforeEventLoop();
            $iTimeoutPoll = $this->_suggestEventTimeout();
            do {
            	$this->_implementation->setupBeforeEventWait();
    
                // Se inicia espera larga con el navegador...
                $iTimestampInicio = time();
                $this->debug("Respuesta antes de while: ".print_r($jsonResponse, 1));                
                $this->debug("Estado antes de while: ".print_r($estadoCliente, 1));                
                while (connection_status() == CONNECTION_NORMAL 
                    && $this->_implementation->isEmptyResponse($jsonResponse) 
                    && time() - $iTimestampInicio <  $iTimeoutPoll) {
    
                    session_commit();
                    if (!$this->_implementation->waitForEvents()) {
                        $jsonResponse['error'] = $this->_implementation->getErrMsg();
                        $this->_jsonflush($bSSE, $jsonResponse);
                        $this->_implementation->shutdown();
                        return;
                    }
                    @session_start();
                    
                    if (isset($_SESSION[$this->_module_name]) 
                        && $this->_implementation->checkInvalidatedWait($estadoCliente, $_SESSION[$this->_module_name]['estadoCliente'])) {
                        $this->debug("Estado invalidado\n");
                        $jsonResponse['estadoClienteHash'] = 'invalidated';
                        if ($bSSE) $this->_printflush("retry: 5000\n");
                        $this->_jsonflush($bSSE, $jsonResponse);
                        $this->_implementation->shutdown();
                        return;
                    }
                    
                    if (isset($_SESSION[$this->_module_name]) && 
                        isset($_SESSION[$this->_module_name]['finalizarEscucha']) && 
                        $_SESSION[$this->_module_name]['finalizarEscucha']) {
                        $this->debug("Petición de finalización\n");
                        $jsonResponse['estadoClienteHash'] = 'shutdown';
                        if ($bSSE) $this->_printflush("retry: 5000\n");
                        $this->_jsonflush($bSSE, $jsonResponse);
                        $this->_implementation->shutdown();
                        return;
                    }
                    
                    $bKeepListening = $this->_implementation->findEventStateDifferences($estadoCliente, $jsonResponse);
                    $this->debug("Diferencias encontradas ($bKeepListening): ".print_r($jsonResponse, 1));
                    $this->debug("Estado modificado: ".print_r($estadoCliente, 0));                
                }
    
                $jsonResponse['estadoClienteHash'] = self::generarEstadoHash($this->_module_name, $estadoCliente);
                $this->_jsonflush($bSSE, $jsonResponse);
                
                $jsonResponse = $this->_implementation->createEmptyResponse();
            } while ($bSSE && $bKeepListening && connection_status() == CONNECTION_NORMAL);
        }
        
        $this->_implementation->shutdown();
    }
    
    private function _suggestEventTimeout()
    {
        $iTimeoutPoll = 2 * 60;
/*
        // Problemas con MSIE al haber más de un AJAX con respuesta larga
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE ') !== false) {
            $iTimeoutPoll = 2;
        }
*/
        return $iTimeoutPoll;
    }

    private function _jsonflush($bSSE, $jsonResponse)
    {
        $json = new Services_JSON();
        $r = $json->encode($jsonResponse);
        if ($bSSE)
            $this->_printflush("data: $r\n\n");
        else $this->_printflush($r);
    }
    
    private function _printflush($s)
    {
        print $s;
        $this->debug($s);
        ob_flush();
        flush();
    }
    
    static function generarEstadoHash($module_name, $estadoCliente)
    {
        $estadoHash = md5(serialize($estadoCliente));
        $_SESSION[$module_name]['estadoCliente'] = $estadoCliente;
        $_SESSION[$module_name]['estadoClienteHash'] = $estadoHash;
        $_SESSION[$module_name]['finalizarEscucha'] = FALSE;
    
        return $estadoHash;
    }
    
}
?>
