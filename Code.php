<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Code extends DataObject {

		public function __construct($data = null) {
			parent::__construct($data);
			if(self::code() == null) {
				self::code(static::generateCode(16));
			}
		}

		public static function tableName() {
			return "onsong_connect_code";
		}

		public static function className() {
			return "Code";
		}

		public static function generateCode($length) {
			$code = "";
			$pool = array_merge(range(0,9), range('A', 'Z'));
			for($i=0; $i<$length; $i++) {
				$code .= $pool[mt_rand(0, count($pool) - 1)];
			}
			return $code;
		}
		
		public static function fromCode($code) {
			global $pdo;
			
			// Return null by default
			$o = null;

			// Normalize the code as all caps
			$code = strtoupper($code);

			// Remove any non-alphanumeric characters
			$code = preg_replace("/[^A-Za-z0-9 ]/", '', $code);

			// Perform the lookup
			$sql = "SELECT * FROM onsong_connect_code WHERE code = ? ";
			$statement = $pdo->prepare($sql);
			$statement->execute(array($code));
			if($statement) {
				$row = $statement->fetch(PDO::FETCH_ASSOC);
				if($row) {
					$o = new Code($row);
				}
			}
			
			// Return the output
			return $o;
		}
		
		public function formattedCode() {
			$width = 4;
			$seperator = "-";
			$code = $this->code();
			$o = "";
			while(strlen($code) > 0) {
				$nibble = substr($code, 0, $width);
				$code = substr($code, $width);
				if(strlen($o) > 0) {
					$o .= $seperator;
				}
				$o .= $nibble;
			}
			return $o;
		}
		
		public function redeem($account = null) {
			
			// If we don't have an account, usee the current one
			if($account == null) {
				$account = Account::curren();
			}
			
			// If we still don't have an account, return false;
			if($account == null) { return false; }
			
			// If the code has already been redeemed, reject it
			if($this->redeemed() != null) { return false; }
			
			// If the campaign has expired, then the code can't be redeemed
			if($this->campaign()->isExpired()) { return false; }

			// Determine the base date to add to
			$starting = time();
			if($account->isExpired() == false) {
				$starting = $this->expires();
			}

			// Then go and add the intervals
			$expires = new DateTime($starting);
			$expires->add($this->dateInterval());

			// Set the account expiration
			$account->expires($expires->getTimestamp());
			
			// Set the subscription if we find one
			$account->subscription(Subscription::fromUnit($this->unit()));
			
			// Save the account
			if($account->save()) {
				
				// Now mark the code as redeemed
				$this->redeemed(time());
				
				// Set the account that has redeemed the code
				$this->redeemedBy($account->ID());
				
				// Save the change
				return $this->save();
			}
			
			return false;
		}
		
		public function timeframe() {
			$timeframe = $this->period() ." ". $this->unit();
			if($this->period() != 1) {
				$timeframe .= "s";
			}
			return $timeframe;
		}

		public function dateInterval() {
			if($this->unit() == "year") {
				return new DateInterval("P". $this->period()."Y");
			}
			else if($this->unit() == "month") {
				return new DateInterval("P". $this->period()."M");
			}
			else if($this->unit() == "week") {
				return new DateInterval("P". ($this->period() * 7) ."D");
			}
			else if($this->unit() == "day") {
				return new DateInterval("P". $this->period()."D");
			}
			return null;
		}
	}
?>