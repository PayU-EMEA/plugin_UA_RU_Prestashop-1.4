<?php
if ( $_GET['payudebugmode'] == 'true' ) 
{
	ini_set('display_errors', true);
	error_reporting(E_ALL);
}

class PayuCLS
{
	var $luUrl = "https://secure.payu.ua/order/lu.php", 
		$button = "<input type='submit'>",
		$debug = 0,
		$showinputs = "hidden";

	private static $Inst = false, $merchant, $key;

	private $data = array(), $dataArr = array(), $answer = ""; 
	private $LUcell = array( 'MERCHANT' => 1, 'ORDER_REF' => 0, 'ORDER_DATE' => 1, 'ORDER_PNAME' => 1, 'ORDER_PGROUP' => 0,
							'ORDER_PCODE' => 1, 'ORDER_PINFO' => 0, 'ORDER_PRICE' => 1, 'ORDER_QTY' => 1, 'ORDER_VAT' => 1, 
							'ORDER_SHIPPING' => 1, 'PRICES_CURRENCY' => 1);

	private $IPNcell = array( "IPN_PID", "IPN_PNAME", "IPN_DATE", "ORDERSTATUS" );

	private function __construct(){}
	private function __clone(){}
	public function __toString()
	{ 
		return ( $this->answer === "" ) ? "<!-- Answer are not exists -->" : $this->answer;  
	}
	public static function getInst()
	{	
		if( self::$Inst === false ) self::$Inst = new PayuCLS();
		return self::$Inst;
	}



#---------------------------------------------
# Add all options for PayU object. 
# Can change all public variables;
# $opt = array( merchant, secretkey, [ luUrl, debug, button ] );
#---------------------------------------------
	function setOptions( $opt = array() )
	{
		if ( !isset( $opt['merchant'] ) || !isset( $opt['secretkey'] )) die("No params");
		self::$merchant = $opt['merchant'];
		self::$key = $opt['secretkey'];
		unset( $opt['merchant'], $opt['secretkey'] );
		if ( count($opt) === 0 ) return $this;
		foreach ( $opt as $k => $v) $this->$k = $v;
		return $this;
	}

	function setData( $array = null )
	{	
		if ($array === null ) die("No data");
		$this->dataArr = $array;
		return $this;
	}

#--------------------------------------------------------
#	Generate HASH
#--------------------------------------------------------
	function md5_hmac( $data ) 
	{	
		$key = self::$key;
		$b = 64;
 		if (strlen($key) > $b) $key = pack("H*",md5($key));
  		$key  = str_pad($key, $b, chr(0x00));
  		$ipad = str_pad('', $b, chr(0x36));
  		$opad = str_pad('', $b, chr(0x5c));
  		$k_ipad = $key ^ $ipad;
  		$k_opad = $key ^ $opad;
  		return md5($k_opad  . pack("H*", md5($k_ipad . $data)));
	}

	function Signature( $data ) 
	{		
		$str = "";
		foreach ( $data as $k => $v ) $str .= $this->convData( $v );
		return $this->md5_hmac( $str );
	}

#--------------------------------------------------------
# Outputs a string for hmac format.
# For a string like 'aa' it will return '2aa'.
#--------------------------------------------------------
	private function convString($string) 
	{	
		return mb_strlen($string, '8bit') . $string;
	}

#--------------------------------------------------------
# The same as convString except that it receives
# an array of strings and returns the string from all values within the array.
#--------------------------------------------------------	
	private function convArray($array) 
	{
  		$return = '';
  		foreach ($array as $v) $return .= $this->convString( $v );
  		return $return;
	}

	private function convData( $val )
	{
		return ( is_array( $val ) ) ? $this->convArray( $val ) : $this->convString( $val );
	}
#----------------------------

#====================== LU GENERETE FORM =================================================

	public function LU()
	{	
		$arr = &$this->dataArr;
		$arr['MERCHANT'] = self::$merchant;
		if( !isset($arr['ORDER_DATE']) ) $arr['ORDER_DATE'] = date("Y-m-d H:i:s");
		$arr['TESTORDER'] = ( $this->debug == 1 ) ? "TRUE" : "FALSE";
		$arr['DEBUG'] = $this->debug;
		$arr['ORDER_HASH'] = $this->Signature( $this->checkArray( $arr ) );
		$this->answer = $this->genereteForm( $arr );
		return $this;
	}

#-----------------------------
# Check array for correct data
#-----------------------------
	private function checkArray( $data )
	{
		$ret = array();
		foreach ( $this->LUcell as $k => $v ) 
		{ 	
			if ( isset($data[$k]) ) $ret[$k] = $data[$k];
			elseif ( $v == 1 ) die("$k is not set");
		}
		return $ret;
	}

#-----------------------------
# Method which create a form
#-----------------------------
	private function genereteForm( $data )
	{	
		$form = '<form method="post" id="PayUForm" action="'.$this->luUrl.'">';
		foreach ( $data as $k => $v ) $form .= $this->makeString( $k, $v );
		return $form . $this->button."</form>";
	}	

#-----------------------------
# Make inputs for form
#-----------------------------	
	private function makeString ( $name, $val )
	{
		$str = "";
		if ( !is_array( $val ) ) return '<input type="'.$this->showinputs.'" name="'.$name.'" value="'.htmlspecialchars($val).'">'."\n";
		foreach ($val as $v) $str .= $this->makeString( $name.'[]', $v );
		return $str;
	}

#======================= END LU =====================================	


#======================= IPN READ ANSWER ============================

	public function IPN()
	{	
		$arr = &$this->dataArr;
		$arr = $_POST;
		foreach ( $this->IPNcell as $name ) if ( !isset( $arr[ $name ] ) ) die( "Incorrect data" );

		$hash = $arr["HASH"];  
		unset( $arr["HASH"] );
		$sign = $this->Signature( $arr );

		if ( $hash != $sign ) return $this;
		$datetime = date("YmdHis");
		$sign = $this->Signature(  array(
				   						"IPN_PID" => $arr[ "IPN_PID" ][0], 
				  						"IPN_PNAME" => $arr[ "IPN_PNAME" ][0], 
				   						"IPN_DATE" => $arr[ "IPN_DATE" ], 
				   						"DATE" => $datetime
										)
								);

		$this->answer = "<!-- <EPAYMENT>$datetime|$sign</EPAYMENT> -->";
		return $this;
	}

#======================= END IPN ============================

#======================= Check BACK_REF =====================
	function checkBackRef( $type = "http")
	{
		$path = $type.'://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
		$tmp = explode("?", $path);
		$url = $tmp[0].'?';
		$params = array();
		foreach ($_GET as $k => $v)
		{
			if ( $k != "ctrl" ) $params[] = $k.'='.rawurlencode($v);
		}
		$url = $url.implode("&", $params);
		$arr = array($url);
		$sign = $this->Signature( $arr );

		#echo "$sign === ".$_GET['ctrl'];
		$this->answer = ( $sign === $_GET['ctrl'] ) ? true : false;
		return $this->answer;
	}

#======================= END Check BACK_REF =================

}

?>