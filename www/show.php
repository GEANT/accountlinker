<?php
SimpleSAML_Logger::info('TAL: Showing template');
session_cache_limiter('nocache');

$globalConfig = SimpleSAML_Configuration::getInstance();

if (!array_key_exists('StateId', $_REQUEST)) {
    throw new SimpleSAML_Error_BadRequest(
        'Missing required StateId query parameter.'
    );
}

$id = $_REQUEST['StateId'];
$session = SimpleSAML_Session::getInstance();

$statePrevious = SimpleSAML_Auth_State::loadState($id, 'accountlinker:request');
$spEntityId = $statePrevious['SPMetadata']['entityid'];
$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
$spMetadata = $metadata->getMetaDataConfig($spEntityId, 'saml20-sp-remote');
echo '<pre>';print_r($spMetadata);echo '</pre>';exit();

if (isset($_REQUEST['submit'])) {
	if ($_REQUEST['submit'] == 'Yes') {
		// user has clicked 'link my account'

		$stateCurrent = $session->getAuthState();

		unset($statePrevious['Attributes']['TAL:account_id']);

		if ($statePrevious['Attributes'] == $stateCurrent['Attributes']) {
			SimpleSAML_Logger::info('TAL: same accounts, nothing to link');
		} else {
			SimpleSAML_Logger::info('TAL: different accounts, link them');
		}

		SimpleSAML_Logger::info('TAL: re-auth');
		$auth = new SimpleSAML_Auth_Simple('default-sp');
		$auth->login(array(
			//'ReturnTo' => SimpleSAML_Module::getModuleURL('accountLinker/show.php')
			//'ReturnCallback' => array('sspmod_accountLinker_Auth_Process_AccountLinker', 'test')
		));
	} else {
		// proceed with processing
	}
}


// template stuff
$tpl = new SimpleSAML_XHTML_Template($globalConfig, 'accountLinker:linkerform.php');
#$tpl->data['formAction'] = SimpleSAML_Module::getModuleURL('accountLinker/show.php');
$tpl->data['yesData'] = array('StateId' => $id);
$tpl->idp = $statePrevious['saml:sp:IdP'];
$tpl->accountId = $statePrevious['Attributes']['TAL:account_id'][0];
$tpl->show();
?>