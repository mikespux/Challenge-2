<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class DriveItemList extends DataObjectList {

		public function __construct($attributes = null) {
			parent::__construct("DriveItem", $attributes, true);
		}
	}
?>