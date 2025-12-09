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
  $Id: index.php,v 1.1 2007/01/09 23:49:36 alex Exp $
*/
require_once 'libs/misc.lib.php';
require_once 'libs/paloSantoDB.class.php';
require_once 'AGI_AsteriskManager2.class.php';

class paloControlPanelUtils
{
    private $_db;
    var $errMsg;

    function __construct()
    {
        global $arrConf;
        $this->_db = new paloDB($arrConf['dsn_conn_database']);
        if ($this->_db->errMsg != '') {
            $this->errMsg = $this->_db->errMsg;
            $this->_db = NULL;
        }
    }

    function loadAreaProperties()
    {
    	$sql = 'SELECT name, width, description, color FROM area ORDER BY id';
        $recordset = $this->_db->fetchTable($sql, TRUE);
        if (!is_array($recordset)) return NULL;
        
        $prop = array();
        foreach ($recordset as $tupla) $prop[$tupla['name']] = $tupla;
        return $prop;
    }
    
    // Leer el ID correspondiente al panel, o NULL si panel no existe
    private function _getPanelID($panel)
    {
    	$tupla = $this->_db->getFirstRowQuery(
            'SELECT id FROM area WHERE name = ?', TRUE, array($panel));
        if (!is_array($tupla)) {
            $this->errMsg = '(internal) Failed to read panel ID - '.$this->_db->errMsg;
        	return NULL;
        }
        if (count($tupla) <= 0) {
            $this->errMsg = '(internal) Panel not found';
        	return NULL;
        }
        return $tupla['id'];
    }
    
    /**
     * Procedimiento que registra que una extensión debe de aparecer en el panel
     * indicado por $panel.
     * 
     * @param   string  $panel      Nombre del panel al que se manda la extensión
     * @param   string  $extension  Extensión a asignar al panel
     * 
     * @return  bool    VERDADERO en caso de éxito, FALSO en error
     */
    function updateExtensionPanel($panel, $extension)
    {
    	$panelid = $this->_getPanelID($panel);
        if (is_null($panelid)) return FALSE;
        $r = $this->_db->genQuery('DELETE FROM item_box WHERE id_device = ?',
            array($extension));
        if (!$r) {
            $this->errMsg = '(internal) Cannot delete old association for extension - '.$this->_db->errMsg;
        	return FALSE;
        }
        $r = $this->_db->genQuery('INSERT INTO item_box (id_area, id_device) VALUES (?, ?)',
            array($panelid, $extension));
        if (!$r) {
            $this->errMsg = '(internal) Cannot create association for extension - '.$this->_db->errMsg;
            return FALSE;
        }
        return TRUE;
    }
    
    /**
     * Procedimiento que registra que un panel debe de tener una nueva descripción
     * 
     * @param   string  $panel          Nombre del panel al que se manda la extensión
     * @param   string  $description    Extensión a asignar al panel
     * 
     * @return  bool    VERDADERO en caso de éxito, FALSO en error
     */
    function updatePanelDesc($panel, $description)
    {
        $panelid = $this->_getPanelID($panel);
        if (is_null($panelid)) return FALSE;
        $r = $this->_db->genQuery('UPDATE area SET description = ? WHERE id = ?',
            array($description, $panelid));
        if (!$r) {
            $this->errMsg = '(internal) Cannot update description for panel - '.$this->_db->errMsg;
            return FALSE;
        }
        return TRUE;
    }
    
    /**
     * Procedimiento que registra un nuevo ancho para un grupo de paneles
     * 
     * @param   string  $panelgroup     Grupo de paneles: 'left', 'right'
     * @param   string  $description    Extensión a asignar al panel
     * 
     * @return  bool    VERDADERO en caso de éxito, FALSO en error
     */
    function updatePanelSize($panelgroup, $width)
    {
    	$panelgroups = array(
            'left'  =>  array('Extension', 'TrunksSIP', 'Trunks'),
            'right' =>  array('Area1', 'Area2', 'Area3', 'Area4', 'Area5', 'Area6', 'Area7', 'Area8', 'Area9', 'Queues', 'Conferences', 'Parkinglots'),
        );
        if (!in_array($panelgroup, array_keys($panelgroups))) {
            $this->errMsg = '(internal) Invalid panel group';
        	return FALSE;
        }
        $num_cols = (int)($width / 186);
        if ($num_cols < 1) $num_cols = 1;
        
        foreach ($panelgroups[$panelgroup] as $panel) {
            $panelid = $this->_getPanelID($panel);
            if (is_null($panelid)) return FALSE;

            $r = $this->_db->genQuery('UPDATE area SET width = ?, no_column = ? WHERE id = ?',
                array($width, $num_cols, $panelid));
            if (!$r) {
                $this->errMsg = '(internal) Cannot update width for panel - '.$this->_db->errMsg;
                return FALSE;
            }
        }
        return TRUE;
    }
    
    /**
     * Procedimiento que inicia una llamada desde la cuenta que corresponde a
     * $source, a la extensión indicada por $target.
     * 
     * @param   string  $source Extensión de la cuenta fuente de la llamada
     * @param   string  $target Extensión a la cual se va a marcar
     * 
     * @return  bool    VERDADERO en éxito, FALSO en error
     */
    function callExtension($source, $target)
    {
        // Verificar fuente y destino para que sean números o #*
        if (!preg_match('/^[[:digit:]#*]+$/', $source)) {
            $this->errMsg = _tr('Invalid source for call');
        	return FALSE;
        }
        if (!preg_match('/^[[:digit:]#*]+$/', $target)) {
            $this->errMsg = _tr('Invalid target for call');
            return FALSE;
        }
    	
        $ami = new AGI_AsteriskManager2();
        if (!$ami->connect('localhost', 'admin', obtenerClaveAMIAdmin())) {
            $this->_errMsg = _tr("Error when connecting to Asterisk Manager");
            return FALSE;
        }
        
        // Leer la cadena de dial para la fuente.
        list($dialchan, $cidname) = $this->_getDialString($ami, $source);
        if ($dialchan === FALSE) {
            $ami->disconnect();
            return FALSE;
        }
        
        // Originar la llamada ahora
        $r = $ami->Originate($dialchan,
            $target, 'from-internal', 1,
            NULL, NULL, NULL, "$cidname <$source>", NULL, NULL);

        $ami->disconnect();
        if ($r['Response'] != 'Success') {
            $this->errMsg = '(internal) failed to Originate call';
            return FALSE;
        }
        return TRUE;
    }
    
    /**
     * Procedimiento que cuelga el primer canal activo asociado con la cuenta
     * que posee la extensión indicada.
     * 
     * @param   string  $target Extensión con un canal activo a colgar
     * 
     * @return  bool    VERDADERO en éxito, FALSO en error
     */
    function hangupExtension($target)
    {
        if (!preg_match('/^[[:digit:]#*]+$/', $target)) {
            $this->errMsg = _tr('Invalid target for call');
            return FALSE;
        }
        
        $ami = new AGI_AsteriskManager2();
        if (!$ami->connect('localhost', 'admin', obtenerClaveAMIAdmin())) {
            $this->_errMsg = _tr("Error when connecting to Asterisk Manager");
            return FALSE;
        }
        
        // Leer la cadena de dial para la fuente.
        list($dialchan, $cidname) = $this->_getDialString($ami, $target);
        if ($dialchan === FALSE) {
            $ami->disconnect();
            return FALSE;
        }
        
        // Listar los canales activos y buscar el que tiene como prefijo el $dialchan
        $r = $ami->Command('core show channels concise');        
        if ($r['Response'] != 'Follows') {
            $this->errMsg = _tr('(internal) Failed to list channels');
        	return FALSE;
        }
        $channel = NULL;
        foreach (explode("\n", $r['data']) as $s) {
        	$tupla = explode('!', $s);
            if (count($tupla) >= 11 && strpos($tupla[0], $dialchan) === 0) {
                $channel = $tupla[0];
            	break;
            }
        }        
        if (is_null($channel)) {
            $this->errMsg = _tr('No active channels for target');
        	$ami->disconnect();
            return FALSE;
        }
        
        // Colgar el canal que ha sido encontrado
        $r = $ami->Hangup($channel);
        $ami->disconnect();
        if ($r['Response'] != 'Success') {
            $this->errMsg = _tr('(internal) Failed to hangup active channel for target');
            return FALSE;
        }
        return TRUE;
    }
    
    // Leer la cadena de dial para la fuente. Este método depende de FreePBX
    private function _getDialString($ami, $source)
    {
        $dialchan = $ami->database_get('DEVICE', "$source/dial");
        if ($dialchan === FALSE) {
            $this->_errMsg = _tr("Failed to query dialchannel for source extension");
            return array(NULL, NULL);
        }
        $cidname = $ami->database_get('AMPUSER', "$source/cidname");
        if ($cidname === FALSE) $cidname = '(unknown)';

        return array($dialchan, $cidname);
    }
    
    /**
     * Procedimiento que inicia una llamada desde la cuenta que corresponde a
     * $source, a la extensión de voicemail. La extensión a marcar para el 
     * voicemail se recupera a través de una consulta a la base de datos 
     * FreePBX.
     * 
     * @param   string  $source Extensión de la cuenta fuente de la llamada
     * 
     * @return  bool    VERDADERO en éxito, FALSO en error
     */
    function callExtensionVoicemail($source)
    {
        // Abrir conexión a la base de datos de FreePBX
        $dsn = generarDSNSistema('asteriskuser', 'asterisk');
        $db = new paloDB($dsn);
        if ($db->errMsg != '') {
            $this->errMsg = $db->errMsg;
            $db->disconnect();
            $db = NULL;
            return FALSE;            
        }
        
        // Consulta del código correspondiente al voicemail
        $target = '*97';   // Valor por omisión de la extensión de voicemail
        $sql =
            'SELECT defaultcode, customcode FROM featurecodes '.
            'WHERE modulename = "voicemail" AND featurename = "myvoicemail"';
        $tupla = $db->getFirstRowQuery($sql, TRUE);
        if (!is_array($tupla)) {
            $this->errMsg = $db->errMsg;
            $db->disconnect();
            $db = NULL;
            return FALSE;
        }
        if (isset($tupla['customcode']) && !is_null($tupla['customcode']))
            $target = $tupla['customcode'];
        elseif (isset($tupla['defaultcode']) && !is_null($tupla['defaultcode']))
            $target = $tupla['defaultcode'];

        return $this->callExtension($source, $target);
    }
}
?>
