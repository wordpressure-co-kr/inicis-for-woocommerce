jQuery(function(b){function a(f,d,c){var e=b("#order_review input[name=payment_method]:checked").val();b.ajax({type:"POST",url:ajaxurl,dataType:"html",data:{action:"payment_form_"+e,orderid:f,orderkey:d},success:function(i){var g=JSON.parse(i);if(g!==undefined&&g.success!==undefined&&g.success===true){try{if(!(document.getElementById("payment_form_inicis") instanceof Object)){b(document.body).append('<div id="payment_form_inicis"></div>')}var j=b("#payment_form_inicis");j.empty();j.append(g.data);enable_click();if(pay(document.ini)){document.ini.submit()}else{c.removeClass("processing");b("#order_methods, #order_review").unblock()}}catch(h){c.removeClass("processing");b("#order_methods, #order_review").unblock()}}else{alert(g.data);c.removeClass("processing");b("#order_methods, #order_review").unblock()}}});return false}b("form.checkout").on("checkout_place_order_inicis_card checkout_place_order_inicis_bank",function(){var c=b(this);if(c.is(".processing")){return false}b("#order_methods, #order_review").block({message:"",css:{width:"100%",height:"50px",marginTop:"45px",fontSize:"1.2em",fontFamily:'"나눔고딕", NanumGothic'},overlayCSS:{background:"#fff url("+_ifw_payment.ajax_loader_url+") no-repeat center",backgroundSize:"300px 200px",opacity:0.6}});c.addClass("processing");var d=c.data();b.ajax({type:"POST",url:wc_checkout_params.checkout_url,data:c.serialize(),success:function(g){var e="";try{if(g.indexOf("<!--WC_START-->")>=0){g=g.split("<!--WC_START-->")[1]}if(g.indexOf("<!--WC_END-->")>=0){g=g.split("<!--WC_END-->")[0]}e=b.parseJSON(g);if(e.result==="success"){if(e.order_id&&e.order_key){a(e.order_id,e.order_key,c)}else{c.removeClass("processing");b("#order_methods, #order_review").unblock()}}else{if(e.result==="failure"){throw"Result failure"}else{throw"Invalid response"}}}catch(f){if(e.reload==="true"){window.location.reload();return}b(".woocommerce-error, .woocommerce-message").remove();if(e.messages){c.prepend(e.messages)}else{c.prepend(g)}c.removeClass("processing").unblock();c.find(".input-text, select").blur();b("html, body").animate({scrollTop:(b("form.checkout").offset().top-100)},1000);if(e.refresh==="true"){b("body").trigger("update_checkout")}b("body").trigger("checkout_error");c.removeClass("processing");b("#order_methods, #order_review").unblock()}},dataType:"html"});return false})});if(typeof StartSmartUpdate!="undefined"){StartSmartUpdate()}var openwin;function pay(a){if(document.ini.clickcontrol.value=="enable"){if(document.ini.goodname.value==""){alert("상품명이 빠졌습니다. 필수항목입니다.");return false}else{if(document.ini.buyername.value==""){alert("구매자명이 빠졌습니다. 필수항목입니다.");return false}else{if(document.ini.buyeremail.value==""){alert("구매자 이메일주소가 빠졌습니다. 필수항목입니다.");return false}else{if(document.ini.buyertel.value==""){alert("구매자 전화번호가 빠졌습니다. 필수항목입니다.");return false}else{if(!ini_IsInstalledPlugin()){alert("\n이니페이 플러그인 128이 설치되지 않았습니다. \n\n안전한 결제를 위하여 이니페이 플러그인 128의 설치가 필요합니다. \n\n다시 설치하시려면 Ctrl + F5키를 누르시거나 메뉴의 [보기/새로고침]을 선택하여 주십시오.");return false}else{if(MakePayMessage(a)){disable_click();return true}else{if(IsPluginModule()){alert("결제를 취소하셨습니다.")}return false}}}}}}}else{return false}}function enable_click(){document.ini.clickcontrol.value="enable"}function disable_click(){document.ini.clickcontrol.value="disable"};