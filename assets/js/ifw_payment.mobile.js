jQuery(function(b){function a(f,d,c){var e=b("#order_review input[name=payment_method]:checked").val();b.ajax({type:"POST",url:ifw_ajaxurl,dataType:"html",data:{action:"payment_form_"+e,orderid:f,orderkey:d},success:function(j){var g=JSON.parse(j);if(g!==undefined&&g.success!==undefined&&g.success===true){try{if(!(document.getElementById("payment_form_inicis") instanceof Object)){b(document.body).append('<div id="payment_form_inicis"></div>')}var k=b("#payment_form_inicis");k.empty();k.append(g.data);pay(document.ini)}catch(h){c.removeClass("processing");b("#order_methods, #order_review").unblock()}}else{alert(g.data);c.removeClass("processing");b("#order_methods, #order_review").unblock()}}});return false}b("form.checkout").on("checkout_place_order_inicis_card checkout_place_order_inicis_bank",function(){var c=b(this);if(c.is(".processing")){return false}b("#order_methods, #order_review").block({message:"",css:{width:"100%",height:"50px",marginTop:"45px",fontSize:"1.2em",fontFamily:'"나눔고딕", NanumGothic'},overlayCSS:{background:"#fff url("+_ifw_payment.ajax_loader_url+") no-repeat center",backgroundSize:"300px 200px",opacity:1}});c.addClass("processing");var d=c.data();b.ajax({type:"POST",url:wc_checkout_params.checkout_url,data:c.serialize(),success:function(g){var e="";try{if(g.indexOf("<!--WC_START-->")>=0){g=g.split("<!--WC_START-->")[1]}if(g.indexOf("<!--WC_END-->")>=0){g=g.split("<!--WC_END-->")[0]}e=b.parseJSON(g);if(e.result==="success"){if(e.order_id&&e.order_key){a(e.order_id,e.order_key,c)}else{c.removeClass("processing");b("#order_methods, #order_review").unblock()}}else{if(e.result==="failure"){throw"Result failure"}else{throw"Invalid response"}}}catch(f){if(e.reload==="true"){window.location.reload();return}b(".woocommerce-error, .woocommerce-message").remove();if(e.messages){c.prepend(e.messages)}else{c.prepend(g)}c.removeClass("processing").unblock();c.find(".input-text, select").blur();b("html, body").animate({scrollTop:(b("form.checkout").offset().top-100)},1000);if(e.refresh==="true"){b("body").trigger("update_checkout")}b("body").trigger("checkout_error");c.removeClass("processing");b("#order_methods, #order_review").unblock()}},dataType:"html"});return false})});jQuery(document).ready(function(){});var currentWidth=0;function updateLayout(){if(window.innerWidth!=currentWidth){currentWidth=window.innerWidth;var a=currentWidth==320?"profile":"landscape";document.body.setAttribute("orient",a);setTimeout(function(){window.scrollTo(0,1)},100)}}setInterval(updateLayout,400);window.name="BTPG_CLIENT";var width=330;var height=480;var xpos=(screen.width-width)/2;var ypos=(screen.width-height)/2;var position="top="+ypos+",left="+xpos;var features=position+", width=320, height=440";var date=new Date();var date_str="testoid_"+date.getFullYear()+""+date.getMinutes()+""+date.getSeconds();if(date_str.length!=16){for(i=date_str.length;i<16;i++){date_str=date_str+"0"}}function pay(){var c=document.ini;var b=c.inipaymobile_type.value;var a=c.paymethod.value;if(a=="bank"){c.P_APP_BASE.value="ON"}c.target="BTPG_WALLET";c.action="https://mobile.inicis.com/smart/"+a+"/";c.submit()}function focus_control(){if(document.ini.clickcontrol.value=="disable"){openwin.focus()}};