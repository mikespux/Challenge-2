<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class PivotalIntegrationBase {
		private $integration;

		public function __construct($integration) {
			$this->integration = $integration;
		}

		public function integration() {
			return $this->integration;
		}

		public function search($query = null) {
			return null;
		}
	}
?>