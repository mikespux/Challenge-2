<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Administrator extends DataObject {

		public static function tableName() {
			return "onsong_connect_administrator";
		}

		public static function className() {
			return "Administrator";
		}
		
		public static function find($lookup) {
			global $pdo;
			
			$a = array();
			$sql = "SELECT * FROM onsong_connect_administrator WHERE code = ? OR name = ? LIMIT 1";
			$statement = $pdo->prepare($sql);
			$statement->execute(array($lookup, $lookup));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					return new Publisher($row);
				}
			}
			return null;
		}
	}
?>