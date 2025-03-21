<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class DriveItem extends S3DataObject {
		protected $additional = null;
		private $originalPath = null;
		private $subfolders = null;

		public static function tableName() {
			return "onsong_connect_drive_item";
		}

		public static function className() {
			return "DriveItem";
		}
		
		public static function classNamesForProperty($name) {
			if($name == "parent") {
				return "DriveItem";
			} else {
				return parent::classNamesForProperty($name);
			}
		}
		
		public static function searchAll($query, $types = null) {
			global $pdo;
			
			$params = array($query, User::current()->ID(), User::current()->ID(), '%'. $query .'%');
			$sql = "SELECT i.*, POSITION(LOWER(?) in LOWER(i.name)) AS sort FROM onsong_connect_drive_item i WHERE ( i.accountID IN( SELECT r.accountID FROM onsong_connect_role r WHERE r.userID = ? ) OR i.userID = ? ) AND i.name LIKE ? ";
			if(!empty($types)) {
				$sql .= " AND ( ";
				for($i=0;$i<$types;$i++) {
					if($i > 0) {
						$sql .= " OR ";
					}
					if($types[$i] == "application/folder") {
						$sql .= " i.mimeType IS NULL ";
					} else {
						$sql .= " i.mimeType = ? ";
						$params[] = $types[$i];
					}
				}
				$sql .= " ) ";
			} else {
				$sql .= " AND i.mimeType IS NOT NULL";
			}
			$sql .= " ORDER BY sort ";

			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			if($statement) {
				$a = array();
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					$item = new DriveItem($row);
					if(!empty($item)) {
						$a[] = $item;
					}
				}
				return $a;
			}
			return null;
		}

		public function search($query, $types = null) {
			global $pdo;
			$params = array($query, $this->accountID(), $this->userID(), $this->path() .'%', '%'. $query .'%');
			$sql = "SELECT *, POSITION(LOWER(?) in LOWER(name)) AS sort FROM onsong_connect_drive_item WHERE accountID = ? AND userID = ? AND path LIKE ? AND name LIKE ? ";
			if(!empty($types)) {
				$sql .= " AND ( ";
				for($i=0;$i<$types;$i++) {
					if($i > 0) {
						$sql .= " OR ";
					}
					if($types[$i] == "application/folder") {
						$sql .= " mimeType IS NULL ";
					} else {
						$sql .= " mimeType = ? ";
						$params[] = $types[$i];
					}
				}
				$sql .= " ) ";
			} else {
				$sql .= " AND mimeType IS NOT NULL";
			}
			$sql .= " ORDER BY sort ";
			
			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			if($statement) {
				$a = array();
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					$item = new DriveItem($row);
					if(!empty($item)) {
						$a[] = $item;
					}
				}
				return $a;
			}
			return null;
		}
		
		public function folders() {
			global $pdo;
		
			// Not allowed on files
			if($this->type() == "file") {
				return null;
			}
		
			// Set up the parameters
			$params = array($this->accountID());
		
			// Get the list in path order
			$sql = "SELECT * FROM onsong_connect_drive_item WHERE mimeType IS NULL AND deleted IS NULL AND accountID = ? ";
			if(empty($this->userID())) {
				$sql .= " AND userID IS NULL ";
			} else {
				$sql .= " AND userID = ? ";
				array_push($params, $this->userID());
			}
			$sql .= " AND path LIKE ? AND path LIKE '%/' ORDER BY path";
			array_push($params, $this->path() . '%');
			
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
				return $root;
			}
			return null;
		}
		
		public function addSubfolder($subfolder) {
			if($this->subfolders == null) {
				$this->subfolders = array();
			}
			$this->subfolders[] = $subfolder;
		}
		
		public function subfolders() {
			return $this->subfolders;
		}

		public static function find($path, $group = null, $includeDeleted = false) {
			global $pdo;
			
			// If the group isn't what it should be
			if(!empty($group) && $group != '~' && DataObject::isUUID($group) == false) {
				if(endsWith($path, "/") == false) {
					$path .= "/";
				}
				$path .= $group . '/';
				$group = null;
			}

			// The user ID
			$userID = User::current()->ID();

			// The additional statement
			$additional = null;
			if(empty($group) || $group == '~') {
				$additional = "userID = '". $userID ."' ";
			} else {
				$additional = "accountID = '". $group ."' AND userID IS NULL AND accountID IN ( SELECT accountID FROM onsong_connect_role WHERE userID = '". $userID ."' AND permissions > 0 ) ";
			}

			// Create the SQL statement
			$params = array($path);
			$column = (strpos($path, "/") === false) ? "ID" : "path";
			$sql = "SELECT * FROM onsong_connect_drive_item WHERE ". $column ." = ? AND ". $additional;
			if($includeDeleted == false) {
				$sql .= " AND deleted IS NULL ";
			}

			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			if($statement && $row = $statement->fetch(PDO::FETCH_ASSOC)) {
				$item = new DriveItem($row);
				$item->additional = $additional;
				return $item;
			}
			return null;
		}

		public function fileKey($name = null) {
			if(empty($name)) {
				$name = $this->name();
			}
			if($this->type() == "file") {
				return $this->accountID() .'/'. static::className() .'/'. (empty($this->userID()) ? 'Shared' : $this->userID()) .'/'. $this->ID() .'/'. $name;
			} else {
				return null;
			}
		}
		
		public function type() {
			if(empty($this->mimeType()) || endsWith($this->path(), '/')) {
				if($this->path() == '/') {
					if(empty($this->userID())) {
						return "group";
					} else {
						return "user";
					}
				} else {
					return "folder";
				}
			} else {
				return "file";
			}
		}
		
		public function isUploading() {
			return (!empty($this->started()) && empty($this->size()));
		}
		
		public function downloadFilename() {
			return $this->name();
		}

		public function uploadURL() {
			$url = parent::uploadURL();
			$this->started(time());
			$this->save();
			return $url;
		}
		
		public function children($attributes=null) {
			if($this->path() == "/" && empty($this->additional)) {
				return new DriveGroupList($attributes);
			} else {
				$list = new DriveItemList($attributes);
				if(empty($list->other())) {
					$list->other(array());
				}
				$list->other("parentID", $this->ID());
				$list->additional($this->additional);
				$list->ignorePermissions(true);
			}
			return $list;
		}
		
		public function calculatedSize() {
			global $pdo;
			if(empty($this->mimeType())) {
				$params = array($this->accountID());
				$sql = "SELECT SUM(size) FROM onsong_connect_drive_item WHERE accountID = ? ";
				if(!empty($this->userID())) {
					$sql .= " AND userID = ? ";
					$params[] = $this->userID();
				} else {
					$sql .= " AND userID IS NULL ";
				}
				$sql .= " AND mimeType IS NOT NULL AND deleted IS NULL AND path LIKE ? ";
				$params[] = $this->path() . '%';
				$statement = $pdo->prepare($sql);
				$statement->execute($params);
				if($statement && $row = $statement->fetch(PDO::FETCH_NUM)) {
					return $row[0];
				}
				return null;
			} else {
				return $this->size();
			}
		}

		public function size($value = false) {
			if($value == false) {
				if(empty($this->mimeType())) {
					return null;
				} else {
					$size = $this->value("size");
					if(is_null($size)) {
						$size = parent::size();
						if(is_null($size)) {
							if(!empty($this->started()) && $this->started() < strtotime("-1 hour")) {
								$this->deleted(time());
							}
						} else {
							$this->size($size);
						}
						if($this->isNew() == false) {
							$this->save();
						}
					}
					return $size;
				}
			} else {
				
				// Set it to null and then recalculate the size
				parent::size($value);
			}
		}
		
		public function parent($value = false) {
			if($value == false) {
				return parent::parent();
			} else {
				parent::parent($value);
				if(!empty($value)) {
					$this->value("parentID", $value->ID());
					$this->value("accountID", $value->accountID());
					$this->value("userID", $value->userID());
				}
			}
		}

		public function parentID($value = false) {
			if($value === false) {
				return parent::parentID();
			} else {
				if(empty($value)) {
					$value = null;
				}
				parent::parentID($value);
				$path = "/";
				if(!empty($value) && !empty($this->parent())) {
					$path = $this->parent()->path();
				}
				$path .= $this->name();
				$this->path($path);
			}
		}

		public function name($value = false) {
			if($value == false) {
				$name = parent::name();
				if(is_null($name) || empty(trim($name))) {
					if($this->path() == "/") {
						if(empty($this->userID())) {
							if(empty($this->accountID())) {
								$name = 'OnSong® Cloud';
							} else {
								$name = $this->account()->name();
							}
						} else {
							$name = l10n("Personal Files");
						}
					} else {
						$name = l10n("Untitled");
					}
				}
				return $name;
			} else {

				// Set the name
				parent::name($value);
					
				// If we have no parent, we don't have to rewrite the path
				if(empty($this->parent())) {
					return;
				}

				// Reset the path
				$path = $this->parent()->path();
				$path .= $value;
				if(empty($this->mimeType())) {
					$path .= "/";
				}

				// Set the original path
				$originalPath = $this->value("path");
				
				// If it's different
				if($originalPath != $path) {
				
					// Set the new path
					$this->value("path", $path);
					
					// Then the original so that we save the changes
					$this->originalPath = $originalPath;
				}
			}
		}
		
		public function permissions() {
			if(!empty($this->userID()) && $this->userID() == Role::current()->userID()) {
				return "rwd";
			}
			else if(!empty($this->accountID())) {
				$o = "";
				$role = Role::find($this->accountID(), Role::current()->userID());
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
			else {
				return null;
			}
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
		
		public function share($value = false) {
			if($value === false) {
				if(empty($this->userID())) {
					return $this->accountID();
				} else {
					return '~';
				}
			} else {
				$group = ($value == "~") ? null : $value;
				$parent = DriveItem::find('/', $group);
				if(empty($parent)) {
					$parent = new DriveItem();
					$parent->path('/');
					if($value == '~') {
						$parent->account(DriveGroup::personal());
						$parent->user(User::current());
					} else {
						$parent->accountID($value);
					}
					$parent->save();
				}
				if(!empty($parent)) {
					$this->parent($parent);
				}
			}
		}

		public function URI() {
			$uri = $this->path();
			if(empty($this->userID())) {
				$var = '/'. $this->accountID() . $uri;
				$uri = $var;
			} else {
				$uri = '/~' . $uri;
			}
			return $uri;
		}

		public function path($value = false) {
			if($value === false) {
				$path = parent::path();
				if(empty($path)) {
					if(empty($this->parentID())) {
						$path = "/" . $this->name();
					} else {
						$path = $this->parent()->path() . $this->name();
					}
				}
				return $path;
			}
			
			else {
				
				// Get the first portion of the path
				$share = null;
				$parts = explode("/", $value);
				if(count($parts) > 1) {
					$share = $parts[1];
				}
				
				// Now, if this is the user group
				if($share == "~") {
					$this->userID(User::current()->ID());
					$value = substr($value, 2);
				} else if(strtolower($share) == strtolower($this->accountID())) {
					$this->userID(null);
					$value = substr($value, strlen($share) + 1);
				}
				if(startsWith($value, "/") == false) {
					$value = "/" . $value;
				}
				
				// If we don't have a share, figure it out
				if(empty($share)) {
					if(empty($this->userID())) {
						$share = '~';
					} else {
						$share = $this->accountID();
					}
				}

				// Set the original path
				$originalPath = $this->value("path");
				
				// If it's different
				if($originalPath != $value) {
					
					// Set the original path so that we can update it
					$this->originalPath = $originalPath;

					// Set the value
					parent::path($value);
		
					// Update the child paths
					$this->updateChildPaths($value);
	
					// Split into the parts
					$i = pathinfo($value);
					$parentPath = $i['dirname'];
					if(endsWith($parentPath, '/') == false) {
						$parentPath .= '/';
					}

					// Set the parent
					if($value != "/") {
						$parent = DriveItem::find($parentPath, $share);
						$this->parent($parent);
					}
	
					$this->value("name", $i['basename']);
					
					// Get the file name if we have one	
					$filename = basename($value);
	
					// If this is a file, set the mime type
					if(!empty($filename)) {
						$mimeTypes = DriveMimeType::find($filename);
						if(count($mimeTypes) > 0) {
							$this->mimeType($mimeTypes[0]->mimeType());
						}
					} else {
						$this->mimeType(null);
					}
				}
			}
		}
		
		public function save(&$errors = null, $exceptions = null) {
			global $pdo;
			
			$wasNew = $this->isNew();
	
			// If it was saved, now update the children
			if(!$this->isNew() && !empty($this->originalPath)) {
				
				// See if the name has changed
				$pi = pathinfo($this->originalPath);
				$oldName = $pi['basename'];
				if($oldName != $this->name()) {

					// Rename the file in S3
					$s3Client = S3::client(true);
						
					try {
						if($this->type() == "file") {
							$s3Client->copyObject(array(
								'Bucket'=>S3::storageBucket(),
								'CopySource'=>S3::storageBucket() .'/'. $this->fileKey($oldName),
								'Key'=>$this->fileKey(),
								'MetadataDirective'=>'REPLACE'
							));
							$s3Client->deleteObject([
								'Bucket'=>S3::storageBucket(),
								'Key'=>$this->fileKey($oldName)
							]);
						}
					} catch(S3Exception $ex) {
						if(!is_null($errors)) {
							$errors[] = $ex->getMessage();
							return false;
						}
					}
				}

				// Update the children paths
				if($this->originalPath != '/' && $this->originalPath != $this->value("path")) {
					$params = array($this->value("path"), $this->originalPath, $this->originalPath, $this->originalPath . '%');
					$sql = "UPDATE onsong_connect_drive_item SET path = CONCAT(?, SUBSTRING(path, LENGTH(?) + 1, LENGTH(path) - LENGTH(?) + 1)) WHERE path LIKE ?";
					$statement = $pdo->prepare($sql);
					$statement->execute($params);
				}

				// Set the original path back
				$this->originalPath = null;
			}

			// Save the changes
			$success = parent::save($errors, $exceptions);

			// If this was new,
			if($wasNew) {

				// The additional statement
				if(!empty($this->userID())) {
					$this->additional = "userID = '". $this->userID() ."' ";
				} else {
					$this->additional = "accountID = '". $this->accountID() ."' AND userID IS NULL AND accountID IN ( SELECT accountID FROM onsong_connect_role WHERE userID = '". User::current()->ID() ."' AND permissions > 0 ) ";
				}
			}

			// If that worked
			if($success) {
				$this->updateAncestors(['modified'=>date('Y-m-d H:i:s')]);
			}
			return $success;
		}
		
		public function delete($permanently = false, $ignorePermissions = false) {
			
			// If this is a folder
			if($this->type() == "folder") {
				
				// Then go through all the children first and delete those
				foreach($this->children()->results() as $item) {
					$item->delete($permanently, $ignorePermissions);
				}
			}

			// Then delete this
			$success = parent::delete($permanently, $ignorePermissions);
			if($success) {
				$this->updateAncestors(['modified'=>date('Y-m-d H:i:s')]);
			}
			return $success;
		}
		
		public function updateAncestors($changes) {
			global $pdo;
			
			// If we have no changes
			if(empty($changes)) {
				return false;
			}
			
			// Then update the modified date of the ancestors
			$parts = explode("/", $this->path());
			$last = array_pop($parts);
			if(empty($last)) {
				$last = array_pop($parts);
			}
			
			// Set up the params
			$params = array();
			
			// Start generating the update statement
			$sql = "UPDATE onsong_connect_drive_item SET ";
			foreach($changes as $column=>$value) {
				$sql .= $column . " = ? ";
				array_push($params, $value);
			}
			$sql .= " WHERE accountID = ? AND userID ";
			array_push($params, $this->accountID());
			
			// Handle the user ID
			if(empty($this->userID())) {
				$sql .= "IS NULL";
			} else {
				$sql .= "= ?";
				array_push($params, $this->userID());
			}
			
			// Then apply the path equality
			if(count($parts) > 0) {
				$sql .= " AND ( ";
				$path = "";
				foreach($parts as $part) {
					if(!empty($path)) {
						$sql .= " OR";
					}
					$path .= $part . "/";
					$sql .= " path = ?";
					array_push($params, $path);
				}
				$sql .= " ) ";
			}
			
			// Run the statement
			$statement = $pdo->prepare($sql);
			return $statement->execute($params);
		}
		
		public static function emptyTrash($forUser = null) {
			
			// Get the list of deleted items
			$list = static::list();
			if(!empty($forUser)) {
				$list->other("userID", $forUser);
			}
			$list->status(DataObjectList::STATUS_DELETED);
				
			$success = false;
			foreach($list->results() as $item) {
				if($item->delete(true)) {
					$success = true;
				}
			}
			return $success;
		}

		#[\ReturnTypeWillChange]
		public function jsonSerialize() {
			return self::jsonSerializeIncluding(null);
		}
				
		public function jsonSerializeIncluding($include = null) {
			$o = parent::jsonSerializeIncluding($include = null);
			if($this->path() == '/') {
				if(empty($this->userID())) {
					if(isset($o['name']) == false || is_null($o['name']) || empty(trim($o['name']))) {
						$account = Account::retrieve($this->accountID(), true, true);
						if(!empty($account)) {
							$o['name'] = $account->name();
						}
					}
					if(isset($o['color']) == false || empty($o['color'])) {
						$o['color'] = '808080';
					}
				} else {
					if(isset($o['name']) == false || is_null($o['name']) || empty(trim($o['name']))) {
						$o['name'] = l10n('Personal Files');
					}
					if(isset($o['color']) == false || empty($o['color'])) {
						$o['color'] = '00c6b3';
					}
				}
			}
			$o['type'] = $this->type();
			$o['path'] = $this->URI();
			$o['has'] = $this->permissions();
			if(empty($this->userID())) {
				$o['share'] = $this->accountID();
			} else if($this->userID() == User::current()->ID()) {
				unset($o['userID']);
			}
			if(!is_null($this->subfolders)) {
				$o['subfolders'] = $this->subfolders;
			}
			return $o;
		}
	}
?>