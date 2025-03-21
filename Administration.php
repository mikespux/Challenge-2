<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Administration extends DataObject {

		public static function tableName() {
			return "onsong_connect_song_administration";
		}

		public static function className() {
			return "Administration";
		}
		
		public static function reset($songID, $processID) {
			global $pdo;
			$sql = "DELETE FROM onsong_connect_administration WHERE songID = ? AND processID IS NOT NULL AND processID <> ? ";
			$statement = $pdo->prepare($sql);
			return $statement->execute(array($songID, $processID));
		}
	}
?>