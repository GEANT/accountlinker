<?php
/**
 * Account Linking Admin
 *
 * @author Christian Gijtenbeek
 */
class sspmod_accountLinker_Admin_admin {

	/**
	 * Holds the datastore
	 */
	protected $_store = null;
	
	private $_namespace;

	public function __construct($config)
	{
	    assert('is_array($config)');
		$this->_store = $this->_getStore($config);
		$this->_namespace = 'accountlinker';
	}

	/**
	 * Get Account Linking Store
	 *
	 * @param	array	$config		Configuration array
	 * @return	sspmod_accountLinker_AccountLinker_Store
	 */
	protected function _getStore($config)
	{
		if (!$config->getValue('class')) {
			throw new Exception('No store class specified in configuration');
		}

		$storeClassName = SimpleSAML_Module::resolveClass(
			$config->getValue('class'),
			'Admin_Store'
		);
		return new $storeClassName($config->toArray());
	}

	public function getAccounts($tal_id)
	{
		return $this->_store->getAccounts($tal_id);
	}
	
	/**
	 * @todo add parameter so you can clear ONE instance of the search
	 *
	 */
	public function resetSearch($session)
	{
		unset($_SESSION[$this->_namespace][$session]);
	}

	public function searchAccount($type, $value, $session)
	{
	    assert('is_string($type)');

		$accountIds = null;

		if (isset($_SESSION[$this->_namespace][$session])) {
			#foreach ($_SESSION[$this->_namespace] as $k => $v) {
			#	$accountIds = array_keys($v);			
			#}
			$accountIds = array_keys($_SESSION[$this->_namespace][$session]);
		}

		$result = $this->_store->searchAccount($type, $value, $accountIds);
		#$_SESSION[$this->_namespace][$type] = $result;
		$_SESSION[$this->_namespace][$session] = $result;
		

		return $result;
	}

}