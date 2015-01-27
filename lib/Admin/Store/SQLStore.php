<?php
/**
 * Account Linking Admin SQL Store
 *
 * @author Christian Gijtenbeek
 */
class sspmod_accountLinker_Admin_Store_SQLStore {

	/**
	 * DSN for the database.
	 */
	private $_dsn;

	/**
	 * Username for the database.
	 */
	private $_username;

	/**
	 * Password for the database;
	 */
	private $_password;

	/**
	 * Database handle.
	 *
	 * This variable can't be serialized.
	 */
	private $_store;

	/**
	 * Initialize store.
	 *
	 * @param array $config  Configuration information for this collector.
	 */
	public function __construct($config)
	{
		foreach (array('dsn', 'username', 'password') as $id) {
			if (!array_key_exists($id, $config)) {
				throw new Exception('AccountLinkingAdmin - Missing required option \'' . $id . '\'.');
			}
			if (!is_string($config[$id])) {
				throw new Exception('AccountLinkingAdmin - \'' . $id . '\' is supposed to be a string.');
			}
		}

		$this->_dsn = $config['dsn'];
		$this->_username = $config['username'];
		$this->_password = $config['password'];
	}

	public function getAccounts($tal_id)
	{
		$dbh = $this->_getStore();
		$stmt = $dbh->prepare("SELECT * FROM vw_attributes_new where user_id=?");
		$stmt->execute(array($tal_id));
		return $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
	}

	public function searchAccount($type, $value, $accountIds)
	{
		$dbh = $this->_getStore();
		switch ($type) {
			case 'sp':
				$query = "select attr.account_id, attr.value, a.user_id, a.entityid_id, attr.attributeproperty_id
				from attributes attr left join accounts a ON (attr.account_id = a.account_id)
				where a.user_id IN (select user_id from groups_spentityids where spentityid ILIKE '%".$value."%')";
				if ($accountIds) {
					$query .= " AND a.account_id IN (".implode(',', $accountIds).")";
				}
				$stmt = $dbh->prepare($query);
			break;
			case 'idp':
				$query = "select attr.account_id, attr.value, a.user_id, a.entityid_id, attr.attributeproperty_id
				from attributes attr left join accounts a ON (attr.account_id = a.account_id)
				where a.entityid_id IN (select entityid_id from entityids where name ILIKE '%".$value."%')";
				if ($accountIds) {
					$query .= " AND a.account_id IN (".implode(',', $accountIds).")";
				}
				$stmt = $dbh->prepare($query);
			break;
			case 'attr':
				//$query = "select attr.account_id, attr.value, a.user_id, a.entityid_id, attr.attributeproperty_id
				//from accounts a left join attributes attr on (a.account_id = attr.account_id) 
				//where attr.account_id IN (select account_id from attributes where value ILIKE '%".$value."%')";
				$query = "select e.name, attr.account_id, attr.value, a.user_id, a.entityid_id, attr.attributeproperty_id
				from entityids e left join accounts a on (e.entityid_id = a.entityid_id) 
				left join attributes attr on (a.account_id = attr.account_id) 
				where attr.account_id IN (select account_id from attributes where value ILIKE '%".$value."%')";
				if ($accountIds) {
					$query .= " AND a.account_id IN (".implode(',', $accountIds).")";
				}
				$stmt = $dbh->prepare($query);
			break;

		}
		//select * from accounts where user_id IN (select user_id from groups_spentityids where spentityid ILIKE '%chris%');
		//select * from accounts where entityid_id IN ( select entityid_id from entityids where name ILIKE '%terena%');
		$stmt->execute();
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$grouped = array();
		// group by user_id and account_id
		foreach ($result as $key => $val) {
			$grouped[$val['user_id']][$val['account_id']]['attributes'][$val['attributeproperty_id']] = $val['value'];
			$grouped[$val['user_id']][$val['account_id']]['account_id'] = $val['account_id'];
			$grouped[$val['user_id']][$val['account_id']]['entityid_id'] = $val['entityid_id'];
			$grouped[$val['user_id']][$val['account_id']]['entityid_name'] = $val['name'];
		}
		return $grouped;
	}

	/**
	 * Lazy load database handle
	 *
	 * @return mixed  PDO Database handle, or false
	 */
	private function _getStore()
	{
		if (null !== $this->_store) {
			return $this->_store;
		}
		try {
			$this->_store = new PDO($this->_dsn, $this->_username, $this->_password);
		} catch (PDOException $e) {
			throw new Exception('could not connect to database');
		}
		return $this->_store;
	}

}

?>