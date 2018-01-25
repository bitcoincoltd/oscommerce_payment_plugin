Project: CoinPay.in.th Merchant Account
Module: osCommerce 2.3 Module Payment
Author: info@coinpay.in.th

Installation:
1) Create a merchant account at https://coinpay.in.th
2) Copy all contents of the /coinpay_osc2.3/ folder to your root osCommerce directory
3) Go to osCommerce Admin -> Modules -> Payment -> Install Module -> Click on "BX CoinPay" module and "Install"
4) Go to admin/includes/template_top.php and add following line right after jquery
<script type="text/javascript" src="<?php echo tep_catalog_href_link('includes/modules/payment/bitcointhai/js/bitcointhai.js', '', 'SSL'); ?>"></script>
5) "Edit" the Bitcoin module and enter your API Key and API Secret from coinpay.in.th

Tested with osCommerce Online Merchant v2.3.4.1

Zip File Structure
includes
includes/languages
includes/languages/english
includes/languages/english/modules
includes/languages/english/modules/payment
includes/languages/english/modules/payment/bitcointhai.php
includes/languages/thai
includes/languages/thai/modules
includes/languages/thai/modules/payment
includes/languages/thai/modules/payment/bitcointhai.php
includes/modules
includes/modules/payment
includes/modules/payment/bitcointhai.php
includes/modules/payment/bitcointhai
includes/modules/payment/bitcointhai/bitcointhai_api_client.php
includes/modules/payment/bitcointhai/bitcointhai_api_request.php
includes/modules/payment/bitcointhai/bitcointhai_signature.php
includes/modules/payment/bitcointhai/helpers.php
includes/modules/payment/bitcointhai/payment_details_request.php
includes/modules/payment/bitcointhai/payment_details_response.php
includes/modules/payment/bitcointhai/payment_fields.php
includes/modules/payment/bitcointhai/unconfirmed_amount_response.php
includes/modules/payment/bitcointhai/js
includes/modules/payment/bitcointhai/js/bitcointhai.js
