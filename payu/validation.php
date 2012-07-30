<?php
include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../header.php');
include(dirname(__FILE__).'/payu.php');
include(dirname(__FILE__).'/payu.scls.php');

$payu = new payu();

if ($cart->id_customer == 0 OR $cart->id_address_delivery == 0 OR $cart->id_address_invoice == 0 OR !$payu->active)
	Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');

$authorized = false;
foreach (Module::getPaymentModules() as $module)
	if ($module['name'] == 'payu')
	{
		$authorized = true;
		break;
	}

if (!$authorized)
	die(Tools::displayError('This payment method is not available.'));
	
$customer = new Customer((int)$cart->id_customer);

if (!Validate::isLoadedObject($customer))
	Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');

$currency = new Currency($cookie->id_currency);
$total = (float)$cart->getOrderTotal(true, Cart::BOTH);

$button = "<div style='position:absolute; top:50%; left:50%; margin:-40px 0px 0px -60px; '>".
		  "<div><img src='./img/payu.png' width='120px' style='margin:0px 5px;'></div>".
		  "<div><img src='./img/loader.gif' width='120px' style='margin:5px 5px;'></div>".
		  "</div>".
		  "<script>
		  	setTimeout( subform, 5000 );
		  	function subform(){ document.getElementById('PayUForm').submit(); }
		  </script>";


$option  = array( 	'merchant' => $payu->Payu_getVar("merchant"), 
					'secretkey' => $payu->Payu_getVar("secret_key"), 
					'debug' => $payu->Payu_getVar("debug_mode"),
					'button' => $button );

if ( $payu->Payu_getVar("system_url") != '' ) $option['luUrl'] = $payu->Payu_getVar("system_url");

$forSend = array();

foreach ( $cart->getProducts() as $item )
{	
	$price = round( $item['price'], 3 );
	if ( $item['price'] > $price ) $price += 0.001;

	$forSend['ORDER_PNAME'][] = $item['name'];
	$forSend['ORDER_PCODE'][] = $item['id_product'];
	$forSend['ORDER_PINFO'][] = $item['description_short'];
	$forSend['ORDER_PRICE'][] = $price;
	$forSend['ORDER_QTY'][] = $item['quantity'];
	$forSend['ORDER_VAT'][] = $item['rate'];
	
}

if ( $payu->Payu_getVar("back_ref") != '' ) $forSend['BACK_REF'] = $payu->Payu_getVar("back_ref");

$delivery =  new Address( $cart->id_address_delivery );
$user = $delivery->getFields();
$forSend += array(
					'BILL_FNAME' => $user['firstname'],
					'BILL_LNAME' => $user['lastname'],
					'BILL_ADDRESS' => $user['address1'],
					'BILL_ADDRESS2' => $user['address2'],
					'BILL_ZIPCODE' => $user['postcode'],
					'BILL_CITY' => $user['city'],
					'BILL_PHONE' => $user['phone_mobile'],
					'BILL_EMAIL' =>$customer->email
					);

$mailVars = array();

$payu->validateOrder($cart->id, 1, $total, $payu->displayName, NULL, NULL, (int)$currency->id, false, $customer->secure_key);
$order = new Order($payu->currentOrder);

$orderID = $payu->currentOrder.'_'.$cart->id;

$forSend += array (
					'ORDER_REF' => $orderID, # Uniqe order 
					'ORDER_SHIPPING' => $cart->getOrderShippingCost(), # Shipping cost
					'PRICES_CURRENCY' => $payu->Payu_getVar("currency"),  # Currency
					'LANGUAGE' => $payu->Payu_getVar("language"),
				  );

$pay = PayuCLS::getInst()->setOptions( $option )->setData( $forSend )->LU();
echo $pay;
