jQuery(function(b){function a(c){b("#order_methods, #order_review").block({message:"결제가 진행중입니다.",css:{width:"100%",height:"50px",marginTop:"45px",fontFamily:'"나눔고딕", NanumGothic'},overlayCSS:{background:"#fff url("+_ifw_pay_for_order.ajax_loader_url+") no-repeat center",backgroundSize:"32px 32px",opacity:0.6}});b.ajax({type:"POST",url:ajaxurl,dataType:"html",data:{action:"payment_form_"+c,orderid:_ifw_pay_for_order.order_id,orderkey:_ifw_pay_for_order.order_key},success:function(f){var d=JSON.parse(f);if(d!==undefined&&d.success!==undefined&&d.success===true){try{if(!(document.getElementById("payment_form_inicis") instanceof Object)){b(document.body).append('<div id="payment_form_inicis"></div>')}var g=b("#payment_form_inicis");g.empty();g.append(d.data);enable_click();if(pay(document.ini)){document.ini.submit()}else{$form.removeClass("processing");b("#order_methods, #order_review").unblock()}}catch(e){}}else{alert(d.data)}b("#order_methods, #order_review").unblock()}});return false}b("#place_order").on("click",function(){var c=b("#order_review input[name=payment_method]:checked").val();if(c==="inicis_card"||c==="inicis_bank"){a(c)}else{b(this).closest("form").submit()}})});if(typeof StartSmartUpdate!="undefined"){StartSmartUpdate()}var openwin;function pay(a){if(document.ini.clickcontrol.value=="enable"){if(document.ini.goodname.value==""){alert("상품명이 빠졌습니다. 필수항목입니다.");return false}else{if(document.ini.buyername.value==""){alert("구매자명이 빠졌습니다. 필수항목입니다.");return false}else{if(document.ini.buyeremail.value==""){alert("구매자 이메일주소가 빠졌습니다. 필수항목입니다.");return false}else{if(document.ini.buyertel.value==""){alert("구매자 전화번호가 빠졌습니다. 필수항목입니다.");return false}else{if(!ini_IsInstalledPlugin()){alert("\n이니페이 플러그인 128이 설치되지 않았습니다. \n\n안전한 결제를 위하여 이니페이 플러그인 128의 설치가 필요합니다. \n\n다시 설치하시려면 Ctrl + F5키를 누르시거나 메뉴의 [보기/새로고침]을 선택하여 주십시오.");return false}else{if(MakePayMessage(a)){disable_click();return true}else{if(IsPluginModule()){alert("결제를 취소하셨습니다.")}return false}}}}}}}else{return false}}function enable_click(){document.ini.clickcontrol.value="enable"}function disable_click(){document.ini.clickcontrol.value="disable"};