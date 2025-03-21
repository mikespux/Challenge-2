<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	require_once(__DIR__ . "/Functions.php");
	require_once(__DIR__ . "/CFPropertyList/CFPropertyList.php");
	
	class Chart extends Song {
		
		public function percentageLicensed() {
			$p = 0;
			foreach($this->ownership() as $ownership) {
				$p += $ownership->percentage();
			}
			return $p;
		}

		public static function retrieve($ID, $includeDeleted = false) {
			$chart = parent::retrieve($ID, $includeDeleted);
			if(empty($chart)) { return null; }
			if(empty($chart->statusID())) { return null; }
			
			if(check_app_roles("scribe", "read") == false) {
				if($chart->status()->status() != 'active') { return null; }
			}
			return $chart;
		}

		public static function find($title, $artist = null, $key = null) {
			global $pdo;

			// Maintain an array of parameters
			$params = array($title);
			
			// Generate the SQL
			$sql = "SELECT DISTINCT s.*, t.status, FIND_IN_SET(COALESCE(t.status, 'none'), 'published,performed,scribed,licensed,requested,removed,none') AS orderIndex ";
			$sql .= " FROM onsong_connect_song s ";
			$sql .= " INNER JOIN onsong_connect_role r ON r.accountID = s.accountID ";
			$sql .= " INNER JOIN onsong_connect_user u ON u.ID = r.userID ";
			$sql .= " INNER JOIN onsong_connect_module_user m on m.username = u.username AND moduleID = 'scribe' ";
			$sql .= " LEFT OUTER JOIN onsong_connect_song_status t ON s.ID = t.songID ";
			$sql .= " WHERE s.title LIKE ? ";
			if(!empty($artist)) {
				array_push($params, $artist);
				$sql .= " AND s.artist LIKE ? ";
			}
			if(!empty($key)) {
				array_push($params, $key);
				$sql .= " AND s.key LIKE ? ";
			}
			$sql .= " ORDER BY orderIndex ";
			
			$a = array();
			$statement = $pdo->prepare($sql);
			if($statement->execute($params)) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					array_push($a, new Chart($row));
				}
			}
			return $a;
		}
		
		public static function permissionsEnabled() {
			return false;
		}
	}
?>