<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	include_once($_SERVER['DOCUMENT_ROOT'] . '/assets/includes/sendgrid.php');

	class GiftCard extends DataObject {

		public static function tableName() {
			return "onsong_connect_gift_card";
		}

		public static function className() {
			return "GiftCard";
		}
		
		public static function queryList() {
			return array("ID", "code", "paymentID", "fromEmail", "fromName", "toEmail", "toName");
		}
		
		public function product() {
			return "OnSong ". $this->plan()->tier()->name();
		}
		
		public function duration() {
			return $this->quantity(). " ". pluralize($this->quantity(), $this->unit());
		}
		
		public function statusText() {
			if($this->isRedeemed()) {
				return "Redeemed";
			} else if($this->isClaimed()) {
				return "Claimed";
			} else if($this->isDelivered()) {
				return "Delivered";
			} else if($this->isActivated()) {
				return "Activated";
			} else {
				return "Created";
			}
		}
		
		public function statusDate() {
			if($this->isRedeemed()) {
				return $this->redeemedOn();
			} else if($this->isClaimed()) {
				return $this->claimedOn();
			} else if($this->isDelivered()) {
				return $this->deliveredOn();
			} else if($this->isActivated()) {
				return $this->activatedOn();
			} else {
				return $this->created();
			}
		}
		
		public function code($value = "____") {
			if($value == "____") {
				if(empty(parent::code())) {
					parent::code(strtoupper(uniqid()));
				}
				return parent::code();
			} else {
				parent::code($value);
			}
		}
		
		public function claimedByUser() {
			if(!empty($this->claimedByUserID())) {
				return User::retrieve($this->claimedByUserID());
			}
			return null;
		}
		
		public function redeemedByAccount() {
			if(!empty($this->redeemedByAccountID())) {
				return Account::retrieve($this->redeemedByAccountID());
			}
			return null;
		}
		
		public function isActivated() {
			return (!empty($this->activatedOn()) && $this->activatedOn() < time());
		}
		
		public function activate($paymentID = null, &$errors = null) {
			
			// Set up errors if we don't have then already
			if(is_null($errors)) {
				$errors = array();
			}

			// If it's already been activated, bail
			if(!empty($this->activatedOn())) {
				array_push($errors, "The gift card was already been activated");
			}

			// If this has already been activated
			if(count($errors) == 0) {
				$this->activatedOn(time());
				$this->paymentID($paymentID);
				return $this->save($errors);
			}

			// Otherwise, we fail
			return false;
		}
		
		public function sendReceipt() {

			// If this isn't a singly purchased gift card
			if(empty($this->fromEmail())) {
				return false;
			}

			// Then send the sender a confirmation that it was delivered
			$additional = array("card"=>$this);
			$from = array("email"=>"no-reply@onsongapp.com");
			$recipients = array(array("to"=>array(array("email"=>$this->fromEmail(), "name"=>$this->fromName())), "dynamic_template_data"=>$additional));
			$post = array("template_id"=>"d-fc5169486a2b46caa7ce3439af5ca837", "personalizations"=>$recipients, "from"=>$from); // Send the receipt email.
			sendgrid_call("mail/send", "POST", $post);
			return true;
		}
		
		public function isDelivered() {
			return (!empty($this->deliveredOn()) && $this->deliveredOn() < time());
		}
		
		public function deliver(&$errors = null, $force = false) {
			
			// If force is false, and we are to deliver later, then bail
			if($force == false && (empty($this->templateID()) || empty($this->template()->sendgrid()))) {
				if(!is_null($errors)) {
					array_push($errors, "No email template selected to deliver");
				}
				return false;
			}

			// If force is false, and we are to deliver later, then bail
			if($force == false && !empty($this->deliverOn()) && $this->deliverOn() > time()) {
				if(!is_null($errors)) {
					array_push($errors, "The delivery date is in the future");
				}
				return false;
			}

			// Get the template ID to use
			$templateID = (!empty($this->template()) && !empty($this->template()->sendgrid())) ? $this->template()->sendgrid() : GiftCardTemplate::default()->sendgrid();

			// Otherwise, let's generate the gift card content
			$additional = array("card"=>$this);
			$from = array("email"=>"no-reply@onsongapp.com");
			$replyTo = array("email"=>$this->fromEmail(), "name"=>$this->fromName());
			$recipients = array(array("to"=>array(array("email"=>$this->toEmail(), "name"=>$this->toName())), "dynamic_template_data"=>$additional));
			$post = array("template_id"=>$templateID, "personalizations"=>$recipients, "from"=>$from, "reply_to"=>$replyTo);
			sendgrid_call("mail/send", "POST", $post);

			// Then say that we've delivered the mail
			$this->deliveredOn(time());
			if($this->save()) {
				
				// Then send the sender a confirmation that it was delivered
				$additional = array("card"=>$this);
				$from = array("email"=>"no-reply@onsongapp.com");
				$recipients = array(array("to"=>array(array("email"=>$this->fromEmail(), "name"=>$this->fromName())), "dynamic_template_data"=>$additional));
				$post = array("template_id"=>"d-4eb8d438867a41f3a672b4926d8756a1", "personalizations"=>$recipients, "from"=>$from); // Send the confirmation email.
				sendgrid_call("mail/send", "POST", $post);
				return true;
			}
			return false;
		}
		
		public function isRefunded() {
			return (!empty($this->refundedOn()) && $this->refundedOn() < time());
		}
		
		public function isClaimed() {
			return (!empty($this->claimedOn()) && $this->claimedOn() < time());
		}

		public function claim(&$errors = null) {
			
			// Create errors array if we need to
			if(is_null($errors)) {
				$errors = array();
			}

			// If it hasn't been activated, we can't claim it
			if(!empty($this->activatedOn())) {
				if(!is_null($errors)) {
					array_push($errors, "The gift card has not been activated");
				}
			}

			// If it's already been claimed, bail
			if(!empty($this->claimedOn())) {
				if(!is_null($errors)) {
					array_push($errors, "The gift card was already claimed");
				}
			}
			
			// Make sure that we have a user
			if(empty(User::current())) {
				if(!is_null($errors)) {
					array_push($errors, "The gift card must be claimed by an authenticated user");
				}
			}
			
			// If we do, then set the user and claim the card
			if(count($errors) == 0) {
				$this->claimedOn(time());
				$this->claimedByUserID(User::current()->ID());
				return $this->save($errors);
			} else {
				return false;
			}
		}
		
		public function applicableAccounts($user = null, &$errors = null) {
			
			// If the user is null, use the currently signed in user
			if(empty($user)) {
				$user = User::current();
			}
			
			// If we have no users, then return null
			if(empty($user)) {
				return array();
			}

			// See if we have an account for the user that we can apply the gift card to
			$o = array();
			foreach($user->roles() as $role) {
				
				// Make sure that we have billing access
				if($role->has(Role::PERMISSIONS_BILLING) == false) {
					continue;
				}

				// Get the account
				$account = $role->account();
				
				// If it's applicable, add it
				if($this->isApplicable($account, $errors)) {
					array_push($o, $account);
				}
			}

			// Sort each array by the expiration descending
			if(count($o) > 1) {
				usort($o, function($a, $b) {
					$ae = strtotime($a->expires() ?? "+10 years");
					$be = strtotime($b->expires() ?? "+10 years");
					return ($be - $ae);
				});
			}

			// Now return the array
			return $o;
		}

		public static function expiringAccounts($withinDays = 1) {
			$ends = date("Y-m-d H:i:s", strtotime($withinDays ." days", time()));
			$starts = date("Y-m-d H:i:s", strtotime($withinDays - 1 ." days", time()));
			$params = array("sourceID"=>"gift_card","expires"=>array($starts=>">="),"expires"=>array($ends=>"<="));
			return Account::list($params)->results();
		}

		public function isApplicable($account, &$errors = null) {
			
			// Make an array if needed
			if(is_null($errors)) {
				$errors = array();
			}

			// If the source is empty or gift card, we can use it
			if(empty($account->sourceID()) || $account->sourceID() == "gift_card") {
				return true;
			}

			// If the account was deleted, fail
			if($account->isDeleted()) {
				array_push($errors, "The account was deleted");
				return false;
			}
			
			// If the account was cancelled, fail
			else if($account->isCancelled()) {
				array_push($errors, "The account was cancelled");
				return false;
			}

			// If the account is expired
			else if($account->isExpired()) {
				array_push($errors, "The account has already expired");
				return false;
			}

			// Don't permit gift cards to be applied to lifetime subscriptions
			else if(!empty($account->plan()) && $account->plan()->unit() == "lifetime") {
				array_push($errors, "The account has a lifetime duration and gift cards cannot be applied");
				return false;
			}

			// Then we need to determine if the subscription can be used
			else if(!empty($account->plan()) && ($account->plan()->tierID() != $this->plan()->tierID())) {
				array_push($errors, "The subscription account must have the same feature tier as the gift card");
				return false;
			}
			
			// Return true if we've gotten this far
			return true;
		}
		
		public function isRedeemable() {
			return ($this->isActivated() && $this->isRedeemed() == false);
		}
		
		public function isRedeemed() {
			return (!empty($this->redeemedOn()));
		}
		
		public function redeem($account = null, &$errors = null) {
			
			// Create errors array if we need to
			if(is_null($errors)) {
				$errors = array();
			}

			// If it hasn't been activated, we can't claim it
			if($this->isActivated() == false) {
				array_push($errors, "The gift card has not been activated");
			}

			// If it's already been redeemed, bail
			if($this->isRedeemed()) {
				array_push($errors, "The gift card was already redeemed");
			}

			// Make sure that we have an authenticated user
			if(!empty($account) && $this->isApplicable($account, $errors) == false) {
				array_push($errors, "The gift card must be applied to a valid account");
			}

			// If we have errors, fail
			if(count($errors) > 0) {
				return false;
			}

			// If we have no account, then make one
			if(empty($account)) {
				$account = new Account();
				if($account->save($errors)) {
					
					$role = Role::create($account, User::current(), Role::PERMISSIONS_ALL, "Administrator");
					if($role == null) {
						array_push($errors, "Could not create role");
					} else {
					
						// Save the role relationship
						if($role->save($errors) == false) {
							array_push($errors, "Could not save role");
						}
					}
				}
			}

			// If we do, then set the account and redeem the card
			if(count($errors) == 0) {

				// If it wasn't claimed, let's do that
				if(empty($this->claimedOn())) {
					$this->claimedOn(time());
					$this->claimedByUserID(User::current()->ID());
				}
				
				// Append the duration in time to the expiration date of the account
				$baseExpires = null;
				if(!empty($account->expires())) {
					$baseExpires = $account->expires();
					if($baseExpires < time()) {
						$baseExpires = time();
					}
				}

				if(empty($baseExpires)) {
					$baseExpires = time();
				}

				// Now add the time from the gift card to the account
				$expires = strtotime("+" . $this->quantity() ." ". pluralize($this->quantity(), $this->unit()), $baseExpires);

				// Add an additional three days grace to handle 31 day months and just to be nice
				$expires = strtotime("+3 days", $expires);
				
				// Now calculate the credit by subtracting the base from the expiration
				$credit = $expires - $baseExpires;
				
				// If this is a new account without a plan
				if(empty($account->planID()) || $account->plan()->tier()->priority() < $this->plan()->tier()->priority()) {

					// Then set the plan on the gift card
					$account->planID($this->planID());
				}

				// If we've never had a source, set that to a gift card
				if(empty($account->sourceID())) {
					$account->sourceID("gift_card");
					$account->subscriberID($this->ID());
				}
				
				// Update the period, credit, and expiration date
				$account->periodID("normal");
				$account->credit($credit);
				$account->expires($baseExpires);

				// Clear out any deleted or cancelled status
				$account->deleted(null);
				$account->cancelled(null);
				
				// Save the account information
				if($account->save($errors)) {

					// Then set the redemption date and account
					$this->redeemedOn(time());
					$this->redeemedByAccountID($account->ID());
					return $this->save($errors);
				}
			}
			return false;
		}
		
		public static function find($code) {
			global $pdo;
			$a = array();
			$sql = "SELECT * FROM onsong_connect_gift_card WHERE code LIKE ? ";
			$statement = $pdo->prepare($sql);
			$statement->execute(array($code));
			if($statement) {
				$row = $statement->fetch(PDO::FETCH_ASSOC);
				if($row) {
					return new GiftCard($row);
				}
			}
			return null;
		}
		
		public static function redeemedBy($account) {
			global $pdo;
			$a = array();
			$sql = "SELECT * FROM onsong_connect_gift_card WHERE redeemedByAccountID = ? ORDER BY redeemedOn DESC";
			$statement = $pdo->prepare($sql);
			$statement->execute(array($account->ID()));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					array_push($a, new GiftCard($row));
				}
			}
			return $a;
		}
		
		public static function deliverableBy($date = null) {
			global $pdo;
			
			// Normalize the date
			if(is_null($date)) {
				$date = time();
			}
			if(is_integer($date)) {
				$date = date('Y-m-d H:i:s', $date);
			}

			$a = array();
			$params = array($date);
			$sql = "SELECT * FROM onsong_connect_gift_card WHERE deliveredOn IS NULL AND deliverOn IS NOT NULL AND deliverOn <= ? ORDER BY deliverOn ";
			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					array_push($a, new GiftCard($row));
				}
			}
			return $a;
		}
		
		public static function inactivated($remindedSince = null) {
			global $pdo;
			
			$a = array();
			$params = array();
			$sql = "SELECT * FROM onsong_connect_gift_card WHERE activatedOn IS NULL AND remindedOn IS NULL";
			if(!empty($remindedSince)) {
				$sql .= " OR remindedOn <= ?";
				array_push($params, $remindedSince);
			}
			$sql .= " ORDER BY created ";
			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					array_push($a, new GiftCard($row));
				}
			}
			return $a;
		}
		
		#[\ReturnTypeWillChange]
		public function jsonSerialize() {
			return self::jsonSerializeIncluding(null);
		}
				
		public function jsonSerializeIncluding($include = null) {
			$o = parent::jsonSerializeIncluding($include = null);
			$o["duration"] = $this->duration();
			$o["product"] = $this->product();
			return $o;
		}
	}
?>