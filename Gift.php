<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Gift extends DataObject {

		public static function tableName() {
			return "onsong_connect_gift";
		}

		public static function className() {
			return "Gift";
		}
		
		public function product() {
			return "OnSong ". $this->plan()->tier()->name();
		}
		
		public function duration() {
			return $this->quantity(). " ". pluralize($this->quantity(), $this->unit());
		}
		
		public function isBulk() {
			return ($this->quantityMax() != 1);
		}
		
		public static function fromDuration($unit = 'year', $quantity = 1) {
			global $pdo;
			$sql = "SELECT * FROM onsong_connect_gift WHERE unit = ? AND quantity = ?";
			$statement = $pdo->prepare($sql);
			$statement->execute(array($unit, $quantity));
			if($statement) {
				$row = $statement->fetch(PDO::FETCH_ASSOC);
				if($row) {
					return new Gift($row);
				}
			}
			return null;
		}
	}
?>