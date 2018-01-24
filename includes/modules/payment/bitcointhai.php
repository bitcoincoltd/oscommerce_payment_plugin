<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

//include_once(DIR_WS_MODULES.'payment/bitcointhai/bitcointhai_api_client.php');
$dir = dirname(__FILE__);
include_once($dir.'/bitcointhai/bitcointhai_api_client.php');

class Bitcointhai {
  var $code, $title, $description, $enabled, $api, $ipn, $order_id;

  //class constructor
  function bitcointhai() {
      global $order;

      $this->code = 'bitcointhai';
      $this->api_id = MODULE_PAYMENT_BITCOINTHAI_API_ID;
      $this->title = MODULE_PAYMENT_BITCOINTHAI_TEXT_TITLE;
      $this->description = MODULE_PAYMENT_BITCOINTHAI_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_PAYMENT_BITCOINTHAI_SORT_ORDER;
      $this->enabled = ((MODULE_PAYMENT_BITCOINTHAI_STATUS == 'True') ? true : false);
      $this->callback = tep_href_link('ext/modules/payment/bitcointhai/bitcointhai.php', '', 'SSL', false, false);
      // LINK?? tep_href_link('ext/modules/payment/bitcointhai/bitcointhai.php', '', 'SSL', false, false));

  		$this->api = new BitcointhaiApiClient($this->api_id);

      if ((int)MODULE_PAYMENT_BITCOINTHAI_ORDER_STATUS_ID > 0) {
        $this->order_status = MODULE_PAYMENT_BITCOINTHAI_ORDER_STATUS_ID;
      }

      if (is_object($order)) $this->update_status();
  }

    // class methods
    function update_status() {
      global $order;

      if ( ($this->enabled == true) && ((int)MODULE_PAYMENT_BITCOINTHAI_ZONE > 0) ) {
        $check_flag = false;
        $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_BITCOINTHAI_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
        while ($check = tep_db_fetch_array($check_query)) {
          if ($check['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check['zone_id'] == $order->delivery['zone_id']) {
            $check_flag = true;
            break;
          }
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }
    }

    function javascript_validation() {
      return false;
    }

    function selection() {
      if( isset($_GET['order_id']) ) {
        $_SESSION['order_id'] = $_GET['order_id'];
      }
      return array(
        'id' => $this->code,
        'module' => $this->title
      );
    }

    function pre_confirmation_check() {
      return false;
    }

  function confirmation() {
		global $order;

    $request = new PaymentDetailsRequest(
      $this->callback,
      $order->info['total'],
      $order->info['currency'],
      "Payment for order on ". STORE_NAME
    );

    if( $this->paymentDetailsMustBeRefreshed($request)) {
      $payment_details = $this->api->getPaymentDetails($request);
      $_SESSION['payment_details'] = $payment_details;
      $_SESSION['payment_details_hash'] = $request->hash();
    } else {
      $payment_details = $_SESSION['payment_details'];
    }

    if( !$payment_details ) {
      $this->getPaymentDetailsFailed();
      return;
    }

    // Loop throuh all addresses
    $addresses_arr = [];
    foreach($payment_details as $key => $value) {
      foreach($value as $key => $item) {
        array_push($addresses_arr, $item->address);
      }
    }
    $_SESSION['bx_payment_addresses'] = $addresses_arr;

    include_once('bitcointhai/payment_fields.php');
    echo "<input type='hidden' name='order_id' value='".(int)$this->order_id ."'>";
  }

  function process_button() {
    return false;
  }

  function before_process() {
    global $order;
    $bx_payment_addresses = $_SESSION['bx_payment_addresses'];

    $result = $this->api->checkPaymentReceived(
      $bx_payment_addresses,
      $insert_id
    );

    //$comment = "addresses to pay: 4lkahdsflhaodifh98asdfo2";
    //$this->order_comment_update($insert_id, $order->info['order_status'],$comment);


    if( !$result ) {
      $title_error = 'Payment error! '. $result->error;
      //$this->order_remove($insert_id, false);
      tep_redirect(str_replace('amp;','',tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code . '&error=' . urlencode($title_error), 'SSL')));
    }

    if( $result->payment_received === false ) {
      // $this->order_remove($insert_id, false);
      $title_error = 'Did you already pay it? We still did not see you payment!<br>It can take a few seconds for your payment to appear. If you already paid - press button again.';
      tep_redirect(str_replace('amp;','',tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code . '&error=' . urlencode($title_error), 'SSL')));
    }

    // Add result into session so after order create can add comment not enough
    $_SESSION['payment_received'] = $result;
    return true;
  }

  function after_process() {
		global $insert_id, $order;

    // Check if order_id already in session
    if( isset($_SESSION['order_id']) ) {
      $order_id = $_SESSION['order_id'];
      $this->order_remove($insert_id, false);
    }else{
      $order_id = $insert_id;
      // add comment expecting amount, address, type
      $this->order_comment_update($order_id, $order->info['order_status'], $this->expected_amount());
    }

    $this->not_enough_error($order_id, $order->info['order_status']);

    // send save_order_id request here
    $order_saved = $this->api->saveOrderId(
      $_SESSION['bx_payment_addresses'],
      $order_id
    );

    if( $order_saved->success === false ) {
      $error = "Something went wrong! Order ID can't be saved: ".$order_saved->error;
      tep_redirect(str_replace('amp;','',tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error='. $this->code . '&error='. urlencode($error).'&order_id='.$order_id, 'SSL')));
    }

    $awaiting_confirmation = "[Coinpay: Payment awaiting confirmation] ".$this->payment_received();
    $this->order_comment_update($order_id, $order->info['order_status'], $awaiting_confirmation);

    // unset all sessions
    unset($_SESSION['bx_payment_addresses']);
    unset($_SESSION['payment_details_hash']);
    unset($_SESSION['payment_details']);
    unset($_SESSION['order_id']);
    unset($_SESSION['payment_received']);
    return false;
  }

  function get_error() {
    return array('title' => MODULE_PAYMENT_BITCOINTHAI_TITLE_ERROR,
                   'error' => urldecode($_GET['error']));
  }

    function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_BITCOINTHAI_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }

    function install() {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Cash On Delivery Module', 'MODULE_PAYMENT_BITCOINTHAI_STATUS', 'True', 'Do you want to accept BX CoinPay payments?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_BITCOINTHAI_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('API Access ID', 'MODULE_PAYMENT_BITCOINTHAI_API_ID', '0', 'API Access ID from https://coinpay.in.th/', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('List of Currencies', 'MODULE_PAYMENT_BITCOINTHAI_LIST_CURRENCIES', 'BTC, BCH,LTC', 'Example: BTC, BCH, DAS, DOG, LTC', '6', '0', now())");
      //tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('API Access KEY', 'MODULE_PAYMENT_BITCOINTHAI_API_KEY', '0', 'API Access Key from https://coinpay.in.th/', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_BITCOINTHAI_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Before Confirmation Order Status', 'MODULE_PAYMENT_BITCOINTHAI_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('After Confirmation Order Status', 'MODULE_PAYMENT_BITCOINTHAI_CONFIRMED_STATUS_ID', '0', 'Set the status of orders after payment confirmation is received', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
   }

    function remove() {
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array('MODULE_PAYMENT_BITCOINTHAI_STATUS', 'MODULE_PAYMENT_BITCOINTHAI_ZONE', 'MODULE_PAYMENT_BITCOINTHAI_API_ID','MODULE_PAYMENT_BITCOINTHAI_LIST_CURRENCIES','MODULE_PAYMENT_BITCOINTHAI_ORDER_STATUS_ID', 'MODULE_PAYMENT_BITCOINTHAI_SORT_ORDER','MODULE_PAYMENT_BITCOINTHAI_CONFIRMED_STATUS_ID');
    }

    public function check_ipn_response()
    {
      $response = json_decode(file_get_contents('php://input'),true);
      print_r( $response );
      die();
    }

    protected function paymentDetailsMustBeRefreshed($request)
    {
      // Hash vill change if cart has changes significant to payment
      return $_SESSION['payment_details_hash'] != $request->hash() OR !$_SESSION['payment_details'];
    }

    protected function getPaymentDetailsFailed()
    {
      echo MODULE_PAYMENT_BITCOINTHAI_TEXT_ERROR;
    }

   // Original in admin/includes/functions/general.php
   protected function order_remove($order_id, $restock = false) {
    if ($restock == true) {
      $order_query = tep_db_query("select products_id, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int)$order_id . "'");
      while ($order = tep_db_fetch_array($order_query)) {
        tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = products_quantity + " . $order['products_quantity'] . ", products_ordered = products_ordered - " . $order['products_quantity'] . " where products_id = '" . (int)$order['products_id'] . "'");
      }
    }

    tep_db_query("delete from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");
    tep_db_query("delete from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int)$order_id . "'");
    tep_db_query("delete from " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " where orders_id = '" . (int)$order_id . "'");
    tep_db_query("delete from " . TABLE_ORDERS_STATUS_HISTORY . " where orders_id = '" . (int)$order_id . "'");
    tep_db_query("delete from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . (int)$order_id . "'");
   }

   protected function order_comment_update($id, $status, $comment)
   {
     tep_db_query("
       insert into ".TABLE_ORDERS_STATUS_HISTORY."
       (orders_id, orders_status_id, date_added, customer_notified, comments)
       values(
         ".(int)$id.",
         ".$status.",
         now(),
         0,
         '".$comment."'
       )
      ");
   }

   // Return @string
   protected function expected_amount()
   {
    $str = 'Expecting ';
    if( isset($_SESSION['payment_details']) ) {
      foreach( $_SESSION['payment_details']->addresses as $key => $value ) {
        if( $value->available ) {
          $str .= " {$value->amount} in {$key} to {$value->address}; ";
        }
      }
    }
    return $str;
   }

   // return @void
   protected function not_enough_error($order_id,$order_status)
   {
    if( $_SESSION['payment_received']->is_enough === false ) {
      $str = '';
      foreach($_SESSION['payment_received']->paid as $key => $value) {
        $str .= " {$value->amount} in {$value->cryptocurrency}; ";
      }
      $title_error = 'Payment amount is not enough. Got: '.$str;
      $this->order_comment_update($order_id, $order_status, $title_error);
      tep_redirect(str_replace('amp;','',tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error='. $this->code . '&error='. urlencode($title_error).'&order_id='.$order_id, 'SSL')));
    }
   }

   // Return @string
   protected function payment_received()
   {
     $str = 'Paid: ';
     $paid = $_SESSION['payment_received']->paid_by;
     if( isset($paid)  ) {
       $str .= " {$paid->amount} in {$paid->name} ({$paid->ticker}) to {$paid->address} proof link: {$paid->proof_link}; ";
     }
     return addslashes($str);
   }

}
?>
