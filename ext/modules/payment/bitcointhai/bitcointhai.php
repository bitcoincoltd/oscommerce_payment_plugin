<?php
// CoinPay.in.th IPN module

chdir('../../../..');
include('includes/application_top.php');
include_once(DIR_WS_MODULES.'/payment/bitcointhai/bitcointhai_api_client.php');

/**
 * Geting response from POST
 * @array
 */
$response = json_decode(file_get_contents('php://input'),true);

/**
 * Find bx ID to include API Client
 */
$api_query = tep_db_query("SELECT configuration_value FROM ".TABLE_CONFIGURATION." WHERE configuration_key='MODULE_PAYMENT_BITCOINTHAI_API_ID'");
$api_rec = tep_db_fetch_array($api_query);
$api_id = $api_rec['configuration_value'];
$api = new BitcointhaiApiClient($api_id);

	//$query = tep_db_query("SELECT orders_id, orders_status FROM ".TABLE_ORDERS." WHERE orders_id='".tep_db_input($response['order_id'])."'");
//$rec = tep_db_fetch_array($query);

if( !$api->validIPN($response) ){
  header("HTTP/1.0 403 Forbiden");
  print_r( "IPN Failed. Signature invalid." );
  exit();
}

if($ipn = $api->validIPN($response)){
	$query = tep_db_query("SELECT orders_id, orders_status FROM ".TABLE_ORDERS." WHERE orders_id='".tep_db_input($response['order_id'])."'");
	if($rec = tep_db_fetch_array($query)){
		$order_status = ($response['confirmed_in_full'] == true ? MODULE_PAYMENT_BITCOINTHAI_CONFIRMED_STATUS_ID : $rec['orders_status']);
		if($order_status == 0){
			$order_status = DEFAULT_ORDERS_STATUS_ID;
		}

		tep_db_query("UPDATE ".TABLE_ORDERS." SET orders_status='".$order_status."' WHERE orders_id='".$rec['orders_id']."'");

		$sql_data_array = array('orders_id' => $rec['orders_id'],
							    'orders_status_id' => $order_status,
							    'date_added' => 'now()',
							    'customer_notified' => '0',
							    'comments' => '[BX CoinPay IPN: '.$response['message'].']');

		tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
	}
}else{
	header("HTTP/1.0 403 Forbidden");
	print_r('IPN Failed');
}
?>
