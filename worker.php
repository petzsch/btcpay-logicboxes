<?php
// Include autoload file.
require __DIR__ . '/../vendor/autoload.php';

// Import Invoice client class.
use BTCPayServer\Client\Invoice;


 require("functions.php");	//file which has required functions

 $worker = new GearmanWorker();
 $worker->addServer();

 $worker->addFunction("get_ipn", function(GearmanJob $job) {
     // Fill in with your BTCPay Server data.
     $apiKey = 'API_KEY';
     $host = 'https://gw.btcpay.host'; // e.g. https://your.btcpay-server.tld
     $storeId = 'STORE_IID';

     $workload = json_decode($job->workload());
     $servername = "localhost";
     $username = "db_user";
     $password = "db_pass";
     $dbname = "db_name";
     $key = "encryption_key";
     $api_userid = reseller_id;
     $api_key = 'reseller_api_key';
     $server_ip = 'server_ip';

     // Create connection
     $conn = new mysqli($servername, $username, $password, $dbname);

     // Check connection
     if ($conn->connect_error) {
         die("Connection failed: " . $conn->connect_error);
        } 

     // get the invoice from btcpay
     try {
        $client = new Invoice($host, $apiKey);
        $invoice = $client->getInvoice($storeId, $workload->id);
     } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage();
        exit(1); // stop the worker when we start to have connections issues to btcpay
     }


$date = date('m/d/Y h:i:s a', time());
echo $date . " - Invoice incomming: " . $workload->id . "\n";

/**
 * This is where we will fetch the invoice object
 */
$invoiceId = $invoice->getData()['id'];
$invoiceStatus = $invoice->getData()['status'];
$invoicePrice = $invoice->getData()['amount'];



// fetch the invoice from the db
if ($invoiceStatus == "Settled") {
    $status = "Y";
    // fetch the invoice from the db
    $stmt = $conn->prepare("SELECT invoice_status, paymentTypeId, transid, userid, userType, transactionType, invoiceIds, debitNoteIds, description, sellingCurrencyAmount, accountingCurrencyAmount, redirectUrl FROM payments WHERE btcpay_id=?");
    $stmt->bind_param('s', $invoiceId);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $row = $result->fetch_assoc();
    if ($row['invoice_status'] == 'Y') {
       $date = date('m/d/Y h:i:s a', time());
       echo $date . " - Invoice skipped: " . $workload->id . "\n";
       return true;
      }
    $redirectUrl = $row['redirectUrl'];  // redirectUrl received from foundation
    $transId = $row['transid'];		 //Pass the same transid which was passsed to your Gateway URL at the beginning of the transaction.
    $sellingCurrencyAmount = $row['sellingCurrencyAmount'];
    $accountingCurrencyAmount = $row['accountingCurrencyAmount'];
    
    srand((double)microtime()*1000000);
    $rkey = rand();
    
    $checksum = generateChecksum($transId,$sellingCurrencyAmount,$accountingCurrencyAmount,$status, $rkey,$key);
    // get login token
    if ($row['userType'] == 'customer') {
        $curl = curl_init('https://httpapi.com/api/customers/generate-login-token.json?auth-userid=' . $api_userid . '&api-key=' . $api_key . '&customer-id=' . $row['userid'] . '&ip=' . $server_ip);
        curl_setopt($curl, CURLOPT_FAILONERROR, true); 
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); 
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); 
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);   
        $result = curl_exec($curl); // login token

        // do the login
        $cookiefile = "../../private/cookie-". time(). ".txt";


        // Define the URL and the data you want to send
        $loginUrl = 'http://cp.us2.net/servlet/AutoLoginServlet?userLoginId=' . $result . '&role=customer';
        $parseUrl = $row['redirectUrl'] ."?transid=". urlencode($transId) . "&status=" . urlencode($status) . "&rkey=" . urlencode($rkey) . "&checksum=" . urlencode($checksum) . "&sellingamount=" . urlencode($sellingCurrencyAmount) . "&accountingamount=" . urlencode($accountingCurrencyAmount);
        // Now we try to login at the page
        $login = curl_init();

        curl_setopt( $login, CURLOPT_URL, $loginUrl);
        curl_setopt( $login, CURLOPT_COOKIEJAR, $cookiefile);
        curl_setopt( $login, CURLOPT_COOKIEFILE, $cookiefile);
        curl_setopt($login, CURLOPT_FAILONERROR, true); 
        curl_setopt( $login, CURLOPT_MAXREDIRS, 30);
        curl_setopt($login, CURLOPT_FOLLOWLOCATION, true); 
        curl_setopt($login, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($login, CURLOPT_SSL_VERIFYHOST, false); 
        curl_setopt($login, CURLOPT_SSL_VERIFYPEER, false);   
        curl_exec($login);
        curl_close($login);

        // No we download another page while reusing the cookie

        $parse = curl_init();

        curl_setopt( $parse, CURLOPT_URL, $parseUrl);
        curl_setopt( $parse, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt( $parse, CURLOPT_HEADER, 1);
        curl_setopt( $parse, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $parse, CURLOPT_MAXREDIRS, 30);
        curl_setopt( $parse, CURLOPT_COOKIEJAR, $cookiefile);
        curl_setopt( $parse, CURLOPT_COOKIEFILE, $cookiefile);
        curl_setopt( $parse, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt( $parse, CURLOPT_SSL_VERIFYHOST, false);
        $parseResponse = curl_exec( $parse );
        curl_close($parse);
    }
    elseif ($row['userType'] == 'reseller') {
        $curl = curl_init('https://httpapi.com/api/resellers/generate-login-token.json?auth-userid=' . $api_userid . '&api-key=' . $api_key . '&reseller-id=' . $row['userid'] . '&ip=' . $server_ip);
        curl_setopt($curl, CURLOPT_FAILONERROR, true); 
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); 
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); 
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);   
        $result = curl_exec($curl); // login token

        // do the login
        $cookiefile = "../../private/cookie-". time(). ".txt";


        // Define the URL and the data you want to send
        $loginUrl = 'http://cp.us2.net/servlet/AutoLoginServlet?userLoginId=' . $result . '&role=reseller';
        $parseUrl = $row['redirectUrl'] ."?transid=". urlencode($transId) . "&status=" . urlencode($status) . "&rkey=" . urlencode($rkey) . "&checksum=" . urlencode($checksum) . "&sellingamount=" . urlencode($sellingCurrencyAmount) . "&accountingamount=" . urlencode($accountingCurrencyAmount);
        // Now we try to login at the page
        $login = curl_init();

        curl_setopt( $login, CURLOPT_URL, $loginUrl);
        curl_setopt( $login, CURLOPT_COOKIEJAR, $cookiefile);
        curl_setopt( $login, CURLOPT_COOKIEFILE, $cookiefile);
        curl_setopt($login, CURLOPT_FAILONERROR, true); 
        curl_setopt($login, CURLOPT_FOLLOWLOCATION, true); 
        curl_setopt( $login, CURLOPT_MAXREDIRS, 30);
        curl_setopt($login, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($login, CURLOPT_SSL_VERIFYHOST, false); 
        curl_setopt($login, CURLOPT_SSL_VERIFYPEER, false);   
        curl_exec($login);
        curl_close($login);

        // No we download another page while reusing the cookie

        $parse = curl_init();

        curl_setopt( $parse, CURLOPT_URL, $parseUrl);
        curl_setopt( $parse, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt( $parse, CURLOPT_HEADER, 1);
        curl_setopt( $parse, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $parse, CURLOPT_MAXREDIRS, 30);
        curl_setopt( $parse, CURLOPT_COOKIEJAR, $cookiefile);
        curl_setopt( $parse, CURLOPT_COOKIEFILE, $cookiefile);
        curl_setopt( $parse, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt( $parse, CURLOPT_SSL_VERIFYHOST, false);
        $parseResponse = curl_exec( $parse );
        curl_close($parse);
    }
    else die("unknown userType");
    
    
}
else if ($invoiceStatus == "Expired") {
    $status = "N";
    // fetch the invoice from the db
    $stmt = $conn->prepare("SELECT invoice_status, paymentTypeId, transid, userid, userType, transactionType, invoiceIds, debitNoteIds, description, sellingCurrencyAmount, accountingCurrencyAmount, redirectUrl FROM payments WHERE btcpay_id=?");
    $stmt->bind_param('s', $invoiceId);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $row = $result->fetch_assoc();
    
    $redirectUrl = $row['redirectUrl'];  // redirectUrl received from foundation
    $transId = $row['transid'];		 //Pass the same transid which was passsed to your Gateway URL at the beginning of the transaction.
    $sellingCurrencyAmount = $row['sellingCurrencyAmount'];
    $accountingCurrencyAmount = $row['accountingCurrencyAmount'];
    
    srand((double)microtime()*1000000);
    $rkey = rand();
    
    $checksum = generateChecksum($transId,$sellingCurrencyAmount,$accountingCurrencyAmount,$status, $rkey,$key);
    if ($row['userType'] == 'customer') {
        $curl = curl_init('https://httpapi.com/api/customers/generate-login-token.json?auth-userid=' . $api_userid . '&api-key=' . $api_key . '&customer-id=' . $row['userid'] . '&ip=' . $server_ip);
        curl_setopt($curl, CURLOPT_FAILONERROR, true); 
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); 
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); 
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);   
        $result = curl_exec($curl); // login token

        // do the login
        $cookiefile = "../../private/cookie-". time(). ".txt";


        // Define the URL and the data you want to send
        $loginUrl = 'http://cp.us2.net/servlet/AutoLoginServlet?userLoginId=' . $result . '&role=customer';
        $parseUrl = $row['redirectUrl'] ."?transid=". urlencode($transId) . "&status=" . urlencode($status) . "&rkey=" . urlencode($rkey) . "&checksum=" . urlencode($checksum) . "&sellingamount=" . urlencode($sellingCurrencyAmount) . "&accountingamount=" . urlencode($accountingCurrencyAmount);
        // Now we try to login at the page
        $login = curl_init();

        curl_setopt( $login, CURLOPT_URL, $loginUrl);
        curl_setopt( $login, CURLOPT_COOKIEJAR, $cookiefile);
        curl_setopt( $login, CURLOPT_COOKIEFILE, $cookiefile);
        curl_setopt($login, CURLOPT_FAILONERROR, true); 
        curl_setopt($login, CURLOPT_FOLLOWLOCATION, true); 
        curl_setopt( $login, CURLOPT_MAXREDIRS, 30);
        curl_setopt($login, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($login, CURLOPT_SSL_VERIFYHOST, false); 
        curl_setopt($login, CURLOPT_SSL_VERIFYPEER, false);   
        curl_exec($login);
        curl_close($login);

        // No we download another page while reusing the cookie

        $parse = curl_init();

        curl_setopt( $parse, CURLOPT_URL, $parseUrl);
        curl_setopt( $parse, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt( $parse, CURLOPT_HEADER, 1);
        curl_setopt( $parse, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $parse, CURLOPT_MAXREDIRS, 30);
        curl_setopt( $parse, CURLOPT_COOKIEJAR, $cookiefile);
        curl_setopt( $parse, CURLOPT_COOKIEFILE, $cookiefile);
        curl_setopt( $parse, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt( $parse, CURLOPT_SSL_VERIFYHOST, false);
        $parseResponse = curl_exec( $parse );
        curl_close($parse);
    }
    elseif ($row['userType'] == 'reseller') {
        $curl = curl_init('https://httpapi.com/api/resellers/generate-login-token.json?auth-userid=' . $api_userid . '&api-key=' . $api_key . '&reseller-id=' . $row['userid'] . '&ip=' . $server_ip);
        curl_setopt($curl, CURLOPT_FAILONERROR, true); 
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); 
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); 
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);   
        $result = curl_exec($curl); // login token

        // do the login
        $cookiefile = "../../private/cookie-". time(). ".txt";


        // Define the URL and the data you want to send
        $loginUrl = 'http://cp.us2.net/servlet/AutoLoginServlet?userLoginId=' . $result . '&role=reseller';
        $parseUrl = $row['redirectUrl'] ."?transid=". urlencode($transId) . "&status=" . urlencode($status) . "&rkey=" . urlencode($rkey) . "&checksum=" . urlencode($checksum) . "&sellingamount=" . urlencode($sellingCurrencyAmount) . "&accountingamount=" . urlencode($accountingCurrencyAmount);
        // Now we try to login at the page
        $login = curl_init();

        curl_setopt( $login, CURLOPT_URL, $loginUrl);
        curl_setopt( $login, CURLOPT_COOKIEJAR, $cookiefile);
        curl_setopt( $login, CURLOPT_COOKIEFILE, $cookiefile);
        curl_setopt($login, CURLOPT_FAILONERROR, true); 
        curl_setopt($login, CURLOPT_FOLLOWLOCATION, true); 
        curl_setopt( $login, CURLOPT_MAXREDIRS, 30);
        curl_setopt($login, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($login, CURLOPT_SSL_VERIFYHOST, false); 
        curl_setopt($login, CURLOPT_SSL_VERIFYPEER, false);   
        curl_exec($login);
        curl_close($login);

        // No we download another page while reusing the cookie

        $parse = curl_init();

        curl_setopt( $parse, CURLOPT_URL, $parseUrl);
        curl_setopt( $parse, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt( $parse, CURLOPT_HEADER, 1);
        curl_setopt( $parse, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $parse, CURLOPT_MAXREDIRS, 30);
        curl_setopt( $parse, CURLOPT_COOKIEJAR, $cookiefile);
        curl_setopt( $parse, CURLOPT_COOKIEFILE, $cookiefile);
        curl_setopt( $parse, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt( $parse, CURLOPT_SSL_VERIFYHOST, false);
        $parseResponse = curl_exec( $parse );
        curl_close($parse);
    }
    else die("unknown userType");
} 
else $status = "P";

$stmt = $conn->prepare("UPDATE payments SET invoice_status=? WHERE btcpay_id=?");
$stmt->bind_param('ss', $status, $invoiceId);
$stmt->execute();
$date = date('m/d/Y h:i:s a', time());
echo $date . " - Invoice processed: " . $workload->id . "\n";
 });

 while ($worker->work());


?>
