<?php

if(!defined('SYSTEMPATH')) define('SYSTEMPATH', 'system/');

require_once(SYSTEMPATH.'FsgApi.class.php');
require_once(SYSTEMPATH.'ApiFacebook.php');
require_once(SYSTEMPATH.'config/fsgapi-common.php');
require_once(SYSTEMPATH.'config/fsgplan-facebook.php');

$pdo = new PDO(DB_URI, DB_USER, DB_PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
$fsgapi = new FsgApi($pdo);

/* Vertretungsplan */

echo 'fetching html from www.fsg-marbach.de'.PHP_EOL;
$con = curl_init();
curl_setopt($con, CURLOPT_URL, 'http://www.fsg-marbach.de/fileadmin/unterricht/vertretungsplan/w00000.htm');
curl_setopt($con, CURLOPT_HEADER, 0);
curl_setopt($con, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($con, CURLOPT_CRLF, 1);
curl_setopt($con, CURLOPT_USERAGENT, 'fsgapi/'.FSGAPI_VERSION.' PHP/'.phpversion());
$html = trim(utf8_encode(curl_exec($con)));
curl_close($con);
if($html) {
	$hash = md5($html);
	echo 'got it, hash is '.$hash.PHP_EOL;
	if($hash != $fsgapi->getConfig('fsgparse_changes_hash')) {
		echo 'hash missmatch, commencing parse cycle'.PHP_EOL;
        $diagnose_error = false;
		$lines = explode("\r\n",  str_replace('&nbsp;', ' ', $html));
		$last = $fsgapi->getConfig('fsgapi_changes_valid');
        $days = 0;
        $changes = 0;
		$parse = false;
		$date = false;
		foreach($lines as $line) {
			if(stripos($line, '<div id="vertretung">') !== false) {
				$parse = true;
				continue;
			}

			if(stripos($line, '</div>') !== false) {
				$parse = false;
				break;
			}
			if(!$parse) continue;
			
			if(preg_match('/<a name="[1-9]">.*<\/a><br>(( \| )?((<a href="#[1-9]">\[ [A-z]* ]<\/a>)|(<b>(([0-9]*\.)*) [A-z]*<\/b>)))+/', $line, $values)) {
				$date = $values[6];
                $days++;
				continue;
			}
			if(!$date) continue;
			
			if(stripos($line, 'tr') === false) continue;
			
			if(preg_match('/^<tr\b[^>]*((odd)|(even))\b[^>]*><td\b[^>]*>(?P<classes>.*?)<\/td><td\b[^>]*>(?P<date>.*?)<\/td><td\b[^>]*>(?P<lessons>.*?)<\/td><td\b[^>]*>(?P<teacher>.*?)<\/td><td\b[^>]*>(?P<course>.*?)<\/td><td\b[^>]*>(?P<room>.*?)<\/td><td\b[^>]*>(?P<type>.*?)<\/td><td\b[^>]*>(?P<from>.*?)<\/td><td\b[^>]*>(?P<comment>.*?)<\/td><\/tr>$/i', $line, $change)) {
				$changes++;
                foreach($change as $key => $value) {
                    if(is_numeric($key)) {
                        unset($changes[$key]);
                        continue;
                    }
                    $change[$key] = strip_tags($value);
                }
                $change['to'] = '';
				$change['classes'] = preg_replace('/((0([5-9]))([a-z]))/', '$3$4', str_replace(' ', '', $change['classes']));
				$tmp = explode('.', $change['date']);
                if(is_string($tmp['0']) && !is_numeric($tmp[0])) $tmp['0'] = strip_tags($tmp['0']);
				$time = mktime(23,59,59,$tmp[1],$tmp[0],date('Y'));
				if($time > $last) $last = $time;
				if($tmp[1] < 6 && $time < $_SERVER['REQUEST_TIME']-(26*7*24*60*60)) {
					echo 'year conflict detected'.PHP_EOL;
					$time = mktime(0,0,0,$tmp[1],$tmp[0],date('Y')+1);
					$change['date'] .= date('Y')+1;
				}
				else {
					$change['date'] .= date('Y');
				}
				$change['time'] = $time;
				$change['lessons'] = str_replace(array('-', ' '), array(',', ''), $change['lessons']);
				$change['teacher'] = str_replace(array('+', '???'), '', $change['teacher']);
				$change['course'] = str_replace(array('-'), array(''), $change['course']);
				if(preg_match('/[a-z]*[0-9]/', strtolower($change['course']))) $change['course'] = substr($change['course'], 0, 1) == strtoupper(substr($change['course'], 0, 1)) ? strtoupper($change['course']) : strtolower($change['course']);
				$change['room'] = str_replace(array('---', '???'), '', $change['room']);		
				if(preg_match('/R [0-9]{3}/', $change['room'])) $change['room'] = 'R'.ltrim($change['room'], 'R ');
                if($change['type'] == $change['to']) $change['to'] = '';
                $change['type'] = str_replace(array('Veranst.'), array('Veranstaltung'), $change['type']);
				$change['comment'] = trim($change['comment'], '.!? ');
				foreach($change as $key => $val) $change[$key] = htmlspecialchars(trim($val));
				
				$classes = explode(',', $change['classes']);
				foreach($classes as $class) {
					$change['class'] = $class;
					$lessons = explode(',', $change['lessons']);
					foreach($lessons as $lesson) {
						$change['lesson'] = $lesson;
						$fsgapi->insertData('FSGAPIDATA_CHANGES', $change);		
					}
				}
						
			}
		}
        echo 'done, '.$days.' days parsed'.PHP_EOL;
        if(!$days && strlen($html) > 10000) {
            $diagnose_error = 'No days and long html ('.strlen($html).') registered';
        }
        if(!$changes && strlen($html) > 10000) {
            $diagnose_error = 'No changes and long html ('.strlen($html).') with '.$days.' days registered';
        }

        echo 'checking for duplicates and replacements'.PHP_EOL;
        $statement = $pdo->prepare('SELECT `changes2`.`id`, `changes1`.`id` AS `replaced` FROM `fsgapi_changes` AS `changes1`, `fsgapi_changes` AS `changes2` WHERE
`changes1`.`id` != `changes2`.`id` AND `changes1`.`time` = `changes2`.`time` AND `changes1`.`lesson` = `changes2`.`lesson` AND
`changes1`.`class` = `changes2`.`class` AND `changes1`.`course` = `changes2`.`course` AND `changes2`.`replaced` = 0 AND
`changes1`.`found` > `changes2`.`found`');
        $statement->execute();
        $i = 0;
        foreach($statement->fetchAll() as $row) {
           $update = $pdo->prepare('UPDATE `fsgapi_changes` SET `replaced` = :replaced WHERE `id` = :id LIMIT 1');
           echo $row['id'].' replaced by '.$row['replaced'].PHP_EOL;
           $update->execute(array(':replaced' => $row['replaced'], ':id' => $row['id']));
           $i++;
        }
        if($i) {
            echo 'done, '.$i.' found and successfully updated'.PHP_EOL;
        }
        else {
            echo 'done, none found'.PHP_EOL;        
        }

        if($fsgapi->getConfig('fsgparse_error')) {
            echo 'warning: selfdiagnose error flag is set'.PHP_EOL;
        }
        else if($diagnose_error)  {
            $errors++;
            $fsgapi->setConfig('fsgparse_error', $diagnose_error);
            mail('developer@example.com', 'FsgParse: Triggered error flag', 'Hi there'.PHP_EOL.PHP_EOL.'FsgParse triggered the error flag and FsgPlan should now be aware of this issue.'.PHP_EOL.PHP_EOL.'Details are:'.PHP_EOL.'Time: '.date('H:i:s, d.m.Y', $_SERVER['REQUEST_TIME']).PHP_EOL.'Message: '.$diagnose_error.'.'.PHP_EOL.PHP_EOL.'Best regards'.PHP_EOL.'FsgApi::FsgParse', 'From: FsgParse <fsgplan@example.com>');
            echo 'warning: selfdiagnose error flag activated, and notified'.PHP_EOL;
        }
        
		$fsgapi->setConfig('fsgapi_changes_valid', $last);
		$fsgapi->setConfig('fsgapi_changes_on', $_SERVER['REQUEST_TIME']);
		$fsgapi->setConfig('fsgparse_changes_hash', $hash);
		echo 'updating hash and times'.PHP_EOL;
	} else {
		echo 'hashes match, no changes parsing needed'.PHP_EOL;
	}
}
else {
	error_log('got it, but empty html returned by curl');
}

$pdo = null;
?>
