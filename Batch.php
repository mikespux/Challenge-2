<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Batch extends DataObject {
		private $codes = null;

		public static function tableName() {
			return "onsong_connect_batch";
		}

		public static function className() {
			return "Batch";
		}

		public function codes() {
			global $pdo;
			
			// If we don't have any codes yet, go and create some
			if($this->codes == null) {

				// Create the empty array
				$this->codes = array();
				
				// Create the SQL statement
				$sql = "SELECT * FROM onsong_connect_code WHERE batchID = ? ORDER BY created";
	
				// Execute the statement and return the list
				$statement = $pdo->prepare($sql);
				$statement->execute(array($this->ID()));
				if($statement) {
					while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
						$code = new Code($row);
						array_push($this->codes, $code);
					}
				}
			}

			// If we don't have enough, let's make more
			while(count($this->codes) < $this->quantity()) {

				// Create a new code
				$code = new Code();
				$code->batch($this);
				$code->period($this->period());
				$code->unit($this->unit());

				// See if we can save this to the database
				if($code->save()) {

					// If we could save the code, then append it to the codes array
					array_push($this->codes, $code);
				}
			}

			// Return the codes
			return $this->codes;
		}
	}
?>