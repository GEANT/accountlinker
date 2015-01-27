<?php
$config = SimpleSAML_Configuration::getInstance();
$session = SimpleSAML_Session::getInstance();

SimpleSAML_Utilities::requireAdmin();

$adminConfig = SimpleSAML_Configuration::getConfig('module_accountlinker.php');
$meh = $adminConfig->getValue('logfile', '/var/simplesamlphp.log');

$accountAdmin = new sspmod_accountLinker_Admin_admin($adminConfig);

$tpl = new SimpleSAML_XHTML_Template($config, 'accountLinker:admin.tpl.php');
$tpl->accounts = $accountAdmin->getAccounts((int) $_POST['tal_id']);
$tpl->tal_id = (int) $_POST['tal_id'];
$tpl->show();
?>