<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	require_once($_SERVER['DOCUMENT_ROOT'] . '/assets/classes/stripe/init.php');

	class Invoice extends DataObject {

		public static function tableName() {
			return "onsong_connect_invoice";
		}

		public static function className() {
			return "Invoice";
		}
		
		public static function last() {
			global $pdo;

			// Create the SQL statement
			$sql = " SELECT * FROM onsong_connect_invoice ORDER BY created DESC LIMIT 1";

			// Execute the statement and return the list
			$statement = $pdo->prepare($sql);
			$statement->execute();
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					return new Invoice($row);
				}
			}
			return null;
		}
		
		public static function refresh() {
			global $stripeAPISecret;
			
			// Set up stripe
			$stripe = new \Stripe\StripeClient($stripeAPISecret);
			
			// Get the last known invoice that was downloaded from Stripe
			$last = static::last();
			$list = null;
			
			// Now go and get the last known 
			while($list == null || $list->has_more) {
				$params = array('limit'=>100);
				if(!empty($list)) {
					$last = $list->data[count($list->data)-1];
//					$params['starting_after'] = $last->id;
//				} else if(!empty($last)) {
//					$params['created'] = array("gt"=>$last->created());
				}
				$list = $stripe->invoices->all($params);

				// Now add the items to the invoices array
				foreach($list->data as $item) {
					
					// Now create a new Invoice record
					$invoice = new Invoice();
					$invoice->ID($item->id);
					$invoice->customerID($item->customer);
					if(!empty($item->customer_address)) {
						$invoice->state($item->customer_address->state);
						$invoice->country($item->customer_address->country);
					}
					$invoice->subtotal($item->subtotal);
					if(empty($item->tax)) {
						$invoice->tax(0);
					} else {
						$invoice->tax($item->tax);
					}
					$invoice->total($item->total);		
					$invoice->paid($item->created);
					$invoice->created($item->created);

					// Now save the invoice
					$invoice->save();
				}
			}
		}

		public static function affiliateReports($options = null) {
			global $pdo;
			
			// If we have no options, make a blank array
			if($options == null) { $options = array(); }

			// Make a list
			$a = array();

			// First, refresh the invoices
			static::refresh();
			
			// Set up the parameters with start/ending dates
			$params = array();

			$includeEmpty = true;
			if(!empty($options["includeEmpty"])) {
				$includeEmpty = boolval($options["includeEmpty"]);
			}

			// Add the dates where applicable
			if(!empty($options["starts"])) {
				if(is_string($options["starts"])) {
					$options["starts"] = strtotime($options["starts"]);
				}
				array_push($params, date('Y-m-d', $options["starts"]));
			}
			if(!empty($options["ends"])) {
				if(is_string($options["ends"])) {
					$options["ends"] = strtotime($options["ends"]);
				}
				array_push($params, date('Y-m-d', $options["ends"]));
			}

			// Then filter for affiliate if needed
			if(!empty($options["affiliateID"])) {
				$array_push($params, $options["affiliateID"]);
			}
			
			// Now set up grouping
			$grouping = "'—'";
			if(!empty($options["grouping"])) {
				
				// If we are set to auto, handle it by evaluating the difference between the start/end dates
				if($options["grouping"] == "auto") {
					$test = "day";
					
					// Calculate the start and end
					if(!empty($options["starts"])) {
						$starts = $options["starts"];
					} else {
						$starts = strtotime("2021-07-01");
					}
					
					if(!empty($options["ends"])) {
						$ends = $options["ends"];
					} else {
						$ends = time();
					}
					
					// Now determine the distance between
					$s = date_create(date("c", $starts));
					$e = date_create(date("c", $ends));
					$d = date_diff($s, $e)->days;
					
					// If it's longer than say 720 days
					if($d >= 1000) {
						$test = "year";
					}
					else if($d > 450) {
						$test = "quarter";
					}
					else if($d > 35) {
						$test = "month";
					}
				
				} else {
					$test = strtolower($options["grouping"]);
				}
				
				// Then handle based on the grouping
				if($test == "day") {
					$grouping = "DATE_FORMAT(i.paid, '%Y-%m-%d')";
				}
				else if($test== "month") {
					$grouping = "DATE_FORMAT(i.paid, '%Y-%m')";
				}
				else if($test == "quarter") {
					$grouping = "CONCAT(YEAR(i.paid), ' Q', QUARTER(i.paid))";
				}
				else if($test == "week") {
					$grouping = "DATE_FORMAT(i.paid, '%Y Week %V')";
				}
				else if($test == "year") {
					$grouping = "DATE_FORMAT(i.paid, '%Y')";
				}
			}

			// Create the SQL statement
			$sql = "SELECT ";
			if(!empty($grouping)) {
				$sql .= $grouping ." AS date,";
			}
			$sql .= " a.ID AS affiliateID, a.name AS affiliateName, COUNT(i.ID) AS transactions, SUM(i.subtotal) AS subtotal, SUM(i.tax) AS tax, SUM(i.total) AS total FROM onsong_connect_invoice i INNER JOIN onsong_connect_organization o ON o.customerID = i.customerID INNER JOIN onsong_connect_affiliate a ON o.affiliateID = a.ID WHERE i.total > 0 ";
			if(!empty($options["starts"])) {
				$sql .= " AND i.paid >= ? ";
			}
			if(!empty($options["ends"])) {
				$sql .= " AND i.paid < ? ";
			}
			if(!empty($options["affiliates"])) {
				$sql .= " AND o.affiliateID IN('". implode("', '", $options["affiliates"]) ."') ";
			}
			$sql .= " GROUP BY date, a.ID, a.name ORDER BY date, a.name ";

			// Prepare and execute the statement
			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					if($includeEmpty || $row["transactions"] > 0) {
						array_push($a, $row);
					}
				}
			}

			// Return the list of processed items
			return $a;
		}

		public static function trendReports($options = null) {
			global $pdo;
			
			// If we have no options, make a blank array
			if($options == null) { $options = array(); }

			// Make a list
			$a = array();

			// First, refresh the invoices
			static::refresh();
			
			// Set up the parameters with start/ending dates
			$params = array();
			
			$includeEmpty = true;
			if(!empty($options["includeEmpty"])) {
				$includeEmpty = boolval($options["includeEmpty"]);
			}

			// Add the dates where applicable
			if(!empty($options["starts"])) {
				if(is_string($options["starts"])) {
					$options["starts"] = strtotime($options["starts"]);
				}
				array_push($params, date('Y-m-d', $options["starts"]));
			}
			if(!empty($options["ends"])) {
				if(is_string($options["ends"])) {
					$options["ends"] = strtotime($options["ends"]);
				}
				array_push($params, date('Y-m-d', $options["ends"]));
			}
			
			// Now set up grouping
			$grouping = "'—'";
			if(!empty($options["grouping"])) {
				
				// If we should group by level
				if($options["grouping"] == "level") {
					$grouping = "REPLACE(p.ID, CONCAT(p.planID, '_'), '')";
				}

				// If this is the plan
				else if($options["grouping"] == "tier") {
					$grouping = "t.name";
				}

				// If this is the plan
				else if($options["grouping"] == "unit") {
					$grouping = "p.unit";
				}

				// If this is the plan
				else if($options["grouping"] == "plan") {
					$grouping = "p.name";
				}
				
				// If this is a state
				else if($options["grouping"] == "state") {
					$grouping = "CONCAT(i.country, CASE WHEN i.state IS NULL THEN '' ELSE CONCAT(' - ', i.state) END)";
				}
				
				// If this is a country
				else if($options["grouping"] == "country") {
					$grouping = "i.country";
				}

				// If this is a country
				else if($options["grouping"] == "affiliate") {
					$grouping = "f.name";
				}
				
				// Otherwise
				else {
				
					// If we are set to auto, handle it by evaluating the difference between the start/end dates
					if($options["grouping"] == "timeframe") {
						$test = "day";
						
						// Calculate the start and end
						if(!empty($options["starts"])) {
							$starts = $options["starts"];
						} else {
							$starts = strtotime("2021-07-01");
						}
						
						if(!empty($options["ends"])) {
							$ends = $options["ends"];
						} else {
							$ends = time();
						}
						
						// Now determine the distance between
						$s = date_create(date("c", $starts));
						$e = date_create(date("c", $ends));
						$d = date_diff($s, $e)->days;
						
						// If it's longer than say 720 days
						if($d >= 1000) {
							$test = "year";
						}
						else if($d > 450) {
							$test = "quarter";
						}
						else if($d > 35) {
							$test = "month";
						}
					
					} else {
						$test = strtolower($options["grouping"]);
					}
					
					// Then handle based on the grouping
					if($test == "day") {
						$grouping = "DATE_FORMAT(i.paid, '%Y-%m-%d')";
					}
					else if($test== "month") {
						$grouping = "DATE_FORMAT(i.paid, '%Y-%m')";
					}
					else if($test == "quarter") {
						$grouping = "CONCAT(YEAR(i.paid), ' Q', QUARTER(i.paid))";
					}
					else if($test == "week") {
						$grouping = "DATE_FORMAT(i.paid, '%Y Week %V')";
					}
					else if($test == "year") {
						$grouping = "DATE_FORMAT(i.paid, '%Y')";
					}
				}
			}

			// Create the SQL statement
			$sql = "SELECT ". $grouping ." AS name, COUNT(i.ID) AS transactions, SUM(i.subtotal) AS subtotal, SUM(i.tax) AS tax, SUM(i.total) AS total ";
			$sql .= " FROM onsong_connect_invoice i ";
			$sql .= " INNER JOIN onsong_connect_organization o ON o.customerID = i.customerID ";
			$sql .= " INNER JOIN onsong_connect_account a ON o.ID = a.organizationID ";
			$sql .= " INNER JOIN onsong_connect_plan p ON p.ID = a.planID ";
			$sql .= " INNER JOIN onsong_connect_plan_tier t ON t.ID = p.tierID ";
			$sql .= " LEFT OUTER JOIN onsong_connect_affiliate f ON o.affiliateID = f.ID ";
			$sql .= " WHERE i.total > 0 ";
			if(!empty($options["starts"])) {
				$sql .= " AND i.paid >= ? ";
			}
			if(!empty($options["ends"])) {
				$sql .= " AND i.paid < ? ";
			}
			$sql .= " GROUP BY name ORDER BY name ";

			// Prepare and execute the statement
			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					if($includeEmpty || $row["transactions"] > 0) {
						array_push($a, $row);
					}
				}
			}

			// Return the list of processed items
			return $a;
		}
		
		public static function nexusReports($includeEmpty = false) {
			global $pdo;
			
			// Make a list
			$a = array();
			
			// First, refresh the invoices
			static::refresh();
			
			// Get a list of known timeframes
			$timeframes = static::reportTimeframes();
			
			// Next, get the states
			foreach(LocationState::list()->results() as $state) {
				
				// If we don't have a period timeframe, skip
				if(empty($state->nexusPeriod())) {
					continue;
				}

				// Otherwise, get the date range to process
				$reports = $timeframes[$state->nexusPeriod()];
				foreach($reports as $timeframe) {
				
					// Execute the aggregate for the state
					$params = array($state->ID(), date('Y-m-d', $timeframe["starts"]), date('Y-m-d', $timeframe["ends"]));
					$sql = "SELECT COUNT(ID) AS count, SUM(subtotal) AS subtotal, SUM(tax) AS tax, SUM(total) AS total FROM onsong_connect_invoice WHERE state = ? AND paid >= ? AND paid < ? LIMIT 1";
					$statement = $pdo->prepare($sql);
					$statement->execute($params);
					if($statement) {
						while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
							if($includeEmpty || $row["count"] > 0) {
								$row["name"] = $timeframe["name"];
								$row["state"] = $state;
								$row["country"] = $state->country();
								array_push($a, $row);
							}
						}
					}
				}
			}
			
			// Next, get the countries
			foreach(LocationCountry::list()->results() as $country) {
				
				// If we don't have a period timeframe, skip
				if(empty($country->nexusPeriod())) {
					continue;
				}

				// Otherwise, get the date range to process
				$reports = $timeframes[$country->nexusPeriod()];
				foreach($reports as $timeframe) {
				
					// Execute the aggregate for the country
					$params = array($country->ID(), date('Y-m-d', $timeframe["starts"]), date('Y-m-d', $timeframe["ends"]));
					$sql = "SELECT COUNT(ID) AS count, SUM(subtotal) AS subtotal, SUM(tax) AS tax, SUM(total) AS total FROM onsong_connect_invoice WHERE country = ? AND paid >= ? AND paid < ? LIMIT 1";
					$statement = $pdo->prepare($sql);
					$statement->execute($params);
					if($statement) {
						while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
							if($includeEmpty || $row["count"] > 0) {
								$row["name"] = $timeframe["name"];
								$row["state"] = null;
								$row["country"] = $country;
								array_push($a, $row);
							}
						}
					}
				}
			}

			// Return the list of processed items
			return $a;
		}

		public static function reportTimeframes() {
			$a = array();
			
			// Add the all-time timeframe
			$a["all-time"] = array(array("name"=>"All Time", "starts"=>strtotime("January 1, 1970"), "ends"=>strtotime("now")));
			
			// Add the annual timeframe
			$a["annual"] = array(array("name"=>"Previous Year", "starts"=>strtotime('first day of january last year'), "ends"=>strtotime('first day of january this year')), array("name"=>"Current Year", "starts"=>strtotime('first day of january this year'), "ends"=>strtotime('first day of january next year')));
			
			// Add the annual timeframe
			$a["12-month"] = array(array("name"=>"Last 12 Months", "starts"=>strtotime('first day of next month -1 year'), "ends"=>strtotime('first day of next month')));

			// Add the rolling quarters
			$a["q2-q1"] = array(array("name"=>"Q2 through Q1", "starts"=>strtotime('April 1, '. date('Y')), "ends"=>strtotime('April 1, '. date('Y', strtotime("+1 year")))));
			$a["q3-q2"] = array(array("name"=>"Q3 through Q2", "starts"=>strtotime('July 1, '. date('Y')), "ends"=>strtotime('July 1, '. date('Y', strtotime("+1 year")))));
			$a["q4-q3"] = array(array("name"=>"Q4 through Q3", "starts"=>strtotime('October 1, '. date('Y')), "ends"=>strtotime('October 1, '. date('Y', strtotime("+1 year")))));
			
			// Return the list
			return $a;
		}
	}
?>