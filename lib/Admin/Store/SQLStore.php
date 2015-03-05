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

	public function getAllSp()
	{
		$dbh = $this->_getStore();
		$stmt = $dbh->prepare("select distinct(spentityid) from users_spentityids");
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
		$metadataHandler = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		foreach($rows as $row) {
			if (isset($row)) {
				try {
					$spName = $metadataHandler->getMetaData($row, 'saml20-sp-remote');
					$spName = (empty($spName['name']['en'])) ? $row : $spName['name']['en'];
				}	catch (Exception $e) {
					$spName = $row;
				}
				$names[$row] = $spName;
			}
		}
		asort($names);
		return $names;
	}

	public function getAllIdp()
	{
		$dbh = $this->_getStore();
		$stmt = $dbh->prepare("select entityid_id, name from entityids where type='idp'");
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_GROUP);
	}

	public function searchAccount($type)
	{
		$dbh = $this->_getStore();
		$query = 'select * from vw_attributes_new where user_id in (select user_id from users_spentityids where 1=1)';
		foreach ($type as $key => $value) {
    		$query .= sprintf(' AND `%s` = :%s', $key, $key);
		}
		$stmt = $dbh->prepare($query);
		foreach ($type as $key => $value) {
		    $stmt->bindValue(':'.$key, $value);
		}
				
		$stmt->execute();
		
		return $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
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