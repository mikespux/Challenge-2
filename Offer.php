<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Offer extends DataObject {

		public static function tableName() {
			return "onsong_connect_offer";
		}

		public static function className() {
			return "Offer";
		}
		
		public function nextAvailable() {
			global $pdo;
			
			// Otherwise, let's provision a new code
			$sql = " SELECT c.* FROM onsong_connect_offer_code c INNER JOIN onsong_connect_offer o ON o.ID = c.offerID WHERE c.offerID = ? AND c.redeemed IS NULL AND c.expires > NOW() ORDER BY c.expires LIMIT 1 ";

			// Execute the statement
			$statement = $pdo->prepare($sql);
			$statement->execute(array($this->ID()));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {

					// If we have one, create it
					$code = new OfferCode($row);
					
					// Redeem this code so it's not used again
					$code->redeemed(time());
					if($code->save()) {
						return $code;
					}
					
					break;
				}
			}

			// Otherwise, return nil because there may be a problem.
			return null;
		}
	}
?>