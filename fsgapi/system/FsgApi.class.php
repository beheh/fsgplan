<?php
require('FsgApiException.class.php');

class FsgApi {

    private $pdo;
	public $config;
    public static $course_corrections = array('reli' => 'rel', 'info' => 'inf', 'eng' => 'e', 'ma' => 'm');
    
    function __construct($pdo) {
        if(!$pdo) {
            throw new Exception('no pdo supplied');
		}
        $this->pdo = $pdo;
		$statement = $this->pdo->prepare('SELECT * FROM `fsgapi_config`');
		$statement->execute();
		$this->config = array();
		foreach($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$this->config[$row['name']] = $row['data'];
		}
	}
	
    public static function parseClass($class) {	
	    $class = trim(strtolower($class));
		if(!preg_match('/^(((?P<class_year>[5-9]|10)(?P<class_letter>[a-z]))|(?P<class_course_year>1[2-3]))$/', $class, $query)) return false;
		$result = array();
		$result['full'] = $class;
		$result['year'] = isset($query['class_year']) && $query['class_year'] ? $query['class_year'] : $query['class_course_year'];
		$result['letter'] = isset($query['class_letter']) && $query['class_letter'] ? $query['class_letter'] : false;
		return $result;
	}

	public static function parseCourses($courses) {
	    $courses = trim($courses);
		$courses = str_replace(array('-', '_', ':'), array(''), $courses);
		$courses = str_replace(array(' ', ';'), ',', $courses);
        if(substr($courses, -1) != ',') $courses .= ',';
		if(!preg_match('/^((([A-Z]|[a-z]){1,8}-?[1-9][0-9]?( ?, ?))+)$/', $courses)) return false;
		$courses = trim($courses, ',');
		$courses_array = explode(',', $courses);
		$courses_array_clean = array();
		foreach($courses_array as $course) {
			$upper = substr($course, 0, 1) == strtoupper(substr($course, 0, 1));
            foreach(self::$course_corrections as $wrong => $correction) {
                if(strtolower(rtrim($course, '1234567890')) == strtolower($wrong)) {
                    $course = str_ireplace($wrong, $correction, $course);
                }
            }
            $course = $upper ? strtoupper($course) : strtolower($course);			
			$courses_array_clean[$course] = true;
		}
		$courses_clean = '';
		foreach($courses_array_clean as $course => $dummy) {
			if($courses_clean) $courses_clean .= ',';
			$courses_clean .= $course;
		}
		return $courses_clean;
	}
    
	public function insertData($type, $data) {
		switch($type) {
			case 'FSGAPIDATA_CHANGES':
				$statement = $this->pdo->prepare('INSERT INTO `fsgapi_changes` (`class`, `time`, `lesson`, `teacher`, `course`, `room`, `type`, `from`, `to`, `comment`, `found`) VALUES ( :class , :time , :lesson , :teacher , :course , :room , :type , :from , :to , :comment , :found ) ON DUPLICATE KEY UPDATE `class` = :class , `time` = :time , `lesson` = :lesson , `teacher` = :teacher , `course` = :course , `room` = :room , `type` = :type , `from` = :from , `to` = :to , `comment` = :comment');
				$statement->execute(array(':class' => $data['class'], ':time' => $data['time'], ':lesson' => $data['lesson'], ':teacher' => $data['teacher'], ':course' => $data['course'], ':room' => $data['room'], ':type' => $data['type'], ':from' => $data['from'], ':to' => $data['to'], ':comment' => $data['comment'], ':found' => $_SERVER['REQUEST_TIME']));
				if($statement->rowCount() == 1) {
					$statement = $this->pdo->prepare('UPDATE `fsgapi_changes` SET `updated` = :updated WHERE `class` = :class AND `time` = :time AND `lesson` = :lesson AND `room` = :room');
					$statement->execute(array(':updated' => $_SERVER['REQUEST_TIME'], ':class' => $data['class'], ':time' => $data['time'], ':lesson' => $data['lesson'], ':room' => $data['room']));
				}
				break;
			case 'FSGAPIDATA_FOOD':
			
				break;
			default:
				return false;
				break;
		}
		return true;
	}
	
	public function getData($type, $params = false, $from = 0, $until = 0, $updated_after = 0) {
        if($from == 0) $from = time();
		switch($type) {
			case 'FSGAPIDATA_CHANGES':
				if(isset($params['class']) && $params['class']) {
                    if(isset($params['courses']) && $params['courses']) {
                        $statement_courses = '';
                        $statement_course_data = array();
                        $courses = explode(',', $params['courses']);
                        $i = 0;
                        foreach($courses as $course) {
                            if($i) $statement_courses .= ',';
                            $statement_courses .= ' :course'.$i.' ';
                            $statement_course_data[':course'.$i] = $course;
                            $i++;
                        }
                        $statement = $this->pdo->prepare('SELECT * FROM `fsgapi_changes` WHERE `class` = :class AND (`course` IN ('.$statement_courses.') OR `course` = \'\') AND `time` >= :from AND (`time` <= :until OR :until = 0) AND `updated` > :updated AND `replaced` = 0 ORDER BY `time` ASC, `lesson` ASC');
                        $statement->execute(array_merge(array(':class' => $params['class'], ':from' => $from, ':until' => $until, ':updated' => $updated_after), $statement_course_data));
                    }
                    else {
                        $statement = $this->pdo->prepare('SELECT * FROM `fsgapi_changes` WHERE `class` = :class AND `time` >= :from AND (`time` <= :until OR :until = 0) AND `updated` > :updated AND `replaced` = 0 ORDER BY `time` ASC, `lesson` ASC');
                        $statement->execute(array(':class' => $params['class'], ':from' => $from, ':until' => $until, ':updated' => $updated_after));
                    }
                    $changes = $statement->fetchAll(PDO::FETCH_ASSOC);
                    if(is_array($changes)) {
                        foreach($changes as $i => $change) {
                            $changes[$i]['expires'] = $change['time']+(7.5*60*60);
                        }
                    }
                    return $changes;
                }
				break;
            case 'FSGAPIDATA_CHANGE':
                if(isset($params['id'])) {
                    $statement = $this->pdo->prepare('SELECT * FROM `fsgapi_changes` WHERE `id` = :id LIMIT 1');
                    $statement->execute(array(':id' => $params['id']));
                    return $statement->fetch(PDO::FETCH_ASSOC);
                }
                break;
            case 'FSGAPIDATA_HOLIDAY':
                $statement = $this->pdo->prepare('SELECT * FROM `fsgplan_holiday` WHERE (:from >= `from` AND :from <= `until`) OR (:until <= `until` AND :until >= `from`) OR (:from <= `from` AND ( :until >= `until` OR :until = 0)) LIMIT 1');
                $statement->execute(array(':from' => $from, ':until' => $until));
                $holidays = $statement->fetch(PDO::FETCH_ASSOC);
                return $holidays;
                break;
			case 'FSGAPIDATA_FOOD':
			
				break;
		}
		return false;
	}
	
	public function getConfig($name) {
		if(isset($this->config[$name])) {
			return $this->config[$name];
		}
		return false;
	}
	
	public function setConfig($name, $data) {
		$statement = $this->pdo->prepare('INSERT INTO `fsgapi_config` (`name`, `data`) VALUES ( :name , :data ) ON DUPLICATE KEY UPDATE `data` = :data');
		$statement->execute(array(':name' => $name, ':data' => $data));
		if($statement->rowCount() > 0) {
			$this->config[$name] = $data;
			return true;
		}
		return false;
	}
    
    public function getChangeEstimate() {
        $statement = $this->pdo->prepare('SELECT `TABLE_ROWS` from information_schema.TABLES where `TABLE_SCHEMA` =  \'fsgapi\' AND `TABLE_NAME` = \'fsgapi_changes\'');
        $statement->execute();
        $row = $statement->fetch();
        return $row['TABLE_ROWS'];
    }
}

?>
