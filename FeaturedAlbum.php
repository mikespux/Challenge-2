<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class FeaturedAlbum extends DataObject {

		public static function tableName() {
			return "onsong_featured_album";
		}

		public static function className() {
			return "FeaturedAlbum";
		}

		public function iTunesURL() {
			$term = $this->artist()->alias();
			if(empty($term)) {
				$term = $this->artist()->name();
			}
			$result = json_call("https://itunes.apple.com/search?term=". urlencode($term) ."&entity=album&limit=200");
			foreach($result->results as $item) {
				if($item->artistName == $term && stripos($item->collectionName, $this->name()) === 0) {
					return $item->collectionViewUrl . '&at=10l4Hw&mt=1&app=music';
				}
			}
			return null;
		}

		public function songs() {

			// Find the path to the text files to load
			return FeaturedSong::songs($_SERVER['DOCUMENT_ROOT'] . '/artists/'. $this->artistID() .'/'. $this->ID() .'/');
		}

		public function song($songID) {
			$path = $_SERVER['DOCUMENT_ROOT'] . '/artists/'. $this->artistID() .'/'. $this->ID() .'/'. $songID .'.txt';
			if(file_exists($path)) {
				return new FeaturedSong($path);
			}
			return null;
		}
	}
?>