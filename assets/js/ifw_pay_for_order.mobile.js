jQuery(function(b){function a(c){b("#order_methods, #order_review").block({message:"결제가 진행중입니다.",css:{width:"100%",height:"50px",marginTop:"45px",fontFamily:'"나눔고딕", NanumGothic'},overlayCSS:{background:"#fff url("+_ifw_pay_for_order.ajax_loader_url+") no-repeat center",backgroundSize:"32px 32px",opacity:0.6}});b.ajax({type:"POST",url:ajaxurl,dataType:"html",data:{action:"payment_form_"+c,orderid:_ifw_pay_for_order.order_id,orderkey:_ifw_pay_for_order.order_key},success:function(f){var d=JSON.parse(f);if(d!==undefined&&d.success!==undefined&&d.success===true){try{if(!(document.getElementById("payment_form_inicis") instanceof Object)){b(document.body).append('<div id="payment_form_inicis"></div>')}var g=b("#payment_form_inicis");g.empty();g.append(d.data);pay(document.ini)}catch(e){}}else{alert(d.data)}b("#order_methods, #order_review").unblock()}});return false}b("#place_order").on("click",function(){var c=b("#order_review input[name=payment_method]:checked").val();if(c==="inicis_card"||c==="inicis_bank"){a(c)}else{b(this).closest("form").submit()}})});jQuery(document).ready(function(){});var currentWidth=0;function updateLayout(){if(window.innerWidth!=currentWidth){currentWidth=window.innerWidth;var a=currentWidth==320?"profile":"landscape";document.body.setAttribute("orient",a);setTimeout(function(){window.scrollTo(0,1)},100)}}setInterval(updateLayout,400);window.name="BTPG_CLIENT";var width=330;var height=480;var xpos=(screen.width-width)/2;var ypos=(screen.width-height)/2;var position="top="+ypos+",left="+xpos;var features=position+", width=320, height=440";var date=new Date();var date_str="testoid_"+date.getFullYear()+""+date.getMinutes()+""+date.getSeconds();if(date_str.length!=16){for(i=date_str.length;i<16;i++){date_str=date_str+"0"}}function pay(){var c=document.ini;var b=c.inipaymobile_type.value;var a=c.paymethod.value;if(a=="bank"){c.P_APP_BASE.value="ON"}c.target="BTPG_WALLET";c.action="https://mobile.inicis.com/smart/"+a+"/";c.submit()}function focus_control(){if(document.ini.clickcontrol.value=="disable"){openwin.focus()}};