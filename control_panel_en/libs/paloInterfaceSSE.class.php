<?php
/*
  vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Issabel version 1.0                                                  |
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
  | Autores: Alex Villacís Lasso <a_villacis@palosanto.com>              |
  +----------------------------------------------------------------------+
  $Id: index.php,v 1.1 2007/01/09 23:49:36 alex Exp $
*/

abstract class paloInterfaceSSE
{
    protected $_errMsg = NULL;
    function getErrMsg() { return $this->_errMsg; }
    
    /**
     * Procedimiento que crea una respuesta vacía para devolver al navegador.
     * Por omisión, se crea un arreglo asociativo vacío. Si es necesario, se 
     * puede sobrecargar para devolver una estructura más compleja.
     */
    function createEmptyResponse() { return array(); }
        
    /**
     * Este procedimiento debe de ser implementado según el proceso. Al momento
     * de iniciar la espera, se pasa a este método el estado previamente 
     * guardado, o un estado vacío, si no hay estado previo. Este método debe
     * entonces ejecutar sus operaciones para recolectar el estado actual del
     * monitoreo, y compararlo contra el estado especificado. Cualquier 
     * diferencia debe ser traducida a una lista de respuestas a ser llenada en
     * $jsonResponse. Si la respuesta sigue vacía (tal como lo evalúa 
     * isEmptyResponse), entonces se asume que no hay cambios relevantes en el
     * monitoreo. Se espera que el estado que se ha proporcionado sea modificado
     * para que refleje los cambios que serán comunicados por $jsonResponse. Si
     * el examen del estado inicial indica que no debe de esperarse eventos, se
     * debe devolver FALSE, o TRUE en caso contrario.
     */
    abstract function findInitialStateDifferences(&$initialClientState, &$jsonResponse);
    
    /**
     * Este método se llama justo antes de iniciar el bucle de espera de 
     * eventos, una sola vez.
     */
    function setupBeforeEventLoop() {}
    
    /**
     * Este método se llama justo antes de esperar eventos, repetidas veces
     */
    function setupBeforeEventWait() {}
    
    /**
     * Este procedimiento debe de ser implementado según el proceso. Aquí se
     * debe de ejecutar la espera por eventos, durante un intervalo corto. Los
     * eventos que se capturen deben de almacenarse internamente hasta ser
     * procesados por findEventStateDifferences(). Se debe de devolver TRUE si
     * se ha capturado exitosamente al menos un evento, o FALSO si ocurrió un
     * error durante la espera.
     */
    abstract function waitForEvents();
    
    /**
     * Este procedimiento revisa si durante la espera, otro proceso cambio de
     * forma independiente el estado de la sesión, de forma que nuestro estado
     * en $currentClientState ya no aplica. Si este caso puede ocurrir, entonces
     * se implementa la verificación aquí, y se devuelve TRUE si el estado de
     * la sesión se volvió incompatible con el estado actual.
     */
    function checkInvalidatedWait(&$currentClientState, &$newClientState) { return FALSE; }
    
    /**
     * Este procedimiento debe de ser implementado según el proceso, Aquí se
     * revisan los eventos recibidos en waitForEvents() y se calcula de qué 
     * manera modifican el estado del cliente. Cada modificación debe de hacerse
     * en $currentClientState y registrada en $jsonResponse. Se debe de devolver
     * FALSE si alguno de los eventos implica el fin del monitoreo, o TRUE para
     * continuar en el bucle de eventos.
     */
    abstract function findEventStateDifferences(&$currentClientState, &$jsonResponse);
    
    /**
     * Este procedimiento es la definición de una "respuesta vacía" al cliente.
     */
    function isEmptyResponse($jsonResponse) { return (count($jsonResponse) <= 0);}
    
    /**
     * Este método se llama cuando se va a finalizar el monitoreo y deben 
     * cerrarse todos los recursos usados.
     */
    function shutdown() {}
}
?>