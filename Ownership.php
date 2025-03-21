<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Ownership extends DataObject {
		
		public function weight($value = false) {
			if($value !== false) {
				parent::weight($value);
			} else {
				$o = parent::weight();
				if(!is_null($o)) {
					return $o;
				} else {
					return 1;
				}
			}
		}

		public function percentage($value = false) {
			if($value !== false) {
				parent::percentage($value);
			} else {
				$o = parent::percentage();
				if(!is_null($o)) {
					return $o;
				} else {
					return 1;
				}
			}
		}

		public function sort($value = false) {
			if($value !== false) {
				parent::sort($value);
			} else {
				$o = parent::sort();
				if(is_null($o)) {
					return $o;
				} else {
					return 1;
				}
			}
		}

		public function jsonSerialize($include = null) {
			$o = parent::jsonSerialize();
			$o["publisher"] = $this->publisher()->jsonSerialize(array("ID", "name"));
			return $o;
		}

		public static function tableName() {
			return "onsong_connect_song_ownership";
		}

		public static function className() {
			return "Ownership";
		}
		
		public static function reset($songID, $processID) {
			global $pdo;
			$sql = "DELETE FROM onsong_connect_song_ownership WHERE songID = ? AND processID IS NOT NULL AND processID <> ? ";
			$statement = $pdo->prepare($sql);
			return $statement->execute(array($songID, $processID));
		}
	}
?>