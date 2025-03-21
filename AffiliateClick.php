<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class AffiliateClick extends DataObject {

		public static function tableName() {
			return "onsong_connect_affiliate_click";
		}

		public static function className() {
			return "AffiliateClick";
		}
		
		public function geolocate() {
			
			// Go to https://geo.ipify.org/pricing for pricing
			$results = json_call("https://geo.ipify.org/api/v2/country,city?apiKey=at_IKCtSz3tFzmB4cUco0pZA0XBWUTnZ&ipAddress=". $this->ipAddress());

			// If we have results, then set those values
			if(isset($results->location)) {
				$l = $results->location;

				// Now set the other values
				if(isset($l->country)) {
					$this->country($l->country);
				}
				if(isset($l->region)) {
					$this->region($l->region);
				}
				if(isset($l->city)) {
					$this->city($l->city);
				}
				if(isset($l->lat)) {
					$this->latitude($l->lat);
				}
				if(isset($l->lng)) {
					$this->longitude($l->lng);
				}
			}
		}
		
		public static function report($options = null) {
			global $pdo;
			
			// If we have no options, make a blank array
			if($options == null) { $options = array(); }

			// Make a list
			$a = array();
			
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
					$grouping = "DATE_FORMAT(c.created, '%Y-%m-%d')";
				}
				else if($test== "month") {
					$grouping = "DATE_FORMAT(c.created, '%Y-%m')";
				}
				else if($test == "quarter") {
					$grouping = "CONCAT(YEAR(c.created), ' Q', QUARTER(c.created))";
				}
				else if($test == "week") {
					$grouping = "DATE_FORMAT(c.created, '%Y Week %V')";
				}
				else if($test == "year") {
					$grouping = "DATE_FORMAT(c.created, '%Y')";
				}
			}

			// Create the SQL statement
			$sql = "SELECT ";
			if(!empty($grouping)) {
				$sql .= $grouping ." AS grouping,";
			} else {
				$sql .= "NULL AS grouping,";
			}
			$sql .= " a.ID AS affiliateID, a.name AS affiliateName, COUNT(c.ID) AS clicks FROM onsong_connect_affiliate_click c INNER JOIN onsong_connect_affiliate a ON c.affiliateID = a.ID WHERE 1 = 1 ";
			if(!empty($options["starts"])) {
				$sql .= " AND c.created >= ? ";
			}
			if(!empty($options["ends"])) {
				$sql .= " AND c.created < ? ";
			}
			if(!empty($options["affiliates"])) {
				$sql .= " AND c.affiliateID IN('". implode("', '", $options["affiliates"]) ."') ";
			}
			$sql .= " GROUP BY grouping, a.ID, a.name ORDER BY grouping, a.name ";

			// Prepare and execute the statement
			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					if($includeEmpty || $row["clicks"] > 0) {
						array_push($a, $row);
					}
				}
			}

			// Return the list of processed items
			return $a;
		}
	}
?>