<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	
	class PivotalIntegration extends PivotalObject {
	
		public static function tableName() {
			return "pivotal_integration";
		}
	
		public static function className() {
			return "PivotalIntegration";
		}
		
		public static function lookup($type) {
			static $lookup = array();
			if(isset($lookup[$type]) == false) {
				$i = new PivotalIntegration();
				$i->type($type);
				$i->name(ucwords($type));
				if($i->save()) {
					$lookup[$type] = $i;
				}
			}
			return $lookup[$type];
		}
	}
?>