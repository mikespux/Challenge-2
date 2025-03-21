<?php
// Require the framework
require_once(__DIR__ . "/autoload.php");

class DriveMimeType extends DataObject {

	public static function tableName() {
		return "onsong_connect_drive_mimetype";
	}

	public static function className() {
		return "DriveMimeType";
	}

	public static function find($value) {
		global $pdo;

		$column = (strpos($value, "/") === false) ? "extension" : "mimeType";
		if($column == "extension" && strpos($value, ".") > 0) {
			$value = pathinfo($value)['extension'];
		}
		$value = strtolower($value);

		$sql = "SELECT * FROM onsong_connect_drive_mimetype WHERE ". $column ." = ? ";
		$statement = $pdo->prepare($sql);
		$statement->execute(array($value));
		
		$a = array();
		if($statement && $row = $statement->fetch(PDO::FETCH_ASSOC)) {
			array_push($a, new DriveMimeType($row));
		}
		return $a;
	}
}