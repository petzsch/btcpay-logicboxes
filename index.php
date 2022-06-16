<?php
/**
 * @var string $btcpay_url
 * @var string $storeid
 * @var string $apikey
 * @var string $lbapikey
 * @var string $resellerid
 * @var string $checksum_secret
 * @var string $reseller_base_url
 */
// Include autoload file.
require __DIR__ . '/vendor/autoload.php';

// redirect user to setup.php if DB doesn't exist
if (!file_exists(__DIR__ . '/private/config.inc.php')) {
   header('Location: setup.php');
   exit(0);
}

// Import Invoice client class.
use BTCPayServer\Client\Invoice;
use BTCPayServer\Client\InvoiceCheckoutOptions;
use BTCPayServer\Util\PreciseNumber;

require __DIR__ . '/private/config.inc.php';

require("functions.php");	//file which has required functions

$_GET = filter_var_array($_GET, FILTER_SANITIZE_STRING);

// set parameters and check checksum

$paymenttypeid = isset($_GET["paymenttypeid"]) ? $_GET["paymenttypeid"] : NULL;
$transid = isset($_GET["transid"]) ? $_GET["transid"] : NULL;
$userid = isset($_GET["userid"]) ? $_GET["userid"] : NULL;
$usertype = isset($_GET["usertype"]) ? $_GET["usertype"] : NULL;
$transactiontype = isset($_GET["transactiontype"]) ? $_GET["transactiontype"] : NULL;
$invoiceids = isset($_GET["invoiceids"]) ? $_GET["invoiceids"] : NULL;
$debitnoteids = isset($_GET["debitnoteids"]) ? $_GET["debitnoteids"] : NULL;
$description = isset($_GET["description"]) ? $_GET["description"] : NULL;
$sellingcurrencyamount = isset($_GET["sellingcurrencyamount"]) ? $_GET["sellingcurrencyamount"] : NULL;
$accountingcurrencyamount = isset($_GET["accountingcurrencyamount"]) ? $_GET["accountingcurrencyamount"] : NULL;
$redirecturl = isset($_GET["redirecturl"]) ? $_GET["redirecturl"] : NULL;
$checksum = isset($_GET["checksum"]) ? $_GET["checksum"] : NULL;

$checksum_ok = (int)verifyChecksum($paymenttypeid, $transid, $userid, $usertype, $transactiontype, $invoiceids, $debitnoteids, $description, $sellingcurrencyamount, $accountingcurrencyamount, $checksum_secret, $checksum);
if ($checksum_ok == 0) die("somone passed naughty bits to this script!");


// now let's create an invoice with Greenfield API

$client = new Invoice($btcpay_url, $apikey);

// resell.biz api user and key
$api_userid = $resellerid;
$api_key = $lbapikey;


//get country code from reseller or user
if ($usertype == 'customer') {
	$curl = curl_init('https://httpapi.com/api/customers/details-by-id.json?auth-userid=' . $api_userid . '&api-key=' . $api_key . '&customer-id=' . $userid);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $result = json_decode(curl_exec($curl));
	$country_code = $result->country;
	$user_email = $result->useremail;

}
else{
	$curl = curl_init('https://httpapi.com/api/resellers/details.json?auth-userid=' . $api_userid . '&api-key=' . $api_key . '&reseller-id=' . $userid);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $result = json_decode(curl_exec($curl));
        $country_code = $result->country;
        $user_email = $result->username;
}

srand((double)microtime()*1000000);
$rkey = rand();

$checksum_n = generateChecksum($transid,$sellingcurrencyamount,$accountingcurrencyamount,'N',$rkey,$checksum_secret);
$checksum_y = generateChecksum($transid,$sellingcurrencyamount,$accountingcurrencyamount,'Y',$rkey,$checksum_secret);
$checksum_p = generateChecksum($transid,$sellingcurrencyamount,$accountingcurrencyamount,'P',$rkey,$checksum_secret);


$link_array = array(
    'n_url' => $parseUrl = $redirecturl ."?transid=". urlencode($transid) . "&status=" . urlencode('N') . "&rkey=" . urlencode($rkey) . "&checksum=" . urlencode($checksum_n) . "&sellingamount=" . urlencode($sellingcurrencyamount) . "&accountingamount=" . urlencode($accountingcurrencyamount),
    'y_url' => $parseUrl = $redirecturl ."?transid=". urlencode($transid) . "&status=" . urlencode('Y') . "&rkey=" . urlencode($rkey) . "&checksum=" . urlencode($checksum_y) . "&sellingamount=" . urlencode($sellingcurrencyamount) . "&accountingamount=" . urlencode($accountingcurrencyamount),
    'p_url' => $parseUrl = $redirecturl ."?transid=". urlencode($transid) . "&status=" . urlencode('P') . "&rkey=" . urlencode($rkey) . "&checksum=" . urlencode($checksum_p) . "&sellingamount=" . urlencode($sellingcurrencyamount) . "&accountingamount=" . urlencode($accountingcurrencyamount)
);

$metadata = [
    'buyerCountry' => $country_code,
    'itemDesc' => $description,
    'itemCode' => $transid,
    'posData' => json_encode($link_array)
];

$checkoutOptions = new InvoiceCheckoutOptions();
$checkoutOptions
   ->setSpeedPolicy($checkoutOptions::SPEED_MEDIUM)
   ->setRedirectURL('https://' . $_SERVER['HTTP_HOST'] . '/returnurl.php?invoice_id={InvoiceId}');

try{
   $invoice = $client->createInvoice(
      $storeid,
       "USD",
      PreciseNumber::parseString($sellingcurrencyamount),
      (string)$transid,
      $user_email,
      $metadata,
      $checkoutOptions
   );
} catch (\Throwable $e) {
   echo "Error: " . $e->getMessage();
   exit(1); // don't continue if a error occours
}

header("Location: ".$invoice->getData()['checkoutLink']);

?>
