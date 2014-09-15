jQuery( function( $ ) {
	
	function payment_form(order_id, order_key, $form) {
		var payment_method = $( '#order_review input[name=payment_method]:checked' ).val();
		$.ajax({
			type: 'POST',
			url: ifw_ajaxurl,
			dataType: 'html',
			data: {
				'action': 'payment_form_' + payment_method,
				'orderid': order_id,
				'orderkey': order_key
				},
			success:	function( data ) {
				var result = JSON.parse(data);

				if( result !== undefined && result.success !== undefined && result.success === true ){
					try {
						if( !(document.getElementById('payment_form_inicis') instanceof Object) ) {
	                		$(document.body).append('<div id="payment_form_inicis"></div>');
	           			}
						var $container = $('#payment_form_inicis');
						$container.empty();
						$container.append(result.data);
	
						pay(document.ini);
					}
					catch( err ) {
						$form.removeClass( 'processing' );
						$( '#order_methods, #order_review' ).unblock();								
					}
				}else{
					alert( result.data );
					$form.removeClass( 'processing' );
					$( '#order_methods, #order_review' ).unblock();					
				}
				
			}
		});
		
		return false;
	};
	
	$( 'form.checkout' ).on( 'checkout_place_order_inicis_card checkout_place_order_inicis_bank', function() {
		
		var $form = $( this );
		
		if ( $form.is( '.processing' ) ) {
			return false;
		}
		
		$( '#order_methods, #order_review' ).block({ message: '', css: {width: '100%', height: '50px', marginTop: '45px', fontSize: '1.2em', fontFamily: '"나눔고딕", NanumGothic'}, overlayCSS: { background: '#fff url(' + _ifw_payment.ajax_loader_url + ') no-repeat center', backgroundSize: '300px 200px', opacity: 1 } });
				
		$form.addClass( 'processing' );

		var form_data = $form.data();

		$.ajax({
			type:		'POST',
			url:		wc_checkout_params.checkout_url,
			data:		$form.serialize(),
			success:	function( code ) {
				var result = '';

				try {
					if ( code.indexOf( '<!--WC_START-->' ) >= 0 )
						code = code.split( '<!--WC_START-->' )[1]; // Strip off before after WC_START

					if ( code.indexOf( '<!--WC_END-->' ) >= 0 )
						code = code.split( '<!--WC_END-->' )[0]; // Strip off anything after WC_END

					result = $.parseJSON( code );
					
					if ( result.result === 'success' ) {
						if(result.order_id && result.order_key){
							payment_form(result.order_id, result.order_key, $form);
						}else{
							$form.removeClass( 'processing' );
							$( '#order_methods, #order_review' ).unblock();
						}
					} else if ( result.result === 'failure' ) {
						throw 'Result failure';
					} else {
						throw 'Invalid response';
					}
				}

				catch( err ) {
					if ( result.reload === 'true' ) {
						window.location.reload();
						return;
					}

					// Remove old errors
					$( '.woocommerce-error, .woocommerce-message' ).remove();

					// Add new errors
					if ( result.messages ) {
						$form.prepend( result.messages );
					} else {
						$form.prepend( code );
					}

					// Cancel processing
					$form.removeClass( 'processing' ).unblock();

					// Lose focus for all fields
					$form.find( '.input-text, select' ).blur();

					// Scroll to top
					$( 'html, body' ).animate({
						scrollTop: ( $( 'form.checkout' ).offset().top - 100 )
					}, 1000 );

					// Trigger update in case we need a fresh nonce
					if ( result.refresh === 'true' )
						$( 'body' ).trigger( 'update_checkout' );

					$( 'body' ).trigger( 'checkout_error' );
					
					$form.removeClass( 'processing' );
					$( '#order_methods, #order_review' ).unblock();
				}
			},
			dataType: 'html'
		});
		
		return false;
	} );
});

/***** Mobile Payment Part *****/

jQuery(document).ready(function(){
	// updateLayout();
});
 
var currentWidth = 0;
                    
function updateLayout()
{
	if (window.innerWidth != currentWidth)
	{
		currentWidth = window.innerWidth;
		var orient = currentWidth == 320 ? "profile" : "landscape";
        document.body.setAttribute("orient", orient);
        setTimeout(function()
        {
            window.scrollTo(0, 1);
        }, 100);            
    }
}
 
setInterval(updateLayout, 400);
window.name = "BTPG_CLIENT";

var width = 330;
var height = 480;
var xpos = (screen.width - width) / 2;
var ypos = (screen.width - height) / 2;
var position = "top=" + ypos + ",left=" + xpos;
var features = position + ", width=320, height=440";
var date = new Date();
var date_str = "testoid_"+date.getFullYear()+""+date.getMinutes()+""+date.getSeconds();
if( date_str.length != 16 )
{
    for( i = date_str.length ; i < 16 ; i++ )
    {
        date_str = date_str+"0";
    }
}

function pay()
{
    var order_form = document.ini;
    var inipaymobile_type = order_form.inipaymobile_type.value;
    var paymethod = order_form.paymethod.value;

    if ( paymethod == "bank")
        order_form.P_APP_BASE.value = "ON";
    order_form.target = "BTPG_WALLET";
    order_form.action = "https://mobile.inicis.com/smart/" + paymethod + "/";
    order_form.submit();
}

function focus_control(){
 	if(document.ini.clickcontrol.value == "disable")
		openwin.focus();
}

