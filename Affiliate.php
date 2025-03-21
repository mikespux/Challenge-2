<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	require_once($_SERVER['DOCUMENT_ROOT'] . '/assets/classes/stripe/init.php');

	class Affiliate extends DataObject {

		public static function tableName() {
			return "onsong_connect_affiliate";
		}

		public static function className() {
			return "Affiliate";
		}
		
		public function set($additional = null) {
			if(!empty($this->code())) {
				
				// Set the cookie
				if(cookies_enabled()) {
					setcookie("affiliateCode", $this->code(), strtotime("+". $this->duration() ." day"), '/');
				}

				// Create the click record
				$click = new AffiliateClick();
				$click->affiliateID($this->ID());
				$click->additional($additional);
				
				if(!empty(client_ip_address())) {
					$click->ipAddress(client_ip_address());
					$click->geolocate();
				}
				$click->save();

				return true;
			}
			return false;
		}
		
		public function hasPromotion() {
			return (!empty($_COOKIE['promotionID_'. $this->code()]));
		}

		public function currentPromotion() {
			global $stripeAPISecret;

			if(!empty($_COOKIE['promotionID_'. $this->code()])) {
				$stripe = new \Stripe\StripeClient($stripeAPISecret);

				try {
					$promotion = $stripe->promotionCodes->retrieve($_COOKIE['promotionID_'. $this->code()]);
				} catch(Exception $ex) {
				}
			}

			// If we have no promotion already
			if(empty($promotion)) {

				// Then generate one from the coupon code
				$promotion = $this->retrievePromotion();
			}

			// If we have a promotion, 
			if(!empty($promotion)) {

				// Then let's store the ID so we don't generate again
				if(cookies_enabled()) {
					setcookie('promotionID_'. $this->code(), $promotion->id, strtotime("+5 year"), '/');
				}
			}

			return $promotion;
		}
		
		public function retrievePromotion() {
			global $stripeAPISecret;
			
			// If we don't have a coupon code, then we have nothing
			if(empty($this->couponCode())) { return null; }

			// Otherwise, try to generate the promo code from the coupon
			$stripe = new \Stripe\StripeClient($stripeAPISecret);
			$promo = null;

			try {
				$promo = $stripe->promotionCodes->create(['coupon'=>$this->couponCode(), 'max_redemptions'=>1, 'expires_at'=>strtotime("+". $this->duration() ." day"), 'restrictions'=>array('first_time_transaction'=>true)]);
			} catch(Exception $ex) {
				try {
					$promo = $stripe->promotionCodes->create(['coupon'=>$this->couponCode(), 'max_redemptions'=>1, 'restrictions'=>array('first_time_transaction'=>true)]);
				} catch(Exception $ex) {
				}
			}

			// If we have a promotion,
			return $promo;
		}
		
		public function organizations($index = null) {
			global $pdo;
			$a = array();
			$sql = "SELECT o.* FROM onsong_connect_organization o INNER JOIN onsong_connect_account a ON a.organizationID = o.ID WHERE a.affiliateID = ? ORDER BY a.created ";
			$statement = $pdo->prepare($sql);
			$statement->execute(array($this->ID()));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					array_push($a, new Organization($row));
				}
			}
			if(isset($index)) {
				if($index < count($a)) {
					return $a[$index];
				} else {
					return null;
				}
			}
			return $a;
		}

		public static function lookup($sourceID = 'stripe') {
			global $pdo;
			
			// Get the lookup table
			$lookup = array();
			$sql = "SELECT o.customerID, a.affiliateID FROM onsong_connect_account a INNER JOIN onsong_connect_organization o ON a.organizationID = o.ID WHERE a.sourceID = ? AND a.affiliateID IS NOT NULL AND o.customerID IS NOT NULL ";
			$statement = $pdo->prepare($sql);
			$statement->execute(array($sourceID));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					$lookup[$row['customerID']] = $row['affiliateID'];
				}
			}
			return $lookup;
		}

		public static function find($code) {
			global $pdo;
			
			// If it's empty, quit now
			if(empty($code)) { return null; }

			// Retrieve the affiliate using the code
			$sql = "SELECT * FROM onsong_connect_affiliate WHERE ID = ? OR code = ? OR ? LIKE website ";
			$statement = $pdo->prepare($sql);
			$statement->execute(array($code, $code, $code));
			if($statement && $row = $statement->fetch(PDO::FETCH_ASSOC)) {
				return new Affiliate($row);
			}
			return null;
		}
		
		public static function current() {
			
			// If we have an affiliate code in session
			if(!empty($_COOKIE["affiliateCode"])) {
				
				// Then find the affiliate
				return Affiliate::find($_COOKIE["affiliateCode"]);
			}
			
			// Otherwise, return null
			return null;
		}
		
		public static function capture() {
			$affiliateCode = retrieve_param("affiliateCode");
			if(empty($affiliateCode)) {
				$affiliateCode = retrieve_param("affiliateID");
			}
			if(empty($affiliateCode)) {
				$affiliateCode = retrieve_param("affiliate");
			}
			if(empty($affiliateCode)) {
				$affiliateCode = retrieve_param("aid");
			}
			if(!empty($affiliateCode)) {
				$affiliate = Affiliate::find($affiliateCode);
				if(!empty($affiliate)) {
					$affiliate->set();
				}
			}
		}
	}
?>