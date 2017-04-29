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

	public function getAllSp()
	{
		return $this->_store->getAllSp();
	}
	
	public function getAllIdp()
	{
		return $this->_store->getAllIdp();
	}
	
	/**
	 * @todo add parameter so you can clear ONE instance of the search
	 *
	 */
	public function resetSearch($session)
	{
		unset($_SESSION[$this->_namespace][$session]);
	}

	public function searchAccount($filters)
	{
	    assert('is_string($type)');

		$result = $this->_store->searchAccount($filters);
		$_SESSION[$this->_namespace][$session] = $result;
		
		$assoc_arr = array_reduce($result, function ($result, $item) {
		    #$result[$item['text']] = $item['id'];
		    return $result;
		}, array());
		
		#echo '<pre>';print_r($result);echo '</pre>';exit();
		return $result;
	}

}