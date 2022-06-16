<?php
if(file_exists(__DIR__ . '/private/config.inc.php')) {
    die("config allready written!");
}
?>
<html>
<head>
<title>Ugly Logicboxes Bridge Setup Script</title>
</head>
<body>
We need some bits of configuration to work. Just fill in these fields and you<br>
can start accepting BTCPay in your Logicboxes system in no time.<br><br>

<form action="setup.php" method="POST">
    <input type="hidden" name="posted" value="true">
    BTCPay URL: <input name="btcpay_url" type="url"></br>
    BTCPay Store-ID: <input name="storeid" type="text"><br>
    BTCPay API-Key: <input name="apikey" type="text"><br>
    Logicboxes API-Key: <input name="lbapikey" type="text"><br>
    Logicboxes Reseller-ID: <input name="resellerid" type="text"><br>
    Logicboxes checksum secret: <input name="checksum_secret" type="text"><br>
    Logicboxes reseller control panel: <input type="url" name="reseller_base_url" value="https://manage.resellerclub.com"><br>
    <input type="submit" value="save">
</form>

<?php
if(isset($_POST['posted'])) {
    $content = "<?php
\$btcpay_url = \"" . $_POST['btcpay_url'] . "\";
\$storeid = \"" . $_POST['storeid'] . "\";
\$apikey = \"" . $_POST['apikey'] . "\";
\$lbapikey = \"" . $_POST['lbapikey'] . "\";
\$resellerid = \"" . $_POST['resellerid'] . "\";
\$checksum_secret = \"" . $_POST['checksum_secret'] . "\";
\$reseller_base_url = \"" . $_POST['reseller_base_url'] . "\";";
    file_put_contents(__DIR__ . '/private/config.inc.php', $content);
    echo "<b>config file written!</b></body></html>";
} else{
    echo "</body></html>";
}

?>