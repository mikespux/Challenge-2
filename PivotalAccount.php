<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	
	class PivotalAccount extends Account {
	
		public static function tableName() {
			return "onsong_connect_account";
		}
	
		public static function className() {
			return "PivotalAccount";
		}
		
		public function jsonSerialize() {
			$status = "active";
			if($this->isDeleted()) {
				$status = "deleted";
			}
			else if($this->isExpired()) {
				$status = "suspended";
				// delinquent
			}
			else if($this->isFree()) {
				$status = "limited";
			}
			$plan = (!empty($this->plan())) ? $this->plan()->name() : null;
			// days_left
			// project_ids
			$d = [
				"kind"=>"account_summary",
				"id"=>$this->ID(),
				"name"=>$this->name(),
				"status"=>$status,
				"plan"=>$plan,
				"over_the_limit"=>false,
				"created_at"=>PivotalObject::serializeDate($this->created()),
				"updated_at"=>PivotalObject::serializeDate($this->modified())
			];
			return $d;
		}
	}
?>