<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class LocationState extends DataObject {

		public static function tableName() {
			return "onsong_state";
		}

		public static function className() {
			return "LocationState";
		}
		
		public static function find($named, $country = null) {
			global $pdo;
			$params = array($named, $named);
			$sql = "SELECT * FROM onsong_state WHERE ( ID LIKE ? OR name LIKE ? ) ";
			if(!empty($country)) {
				array_push($params, $country);
				$sql .= " AND countryID = ? ";
			}
			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			if($statement) {
				$row = $statement->fetch(PDO::FETCH_ASSOC);
				return new LocationState($row);
			}
			return null;
		}
	}
?>