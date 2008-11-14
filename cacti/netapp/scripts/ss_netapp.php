<?php

# cacti script to read stats from NetApp filers

#$cache_dir = '/var/tmp';	# If set, this uses caching to avoid multiple calls.
$poll_time = 60;			# Adjust to match your polling interval.

# ============================================================================
# You should not need to change anything below this line.
# ============================================================================

# do NOT run this script through a web browser
if(!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD']) || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

# ============================================================================
# Define whether you want debugging behavior.
# ============================================================================
$debug = FALSE;
error_reporting($debug ? E_ALL : E_ERROR);

# Make this a happy little script even when there are errors.
$no_http_headers = true;

# No output, ever.
ini_set('implicit_flush', false);
# Catch all output such as notices of undefined array indexes.
ob_start();

# ============================================================================
# Set up an error handler.
# ============================================================================
function error_handler($errno, $errstr, $errfile, $errline) {
	print("$errstr at $errfile line $errline\n");
}

# ============================================================================
# Set up the stuff we need to be called by the script server.
# ============================================================================
if (file_exists(dirname(__FILE__) . "/../include/global.php")) {
	include_once(dirname(__FILE__) . "/../include/global.php");
} else {
	# Some versions don't have global.php.
	include_once(dirname(__FILE__) . "/../include/config.php");
}

# We'll be using SNMP, so include it.
include_once($config["base_path"] . "/lib/snmp.php");

# ============================================================================
# Make sure we can also be called as a script.
# ============================================================================
if (!isset($called_by_script_server)) {
	if ($debug) {
		print "DEBUG: script arguments\n";
		for ($i=0;($i<sizeof($_SERVER["argv"]));$i++) {
			print "DEBUG: $i = " . $_SERVER["argv"][$i] . "\n";
		}
	}
	array_shift($_SERVER["argv"]);
	# At this point, we've removed our own name from the array, but we still do
	# not know which script we should run, or if we should run them all.  Since
	# we want to be used as a Data Query, we check the first argument for a
	# short name for a function and go from there, or show them all, if the
	# argument is missing.
	if (($_SERVER["argv"][0] == "lun") || ($_SERVER["argv"][0] == "vol")) {
		$statstype = $_SERVER["argv"][0];
		switch ($statstype) {
			case "lun":
				$result = call_user_func_array("ss_netapp_luns", $_SERVER["argv"]);
				break;
			case "vol":
				$result = call_user_func_array("ss_netapp_volumes", $_SERVER["argv"]);
				break;
		}
		if (!$debug) {
			# Throw away the buffer, which ought to contain only errors.
			ob_end_clean();
		} else {
			# In debugging mode, print out the errors.
			ob_end_flush();
		}
		print($result);
	} else {
		$result = call_user_func_array("ss_netapp_luns", $_SERVER["argv"]);
		if (!$debug) {
			ob_end_clean();
		} else {
			ob_end_flush();
		}
		print($result);
		ob_start();
		$result = call_user_func_array("ss_netapp_volumes", $_SERVER["argv"]);
		if (!$debug) {
			ob_end_clean();
		} else {
			ob_end_flush();
		}
		print($result);
	}
}

# ============================================================================
# This function gets the statistics for each LUN on the filer.  This will need
# either a FCP license or an iSCSI license.
# ============================================================================
function ss_netapp_luns($statstype = "", $hostname, $snmp_auth, $cmd, $arg1 = "", $arg2 = "") {
	$snmp						= explode(":", $snmp_auth);
	$snmp_version				= $snmp[0];
	$snmp_port					= $snmp[1];
	$snmp_timeout				= $snmp[2];

	$snmp_auth_username			= "";
	$snmp_auth_password			= "";
	$snmp_auth_protocol			= "";
	$snmp_priv_passphrase		= "";
	$snmp_priv_protocol			= "";
	$snmp_context				= "";
	$snmp_community				= "";

	if ($snmp_version == 3) {
		$snmp_auth_username		= $snmp[4];
		$snmp_auth_password		= $snmp[5];
		$snmp_auth_protocol		= $snmp[6];
		$snmp_priv_passphrase	= $snmp[7];
		$snmp_priv_protocol		= $snmp[8];
		$snmp_context			= $snmp[9];
	} else {
		$snmp_community			= $snmp[3];
	}
	$baseOID = ".1.3.6.1.4.1.789";

	$lunCount = $baseOID . ".1.17.15.1.0";
	$lunTable = $baseOID . ".1.17.15.2";
	$oids = array(
		"index"		=> $lunTable . ".1.1",
		"name"		=> $lunTable . ".1.2",
		"descr"		=> $lunTable . ".1.3",
		"lsize"		=> $lunTable . ".1.4",
		"hsize"		=> $lunTable . ".1.5",
		"hops"		=> $lunTable . ".1.9",
		"lops"		=> $lunTable . ".1.10",
		"hrbytes"	=> $lunTable . ".1.11",
		"lrbytes"	=> $lunTable . ".1.12",
		"hwbytes"	=> $lunTable . ".1.13",
		"lwbytes"	=> $lunTable . ".1.14",
		"herrors"	=> $lunTable . ".1.15",
		"lerrors"	=> $lunTable . ".1.16",
		"hrops"		=> $lunTable . ".1.22",
		"lrops"		=> $lunTable . ".1.23",
		"hwops"		=> $lunTable . ".1.24",
		"lwops"		=> $lunTable . ".1.25",
		"hoops"		=> $lunTable . ".1.26",
		"loops"		=> $lunTable . ".1.27"
	);

	# Define the variables to output for each graph.
	$keys = array(
		"index",
		"name",
		"size", # lsize + hsize
		"ops", # hops + lops
		"rops", # hrops + lrops
		"wops", # hwops + lwops
		"oops", # hoops + loops
		"rbytes", # hrbytes + lrbytes
		"wbytes", # hwbytes + lwbytes
		"errors" # herrors + lerrors
	);

	# Process connection options and connect to MySQL.
	global $debug, $cache_dir, $poll_time;

	# Set up variables
	$status = array(
		"size"		=> 0,
		"ops"		=> 0,
		"rops"		=> 0,
		"wops"		=> 0,
		"oops"		=> 0,
		"rbytes"	=> 0,
		"wbytes"	=> 0,
		"errors"	=> 0
	);

	if ($cmd == "num_indexes") {
		print cacti_snmp_get($hostname, $snmp_community, $lunCount, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol,$snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER) . "\n";
	} elseif ($cmd == "index") {
		$return_arr = ss_netapp_reindex(cacti_snmp_walk($hostname, $snmp_community, $oids["index"], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER));
		for ($i=0;($i<sizeof($return_arr));$i++) {
			print $return_arr[$i] . "\n";
		}
	} elseif ($cmd == "query") {
		$arg = $arg1;
		$arr_index = ss_netapp_reindex(cacti_snmp_walk($hostname, $snmp_community, $oids["index"], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER));
		switch ($arg) {
			case "index":
				$arr = ss_netapp_reindex(cacti_snmp_walk($hostname, $snmp_community, $oids[$arg], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER));
				break;
			case "name":
				$arr = ss_netapp_reindex(cacti_snmp_walk($hostname, $snmp_community, $oids[$arg], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER));
				break;
			default:
				$arr = ss_netapp_add_high_low($cmd, $arg, $hostname, $snmp_community, $oids, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol,$snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout);
				break;
		}
		for ($i=0;($i<sizeof($arr_index));$i++) {
			print $arr_index[$i] . "!" . $arr[$i] . "\n";
		}
	} elseif ($cmd == "get") {
		$arg = $arg1;
		$index = $arg2;
		switch ($arg) {
			case "name":
				return cacti_snmp_get($hostname, $snmp_community, $oids[$arg] . ".$index", $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER);
				break;
			default:
				return ss_netapp_add_high_low($cmd, $arg, $hostname, $snmp_community, $oids, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $index);
		}
	}
}

# ============================================================================
# This function pulls volume statistics.  It uses the high and low counters to
# be more accurate.  The shared and saved stats require an A-SIS license.
# ============================================================================
function ss_netapp_volumes($statstype = "", $hostname, $snmp_auth, $cmd, $arg1 = "", $arg2 = "") {
	$snmp						= explode(":", $snmp_auth);
	$snmp_version				= $snmp[0];
	$snmp_port					= $snmp[1];
	$snmp_timeout				= $snmp[2];

	$snmp_auth_username			= "";
	$snmp_auth_password			= "";
	$snmp_auth_protocol			= "";
	$snmp_priv_passphrase		= "";
	$snmp_priv_protocol			= "";
	$snmp_context				= "";
	$snmp_community				= "";

	if ($snmp_version == 3) {
		$snmp_auth_username		= $snmp[4];
		$snmp_auth_password		= $snmp[5];
		$snmp_auth_protocol		= $snmp[6];
		$snmp_priv_passphrase	= $snmp[7];
		$snmp_priv_protocol		= $snmp[8];
		$snmp_context			= $snmp[9];
	} else {
		$snmp_community			= $snmp[3];
	}
	$baseOID = ".1.3.6.1.4.1.789";

	$volTable = $baseOID . ".1.5.4.1";
	$voloids = array(
		"index"			=> $volTable . ".1",
		"name"			=> $volTable . ".2",
		"usedper"		=> $volTable . ".6",
		"iused"			=> $volTable . ".7",
		"ifree"			=> $volTable . ".8",
		"iusedper"		=> $volTable . ".9",
		"ffree"			=> $volTable . ".11",
		"fused"			=> $volTable . ".12",
		"fposs"			=> $volTable . ".13",
		"hkbtotal"		=> $volTable . ".14",
		"lkbtotal"		=> $volTable . ".15",
		"hkbused"		=> $volTable . ".16",
		"lkbused"		=> $volTable . ".17",
		"hkbfree"		=> $volTable . ".18",
		"lkbfree"		=> $volTable . ".19",
		"type"			=> $volTable . ".23",
		"hkbshared"		=> $volTable . ".24",
		"lkbshared"		=> $volTable . ".25",
		"hkbsaved"		=> $volTable . ".26",
		"lkbsaved"		=> $volTable . ".27",
		"savedper"		=> $volTable . ".28",
		"kbtotal64"		=> $volTable . ".29",
		"kbused64"		=> $volTable . ".30",
		"kbfree64"		=> $volTable . ".31",
		"kbshared64"	=> $volTable . ".32",
		"kbsaved64"		=> $volTable . ".33"
	);

	# Define the variables to output for each graph.
	$volkeys = array(
		"index",
		"name",
		"usedper",
		"iused",
		"ifree",
		"iusedper",
		"ffree",
		"fused",
		"fposs",
		"kbtotal", # hkbtotal + lkbtotal -or- kbtotal64
		"kbused", # hkbused + lkbused -or- kbused64
		"kbfree", # hkbfree + lkbfree -or- kbfree64
		"kbshared", # hkbshared + lkbshared -or- kbshared64
		"kbsaved", # hkbsaved + lkbsaved -or- kbsaved64
		"savedper"
	);

	# Process connection options and connect to MySQL.
	global $debug, $cache_dir, $poll_time;

	# Set up variables
	$volstatus = array(
		"usedper"	=> 0,
		"iused"		=> 0,
		"ifree"		=> 0,
		"iusedper"	=> 0,
		"ffree"		=> 0,
		"fused"		=> 0,
		"fposs"		=> 0,
		"kbtotal"	=> 0,
		"kbused"	=> 0,
		"kbfree"	=> 0,
		"kbshared"	=> 0,
		"kbsaved"	=> 0,
		"savedper"	=> 0
	);

	if ($cmd == "index") {
		$return_arr = ss_netapp_reindex(cacti_snmp_walk($hostname, $snmp_community, $voloids["index"], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER));
		for ($i=0;($i<sizeof($return_arr));$i++) {
			print $return_arr[$i] . "\n";
		}
	} elseif ($cmd == "query") {
		$arg = $arg1;
		$arr_index = ss_netapp_reindex(cacti_snmp_walk($hostname, $snmp_community, $voloids["index"], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER));
		switch ($arg) {
			case "kbtotal":
			case "kbused":
			case "kbfree":
			case "kbshared":
			case "kbsaved":
				if ($snmp_version > 1) {
					$arr = ss_netapp_reindex(cacti_snmp_walk($hostname, $snmp_community, $voloids[$arg . "64"], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER));
				} else {
					$arr = ss_netapp_add_high_low($cmd, $arg, $hostname, $snmp_community, $voloids, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol,$snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout);
				}
				break;
			default:
				$arr = ss_netapp_reindex(cacti_snmp_walk($hostname, $snmp_community, $voloids[$arg], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER));
				break;
		}
		for ($i=0;($i<sizeof($arr_index));$i++) {
			print $arr_index[$i] . "!" . $arr[$i] . "\n";
		}
	} elseif ($cmd == "get") {
		$arg = $arg1;
		$index = $arg2;
		switch ($arg) {
			case "kbtotal":
			case "kbused":
			case "kbfree":
			case "kbshared":
			case "kbsaved":
				if ($snmp_version > 1) {
					return cacti_snmp_get($hostname, $snmp_community, $voloids[$arg . "64"] . ".$index", $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER);
				} else {
					return ss_netapp_add_high_low($cmd, $arg, $hostname, $snmp_community, $voloids, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $index);
				}
				break;
			default:
				return cacti_snmp_get($hostname, $snmp_community, $voloids[$arg] . ".$index", $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER);
				break;
		}
	}
}

# ============================================================================
# This function is a stub.
# ============================================================================
function ss_netapp_cifs($hostname, $snmp_auth) {
	$snmp						= explode(":", $snmp_auth);
	$snmp_version				= $snmp[0];
	$snmp_port					= $snmp[1];
	$snmp_timeout				= $snmp[2];

	$snmp_auth_username			= "";
	$snmp_auth_password			= "";
	$snmp_auth_protocol			= "";
	$snmp_priv_passphrase		= "";
	$snmp_priv_protocol			= "";
	$snmp_context				= "";
	$snmp_community				= "";

	if ($snmp_version == 3) {
		$snmp_auth_username		= $snmp[4];
		$snmp_auth_password		= $snmp[5];
		$snmp_auth_protocol		= $snmp[6];
		$snmp_priv_passphrase	= $snmp[7];
		$snmp_priv_protocol		= $snmp[8];
		$snmp_context			= $snmp[9];
	} else {
		$snmp_community			= $snmp[3];
	}
	$baseOID = ".1.3.6.1.4.1.789";

	$oids = array();
	$keys = array();
	$status = array();

	return $status;
}

# ============================================================================
# This function gets the global FCP access statistics from a filer.  It
# requires an FCP access license to get anything meaningful.
# ============================================================================
function ss_netapp_fcp($hostname, $snmp_auth) {
	$snmp						= explode(":", $snmp_auth);
	$snmp_version				= $snmp[0];
	$snmp_port					= $snmp[1];
	$snmp_timeout				= $snmp[2];

	$snmp_auth_username			= "";
	$snmp_auth_password			= "";
	$snmp_auth_protocol			= "";
	$snmp_priv_passphrase		= "";
	$snmp_priv_protocol			= "";
	$snmp_context				= "";
	$snmp_community				= "";

	if ($snmp_version == 3) {
		$snmp_auth_username		= $snmp[4];
		$snmp_auth_password		= $snmp[5];
		$snmp_auth_protocol		= $snmp[6];
		$snmp_priv_passphrase	= $snmp[7];
		$snmp_priv_protocol		= $snmp[8];
		$snmp_context			= $snmp[9];
	} else {
		$snmp_community			= $snmp[3];
	}
	$baseOID = ".1.3.6.1.4.1.789";

	$fcpoids = array(
		"lrbytes"	=> $baseOID . ".1.17.3.0",
		"hrbytes"	=> $baseOID . ".1.17.4.0",
		"lwbytes"	=> $baseOID . ".1.17.5.0",
		"hwbytes"	=> $baseOID . ".1.17.6.0",
		"hops"		=> $baseOID . ".1.17.13.0",
		"lops"		=> $baseOID . ".1.17.14.0",
	);

	$fcpkeys = array(
		"rbytes",
		"wbytes",
		"ops"
	);

	$fcpstatus = array(
		"rbytes"	=> 0,
		"wbytes"	=> 0,
		"ops"		=> 0
	);

	for ($keys as $key) {
		$fcpstatus[$key] = ss_netapp_add_high_low($cmd, $arg, $hostname, $snmp_community, $fcpoids, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $index);
	}
	return $fcpstatus;
}

# ============================================================================
# This function is a stub.
# ============================================================================
function ss_netapp_http($hostname, $snmp_auth) {
	$snmp						= explode(":", $snmp_auth);
	$snmp_version				= $snmp[0];
	$snmp_port					= $snmp[1];
	$snmp_timeout				= $snmp[2];

	$snmp_auth_username			= "";
	$snmp_auth_password			= "";
	$snmp_auth_protocol			= "";
	$snmp_priv_passphrase		= "";
	$snmp_priv_protocol			= "";
	$snmp_context				= "";
	$snmp_community				= "";

	if ($snmp_version == 3) {
		$snmp_auth_username		= $snmp[4];
		$snmp_auth_password		= $snmp[5];
		$snmp_auth_protocol		= $snmp[6];
		$snmp_priv_passphrase	= $snmp[7];
		$snmp_priv_protocol		= $snmp[8];
		$snmp_context			= $snmp[9];
	} else {
		$snmp_community			= $snmp[3];
	}
	$baseOID = ".1.3.6.1.4.1.789";

	$oids = array();
	$keys = array();
	$status = array();

	return $status;
}

# ============================================================================
# This function is a stub.
# ============================================================================
function ss_netapp_iscsi($hostname, $snmp_auth) {
	$snmp						= explode(":", $snmp_auth);
	$snmp_version				= $snmp[0];
	$snmp_port					= $snmp[1];
	$snmp_timeout				= $snmp[2];

	$snmp_auth_username			= "";
	$snmp_auth_password			= "";
	$snmp_auth_protocol			= "";
	$snmp_priv_passphrase		= "";
	$snmp_priv_protocol			= "";
	$snmp_context				= "";
	$snmp_community				= "";

	if ($snmp_version == 3) {
		$snmp_auth_username		= $snmp[4];
		$snmp_auth_password		= $snmp[5];
		$snmp_auth_protocol		= $snmp[6];
		$snmp_priv_passphrase	= $snmp[7];
		$snmp_priv_protocol		= $snmp[8];
		$snmp_context			= $snmp[9];
	} else {
		$snmp_community			= $snmp[3];
	}
	$baseOID = ".1.3.6.1.4.1.789";

	$oids = array();
	$keys = array();
	$status = array();

	return $status;
}

# ============================================================================
# This function is a stub.
# ============================================================================
function ss_netapp_nfs($hostname, $snmp_auth) {
	$snmp						= explode(":", $snmp_auth);
	$snmp_version				= $snmp[0];
	$snmp_port					= $snmp[1];
	$snmp_timeout				= $snmp[2];

	$snmp_auth_username			= "";
	$snmp_auth_password			= "";
	$snmp_auth_protocol			= "";
	$snmp_priv_passphrase		= "";
	$snmp_priv_protocol			= "";
	$snmp_context				= "";
	$snmp_community				= "";

	if ($snmp_version == 3) {
		$snmp_auth_username		= $snmp[4];
		$snmp_auth_password		= $snmp[5];
		$snmp_auth_protocol		= $snmp[6];
		$snmp_priv_passphrase	= $snmp[7];
		$snmp_priv_protocol		= $snmp[8];
		$snmp_context			= $snmp[9];
	} else {
		$snmp_community			= $snmp[3];
	}
	$baseOID = ".1.3.6.1.4.1.789";

	$oids = array();
	$keys = array();
	$status = array();

	return $status;
}

# ============================================================================
# This function is used to determine if we are licensed for the requested
# NetApp license.  If the license isn't specified, we check everything that
# we support, which is currently CIFS, FCP, iSCSI, and NFS.
# ============================================================================
function ss_netapp_license($hostname, $snmp_auth, $arg1="") {
	$snmp						= explode(":", $snmp_auth);
	$snmp_version				= $snmp[0];
	$snmp_port					= $snmp[1];
	$snmp_timeout				= $snmp[2];

	$snmp_auth_username			= "";
	$snmp_auth_password			= "";
	$snmp_auth_protocol			= "";
	$snmp_priv_passphrase		= "";
	$snmp_priv_protocol			= "";
	$snmp_context				= "";
	$snmp_community				= "";

	if ($snmp_version == 3) {
		$snmp_auth_username		= $snmp[4];
		$snmp_auth_password		= $snmp[5];
		$snmp_auth_protocol		= $snmp[6];
		$snmp_priv_passphrase	= $snmp[7];
		$snmp_priv_protocol		= $snmp[8];
		$snmp_context			= $snmp[9];
	} else {
		$snmp_community			= $snmp[3];
	}
	$baseOID = ".1.3.6.1.4.1.789";

	$oids = array(
		"nfs"	=> ".1.3.3.1.0",
		"cifs"	=> ".1.7.1.21.0",
		"fcp"	=> ".1.17.1.0",
		"iscsi"	=> ".1.17.2.0"
	);

	$keys = array(
		"cifs",
		"fcp",
		"iscsi",
		"nfs"
	);

	$status = array(
		"cifs"	=> 0,
		"fcp"	=> 0,
		"iscsi"	=> 0,
		"nfs"	=> 0
	);

	$license = $arg1;
	if ($license) {
		foreach ($keys as $key) {
			if ($license == $key) {
				$license_supported = 1;
			}
		}
		if ($license_supported) {
			$status[$key] = cacti_snmp_get($hostname, $snmp_community, $oids[$key], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER);
		} else {
			die("License type not supported.");
		}
	} else {
		foreach ($keys as $key) {
			$status[$key] = cacti_snmp_get($hostname, $snmp_community, $oids[$key], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER);
		}
	}
	return $status;
}

# ============================================================================
# This function takes the highest and lowest significant 32-bits and adds them
# together into a single 64-bit integer.
# ============================================================================
function ss_netapp_add_high_low($type, $arg, $hostname, $snmp_community, $oids, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol,$snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $arg1 = "") {
	if ($type == "query") {
		$high_arr = ss_netapp_reindex(cacti_snmp_walk($hostname, $snmp_community, $oids["h" . $arg], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER));
		$low_arr = ss_netapp_reindex(cacti_snmp_walk($hostname, $snmp_community, $oids["l" . $arg], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER));
		if (sizeof($high_arr) != sizeof($low_arr)) {
			die("High and Low arrays are not equal in size!");
		}
		for ($i=0;($i<sizeof($high_arr));$i++) {
			$return_arr[$i] = (($high_arr[$i] << 32) | $low_arr[$i]);
		}
		return $return_arr;
	} else {
		$index = $arg1;
		if ($index == "") {
			$highoid = $oids["h" . $arg];
			$lowoid = $oids["l" . $arg];
		} else {
			$highoid = $oids["h" . $arg] . ".$index";
			$lowoid = $oids["l" . $arg] . ".$index";
		}
		$high_get = cacti_snmp_get($hostname, $snmp_community, $highoid, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER);
		$low_get = cacti_snmp_get($hostname, $snmp_community, $lowoid, $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER);
		return (($high_get << 32) | $low_get);
	}
}

# ============================================================================
# This function reindexes an array.
# ============================================================================
function ss_netapp_reindex($arr) {
	$return_arr = array();

	for ($i=0;($i<sizeof($arr));$i++) {
		$return_arr[$i] = $arr[$i]["value"];
	}

	return $return_arr;
}

function ss_netapp_return_output() {
	# Return the output.
	$output = array();
	foreach ($keys as $key) {
		$val		= isset($status[$key]) ? $status[$key] : 0;
		$output[] = "$key:$val";
	}
	$result = implode(' ', $output);
	if ( $fp ) {
		if ( fwrite($fp, $result) === FALSE ) {
			die("Cannot write to '$cache_file'");
		}
		fclose($fp);
		run_query("SELECT RELEASE_LOCK('cacti_monitoring')", $conn);
	}
	return $result;
}

# ============================================================================
# Returns SQL to create a bigint from two ulint
# ============================================================================
function ss_netapp_make_bigint_sql ($hi, $lo) {
	return "(($hi << 32) + $lo)";
}

# ============================================================================
# Extracts the numbers from a string.  You can't reliably do this by casting to
# an int, because numbers that are bigger than PHP's int (varies by platform)
# will be truncated.  So this just handles them as a string instead.  Note that
# all bigint math is done by sending values in a query to MySQL!  :-)
# ============================================================================
function ss_netapp_tonum ( $str ) {
	global $debug;
	preg_match('{(\d+)}', $str, $m); 
	if ( isset($m[1]) ) {
		return $m[1];
	}
	elseif ( $debug ) {
		print_r(debug_backtrace());
	}
	else {
		return 0;
	}
}

# ============================================================================
# Wrap mysql_query in error-handling
# ============================================================================
function ss_netapp_run_query($sql, $conn) {
	global $debug;
	$result = @mysql_query($sql, $conn);
	if ( $debug ) {
		$error = @mysql_error($conn);
		if ( $error ) {
			die("Error executing '$sql': $error");
		}
	}
	return $result;
}

?>
