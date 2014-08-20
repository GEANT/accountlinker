<?php
$version = '0.0.2';
header('P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>TAL Admin</title>


<?php
echo '<!-- JQuery -->';
echo '<script type="text/javascript" language="javascript" src="' . SimpleSAML_Module::getModuleURL('accountLinker/resources/jquery.js') . '"></script>
<script type="text/javascript" language="javascript" src="' . SimpleSAML_Module::getModuleURL('accountLinker/resources/jquery-ui.js') . '"></script>
';

// todo: add option loading here by echoing out javascript, same as in discojuice module
echo '<!-- AccountLinker -->
<script type="text/javascript" language="javascript" src="' . SimpleSAML_Module::getModuleURL('accountLinker/resources/admin.js?v=' . $version ) . '"></script>';
?>

<style type="text/css">
div.infobox {
	border: 1px solid #B1B1B1;
	border-radius: 10px 10px 10px 10px;
	margin: 5px 0 30px;
	padding: 0 6px 0 6px;
}
div.outerbox {
	padding:20px;
}
div.grnav {
	background: #fff;
    color: #000000;
	border: 1px solid #B1B1B1;
	border-radius: 6px 6px 6px 6px;
    font-weight: bold;
    height: 22px;
    margin: -38px 0 0;
    padding: 5px 5px;
    width: 100px;
}    
dl.infolist dd {
    font-size: 92%;
    margin-bottom: 12px;
}
dt:after {
    content: ":";
}
dt {
    margin-bottom: 2px;
}
dt {
    clear: left;
    float: left;
    text-align: left;
    width: 80px;
}
span.metanav {
    font-size: 11px;
}

</style>
</head>

<h1>Account admin</h1>

<p>Legacy smart_id importer</p>
<form action="" accept-charset="UTF-8" method="post" class="whatever" id="c">
<label for="smart_id">smart_id</label><input type="text" id="smart_id" name="smart_id" />
<input type="submit" value="Go!" />
</form>

<form action="" accept-charset="UTF-8" method="post" class="searchform" id="c">
<p>Show all accounts that logged in to
<input type="text" name="sp" />
using IdP
<input type="text" name="idp" />
with attribute value
<input type="text" name="attr" />
<span><a href="#" class="reset">reset</a></span>
</p>
</form>


<?php
$this->includeAtTemplateBase('includes/footer.php');
?>