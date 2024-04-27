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

require_once "libs/paloSantoJSON.class.php";

function _moduleContent(&$smarty, $module_name)
{
    require_once "modules/$module_name/configs/default.conf.php";
    require_once "modules/$module_name/libs/paloInterfaceSSE.class.php";
    require_once "modules/$module_name/libs/paloServerSentEvents.class.php";
    require_once "modules/$module_name/libs/paloControlPanelUtils.class.php";


    load_language_module($module_name);

    global $arrConf;
    global $arrConfModule;
    $arrConf = array_merge($arrConf,$arrConfModule);



    //folder path for custom templates
    $base_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir = (isset($arrConf['templates_dir']))?$arrConf['templates_dir']:'themes';
    $local_templates_dir = "$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    checkAreas();

    // Valores estáticos comunes a todas las operaciones
    $smarty->assign('module_name', $module_name);

    $h = 'handleHTML_mainReport';
    if (isset($_REQUEST['action'])) {
        $h = NULL;
        
        if (is_null($h) && function_exists('handleJSON_'.$_REQUEST['action']))
            $h = 'handleJSON_'.$_REQUEST['action'];
        if (is_null($h))
            $h = 'handleJSON_unimplemented';
    }        
    return call_user_func($h, $smarty, $module_name, $local_templates_dir);
}

function handleJSON_unimplemented($smarty, $module_name, $local_templates_dir)
{
    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode(array(
        'status'    =>  'error',
        'message'   =>  _tr('Unimplemented method'),
    ));
}




function handleHTML_mainReport($smarty, $module_name, $local_templates_dir)
{

    //obtenemos la extension actual del usuario activo
    global $arrConf;


    // Inicialización del estado del módulo
    paloServerSentEvents::generarEstadoHash($module_name, array());
    $pUtils = new paloControlPanelUtils();
    $areaProp = $pUtils->loadAreaProperties();
    foreach (array_keys($areaProp) as $k)
        $areaProp[$k]['description'] = _tr($areaProp[$k]['description']);
    
    // Ember.js requiere jQuery 1.7.2 o superior.
    modificarReferenciasLibreriasJS($smarty, $module_name);

    $json = new Services_JSON();

    $smarty->assign(array(
        'title'                     =>  _tr('Control Panel'),
        'LBL_EDIT_NAME'             =>  _tr('Edit Name'),
        'LBL_SAVE_NAME'             =>  _tr('Save'),
        'icon'                      =>  'modules/'.$module_name.'/images/pbx_operator_panel.png',
        'LBL_NEW'                   =>  _tr('New'),
        'LBL_OLD'                   =>  _tr('Old'),
        'LBL_QUEUE_MEMBERS'         =>  _tr('Members'),
        'LBL_QUEUE_NO_MEMBERS'      =>  _tr('Not Attended'),
        'LBL_QUEUE_CALLERS'         =>  _tr('Callers'),
        'LBL_QUEUE_NO_CALLERS'      =>  _tr('No Callers'),
        'LBL_CONF_PARTICIPANTS'     =>  _tr('Participant(s)'),
        'LBL_PARKED'                =>  _tr('Parked'),
        'ARRLANG_MAIN'              =>  $json->encode(array(
            'LBL_HIDE_ALL'      =>  _tr('Hide All'),
            'LBL_SHOW_ALL'      =>  _tr('Show All'),
        )),
        'VAR_INIT'                  =>  $json->encode(array(
            'ESTADO_CLIENTE_HASH'   =>  $_SESSION[$module_name]['estadoClienteHash'],
            'ESTADO_PANELES'        =>  $areaProp,
            'TIMESTAMP_START'       =>  time(),
	   

        )),
    ));
    foreach ($areaProp as $k => $tupla) {
    	$smarty->assign('AREA_DESCR_'.strtoupper($k), $tupla['description']);
    }
    $c = $smarty->fetch($local_templates_dir.'/panel.tpl');
    
    return $c;
}

function handleJSON_pbxStatus($smarty, $module_name, $local_templates_dir)
{
    require_once "modules/$module_name/libs/paloControlPanelStatus.class.php";

    $paloSSE = new paloServerSentEvents($module_name, 'paloControlPanelStatus');
    $paloSSE->handle();
    return '';
}

function handleJSON_pbxStatusShutdown($smarty, $module_name, $local_templates_dir)
{
    $respuesta = array(
        'status'    =>  'success',
        'message'   =>  '(no message)',
    );
    
	$_SESSION[$module_name]['finalizarEscucha'] = TRUE;
    
    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}



function handleJSON_updateExtensionPanel($smarty, $module_name, $local_templates_dir)
{
    $respuesta = array(
        'status'    =>  'success',
        'message'   =>  '(no message)',
    );
    
    if (!isset($_REQUEST['panel']) || !isset($_REQUEST['extension'])) {
    	$respuesta['status'] = 'error';
        $respuesta['message'] = _tr('Invalid or missing parameters');
    } else {
        $pUtils = new paloControlPanelUtils();
        if (!$pUtils->updateExtensionPanel($_REQUEST['panel'], $_REQUEST['extension'])) {
            $respuesta['status'] = 'error';
            $respuesta['message'] = $pUtils->errMsg;
        }
    }
    
    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function handleJSON_updatePanelDesc($smarty, $module_name, $local_templates_dir)
{
    $respuesta = array(
        'status'    =>  'success',
        'message'   =>  '(no message)',
    );

    if (!isset($_REQUEST['panel']) || !isset($_REQUEST['description'])) {
        $respuesta['status'] = 'error';
        $respuesta['message'] = _tr('Invalid or missing parameters');
    } else {
        $pUtils = new paloControlPanelUtils();
        if (!$pUtils->updatePanelDesc($_REQUEST['panel'], $_REQUEST['description'])) {
            $respuesta['status'] = 'error';
            $respuesta['message'] = $pUtils->errMsg;
        }
    }
    
    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function handleJSON_updatePanelSize($smarty, $module_name, $local_templates_dir)
{
    $respuesta = array(
        'status'    =>  'success',
        'message'   =>  '(no message)',
    );

    if (!isset($_REQUEST['panelgroup']) || !isset($_REQUEST['width'])) {
        $respuesta['status'] = 'error';
        $respuesta['message'] = _tr('Invalid or missing parameters');
    } else {
        $pUtils = new paloControlPanelUtils();
        if (!$pUtils->updatePanelSize($_REQUEST['panelgroup'], $_REQUEST['width'])) {
            $respuesta['status'] = 'error';
            $respuesta['message'] = $pUtils->errMsg;
        }
    }
    
    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function handleJSON_callExtension($smarty, $module_name, $local_templates_dir)
{
    $respuesta = array(
        'status'    =>  'success',
        'message'   =>  '(no message)',
    );

    if (!isset($_REQUEST['source']) || !isset($_REQUEST['target'])) {
        $respuesta['status'] = 'error';
        $respuesta['message'] = _tr('Invalid or missing parameters');
    } else {
        $pUtils = new paloControlPanelUtils();
        if (!$pUtils->callExtension($_REQUEST['source'], $_REQUEST['target'])) {
            $respuesta['status'] = 'error';
            $respuesta['message'] = $pUtils->errMsg;
        }
    }
    
    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function handleJSON_hangupExtension($smarty, $module_name, $local_templates_dir)
{
    $respuesta = array(
        'status'    =>  'success',
        'message'   =>  '(no message)',
    );

    if (!isset($_REQUEST['target'])) {
        $respuesta['status'] = 'error';
        $respuesta['message'] = _tr('Invalid or missing parameters');
    } else {
        $pUtils = new paloControlPanelUtils();
        if (!$pUtils->hangupExtension($_REQUEST['target'])) {
            $respuesta['status'] = 'error';
            $respuesta['message'] = $pUtils->errMsg;
        }
    }
    
    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function handleJSON_voicemailExtension($smarty, $module_name, $local_templates_dir)
{
    $respuesta = array(
        'status'    =>  'success',
        'message'   =>  '(no message)',
    );

    if (!isset($_REQUEST['source'])) {
        $respuesta['status'] = 'error';
        $respuesta['message'] = _tr('Invalid or missing parameters');
    } else {
        $pUtils = new paloControlPanelUtils();
        if (!$pUtils->callExtensionVoicemail($_REQUEST['source'])) {
            $respuesta['status'] = 'error';
            $respuesta['message'] = $pUtils->errMsg;
        }
    }
    
    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function modificarReferenciasLibreriasJS($smarty, $module_name)
{
    $listaLibsJS_framework = explode("\n", $smarty->get_template_vars('HEADER_LIBS_JQUERY'));
    $listaLibsJS_modulo = explode("\n", $smarty->get_template_vars('HEADER_MODULES'));

    /* Se busca la referencia a jQuery (se asume que sólo hay una biblioteca que
     * empieza con "jquery-") y se la quita. Las referencias a Ember.js y 
     * Handlebars se reordenan para que Handlebars aparezca antes que Ember.js 
     */ 
    $sEmberRef = $sHandleBarsRef = $sjQueryRef = NULL;
    foreach (array_keys($listaLibsJS_modulo) as $k) {
        if (strpos($listaLibsJS_modulo[$k], 'themes/default/js/jquery-') !== FALSE) {
            $sjQueryRef = $listaLibsJS_modulo[$k];
            unset($listaLibsJS_modulo[$k]);
        } elseif (strpos($listaLibsJS_modulo[$k], 'themes/default/js/handlebars-') !== FALSE) {
            $sHandleBarsRef = $listaLibsJS_modulo[$k];
            unset($listaLibsJS_modulo[$k]);
        } elseif (strpos($listaLibsJS_modulo[$k], 'themes/default/js/ember-') !== FALSE) {
            $sEmberRef = $listaLibsJS_modulo[$k];
            unset($listaLibsJS_modulo[$k]);
        }
    }
    array_unshift($listaLibsJS_modulo, $sEmberRef);
    array_unshift($listaLibsJS_modulo, $sHandleBarsRef);
    
    $smarty->assign('HEADER_MODULES', implode("\n", $listaLibsJS_modulo));

    /* Se busca la referencia original al jQuery del framework, y se reemplaza
     * si es más vieja que el jQuery del módulo */
    $sRegexp = '/jquery-(\d.+?)(\.min)?\.js/'; $regs = NULL;
    preg_match($sRegexp, $sjQueryRef, $regs);
    $sVersionModulo = $regs[1];
    $sVersionFramework = NULL;
    foreach (array_keys($listaLibsJS_framework) as $k) {
        if (preg_match($sRegexp, $listaLibsJS_framework[$k], $regs)) {
            $sVersionFramework = $regs[1];
            
            // Se asume que la versión sólo consiste de números y puntos
            $verFramework = explode('.', $sVersionFramework);
            $verModulo = explode('.', $sVersionModulo);
            while (count($verFramework) < count($verModulo)) $verFramework[] = "0";
            while (count($verFramework) > count($verModulo)) $verModulo[] = "0";
            if ($verModulo > $verFramework) $listaLibsJS_framework[$k] = $sjQueryRef;
        }
    }
    $smarty->assign('HEADER_LIBS_JQUERY', implode("\n", $listaLibsJS_framework));


    
}

function checkAreas() {
    $db = new SQLite3('/var/www/db/control_panel_design.db');

    // Contar cuántas áreas existen
    $countQuery = "SELECT COUNT(*) as count FROM area WHERE name LIKE '%Area%'";
    $result = $db->querySingle($countQuery);

    if ($result !== FALSE) {
        $existingAreasCount = $result;
        $response['existingAreasCount'] = $existingAreasCount;
    } else {
        $response['error'] = 'Error al contar áreas: ' . $db->lastErrorMsg();
    }

    // Insertar áreas adicionales hasta el Área 9 si es necesario
    if ($existingAreasCount < 9) {
        for ($i = $existingAreasCount + 1; $i <= 9; $i++) {
            $areaName = 'Area' . $i;
            $insertQuery = "INSERT INTO area (name, height, width, description, no_column, color)
                VALUES ('$areaName', 100, 1070, 'Area $i', 5, '#E0FFEF');";

            if ($db->query($insertQuery) === FALSE) {
                $response['error'] = 'Error al insertar áreas adicionales: ' . $db->lastErrorMsg();
                break;
            }
        }
    }

    $db->close();

    // Agregar mensaje de éxito si no hay errores
    if (!isset($response['error'])) {
        $response['areasInserted'] = true;
    }

    // Devolver la respuesta en formato JSON
    //echo json_encode($response);
}

?>