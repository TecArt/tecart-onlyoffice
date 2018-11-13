<?php

/*
 * @link https://api.onlyoffice.com/editors/callback
 *
 * Status: 0: Document not found
 *         1: Document is in Edit mode
 *         2: Document ready for saving (after closing the window)
 *         3: Saving error in onlyoffice
 *         4: Document closed with no changes
 *         5: --
 *         6: Document saved, window still open
 *         7: Error while forced saving
 */


define("CRM_CLASS_PATH", dirname(dirname(dirname(__FILE__))));

require_once CRM_CLASS_PATH.'/class/api/crmapi.class.php';
require_once CRM_CLASS_PATH."/class/dao/dao.class.php";

$logon = crmapi::logon();

$query_string = $_SERVER['QUERY_STRING'];

$cache = object::get('cache');
$unique_id = $query_string;
$data = $cache->{'fvoo_'.$unique_id};

$postdata = file_get_contents("php://input");
$postdata = json_decode($postdata, true);

if (!$data) {
    log_error(date('c'), 'onlyoffice.log');
    log_error('No Cache Data', 'onlyoffice.log');
    log_error($query_string, 'onlyoffice.log');
    log_error($data, 'onlyoffice.log');
    log_error($postdata, 'onlyoffice.log');
    log_error('--------------', 'onlyoffice.log');
    exit;
}
if (!$data['write']) {
    log_error(date('c'), 'onlyoffice.log');
    log_error('No Write Permission', 'onlyoffice.log');
    log_error($query_string, 'onlyoffice.log');
    log_error($data, 'onlyoffice.log');
    log_error($postdata, 'onlyoffice.log');
    log_error('--------------', 'onlyoffice.log');
    exit;
}

$logon->Ping($data['sid']);

$path = $data['write_path'];

if ($postdata['status'] == 4) {
    // Closed without changing
    $document = object::get('document', $path);
    $document->update_all(array('edit' => 1));
    $cache->{'document_edit_'.$document->id} = 1;
    echo '{"error": 0}';
    exit;
}

// Status 2 = Closed with changes
// Status 6 = Saved
if ($postdata['status'] != 2 && $postdata['status'] != 6) {
    echo '{"error": 0}';
    exit;
}

$document = object::get('document', $path);
$tmp_path = $GLOBALS['config']['data_paths']['doc_tmp'].$document->edittmp;
if (!$document->edittmp) {
    log_error(date('c'), 'onlyoffice.log');
    log_error('No edittmp', 'onlyoffice.log');
    log_error($query_string, 'onlyoffice.log');
    log_error($data, 'onlyoffice.log');
    log_error($postdata, 'onlyoffice.log');
    log_error('--------------', 'onlyoffice.log');
    exit;
}

//Datei zurückholen
$downloadUri = $postdata["url"];
if (strpos($downloadUri, $GLOBALS['config']['office_server_domain']) !== 0) {
    trigger_error('invalid uri');
    log_error(date('c'), 'onlyoffice.log');
    log_error('Invalid URI', 'onlyoffice.log');
    log_error($query_string, 'onlyoffice.log');
    log_error($data, 'onlyoffice.log');
    log_error($postdata, 'onlyoffice.log');
    log_error('--------------', 'onlyoffice.log');
    echo 'Bad Request';
    exit;
}

if (($new_data = fops::file_get_contents($downloadUri))===FALSE){
    trigger_error('no content');
    log_error(date('c'), 'onlyoffice.log');
    log_error('No Content', 'onlyoffice.log');
    log_error($query_string, 'onlyoffice.log');
    log_error($data, 'onlyoffice.log');
    log_error($postdata, 'onlyoffice.log');
    log_error('--------------', 'onlyoffice.log');
    echo "Bad Response";
    exit;
}

if (fops::file_put_contents($tmp_path, $new_data)===FALSE){
    trigger_error('could not write');
    log_error(date('c'), 'onlyoffice.log');
    log_error('Could not write', 'onlyoffice.log');
    log_error($query_string, 'onlyoffice.log');
    log_error($data, 'onlyoffice.log');
    log_error($postdata, 'onlyoffice.log');
    log_error('--------------', 'onlyoffice.log');
    echo "Bad Response";
    exit;
}

if ($postdata['status'] == 2) {
    $document->update_all(array('edit' => 1, 'ctime' => time()));
    $cache->{'fvoo_'.$unique_id} = null;
    $cache->{'document_edit_'.$document->id} = 1;
}

echo '{"error": 0}';
