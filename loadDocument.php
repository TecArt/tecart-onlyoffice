<?php

if (!defined( "CRM_EXEC_START" )) {
	define("CRM_EXEC_START", microtime(true));
}

if (!isset($_SERVER['QUERY_STRING']) || !trim($_SERVER['QUERY_STRING'])) exit;

define("CRM_CLASS_PATH", dirname(dirname(dirname(__FILE__))));
require_once CRM_CLASS_PATH.'/class/api/crmapi.class.php';
require_once CRM_CLASS_PATH.'/class/dao/fops.class.php';
require_once CRM_CLASS_PATH.'/class/dao/dates.class.php';
$logon = crmapi::logon();

/*
 * Query-String-Format: pfadZurDatei&dateiname&[attach]&[parse]&[session_id]
 *
 */
$query_string_arr = explode('&',$_SERVER['QUERY_STRING']);

$cache = object::get('cache');
$unique_id = $query_string_arr[1];
$data = $cache->{'fvoo_'.$unique_id};

if (!$data['read']) exit;

$session_id = $data['sid'];

$data['read'] = false;
$cache->set_lifetime(dates::DAY_SEC);
$cache->{'fvoo_'.$unique_id} = $data;

$logon->Ping($session_id);
$query = $data['read_path'];

$local_stream = false;
$mem_limit = MEM_LIMIT - (50 * 1048576);

//Lokale Datei
if (fops::file_exists($query)) {
	$query   = urldecode($query);
	$fsize = fops::filesize($query);
	if ($fsize > $mem_limit) {
		$local_stream = true;
	} else {
		$content = fops::file_get_contents($query);
	}
} else {
	exit;
}


// Ausgabe
if ($local_stream) {

	$fhandle = fopen($query, 'r');
	while ($content = fread($fhandle, 2048)) {
		echo $content;
	}
	fclose($fhandle);

} else {
	echo $content;
}

exit;

