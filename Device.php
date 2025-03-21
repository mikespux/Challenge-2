<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Device extends DataObject {

		public static function tableName() {
			return "onsong_connect_device";
		}

		public static function className() {
			return "Device";
		}
		
		public static function current() {
			if(Token::current()) {
				return Token::current()->device();
			}
			return null;
		}
		
		public function appName() {
			$appName = $this->appID();
			if(!empty($this->appVersion())) {
				$parts = explode(".", $this->appVersion());
				if(endsWith($appName, $parts[0])) {
					$appName = trim(substr($appName, 0, strlen($appName) - strlen($parts[0])));
				}
				$appName .= " " . $this->appVersion();
			}
			return $appName;
		}

		public function os() {
			$os = "iOS";
			if($this->version() >= 13 && ($this->version() == "iPad")) {
				$os = "iPadOS";
			}
			$os .= " " . $this->version();
			return $os;
		}
		
		public function model($value = false) {
			if($value === false) {
				return trim(parent::model());
			} else {
				parent::model(trim($value));
			}
		}

		public function tokens() {
			global $pdo;
			
			$a = array();

			// Create the SQL statement
			$sql = "SELECT * FROM onsong_connect_token WHERE deviceID = ? ORDER BY accessed DESC";

			// Execute the statement and return the list
			$statement = $pdo->prepare($sql);
			$statement->execute(array($this->ID()));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					array_push($a, new Token($row));
				}
			}
			return $a;
		}

		public function user() {
			global $pdo;
			
			// Create the SQL statement
			$sql = "SELECT u.* FROM onsong_connect_user u INNER JOIN onsong_connect_role r ON r.userID = u.ID INNER JOIN onsong_connect_token t ON t.roleID = r.ID WHERE t.deviceID = ? ORDER BY accessed DESC LIMIT 1";

			// Execute the statement and return the list
			$statement = $pdo->prepare($sql);
			$statement->execute(array($this->ID()));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					return new User($row);
				}
			}
			return null;
		}

		public function localHostname() {
			
			// If we don't have a local address, then null
			if(empty($this->localAddress())) { return null; }
			
			// Split by slashes
			$parts = explode("/", $this->localAddress());
			
			// Now loop
			foreach($parts as $part) {
				
				// Return if it has a period
				if(strpos($part, ".") !== false) {
					
					// Return that part
					return $part;
				}
			}
			return null;
		}
		
		public function sendPushNotification($apn, $dev = false) {
			
			return false;
		}
	}
?>