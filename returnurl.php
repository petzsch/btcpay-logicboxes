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

if (!isset($_GET['invoice_id'])) die("We need an invoice_id!");

require __DIR__ . '/private/config.inc.php';

// Import Invoice client class.
use BTCPayServer\Client\Invoice;

// Get information about a specific invoice.
try {
    $client = new Invoice($btcpay_url, $apikey);
    $invoice = $client->getInvoice($storeid, $_GET['invoice_id']);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
    exit(1);
}

$state = $invoice->getData()['status'];
$return_urls = $invoice->getData()['metadata']['posData'];

if ($state == "Expired" || $state == "Invalid") {
	header('Location: ' . $return_urls['n_url']);
	exit(0);
}
elseif ($state == "Settled") {
	header('Location: ' . $return_urls['y_url']);
	exit(0);
}
elseif ($state == "Processing") {
	header('Location: ' . $return_urls['p_url']);
	exit(0);
}
else {
	echo "unknown invoice status: " . $state;
	exit(0);
}
?>
