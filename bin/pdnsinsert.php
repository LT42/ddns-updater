<?php

// verifing cli parameters are given
$parameter = getopt("h:p:");
if($parameter["h"]=="") {
	echo "DDNS-PWUpdater\nUsage ./password_insertion.php -h HOSTNAME -p PASSWORD\n\n";	
	echo "ERR: no hostname given";
	exit(1);
}

if($parameter["p"]=="") {
	echo "DDNS-PWUpdater\nUsage ./password_insertion.php -h HOSTNAME -p PASSWORD\n\n";	
	echo "ERR: no password given";
	exit(1);
}

$hostname = str_replace("'", "''", $parameter["h"]);
$password = str_replace("'", "''", str_replace('"', '', $parameter["p"]));

// #######################
// load database stuff
require("../config.inc.php");


// new database connection
$mysqli = new mysqli($config["dbhost"], $config["dbuser"], $config["dbpasswd"], $config["dbname"], $config["dbport"]);

// error handling for database
if ($mysqli->connect_error) {
	echo 'Can\'t connect to database: '.mysqli_connect_error();
	exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
// verify hash
if(password_verify($password, $hash)) echo "Hash verified.\n";
else {
	echo "Hash verification failed. Exiting.";
	exit(1);
}

$hostq = $mysqli->query("SELECT * FROM userkey WHERE hostname='$hostname';");
if(mysqli_num_rows($hostq)==0) {
	$sql = "INSERT INTO userkey (hostname, password) values ('$hostname', '$hash');";
}
else {
	$sql = "UPDATE userkey SET password='$hash' WHERE hostname='$hostname'";
}

if($mysqli->query($sql)===true)  {
	echo "Password updated.";
	exit(0);
}

?>
