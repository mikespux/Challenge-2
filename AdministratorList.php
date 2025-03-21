<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class AdministratorList extends DataObjectList {
		private $since = null;

		public function __construct($attributes = null) {
			parent::__construct("Administrator", $attributes, true);
		}
	}
?>