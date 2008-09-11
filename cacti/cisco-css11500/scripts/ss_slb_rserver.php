<?php

$no_http_headers = true;

// called by spine
// Query Cisco CSM board
// originally by Daniel.Grandjean@epfl.ch
// edited by Chris Gianelloni <wolf31o2@wolf31o2.org>

/* display No errors */
error_reporting(E_ERROR);

include_once(dirname(__FILE__) . "/../lib/snmp.php");

if (file_exists(dirname(__FILE__) . "/../include/global.php")) {
	include_once(dirname(__FILE__) . "/../include/global.php");
} else {
	# Some versions don't have global.php
	include_once(dirname(__FILE__) . "/../include/config.php");
}

if (!isset($called_by_script_server)) {
        array_shift($_SERVER["argv"]);

        print call_user_func_array("ss_slb_rserver", $_SERVER["argv"]);
}


function ss_slb_rserver($hostname, $host_id, $snmp_auth, $cmd, $arg1 = "", $arg2 = "") {
        $snmp = explode(":", $snmp_auth);
        $snmp_version = $snmp[0];
        $snmp_port = $snmp[1];
        $snmp_timeout = $snmp[2];

        $snmpv3_auth_username = "";
        $snmpv3_auth_password = "";
        $snmpv3_auth_protocol = "";
        $snmpv3_priv_passphrase = "";
        $snmpv3_priv_protocol = "";
        $snmp_community = "";

        if ($snmp_version == 3) {
                $snmpv3_auth_username = $snmp[4];
                $snmpv3_auth_password = $snmp[5];
                $snmpv3_auth_protocol = $snmp[6];
                $snmpv3_priv_passphrase = $snmp[7];
                $snmpv3_priv_protocol = $snmp[8];
        }else{
                $snmp_community = $snmp[3];
        }


$oids = array(
	"State" => ".1.3.6.1.4.1.9.9.161.1.3.1.1.4",
	"HCTotal" => ".1.3.6.1.4.1.9.9.161.1.3.1.1.19",
	"TotalFails" => ".1.3.6.1.4.1.9.9.161.1.3.1.1.16",
	"ConsecutiveFails" => ".1.3.6.1.4.1.9.9.161.1.3.1.1.15",
	"NumberOfC" => ".1.3.6.1.4.1.9.9.161.1.3.1.1.5",
	"MaxC" => ".1.3.6.1.4.1.9.9.161.1.3.1.1.7"
        );



if ($cmd == "get") {

	$target = $oids[$arg1] . "." . $arg2;
	$value = cacti_snmp_get($hostname, $snmp_community, $target, $snmp_version, $snmpv3_auth_username, $snmpv3_auth_password, $snmp_port, $snmp_timeout, SNMP_POLLER);
        print $value . "\n";

}else{

$index_array = slb_rindex(cacti_snmp_walk($hostname, $snmp_community, $oids["State"], $snmp_version, $snmpv3_auth_username, $snmpv3_auth_password, $snmp_port, $snmp_timeout, SNMP_POLLER));

$catalyst = explode(".",$hostname);

if ($cmd == "index") {

	for ($i=0;($i<sizeof($index_array));$i++) {
                print $index_array[$i]["index"] . "\n";
	}
}elseif ($cmd == "num_indexes") {

	$num_indexes = sizeof($index_array);
	print "$num_indexes" . "\n";

}elseif ($cmd == "query") {
    $arg = $arg1;

    if ($arg == "RServer") {

        for ($i=0;($i<sizeof($index_array));$i++) {
                print $index_array[$i]["index"] . "!" . $index_array[$i]["ip"] . "\n";
        }

    }elseif ($arg == "ID") {

        for ($i=0;($i<sizeof($index_array));$i++) {
                print $index_array[$i]["index"] . "!" . $index_array[$i]["index"] . "\n";
        }

    }elseif ($arg == "Module") {
	
        for ($i=0;($i<sizeof($index_array));$i++) {
                print $index_array[$i]["index"] . "!" . $index_array[$i]["module"] . "\n"; 
        }

    }elseif ($arg == "RName") {
	
        for ($i=0;($i<sizeof($index_array));$i++) {
		$name = explode(".",gethostbyaddr($index_array[$i]["ip"]));
                print $index_array[$i]["index"] . "!" . $name[0] . "\n"; 
        }

    }elseif ($arg == "Port") {
	
        for ($i=0;($i<sizeof($index_array));$i++) {
                print $index_array[$i]["index"] . "!" . $index_array[$i]["port"] . "\n"; 
        }

    }elseif ($arg == "Cata") {
	
        for ($i=0;($i<sizeof($index_array));$i++) {
                print $index_array[$i]["index"] . "!" . $catalyst[0] . "\n"; 
        }

    }elseif ($arg == "IX") {
	
        for ($i=0;($i<sizeof($index_array));$i++) {
                print $index_array[$i]["index"] . "!" . $index_array[$i]["ix"] . "\n"; 
        }

    }else{
	$arr = reindex(cacti_snmp_walk($hostname, $snmp_community, $oids[$arg],$snmp_version, $snmpv3_auth_username, $snmpv3_auth_password, $snmp_port, $snmp_timeout, SNMP_POLLER));


        for ($i=0;($i<sizeof($index_array));$i++) {
                print $index_array[$i]["index"] . "!" . $arr[$i] . "\n";
        }
    }

  }
}
}
function slb_rindex($arr) {
        $return_arr = array();


        for ($i=0;($i<sizeof($arr));$i++) {
		$rserver = '';
                $arr[$i]["oid"]=trim ($arr[$i]["oid"],".");

		if ( ereg ("1\.3\.6\.1\.4\.1\.9\.9\.161\.1\.3\.1\.1\.4\.([0-9]{1,2})\.([0-9]{1,2})\.(.*)\.([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\.([0-9]{1,5})$",$arr[$i]["oid"],$regs)){
			$oid_index = preg_split("/\./",$regs[3]);
			for ($j=0;($j<sizeof($oid_index));$j++) {
				$rserver .= chr($oid_index[$j]);
			}
			$return_arr[$i]["ip"] = $regs[4];
			$return_arr[$i]["port"] = $regs[5];
			$return_arr[$i]["rserver"] = $rserver;
			$return_arr[$i]["module"] = $regs[1];
			$return_arr[$i]["ix"] = $regs[2];
			$return_arr[$i]["index"] = $regs[1] . "." . $regs[2] . "." . $regs[3] . "." . $regs[4] . "." . $regs[5];
		} else {
		}		
        }
	//echo "\n";
        return $return_arr;
}
	
function reindex($arr) {
        $return_arr = array();

        for ($i=0;($i<sizeof($arr));$i++) {
                $return_arr[$i] = $arr[$i]["value"];
        }

        return $return_arr;
}

?>

