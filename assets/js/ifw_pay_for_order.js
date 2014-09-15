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
						enable_click();
						
						if( pay(document.ini) ){
							document.ini.submit();
						}else{
							$form.removeClass( 'processing' );
							$( '#order_methods, #order_review' ).unblock();							
						}
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

/***** PC Payment Part *****/

if(typeof StartSmartUpdate != "undefined"){
	StartSmartUpdate();
}

var openwin;
function pay(frm)
{
    if(document.ini.clickcontrol.value == "enable")
    {
        if(document.ini.goodname.value == "")  // 필수항목 체크 (상품명, 상품가격, 구매자명, 구매자 이메일주소, 구매자 전화번호)
        {
            alert("상품명이 빠졌습니다. 필수항목입니다.");
            return false;
        }
        else if(document.ini.buyername.value == "")
        {
            alert("구매자명이 빠졌습니다. 필수항목입니다.");
            return false;
        } 
        else if(document.ini.buyeremail.value == "")
        {
            alert("구매자 이메일주소가 빠졌습니다. 필수항목입니다.");
            return false;
        }
        else if(document.ini.buyertel.value == "")
        {
            alert("구매자 전화번호가 빠졌습니다. 필수항목입니다.");
            return false;
        }
        else if( !ini_IsInstalledPlugin() )  // 플러그인 설치유무 체크
        {
            alert("\n이니페이 플러그인 128이 설치되지 않았습니다. \n\n안전한 결제를 위하여 이니페이 플러그인 128의 설치가 필요합니다. \n\n다시 설치하시려면 Ctrl + F5키를 누르시거나 메뉴의 [보기/새로고침]을 선택하여 주십시오.");
            return false;
        }
        else
        {
            if (MakePayMessage(frm))
            {
                disable_click();
                return true;
            }
            else
            {
                if( IsPluginModule() )
                {
                    alert("결제를 취소하셨습니다.");
                }
                return false;
            }
        }
    }
    else
    {
        return false;
    }
}

function enable_click(){
    document.ini.clickcontrol.value = "enable"
}
function disable_click(){
    document.ini.clickcontrol.value = "disable"
}