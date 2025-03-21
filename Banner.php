<?php
// Require the framework
require_once(__DIR__ . "/autoload.php");

class Banner extends DataObject {

	public static function tableName() {
		return "onsong_connect_banner";
	}

	public static function className() {
		return "Banner";
	}
	
	public function proximity($datetime = null) {
		
		// If the date/time is null, then make it now
		$t = null;
		if(!empty($datetime)) {
			if(is_numeric($datetime)) {
				$t = $datetime;
			} else {
				$t = strtotime($datetime);
			}
		}
		if(empty($t)) {
			$t = time();
		}
		
		$p = 0;
		if(!empty($this->starts())) {
			$a = $this->starts() - $t;
			if($a > $p) {
				$p = $a;
			}
		}
		if(!empty($this->ends())) {
			$b = $t - $this->ends();
			if($b > $p) {
				$p = $b;
			}
		}
		return $p;
	}
	
	public function isCurrent($datetime = null) {
		return ($this->proximity() == 0);
	}
	
	public static function current($datetime = null) {
		global $pdo;

		// If the date/time is null, then make it now
		$t = null;
		if(!empty($datetime)) {
			if(is_numeric($datetime)) {
				$t = $datetime;
			} else {
				$t = strtotime($datetime);
			}
		}
		if(empty($t)) {
			$t = time();
		}
		
		// Convert that to a MySQL time
		$d = date("Y-m-d H:i:s");
		
		// Get a list of applicable banners for the date/time
		$a = array();
		$sql = "SELECT * FROM onsong_connect_banner WHERE (starts < ? || starts IS NULL) AND COALESCE(ends, '9999-12-31') > ? ";
		$statement = $pdo->prepare($sql);
		$statement->execute(array($d, $d));
		if($statement) {
			while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
				array_push($a, new Banner($row));
			}
		}
		
		if(count($a) > 0) {
			return $a[array_rand($a)];
		} else {
			return null;
		}
	}
}