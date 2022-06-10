<?php
// Include autoload file.
require __DIR__ . '/../vendor/autoload.php';

// Import Invoice client class.
use BTCPayServer\Client\Invoice;

// Fill in with your BTCPay Server data.
$apiKey = 'API_KEY';
$host = 'https://gw.btcpay.host'; // e.g. https://your.btcpay-server.tld
$storeId = 'STORE_ID';

// Get information about a specific invoice.
try {
    $client = new Invoice($host, $apiKey);
    $invoice = $client->getInvoice($storeId, $_GET['invoice_id']);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
    throw $e;
}

$state = $invoice->getData()['status'];

if ($state == "Expired" || $state == "Invalid") {
	header('Location: https://domorder.com/payment_timeout.html');
	exit(0);
}
elseif ($state == "Settled" || $state == "Processing") {
	header('Location: https://domorder.com/payment_success.html?order_id=' . $_GET['order_id'] . '&country_code=' . $_GET['country_code'] . '&user_email=' . $_GET['user_email']);
	exit(0);
}
else {
	echo "unknown invoice status: " . $state;
	exit(0);
}
?>
