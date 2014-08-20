<?php
$this->includeAtTemplateBase('includes/header.php');
?>
<h1>Hey there #<?php echo $this->accountId; ?> (<?php echo $this->idp; ?>)</h1>
<p>Would you like to link your account?</p>
<form action="<?php echo htmlspecialchars($this->data['formAction']); ?>" accept-charset="UTF-8" method="post">
<ul>
<?php
// Embed hidden fields...
foreach ($this->data['yesData'] as $name => $value) {
    echo '<li><input type="hidden" name="' . htmlspecialchars($name) .
        '" value="' . htmlspecialchars($value) . '" /></li>';
}
?>
<li><input type="submit" value="Yes" name="submit"></li>
<li><input type="submit" value="No" name="submit"></li>
</ul>
</form>

<?php
$this->includeAtTemplateBase('includes/footer.php');
?>