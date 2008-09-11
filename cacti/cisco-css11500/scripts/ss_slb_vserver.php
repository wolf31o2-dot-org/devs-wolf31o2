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
	# Some versions don't have global.php.
	include_once(dirname(__FILE__) . "/../include/config.php");
}

if (!isset($called_by_script_server)) {
        array_shift($_SERVER["argv"]);

        print call_user_func_array("ss_slb_vserver", $_SERVER["argv"]);
}


function ss_slb_vserver($hostname, $host_id, $snmp_auth, $cmd, $arg1 = "", $arg2 = "") {
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
	"FarmName" => ".1.3.6.1.4.1.9.9.161.1.4.1.1.9",
	"NumberOfC" => ".1.3.6.1.4.1.9.9.161.1.4.1.1.17",
	"HCTotal" => ".1.3.6.1.4.1.9.9.161.1.4.1.1.19",
	"IpAddress" => ".1.3.6.1.4.1.9.9.161.1.4.1.1.4",
	"Port" => ".1.3.6.1.4.1.9.9.161.1.4.1.1.5"
        );


if ($cmd == "get") {

 	$target = $oids[$arg1] . "." . $arg2;	
	$value  = cacti_snmp_get($hostname, $snmp_community, $target, $snmp_version, $snmpv3_auth_username, $snmpv3_auth_password, $snmp_port, $snmp_timeout, SNMP_POLLER);
 	print $value . "\n";

}else{

$index_arr = slb_index(cacti_snmp_walk($hostname, $snmp_community, $oids["FarmName"], $snmp_version, $snmpv3_auth_username, $snmpv3_auth_password, $snmp_port, $snmp_timeout, SNMP_POLLER));

$catalyst = explode(".",$hostname);

if ($cmd == "index") {

	for ($i=0;($i<sizeof($index_arr));$i++) {
                print $index_arr[$i]["index"] . "\n";
	}
}elseif ($cmd == "num_indexes") {

	$num_indexes = count($index_arr);
	print "$num_indexes" . "\n";

}elseif ($cmd == "query") {
    $arg = $arg1;

    if ($arg == "VServer") {

        for ($i=0;($i<sizeof($index_arr));$i++) {
                print $index_arr[$i]["index"] . "!" . $index_arr[$i]["vserver"] . "\n";
        }

    }elseif ($arg == "Module") {
	
        for ($i=0;($i<sizeof($index_arr));$i++) {
                print $index_arr[$i]["index"] . "!" . $index_arr[$i]["module"] . "\n";
        }

    }elseif ($arg == "VName") {
        $arg = "IpAddress";
	$arr = reindex(cacti_snmp_walk($hostname, $snmp_community, $oids[$arg],$snmp_version, $snmpv3_auth_username, $snmpv3_auth_password, $snmp_port, $snmp_timeout, SNMP_POLLER));

        for ($i=0;($i<sizeof($index_arr));$i++) {
                $name = explode(".",gethostbyaddr($arr[$i]));
                print $index_arr[$i]["index"] . "!" . $name[0] . "\n";
        }
    }elseif ($arg == "Cata") {

        for ($i=0;($i<sizeof($index_arr));$i++) {
                print $index_arr[$i]["index"] . "!" . $catalyst[0] . "\n";
        }

    }else{
	$arr = reindex(cacti_snmp_walk($hostname, $snmp_community, $oids[$arg],$snmp_version, $snmpv3_auth_username, $snmpv3_auth_password, $snmp_port, $snmp_timeout, SNMP_POLLER));


        for ($i=0;($i<sizeof($index_arr));$i++) {
                print $index_arr[$i]["index"] . "!" . $arr[$i] . "\n";
        }
    }

  }
}
}

function slb_index($arr) {
        $return_arr = array();

        for ($i=0;($i<sizeof($arr));$i++) {
		$vserver = '';
                $arr[$i]["oid"]=trim ($arr[$i]["oid"],".");
		if ( ereg ("1\.3\.6\.1\.4\.1\.9\.9\.161\.1\.4\.1\.1\.9\.([0-9]{1,2})\.([0-9]{1,2})\.(.*)$",$arr[$i]["oid"],$regs)){
                        $return_arr[$i]["module"] = $regs[1];
			$return_arr[$i]["index"] = $return_arr[$i]["module"] . "." . $regs[2] . "." . $regs[3];
			$oid_index = preg_split("/\./",$regs[3]);
                        for ($j=0;($j<sizeof($oid_index));$j++) {
                                $vserver .= chr($oid_index[$j]);
                        }
                        $return_arr[$i]["vserver"] = $vserver;
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

