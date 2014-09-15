jQuery( function( $ ) {
	
	$("#ifw-refund-request").on( 'click', function() {
        var data = {
            action: _ifw_admin.action,
            order_id: _ifw_admin.order_id,
            refund_request: _ifw_admin.nonce,
        };

		if(confirm('환불처리를 진행하시겠습니까?')){
			$(this).attr('disabled','true');
	        $(this).attr('value', "처리중...");
	        
	        $.post(ajaxurl, data, function(response) {
	        	response = JSON.parse(response);
	            if( response.success == 'true' || response.success ) {
	                alert(response.data);
					location.reload();
				} else {
					alert(response.data);
			        $(this).removeAttr('disabled');
	        		$(this).attr('value', "환불하기");
	            }
	        });
		}
	});

	$("#ifw-check-receipt").on( 'click', function() {
        window.open( "https://iniweb.inicis.com/app/publication/apReceipt.jsp?noMethod=1&noTid=" + _ifw_admin.tid );
	});
});
