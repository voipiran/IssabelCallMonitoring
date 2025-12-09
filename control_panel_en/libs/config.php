<?php


if (is_readable("/etc/amportal.conf")) {
    $amp_conf 	= amportal_conf("/etc/amportal.conf");
    $amiHost 	= $amp_conf['ASTMANAGERHOST'];
    $amiPort    = $amp_conf['ASTMANAGERPORT'];
    $amiUser 	= $amp_conf['AMPMGRUSER'];
    $amiPass 	= $amp_conf['AMPMGRPASS'];
    $DBHOST     = $amp_conf['AMPDBHOST'];
    $DBNAME     = $amp_conf['AMPDBNAME'];
    $DBUSER     = $amp_conf['AMPDBUSER'];
    $DBPASS     = $amp_conf['AMPDBPASS'];
}

function amportal_conf($filename) {

    $file = file($filename);
    if (is_array($file)) {
        foreach ($file as $line) {
            if (preg_match("/^\s*([^=]*)\s*=\s*[\"']?([\w\/\:\.\,\}\{\>\<\(\)\*\?\%!=\+\#@&\\$-]*)[\"']?\s*([;].*)?/",$line,$matches)) {
                if(preg_match('/\$amp_conf/',$matches[1])) {
                    $matches[1] = preg_replace('/\$amp_conf\[\'/','',$matches[1]);
                    $matches[1] = preg_replace('/\$amp_conf\["/','',$matches[1]);
                    $matches[1] = trim($matches[1]);
                    $matches[1] = substr($matches[1],0,-2);
                }
                $matches[1] = trim($matches[1]);
                $conf[ $matches[1] ] = trim($matches[2]);
            }
        }
    } else {
        die("<h1>".sprintf("Missing or unreadable config file (%s)...cannot continue", $filename)."</h1>");
    }
    return $conf;
}


?>