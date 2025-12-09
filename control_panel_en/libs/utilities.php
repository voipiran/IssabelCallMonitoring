<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cleanQueue = $_POST['cleanQueue'];
    
    // Realiza las operaciones necesarias con $cleanQueue
    if ($cleanQueue){
        // Ahora, $cleanQueue es una cadena simple, no necesita ser decodificado
        shell_exec("asterisk -rx 'queue reset stats " . $cleanQueue . "'");
    }
}


action("QueueStatus");

function action($action) {
include("config.php");

$oSocket = fsockopen($amiHost, $amiPort, $errnum, $errdesc) or die("Connection to host failed queueinfo.php");
fputs($oSocket, "Action: Login\r\n");
fputs($oSocket, "UserName: $amiUser\r\n");
fputs($oSocket, "Secret: $amiPass\r\n\r\n");
$wrets = fgets($oSocket, 128);
// Realizar una solicitud para obtener QueueStatus
fputs($oSocket, "Action: $action\r\n\r\n");
// Crear un archivo temporal para almacenar la respuesta
$tempFile = tempnam(sys_get_temp_dir(), "ami_response_");
$fileHandle = fopen($tempFile, "w");

while (!feof($oSocket)) {
    $line = fgets($oSocket, 4096);
    if (fwrite($fileHandle, $line) === false) {
        break;
    }
    if (strpos($line, "$action") !== false) {
        break;
    }
}
fclose($fileHandle);
fclose($oSocket);
// Leer el archivo temporal y mostrar la información de QueueStatus
$data = file_get_contents($tempFile);
unlink($tempFile); // Elimina el archivo temporal


$action($data);
}


function QueueStatus($data) {
    $lines = explode("\n", $data);
    $events = [
        'QueueParams',
        'QueueMember'
    ];

    $QueueParams = "";
    $QueueMember = "";
    $currentEvent = "";

    foreach ($lines as $line) {
        foreach ($events as $event) {
            if (strpos($line, "Event: $event") !== false) {
                $currentEvent = $event;
                break;
            }
        }

        if ($currentEvent !== "") {
            // Agregar la línea al resultado correspondiente
            if ($currentEvent === 'QueueParams') {
                $QueueParams .= $line . "\n";
            } elseif ($currentEvent === 'QueueMember') {
                $QueueMember .= $line . "\n";
            }
        }
    }


    // Imprimir los resultados por evento
    //Datos de QueueParams
    $obj = QueueParams($QueueParams);
    $obj = QueueMember($QueueMember, $obj);

header('Content-Type: application/json');
echo json_encode($obj);

}


function QueueParams($data) {
    $lines = explode("\n", $data);
    $obj = array();
    $currentQueue = null;

    foreach ($lines as $line) {
        // Divide cada línea en dos partes: clave y valor
        $parts = array_map('trim', explode(": ", $line, 2));

        if (count($parts) === 2) {
        list($key, $value) = $parts;

        if ($key === "Queue" && $value !== "default") {
            // Inicia un nuevo array para la cola
            $currentQueue = array("Queue" => $value);
        } elseif ($key === "Completed" && $currentQueue !== null) {
            // Agrega el número de llamadas a la cola
            $currentQueue["Completed"] = $value;
        } elseif ($key === "Abandoned" && $currentQueue !== null) {
            // Agrega el número de llamadas a la cola
            $currentQueue["Abandoned"] = $value;
        } elseif ($key === "Weight") {
            // Agrega el array de la cola al resultado
            if ($currentQueue !== null) {
                $obj[] = $currentQueue;
            }
            $currentQueue = null;
        }
    }
}
    return $obj;
}

function QueueMember($data, $obj) {
    $lines = explode("\n", $data);
    $queueMembers = array();
    $currentMember = array();

    foreach ($lines as $line) {
        if (strpos($line, "Event: QueueMember") !== false) {
            if (!empty($currentMember)) {
                $queueMembers[] = $currentMember;
            }
            $currentMember = array();
        } elseif (!empty($line)) {
            if (strpos($line, ": ") !== false) {
                list($key, $value) = array_map('trim', explode(": ", $line, 2));
                $currentMember[$key] = $value;
            }
        }
    }

    if (!empty($currentMember)) {
        $queueMembers[] = $currentMember;
    }

    foreach ($obj as &$element) {
        $element['Members'] = array();

        foreach ($queueMembers as $member) {
            if (isset($element['Queue']) && isset($member['Queue']) && $element['Queue'] == $member['Queue']) {
                $memberData = [
                    'Name' => $member['Name'],
                    'Location' => extractLocation($member['Location']),
                    'Status' => $member['Status'],
                    'Paused' => $member['Paused']
                ];
                $element['Members'][] = $memberData;
                
                // Eliminar el array $member
                unset($member);
            }
        }

            // Ordenar los miembros por 'Location' de menor a mayor
            usort($element['Members'], function ($a, $b) {
            $locationA = intval($a['Location']);
            $locationB = intval($b['Location']);
            if ($locationA < $locationB) {
                return -1;
            } elseif ($locationA > $locationB) {
                return 1;
            } else {
                return 0;
            }
        });
    }

    return $obj;
}

function extractLocation($location) {
    $startPJSIP = strpos($location, 'PJSIP/');
    $startSIP   = strpos($location, 'SIP/');
    $startLocal = strpos($location, 'Local/');

    if ($startPJSIP !== false && $startLocal === false && ($startSIP === false || $startPJSIP < $startSIP)) {
        // La cadena contiene 'PJSIP/' y no contiene 'Local/' ni 'SIP/' o 'PJSIP/' aparece antes que 'SIP/'
        return substr($location, $startPJSIP + 6);
    } elseif ($startSIP !== false && $startLocal === false && ($startPJSIP === false || $startSIP < $startPJSIP)) {
        // La cadena contiene 'SIP/' y no contiene 'Local/' ni 'PJSIP/' o 'SIP/' aparece antes que 'PJSIP/'
        return substr($location, $startSIP + 4);
    } elseif ($startLocal !== false && $startPJSIP === false && $startSIP === false) {
        // La cadena contiene 'Local/' y no contiene 'PJSIP/' ni 'SIP/'
        $end = strpos($location, '@', $startLocal);
        if ($end !== false) {
            return substr($location, $startLocal + 6, $end - $startLocal - 6);
        } else {
            return "";
        }
    } else {
        return "";
    }
}

?>