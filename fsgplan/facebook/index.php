<?php
define('SYSTEMPATH', '../../fsgapi/system/');

require_once(SYSTEMPATH.'FsgApi.class.php');
require_once(SYSTEMPATH.'FsgPlan.class.php');
require_once(SYSTEMPATH.'libs/facebook/src/facebook.php');
require_once(SYSTEMPATH.'libs/smarty/Smarty.class.php');
require_once(SYSTEMPATH.'config/fsgapi-common.php');
require_once(SYSTEMPATH.'config/fsgplan-facebook.php');

error_reporting(0);

$smarty = new Smarty();
$smarty->template_dir = 'php/smarty/templates/';
$smarty->compile_dir = 'php/smarty/compiled/';
$smarty->config_dir = 'php/smarty/configs/';
$smarty->cache_dir = 'php/smarty/cache/';
$smarty->assign('title', APP_TITLE);
$smarty->assign('root', FB_APP_CANVAS);

$pdo = new PDO(DB_URI, DB_USER, DB_PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
$fsgapi = new FsgApi($pdo);
$fsgplan = new FsgPlan($pdo);

//Pre-Server2Server-Stuff
$controller = isset($_GET['c']) && $_GET['c'] ? $_GET['c'] : 'dash';

switch($controller) {
	case 'ajax':
		header('Content-Type: application/json');
		$install = isset($_GET['o']) && $_GET['o'] == 'install';
		$result = array();
		$result['step'] = $_GET['step'];
		if($result['step'] == 2) {
			$class = FsgApi::parseClass(strtolower($_GET['val']));
			if(!$class) { 
				$result['error'] = 'Klasse ungültig';
				if(trim($_GET['val']) == '11') $result['error'] .= ' (Klasse 11 bitte als 12 eintragen)';
				if(stripos($_GET['val'], 'Kurs') !== false) $result['error'] .= ' (Kursstufe 2 bitte als 13 eintragen)';
				$result['error'] .= '.';
			}
			else {
				$result['val'] = $class['full'];
				if(!$class['letter']) {
					if($install) {
						$result['info'] = 'Trage deine Kurse mit Komma getrennt ein. Schreibe vierstündige Kurse groß, und zweistündige Kurse klein.';
					}
				}
				$result['courses'] = !$class['letter'];
			}
		}
		if($result['step'] == 3) {
			$courses = FsgApi::parseCourses($_GET['val']);
			if(!$courses) {
				$result['error'] = 'Kurse ungültig';
                if(strlen($_GET['val']) >= 4 && stripos($_GET['val'], ',') === false) $result['error'] .= ' (Kurse bitte kommagetrennt eintragen)';
                $result['error'] .= '.';
			} else {
				$result['val'] = $courses;;
			}
		}
		echo json_encode($result);
		exit();
		break;
	case 'deauthorize':
		header('Content-Type: application/json');
        if(!isset($_POST['signed_request'])) {
			die('No signed_reqest given.');
			exit();
		}
		list($encoded_sig, $payload) = explode('.', $_POST['signed_request'], 2); 
		$sig = base64_decode(strtr($encoded_sig, '-_', '+/'));
		$data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
		if (strtoupper($data['algorithm']) !== 'HMAC-SHA256') {
			die('Unknown algorithm. Expected HMAC-SHA256');
			exit();
		}
		$expected_sig = hash_hmac('sha256', $payload, FB_APP_SECRET, true);
		if ($sig !== $expected_sig) {
			die('Bad Signed JSON signature!');
			exit();
		}
        if(!isset($data['user_id'])) exit();
        $fsguser = $fsgplan->getUserData($data['user_id']);
        if(!$fsguser['admin']) $fsgplan->removeUser($data['user_id']);
		exit();
		break;
}

//Facebook
$facebook = new Facebook(array('appId' => FB_APP_ID, 'secret' => FB_APP_SECRET, 'cookie' => true));

$user = $facebook->getUser();

$me = null;
if($user) {
    try {
        define('FB_USER_ID', $user);
        $me = $facebook->api('/me');
    }
    catch(FacebookApiException $e) {
        error_log($e);
    }
}

//Security-policy for Internet Explorer (p3p to accept iframe cookies)
if(stristr($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
    header('p3p: CP="ALL DSP COR PSAa PSDa OUR NOR ONL UNI COM NAV"');
}

if($me) {

    //Get user
    $fsguser = $fsgplan->getUserData($me['id']);
    if(!$fsguser) {
        $fsgplan->createUser($me['id']);
        $fsguser = $fsgplan->getUserData($me['id']);    
    }
    
    //Remove all app requests for user
    $requests = $facebook->api('/me/apprequests');
    $batches = array();
    $batch = array();
    foreach($requests['data'] as $result) {
        $batch[] = $result['id'];
        if(count($batch) >= FB_API_BATCH_SIZE) {
            $batches[] = $batch;
            $batch = array();
        }
    }
    if($batch) {
        $batches[] = $batch;
    }
    foreach($batches as $batch) {
        $queries = array();
        foreach($batch as $id) {
            $query = array('method' => 'DELETE', 'relative_url' => '/'.$id);
            $queries[] = $query;
        }
        $facebook->api('/', 'POST', array('batch' => json_encode($queries)));
    }
    $fsgplan->removeScheduledRequests($me['id']);

    $content = '';
    $footer = '';
    
    $status = 'holiday';
    if($fsguser['finished'] && !$fsguser['admin']) {
        $status = 'finished';
    }
    else if($_SERVER['REQUEST_TIME'] > $fsgapi->getConfig('fsgplan_holiday_end')) { //Schulzeit
        if($fsgapi->getConfig('fsgplan_install_ready') || $fsguser['admin']) { //manuelle Freigabe
            $status = 'install';
        }
        else {
            $status = 'prepare';
        }
    }
    
    switch($controller) {
		case 'holiday':
		    if($controller != $status) {
                header('Location: ?c='.$status);
                exit();
            }
            $again = $fsguser['class'] ? ' wieder' : '';
            $content .= '<p>Hallo '.$me['first_name'].', schön, dass du'.$again.' vorbeischaust!</p>';
			$content .= '<p>Komm nach den Ferien vorbei, um deine neue Klasse und gegebenenfalls auch deine neuen Kurse einzutragen.<br>';
			$content .= 'Bis dahin noch: <strong>Schöne Ferien!</strong></p>';
			$content .= '<div class="fb-like" data-href="https://www.facebook.com/fsgplan" data-width="750px" data-ref="holiday" data-send="true" data-show-faces="true"></div>';		    
			break;
        case 'prepare':
		    if($controller != $status) {
                header('Location: ?c='.$status);
                exit();
            }
            $again = $fsguser['class'] ? ' wieder' : '';
            $content .= '<p>Hallo '.$me['first_name'].', schön, dass du'.$again.' vorbeischaust!</p>';
			$content .= '<p>Wir arbeiten momentan am Vertretungsplan, und können dir deswegen gerade nichts anzeigen. Schau einfach demnächst wieder vorbei, dann sollte alles wieder wie gewohnt sein.</p>';
			$content .= '<p>Am Besten du schaust kurz auf den <a href="http://www.fsg-marbach.de/fileadmin/unterricht/vertretungsplan/w00000.htm" target="_top">offizellen Vertretungsplan</a>, bis bei uns alles wieder läuft.</p>';
 			break;
        case 'install':
        	if($controller != $status) {
                header('Location: ?c='.$status);
                exit();
            }
            if($fsguser['installed']) {
                header('Location: ./?c=settings');
                exit();
            }
            if(isset($_POST['school'])) {
                $class = FsgApi::parseClass(strtolower($_POST['class']));
				$courses = null;
				if(!$class['letter'] && !($courses = FsgApi::parseCourses($_POST['courses']))) {
					$class = null;
				}
                if($class) {
                    if($fsgplan->changeUserData($me['id'], array('class' => $class['full'], 'courses' => $courses, 'installed' => 1))) {
                        header('Location: ./?c=dash');
                        exit();
                    }
                    else {
                        $smarty->assign('error', 'Ein Fehler ist aufgetreten. Bitte versuche es später erneut.');
                    }
                }
                else {
                    $smarty->assign('error', 'Deine Daten scheinen ungültig zu sein. Bitte versuche es erneut.');
                }
            }
            $back = $fsguser['class'] ? ' zurück' : '';
			$new = $_SERVER['REQUEST_TIME'] <= ($fsgapi->getConfig('fsgplan_holiday_end')+31*24*60*60) ? ' neue' : '';
            if(!$fsguser['admin']) {
				$content .= '<p>Hallo '.$me['first_name'].', willkommen '.$back.' beim Vertretungsplan für das'.$new.' Schuljahr 2011/2012!</p>';
			} 
			else {
				$content .= '<p><a href="?c=settings">Zurück zur Administration</a>.</p>';
			}
            $content .= '<p>Gib deine Klasse und gegebenenfalls deine Kurse an, um deinen Vertretungsplan einzurichten:</p>';
            $class = isset($_GET['class']) ? htmlspecialchars(urldecode($_GET['class'])) : '';
            $courses = isset($_GET['courses']) ? htmlspecialchars(urldecode($_GET['courses'])) : '';
            $smarty->assign('class', $class);
            $smarty->assign('courses', $courses);
            $content .= $smarty->fetch('install.tpl');
            break;
        case 'dash':
			if($status != 'install') {            
				header('Location: ./?c='.$status);
                exit();
			}
            if(!$fsguser['installed']) {
				if($fsguser['admin']) {
	                header('Location: ./?c=settings');
	                exit();
				}
                header('Location: ./?c=install&class='.$_GET['class'].'&courses='.$_GET['courses']);
                exit();
            }
			$fsgplan->updateUser($fsguser['id']);

            $count = 0;
			$courses = ' die';
			if($fsguser['courses']) {
				$count = substr_count($fsguser['courses'], ',')+1;
				$courses = $count != 1 ? ' '.$count.' Kurse in ' : ' einen Kurs in';
            }
            $courses_count = $count;
			$content .= '<p>Deine Vertretungen für'.$courses.' Klasse '.$fsguser['class'].' (';
            
            if($fsguser['admin']) $content .= '<a href="?c=admin">Administration</a>, ';
            $content .= '<a href="?c=settings">Einstellungen</a>';
            $content .='):</p>';
			
			$daynames = array('Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag');
            
			$day = date('d');
            $month = date('m');
            $year = date('Y');
            
            if(date('H') >= 18 && date('i') >= 5) $day++;
			$time = mktime(0,0,0,date('m'),$day,date('Y'));
			$days = 0;
            
			$end = $fsgapi->getConfig('fsgapi_changes_valid');
			if($end < $time) $end = $time;

            $i = 0;            
           
			while($time <= $end) {
                $i++;
                if($i >= 100) {
                    $content .= '<h2>Fehler</h2>';
                    $content .= '<p class="fberrorbox">Ausführung abgebrochen.</p>';
                    break;
                }
                $holiday = $fsgapi->getData('FSGAPIDATA_HOLIDAY', $time, $time+24*60*60);
                if(is_array($holiday) && $time >= $holiday['from'] && $time <= $holiday['until'] && $holiday['from'] != $holiday['until']) {
                    $content .= '<h2>Ab dem '.date('d.m.Y', $holiday['from']).'</h2>';
                    $content .= '<p class="fbbluebox">'.$holiday['name'].' bis zum '.date('d.m.Y', $holiday['until']-1).'.</p>';
                                        
                    $time = mktime(0,0,0,date('m', $holiday['until']), date('d', $holiday['until']), date('Y', $holiday['until']));
                    
                    $day = date('d', $holiday['until']);
                    $month = date('m', $holiday['until']);
                    $year = date('Y', $holiday['until']);
                    
                    $holiday = false;
                    continue;
                }
                if($time > $fsgapi->getConfig('fsgapi_changes_valid')) break;
                $days++;
                if(date('w', $time) > 0 && date('w', $time) <= 5) {
                    $content .= '<h2>'.$daynames[date('w', $time)-1].', '.date('d.m.Y', $time).'</h2>';
                    $count = 0;
                    $changes = $fsgapi->getData('FSGAPIDATA_CHANGES', $fsguser, $time, $time+(24*60*60)-1);
                    foreach($changes as $change) {
                        $time = $change['lesson'].'. Stunde';
                        $color = '';
                        switch($change['type']) {
                            case 'Entfall':
                            case 'EA':
                                $color = 'fberrorbox';
                                break;
                            case 'Raum':
                            case 'Vertretung':
                            case 'Betreuung':
                                $color = 'fbinfobox';
                                break;
                            case 'Verlegung':
                                $color = 'fbbluebox';
                                break;
                            default:
                                $color = 'fbbluebox';
                                break;
                        }
                        $content .= '<p class="'.$color.'">'.FsgPlan::makeChangeString($change).'</p>';
                        $count++;
                    }
                    if(!$count) {
                        $content .= '<p class="fbgreybox">Keine anstehenden Vertretungen.</p>';
                    }
                }
				$day++;
				$time = mktime(0, 0, 0, $month, $day, $year);
			}
			
			if(!$days) {
				
                if($fsgapi->getConfig('fsgparse_error')) {
                    $content .= '<h2>Fehler beim Ermitteln der Vertretungen</h2>';
                }
                else {
                    $content .= '<h2>Keine Vertretungen verfügbar</h2>';
                }
                $content .= '<p class="fbgreybox">';
				$content .= 'Bitte überprüfe den <a href="http://www.fsg-marbach.de/fileadmin/unterricht/vertretungsplan/w00000.htm" target="_blank">offiziellen Vertretungsplan</a>.';
                $content .= '</p>';
            }
            else {
                if($courses_count > 0 && $courses_count <= 5) {
                    $content .= '<p class="info">Du hast wenige Kurse angegeben. <strong>Möchtest du <a href="?c=settings&o=courses">weitere Kurse eintragen</a>?</strong></p>';
                }
                else if((isset($_GET['count']) && $_GET['count'] > 0) || isset($_GET['request_ids'])) {
                    $result = $facebook->api('/'.$fsguser['id'].'/likes/'.FB_PAGE_ID);
                    if(!$result['data']) {
                        $content .= '<p class="info"><strong>Findest du den Vertretungsplan gut?</strong> Wir freuen uns über jedes „Gefällt mir“ und Feedback auf <a href="https://www.facebook.com/'.FB_PAGE_NAME.'">unserer Anwendungsseite</a>.</p>';
                        $content .= '<div class="fb-like" data-href="https://www.facebook.com/fsgplan" data-width="750px" data-ref="dash-ask" data-send="false" data-show-faces="false"></div>';
                    }
                }
                /*else if($fsguser['admin']){
                    $facts = array('Den Vertretungsplan haben insgesamt über 500 Schüler verwendet, und du bist einer davon.', 'Der Vertretungsplan funktioniert völlig automatisch und muss theoretisch niemals überprüft werden.', 'Nur für den Vertretungsplan wurden über zweitausend Zeilen Code entworfen, geschrieben und getestet.');
                    $content .= '<p class="info"><em>Fakt: '.$facts[array_rand($facts)].'</em> <a href="?c=settings&expand=1">Möchtest du mehr erfahren?</a></p>';
                }*/
            }
			
			$smarty->assign('footer_left', '<a href="http://www.fsg-marbach.de/fileadmin/unterricht/vertretungsplan/w00000.htm" target="_blank" title="Letzte Änderung: '.date('d.m.Y, H:i:s', $fsgapi->getConfig('fsgapi_changes_on')).'">Vertretungen automatisch ermittelt</a>.');
			break;
        case 'admin':
            if($fsguser['admin']) {
				$content .= '<p>Der Vertretungsplan kann hier für alle Nutzer administriert werden (<a href="?c=dash">zurück zur Übersicht</a>).</p>';				
                $content .= '<h2>Administration</h2>';
				if(isset($_GET['o']) && $_GET['o'] == 'fsgplanoff') {
					$fsgapi->setConfig('fsgplan_install_ready', 0);
				}
				else if(isset($_GET['o']) && $_GET['o'] == 'fsgplanon') {
					$fsgapi->setConfig('fsgplan_install_ready', 1);
				}
				if($fsgapi->getConfig('fsgplan_install_ready')) {
					$content .= '<p>Der Zugriff auf den Vertretungsplan ist momentan <strong>freigegeben</strong> (<a href="?c=admin&o=fsgplanoff">Sperren</a>).</p>';
				} else {
					$content .= '<p>Der Zugriff auf den Vertretungsplan ist momentan <strong>gesperrt</strong> (<a href="?c=admin&o=fsgplanon">Freigeben</a>).</p>';
				}
                if(isset($_GET['o']) && $_GET['o'] == 'reparse') {
					$fsgapi->setConfig('fsgparse_changes_hash', 0);
				}
                if($fsgapi->getConfig('fsgparse_changes_hash')) {
                    $content .= '<p>Ein <strong>erneutes Einlesen</strong> des Vertretungsplans kann hier initiert werden (<a href="?c=admin&o=reparse">Erneut einlesen</a>).</p>';
                }
                else {
                    $content .= '<p>Der Vertretungsplan wird beim nächsten automatischen Durchlauf <strong>erneut eingelesen</strong>.</p>';				
                }
                if(isset($_GET['o']) && $_GET['o'] == 'reseterror') {
					$fsgapi->setConfig('fsgparse_error', 0);
				}
                if($fsgapi->getConfig('fsgparse_error')) {
                    $content .= '<p>Die Fehlererfassung des Parses wurde <strong>ausgelöst</strong>, Wert: "'.htmlspecialchars($fsgapi->getConfig('fsgparse_error')).'" (<a href="?c=admin&o=reseterror">Zurücksetzen</a>).</p>';
                }
                else {
                    $content .= '<p>Die Fehlererfassung des Parses ist <strong>betriebsbereit</strong> und wurde nicht ausgelöst.</p>';				
                }
                $content .= '<h2>Datenverwaltung</h2>';
                $content .= '<p>[TODO] Urlaub.</p>';
                $content .= '<p>[TODO] Ankündigungen.</p>';
                $content .= '<h2>Statistiken</h2>';
				$usercount = count($fsgplan->getUsers(true));
				$content .= '<p>Aktuell <strong>'.$usercount.' installierte'.($usercount == 1 ? 'r' : '').' Benutzer</strong> (von '.count($fsgplan->getUsers(false)).').</p>';
                $changecount = $fsgapi->getChangeEstimate();
                $content .= '<p>Es wurden insgesamt ca. <strong>'.$changecount.' Vertretung'.($changecount != 1 ? 'en' : '').'</strong> automatisch erfasst.</p>';
				/*if(isset($_GET['o']) && $_GET['o'] == 'message' && isset($_POST['message']) && $_POST['message']) {
					$users = $fsgplan->getUsers(false, false);
					$batches = array();
					$batch = array();
					$count = 0;
					foreach($users as $fsguser) {
						$count++;
						$batch[] = $fsguser['id'].'/apprequests?message='.urlencode($_POST['message']).'&data='.time();
						if(count($batch) >= FB_API_BATCH_SIZE) {
							$batches[] = $batch;
							$batch = array();
						}
					}
					if($batch) {
						$batches[] = $batch;
					}
					foreach($batches as $i => $batch) {
						$queries = array();
						foreach($batch as $url) {
							$query = array('method' => 'POST', 'relative_url' => $url);
							$queries[] = $query;
						}
						$result = false;
						try {
							$results = $facebook->api('/', 'POST', array('batch' => json_encode($queries)));
						}
						catch(Exception $ex) {
							log_error($ex);
						}
					}
					$content .= '<p class="fbbluebox">Nachricht an '.$count.' Nutzer gesendet.</p>';
				}
				else {
					$content .= '<p>Eine <strong>Benachrichtigung</strong> für alle Nutzer, die noch nicht als fertig gekennzeichnet wurden, kann hier gesendet werden:<br>';
					$content .= '<form method="post" action="?c=admin&o=message"><input type="text" name="message" id="message"><input type="submit" id="message_send" value="Senden"></form></p>';
					//$content .= '<script type="text/javascript">$(\'#message_send\').hide();</script>';
				}*/
			}
            else {
                header('Location: ./?c=settings');
            }
            break;            
        case 'settings':
			if($status != 'install') {            
				header('Location: ./?c='.$status);
                exit();
			}
            if(!$fsguser['installed'] && !$fsguser['admin']) {
                header('Location: ./?c=install');
                exit();
            }
			$content .= '<p>Hier kannst du die Einstellungen deines Vertretungsplans anpassen';
			if($fsguser['installed']) {
				$content .= ' (<a href="?c=dash">zurück zur Übersicht</a>)';
			}
			else {
				$content .= ' (<a href="?c=install">Vertretungsplan einrichten</a>)';
			}
			$content .= '.</p>';
            if($fsguser['admin']) {
                $content .= '<h2>Administration</h2>';
                $content .= '<a href="?c=admin">Die Administration anzeigen</a>.';
            }
			if($fsguser['installed']) {
				$content .= '<h2>Vertretungen</h2>';
				$content .= '<p>Diese Einstellungen dienen zur Konfiguration der geholten Vertretungen (sie werden am Ende des Schuljahres automatisch zurückgesetzt):</p>';
				if(isset($_GET['o']) && $_GET['o'] == 'changes' && isset($_POST['class']) && isset($_POST['courses'])) {
					$class = FsgApi::parseClass(strtolower($_POST['class']));
					$courses = null;
					if(!$class['letter'] && !($courses = FsgApi::parseCourses($_POST['courses']))) {
						$class = null;
					}
					if($class) {
						if(($fsguser['class'] == $class['full'] && $fsguser['courses'] == $courses) || $fsgplan->changeUserData($me['id'], array('class' => $class['full'], 'courses' => $courses))) {
							header('Location: ?c=dash');
							exit();
						}
						else {
							$smarty->assign('error', 'Ein Fehler ist aufgetreten, oder die Daten sind identisch. Bitte versuche es später erneut.');
						}
					}
					else {
						$smarty->assign('error', 'Deine Daten scheinen ungültig zu sein. Bitte versuche es erneut.');
					}
				}
				$smarty->assign('class', $fsguser['class']);
				$smarty->assign('courses', $fsguser['courses']);		
				$content .= $smarty->fetch('settings.tpl');
                /*if($fsguser['courses'] && isset($_GET['o']) && $_GET['o'] == 'courses') {
                    $content .= '<script type="text/javascript">$(\'#courses_change_link\').click();</script>';
                }*/
				$content .= '<h2>Anzeige</h2>';
				$content .= '<p>Mit diesen Einstellungen kann die Anzeige und die erfolgenden Benachrichtigungen konfiguriert werden:</p>';
				$content .= '<p>';
				if(isset($_GET['o']) && $_GET['o'] == 'notifyoff') {
					$fsguser['notify'] = 0;
					$fsgplan->changeUserData($fsguser['id'], array('notify' => 0));
				}
				else if(isset($_GET['o']) && $_GET['o'] == 'notifyon') {
					$fsguser['notify'] = 1;
					$fsgplan->changeUserData($fsguser['id'], array('notify' => 1));
				}
				if($fsguser['notify']) {
					$content .= '<p>Du wirst <strong>benachrichtigt</strong>, wenn neue Vertretungen erfasst werden (<a href="?c=settings&o=notifyoff">Deaktivieren</a>).';
				}
				else {
					$content .= '<p>Du wirst <strong>nicht benachrichtigt</strong>, wenn neue Vertretungen erfasst werden (<a href="?c=settings&o=notifyon">Aktivieren</a>).';
				}
				$content .= '<br>Wir benachrichtigen dich in jedem Fall bei wichtigen Ankündigungen zur Anwendung.';
				$content .= '</p>';
			}
			else {
				$content .= '<h2>Einrichtung</h2>';
				$content .= '<p>Diese Einstellungen stehen dir nur zur Verfügung, nachdem der Vertretungsplan <a href="?c=install">eingerichtet</a> wurde.</p>';
			}
			if(!isset($_GET['expand']) || !$_GET['expand']) {
				$footer_left = '<span id="show_info" display: none;><a href="#" id="show_info_link">Mehr über die Anwendung erfahren</a>&hellip;</span>';
				$footer_left .= '<script type="text/javascript">$(\'#show_info\').show(); $(\'#more_info\').hide(); $(\'#show_info_link\').click(function(e) {e.preventDefault(); $(\'#show_info\').hide();$(\'#more_info\').fadeIn();});</script>';
				$smarty->assign('footer_left', $footer_left);
			}
			$content .= '<div id="more_info">';
			$content .= '<h2>Über den Vertretungsplan</h2>';
			$content .= '<p>Der Vertretungsplan entstand Ende November 2010 als eine spontane Idee und wurde bis Ende Juni/Anfang Juli 2011 entwickelt. Nach einer längeren Pause und langer Vorbereitung und Entwicklung im August ist eine komplett neue Version dann Ende September 2011 für das neue Schuljahr freigegeben worden.<br>Dezember 2011 wurde die Entwicklung zeitweise eingestellt, Anfang 2012 jedoch wieder aufgenommen.</p>';
			$content .= '<p>Besuch uns auch auf unserer <a href="https://www.facebook.com/'.FB_APP_ID.'" target="_top">Anwendungsseite</a>. Wir freuen uns natürlich trotzdem immer noch über ein "Gefällt mir", und auch wenn du deinen Freunden diese Anwendung empfiehlst.</p>';
			$content .= '<div class="fb-like" data-href="https://www.facebook.com/fsgplan" data-width="750px" data-ref="settings" data-send="true" data-show-faces="true"></div>';
			$content .= '</div>';
			/*$content .= '<h2>Alle Einstellungen zurücksetzen</h2>';
			$content .= '<p>Du kannst alle deine Einstellungen hier zurücksetzen. Dabei werden deine Daten wie Klasse und Kurse unwiderruflich gelöscht und müssen danach erneut eingegeben werden.</p>';
			$content .= '<form method="post" action="?c=settings"><table>';
			$content .= '<tr><td></td><td><input type="checkbox" id="reset_sure"></td></tr>';
			$content .= '<tr><td></td><td><input type="submit" name="reset" id="reset" value="Zurücksetzen"></td></tr>';
			$content .= '</table></form>';*/
            break;
        case 'finished':
        	if($controller != $status) {
                header('Location: ?c='.$status);
                exit();
            }
        case 'debugfinished':
            $content .= '<p>Hallo '.$me['first_name'].', alles klar bei dir?</p>';
		    $content .= '<p>Du wurdest ausgehend von deiner letzten Klasse automatisch als „fertig“ markiert.<br>';
			if(isset($_GET['o']) && $_GET['o'] == 'notfinished') {
				$fsgplan->changeUserData($fsguser['id'], array('finished' => 0));
				header('Location: ?c=install');
				exit();
			}
		    $content .= 'Falls das nicht stimmt, kannst du dich selbst wieder <a href="?c=finished&o=notfinished">hier</a> als Schüler markieren.</p>';
		    $content .= '<p>Wir hoffen, dir hat die Verwendung des Vertretungsplans soviel Spaß gemacht, wie uns die Ideen und Entwicklung daran, und wünschen dir daher:</p>';
            $content .= '<p><strong>Alles Gute!</strong></p>';
		    $content .= '<p>Solltest du noch Fragen, Lob oder Feedback haben, kannst dich gerne jederzeit auf der <a href="https://www.facebook.com/'.FB_PAGE_NAME.'" target="_top">Anwendungsseite</a> melden.<br>';
		    $content .= 'Natürlich freuen wir uns auch jederzeit auch noch über ein „Gefällt mir“, falls du das noch nicht getan hast. Wir nehmen es dir auch nicht übel, wenn du uns jetzt als Anwendung <a href="https://www.facebook.com/settings/?tab=applications&app_id='.FB_APP_ID.'#application-li-'.FB_APP_ID.'">hier</a> entfernen willst, auch wenn wir dich jetzt nicht mehr benachrichtigen.</p>';
            $content .= '<p>Vielleicht sieht man sich ja eines Tages. Bis dann!</p>';
		    $content .= '<div class="fb-like" data-href="https://www.facebook.com/'.FB_PAGE_NAME.'" data-width="750px" data-ref="finished" data-send="true" data-show-faces="true"></div>';
            break;
        default:
            $smarty->assign('message', 'Sorry, da ist etwas schiefgelaufen :(');
            $smarty->assign('description', 'Zurück zur Übersicht');
            $smarty->assign('location', FB_APP_CANVAS);
            $smarty->display('redirect.tpl');
            exit();
			break;
    }

    $footer .= '<a href="https://www.facebook.com/'.FB_PAGE_NAME.'" target="_top" title="fsgapi '.FSGAPI_VERSION.'">'.APP_TITLE.'</a>';
    if($fsguser['admin']) $footer .= ' (<a href="https://developers.facebook.com/apps/'.FB_APP_ID.'" target="_top">Administrator</a>)';    
    $footer.= ' &middot; ';
    $footer .= '<a href="https://www.facebook.com/benedict.etzel" target="_top">Benedict Etzel</a> &copy; '.date('Y').'';

    $smarty->assign('content', $content);
    $smarty->assign('footer', $footer);
    $smarty->display('index.tpl');
}
else {
    $login_par = array();
    $login_par['redirect_uri'] = FB_APP_CANVAS;
    $login_par['scope'] = 'manage_notifications';

    //Check if user previously denied
    if(isset($_GET['error_reason']) && $_GET['error_reason'] == 'user_denied') {
        $smarty->assign('message', 'Upps, da hat etwas nicht geklappt. Um die Anwendung zu verwenden, musst du sie aus Sicherheitsgründen bestätigen.');
        $smarty->assign('description', 'Erneut versuchen');
    }

    $smarty->assign('location', $facebook->getLoginUrl($login_par));
    $smarty->display('redirect.tpl');
    exit();
}

$pdo = null;
?>
