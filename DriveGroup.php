<?php
// Require the framework
require_once(__DIR__ . "/autoload.php");

class DriveGroup extends DataObject {

	public static function tableName() {
		return "onsong_connect_account";
	}

	public static function className() {
		return "DriveGroup";
	}
	
	public function permissions() {
		$o = "";
		$role = Role::find($this->ID(), Role::current()->userID());
		if($role->has(Role::PERMISSIONS_READ)) {
			$o .= "r";
		}
		if($role->has(Role::PERMISSIONS_WRITE)) {
			$o .= "w";
		}
		if($role->has(Role::PERMISSIONS_DELETE)) {
			$o .= "d";
		}
		return $o;
	}
	
	public function canRead() {
		return (strpos($this->permissions() ?? "", "r") !== false);
	}
	
	public function canWrite() {
		return (strpos($this->permissions() ?? "", "w") !== false);
	}
	
	public function canDelete() {
		return (strpos($this->permissions() ?? "", "d") !== false);
	}

	public function path() {
		return ($this->users() == 1) ? '/~/' : '/'. $this->ID() .'/';
	}
	
	public function URI() {
		return $this->path();
	}
	
	public function folders() {
		global $pdo;

		// Set up the parameters
		$params = array($this->ID());
		
		// Get the list in path order
		$sql = "SELECT * FROM onsong_connect_drive_item WHERE mimeType IS NULL AND deleted IS NULL AND accountID = ? ";
		if($this->type() == 'group') {
			$sql .= " AND userID IS NULL ";
		} else {
			$sql .= " AND userID = ? ";
			array_push($params, User::current()->ID());
		}
		$sql .= " ORDER BY path";

		$statement = $pdo->prepare($sql);
		$statement->execute($params);
		if($statement) {
			$root = null;
			$a = array();
			while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
				$item = new DriveItem($row);
				if(empty($root)) {
					$root = $item;
				}
				$a[$item->ID()] = $item;
				if(!empty($item->parentID())) {
					if(isset($a[$item->parentID()])) {
						$parent = $a[$item->parentID()];
						$parent->addSubfolder($item);
					}
				}
			}
			if(!is_null($root)) {
				return $root->subfolders();
			} else {
				return null;
			}
		}
		return null;
	}
	
	public static function personal() {
		global $pdo;
		
		$params = array();
		$sql  = "";
		$sql .= "     SELECT ";
		$sql .= "       a.ID, COALESCE(NULLIF(d.name, ''), ?) AS name, 'user' AS type, COALESCE(d.name, '') AS sort, NULL AS mimeType, 0 AS size, COALESCE(d.color, '#00c6b3') AS color, a.avatar, COALESCE(a.users, p.users) AS users, COALESCE(a.paidStorage, 0) + COALESCE(a.additionalStorage, 0) + COALESCE(p.storage, t.storage, 0) AS storage, a.created, a.modified ";
		$params[] = l10n('Personal Files');

		$sql .= "     FROM onsong_connect_role r ";
		$sql .= "       INNER JOIN onsong_connect_account a ON r.accountID = a.ID ";
		$sql .= "       LEFT OUTER JOIN onsong_connect_plan p ON p.ID = a.planID ";
		$sql .= "       LEFT OUTER JOIN onsong_connect_plan_tier t ON p.tierID = t.ID ";
		$sql .= "       LEFT OUTER JOIN onsong_connect_drive_item d ON d.accountID = a.ID AND d.userID = r.userID AND d.path = '/' ";
		$sql .= "     WHERE ";
		$sql .= "       r.userID = ? AND r.deleted IS NULL ";
		$params[] = Role::current()->userID();

		$sql .= "     HAVING ";
		$sql .= "       (users IS NULL OR users = 1) AND storage > 0 ";
		$sql .= "     ORDER BY ";
		$sql .= "       t.priority DESC, p.price DESC, COALESCE(a.expires, DATE_ADD(NOW(), INTERVAL 100 YEAR)) DESC ";
		$sql .= "     LIMIT 1 ";
		
		$statement = $pdo->prepare($sql);
		$statement->execute($params);
		
		if($statement && $row = $statement->fetch(PDO::FETCH_ASSOC)) {
			return new DriveGroup($row);
		}
		return null;
	}
	
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return self::jsonSerializeIncluding(null);
	}
			
	public function jsonSerializeIncluding($include = null) {
		$o = parent::jsonSerializeIncluding($include = null);
		$o['type'] = $this->type();
		$o['color'] = $this->color();
		$o['path'] = $this->path();
		$o['has'] = $this->permissions();
		if(!empty($include) && in_array('subfolders', $include)) {
			$o['subfolders'] = $this->folders();
		}
		return $o;
	}
}