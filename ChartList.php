<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	
	class ChartList extends SongList {
	
		public function __construct($attributes = null) {
			if(is_null($attributes)) {
				$attributes = array();
			}
			
			if(check_app_roles("scribe", "read")) {
				$attributes['status'] = array('scribed', 'published');
			} else {
				$attributes['status'] = 'published';
			}
			
			if(isset($attributes['sort']) == false) {
				$attributes['sort'] = 'title';
			}
			parent::__construct($attributes, true);
		}
		
		public static function queryList() {
			return array("title", "keywords", "lyrics");
		}
		
		public function ignorePermissions($value = "____") {
			return true;
		}
	}
?>