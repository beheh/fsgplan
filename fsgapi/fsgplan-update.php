<?php

if(!defined('SYSTEMPATH')) define('SYSTEMPATH', 'system/');

require_once(SYSTEMPATH.'FsgApi.class.php');
require_once(SYSTEMPATH.'FsgPlan.class.php');
require_once(SYSTEMPATH.'ApiFacebook.php');
require_once(SYSTEMPATH.'config/fsgapi-common.php');
require_once(SYSTEMPATH.'config/fsgplan-facebook.php');

$pdo = new PDO(DB_URI, DB_USER, DB_PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
$fsgapi = new FsgApi($pdo);
$fsgplan = new FsgPlan($pdo);
$daynames = array('Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag');

$facebook = false;
if($fsgapi->getConfig('fsgapi_changes_on') > $fsgapi->getConfig('fsgplan_update_on')) {
	echo 'changes found, updating requests'.PHP_EOL;
	$facebook = new ApiFacebook(array('appId' => FB_APP_ID, 'secret' => FB_APP_SECRET, 'cookie' => true));

	$users = $fsgplan->getUsers(true);
	$batches = array();
	$batch = array();
	foreach($users as $fsguser) {
		echo 'started handling user '.$fsguser['id'].PHP_EOL;
		
		$entries = array();
		$changes = $fsgapi->getData('FSGAPIDATA_CHANGES', $fsguser, $_SERVER['REQUEST_TIME'], 0, $fsguser['update']);
		foreach($changes as $change) {
		    if($change['expires'] < $_SERVER['REQUEST_TIME']) continue;
			$entries[$change['id']] = array('message' => $daynames[date('w', $change['time'])-1].', '.FsgPlan::makeChangeString($change), 'expires' => $change['expires']);
		}

		foreach($entries as $id => $change) {
			echo 'scheduled request '.count($batch).' in batch '.count($batches).PHP_EOL;
			$batch[] = array('user' => $fsguser['id'], 'expires' => $change['expires'], 'url' => $fsguser['id'].'/apprequests?message='.urlencode(htmlspecialchars($change['message'])).'&data='.$id);
			if(count($batch) >= FB_API_BATCH_SIZE) {
				$batches[] = $batch;
				$batch = array();
			}
		}
	}
	if($batch) {
		$batches[] = $batch;
	}
	$fsgplan->updateUsers(true);
	echo 'scheduling complete, submitting '.count($batches).' batches'.PHP_EOL;
	foreach($batches as $i => $batch) {
		echo 'started handling batch '.$i.' with '.count($batch).' entries'.PHP_EOL;
		$queries = array();
		foreach($batch as $request) {
			$query = array('method' => 'POST', 'relative_url' => $request['url']);
			$queries[] = $query;
		}
		$result = false;
		try {
			$results = $facebook->api('/', 'POST', array('batch' => json_encode($queries)));
		}
		catch(Exception $ex) {
			log_error($ex);
		}
		foreach($results as $j => $result) {
			if($result['code'] == 200) {
			    $request = json_decode($result['body'], true);
				echo 'request for user '.$batch[$j]['user'].' added, inserting result '.trim($request['request'], '"').' to db'.PHP_EOL;
				$fsgplan->scheduleRequest(trim($request['request'], '"'), $batch[$j]['user'], $batch[$j]['expires']);
			}
			else {
				$errors++;
				$body = json_decode($result['body'], true);
				if(isset($body['error'])) {
					error_log('error on user '.$batch[$j]['user'].': '.$body['error']['message']);
				}
				else {
				    if(isset($result['code'])) {
    					error_log('unreported error on user '.$batch[$j]['user'].', but no error body (http code '.$result['code'].')');
    				}
    				else {
    				    error_log('unrecognized error on user '.$batch[$j]['user'].', result is '.print_r($result, true));
    				}
				}
			}
		}
	}
    echo 'request submitting complete'.PHP_EOL;
	echo 'updating timestamp'.PHP_EOL;
	$fsgapi->setConfig('fsgplan_update_on', $fsgapi->getConfig('fsgapi_changes_on'));
}
else {
	echo 'no changes registered, requests are up to date'.PHP_EOL;
    if($fsgapi->getConfig('fsgapi_changes_valid') < $_SERVER['REQUEST_TIME']+24*60*60) {
        echo 'warning: approaching end of validity'.PHP_EOL;
    }
}

/*echo 'removing expired requests'.PHP_EOL;
$requests = $fsgplan->getExpiredRequests();
$batches = array();
$batch = array();
$count = 0;
foreach($requests as $request) {
    $batch[$request['id']] = $request['user'];
    $count++;
    if(count($batch) >= FB_API_BATCH_SIZE) {
        $batches[] = $batch;
        $batch = array();
    }
}
if($batch) {
    $batches[] = $batch;
}
if(count($batches) && !$facebook) {
	$facebook = new ApiFacebook(array('appId' => FB_APP_ID, 'secret' => FB_APP_SECRET, 'cookie' => true));
}
foreach($batches as $batch) {
    $queries = array();
    foreach($batch as $request) {
        $query = array('method' => 'DELETE', 'relative_url' => '/'.$request['id'].'_'.$request['user']);
        $queries[] = $query;
    }
    $result = $facebook->api('/', 'POST', array('batch' => json_encode($queries)));    
}
echo 'removed '.$count.' expired request(s)'.PHP_EOL;*/

$facebook = null;
$pdo = null;
?>
