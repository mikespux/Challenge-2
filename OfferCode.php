<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class OfferCode extends DataObject {

		public static function tableName() {
			return "onsong_connect_offer_code";
		}

		public static function className() {
			return "OfferCode";
		}

		public static function forAccount($accountID) {
			global $pdo;
			
			// If we don't have an account, then bail
			if(empty($accountID)) { return null; }
			
			// Make sure the account exists
			$account = Account::retrieve($accountID);
			if(empty($account)) { return null; }

			// Create the SQL statement
			$sql = " SELECT c.* FROM onsong_connect_offer_code c INNER JOIN onsong_connect_offer o ON o.ID = c.offerID WHERE c.accountID = ? AND o.pro = ? AND c.expires > NOW() ORDER BY c.redeemed DESC LIMIT 1 ";

			// Execute the statement
			$statement = $pdo->prepare($sql);
			$statement->execute(array($accountID, $account->isPro()));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					
					// If we have one, return the code since the yser can only use one at a time.
					return new OfferCode($row);
				}
			}
			
			// Otherwise, let's provision a new code
			$sql = " SELECT c.* FROM onsong_connect_offer_code c INNER JOIN onsong_connect_offer o ON o.ID = c.offerID WHERE c.accountID IS NULL AND c.expires > NOW() AND o.pro = ? ORDER BY c.expires LIMIT 1 ";

			// Execute the statement
			$statement = $pdo->prepare($sql);
			$statement->execute(array($account->isPro()));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					
					// If we have one, create it
					$code = new OfferCode($row);
					
					// Now set the account and the redemption date
					$code->accountID($accountID);
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