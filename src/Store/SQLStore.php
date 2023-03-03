<?php

declare(strict_types=1);

namespace SimpleSAML\Module\accountlinker\Store;

use Exception;
use PDO;
use PDOException;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\Logger;
use SimpleSAML\Utils;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function http_build_query;
use function rtrim;
use function sprintf;

/**
 * Account Linking SQL Store
 *
 * @author Christian Gijtenbeek
 */
class SQLStore
{
    /**
     * DSN config.
     */
    private Configuration $accountLinkerConfig;

    /**
     * DSN for the database.
     */
    private string $dsn;

    /**
     * Username for the database.
     */
    private string $username;

    /**
     * Password for the database;
     */
    private string $password;

    /**
     * Database handle.
     *
     * This variable can't be serialized.
     *
     * @var \PDO|false
     */
    private $store = false;

    /**
     * Metadata entityId
     */
    private string $entityId;

    /**
     * Metadata SpEntityId
     *
     * @var string|false
     */
    private $spEntityId;

    /**
     * Metadata attributes
     */
    private array $attributes;

    /**
     * ErrorHandling Service url
     */
    private string $ehsURL;

    /**
     * Entityid_id from entityid table
     */
    private ?int $entityidId = null;

    /**
     * Account_id from attributes table
     */
    private ?int $accountId = null;

    /**
     * Is this a new account?
     */
    private bool $newAccount = false;


    /**
     * Initialize store.
     *
     * @param array $config  Configuration information for this collector.
     */
    public function __construct(array $config)
    {
        $this->accountLinkerConfig = Configuration::getConfig('module_accountlinker.php');
        foreach (['dsn', 'username', 'password'] as $param) {
            $config[$param] = $this->accountLinkerConfig->getOptionalString($param, null);
            if ($config[$param] === null) {
                throw new Error\Exception('AccountLinking - Missing required option \'' . $param . '\'.');
            }
        }

        $this->dsn = $config['dsn'];
        $this->ehsURL = "https://ds.incommon.org/FEH/sp-error.html";
        $this->username = $config['username'];
        $this->password = $config['password'];
    }

    /**
     * Set IdP attributes for the store
     */
    public function setRequest(array $request): void
    {
        if (!array_key_exists('saml:sp:IdP', $request)) {
            throw new Error\Exception('AccountLinking - Missing required attribute saml:sp:IdP');
        }

        $this->entityId = $request['saml:sp:IdP'];
        $this->spEntityId = $request['SPMetadata']['entityid'];
        $this->attributes = $request['Attributes'];
    }

    /**
     * Lazy load entityid_id
     *
     * @return false|int|string entityid_id
     */
    protected function getEntityidId()
    {
        if ($this->entityidId) {
            return $this->entityidId;
        }

        $this->entityidId = $this->hasEntityId();
        return $this->entityidId;
    }

    /**
     * Check if entity_id exists
     *
     * @return string|false value of entity_id or false
     */
    public function hasEntityId()
    {
        $dbh = $this->getStore();
        $stmt = $dbh->prepare("SELECT entityid_id FROM entityids WHERE name=:entity_id");
        $stmt->bindParam(':entity_id', $this->entityId, PDO::PARAM_INT);
        $stmt->execute();
        $this->entityidId = $stmt->fetchColumn();
        return $this->entityidId;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    /**
     * @return false|int|string
     */
    public function getSpEntityId()
    {
        return $this->spEntityId;
    }

    /**
     * Insert entity_id
     *
     * @return false|int|string value of entity_id
     */
    public function addEntityId()
    {
        $dbh = $this->getStore();
        $stmt = $dbh->prepare("INSERT INTO entityids (name, type) VALUES (:entity_id,:type)");
        $stmt->execute([
            ':entity_id' => $this->entityId,
            ':type' => 'idp'
        ]);
        return $this->getEntityidId();
    }

    /**
     * Getter for account_id
     *
     * @return integer|null account_id
     */
    protected function getAccountId(): ?int
    {
        return $this->accountId;
    }

    /**
     * Try to match Identifiable attributes
     *
     * @return int|false account_id or false
     */
    public function matchIdentifiableAttributes()
    {
        $dbh = $this->getStore();

        // get identifiable attributes for this entity
        $stmt = $dbh->prepare("SELECT ida.attribute_id, ap.name, ap.singlevalue FROM idattributes ida
            LEFT JOIN attributeproperties ap ON (ida.attribute_id = ap.attributeproperty_id)
            WHERE entity_id=:entity_id ORDER BY ida.aorder");
        $stmt->execute([':entity_id' => $this->getEntityidId()]);

        // @note This only deals with single attribute value
        // @todo this exception must only show if none of the identifiable attributes are in the metadata
        // throw new Exception('AccountLinking - Missing required identifiable attribute '.$row['name']);
        //only deal with single values (eg, take first element of array)

        // @todo Check if IDP gives identifiable attributes AT ALL (should be at least one!).
        // Otherwise throw error back to IDP
        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            Logger::stats('AccountLinker: Checking for attribute \'' . $row['name'] . '\'');
            if (isset($this->attributes[$row['name']])) {
                $count++;
                //$stmt2 = $dbh->prepare("SELECT a.account_id, a.attributeproperty_id
                //FROM attributes a WHERE a.value=:attribute_value AND a.attributeproperty_id=:attribute_id");
                $stmt2 = $dbh->prepare("SELECT at.account_id, at.attributeproperty_id FROM attributes
                    at LEFT JOIN accounts ac ON (at.account_id = ac.account_id) WHERE at.value=:attribute_value
                    AND at.attributeproperty_id=:attribute_id AND ac.entityid_id=:entityid_id");
                $stmt2->execute([
                    ':attribute_value' => $this->attributes[$row['name']][0],
                    ':attribute_id' => $row['attribute_id'],
                    ':entityid_id' => $this->getEntityidId()
                ]);
                $return = $stmt2->fetch(PDO::FETCH_NUM);

                if (!empty($return)) {
                    $stmt3 = $dbh->prepare(
                        "SELECT name FROM attributeproperties WHERE attributeproperty_id=:attribute_id"
                    );
                    $stmt3->execute([':attribute_id' => $return[1]]);
                    $attribute_name = $stmt3->fetchColumn();
                    Logger::stats(sprintf(
                        'AccountLinker: Found match on attribute \'%s\' for account id %s',
                        $attribute_name,
                        $return[0],
                    ));
                    $this->accountId = $return[0];
                    return $this->accountId;
                }
            }
            Logger::stats('AccountLinker: Attribute \'' . $row['name'] . '\' not found in metadata/datastore');
        }

        if ($count === 0) {
            $error = 'Could not find any of the attributes to determine who you are';
            Logger::stats('AccountLinker: EXCEPTION ' . $error);
            #throw new Exception('AccountLinking ' . $error );
            $this->handleException();
        }

        return false;
    }

    /**
     * Add record to the accounts table
     *
     * @note you can't use autoincrement here but instead we use max()+1 to entity_id and user_id.
     * This way you know for sure that a new user is always in its own group.
     *
     * @todo the RETURNING clause only works in PGSQL - add driver testing to add driver specific code
     */
    public function addAccount(): void
    {
        $dbh = $this->getStore();
        $stmt = $dbh->prepare("INSERT INTO accounts (account_id, user_id, entityid_id, priority)
            SELECT max(account_id)+1, max(account_id)+1, :entity_id, 1 FROM accounts RETURNING account_id");
        $stmt->execute([
            ':entity_id' => $this->getEntityidId()
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->accountId = $result['account_id'];
        $this->newAccount = true;
    }

    /**
     * Store saml attributes
     *
     * @todo DO standardizes on using SQL-92 SQLSTATE error code strings; individual PDO drivers
     * are responsible for mapping their native codes to the appropriate SQLSTATE codes.
     * The PDO::errorCode() method returns a single SQLSTATE code.
     *
     * @todo audit log, nothing is done with the update values yet
     *
     * @note the approach for inserting attribute properties could be prone to race conditions.
     *
     */
    public function saveAttributes(): bool
    {
        if (!$this->getAccountId()) {
            throw new Error\Exception("Can't save attributes, no account_id found");
        }

        // init dbh
        $dbh = $this->getStore();
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // define variables
        $metadataAttributeString = null;
        $insertValues = $updateValues = [];
        $metadataAttributes = $this->attributes;

        // get stored attributes for this account
        $stmt = $dbh->prepare("SELECT ap.name, a.value
            FROM attributes a LEFT JOIN attributeproperties ap ON
            (ap.attributeproperty_id = a.attributeproperty_id)
            WHERE a.account_id=:account_id");
        $stmt->execute([
            ':account_id' => $this->getAccountId()
        ]);
        $storedAttributes = $stmt->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_GROUP);

        // Store Attribute Properties
        // get stored attributes that are also metadata attributes
        foreach ($metadataAttributes as $metadataAttribute => $value) {
            $metadataAttributeString .= $dbh->quote($metadataAttribute) . ",";
        }
        $metadataAttributeString = rtrim($metadataAttributeString, ',');
        $stmt = $dbh->prepare("SELECT name FROM attributeproperties WHERE name IN (" . $metadataAttributeString . ")");
        $stmt->execute();
        $return = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $diff = array_diff(array_keys($metadataAttributes), $return);
        // insert attribute properties
        if (!empty($diff)) {
            $stmt = $dbh->prepare("INSERT INTO attributeproperties (name) VALUES (:attribute)");
            foreach ($diff as $key => $val) {
                $stmt->execute([':attribute' => $val]);
            }
        }

        // Map attribute property Ids
        // get stored attributes that are also metadata attributes (including newly inserted ones)
        $stmt = $dbh->prepare(sprintf(
            "SELECT attributeproperty_id, name FROM attributeproperties WHERE name IN (%s)",
            $metadataAttributeString,
        ));
        $stmt->execute();

        // used for debugging only
        $attributeMapping = [];

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $insertValues[$row[0]] = $metadataAttributes[$row[1]];
            $attributeMapping[$row[0]] = $row[1];
        }

        try {
            $dbh->beginTransaction();
            // delete attributes
            $stmt = $dbh->prepare("DELETE FROM attributes WHERE account_id=:account_id");
            $stmt->execute([
                ':account_id' => $this->getAccountId()
            ]);

            // insert attributes
            $query = "INSERT INTO attributes (account_id, attributeproperty_id, value) VALUES ";
            $accountId = $this->getAccountId();
            foreach ($insertValues as $attributePropertyId => $value) {
                if (count($value) === 1) {
                    Logger::stats(sprintf(
                        'AccountLinker: Inserting %s => \'%s\'',
                        $attributeMapping[$attributePropertyId],
                        $value[0],
                    ));
                    $query .= "(" . $accountId . ","
                        . $attributePropertyId . ","
                        . $dbh->quote($value[0]) . "),";
                } else {
                    // multivalue attribute
                    foreach ($value as $val) {
                        Logger::stats(sprintf(
                            'AccountLinker: Inserting %s => \'%s\'',
                            $attributeMapping[$attributePropertyId],
                            $val,
                        ));
                        $query .= "(" . $accountId . ","
                            . $attributePropertyId . ","
                            . $dbh->quote($val) . "),";
                    }
                }
            }

            $query = rtrim($query, ',');
            $stmt = $dbh->prepare($query);
            $stmt->execute();

            $dbh->commit();
        } catch (Exception $e) {
            $dbh->rollBack();
            throw new Error\Exception('Failed to insert attributes');
        }

        return true;
    }

    /**
     * Getter for user_id
     *
     * @return integer user_id
     */
    public function getUserId(): int
    {
        if (!$this->getAccountId()) {
            throw new Error\Exception('Can\'t get user_id, no account_id found');
        }
        $dbh = $this->getStore();
        $stmt = $dbh->prepare("SELECT user_id FROM accounts WHERE account_id=:account_id");
        $stmt->execute([
            ':account_id' => $this->getAccountId()
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['user_id'];
    }

    /**
     * Store sp entityid
     *
     * @return integer user_id
     */
    public function saveSpEntityId(): int
    {
        $userId = $this->getUserId();
        $dbh = $this->getStore();
        $stmt = $dbh->prepare("INSERT INTO users_spentityids (
            user_id,
            account_id,
            spentityid,
            idp_entityid,
            ip_addr,
            user_agent
        ) VALUES (
            :user_id,
            :account_id,
            :spentityid,
            :idp_entityid,
            :ip_addr,
            :user_agent)");
        $stmt->execute(array(
            ':user_id' => $userId,
            ':idp_entityid' => $this->getEntityId(),
            ':account_id' => $this->getAccountId(),
            ':spentityid' => $this->spEntityId,
            ':ip_addr' => $_SERVER['REMOTE_ADDR'] ?? '::1',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ));

        Logger::stats('AccountLinker: Returning user_id ' . $userId);
        return $userId;
    }



    /**
     * Set default values for identifiable attributes
     */
    public function addIdentifiableAttributes(): self
    {
        Logger::stats('AccountLinker: adding default id attributes for entityid_id: ' . $this->getEntityidId());
        $dbh = $this->getStore();
        $stmt = $dbh->prepare(
            "INSERT INTO idattributes (attribute_id, entity_id, aorder) VALUES (:attribute_id,:entity_id, :aorder)"
        );
        $stmt->execute([
            ':attribute_id' => 1,
            ':entity_id' => $this->getEntityidId(),
            ':aorder' => 1
        ]);
        $stmt->execute([
            ':attribute_id' => 2,
            ':entity_id' => $this->getEntityidId(),
            ':aorder' => 2
        ]);
        $stmt->execute([
            ':attribute_id' => 81,
            ':entity_id' => $this->getEntityidId(),
            ':aorder' => 3
        ]);
        $stmt->execute([
            ':attribute_id' => 259,
            ':entity_id' => $this->getEntityidId(),
            ':aorder' => 4
        ]);
        return $this;
    }

    private function handleException(): void
    {
        $data = [
            'sp_entityID' => $this->spEntityId,
            'idp_entityID' => $this->getEntityId()
        ];
        $queryString = $this->ehsURL . '?' . http_build_query($data);
        Logger::stats('TAL EHS:' . $queryString);
        $httpUtils = new Utils\HTTP();
        $httpUtils->redirectTrustedURL($queryString);
    }


    /**
     * Lazy load database handle
     *
     * @return mixed  PDO Database handle, or false
     */
    private function getStore()
    {
        if (false !== $this->store) {
            return $this->store;
        }

        try {
            $this->store = new PDO($this->dsn, $this->username, $this->password);
        } catch (PDOException $e) {
            throw new Error\Exception('could not connect to database');
        }
        return $this->store;
    }
}
