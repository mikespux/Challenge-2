<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class CancellationReason extends DataObject {

		public static function tableName() {
			return "onsong_connect_cancellation_reason";
		}

		public static function className() {
			return "CancellationReason";
		}
		
		public static function list($attributes = null) {
			$a = array_merge(array("sort"=>"orderIndex"), (empty($attributes)) ? array() : $attributes);
			return new DataObjectList(static::className(), $a);
		}
	}
?>