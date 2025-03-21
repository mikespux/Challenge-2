<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class FeaturedArtistLinkType extends DataObject {

		public static function tableName() {
			return "onsong_featured_artist_link_type";
		}

		public static function className() {
			return "FeaturedArtistLinkType";
		}
	}
?>