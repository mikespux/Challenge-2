<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class DriveGroupList extends DataObjectList {
		private $lookup;

		public function __construct($attributes = null) {
			parent::__construct("DriveGroup", $attributes, true);
		}

		public function getStatement($forCount = false) {
			global $pdo;
			$this->lookup = array();
			$this->process(function($o){
				if(in_array($o->ID(), $this->lookup)) {
					return true;
				}
				$this->lookup[] = $o->ID();
				return false;
			});

			$sql = "";
			$sql = "SELECT ";
			if($forCount) {
				$sql .= "COUNT(*)";
			} else {
				$sql .= "DISTINCT *";
			}
			$sql .= " FROM ( ";
			
			$params = array();
			$accountID = $this->other('accountID');

			// Personal Files
			$sql .= "   SELECT * FROM ( ";
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
				
			if($accountID) {
				$sql .= "       AND r.accountID = ? ";
				$params[] = $accountID;
			} else {
				$sql .= "     HAVING ";
				$sql .= "       (users IS NULL OR users = 1) AND storage > 0 ";
				$sql .= "     ORDER BY ";
				$sql .= "       t.priority DESC, p.price DESC, COALESCE(a.expires, DATE_ADD(NOW(), INTERVAL 100 YEAR)) DESC ";
				$sql .= "     LIMIT 1 ";
			}
			$sql .= "   ) AS personal ";
			
			$sql .= "   UNION ALL ";
			
			// Groups
			$sql .= "   SELECT * FROM ( ";
			$sql .= "     SELECT ";
			$sql .= "       a.ID, COALESCE(NULLIF(d.name, ''), a.name, o.name) AS name, 'group' AS type, CONCAT(COALESCE(d.path, 'ZZZ'), COALESCE(NULLIF(d.name, ''), a.name, o.name)) AS sort, NULL AS mimeType, 0 AS size, COALESCE(d.color, '#808080') AS color, a.avatar, COALESCE(a.users, p.users) AS users, COALESCE(a.paidStorage, 0) + COALESCE(a.additionalStorage, 0) + COALESCE(p.storage, t.storage, 0) AS storage, a.created, a.modified ";
			$sql .= "     FROM onsong_connect_role r ";
			$sql .= "       INNER JOIN onsong_connect_account a ON r.accountID = a.ID ";
			$sql .= "       LEFT OUTER JOIN onsong_connect_plan p ON p.ID = a.planID ";
			$sql .= "       LEFT OUTER JOIN onsong_connect_plan_tier t ON p.tierID = t.ID ";
			$sql .= "       LEFT OUTER JOIN onsong_connect_organization o ON a.organizationID = o.ID ";
			$sql .= "       LEFT OUTER JOIN onsong_connect_drive_item d ON d.accountID = a.ID AND d.userID IS NULL AND d.path = '/' ";
			$sql .= "     WHERE ";
			$sql .= "       r.userID = ? AND r.deleted IS NULL ";
			$params[] = Role::current()->userID();

			$sql .= "     HAVING ";
			$sql .= "       users > 1 AND storage > 0 ";
			$sql .= "     ORDER BY ";
			$sql .= "       a.name ";
			$sql .= "   ) AS team ";
			$sql .= " ) AS everything ";

			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			return $statement;
		}
	}
?>