<?php
	if (defined('ICL_LANGUAGE_CODE')) {
		$lang_code = ICL_LANGUAGE_CODE;

		if ($use_ssl == 'yes') {
			?>
				<form name=ini method=post action="<?php echo untrailingslashit( WC()->api_request_url('WC_Gateway_Inicis_Card?type=pc&lang=' . $lang_code, true)); ?>" onSubmit="return pay(this)">
			<?php
		} else {
			?>
				<form name=ini method=post action="<?php echo untrailingslashit( WC()->api_request_url('WC_Gateway_Inicis_Card?type=pc&lang=' . $lang_code, false)); ?>" onSubmit="return pay(this)">
			<?php
		}
	} else {
		if ($use_ssl == 'yes') {
			?>
				<form name=ini method=post action="<?php echo untrailingslashit( WC()->api_request_url('WC_Gateway_Inicis_Card?type=pc', true)); ?>" onSubmit="return pay(this)">
			<?php
		} else {
			?>
				<form name=ini method=post action="<?php echo untrailingslashit( WC()->api_request_url('WC_Gateway_Inicis_Card?type=pc', false)); ?>" onSubmit="return pay(this)">
			<?php
		}
	} ?>
	
    <input type="hidden" name="goodname" size=20 value="<?php echo esc_attr($productinfo); ?>" />
    <input type="hidden" name="oid" size=40 value="<?php echo $txnid; ?>" />
    <input type="hidden" name="buyername" size=20 value="<?php echo $order->billing_last_name . $order->billing_first_name; ?>" />
    <input type="hidden" name="buyeremail" size=20 value="<?php echo $order->billing_email; ?>" />
    <input type="hidden" name="buyertel" size=20 value="<?php echo $order->billing_phone; ?>" />
    <input type="hidden" name="gopaymethod" size=20 value="<?php echo $this->settings['gopaymethod']; ?>" />
    <input type="hidden" name="currency" size=20 value="WON" />
    <input type="hidden" name="acceptmethod" size=20 value="<?php echo $acceptmethod; ?>" />
    <input type="hidden" name="ini_logoimage_url" value="<?php echo $this->settings['logo_upload']; ?>" />
    <input type=hidden name=quotainterest value="">
    <input type=hidden name=paymethod value="">
    <input type=hidden name=cardcode value="">
    <input type=hidden name=cardquota value="">
    <input type=hidden name=rbankcode value="">
    <input type=hidden name=reqsign value="DONE">
    <input type=hidden name=encrypted value="">
    <input type=hidden name=sessionkey value="">
    <input type=hidden name=uid value=""> 
    <input type=hidden name=sid value="">
    <input type=hidden name=version value=4000>
    <input type=hidden name=clickcontrol value="">
    <input type=hidden name=hash value="<?php echo $hash; ?>">
    <input type=hidden name=txnid value="<?php echo $txnid; ?>">
    <input type=hidden name=Amount value="<?php echo $order->get_order_total(); ?>">
    <input type=submit name="결제">
	<input type=hidden name=ini_encfield value="<?php echo $inipay->GetResult("encfield"); ?>">
    <input type=hidden name=ini_certid value="<?php echo $inipay->GetResult("certid"); ?>">
</form>
    