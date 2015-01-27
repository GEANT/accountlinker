<?php
$version = '0.0.2';
header('P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=Edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TAL Search</title>

    <!-- Le HTML5 shim, for IE6-8 support of HTML elements -->
    <!--[if lt IE 9]>
      <script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.6.1/html5shiv.js" type="text/javascript"></script>
    <![endif]-->

	<?php
	echo '<!-- JQuery -->';
	echo '<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>';
	echo '<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/themes/smoothness/jquery-ui.css" />
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/jquery-ui.min.js"></script>';
	echo '<link href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css" rel="stylesheet">';
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

  <body>
<?php #echo '<pre>';print_r($this->accounts);echo '</pre>';exit(); ?>

    <div class="navbar navbar-default navbar-static-top header" role="navigation">
      <div class="container-fluid">
        <div class="navbar-header">
          <a class="navbar-brand" href="/">TERENA Account Linker</a>
        </div>
        <div class="navbar-collapse collapse">
          <ul class="nav navbar-nav navbar-right">
            <li class="navbar-text">TERENA</li>
          </ul>
        </div>
      </div>
    </div>

    <div class="container">
        
      <div class="row">
		<div class="col-md-12">
		
			<form action="" accept-charset="UTF-8" method="post" class="whatever form-inline" id="c">
			  	<div class="form-group">  
					<label for="tal_id">TAL_ID</label>
					<input type="text" id="tal_id" name="tal_id" class="form-control" />
				</div>
			  	<div class="form-group">  
					<input type="submit" value="Search!" class="btn btn-default" />  	
			  	</div>	
			</form>
				
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<a href="#" class="reset">reset</a>
			<form action="" accept-charset="UTF-8" method="post" class="searchform form-inline" id="c">
			  	<div class="form-group">  
					<label>Show all accounts that logged in to</label>
					<input type="text" name="sp" class="form-control" />
				</div>
				
				<div class="form-group">  
					<label>using IdP</label>
					<input type="text" name="idp" class="form-control" />
				</div>
				<div class="form-group">  
					<label>with attribute value</label>
					<input type="text" name="attr" class="form-control" />
				</div>	
			</form>
		</div>
	</div><!--/row-->
		
	<hr />
	
	<?php if (!empty($this->accounts)): ?>
	<?php foreach ($this->accounts as $key => $account): ?>	
	<div class="panel panel-primary">
	  <div class="panel-heading"><strong>Account ID <?php echo $key . ' (' . $account[0]['entity_name'] . ')' ?></strong></div>
	  		<table class="table table-hover">
	  			<tbody>
	  			<?php foreach ($account as $attr): ?>
	  			<tr>
	    			<th scope="row" class="col-md-3"><?php echo $attr['attribute_name'] ?></th><td><?php echo $attr['attribute_value'] ?></td>
	    		</tr>
				<?php endforeach; ?>
			</tbody>
	 		</table>
	</div>
	<?php endforeach; ?>	
	<?php endif; ?>
	
	
    <footer>
    	<p>A service provided by <a href="https://www.terena.org">TERENA</a></p>
    </footer>

    </div><!-- /container -->


  </body>
</html>