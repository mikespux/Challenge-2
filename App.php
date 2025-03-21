<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class App extends DataObject {

		const PERMISSIONS_DEFAULT = 255;
		const PERMISSIONS_CREATE_ACCOUNT = 512;
		const PERMISSIONS_ALL = 1023;

		public static function tableName() {
			return "onsong_connect_app";
		}

		public static function className() {
			return "App";
		}
		
		public function hasPermission($permission) {
			return (($this->permissions() & $permission) == $permission);
		}

		public function redirects($value = null) {
			if($value == null) {
				$result = array_map('trim', explode("\n", parent::redirects()));
				if(count($result) == 0) {
					array_push($result, "http://localhost");
				}
				return $result;
			} else {
				if(is_array($value)) {
					parent::redirects(implode("\n", $value));
				} else {
					parent::redirects($value);
				}
			}
		}
		
		public function alliances($index = null) {
			global $pdo;
			$a = array();
			$sql = "SELECT a.* FROM onsong_connect_alliance a INNER JOIN onsong_connect_app_alliance r ON a.ID = r.allianceID WHERE r.appID = ?";
			$statement = $pdo->prepare($sql);
			$statement->execute(array($this->ID()));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					array_push($a, new Alliance($row));
				}
			}
			if(isset($index)) {
				if($index < count($a)) {
					return $a[$index];
				} else {
					return null;
				}
			}
			return $a;
		}

		public function sources($index = null) {
			global $pdo;
			$a = array();
			$sql = "SELECT s.* FROM onsong_connect_source s INNER JOIN onsong_connect_app_source r ON s.ID = r.sourceID WHERE r.appID = ?";
			$statement = $pdo->prepare($sql);
			$statement->execute(array($this->ID()));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					array_push($a, new Source($row));
				}
			}
			if(isset($index)) {
				if($index < count($a)) {
					return $a[$index];
				} else {
					return null;
				}
			}
			return $a;
		}

		// Returns the master application
		public static function master() {
			global $pdo;
			$statement = $pdo->prepare("SELECT * FROM onsong_connect_app WHERE master = 1 ORDER BY created LIMIT 1");
			if($statement->execute()) {
				return new App($statement->fetch(PDO::FETCH_ASSOC));
			}
			return null;
		}
	}
?>