<?php
define('SYSTEMPATH', '../../fsgapi/system/');

require_once(SYSTEMPATH.'FsgApi.class.php');
require_once(SYSTEMPATH.'config/fsgapi-common.php');

header('Content-Type: application/json');

try {
    $pdo = new PDO(DB_URI, DB_USER, DB_PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
    $fsgapi = new FsgApi($pdo);
    if(!function_exists('json_decode')) {
        throw new Exception('FsgApi needs the JSON PHP extension.');
    }
}
catch(Exception $ex) {
    header('500 Internal Server Error');
    echo json_encode(array('error' => 'Internal connection error'));
    exit();
}
if(isset($_REQUEST['type']) && isset($_REQUEST['params'])) {
    $params = json_decode($_REQUEST['params'], true);
    $from = isset($_REQUEST['from']) ? $_REQUEST['from'] : 0;    
    $until = isset($_REQUEST['until']) ? $_REQUEST['until'] : 0;
    $results = $fsgapi->getData($_REQUEST['type'], $params, $from, $until);
    echo json_encode($results, JSON_NUMERIC_CHECK|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
else {
    echo json_encode(array('error' => 'Missing type or parameter'));
}
?>