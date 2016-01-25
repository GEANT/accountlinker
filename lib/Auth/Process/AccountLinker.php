<?php
/**
 * Account Linking filter
 *
 * @author Christian Gijtenbeek
 */
class sspmod_accountLinker_Auth_Process_AccountLinker extends SimpleSAML_Auth_ProcessingFilter {

	/**
	 * Holds the datastore
	 */
	protected $_store = null;

	/**
	 * Prefix account_id attribute
	 */
	private $_accountIdPrefix;

	public static $config;

	/**
	 * Initialize this filter.
	 *
	 * @param array $config  Configuration information for this filter.
	 */
	public function __construct($config, $reserved)
	{
	    parent::__construct($config, $reserved);

	    assert('is_array($config)');

	    $this->_store = $this->_getStore($config);

	    $this->_accountIdPrefix = (isset($config['accountIdPrefix']))
	    	? $config['accountIdPrefix']
	    	: 'TAL';
	}

	/**
	 * Get Account Linking Store
	 *
	 * @param	array	$config		Configuration array
	 * @return	sspmod_accountLinker_AccountLinker_Store
	 */
	protected function _getStore($config)
	{
		if (!array_key_exists('store', $config) || !array_key_exists("class", $config['store'])) {
			throw new Exception('No store class specified in configuration');
		}

		$storeConfig = $config['store'];
		$storeClassName = SimpleSAML_Module::resolveClass(
			$storeConfig['class'],
			'AccountLinker_Store'
		);
		unset($storeConfig['class']);
		$store = new $storeClassName($storeConfig);
		// @todo store in config
		if (1 == 1) {
			$observerClassName = SimpleSAML_Module::resolveClass(
				'accountLinker:Dashy',
				'AccountLinker_Observer'
			);		
			$observer = new $observerClassName;
			$store->attach($observer);
		}
		return $store;
	}

	/**
	 * Apply filter
	 *
	 * @param array &$request  The current request
	 */
	public function process(&$request)
	{
		assert('is_array($request)');
		assert('array_key_exists("Attributes", $request)');

		$this->_store->setRequest($request);
		
		SimpleSAML_Logger::debug('AccountLinker: === BEGIN === ');
				
		if ($this->_store->hasEntityId()) {
			SimpleSAML_Logger::debug('AccountLinker: entityid '.$this->_store->getEntityId().' is already known here');
			SimpleSAML_Logger::debug('AccountLinker: SP entityid '.$this->_store->getSpEntityId() );
			if (!$this->_store->matchIdentifiableAttributes()) {
				SimpleSAML_Logger::debug('AccountLinker: no account match found, adding account');
				$this->_store->addAccount();
				$newAccount = true;
			}
		} else {
			SimpleSAML_Logger::debug('AccountLinker: entityid does not exist, adding it');
			$this->_store->addEntityId();
			$this->_store->addIdentifiableAttributes();
			SimpleSAML_Logger::debug('AccountLinker: entityid does not exist, adding account');
			$this->_store->addAccount();
		}

		SimpleSAML_Logger::debug('AccountLinker: Inserting attributes');
		
		$this->_store->notify();

		if ($this->_store->saveAttributes()) {
			$request['Attributes'][$this->_accountIdPrefix.':user_id'] = array(
				$this->_store->saveSpEntityId()
			);
			
			SimpleSAML_Logger::debug('AccountLinker: === END === ');
		}

	}
}

?>
