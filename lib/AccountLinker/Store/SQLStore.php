<?php
/**
 * Account Linking SQL Store
 *
 * @author Christian Gijtenbeek
 */
class sspmod_accountLinker_AccountLinker_Store_SQLStore {

    /**
     * DSN config.
     */
    private $_accountLinkerConfig;


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
     * Metadata entityId
     */
    private $_entityId;

    /**
     * Metadata SpEntityId
     */
    private $_spEntityId = null;

    /**
     * Metadata attributes
     */
    private $_attributes;

    /**
     * ErrorHandling Service url
     */
    private $_ehsURL;

    /**
     * Entityid_id from entityid table
     */
    private $_entityidId = null;

    /**
     * Account_id from attributes table
     */
    private $_accountId = null;

    /**
     * Is this a new account?
     */
    private $_newAccount = false;


    /**
     * Initialize store.
     *
     * @param array $config  Configuration information for this collector.
     */
    public function __construct($config)
    {
        $this->_accountLinkerConfig = SimpleSAML_Configuration::getConfig('module_accountlinker.php');
        foreach (array('dsn', 'username', 'password') as $param) {
            $config[$param] = $this->_accountLinkerConfig->getString($param, NULL);
            if ($config[$param] === NULL) {
                throw new Exception('AccountLinking - Missing required option \'' . $param . '\'.');
            }
        }

        $this->_dsn = $config['dsn'];
        $this->_ehsURL = "https://ds.incommon.org/FEH/sp-error.html";
        $this->_username = $config['username'];
        $this->_password = $config['password'];
    }

    /**
     * Set IdP attributes for the store
     *
     */
    public function setRequest(array $request)
    {
        if (!array_key_exists('saml:sp:IdP', $request)) {
            throw new Exception('AccountLinking - Missing required attribute saml:sp:IdP');
        }

        $this->_entityId = $request['saml:sp:IdP'];
        $this->_spEntityId = $request['SPMetadata']['entityid'];
        $this->_attributes = $request['Attributes'];
    }

    /**
     * Lazy load entityid_id
     *
     * @return    integer        entityid_id
     */
    protected function _getEntityidId()
    {
        if ($this->_entityidId) {
            return $this->_entityidId;
        }
        $this->_entityidId = $this->hasEntityId();
        return $this->_entityidId;
    }

    /**
     * Check if entity_id exists
     *
     * @return value of entity_id or false
     */
    public function hasEntityId()
    {
        $dbh = $this->_getStore();
        $stmt = $dbh->prepare("SELECT entityid_id FROM entityids WHERE name=:entity_id");
        $stmt->bindParam(':entity_id', $this->_entityId, PDO::PARAM_INT);
        $stmt->execute();
        $this->_entityidId = $stmt->fetchColumn();
        return $this->_entityidId;
    }

    public function getEntityId()
    {
        return $this->_entityId;
    }

    public function getSpEntityId()
    {
        return $this->_spEntityId;
    }

    /**
     * Insert entity_id
     *
     * @return value of entity_id
     */
    public function addEntityId()
    {
        $dbh = $this->_getStore();
        $stmt = $dbh->prepare("INSERT INTO entityids (name, type) VALUES (:entity_id,:type)");
        $stmt->execute(array(
            ':entity_id' => $this->_entityId,
            ':type' => 'idp'
        ));
        return $this->_getEntityidId();
    }

    /**
     * Getter for account_id
     *
     * @return    integer        account_id
     */
    protected function _getAccountId()
    {
        return $this->_accountId;
    }

    /**
     * Try to match Identifiable attributes
     *
     * @return    mixed        account_id or false
     */
    public function matchIdentifiableAttributes()
    {
        $dbh = $this->_getStore();

        // get identifiable attributes for this entity
        $stmt = $dbh->prepare("SELECT ida.attribute_id, ap.name, ap.singlevalue FROM idattributes ida
            LEFT JOIN attributeproperties ap ON (ida.attribute_id = ap.attributeproperty_id)
            WHERE entity_id=:entity_id ORDER BY ida.aorder");
        $stmt->execute(
            array(':entity_id' => $this->_getEntityidId())
        );

        // @note This only deals with single attribute value
        // @todo this exception must only show if none of the identifiable attributes are in the metadata
        // throw new Exception('AccountLinking - Missing required identifiable attribute '.$row['name']);
        //only deal with single values (eg, take first element of array)

        // @todo Check if IDP gives identifiable attributes AT ALL (should be at least one!). Otherwise throw error back to IDP
        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            SimpleSAML_Logger::stats('AccountLinker: Checking for attribute \''.$row['name'].'\'');
            if (isset($this->_attributes[$row['name']])) {
                $count++;
                //$stmt2 = $dbh->prepare("SELECT a.account_id, a.attributeproperty_id
                //FROM attributes a WHERE a.value=:attribute_value AND a.attributeproperty_id=:attribute_id");
                $stmt2 = $dbh->prepare("SELECT at.account_id, at.attributeproperty_id FROM attributes at LEFT JOIN accounts ac ON (at.account_id = ac.account_id) WHERE at.value=:attribute_value AND at.attributeproperty_id=:attribute_id AND ac.entityid_id=:entityid_id");
                $stmt2->execute(array(
                    ':attribute_value' => $this->_attributes[$row['name']][0],
                    ':attribute_id' => $row['attribute_id'],
                    ':entityid_id' => $this->_getEntityidId()
                ));
                $return = $stmt2->fetch(PDO::FETCH_NUM);

                if (!empty($return)) {
                    $stmt3 = $dbh->prepare("SELECT name FROM attributeproperties WHERE attributeproperty_id=:attribute_id");
                    $stmt3->execute(array(':attribute_id' => $return[1]));
                    $attribute_name = $stmt3->fetchColumn();
                    SimpleSAML_Logger::stats('AccountLinker: Found match on attribute \''.$attribute_name .'\' for account id '. $return[0]);
                    $this->_accountId = $return[0];
                    return $this->_accountId;
                }
            }
            SimpleSAML_Logger::stats('AccountLinker: Attribute \''.$row['name'].'\' not found in metadata/datastore');
        }

        if ($count === 0) {
            $error = 'Could not find any of the attributes to determine who you are';
            SimpleSAML_Logger::stats('AccountLinker: EXCEPTION '.$error);
            #throw new Exception('AccountLinking '.$error );
            $this->_handleException();
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
    public function addAccount()
    {
        $dbh = $this->_getStore();
        $stmt = $dbh->prepare("INSERT INTO accounts (account_id, user_id, entityid_id, priority)
            SELECT max(account_id)+1, max(account_id)+1, :entity_id, 1 FROM accounts RETURNING account_id");
        $stmt->execute(array(
            ':entity_id' => $this->_getEntityidId()
        ));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->_accountId = $result['account_id'];
        $this->_newAccount = true;
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
    public function saveAttributes()
    {
        if (!$this->_getAccountId()) {
            throw new Exception("Can't save attributes, no account_id found");
        }

        // init dbh
        $dbh = $this->_getStore();
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // define variables
        $metadataAttributeString = null;
        $insertValues = $updateValues = array();
        $metadataAttributes = $this->_attributes;

        // get stored attributes for this account
        $stmt = $dbh->prepare("SELECT ap.name, a.value
            FROM attributes a LEFT JOIN attributeproperties ap ON
            (ap.attributeproperty_id = a.attributeproperty_id)
            WHERE a.account_id=:account_id");
        $stmt->execute(array(
            ':account_id' => $this->_getAccountId()
        ));
        $storedAttributes = $stmt->fetchAll(PDO::FETCH_COLUMN|PDO::FETCH_GROUP);

        // Store Attribute Properties
        // get stored attributes that are also metadata attributes
        foreach ($metadataAttributes as $metadataAttribute => $value) {
            $metadataAttributeString .= $dbh->quote($metadataAttribute).",";
        }
        $metadataAttributeString = rtrim($metadataAttributeString, ',');
        $stmt = $dbh->prepare("SELECT name FROM attributeproperties WHERE name IN (".$metadataAttributeString.")");
        $stmt->execute();
        $return = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $diff = array_diff(array_keys($metadataAttributes), $return);
        // insert attribute properties
        if (!empty($diff)) {
            $stmt = $dbh->prepare("INSERT INTO attributeproperties (name) VALUES (:attribute)");
            foreach ($diff as $key => $val) {
                $stmt->execute(array(':attribute' => $val));
            }
        }

        // Map attribute property Ids
        // get stored attributes that are also metadata attributes (including newly inserted ones)
        $stmt = $dbh->prepare("SELECT attributeproperty_id, name FROM attributeproperties WHERE name IN (".$metadataAttributeString.")");
        $stmt->execute();

        // used for debugging only
        $attributeMapping = array();

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $insertValues[$row[0]] = $metadataAttributes[$row[1]];
            $attributeMapping[$row[0]] = $row[1];
        }

        try {
            $dbh->beginTransaction();
            // delete attributes
            $stmt = $dbh->prepare("DELETE FROM attributes WHERE account_id=:account_id");
            $stmt->execute(array(
                ':account_id' => $this->_getAccountId()
            ));

            // insert attributes
            $query = "INSERT INTO attributes (account_id, attributeproperty_id, value) VALUES ";
            $accountId = $this->_getAccountId();
            foreach ($insertValues as $attributePropertyId => $value) {
                if (count($value) === 1) {
                    SimpleSAML_Logger::stats('AccountLinker: Inserting '.$attributeMapping[$attributePropertyId].' => \''.$value[0] . '\'');
                    $query .= "(".$accountId.","
                        .$attributePropertyId.","
                        .$dbh->quote($value[0])."),";
                } else {
                    // multivalue attribute
                    foreach ($value as $val) {
                        SimpleSAML_Logger::stats('AccountLinker: Inserting '.$attributeMapping[$attributePropertyId].' => \''.$val.'\'');
                        $query .= "(".$accountId.","
                            .$attributePropertyId.","
                            .$dbh->quote($val)."),";
                    }
                }
            }

            $query = rtrim($query, ',');
            $stmt = $dbh->prepare($query);
            $stmt->execute();

            $dbh->commit();
        } catch (Exception $e) {
            $dbh->rollBack();
            throw new Exception('Failed to insert attributes');
        }

        return true;
    }

    /**
     * Getter for user_id
     *
     * @return    integer        user_id
     */
    public function getUserId()
    {
        if (!$this->_getAccountId()) {
            throw new Exception('Can\'t get user_id, no account_id found');
        }
        $dbh = $this->_getStore();
        $stmt = $dbh->prepare("SELECT user_id FROM accounts WHERE account_id=:account_id");
        $stmt->execute(array(
            ':account_id' => $this->_getAccountId()
        ));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['user_id'];
    }

    /**
     * Store sp entityid
     *
     * @return    integer        user_id
     */
    public function saveSpEntityId()
    {
        $userId = $this->getUserId();
        $dbh = $this->_getStore();
        $stmt = $dbh->prepare("INSERT INTO users_spentityids (
            user_id,
            account_id,
            spentityid,
            idp_entityid
        ) VALUES (
            :user_id,
            :account_id,
            :spentityid,
            :idp_entityid)");
        $stmt->execute(array(
            ':user_id' => $userId,
            ':idp_entityid' => $this->getEntityId(),
            ':account_id' => $this->_getAccountId(),
            ':spentityid' => $this->_spEntityId
        ));

        SimpleSAML_Logger::stats('AccountLinker: Returning user_id '.$userId);
        return $userId;
    }



    /**
     * Set default values for identifiable attributes
     *
     */
    public function addIdentifiableAttributes()
    {
        SimpleSAML_Logger::stats('AccountLinker: adding default id attributes for entityid_id: '. $this->_getEntityidId());
        $dbh = $this->_getStore();
        $stmt = $dbh->prepare("INSERT INTO idattributes (attribute_id, entity_id, aorder) VALUES (:attribute_id,:entity_id, :aorder)");
        $stmt->execute(array(
            ':attribute_id' => 1,
            ':entity_id' => $this->_getEntityidId(),
            ':aorder' => 1
        ));
        $stmt->execute(array(
            ':attribute_id' => 2,
            ':entity_id' => $this->_getEntityidId(),
            ':aorder' => 2
        ));
        $stmt->execute(array(
            ':attribute_id' => 81,
            ':entity_id' => $this->_getEntityidId(),
            ':aorder' => 3
        ));
        $stmt->execute(array(
            ':attribute_id' => 259,
            ':entity_id' => $this->_getEntityidId(),
            ':aorder' => 4
        ));
        return $this;
    }

    private function _handleException()
    {
        $data = array(
            'sp_entityID' => $this->_spEntityId,
            'idp_entityID' => $this->getEntityId()
        );
        $queryString = $this->_ehsURL.'?'.http_build_query($data);
        SimpleSAML_Logger::stats('TAL EHS:'.$queryString);
        SimpleSAML_Utilities::redirect($queryString);
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
