<form id="form1" name="ini" method="post" action="" accept-charset="EUC-KR">
	<input type="hidden" name="inipaymobile_type" id="select" value="web"/>
	<input type="hidden" name="P_OID" value="<?php echo $txnid; ?>"/>
	<input type="hidden" name="P_GOODS" value="<?php echo esc_attr($productinfo); ?>"/>
	<input type="hidden" name="P_AMT" value="<?php echo $order->get_order_total(); ?>"/>
	<input type="hidden" name="P_UNAME" value="<?php echo $order->billing_last_name . ' ' . $order->billing_first_name; ?>"/>
	<input type="hidden" name="P_MNAME" value="<?php echo get_bloginfo('name'); ?>"/>
	<input type="hidden" name="P_MOBILE" value="<?php echo $order->billing_phone; ?>"/>
	<input type="hidden" name="P_EMAIL" value="<?php echo $order->billing_email; ?>" />
	<input type="hidden" name="P_MID" value="<?php echo $this->merchant_id; ?>">

	<?php
		if (defined('ICL_LANGUAGE_CODE')) {
			$lang_code = ICL_LANGUAGE_CODE;
			if ($use_ssl == 'yes'){
				$flag_ssl = true;
			} else {
				$flag_ssl = false;
			}
			$next_url 	= WC()->api_request_url('WC_Gateway_Inicis_Card', $flag_ssl) . '?lang='.$lang_code.'&type=mobile_next';
			$return_url = WC()->api_request_url('WC_Gateway_Inicis_Card', $flag_ssl) . '?lang='.$lang_code.'&type=mobile_return,oid=' . $txnid ;
			$noti_url 	= WC()->api_request_url('WC_Gateway_Inicis_Card', $flag_ssl) . '?lang='.$lang_code.'&type=mobile_noti';
			$cancel_url = WC()->api_request_url('WC_Gateway_Inicis_Card', $flag_ssl) . '?land='.$lang_code.'&type=mobile_return,oid=' . $txnid ;
		} else {
			if ($use_ssl == 'yes'){
				$flag_ssl = true;
			} else {
				$flag_ssl = false;
			}
			$next_url 	= WC()->api_request_url('WC_Gateway_Inicis_Card', $flag_ssl) . '?type=mobile_next';
			$return_url = WC()->api_request_url('WC_Gateway_Inicis_Card', $flag_ssl) . '?type=mobile_return,oid=' . $txnid ;
			$noti_url 	= WC()->api_request_url('WC_Gateway_Inicis_Card', $flag_ssl) . '?type=mobile_noti';
			$cancel_url = WC()->api_request_url('WC_Gateway_Inicis_Card', $flag_ssl) . '?type=mobile_return,oid=' . $txnid ;
		}
	?>
    <input type="hidden" name="P_NEXT_URL" value="<?php echo $next_url; ?>">
	<input type="hidden" name="P_RETURN_URL" value="<?php echo $return_url; ?>">
	<input type="hidden" name="P_NOTI_URL" value="<?php echo $noti_url; ?>">
	<input type="hidden" name="P_CANCEL_URL" value="<?php echo $cancel_url; ?>">
	
    <input type="hidden" name="P_NOTI" value="<?php echo $notification; ?>">
    <input type="hidden" name="P_HPP_METHOD" value="1">
	<input type="hidden" name="P_APP_BASE" value="">
	<input type="hidden" name="P_RESERVED" value="ismart_use_sign=Y">
    <input type="hidden" name="paymethod" size=20 value="<?php echo $this->settings['paymethod']; ?>" />
	<img id="inicis_image_btn" src="<?php echo plugins_url("../assets/images/button_03.gif", __FILE__) ?>" width="63" height="25" style="width:63px;height:25px;border:none;padding:0px;margin:8px 0px;" onclick="javascript:onSubmit();"/>
</form>