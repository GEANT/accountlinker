<?php

declare(strict_types=1);

namespace SimpleSAML\Module\accountlinker\Auth\Process;

use SimpleSAML\Assert\Assert;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Module\accountlinker\Store\SQLStore;

use function array_key_exists;

/**
 * Account Linking filter
 *
 * @author Christian Gijtenbeek
 */
class AccountLinker extends Auth\ProcessingFilter
{
    /**
     * Holds the datastore
     */
    protected ?SQLStore $store = null;

    /**
     * Prefix account_id attribute
     */
    private string $accountIdPrefix;

    private static Configuration $config;

    /**
     * Initialize this filter.
     *
     * @param array $config  Configuration information for this filter.
     */
    public function __construct(array $config, $reserved)
    {
        parent::__construct($config, $reserved);

        $this->_store = $this->getStore($config);

        $this->accountIdPrefix = (isset($config['accountIdPrefix']))
            ? $config['accountIdPrefix'] : 'TAL';
    }

    /**
     * Get Account Linking Store
     *
     * @param array $config Configuration array
     * @return \SimpleSAML\Module\accountlinker\Store\SQLStore
     */
    protected function getStore(array $config): SQLStore
    {
        if (!array_key_exists('store', $config) || !array_key_exists("class", $config['store'])) {
            throw new Error\Exception('No store class specified in configuration');
        }

        $storeConfig = $config['store'];
        $storeClassName = Module::resolveClass($storeConfig['class']);
        Assert::isInstanceOf($storeClassName, SQLStore::class);
        unset($storeConfig['class']);

        return new $storeClassName($storeConfig);
    }

    /**
     * Apply filter
     *
     * @param array &$request  The current request
     */
    public function process(array &$request): void
    {
        Assert::keyExists($request, 'Attributes');

        $this->store->setRequest($request);

        Logger::stats('AccountLinker: === BEGIN === ');

        if ($this->store->hasEntityId()) {
            Logger::stats('AccountLinker: entityid ' . $this->store->getEntityId() . ' is already known here');
            Logger::stats('AccountLinker: SP entityid ' . $this->store->getSpEntityId());
            if (!$this->store->matchIdentifiableAttributes()) {
                Logger::stats('AccountLinker: no account match found, adding account');
                $this->store->addAccount();
                $newAccount = true;
            }
        } else {
            Logger::stats('AccountLinker: entityid does not exist, adding it');
            $this->store->addEntityId();
            $this->store->addIdentifiableAttributes();
            Logger::stats('AccountLinker: entityid does not exist, adding account');
            $this->store->addAccount();
        }

        Logger::stats('AccountLinker: Inserting attributes');

        if ($this->store->saveAttributes()) {
            $request['Attributes'][$this->accountIdPrefix . ':user_id'] = [
                $this->store->saveSpEntityId()
            ];

            Logger::stats('AccountLinker: === END ===');
        }
    }
}
