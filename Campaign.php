<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Campaign extends DataObject {
		private $batches = null;

		public static function tableName() {
			return "onsong_connect_campaign";
		}

		public static function className() {
			return "Campaign";
		}

		public function isExpired() {
			if($this->expires() != null) {
				return ($this->expires() < time());
			}
			return false;
		}

		public function batches($value = null) {
			if(is_null($value)) {
				if($this->batches == null) {
					$this->batches = $this->listBatches();
				}
				if(is_int($value)) {
					return $this->batches[intval($value)];
				} else {
					return $this->batches;
				}
			} else if(is_int($value)) {
				return $this->batches()[$value];
			} else {
				if(is_string($value)) {
					$value = array($value);
				}
				if(is_array($value)) {
					$this->batches = $value;
					$this->batches_changed = true;
				}
			}
		}
		
		private function listBatches() {
			global $pdo;

			// Create the result array
			$a = array();

			// Create the SQL statement
			$sql = "SELECT * FROM onsong_connect_batch WHERE campaignID = ? ORDER BY created";

			// Execute the statement and return the list
			$statement = $pdo->prepare($sql);
			$statement->execute(array($this->ID()));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					$batch = new Batch($row);
					array_push($a, $batch);
				}
			}

			// Return the resulting array
			return $a;
		}
	}
?>