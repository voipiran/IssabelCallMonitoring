<?php
/*
  vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Issabel version 1.6-3                                               |
  | http://www.issabel.org                                               |
  +----------------------------------------------------------------------+
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
  | The Initial Developer of the Original Code is PaloSanto Solutions    |
  +----------------------------------------------------------------------+
  $Id: paloControlPanelStatus.class.php, Thu 08 Apr 2021 05:37:16 PM EDT, nicolas@issabel.com
*/
require_once 'libs/misc.lib.php';
require_once 'libs/paloSantoDB.class.php';
require_once 'AGI_AsteriskManager2.class.php';
require_once 'paloInterfaceSSE.class.php';
require_once 'libs/paloSantoTrunk.class.php';

class paloControlPanelStatus extends paloInterfaceSSE
{
    private $_db = NULL;
    private $_dbConfig = NULL;
    private $_ami = NULL;
    private $_actionid = NULL;
    
    private $_internalState;
    private $_bModified = FALSE;
    private $_enumsInProgress = 0;
    private $_debug = FALSE;
    private $_bridges = array();

    // Constructor - abrir conexión a base de datos y a AMI    
	function __construct()
    {
        global $arrConf;
        $this->_actionid = get_class($this).'-'.posix_getpid();
        
        foreach (array('phones', 'dahdi', 'iptrunks', 'conferences', 'parkinglots', 'queues') as $k)
            $this->_internalState[$k] = array();
        $this->_internalState['dahdi'] = array(
            'chan2span' =>  array(),    // Dado un channel, obtener el span que lo contiene
            'spans'     =>  array(),    // Información por span y luego por channel
        );
        
        $dsn = generarDSNSistema('asteriskuser', 'asterisk');
        $this->_db = new paloDB($dsn);
        if ($this->_db->errMsg != '') {
            $this->_errMsg = $this->_db->errMsg;
            $this->_db = NULL;
            return;            
        }
        
        $this->_dbConfig = new paloDB($arrConf['dsn_conn_database']);
        if ($this->_dbConfig->errMsg != '') {
            $this->_errMsg = $this->_dbConfig->errMsg;
            $this->_dbConfig = NULL;
            return;            
        }
        
        $this->_ami = new AGI_AsteriskManager2();
        if (!$this->_ami->connect('localhost', 'admin', obtenerClaveAMIAdmin())) {
        	$this->_errMsg = _tr("Error when connecting to Asterisk Manager");
            $this->_ami = NULL;
            return;
        }
        
        // Instalar todos los manejadores según el nombre del método
        foreach (get_class_methods(get_class($this)) as $sMetodo) {
            $regs = NULL;
            if (preg_match('/^msg_(.+)$/', $sMetodo, $regs)) {
                if ($regs[1] != 'Default') {
                    $this->_ami->add_event_handler($regs[1], array($this, $sMetodo));
                }
            }
        }
        if ($this->_debug && method_exists($this, 'msg_Default'))
            $this->_ami->add_event_handler('*', array($this, 'msg_Default'));
    }
    
    /**************************************************************************/
    
    function createEmptyResponse()
    {
    	return array('pbxchanges' => array());
    }
    
    function isEmptyResponse($jsonResponse)
    {
        return (count($jsonResponse['pbxchanges']) == 0);
    }
    
    function findInitialStateDifferences(&$initialClientState, &$jsonResponse)
    {
    	foreach (array('phones', 'dahdi', 'iptrunks', 'conferences', 'parkinglots', 'queues') as $k)
            if (!isset($initialClientState[$k])) $initialClientState[$k] = array();
        if (!isset($initialClientState['dahdi']))
            $initialClientState['dahdi'] = array();
    
        $this->_buildInternalState();
        $r = $this->findEventStateDifferences($initialClientState, $jsonResponse);
        return $r;    
    }
    
    function waitForEvents()
    {
        if ($this->_ami->procesarPaquetes())
            $this->_ami->procesarActividad(0);
        else $this->_ami->procesarActividad(1);
        return !is_null($this->_ami->socket);
    }
    
    function findEventStateDifferences(&$currentClientState, &$jsonResponse)
    {
        if (!$this->_bModified) return TRUE;
        
        foreach (array('phones', 'iptrunks', 'conferences', 'parkinglots', 'queues'/*, 'dahdi'*/) as $objtype) {
        	if (!isset($currentClientState[$objtype]))
                $currentClientState[$objtype] = array();
            foreach ($this->_internalState[$objtype] as $k => $v) {
                if (!isset($currentClientState[$objtype][$k]) || $currentClientState[$objtype][$k] != $v) {
                	$changetype = isset($currentClientState[$objtype][$k]) ? 'update' : 'create';
                    $currentClientState[$objtype][$k] = $v;
                    $v['objtype'] = $objtype;
                    $v['changetype'] = $changetype;
                    if (isset($v['active'])) $v['active'] = array_values($v['active']);
                    if (isset($v['callers'])) $v['callers'] = array_values($v['callers']);
                    $jsonResponse['pbxchanges'][] = $v;
                }
        	}
            foreach (array_keys($currentClientState[$objtype]) as $k) {
            	if (!isset($this->_internalState[$objtype][$k])) {
            		// Objeto ha desaparecido
                    unset($currentClientState[$objtype][$k]);
                    $jsonResponse['pbxchanges'][] = array(
                        'objtype'       =>  $objtype,
                        'changetype'    =>  'delete',
                        'key'           =>  $k,
                    );
            	}
            }
        }

        /* dahdi es especial porque no quiero exponer chan2span */
        if (!isset($currentClientState['dahdi']))
            $currentClientState['dahdi'] = array();
        foreach ($this->_internalState['dahdi']['spans'] as $k => $v) {
            
            if (!isset($currentClientState['dahdi'][$k]) || $currentClientState['dahdi'][$k] != $v) {
                $changetype = isset($currentClientState['dahdi'][$k]) ? 'update' : 'create';
                $currentClientState['dahdi'][$k] = $v;
                $v['objtype'] = 'dahdi';
                $v['changetype'] = $changetype;
                $v['span'] = $k;
                if (isset($v['active'])) $v['active'] = array_values($v['active']);
                $chanlist = array();
                foreach (array_keys($v['chan']) as $ch) {
                	$chanlist[] = array(
                        'chan'      =>  $ch,
                        'Alarm'     =>  $v['chan'][$ch]['Alarm'],
                        'active'    =>  array_values($v['chan'][$ch]['active'])
                    );
                }
                $v['chan'] = $chanlist;
                $jsonResponse['pbxchanges'][] = $v;
            }
        }
        foreach (array_keys($currentClientState['dahdi']) as $k) {
            if (!isset($this->_internalState['dahdi']['spans'][$k])) {
                // Objeto ha desaparecido
                unset($currentClientState['dahdi'][$k]);
                //$jsonResponse['delete']['dahdi'][] = $k;
                $jsonResponse['pbxchanges'][] = array(
                    'objtype'       =>  'dahdi',
                    'changetype'    =>  'delete',
                    'key'           =>  $k,
                );
            }
        }
        $jsonResponse['timestamp'] = time();
        $this->_bModified = FALSE;
        return TRUE;
    }

    private function _buildInternalState()
    {
    	$this->_loadStaticDataFromDatabase();
        $this->_loadAreaAssignments();
        $this->_updateStatusFromAsterisk();
        $this->_bModified = TRUE;
    }
    
    /* Este procedimiento intenta cargar la información estática sobre los 
     * elementos monitoreados, como el hecho de que existen, desde la base de
     * datos. Sólo los elementos que consten en la estructura así formada serán
     * objeto de actualización a través de los eventos de AMI.
     * 
     * Esta implementación lee desde la base de datos de FreePBX */
    private function _loadStaticDataFromDatabase()
    {

    $pDB = new paloDB($arrConf['issabel_dsn']['acl']);
    $pACL = new paloACL($pDB);
    $user = $_SESSION['issabel_user'];

        // Recoger todas las extensiones, con todas las tecnologías
		//VOIPIRAN
		/*
        if ($user !== "admin") {
            $recordset = $this->_db->fetchTable(
            "SELECT devices.dial AS channel, devices.tech AS tech, devices.id AS extension, devices.description AS description
            FROM devices
            INNER JOIN pymecall_panel_acl ON devices.id = pymecall_panel_acl.extension WHERE pymecall_panel_acl.user = '$user'
            ORDER BY CAST(devices.id AS SIGNED)",
            TRUE
        );
        }
        else {
        	$recordset = $this->_db->fetchTable(
                "SELECT dial AS channel, tech, id AS extension, description FROM devices ORDER BY CAST(extension AS SIGNED)",
                TRUE);
        }
		*/
		        $recordset = $this->_db->fetchTable(
                "SELECT dial AS channel, tech, id AS extension, description FROM devices ORDER BY CAST(extension AS SIGNED)",
                TRUE);

        if (is_array($recordset)) foreach ($recordset as $tupla) {
        	$phonestate = array(
                'channel'           =>  $tupla['channel'],
                'tech'              =>  $tupla['tech'],
                'extension'         =>  $tupla['extension'],
                'description'       =>  $tupla['description'],
                'current_area'      =>  'Extension',    // <-- puede cambiar en _loadAreaAssignments()

                'mailbox'           =>  $tupla['extension'].'@default',
                'UrgMessages'       =>  0,
                'NewMessages'       =>  0,
                'OldMessages'       =>  0,

                'ip'                =>  NULL,
                'registered'        =>  FALSE,
                'active'           =>  array(),
            );
            
            // Leer estado actual del voicemail
            $r = $this->_ami->MailboxCount($phonestate['mailbox'], $this->_actionid);
            if ($r['Response'] == 'Success') {
            	foreach (array('UrgMessages', 'NewMessages', 'OldMessages') as $k)
                    if (isset($r[$k])) $phonestate[$k] = (int)$r[$k];
            }
            $this->_internalState['phones'][$tupla['channel']] = $phonestate;
        }
        
        // Leer y clasificar todas las colas conocidas
		//VOIPIRAN
/*
        if ($user !== "admin") {
            $recordset = $this->_db->fetchTable(
            "SELECT queues_config.extension AS extension, queues_config.descr AS description
            FROM queues_config
            INNER JOIN pymecall_panel_acl ON queues_config.extension = pymecall_panel_acl.queue WHERE pymecall_panel_acl.user = '$user'
            ORDER BY CAST(queues_config.extension AS SIGNED)",
            TRUE
        );
        }
        else {
            $recordset = $this->_db->fetchTable(
                "SELECT extension, descr AS description FROM queues_config ORDER BY extension",
                TRUE);
        }
		*/
		  $recordset = $this->_db->fetchTable(
          "SELECT extension, descr AS description FROM queues_config ORDER BY extension",
          TRUE);

        if (is_array($recordset)) foreach ($recordset as $tupla) {
        	$this->_internalState['queues'][$tupla['extension']] = array(
                'extension'     =>  $tupla['extension'],
                'description'   =>  $tupla['description'],
                'members'       =>  array(),
                'callers'       =>  array(),
            );
        }
        
        // Leer y clasificar todas las conferencias Meetme conocidas
        $recordset = $this->_db->fetchTable(
            'SELECT exten AS extension, description FROM meetme ORDER BY exten',
            TRUE);
        if (is_array($recordset)) foreach ($recordset as $tupla) {
            $this->_internalState['conferences'][$tupla['extension']] = array(
                'extension'     =>  $tupla['extension'],
                'description'   =>  $tupla['description'],
                'callers'       =>  array(),
            );
        }
        
        // Generar todas las extensiones disponibles para parqueo de llamadas
        $parkpos = NULL; $numslots = 0;
        $tupla = $this->_db->getFirstRowQuery('SHOW TABLES LIKE "parkplus"');
        if (count($tupla)) {
        	// FreePBX 2.11 o superior
            $tupla = $this->_db->getFirstRowQuery(
                'SELECT parkpos, numslots FROM parkplus ORDER BY id LIMIT 0,1',
                TRUE);
            if (is_array($tupla)) {
            	$parkpos = $tupla['parkpos'];
                $numslots = (int)$tupla['numslots'];
            }
        } else {
            // FreePBX 2.8
            $recordset = $this->_db->fetchTable(
                'SELECT keyword, data FROM parkinglot',
                TRUE);
            if (is_array($recordset)) foreach ($recordset as $tupla) {
            	if ($tupla['keyword'] == 'parkext') $parkpos = $tupla['data'] + 1;
                if ($tupla['keyword'] == 'numslots') $numslots = (int)$tupla['data'];
            }
        }
        if (!is_null($parkpos)) for ($i = 0; $i < $numslots; $i++) {
            $k = $parkpos + $i;
        	$this->_internalState['parkinglots'][$k] = array(
                'extension'     =>  $k,
                'Channel'       =>  NULL,
                'Since'         =>  NULL,
                'Timeout'       =>  NULL,
            );
        }
        
        // Leer y clasificar todas las troncales distintas de DAHDI
        // FIXME: trunks.disabled está en 'off' para troncales desactivadas
        $trunks = getTrunks($this->_db);
        if (is_array($trunks)) foreach ($trunks as $t) {
        	$trunk = $t[1];
            
            if (strpos($trunk, 'DAHDI/') !== 0) {
                $regs = NULL;
                $tech = '';
                if (preg_match('|^(\w+)/|', $trunk, $regs))
                    $tech = $regs[1];
                $this->_internalState['iptrunks'][$trunk] = array(
                    'channel'           =>  $trunk,
                    'tech'              =>  $tech,

                    'ip'            =>  NULL,
                    'registered'    =>  FALSE,
                    'active'       =>  array(),
                );
            }
        }
        
        /* Mostrar todos los canales DAHDI disponibles. Para cada canal, se 
         * clasificará dentro de su span correspondiente. */
        $r = $this->_ami->Command('dahdi show channels');
        if (isset($r['data'])) foreach (explode("\n", $r['data']) as $s) {
        	$regs = NULL;
            if (preg_match('/^\s*(\d+)/', $s, $regs)) {
            	$chan = (int)$regs[1];
                $r = $this->_ami->Command('dahdi show channel '.$chan);
                if (isset($r['data'])) foreach (explode("\n", $r['data']) as $l) {
                    if (preg_match('/^Span: (\d+)/', $l, $regs)) {
                    	$span = (int)$regs[1];
                        $this->_internalState['dahdi']['chan2span'][$chan] = $span;
                        if (!isset($this->_internalState['dahdi']['spans'][$span])) {
                            $this->_internalState['dahdi']['spans'][$span] = array(
                                'active'    => array(), // Conexiones conectadas no clasificadas en canal
                                'chan'      => array(), // Conexión conectada al canal específico
                            );
                        }
                        $this->_internalState['dahdi']['spans'][$span]['chan'][$chan] = array(
                            'active'    =>  array(),
                            'Alarm'     =>  'No Alarm'
                        );
                    }
                }
            }
        }
    }
    
    private function _loadAreaAssignments()
    {
    	/* Se carga el área a la cual va cada extensión. Debe notarse que debido
         * a compatibilidad con la implementación anterior, la asociación es
         * con la EXTENSIÓN de la cuenta, no con la cuenta misma. Esto debe de
         * modificarse cuando se porte esto a Issabel 3. */
        $sqlAreaExt =
            'SELECT item_box.id_device, area.name FROM item_box, area '.
            'WHERE item_box.id_area = area.id';
        $recordset = $this->_dbConfig->fetchTable($sqlAreaExt, TRUE);
        $map = array();
        if (is_array($recordset)) foreach ($recordset as $tupla) {
        	$map[$tupla['id_device']] = $tupla['name'];
        }
        
        // Cambiar de área las extensiones según sea necesario
        foreach (array_keys($this->_internalState['phones']) as $k) {
        	if (isset($this->_internalState['phones'][$k]['extension']) &&
                isset($map[$this->_internalState['phones'][$k]['extension']])) {
                $this->_internalState['phones'][$k]['current_area'] =
                    $map[$this->_internalState['phones'][$k]['extension']];
            }
        }
        
        // TODO: cargar colores y dimensiones de las áreas
    }
    
    private function _updateStatusFromAsterisk()
    {
        $this->_enumsInProgress = 0;
        // Actualiza información de extensions y troncales SIP
        $r = $this->_ami->SIPPeers($this->_actionid);
        if ($r['Response'] == 'Success') $this->_enumsInProgress++;
        
        // Actualiza información de extensions y troncales PJSIP
        $r = $this->_ami->PJSIPShowEndpoints($this->_actionid);
        if ($r['Response'] == 'Success') $this->_enumsInProgress++;

        // Actualiza información de extensions y troncales IAX2
        $r = $this->_ami->IAXpeerlist($this->_actionid);
        if ($r['Response'] == 'Success') $this->_enumsInProgress++;

        // Obtener la información de todos los canales activos
        $r = $this->_ami->Status(NULL, $this->_actionid);        
        if ($r['Response'] == 'Success') $this->_enumsInProgress++;

        // Obtener la información de todas las colas activas
        $r = $this->_ami->QueueStatus($this->_actionid);        
        if ($r['Response'] == 'Success') $this->_enumsInProgress++;

        // Obtener la información de todas las conferencias activas
        $r = $this->_ami->MeetmeList(NULL, $this->_actionid);        
        if ($r['Response'] == 'Success') $this->_enumsInProgress++;
 
        // Obtener la información de todas las llamadas parqueadas
        $r = $this->_ami->ParkedCalls($this->_actionid);
        if ($r['Response'] == 'Success') $this->_enumsInProgress++;
                
        // Obtener la información de todos los canales DAHDI
        $r = $this->_ami->DAHDIShowChannels(NULL, $this->_actionid);        
        if ($r['Response'] == 'Success') $this->_enumsInProgress++;
        while ($this->_enumsInProgress > 0) $this->waitForEvents();
    }
    
    /**************************************************************************/
    
    // Procedimiento que intenta extraer la troncal asociada al canal indicado
    private function _chan2trunk($ch)
    {
    	$regs = NULL;
        if (preg_match('|^((.+/.+?)(@\S+)?-[[:xdigit:]]+)(<ZOMBIE>)?(<MASQ>)?(;\d+)?$|', $ch, $regs))
            return $regs[2];
        else return $ch;
    }
    
    // Procedimiento que intenta devolver un canal sin <ZOMBIE>
    private function _realchan($ch)
    {
        $regs = NULL;
        if (preg_match('|^((.+/.+?)(@\S+)?-[[:xdigit:]]+)(<ZOMBIE>)?(<MASQ>)?(;\d+)?$|', $ch, $regs))
            return $regs[1];
        else return $ch;
    }
    
    // Evento que contiene información sobre enumeración de PJSIP Endpoints
    function msg_EndpointList($sEvent, $params, $sServer, $iPort) {
        $this->_dumpevent($sEvent, $params);
        if ($this->_enumsInProgress <= 0 || $params['ActionID'] != $this->_actionid) return;
        /*
            Event: EndpointList
            ObjectType: endpoint
            ObjectName: 212
            Transport: transport-udp
            Aor: 212
            Auths: auth212
            OutboundAuths:
            Contacts: 212/sip:212@192.10.10.10:21749;ob,
            DeviceState: Not in use
            ActiveChannels:
         */
        $channel = 'PJSIP/'.$params['ObjectName'];
        if (isset($this->_internalState['phones'][$channel])) {
        	$objinfo =& $this->_internalState['phones'][$channel];
            $objinfo['ip'] = NULL;
            if ($params['DeviceState'] === 'Ringing' || $objinfo['registered'] = (strpos($params['DeviceState'], 'use') > 0)){
                $objinfo['registered'] = true;
            }
            if ($objinfo['registered']) {
                $partes = preg_split("/@/",$params['Contacts']);
                $pertes = preg_split("/:/",$partes[1]);
                $ip = $pertes[0];
                $objinfo['ip'] = $ip;
            }

        } elseif (isset($this->_internalState['iptrunks'][$channel])) {
            $objinfo =& $this->_internalState['iptrunks'][$channel];
            $objinfo['ip'] = NULL;
            $objinfo['registered'] = (strpos($params['DeviceState'], 'use') > 0);

            if ($objinfo['registered']) {

            $partes = preg_split("/:/",$params['Contacts']);
                $ip = $partes[1];
                $objinfo['ip'] = $ip;
            
            }
        }
    } 

    // Evento que contiene información sobre enumeración de SIPPeers, IAXpeerlist
    function msg_PeerEntry($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        if ($this->_enumsInProgress <= 0 || $params['ActionID'] != $this->_actionid) return;
    	
        if ($params['Channeltype'] == 'IAX') $params['Channeltype'] = 'IAX2';
        $channel = $params['Channeltype'].'/'.$params['ObjectName'];
        if (isset($this->_internalState['phones'][$channel])) {
        	$objinfo =& $this->_internalState['phones'][$channel];
            $objinfo['ip'] = NULL;
            $objinfo['registered'] = (strpos($params['Status'], 'OK') === 0);
            if ($objinfo['registered']) {
            	$objinfo['ip'] = $params['IPaddress'];
            }
        } elseif (isset($this->_internalState['iptrunks'][$channel])) {
            $objinfo =& $this->_internalState['iptrunks'][$channel];
            $objinfo['ip'] = NULL;
            $objinfo['registered'] = (strpos($params['Status'], 'OK') === 0);
            if ($objinfo['registered']) {
                $objinfo['ip'] = $params['IPaddress'];
            }
        }
    }

    // Evento que termina la enumeración de PJSIPShowEndpoints
    function msg_EndpointListComplete($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

    	if ($this->_enumsInProgress <= 0 || (isset($params['ActionID']) && $params['ActionID'] != $this->_actionid)) return;

        $this->_enumsInProgress--;
    }
    
    // Evento que termina la enumeración de SIPPeers, IAXpeerlist
    function msg_PeerlistComplete($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

    	if ($this->_enumsInProgress <= 0 || (isset($params['ActionID']) && $params['ActionID'] != $this->_actionid)) return;

        $this->_enumsInProgress--;
    }
    
    private function _filterUnknown(&$params, $k)
    {
    	return (isset($params[$k]) && trim($params[$k]) != '' && $params[$k] != '<unknown>')
            ? $params[$k] 
            : NULL;
    }
    
    // Evento que contiene información sobre iteración de Status
    function msg_Status($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        /*
        Event: Status
        Privilege: Call
        Channel: SIP/SIPTVCABLE-0000a74f
        CallerIDNum: 026025006
        CallerIDName: 026025006
        ConnectedLineNum: <unknown>
        ConnectedLineName: <unknown>
        Accountcode: 
        ChannelState: 6
        ChannelStateDesc: Up
        Context: ext-queues
        Extension: 2000
        Priority: 9
        Seconds: 72
        Uniqueid: 1378332655.68099
        ActionID: gato
         */        
        if ($this->_enumsInProgress <= 0 || $params['ActionID'] != $this->_actionid) return;

        // Calcular momento de inicio de la interacción del canal
        $params['Since'] = (isset($params['Seconds'])) ? time() - (int)$params['Seconds'] : NULL;
        if (is_null($params['Since'])) {
            // Lo siguiente toma ventaja de que el Uniqueid es realmente un timestamp
            $a = explode('.', $params['Uniqueid']);
            $params['Since'] = (int)$a[0];
        }
        
        // El estado es de una extensión, de un canal DAHDI, o de un canal SIP 
        $trunkinfo =& $this->_identifyTrunk($params['Channel']);
        
        if (!is_null($trunkinfo)) {
        	$activeinfo = array(
                'Channel'           =>  $params['Channel'],
                'CallerIDNum'       =>  $this->_filterUnknown($params, 'CallerIDNum'),
                'CallerIDName'      =>  $this->_filterUnknown($params, 'CallerIDName'),
                'Since'             =>  $params['Since'],
                'BridgedChannel'    =>  $this->_filterUnknown($params, 'BridgedChannel'),
                'ConnectedLineNum'  =>  $this->_filterUnknown($params, 'ConnectedLineNum'),
                'ConnectedLineName' =>  $this->_filterUnknown($params, 'ConnectedLineName'),
                'ChannelStateDesc'  =>  $this->_filterUnknown($params, 'ChannelStateDesc'),
                
                'Context'           =>  $this->_filterUnknown($params, 'Context'),
                'Extension'         =>  $this->_filterUnknown($params, 'Extension'),
                'Priority'          =>  $this->_filterUnknown($params, 'Priority'),

                // TODO: Por ahora no se llena esto aquí
                'Application'       =>  NULL,
                'AppData'           =>  NULL,
            );
            $trunkinfo['active'][$params['Channel']] = $activeinfo;
        }
        
        // Se verifica si la extensión indicada es una cola
        if (isset($params['Extension']) && isset($this->_internalState['queues'][$params['Extension']])) {
        	$this->_internalState['queues'][$params['Extension']]['callers'][$params['Channel']] = array(
                'Channel'       =>  $params['Channel'],
                'CallerIDNum'       =>  $this->_filterUnknown($params, 'CallerIDNum'),
                'CallerIDName'      =>  $this->_filterUnknown($params, 'CallerIDName'),
                'Since'         =>  $params['Since'],
                
                // Esta información se completa en msg_QueueEntry
                'Position'      =>  NULL,
                'QueueSince'    =>  NULL,
            );
        }
    }

    private function & _identifyTrunk($channel)
    {
        $trunk = $this->_chan2trunk($channel);
        $trunkinfo = NULL;
        $regs = NULL;
        if (isset($this->_internalState['iptrunks'][$trunk]))
            $trunkinfo =& $this->_internalState['iptrunks'][$trunk];
        elseif (isset($this->_internalState['phones'][$trunk]))
            $trunkinfo =& $this->_internalState['phones'][$trunk];
        elseif (preg_match('|^DAHDI/(i?)(\d+)|', $trunk, $regs)) {
            if ($regs[1] == 'i') {
                // Canal DAHDI digital, por ahora sólo se puede identificar span
                $span = (int)$regs[2];
                if (isset($this->_internalState['dahdi']['spans'][$span]))
                    $trunkinfo =& $this->_internalState['dahdi']['spans'][$span];
                
                /* Si el canal ha sido previamente clasificado, se busca debajo 
                 * de cada canal. */
                foreach (array_keys($trunkinfo['chan']) as $chan) {
                	if (isset($trunkinfo['chan'][$chan]['active'][$channel])) {
                		$trunkinfo =& $trunkinfo['chan'][$chan];
                        break;
                	}
                }
            } else {
                // Canal DAHDI analógico
                $chan = (int)$regs[2];
                if (isset($this->_internalState['dahdi']['chan2span'][$chan])) {
                    $span = $this->_internalState['dahdi']['chan2span'][$chan];
                    if (isset($this->_internalState['dahdi']['spans'][$span]))
                        $trunkinfo =& $this->_internalState['dahdi']['spans'][$span]['chan'][$chan];
                }
            }
        }
        
        return $trunkinfo;
    }

    // Evento que termina la enumeración de Status
    function msg_StatusComplete($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        if ($this->_enumsInProgress <= 0 || (isset($params['ActionID']) && $params['ActionID'] != $this->_actionid)) return;

        $this->_enumsInProgress--;
    }

    // Evento que contiene información sobre iteración de QueueStatus
    function msg_QueueMember($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        if ($this->_enumsInProgress <= 0 || $params['ActionID'] != $this->_actionid) return;

        /*
        Event: QueueMember
        Queue: 8001
        Name: Agent/9000
        Location: Agent/9000
        StateInterface: Agent/9000
        Membership: static
        Penalty: 0
        CallsTaken: 0
        LastCall: 0
        Status: 5
        Paused: 0
        ActionID: gato
         */
        if (isset($this->_internalState['queues'][$params['Queue']])) {
            $this->_internalState['queues'][$params['Queue']]['members'][] = $params['Location'];
        } 
    }

    // Evento que contiene información sobre iteración de QueueStatus
    function msg_QueueEntry($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        if ($this->_enumsInProgress <= 0 || $params['ActionID'] != $this->_actionid) return;

        /*
        Event: QueueEntry
        Queue: 8000
        Position: 1
        Channel: SIP/1064-00000000
        Uniqueid: 1378401225.0
        CallerIDNum: 1064
        CallerIDName: Alex
        ConnectedLineNum: unknown
        ConnectedLineName: unknown
        Wait: 40
         */
        if (isset($this->_internalState['queues'][$params['Queue']])) {
            if (isset($this->_internalState['queues'][$params['Queue']]['callers'][$params['Channel']])) {
            	$c =& $this->_internalState['queues'][$params['Queue']]['callers'][$params['Channel']];
                $c['Position'] = (int)$params['Position'];
                $c['QueueSince'] = time() - (int)$params['Wait'];
            }
        } 
    }

    function msg_QueueMemberStatus($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        if (isset($this->_internalState['queues'][$params['Queue']])) {
            $interface = $params['Interface'];
            $existingIndex = null;


            // Buscar si ya existe un elemento con la misma clave 'Interface'
            foreach ($this->_internalState['queues'][$params['Queue']]['memberRefresh'] as $index => $member) {
                if ($member['Interface'] === $interface) {
                    $existingIndex = $index;
                    break;
                }
            }

            if ($existingIndex !== null) {
                // Actualizar el elemento existente
                $this->_internalState['queues'][$params['Queue']]['memberRefresh'][$existingIndex] = [
                    'Interface' => $params['Interface'],
                    'Status' => $params['Status'],
                    'Paused' => $params['Paused'],
                    'MemberName' => $params['MemberName']
                ];
            } else {
                // Agregar un nuevo elemento si no existe
                $this->_internalState['queues'][$params['Queue']]['memberRefresh'][] = [
                    'Interface' => $params['Interface'],
                    'Status' => $params['Status'],
                    'Paused' => $params['Paused'],
                    'MemberName' => $params['MemberName']
                ];
            }
        /*
        $fileLocation = '/var/www/html/modules/control_panel/' . 'JsonParams.json'; // Reemplaza 'archivo_nombre.json' con el nombre de archivo deseado en formato JSON
        $jsonString = json_encode($params, JSON_PRETTY_PRINT) . "\n";
        file_put_contents($fileLocation, $jsonString, FILE_APPEND);
        */
            $this->_bModified = TRUE;
        }
    }

    // Evento que termina la enumeración de QueueStatus
    function msg_QueueStatusComplete($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        if ($this->_enumsInProgress <= 0 || (isset($params['ActionID']) && $params['ActionID'] != $this->_actionid)) return;

        $this->_enumsInProgress--;
    }

    function msg_MeetmeList($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        if ($this->_enumsInProgress <= 0 || $params['ActionID'] != $this->_actionid) return;

        /*
        Event: MeetmeList
        Conference: 8472
        UserNumber: 1
        CallerIDNum: 1064
        CallerIDName: Alex
        ConnectedLineNum: <unknown>
        ConnectedLineName: <no name>
        Channel: SIP/1064-00000001
        Admin: No
        Role: Talk and listen
        MarkedUser: No
        Muted: No
        Talking: Not monitored
         */
    	if (isset($this->_internalState['conferences'][$params['Conference']])) {
    		$caller = array(
                'Channel'   =>  $params['Channel'],
                'ConfSince' =>  NULL,   // No hay manera de saber desde cuándo se participa
            );
            
            // Intento de averiguar el inicio de la participación de la conferencia
            $trunkinfo =& $this->_identifyTrunk($params['Channel']);
            if (!is_null($trunkinfo)) {
            	if (isset($trunkinfo['active'][$params['Channel']])) {
                    $caller['ConfSince'] = $trunkinfo['active'][$params['Channel']]['Since'];
                }
            }
            $this->_internalState['conferences'][$params['Conference']]['callers'][$params['Channel']] = $caller;
    	}
    }

    // Evento que termina la enumeración de MeetmeList
    function msg_MeetmeListComplete($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        if ($this->_enumsInProgress <= 0 || (isset($params['ActionID']) && $params['ActionID'] != $this->_actionid)) return;

        $this->_enumsInProgress--;
    }

    function msg_DAHDIShowChannels($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        if ($this->_enumsInProgress <= 0 || $params['ActionID'] != $this->_actionid) return;

        $chan = $params['DAHDIChannel'];
        if (isset($this->_internalState['dahdi']['chan2span'][$chan])) {
            $span = $this->_internalState['dahdi']['chan2span'][$chan];
            $this->_internalState['dahdi']['spans'][$span]['chan'][$chan]['Alarm'] = $params['Alarm'];
        }
    }

    function msg_Alarm($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        $chan = $params['Channel'];
        if (isset($this->_internalState['dahdi']['chan2span'][$chan])) {
            $span = $this->_internalState['dahdi']['chan2span'][$chan];
            $this->_internalState['dahdi']['spans'][$span]['chan'][$chan]['Alarm'] = $params['Alarm'];
            $this->_bModified = TRUE;
        }
    }

    function msg_AlarmClear($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        $chan = $params['Channel'];
        if (isset($this->_internalState['dahdi']['chan2span'][$chan])) {
            $span = $this->_internalState['dahdi']['chan2span'][$chan];
            $this->_internalState['dahdi']['spans'][$span]['chan'][$chan]['Alarm'] = 'No Alarm';
            $this->_bModified = TRUE;
        }
    }

    // Evento que termina la enumeración de DAHDIShowChannels
    function msg_DAHDIShowChannelsComplete($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        if ($this->_enumsInProgress <= 0 || (isset($params['ActionID']) && $params['ActionID'] != $this->_actionid)) return;

        $this->_enumsInProgress--;
    }
    
    // Evento que avisa por qué span y canal-B pasa una llamada DAHDI
    function msg_DAHDIChannel($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

    /*
    Llamada analógica:

    DEBUG: paloControlPanelStatus::msg_Default
    retraso => 0.0089941024780273
    dahdichannel: => Array
    (
        [Event] => DAHDIChannel
        [Privilege] => call,all
        [Channel] => DAHDI/5-1
        [Uniqueid] => 1378934257.52
        [DAHDISpan] => 1
        [DAHDIChannel] => 5
        [local_timestamp_received] => 1378934257.6046
    )

    Llamada digital:

    2013-10-04 17:15:40: DEBUG: paloControlPanelStatus::_dumpevent
    retraso => 0.69087481498718
    dahdichannel: => Array
    (
        [Event] => DAHDIChannel
        [Privilege] => call,all
        [Channel] => DAHDI/i2/304-9
        [Uniqueid] => 1380924939.34
        [DAHDISpan] => 2
        [DAHDIChannel] => 32
        [local_timestamp_received] => 1380924939.7722
    )
     */

        $trunkinfo =& $this->_identifyTrunk($params['Channel']);
        if (is_null($trunkinfo)) {
            file_put_contents('/tmp/debug-control_panel-events.txt',
                "Failed to identify trunk for channel {$params['Channel']}".
                print_r($trunkinfo, 1), FILE_APPEND);
            return;
        }
     
        /* Un evento DAHDIChannel puede tanto mandar un canal a un B-channel 
         * específico, como indicar que el canal queda sin asociación con un
         * B-channel. En este momento se espera que el canal esté debajo de
         * la lista 'active' del trunk. */
        if (!isset($trunkinfo['active'][$params['Channel']])) {
        	file_put_contents('/tmp/debug-control_panel-events.txt',
                "Channel {$params['Channel']} not found under trunkinfo: ".
                print_r($trunkinfo, 1), FILE_APPEND);
            return;
        }
        $chaninfo = $trunkinfo['active'][$params['Channel']];
        unset($trunkinfo['active'][$params['Channel']]);
        unset($trunkinfo);
        $this->_bModified = TRUE;
        
        /* Para un canal, DAHDIChannel puede ser un número de canal, -1 o pseudo */
        $chan = $params['DAHDIChannel'];
        $span = $params['DAHDISpan'];
        if (ctype_digit($chan) && isset($this->_internalState['dahdi']['chan2span'][$chan])) {
            if ($span != $this->_internalState['dahdi']['chan2span'][$chan]) {
                file_put_contents('/tmp/debug-control_panel-events.txt',
                    "Channel placed at unexpected span, going by map: event: ".
                        print_r($params, 1)."\ndahdi: ".print_r($this->_internalState['dahdi'], 1),
                    FILE_APPEND);
            }
        	$span = $this->_internalState['dahdi']['chan2span'][$chan];            
            $trunkinfo =& $this->_internalState['dahdi']['spans'][$span]['chan'][$chan];
        } elseif (isset($this->_internalState['dahdi']['spans'][$span])) {
        	$trunkinfo =& $this->_internalState['dahdi']['spans'][$span];
        } else {
            file_put_contents('/tmp/debug-control_panel-events.txt',
                "Unrecognized span (channel lost), event: ".
                    print_r($params, 1)."\ndahdi: ".print_r($this->_internalState['dahdi'], 1),
                FILE_APPEND);
        	return;
        }

        $trunkinfo['active'][$params['Channel']] = $chaninfo;
    }
    
    /* ATENCIÓN: este evento se recibe tanto al enumerar con ParkedCalls, como 
     * independientemente cuando una llamada se parquea. Para la enumeración,
     * el ActionID estará seteado.
     */
    function msg_ParkedCall($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        /*
        Event: ParkedCall
        Parkinglot: default
        Exten: 71
        Channel: SIP/1064-00000004
        From: IAX2/1099-2234
        Timeout: 41
        Duration: 4
        CallerIDNum: 1064
        CallerIDName: Alex
        ConnectedLineNum: 
        ConnectedLineName: 
        ActionID: gatito
         */
        /*
        Event: ParkedCall
        Privilege: call,all
        Exten: 71
        Channel: SIP/1064-00000013
        Parkinglot: default
        From: SIP/1065-00000014
        Timeout: 45
        CallerIDNum: 1064
        CallerIDName: Alex
        ConnectedLineNum: 1065
        ConnectedLineName: device
        Uniqueid: 1380209988.23
         */         
        if (isset($this->_internalState['parkinglots'][$params['Exten']])) {
        	$parklot = &$this->_internalState['parkinglots'][$params['Exten']];
            $inicioParqueo = time();
            if (isset($params['Duration'])) $inicioParqueo -= (int)$params['Duration'];
            $parklot['Channel'] = $params['Channel'];
            $parklot['Since'] = $inicioParqueo;
            $parklot['Timeout'] = (int)$params['Timeout'];
            $this->_bModified = TRUE;
        }
    }
    
    // Evento que termina la enumeración de ParkedCalls
    function msg_ParkedCallsComplete($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        if ($this->_enumsInProgress <= 0 || (isset($params['ActionID']) && $params['ActionID'] != $this->_actionid)) return;

        $this->_enumsInProgress--;
    }

    // Evento que indica que una llamada sale de parqueo por timeout
    function msg_ParkedCallTimeOut($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

/*
Event: ParkedCallTimeOut
Privilege: call,all
Exten: 71
Channel: SIP/1064-00000013
Parkinglot: default
CallerIDNum: 1064
CallerIDName: Alex
ConnectedLineNum: 1065
ConnectedLineName: device
UniqueID: 1380209988.23
 */
        
        if (isset($this->_internalState['parkinglots'][$params['Exten']])) {
            $parklot = &$this->_internalState['parkinglots'][$params['Exten']];
            $parklot['Channel'] = NULL;
            $parklot['Since'] = NULL;
            $parklot['Timeout'] = NULL;
            $this->_bModified = TRUE;
        }
    }

    // Evento que indica que una llamada sale de parqueo por colgado remoto
    function msg_ParkedCallGiveUp($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        if (isset($this->_internalState['parkinglots'][$params['Exten']])) {
            $parklot = &$this->_internalState['parkinglots'][$params['Exten']];
            $parklot['Channel'] = NULL;
            $parklot['Since'] = NULL;
            $parklot['Timeout'] = NULL;
            $this->_bModified = TRUE;
        }
    }

    // Evento que indica que una llamada ha sido recogida del parqueo
    function msg_UnParkedCall($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        if (isset($this->_internalState['parkinglots'][$params['Exten']])) {
            $parklot = &$this->_internalState['parkinglots'][$params['Exten']];
            $parklot['Channel'] = NULL;
            $parklot['Since'] = NULL;
            $parklot['Timeout'] = NULL;
            $this->_bModified = TRUE;
        }
    }

    // Newchannel anuncia la creación de un nuevo canal
    function msg_Newchannel($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

/*
    [Event] => Newchannel
    [Privilege] => call,all
    [Channel] => SIP/1064-00000006
    [ChannelState] => 0
    [ChannelStateDesc] => Down
    [CallerIDNum] => 1064
    [CallerIDName] => device
    [AccountCode] => 
    [Exten] => 1099
    [Context] => from-internal
    [Uniqueid] => 1378845796.24
    [local_timestamp_received] => 1378845796.5759
 */
    	$trunkinfo =& $this->_identifyTrunk($params['Channel']);
        if (is_null($trunkinfo)) return;

        $activeinfo = array(
            'Channel'           =>  $params['Channel'],
            'CallerIDNum'       =>  $this->_filterUnknown($params, 'CallerIDNum'),
            'CallerIDName'      =>  $this->_filterUnknown($params, 'CallerIDName'),
            'Since'             =>  time(),
            'BridgedChannel'    =>  NULL,
            'ConnectedLineNum'  =>  NULL,
            'ConnectedLineName' =>  NULL,
            'ChannelStateDesc'  =>  $params['ChannelStateDesc'],

            // Para llenar esto se requiere de Newexten
            'Context'           =>  NULL,
            'Extension'         =>  NULL,
            'Priority'          =>  NULL,
            'Application'       =>  NULL,
            'AppData'           =>  NULL,
        );
        $trunkinfo['active'][$params['Channel']] = $activeinfo;
        $this->_bModified = TRUE;
    }
    
    // Newexten anuncia un avance en la posición de la extensión en el contexto
    function msg_Newexten($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

/*
newexten: => Array
(
    [Event] => Newexten
    [Privilege] => dialplan,all
    [Channel] => SIP/1064-00000001
    [Context] => from-internal
    [Extension] => 1234
    [Priority] => 1
    [Application] => Playback
    [AppData] => demo-congrats
    [Uniqueid] => 1380038460.1
    [local_timestamp_received] => 1380038460.3223
)
 */
     	/* Para reducir las modificaciones al navegador, sólo se revisarán los
         * cambios que contienen una extensión numérica */
        if (!preg_match('/^[[:digit:]#*]+$/', $params['Extension'])) return;
         
        $trunkinfo =& $this->_identifyTrunk($params['Channel']);
        if (is_null($trunkinfo)) return;
        if (isset($trunkinfo['active'][$params['Channel']])) {
            $chaninfo =& $trunkinfo['active'][$params['Channel']];

            /* Con Asterisk16 se reciben eventos de Canal down con Exten configurado, con lo que se estropea
               la vista de callerid o connectedlinenum porque se asume llamado saliente al header Exten,
               por lo tanto ignoramos configurar Exten en el state Down  */
            if(isset($params['ChannelStateDesc'])) {
                if($params['ChannelStateDesc']<>'Down') {
                    foreach (array('Extension', /*'Context', 'Priority', 'Application', 'AppData'*/) as $p) {
                        if (isset($params[$p])) {
                            $chaninfo[$p] = $this->_filterUnknown($params, $p);
                        }
                    }
                }
            } else {
                /* Los campos a excepción de Extension se ignoran porque los cambios
                 * fluyen demasiado rápido y generan demasiados eventos. */
                foreach (array('Extension', /*'Context', 'Priority', 'Application', 'AppData'*/) as $p) {
                    if (isset($params[$p])) {
                        $chaninfo[$p] = $this->_filterUnknown($params, $p);
                    }
                }
            }
            $this->_bModified = TRUE;
        }
    }
    
    // NewCallerid anuncia que se tiene actualización de CallerID para el canal
    function msg_NewCallerid($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

/*
    [Event] => NewCallerid
    [Privilege] => call,all
    [Channel] => IAX2/1099-4615
    [CallerIDNum] => 1099
    [CallerIDName] => 
    [Uniqueid] => 1378845796.25
    [CID-CallingPres] => 0 (Presentation Allowed, Not Screened)
    [local_timestamp_received] => 1378845796.7249
 */
        $trunkinfo =& $this->_identifyTrunk($params['Channel']);
        if (is_null($trunkinfo)) return;
        
        if (isset($trunkinfo['active'][$params['Channel']])) {
            $chaninfo =& $trunkinfo['active'][$params['Channel']];
            foreach (array('CallerIDNum', 'CallerIDName') as $p)
                if (isset($params[$p])) $chaninfo[$p] = $this->_filterUnknown($params, $p);
            $this->_bModified = TRUE;
    	}
    }

    // Cambio de estado del canal, puede que tenga Connected*
    function msg_Newstate($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

/*
    [Event] => Newstate
    [Privilege] => call,all
    [Channel] => IAX2/1099-4615
    [ChannelState] => 5
    [ChannelStateDesc] => Ringing
    [CallerIDNum] => 1099
    [CallerIDName] => 
    [ConnectedLineNum] => 1064
    [ConnectedLineName] => Alex
    [Uniqueid] => 1378845796.25
    [local_timestamp_received] => 1378845796.7249
 */
        $trunkinfo =& $this->_identifyTrunk($params['Channel']);
        if (is_null($trunkinfo)) return;

        if (isset($trunkinfo['active'][$params['Channel']])) {
            $chaninfo =& $trunkinfo['active'][$params['Channel']];
            foreach (array('CallerIDNum', 'CallerIDName', 'ConnectedLineNum',
                'ConnectedLineName', 'ChannelStateDesc') as $p)
                if (isset($params[$p])) $chaninfo[$p] = $this->_filterUnknown($params, $p);
            $this->_bModified = TRUE;
        }
    }

    function msg_BridgeDestroy($sEvent, $params, $sServer, $iPort) {
        unset($this->_bridges[$params['BridgeUniqueid']]);
    }

    function msg_BridgeEnter($sEvent, $params, $sServer, $iPort) {
        $numchannels = $params['BridgeNumChannels'];
        $this->_bridges[$params['BridgeUniqueid']]['Channel'.$numchannels]=$params['Channel'];
        $this->_bridges[$params['BridgeUniqueid']]['Uniqueid'.$numchannels]=$params['Uniqueid'];
        $this->_bridges[$params['BridgeUniqueid']]['CallerID'.$numchannels]=$params['ConnectedLineNum'];

        if($numchannels == 2) {
            $this->_bridges[$params['BridgeUniqueid']]['Event']='Bridge';
            $this->_bridges[$params['BridgeUniqueid']]['Bridgesate']='Link';
            $this->msg_Bridge('Bridge', $this->_bridges[$params['BridgeUniqueid']], $sServer, $iPort);
        }
    }

    function msg_Bridge($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

/*
    [Event] => Bridge
    [Privilege] => call,all
    [Bridgestate] => Link
    [Bridgetype] => core
    [Channel1] => SIP/1064-00000006
    [Channel2] => IAX2/1099-4615
    [Uniqueid1] => 1378845796.24
    [Uniqueid2] => 1378845796.25
    [CallerID1] => 1064
    [CallerID2] => 1099
    [local_timestamp_received] => 1378845803.547
 */
        for ($i = 1; $i <= 2; $i++) {
        	$ch1 = 'Channel'.$i;
            $ch2 = 'Channel'.(3 - $i);
            $trunkinfo =& $this->_identifyTrunk($params[$ch1]);
            if (!is_null($trunkinfo)) {
                if (isset($trunkinfo['active'][$params[$ch1]])) {
                    $chaninfo =& $trunkinfo['active'][$params[$ch1]];
                    if ($params['Bridgestate'] == 'Link') {
                        $chaninfo['BridgedChannel'] = $params[$ch2];
                        $chaninfo['ConnectedLineNum'] = $params['CallerID1'];
                    } elseif ($params['Bridgestate'] == 'Unlink') {
                        $chaninfo['BridgedChannel'] = NULL;
                    }
                    $this->_bModified = TRUE;
                }
            }
        }
    }

    function msg_Hangup($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

/*
    [Event] => Hangup
    [Privilege] => call,all
    [Channel] => SIP/1064-00000006
    [Uniqueid] => 1378845796.24
    [CallerIDNum] => 1064
    [CallerIDName] => Alex
    [ConnectedLineNum] => <unknown>
    [ConnectedLineName] => <unknown>
    [AccountCode] => 
    [Cause] => 16
    [Cause-txt] => Normal Clearing
    [local_timestamp_received] => 1378848238.0673
 */
        $trunkinfo =& $this->_identifyTrunk($params['Channel']);
        if (is_null($trunkinfo)) {
            return;
        }
        
        $realchan = $this->_realchan($params['Channel']);
        if (isset($trunkinfo['active'][$realchan])) {
            unset($trunkinfo['active'][$realchan]);
            $this->_bModified = TRUE;
        } else {
        }
        
        // Verificar que se quite canal de colas y conferencias
        foreach (array_keys($this->_internalState['queues']) as $queue) {
            if (isset($this->_internalState['queues'][$queue]['callers'][$realchan])) {
                unset($this->_internalState['queues'][$queue]['callers'][$realchan]);
                $this->_bModified = TRUE;
                
                file_put_contents('/tmp/debug-control_panel-events.txt',
                    "Failed to process previous Leave event on now-stale call in queue $queue\n",
                    FILE_APPEND);
            }
        }

        foreach (array_keys($this->_internalState['conferences']) as $conf) {
            if (isset($this->_internalState['conferences'][$conf]['callers'][$realchan])) {
                unset($this->_internalState['conferences'][$conf]['callers'][$realchan]);
                $this->_bModified = TRUE;
                
                file_put_contents('/tmp/debug-control_panel-events.txt',
                    "Failed to process previous MeetmeLeave event on now-stale call in conference $conf\n",
                    FILE_APPEND);
            }
        }
    }

    // Mensaje que se emite si hay un nuevo mensaje de voicemail
    function msg_MessageWaiting($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

    	/*
        Event: MessageWaiting
        Mailbox: 1064@default
        Waiting: 1
        New: 2
        Old: 3 

        Event: MessageWaiting
        Privilege: call,all
        Mailbox: 1064@default
        Waiting: 1
        */
        foreach (array_keys($this->_internalState['phones']) as $trunk) {
        	if ($this->_internalState['phones'][$trunk]['mailbox'] == $params['Mailbox']) {
                if ($params['Waiting'] == '1') {
                    // TODO: no hay mensaje que actualice UrgMessages
                    if (isset($params['New']))
                        $this->_internalState['phones'][$trunk]['NewMessages'] = (int)$params['New'];
                    if (isset($params['Old']))
                        $this->_internalState['phones'][$trunk]['OldMessages'] = (int)$params['Old'];
                } else {
                	$this->_internalState['phones'][$trunk]['NewMessages'] = 0;
                    $this->_internalState['phones'][$trunk]['OldMessages'] = 0;
                }
                $this->_bModified = TRUE;
        		break;
        	}
        }
    }

    // Mensaje que se emite en actualización de estado de registro de SIP
    function msg_PeerStatus($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

/*
peerstatus: => Array
(
    [Event] => PeerStatus
    [Privilege] => system,all
    [ChannelType] => SIP
    [Peer] => SIP/1064
    [PeerStatus] => Registered
    [Address] => 192.168.0.11:5060
    [local_timestamp_received] => 1381368086.7617
)
2013-10-09 20:21:26: DEBUG: paloControlPanelStatus::_dumpevent
retraso => 0.022648811340332
peerstatus: => Array
(
    [Event] => PeerStatus
    [Privilege] => system,all
    [ChannelType] => SIP
    [Peer] => SIP/1064
    [PeerStatus] => Reachable
    [Time] => 19
    [local_timestamp_received] => 1381368086.7768
)
 */
        $trunkinfo =& $this->_identifyTrunk($params['Peer']);
        if (!is_null($trunkinfo)) {
            $regs = NULL;
            $trunkinfo['registered'] = in_array($params['PeerStatus'], array('Registered', 'Reachable'));
            if (!$trunkinfo['registered']) {
            	$trunkinfo['ip'] = NULL;
            } elseif (isset($params['Address']) && preg_match('/^(.+):\d+$/', $params['Address'], $regs)) {
            	$trunkinfo['ip'] = $regs[1];
            }
            $this->_bModified = TRUE;
        }
    }

    function msg_QueueCallerJoin($sEvent, $params, $sServer, $iPort)
    {
        $this->msg_Join($sEvent, $params, $sServer, $iPort);
    }

    function msg_QueueCallerLeave($sEvent, $params, $sServer, $iPort)
    {
        $this->msg_Leave($sEvent, $params, $sServer, $iPort);
    }

    // Mensaje que se emite al entrar una llamada a una cola
    function msg_Join($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

/*
    [Event] => Join
    [Privilege] => call,all
    [Channel] => SIP/516-0000b4e5
    [CallerIDNum] => 516
    [CallerIDName] => Carlos Freire
    [ConnectedLineNum] => unknown
    [ConnectedLineName] => unknown
    [Queue] => 2000
    [Position] => 2
    [Count] => 2
    [Uniqueid] => 1378926036.75603
    [local_timestamp_received] => 1378926090.1298
 */
        if (isset($this->_internalState['queues'][$params['Queue']])) {
            $c = array(
                'Channel'       =>  $params['Channel'],
                'CallerIDNum'   =>  $this->_filterUnknown($params, 'CallerIDNum'),
                'CallerIDName'  =>  $this->_filterUnknown($params, 'CallerIDName'),
                'Since'         =>  NULL,
                'Position'      =>  (int)$params['Position'],
                'QueueSince'    =>  time(),
            );
            $trunkinfo =& $this->_identifyTrunk($params['Channel']);
            if (!is_null($trunkinfo)) {
                /*
            	if (isset($trunkinfo['Since'])) {
            		$c['Since'] = $trunkinfo['Since'];
            	}
                */
                if (isset($trunkinfo['active'][$params['Channel']])) {
                	$c['Since'] = $trunkinfo['active'][$params['Channel']]['Since'];
                }
            }
            $this->_internalState['queues'][$params['Queue']]['callers'][$params['Channel']] = $c;
            $this->_bModified = TRUE;
        } 
    }

    // Mensaje que se emite al salir una llamada de la cola
    function msg_Leave($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

/*
    [Event] => Leave
    [Privilege] => call,all
    [Channel] => SIP/516-0000b4e5
    [Queue] => 2000
    [Count] => 1
    [Position] => 2
    [Uniqueid] => 1378926036.75603
    [local_timestamp_received] => 1378926091.3016
 */
        if (isset($this->_internalState['queues'][$params['Queue']])) {
            $realchan = $this->_realchan($params['Channel']);
            if (isset($this->_internalState['queues'][$params['Queue']]['callers'][$realchan])) {
            	unset($this->_internalState['queues'][$params['Queue']]['callers'][$realchan]);
                $this->_bModified = TRUE;
            } else {
            }
        }
    }

    // Mensaje que se emite al agregar un agente a una cola
    function msg_QueueMemberAdded($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        if (isset($this->_internalState['queues'][$params['Queue']])) {
            if (!in_array($params['Location'], $this->_internalState['queues'][$params['Queue']]['members'])) {
            	$this->_internalState['queues'][$params['Queue']]['members'][] = $params['Location'];
                $this->_bModified = TRUE;
            }
        }
    }

    // Mensaje que se emite al quitar un agente de una cola
    function msg_QueueMemberRemoved($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

        if (isset($this->_internalState['queues'][$params['Queue']])) {
            $k = array_search($params['Location'], $this->_internalState['queues'][$params['Queue']]['members']);
            if ($k !== FALSE) {
            	array_splice($this->_internalState['queues'][$params['Queue']]['members'], $k, 1);
                $this->_bModified = TRUE;
            }
        }
    }

    function msg_ConfbridgeLeave($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);
/*
    [Event] => ConfbridgeLeave
    [Privilege] => call,all
    [Conference] => 700
    [BridgeUniqueid] => 3f2064bf-dd48-48f7-9ecb-a839153c0960
    [BridgeType] => base
    [BridgeTechnology] => softmix
    [BridgeCreator] => ConfBridge
    [BridgeName] => 700
    [BridgeNumChannels] => 3
    [BridgeVideoSourceMode] => none
    [Channel] => SIP/202-0000005c
    [ChannelState] => 6
    [ChannelStateDesc] => Up
    [CallerIDNum] => 202
    [CallerIDName] => Armando Garabano
    [ConnectedLineNum] => <unknown>
    [ConnectedLineName] => <unknown>
    [Language] => es
    [AccountCode] => Saliente
    [Context] => from-internal
    [Exten] => STARTMEETME
    [Priority] => 4
    [Uniqueid] => 1617916152.753
    [Linkedid] => 1617916152.753
    [ChanVariable] => DIALERVAR=
    [Admin] => No
    [local_timestamp_received] => 1617916170.2488

*/

        $newparam = array();
        $newparam['Event']             = 'MeetmeLeave';
        $newparam['Channel']           = $params['Channel'];
        $newparam['Uniqueid']          = $params['Uniqueid'];
        $newparam['Meetme']            = $params['BridgeName'];
        $newparam['Usernum']           = $params['BridgeNumChannels'];
        $newparam['CallerIDNum']       = $params['CallerIDNum'];
        $newparam['CallerIDName']      = $params['CallerIDName'];
        $newparam['ConnectedLineNum']  = $params['CallerIDNum'];
        $newparam['ConnectedLineName'] = $params['CallerIDName'];

        $this->msg_MeetmeLeave('MeetmeLeave', $newparam, $sServer, $iPort);

    }


    function msg_ConfbridgeJoin($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);
 /*
    [Event] => ConfbridgeJoin
    [Privilege] => call,all
    [Conference] => 700
    [BridgeUniqueid] => 06b0a074-fe57-4760-9d83-c848b99c2518
    [BridgeType] => base
    [BridgeTechnology] => softmix
    [BridgeCreator] => ConfBridge
    [BridgeName] => 700
    [BridgeNumChannels] => 3
    [BridgeVideoSourceMode] => none
    [Channel] => SIP/202-0000005b
    [ChannelState] => 6
    [ChannelStateDesc] => Up
    [CallerIDNum] => 202
    [CallerIDName] => Armando Garabano
    [ConnectedLineNum] => <unknown>
    [ConnectedLineName] => <unknown>
    [Language] => es
    [AccountCode] => Saliente
    [Context] => from-internal
    [Exten] => STARTMEETME
    [Priority] => 4
    [Uniqueid] => 1617915621.741
    [Linkedid] => 1617915621.741
    [ChanVariable] => DIALERVAR=
    [Admin] => No
    [Muted] => No
    [local_timestamp_received] => 1617915626.284
 */
        $newparam = array();
        $newparam['Event']             = 'MeetmeJoin';
        $newparam['Channel']           = $params['Channel'];
        $newparam['Uniqueid']          = $params['Uniqueid'];
        $newparam['Meetme']            = $params['BridgeName'];
        $newparam['Usernum']           = $params['BridgeNumChannels'];
        $newparam['CallerIDNum']       = $params['CallerIDNum'];
        $newparam['CallerIDName']      = $params['CallerIDName'];
        $newparam['ConnectedLineNum']  = $params['CallerIDNum'];
        $newparam['ConnectedLineName'] = $params['CallerIDName'];

        $this->msg_MeetmeJoin('MeetmeJoin', $newparam, $sServer, $iPort);

    }

    function msg_MeetmeJoin($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

/*
    [Event] => MeetmeJoin
    [Privilege] => call,all
    [Channel] => SIP/1064-00000003
    [Uniqueid] => 1378935337.4
    [Meetme] => 8472
    [Usernum] => 1
    [CallerIDnum] => 1064
    [CallerIDname] => Alex
    [ConnectedLineNum] => <unknown>
    [ConnectedLineName] => <unknown>
    [local_timestamp_received] => 1378935342.3985
 */    	
        if (isset($this->_internalState['conferences'][$params['Meetme']])) {
            $caller = array(
                'Channel'   =>  $params['Channel'],
                'CallerIDNum'   =>  $params['CallerIDNum'],
                'CallerIDName'  =>  $params['CallerIDName'],
                'ConfSince' =>  time(),
            );
            $this->_internalState['conferences'][$params['Meetme']]['callers'][$params['Channel']] = $caller;
            $this->_bModified = TRUE;
        }
    }
    
    function msg_MeetmeLeave($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

/*
    [Event] => MeetmeLeave
    [Privilege] => call,all
    [Channel] => SIP/1064-00000003
    [Uniqueid] => 1378935337.4
    [Meetme] => 8472
    [Usernum] => 1
    [CallerIDNum] => 1064
    [CallerIDName] => Alex
    [ConnectedLineNum] => <unknown>
    [ConnectedLineName] => <unknown>
    [Duration] => 22
    [local_timestamp_received] => 1378935360.6809
 */    	
        if (isset($this->_internalState['conferences'][$params['Meetme']])) {
            $realchan = $this->_realchan($params['Channel']);
            if (isset($this->_internalState['conferences'][$params['Meetme']]['callers'][$realchan])) {
                unset($this->_internalState['conferences'][$params['Meetme']]['callers'][$realchan]);
                $this->_bModified = TRUE;
            }
        }
    }
    
    /* Los siguientes eventos están aquí sólo para ser ignorados */
    function msg_Default($sEvent, $params, $sServer, $iPort)
    {
        $this->_dumpevent($sEvent, $params);

    }

    private function _dumpevent($sEvent, $params)
    {
        if ($this->_debug) {
            $s = date('Y-m-d H:i:s').': DEBUG: '.__METHOD__.
                "\nretraso => ".(microtime(TRUE) - $params['local_timestamp_received']).
                "\n$sEvent: => ".print_r($params, TRUE)
                ;
            file_put_contents('/tmp/debug-control_panel-events.txt', $s, FILE_APPEND);
        }
    }
/*
    function msg_Dial($sEvent, $params, $sServer, $iPort) {$this->_minevent($sEvent);}
    function msg_RTCPSent($sEvent, $params, $sServer, $iPort) {$this->_minevent($sEvent);}
    function msg_RTCPReceived($sEvent, $params, $sServer, $iPort) {$this->_minevent($sEvent);}
    function msg_VarSet($sEvent, $params, $sServer, $iPort) {$this->_minevent($sEvent);}
    function msg_JitterBufStats($sEvent, $params, $sServer, $iPort) {$this->_minevent($sEvent);}
    function msg_Registry($sEvent, $params, $sServer, $iPort) {$this->_minevent($sEvent);}
    function msg_Cdr($sEvent, $params, $sServer, $iPort) {$this->_minevent($sEvent);}
    function msg_ExtensionStatus($sEvent, $params, $sServer, $iPort) {$this->_minevent($sEvent);}
    function msg_ChannelUpdate($sEvent, $params, $sServer, $iPort) {$this->_minevent($sEvent);}
    function msg_MusicOnHold($sEvent, $params, $sServer, $iPort) {$this->_minevent($sEvent);}
    function msg_HangupRequest($sEvent, $params, $sServer, $iPort) {$this->_minevent($sEvent);}
    function msg_SoftHangupRequest($sEvent, $params, $sServer, $iPort) {$this->_minevent($sEvent);}
    function msg_DTMF($sEvent, $params, $sServer, $iPort) {$this->_minevent($sEvent);}
    function msg_NewAccountCode($sEvent, $params, $sServer, $iPort) {$this->_minevent($sEvent);}
    function msg_Hold($sEvent, $params, $sServer, $iPort) {$this->_minevent($sEvent);}
    
    private function _minevent($sEvent)
    {
        $s = date('Y-m-d H:i:s')." $sEvent\n";
        file_put_contents('/tmp/debug-control_panel-events.txt', $s, FILE_APPEND);
    }
*/
    function shutdown()
    {
    	$this->_ami->disconnect();
        $this->_db->disconnect();
        $this->_dbConfig->disconnect();
    }
}
?>
