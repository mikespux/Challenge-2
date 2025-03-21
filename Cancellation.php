<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Cancellation extends DataObject {

		public static function tableName() {
			return "onsong_connect_cancellation";
		}

		public static function className() {
			return "Cancellation";
		}

		public static function forAccount($accountID) {
			global $pdo;

			// Create the SQL statement
			$sql = " SELECT * FROM onsong_connect_cancellation WHERE accountID = ? ORDER BY created DESC ";

			// Execute the statement and return the list
			$list = array();
			$statement = $pdo->prepare($sql);
			$statement->execute(array($accountID));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					$cancellation = new Cancellation($row);
					array_push($list, $cancellation);
				}
			}

			// Return the cancellations
			return $list;
		}

	}
?>