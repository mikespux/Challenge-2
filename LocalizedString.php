<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class LocalizedString extends DataObject {

		public static function tableName() {
			return "onsong_localized_string";
		}

		public static function className() {
			return "LocalizedString";
		}
		
		public static function find($original, $lang = "en") {
			global $pdo;
			$params = array($original, $lang);
			$sql = "SELECT * FROM onsong_localized_string WHERE original = ? AND lang = ?";
			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			if($statement) {
				$row = $statement->fetch(PDO::FETCH_ASSOC);
				return new LocalizedString($row);
			}
			return null;
		}
		
		public static function defaultLanguage() {
			return 'en';
		}

		public static function languages() {
			global $pdo;
			$a = array();
			$sql = "SELECT DISTINCT lang FROM onsong_localized_string ORDER BY CASE WHEN lang = ? THEN '' ELSE lang END";
			$statement = $pdo->prepare($sql);
			$statement->execute(array(static::defaultLanguage()));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					array_push($a, $row['lang']);
				}
			}
			return $a;
		}
		
		public static function translations($original) {
			global $pdo;
			$a = array();
			$params = array($original, static::defaultLanguage());
			$sql = "SELECT * FROM onsong_localized_string WHERE original = ? ORDER BY CASE WHEN lang = ? THEN '' ELSE lang END";
			$statement = $pdo->prepare($sql);
			$statement->execute($params);

			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					$string = new LocalizedString($row);
					$a[$string->lang()] = $string;
				}
			}
			return $a;
		}

		public static function originals($sort = null, $descending = null) {
			global $pdo;
			if(empty($sort)) {
				$sort = "original";
			}
			if(is_null($descending)) {
				$descending = ($sort == "count");
			}
			$orderBy = $sort ." ". (($descending) ? "DESC" : "ASC");
			$a = array();
			$sql = "SELECT original AS text, COUNT(original) AS count FROM onsong_localized_string GROUP BY original ORDER BY ". $orderBy;
			$statement = $pdo->prepare($sql);
			$statement->execute();
			if($statement) {
				while($o = $statement->fetch(PDO::FETCH_OBJ)) {
					array_push($a, $o);
				}
			}
			return $a;
		}
		
		// Returns a list of translated strings for the specified language
		public static function translatedStrings($lang) {
			global $pdo;
			$a = array();
			$params = array($lang);
			$sql = "SELECT * FROM onsong_localized_string WHERE lang = ? ORDER BY original";
			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					$string = new LocalizedString($row);
					$a[$string->original()] = $string;
				}
			}
			return $a;
		}

		// Returns a list of untranslated string for the specified language
		public static function untranslatedStrings($lang, $filled = false) {
			global $pdo;
			$a = array();
			$params = array(static::defaultLanguage(), $lang);
			$sql = "SELECT * FROM onsong_localized_string WHERE lang = ? AND original NOT IN ( SELECT original FROM onsong_localized_string WHERE lang = ? ) ORDER BY original";
			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					$string = new LocalizedString($row);
					$string->lang($lang);
					if($filled == false) {
						$string->translated("");
					}
					$a[$string->original()] = $string;
				}
			}
			return $a;
		}
	}
?>