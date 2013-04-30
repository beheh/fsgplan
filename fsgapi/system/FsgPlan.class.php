<?php
class FsgPlan {

    private $pdo;
    private $admins;
    
    function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $statement = $this->pdo->prepare('SELECT * FROM `fsgplan_admin`');
	    $statement->execute();		
	    $this->admins = array();
	    foreach($statement->fetchAll() as $admin) {
	        $this->admins[$admin['uid']] = true;
	    }
    }

	public function createUser($id) {
	    if(!$id || !is_numeric($id)) return false;
	    $statement = $this->pdo->prepare('INSERT INTO `fsgplan_user` (`id`, `update`) VALUES ( :id , :update )');
	    $statement->execute(array(':id' => $id, ':update' => $_SERVER['REQUEST_TIME']));
		if($statement->rowCount() == 1) return true;
		return false;
	}

    public function removeUser($id) {
	    if(!$id || !is_numeric($id)) return false;
	    $statement = $this->pdo->prepare('DELETE FROM `fsgplan_user` WHERE `id` = :id');
	    $statement->execute(array(':id' => $id));
		if($statement->rowCount() == 1) return true;
		return false;    
    }
    
	public function getUsers($installed = false, $notify = true, $finished = false) {
	    $statement = $this->pdo->prepare('SELECT * FROM `fsgplan_user` WHERE `installed` IN ( :installed , 1) AND `notify` IN ( :notify , 1) AND `finished` IN ( :finished , 0)');
	    $statement->execute(array(':installed' => $installed+0, ':notify' => $notify+0, ':finished' => $finished+0));
	    return $statement->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function updateUsers($installed = false) {
		$statement = $this->pdo->prepare('UPDATE `fsgplan_user` SET `update` = :update WHERE `installed` IN ( :installed , 1)');
	    $statement->execute(array(':installed' => $installed+0, 'update' => $_SERVER['REQUEST_TIME']));
	    return $statement->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function getUserData($id) {
	    if(!$id || !is_numeric($id)) return false;
	    $statement = $this->pdo->prepare('SELECT * FROM `fsgplan_user` WHERE `id` = :id LIMIT 0,1');
	    $statement->execute(array(':id' => $id));
	    $row = $statement->fetch(PDO::FETCH_ASSOC);
	    if(!$row) return false;
	    $row['admin'] = false;
	    if(isset($this->admins[$row['id']]) && $this->admins[$row['id']]) $row['admin'] = true;
	    return $row;
	}
	
	public function changeUserData($id, $data) {
		if(!count($data)) return;
		$params = array(':id' => $id);
		$query = '';
		foreach($data as $row => $value) {
			if($query) $query .= ' ,';
			if($row == 'id') continue;
			$query .= ' `'.$row.'` = :'.$row;
			$params[':'.$row] = $value;
		}
	    $statement = $this->pdo->prepare('UPDATE `fsgplan_user` SET'.$query.' WHERE `id` = :id');
	    $statement->execute($params);
		if($statement->rowCount() == 1) return true;
		return false;
	}
	
	public function updateUser($id) {
		return $this->changeUserData($id, array('update' => $_SERVER['REQUEST_TIME']));		
	}
	
	public function removeScheduledRequests($user) {
	    $statement = $this->pdo->prepare('DELETE FROM `fsgplan_request` WHERE `user` = :user');
	    $statement->execute(array(':user' => $user));
		if($statement->rowCount() == 1) return true;
		return false;
	}

	public function removeScheduledRequest($id) {
	    $statement = $this->pdo->prepare('DELETE FROM `fsgplan_request` WHERE `id` = :id');
	    $statement->execute(array(':id' => $id));
		if($statement->rowCount() == 1) return true;
		return false;
	}
		
	public function scheduleRequest($id, $user, $expires) {
	    $statement = $this->pdo->prepare('INSERT INTO `fsgplan_request` (`id`, `user`, `expires`) VALUES ( :id , :user , :expires )');
	    $statement->execute(array(':id' => $id, ':user' => $user, ':expires' => $expires));
		if($statement->rowCount() == 1) return true;
		return false;
	}
	
	public function getExpiredRequests() {
        $statement = $this->pdo->prepare('SELECT * FROM `fsgplan_request` WHERE `expires` <= :time');
        $statement->execute(array(':time' => $_SERVER['REQUEST_TIME']));
        return $statement->fetchAll(PDO::FETCH_ASSOC);
	}
    
    public static function makeChangeColor($change) {
        switch($change['type']) {
            case 'Entfall':
            case 'EA':
                $color = 'error';
                break;
            case 'Raum':
            case 'Vertretung':
            case 'Betreuung':
                $color = 'grey';
                break;
            case 'Verlegung':
                $color = 'blue';
                break;
            default:
                $color = 'blue';
                break;
        }
        return $color;
    }
	
	public static function makeChangeString($change) {
        $text = '';
		if($change['lesson']) $text .= $change['lesson'].'. Stunde: ';
		switch(strtolower($change['type'])) {
			case 'entfall':
			case 'ea':
				$text .= $change['course'];
				if($change['teacher']) $text .= ' bei '.$change['teacher'];
				if($change['room']) $text .= ' in '.$change['room'];
				$text .= ' entfällt';
				break;
			case 'raum':
				$text .= $change['course'];
				if($change['teacher']) $text .= ' bei '.$change['teacher'];
				if($change['room']) $text .= ' in '.$change['room'];
				break;
			case 'vertretung':
			case 'betreuung':
			case 'statt-vertretung':
			case 'lehrertausch':
				$text .= $change['type'];
				if($change['course']) $text .= ' für '.$change['course'];							
				if($change['teacher']) $text .= ' bei '.$change['teacher'];
				if($change['room']) $text .= ' in '.$change['room'];
				if($change['from'] || $change['to']) {
				    $text .= ' (';
				    if($change['from']) $text .= 'von '.$change['from'].' ';
				    if($change['to']) $text .= 'nach '.$change['to'].' ';
				    $text .= 'verlegt)';
			    }
				break;
			case 'verlegung':
				$text .= $change['course'];
				if($change['teacher']) $text .= ' bei '.$change['teacher'];
				if($change['room']) $text .= ' in '.$change['room'];
				if($change['from']) $text .= ' von '.$change['from'];
				if($change['to']) $text .= ' nach '.$change['to'];
				$text .= ' verlegt';
				break;
	        case 'trotz absenz':
	            if(strtolower(rtrim($change['comment'], '.!')) == 'findet statt') $change['comment'] = '';
			default:
				if($change['type']) $text .= $change['type'].': ';
				if($change['course']) $text .= $change['course'];							
				if($change['teacher']) $text .= ' bei '.$change['teacher'];
				if($change['room']) $text .= ' in '.$change['room'];
                if($change['from'] || $change['to']) {
				    $text .= ' (';
				    if($change['from']) $text .= 'von '.$change['from'].' ';
				    if($change['to']) $text .= 'nach '.$change['to'].' ';
				    $text .= 'verlegt)';
			    }
				break;
		}
		if($change['comment']) {
            if($text) {
                $text .= ' (Bemerkung: '.$change['comment'].')';
            }
            else {
                $text .= $change['comment'];
            }
        }
		if(!in_array(substr($text, -1), array('.', '?', '!'))) $text .= '.';
		return trim($text);
	}
}
?>
