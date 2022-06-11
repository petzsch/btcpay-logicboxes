# btcpay-logicboxes

## TODO

1. Write documentation
2. Write a setup.php script that populates the config.inc.php file in private folder with all needed configuration
3. Remove:
   * Gearman
   * curl user session stuff
   * the need for a database: we can store return URLS in posData of the invoice and do the final redirect at the returnurl.php script
4. Add:
   * Webhook sends an E-Mail through SMTP (i.e. with PHPMailer lib) for onchain stuff
   * redirect the user back to LogicBoxes platform after payment complted (pending or settled or failed urls need to be generated upon invoice creation and stored in posData
5. License the Code
6. Ship it. :)
