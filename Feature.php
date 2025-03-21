<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Feature extends DataObject {
		private static $lookup = null;

		public static function tableName() {
			return "onsong_connect_feature";
		}

		public static function className() {
			return "Feature";
		}
		
		public function tiers() {
			$a = Feature::lookup();
			if(isset($a[$this->ID()])) {
				return $a[$this->ID()];
			}
			return null;
		}

		public static function lookup() {
			global $pdo;
			if(is_null(static::$lookup)) {
				static::$lookup = array();
				$sql = "SELECT featureID, tierID FROM onsong_connect_feature_tier ";
				$statement = $pdo->prepare($sql);
				$statement->execute();
				if($statement) {
					while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
						$value = array();
						if(isset(static::$lookup[$row['featureID']])) {
							$value = static::$lookup[$row['featureID']];
							if(is_array($value) == false) {
								$value = array($value);
							}
						}
						array_push($value, $row['tierID']);
						static::$lookup[$row['featureID']] = $value;
					}
				}
			}
			return static::$lookup;
		}
	}
?>