<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	require_once($_SERVER['DOCUMENT_ROOT'] . '/assets/classes/stripe/init.php');

	class Organization extends DataObject {
		
		private $taxCode;

		public static function tableName() {
			return "onsong_connect_organization";
		}

		public static function className() {
			return "Organization";
		}
		
		public function account() {
			global $pdo;

			// Create the SQL statement
			$sql = " SELECT * FROM onsong_connect_account WHERE organizationID = ? LIMIT 1";

			// Execute the statement and return the list
			$statement = $pdo->prepare($sql);
			$statement->execute(array($this->ID()));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					return new Account($row);
				}
			}
			return null;
		}
		
		public function administrator() {
			$account = $this->account();
			if(!empty($account)) {
				$admins = $account->members(Role::PERMISSIONS_ALL);
				if(count($admins) > 0) {
					return $admins[0];
				}
			}
			return null;
		}
		
		public function address($seperator = ", ") {
			$s = "";
			if(!empty($this->address1())) {
				$s .= $this->address1();
			}
			if(!empty($this->address2())) {
				if(strlen($s) > 0) {
					$s .= $seperator;
				}
				$s .= $this->address2();
			}
			if(!empty($this->city())) {
				if(strlen($s) > 0) {
					$s .= $seperator;
				}
				$s .= $this->city();
			}
			if(!empty($this->state())) {
				if(strlen($s) > 0) {
					$s .= ", ";
				}
				$s .= $this->state();
			}
			if(!empty($this->postalCode())) {
				if(strlen($s) > 0) {
					$s .= " ";
				}
				$s .= $this->postalCode();
			}
			if(!empty($this->country())) {
				if(strlen($s) > 0) {
					$s .= " ";
				}
				$s .= $this->country();
			}
			return $s;
		}
		
		public function hasTaxLocale() {
			
			// If we have no country, then no
			if(empty($this->country()) || strlen($this->country()) != 2) {
				return false;
			}

			// Next, see if we have states that are needed
			$states = LocationState::list(["countryID"=>$this->ID()])->results();
			
			// If we have states, make sure you have a state field
			if(count($states) > 0 && empty($this->state() || strlen($this->state() > 2))) {
				return false;
			}

			// Otherwise, we are good to go
			return true;
		}
		
		public static function taxRates() {
			global $stripeAPISecret;
			$stripe = new \Stripe\StripeClient($stripeAPISecret);
			$a = array();
			foreach($stripe->taxRates->all() as $taxRate) {
				if($taxRate->active) {
					array_push($a, $taxRate);
				}
			}
			return $a;
		}
		
		public function taxRate() {

			// If we are tax exempt or no tax locale, then null
			if(boolval($this->taxExempt()) || $this->hasTaxLocale() == false) {
				return null;
			}

			// Get tax rates from Stripe
			$taxRates = $this->taxRates();
			
			// Now try to filter
			$countryRate = null;
			$stateRate = null;
			foreach(static::taxRates() as $taxRate) {
				if($this->country() == $taxRate->country) {
					if(empty($taxRate->state)) {
						$countryRate = $taxRate;
					} else if($taxRate->state == $this->state()) {
						$stateRate = $taxRate;
						break;
					}
				}
			}
			
			// If we have a state rate, return it
			if(!empty($stateRate)) {
				return $stateRate;
			}

			// Otherwise, return the country rate
			return $countryRate;
		}

		public function taxCode() {
			if(empty($this->taxCode)) {
				$this->taxCode = TaxCode::forCountry($this->country());
			}
			return $this->taxCode;
		}
	
		public function sync() {
			global $stripeAPISecret;

			// Set up the Stripe SDK
			$stripe = new \Stripe\StripeClient($stripeAPISecret);

			// Create an array of the updates
			$o = array("tax_exempt"=>((bool)$this->taxExempt()) ? "exempt" : "none");
			if(!empty($this->name())) {
				$o["name"] = $this->name();
			}

			$a = array();
			if(!empty($this->address1())) {
				$a["line1"] = $this->address1();
			}
			if(!empty($this->address2())) {
				$a["line2"] = $this->address2();
			}
			if(!empty($this->city())) {
				$a["city"] = $this->city();
			}
			if(!empty($this->state())) {
				$a["state"] = $this->state();
			}
			if(!empty($this->postalCode())) {
				$a["postal_code"] = $this->postalCode();
			}
			if(!empty($this->country())) {
				$a["country"] = $this->country();
			}
			if(count($a) > 0) {
				$o["address"] = $a;
			}
			
			// Add an email
			$admin = $this->administrator();
			if(!empty($admin)) {
				$o["email"] = $admin->email();
			}
			
			// If we already have a customer ID
			if($this->sourceID() == "stripe" && empty($this->customerID()) == false) {
			
				// Let's update the customer
				try {
					$stripe->customers->update($this->customerID(), $o);
				} catch(Exception $ex) {
					return false;
				}
			}
			
			// Otherwise, 
			else {

				// Let's create a new customer
				try {
					$result = $stripe->customers->create($o);
				} catch(Exception $ex) {
					return false;
				}
				
				// If successful
				$this->sourceID("stripe");
				$this->customerID($result->id);
			}
			
			// Let's update the EIN number quick
			if(empty($this->taxID()) == false) {
				$state = LocationState::retrieve($this->state());
				if(!empty($state)) {
					$taxCode = $state->taxCode();
					if(!empty($taxCode)) {

						// Let's try to update the customer
						try {
							$stripe->customers->update($this->customerID(), array("tax_id_data"=>array([$taxCode->code()=>$this->taxID()])));
						} catch(Exception $ex) {
							return false;
						}
					}
				}
			}
/*
	// Disabled because we are using automatic tax rates now
			// Now find the organization's account
			$account = $this->account();
				
			// If we have a subscription, find it
			if(!empty($account) && $account->sourceID() == "stripe" && !empty($account->subscriberID())) {
					
				// Calculate the default tax rate
				$defaultTaxRates = array();
				$taxRate = $this->taxRate();
				if(!empty($taxRate)) {
					array_push($defaultTaxRates, $taxRate->id);
				}
				
				// Update the rate in the subscription
				$stripe->subscriptions->update($account->subscriberID(), ["default_tax_rates" => $defaultTaxRates]);
				
				// Now update the open invoices for the subscription too
				foreach($stripe->invoices->all(['status' => 'open', 'subscription' => $account->subscriberID()]) as $invoice) {
					
					// Now set the tax rate for the invoice
					$stripe->invoices->update($invoice->id, ["default_tax_rates" => $defaultTaxRates]);
				}
			}
*/
			// Return true
			return true;
		}
	}
?>