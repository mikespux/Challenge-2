<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class CampaignList extends DataObjectList {

		public function __construct($attributes = null) {
			parent::__construct("Campaign", $attributes, true);
		}
	}
?>