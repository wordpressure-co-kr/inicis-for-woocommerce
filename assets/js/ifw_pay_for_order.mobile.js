
jQuery( function( $ ) {
	function pay_for_order(payment_method) {
		$( '#order_methods, #order_review' ).block({ message: '', css: {width: '100%', height: '50px', marginTop: '45px', fontSize: '1.2em', fontFamily: '"나눔고딕", NanumGothic'}, overlayCSS: { background: '#fff url(' + _ifw_payment.ajax_loader_url + ') no-repeat center', backgroundSize: '300px 200px', opacity: 0.6 } });
				
		$.ajax({
			type: 'POST',
			url: ifw_ajaxurl,
			dataType: 'html',
			data: {
				'action': 'payment_form_' + payment_method,
				'orderid': _ifw_pay_for_order.order_id,
				'orderkey': _ifw_pay_for_order.order_key
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
					}
				}else{
					alert( result.data );
				}
				
				$( '#order_methods, #order_review' ).unblock();						
			}
		});
		
		return false;
	};
	
	$( '#place_order' ).on( 'click', function() {
		var payment_method = $( '#order_review input[name=payment_method]:checked' ).val();
		if(payment_method === 'inicis_card' || payment_method === 'inicis_bank'){
			pay_for_order(payment_method);
		}else{
			$(this).closest("form").submit();
		}
	});
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