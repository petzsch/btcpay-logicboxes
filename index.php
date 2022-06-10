<?php

// Include autoload file.
require __DIR__ . '/vendor/autoload.php';

// redirect user to setup.php if DB doesn't exist
if (!file_exists(__DIR__ . '/../private/db.sqlite')) {
   header('Location: setup.php');
   exit(0);
}

// Import Invoice client class.
use BTCPayServer\Client\Invoice;
use BTCPayServer\Client\InvoiceCheckoutOptions;
use BTCPayServer\Util\PreciseNumber;

// Fill in with your BTCPay Server data.
$apiKey = 'YOUR_GREENFIELD_API_KEY';
$host = 'https://BTCPAY_URL';
$storeId = 'STORE_ID';

require("functions.php");	//file which has required functions

// TODO replace mariadb with sqlite
$servername = "localhost";
$username = "user";
$password = "password";
$dbname = "DBNAME";
// from the ResellerClub/Resell.biz Paymentgateway settings page
$key = "ENCRYPTION_KEY_LOGICBOXES";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 

// store GET Parameters from ResellerClub in the database
// prepare and bind
$stmt = $conn->prepare("INSERT INTO payments (paymentTypeId, transId, userId, userType, transactionType, invoiceIds, debitNoteIds,
                        description, sellingCurrencyAmount, accountingCurrencyAmount, redirectUrl, checksumOK) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param('isisssssddsi', $paymenttypeid, $transid, $userid, $usertype, $transactiontype, $invoiceids, $debitnoteids, $description,
                                $sellingcurrencyamount, $accountingcurrencyamount, $redirecturl, $checksum_ok);

$_GET = filter_var_array($_GET, FILTER_SANITIZE_STRING);
                                

// set parameters and execute

if (isset($_GET["paymenttypeid"])){
    $paymenttypeid = $_GET["paymenttypeid"];
} else $paymenttypeid = NULL;

if (isset($_GET["transid"])){
    $transid = $_GET["transid"];
} else $transid = NULL;

if (isset($_GET["userid"])) {
    $userid = $_GET["userid"];
} else $userid = NULL;

if (isset($_GET["usertype"])) {
    $usertype = $_GET["usertype"];
} else $usertype = NULL;

if (isset($_GET["transactiontype"])) {
    $transactiontype = $_GET["transactiontype"];
} else $transactiontype = NULL;

if (isset($_GET["invoiceids"])) {
    $invoiceids = $_GET["invoiceids"];
} else $invoiceids = NULL;

if (isset($_GET["debitnoteids"])) {
    $debitnoteids = $_GET["debitnoteids"];
} else $debitnoteids = NULL;

if (isset($_GET["description"])) {
    $description = $_GET["description"];
} else $description = NULL;

if (isset($_GET["sellingcurrencyamount"])) {
    $sellingcurrencyamount = $_GET["sellingcurrencyamount"];
} else $sellingcurrencyamount = NULL;

if (isset($_GET["accountingcurrencyamount"])) {
    $accountingcurrencyamount = $_GET["accountingcurrencyamount"];
} else $accountingcurrencyamount = NULL;

if (isset($_GET["redirecturl"])) {
    $redirecturl = $_GET["redirecturl"];
} else $redirecturl = NULL;

if (isset($_GET["checksum"])) {
    $checksum = $_GET["checksum"];
} else $checksum = NULL;


$checksum_ok = (int)verifyChecksum($paymenttypeid, $transid, $userid, $usertype, $transactiontype, $invoiceids, $debitnoteids, $description, $sellingcurrencyamount, $accountingcurrencyamount, $key, $checksum);

if ($checksum_ok == 0) die("somone passed naughty bits to this script!");

$stmt->execute();

$payments_id = $stmt->insert_id;

// now let's create an invoice with Greenfield API

$client = new Invoice($host, $apiKey);

// resell.biz api user and key
$api_userid = API_USER_ID;
$api_key = 'LB_API_KEY';

// default, will get overwritten with userdata below
$country_code = "de";
$user_email = "";

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
elseif ($usertype == 'reseller') {
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

$metadata = [
   'buyerCountry' => $country_code,
   'itemDesc' => $description,
   'itemCode' => $transid
];

$checkoutOptions = new InvoiceCheckoutOptions();
$checkoutOptions
   ->setSpeedPolicy($checkoutOptions::SPEED_MEDIUM)
   ->setRedirectURL('https://gateway.domorder.com/complete/?invoice_id={InvoiceId}&order_id={OrderId}&country_code=' . $country_code . '&user_email=' . $user_email);

try{
   $invoice = $client->createInvoice(
      $storeId,
      PreciseNumber::parseString($sellingcurrencyamount),
      "USD",
      (string)$payments_id,
      $user_email,
      $metadata,
      $checkoutOptions
   );
} catch (\Throwable $e) {
   echo "Error: " . $e->getMessage();
   exit(1); // don't continue if a error occours
}

// configured store wide in BTCPayServer
//$invoice->setNotificationUrl('https://gateway.domorder.com/btcpay/IPNlogger.php');

$invoice_id = $invoice->getData()['id'];

// record the $invoice->id in the database for later getting the status
$stmt = $conn->prepare("UPDATE payments SET btcpay_id=? WHERE id=?");
$stmt->bind_param('si', $invoice_id, $payments_id);
$stmt->execute();

header("Location: ".$invoice->getData()['checkoutLink']);

$conn->close();
?>
