<?php

# cacti script to read stats from NetApp filers.
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
$debug = TRUE;
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
	# See issue 5 for the reasoning behind this.
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
	array_shift($_SERVER["argv"]);
	$result = call_user_func_array("ss_apache", $_SERVER["argv"]);
	if (!$debug) {
		# Throw away the buffer, which ought to contain only errors.
		ob_end_clean();
	} else {
		# In debugging mode, print out the errors.
		ob_end_flush();
	}
	print($result);
}

# ============================================================================
# This is the main function.
# ============================================================================
function ss_apache($hostname, $snmp_auth, $cmd) {
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

	$baseOID = ".1.3.6.1.4.1.19786.1.1";

	$oids = array(
		"hits"	=> $baseOID . ".2.2.0",
		"busy"	=> $baseOID . ".2.3.0",
		"idle"	=> $baseOID . ".2.4.0",
		"agent"	=> $baseOID . ".2.7.0",
		"rps"	=> $baseOID . ".2.8.0",
		"kbps"	=> $baseOID . ".2.9.0",
		"kbpr"	=> $baseOID . ".2.10.0"
	);

	$key = array(
		"hits",
		"busy",
		"idle",
		"agent",
		"rps",
		"kbps",
		"kbpr"
	);

	# Process connection options and connect to MySQL.
	global $debug, $cache_dir, $poll_time;

	# Set up variables
	$status = array(
		"kbtotal"	=> 0,
		"hits"		=> 0,
		"busy"		=> 0,
		"idle"		=> 0,
		"agent"		=> 0,
		"rps"		=> 0,
		"kbps"		=> 0,
		"kbpr"		=> 0
	);

	switch ($cmd) {
		case "workers":
			print "DEBUG: " . $oids["busy"] . "\n";
		#	$status["busy"] = cacti_snmp_get($hostname, $snmp_community, $oids["busy"], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER);
			print "DEBUG: " . $status["busy"] . "\n";
			print "DEBUG: " . $oids["idle"] . "\n";
		#	$status["idle"] = cacti_snmp_get($hostname, $snmp_community, $oids["idle"], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER);
			print "DEBUG: " . $status["idle"] . "\n";
			break;
		case "traffic":
			$status["kbps"] = cacti_snmp_get($hostname, $snmp_community, $oids["kbps"], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER);
			$status["kbpr"] = cacti_snmp_get($hostname, $snmp_community, $oids["kbpr"], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER);
			break;
		default:
			$status[$cmd] = cacti_snmp_get($hostname, $snmp_community, $oids[$cmd], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, read_config_option("snmp_retries"), SNMP_POLLER);
			break;
	}
}

function ss_apache_return_output() {
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
