<?php
include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/payu.php');
include(dirname(__FILE__).'/payu.scls.php');

$payu = new payu();

$option  = array( 	'merchant' => $payu->Payu_getVar("merchant"), 
					'secretkey' => $payu->Payu_getVar("secret_key"), 
					'debug' => $payu->Payu_getVar("debug_mode") );

$payansewer = PayuCLS::getInst()->setOptions( $option )->IPN();
echo $payansewer;

$ord = explode( "_", $_POST["REFNOEXT"]);
$extraVars = "";
$order = new Order(intval($ord[0]));

if (!Validate::isLoadedObject($order) OR !$order->id)
	die('Invalid order');

if (!$amount = floatval(Tools::getValue('IPN_TOTALGENERAL')) OR $amount != $order->total_paid)
	die($amount.' != '. $order->total_paid.' Incorrect amount');

$id_order_state = _PS_OS_PAYMENT_;

$history = new OrderHistory();
$history->id_order = intval($order->id);
$history->changeIdOrderState(intval($id_order_state), intval($order->id));
$history->addWithemail(true, $extraVars);
