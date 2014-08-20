<?php
header('Content-type: application/json; utf-8');

try {
	$config = SimpleSAML_Configuration::getInstance();
	$session = SimpleSAML_Session::getInstance();

	SimpleSAML_Utilities::requireAdmin();
	
	$adminConfig = SimpleSAML_Configuration::getConfig('module_accountlinker.php');
	#$meh = $adminConfig->getValue('logfile', '/var/simplesamlphp.log');

	$accountAdmin = new sspmod_accountLinker_Admin_admin($adminConfig);

	if ($_REQUEST['reset']) {
		$accountAdmin->resetSearch($_REQUEST['session']);
		exit();
	}

	$result = $accountAdmin->searchAccount($_REQUEST['type'], $_REQUEST['val'], $_REQUEST['session']);

	echo json_encode($result); exit;
	throw new Exception('what?');

} catch (Exception $e) {

	echo json_encode(array('status' => 'error', 'error' => $e->getMessage()));

}