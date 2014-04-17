/********************************************
 * INICIS - for WooCommerce, with CODEM(c)
 * JS File
 ********************************************/

/***** Payment Setting (wp-admin) Part *****/

function onClickInicis() { 
	window.open("http://landing.inicis.com/landing/application/application01_2.php?cd=hostinglanding&product=codemstory", "초기등록비 무료 지원 가입신청" ); 
} 

function onClickInicis2() { 
	window.open("http://www.inicis.com/service_application_02.jsp", "서비스 상세보기" ); 
}

/***** Mobile Payment Part *****/

jQuery(document).ready(function(){
	updateLayout();
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

function setOid()
{
    document.ini.P_OID.value = ""+date_str;
}

function on_app()
{
        var order_form = document.ini;
        var paymethod;
        if(order_form.paymethod.value == "wcard")
        paymethod = "CARD";
    else if(order_form.paymethod.value == "mobile")
        paymethod = "HPP";
    else if(order_form.paymethod.value == "vbank")
        paymethod = "VBANK";
    else if(order_form.paymethod.value == "culture")
        paymethod = "CULT";
    else if(order_form.paymethod.value == "hpmn")
        paymethod = "HPMN"; 

    param = "";
    param = param + "mid=" + order_form.P_MID.value + "&";
    param = param + "oid=" + order_form.P_OID.value + "&";
    param = param + "price=" + order_form.P_AMT.value + "&";
    param = param + "goods=" + order_form.P_GOODS.value + "&";
    param = param + "uname=" + order_form.P_UNAME.value + "&";
    param = param + "mname=" + order_form.P_MNAME.value + "&";
    param = param + "mobile=000-111-2222" + order_form.P_MOBILE.value + "&";
    param = param + "paymethod=" + paymethod + "&";
    param = param + "noteurl=" + order_form.P_NOTEURL.value + "&";
    param = param + "ctype=1" + "&";
    param = param + "returl=" + "&";
    param = param + "reqtype=WEB&";
    param = param + "email=" + order_form.P_EMAIL.value;
    var ret = location.href="INIpayMobile://" + encodeURI(param);

    setTimeout
    (
        function()
        {
            if(confirm("INIpayMobile이 설치되어 있지 않아 App Store로 이동합니다. 수락하시겠습니까?"))
            {
                document.location="http://phobos.apple.com/WebObjects/MZStore.woa/wa/viewSoftware?id=351845229&;mt=8";
            }
            return;
        }
    )

}

function on_web()
{
    var order_form = document.ini;
    var paymethod = order_form.paymethod.value;
    /*
    var wallet = window.open("", "BTPG_WALLET", features);
    
    if (wallet == null) 
    {
        if ((webbrowser.indexOf("Windows NT 5.1")!=-1) && (webbrowser.indexOf("SV1")!=-1)) 
        {    // Windows XP Service Pack 2
            alert("팝업이 차단되었습니다. 브라우저의 상단 노란색 [알림 표시줄]을 클릭하신 후 팝업창 허용을 선택하여 주세요.");
        } 
        else 
        {
            alert("팝업이 차단되었습니다.");
        }
        return false;
    }
    */
    
    if ( paymethod == "bank")
        order_form.P_APP_BASE.value = "ON";
    order_form.target = "BTPG_WALLET";
    //order_form.target = "_self";
    order_form.action = "https://mobile.inicis.com/smart/" + paymethod + "/";
    order_form.submit();
}

function onSubmit()
{
    var order_form = document.ini;
    var inipaymobile_type = order_form.inipaymobile_type.value;
    var paymethod = order_form.paymethod.value;

/*
    if( inipaymobile_type == "app" && paymethod == "bank" )
        return false;
*/
    if( inipaymobile_type == "app" )
        return on_app();
    else if( inipaymobile_type == "web" )
        return on_web();
}


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
        else if( ( navigator.userAgent.indexOf("MSIE") >= 0 || navigator.appName == "Microsoft Internet Explorer" ) &&  (document.INIpay == null || document.INIpay.object == null) )  // 플러그인 설치유무 체크
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
function focus_control(){
    if(document.ini.clickcontrol.value == "disable")
        openwin.focus();
}

// 공통 사용 함수
jQuery(document).ready(function(){
    jQuery("input[name=paymethodtype]").click(function(index){  //결제방법 클릭시 해당 value값을 히든폼에 넣어줌
        jQuery("input[name=gopaymethod]").val(jQuery(this).val());
    });
});