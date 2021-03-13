<?php

// #######################
// load database stuff
require("config.inc.php");

// we need monolog for logging, install via composer
require("vendor/autoload.php");

// initialize monolog for logging
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// function to increase the serial number to YYYYMMDDXX
function updateSerial($serial) {
	$viererString = str_split($serial,4);
	$zweierString = str_split($viererString[1],2);
	// SerialCut ist Jahr,Monat,Tag,Nummer
	$serialCut = array("Y" => $viererString[0], "m" => $zweierString[0], "d" => $zweierString[1], "n" => $viererString[2]);
	foreach($serialCut as $type => $value) {
		if($type!="n") {
			if($value!=strftime("%".$type)) {
				$serialCut[$type]=strftime("%".$type);
				$serialCut["n"]="01";
				break;
			}
		}
		else {
			$value++;
			$serialCut["n"]=str_pad($value,2,0,STR_PAD_LEFT);
		}
	}
	return $serialCut["Y"].$serialCut["m"].$serialCut["d"].$serialCut["n"];	
}

// create a log channel for log stuff
$log = new Logger('pdnsupd');
$log->pushHandler(new StreamHandler('../log/pdnsupd.log', Logger::DEBUG));

// generate ID for logging
$rand = bin2hex(random_bytes(12));
// build header for monolog
$header = array(
	'id'=>$rand,
	'ip'=>$_SERVER["REMOTE_ADDR"],
	'timestamp'=>time(),
	'query'=>$_SERVER["QUERY_STRING"],
);

// ################################
// start update process
$log->info('Starting session PDNS-Update-Logger',$header);

// new database connection
$mysqli = new mysqli($config["dbhost"], $config["dbuser"], $config["dbpasswd"], $config["dbname"], $config["dbport"]);

// error handling for database
if ($mysqli->connect_error) {
	$log->alert('Can\'t connect to database: '.mysqli_connect_error(),$header);
	exit(1);
}

// check if dyndns hostname is given in query. If so, replace ' to prohibit sql injection
if(isset($_REQUEST["hostname"]) && $_REQUEST["hostname"]!="") $hostname = str_replace("'", "''", $_REQUEST["hostname"]);
else {
	// if hostname is empty, log and exit
	$log->err('Hostname is empty',$header);
	echo "No hostname given!";
	exit(1);
}

// check if password is given and if given password is correct
if(isset($_REQUEST["passwd"]) && $_REQUEST["passwd"]!="") $passwd = str_replace("'", "''", $_REQUEST["passwd"]);
else {
	// if password is empty, log and exit
	$log->err('Password is empty',$header);
	echo "No password given!";
	exit(1);
}

$pwq = $mysqli->query("SELECT * FROM userkey WHERE hostname='$hostname';");
if(mysqli_num_rows($pwq)==0) {
	$log->info('User '.$hostname.' in userkey not found', $header);
	echo "Hostname not found in user table!";
	exit(1);
}
else {
	$pw = $pwq->fetch_array();
	if(password_verify($passwd, $pw["password"])) $log->info('Found correct hostname/password combination in userkey', $header);
	// if password is wrong, log and exit
	else {
		$log->err('Wrong password given',$header);
		echo "Wrong password!";
		exit(1);
	}
}


// the new ip address is the ip of the remote host; set and log
$ip = $_SERVER["REMOTE_ADDR"];
$log->info('Found '.$ip.' for '.$hostname.'. Updating record now.',$header); 

// check if the given hostname has an A record in the database
$selectsql = "SELECT * FROM records WHERE name='$hostname' AND type='A'";
$selectq = $mysqli->query($selectsql);
if (mysqli_num_rows($selectq)!=1) {
	$log->err('Found none or more than one A record for '.$hostname,$header);
	exit(1);
}
else {
	// if the hostname is in database, update the domain A record
	$sql = "UPDATE records SET content='$ip', change_date='".time()."' WHERE name='$hostname' AND type='A'";
	if($mysqli->query($sql)===true) {
		// log
		$log->info('OK updated A record for '.$hostname.' to '.$ip,$header);
		// stuff for dyndns clients (HTTP 200 and "good IP")
		header("HTTP/1.1 200 OK", true, 200);
		echo "good $ip";

		// write timestamp for monitoring
		$handle = fopen ("../log/pdnsupd_$hostname.log", "w");
		if(fwrite ($handle, time())) $log->info('Timestamp written',$header);
		else $log->err('Timestamp not written',$header);
		fclose ($handle);

		$domain = substr($hostname,strpos($hostname,".")+1);

		// write new serial number
		$soaq = $mysqli->query("SELECT * FROM records WHERE type='SOA' AND name='$domain'");
		if(mysqli_num_rows($soaq)!=1) $log->err('Can\'t get SOA record for '.$hostname,$header);
		else {
			// the SOA record persists of more than the serial number, so we have to cut the string into pieces and find die serial
			$csr = $soaq->fetch_array();
			$soa[] = explode(" ",$csr["content"]);
			// there it is
			$serial = $soa[0][2];
			$soa[0][2] = updateSerial($soa[0][2]);
			$log->info('Got new serial number for '.$domain.', incrementing '.$serial.' to '.$soa[0][2], $header);
			$return = "";
			// rebuild SOA record out of the array
			foreach($soa[0] as $key => $val) {
				$return .= $val." ";
			}
			// update and log
			if($mysqli->query("UPDATE records SET content='".trim($return)."' WHERE type='SOA' AND name='$domain'")) $log->info('OK updated SOA record for '.$domain.', set serial to '.$soa[0][2].' with SOA record: \''.trim($return).'\'', $header);
			else $log->err('Can\'t update SOA record for '.$domain,$header);
		}
	}

	else {
		$log->err('Can\'t update A record for '.$hostname.' to '.$ip.' with '.$sql,$header);
		exit(1);
	}
}

// close logging session
$log->info('Ending session PDNS-Update-Logger',$header);

// and done :-)
?>

