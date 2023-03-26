# btcpay-logicboxes

## Howto Run

1. Copy project onto a Webserver with PHP 8.0+ support (make sure it runs under https://)
2. run `composer install` in the docroot of the project
3. Create a custom payment gateway in your reseller control panel as described here: https://manage.resellerclub.com/kb/servlet/KBServlet/faq411.html#heading_1
4. Create a Store and API Key in BTCPay as you would with any integration.
5. open the location of the script in a browser and fill out the "ugly" setup page.
6. You should be ready to create a testorder and see it action

## Known Limitations

* Payment processing only works when the customer stays on the page and is sent back to LogicBoxes platform through the return_url.
You will get emails from LogicBoxes about pending orders that are waiting for loger then one day.
* Also the automatic processing will not work with **on-chain** transactions: if you do configure a Bitcoin Wallet in your store, you will have to approve such payments manually.
* LogicBoxes Sessions expire after 15 minutes: It's advisable to set invoice expiration time to something smaller then that

## ToDo:
- Send an email when an onchain transaction confirms (i.e. through a btcpay webhook)
