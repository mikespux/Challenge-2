<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Email extends DataObject {

		public static function tableName() {
			return "onsong_connect_email";
		}

		public static function className() {
			return "Email";
		}
		
		public function testRecipients($emails) {
			global $pdo;
			
			$recipients = array();
			$sql = "SELECT ";
			foreach(array_keys(User::schema()) as $column) {
				$sql .= "onsong_connect_user.". $column ." AS user_". $column .", ";
			}
			foreach(array_keys(Account::schema()) as $column) {
				$sql .= "onsong_connect_account.". $column ." AS account_". $column .", ";
			}
			$sql .= " 1 AS temp ";
			$sql .= " FROM onsong_connect_user ";
			$sql .= " INNER JOIN onsong_connect_role ON onsong_connect_role.userID = onsong_connect_user.ID ";
			$sql .= " INNER JOIN onsong_connect_account ON onsong_connect_account.ID = onsong_connect_role.accountID ";
			$sql .= " WHERE onsong_connect_user.email IN(";
			
			$params = array();
			for($i=0;$i<count($emails);$i++) {
				if($i > 0) {
					$sql .= ", ";
				}
				$sql .= "?";
				array_push($params, $emails[$i]);
			}

			$sql .= ") ORDER BY ". $this->dateColumn();
			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					$account = new Account($row, "account_");
					$user = new User($row, "user_");
					array_push($recipients, array("user"=>$user,"account"=>$account));
				}
			}
			return $recipients;
		}

		public function recipients($date = null) {
			global $pdo;
			
			// If date is null, then use today
			if(is_null($date)) {
				$date = time();
			}
			
			$recipients = array();
			$sql = "SELECT ";
			foreach(array_keys(User::schema()) as $column) {
				$sql .= "onsong_connect_user.". $column ." AS user_". $column .", ";
			}
			foreach(array_keys(Account::schema()) as $column) {
				$sql .= "onsong_connect_account.". $column ." AS account_". $column .", ";
			}
			$sql .= " 1 AS temp ";
			$sql .= " FROM onsong_connect_user ";
			$sql .= " INNER JOIN onsong_connect_role ON onsong_connect_role.userID = onsong_connect_user.ID ";
			$sql .= " INNER JOIN onsong_connect_account ON onsong_connect_account.ID = onsong_connect_role.accountID ";
			$sql .= " WHERE onsong_connect_account.unsubscribed IS NULL AND ". $this->dateColumn() ." IS NOT NULL AND CAST(ADDDATE(". $this->dateColumn() .", INTERVAL ? DAY) AS DATE) ". $this->operator() ." CAST(? AS DATE) ";
			if(empty($this->filter()) == false) {
				$sql .= " AND (". $this->filter() .")";
			}
			$sql .= " ORDER BY ". $this->dateColumn();
			$statement = $pdo->prepare($sql);
			$statement->execute(array($this->day(), date('Y-m-d H:i:s', $date)));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					$account = new Account($row, "account_");
					$user = new User($row, "user_");
					array_push($recipients, array("user"=>$user,"account"=>$account));
				}
			}
			return $recipients;
		}

		// Gets the email status for the specified date, or today if omitted		
		public function status($date = null) {
			global $pdo;
			
			// If date is null, then use today
			if(is_null($date)) {
				$date = time();
			}

			$status = new EmailStatus();
			$sql = "SELECT * FROM onsong_connect_email_status WHERE emailID = ? AND CAST(date AS DATE) = CAST(? AS DATE) ORDER BY sent DESC LIMIT 1 ";
			$statement = $pdo->prepare($sql);
			$statement->execute(array($this->ID(), date('Y-m-d H:i:s', $date)));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					$status = new EmailStatus($row);
					break;
				}
			}
			return $status;
		}

		public function complete($recipients, $date = null) {
			global $pdo;
			
			// If date is null, then use today
			if(is_null($date)) {
				$date = time();
			}
			
			// Now create an email status and save it
			$status = new EmailStatus();
			$status->emailID($this->ID());
			$status->date(date('Y-m-d', $date));
			$status->recipients($recipients);
			$status->sent(time());
			$status->save();
		}

		public function incomplete($error, $date = null) {
			global $pdo;
			
			// If date is null, then use today
			if(is_null($date)) {
				$date = time();
			}
			
			// Now create an email status and save it
			$status = new EmailStatus();
			$status->emailID($this->ID());
			$status->date($date('Y-m-d', $date));
			$status->sent(time());
			$status->error($error);
			$status->save();
		}
	}
?>