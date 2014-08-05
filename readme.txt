=== INICIS for WooCommerce ===
Contributors: CODEM(c), CodeMShop, MShop, Inicis
Donate link: http://www.codemshop.com 
Tags: WooCommerce, eCommerce, Inicis, Payment, Gateway, PG, KG, KGINICIS, wordpress, MShop, CodeMStory, CodeMShop, CODEM(c), 이니시스, 우커머스, 결제, 코드엠, 엠샵
Requires at least: 3.9.1
Tested up to: 3.9.1
Stable tag: 2.0.3
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

엠샵에서 개발한 KG 이니시스의 워드프레스 우커머스 이용을 위한 결제 시스템 플러그인 입니다.

== Description ==

워드프레스 쇼핑몰 우커머스에 사용이 가능한 결제 플러그인 입니다.
"INICIS – for WooCommerce" plugin is available for Wordpress's 'WooCommerce' Plugin .

= 결제 지원 범위(Support Features) =
* PC Desktop : 신용카드(Credit Card), 은행 계좌이체(Direct Bank Transfer)
* Mobile(Smart Phone) : 신용카드(Credit Card), 은행 계좌이체(Direct Bank Transfer)
* 카드 포인트 사용(Option To Use Credit Card Point)
* PG 플러그인 스킨 색상 지정(Setting For Changing Skin Color of Payment Gateway Program)
* 할부 개월수 지정(Option To Select Installments)
* 무이자 할부 개월수 지정(Option To Set Interest-free Instalments)

= 간편한 결제 설정(Easy Payment Setting) =
* 개발자를 통하지 않고, PG 계약 이후에 제공받은 KEY 파일을 설정하고 몇가지 간단한 설정을 지정하시면 곧바로 사용이 가능합니다.(Payment Gateway service is available for you with simple setting in wordpress admin panel without going through any development overhead) 

= 맞춤형 서비스 지원 가능(Available Customize) =
* 소스가 공개되어 있어, 개발자 여러분들이 원하시는 대로 소스를 수정하여 사용할 수 있습니다.(The source is available for developers to modify.)

= 온라인 결제를 위한 KG 이니시스 서비스 신청 안내(KG Inicis Services Application Guide) = 
* 사업자 확인 및 카드사 심사를 위해 이니시스 결제 서비스 신청 후 정상적인 서비스를 이용하실 수 있습니다.
 (To use this plugin service properly you must gone through business License Number Check by signing up with INICIS Service and also Credit Card Companies Settlement Examination. )
  
* PG 서비스 상세 이용 설명(For Detailed description of the PG service please go through) : http://www.inicis.com/, http://www.inicis.com/eng/

* PG 서비스 신청 지원(PG Service Application Support) : http://www.codemshop.com

== Installation ==

= 사용 가능 환경(Requirements) =

* 워드프레스 3.9.1 또는 최신 버전 (Wordpress 3.9.1 or later)
* PHP 5.2.4 또는 최신 버전 (PHP 5.2.4 or later)
* PHP 확장(Extension): OpenSSL, LibXML, mcrypt, socket 설치필요 (--with-openssl, --with-mcrypt, --enable-sockets)
* MySQL 5.0 또는 최신 버전 (MySQL 5.0 or later)
* 방화벽 설정 확인 (Check Firewall Setting Manual Provided By KG INICIS ) 

= 수동 설치 방법(Manually Install) =

수동으로 설치할 경우에는 플러그인을 다운로드 받아서 웹서버에 원하는 FTP 프로그램을 이용해서 플러그인을 업로드하여 설치하시면 됩니다.
(To Install plugin manually to the Web Server. First download it, then upload it using FTP program and then install the plugin.)

* 플러그인을 컴퓨터에 다운로드 받아 압축을 풉니다.(Plugin download and unzip on your computer.)
* FTP 프로그램을 이용하거나, 호스팅의 관리페이지 또는 플러그인 업로드 페이지를 이용해서 워드프레스가 설치된 경로의 하위에 /wp-content/plugins/ 디렉토리안에 압축을 푼 파일을 업로드 합니다.
(Using an FTP program, or your hosting control panel, upload the unzipped plugin folder to your WordPress installation’s wp-content/plugins/ directory.)
* 워드프레스 관리자 페이지 플러그인 메뉴에서 해당 플러그인을 활성화 시킵니다.(Activate the plugin from the Plugins Panel within the WordPress admin.) 

== Frequently Asked Questions ==

* TX로 시작하는 에러가 발생합니다.
이니시스 사이트에 보시면 에러 코드를 이용하여 에러 내용을 조회할 수 있습니다. 해당 내용을 확인해 보시기 바랍니다. 
(Showing TX error.
 Check the TX error code. Please Find This Error Code On INICIS Website http://www.inicis.com/err_search)

* TX로 시작하는 에러 코드를 조회했는데 권한 문제라고 나옵니다. 어떻게 해야 하나요? 
플러그인 내부에 포함되어 있는 /pg/inicis/key/ 폴더는 chmod -R 755 권한으로, /pg/inicis/log/ 폴더는 chmod -R 777 권한으로 각각 권한을 주시고 다시 시도해보시기 바랍니다. 
( It Showing TX permissions Error but everything it looking fine, what should I do?
 Check the permissions of folders inside plugin, set permissions of /pg/inicis/key/ folder to 755 "using chmod -R 755" and  /pg/inicis/log/ folder permissions to 777 "using chmod -R 777".)

* "PHP 확장 설치 필요"라는 메시지를 보게 되었습니다. 어떻게 해야 하나요?
PHP 확장은 결제 서비스를 사용하는데 필요한 필수 요소입니다. 이 부분은 서버 관리자가 환경을 구성해주어야만 되는 문제로, 서버 관리자에게 문의하여 주셔야 합니다.
(Its showing "PHP extension installation required" message. What should I do?
 This means PHP extension is required to use payment services.So Please contact to your hosting service provider and ask him to configure the environment.)

* 잘 이해가 되지 않습니다. 도움을 요청할수 있을까요?
잘 모르는 부분이 있으시다면, support@codemstory.com 으로 이메일을 주시거나, 이곳 Support 탭 또는 http://inicis.codemshop.com 사이트에 있는 게시판에 글을 남겨주시기 바랍니다. 
(For any other queries.
you may contact to us by sending email to support@codemstory.com or feel free to write to us on  http://inicis.codemshop.com website or Wordpress.org Plugin Directory under Support tab. )

== Screenshots ==

1. 환경설정 화면(1) / Setting Screen(1)
2. 환경설정 화면(2) / Setting Screen(2)
3. 지불 페이지 / Client Payment Page
4. 실제 결제 플러그인 동작 화면 / Payment Plugin Working Screen
5. 모바일/스마트폰 결제 동작 화면 / Mobile Payment Working Screen

== Changelog ==

= 2.0.3 - 2014/08/05 =
* 결제 완료 페이지 처리 변경
  Change Order-Received Process.
* 기타 버그 수정
  Etc, Bug Fix.

= 2.0.2 - 2014/08/01 =
* 라이브러리 폴더 변경 처리 수정
  Library Folder Path Change Process Fix.
* 기타 버그 수정
  Etc, Bug Fix.
    

= 2.0.1 - 2014/07/31 =
* 언어팩 수정 및 기타 항목 수정
  Language Pack Translate add and Other things Fix. 

= 2.0.0 - 2014/07/30 =

* 소스 구조 변경
  Source Structure Change.
* 모바일 결제 처리 변경
  Mobile Payment Process Fix.
* 결제 페이지에서 결제 가능
  Possible Payment Process to Checkout page.
* 관리자 화면 구성 변경
  Change Payment Option Manage page.
* 상점 키파일 업로드 기능 추가
  Possible to Shopping Mall Keyfile Upload at Payment Option Manage page.
* 대기시간 초과 상품 처리 추가
  Add Timeout Waiting Order Process.    


= 1.0.5 - 2014/07/10 =

* 관리자 주문 환불 처리 수정
  Admin Order Refund Process Fix.


= 1.0.4 - 2014/07/07 =

* 모바일 결제시 ISP 처리 수정
  Mobile Checkout Process Fix about ISP paymethod.


= 1.0.3 - 2014/04/05 =

* 모바일 결제시 ISP 결제 처리 코드 추가
  Mobile Checkout Process add code about ISP paymethod.
* 결제 플러그인 관련 공지사항 추가
  Payment Gateway Notice Funtion add.
* 결제 플러그인 파일명과 폴더명 변경
  Plugin Filename and Folder Name Change.


= 1.0.2 - 2014/03/12 =

* IE8에서 스크립트 오류 수정
  IE8 Javascript Bug Fix.


= 1.0.1 - 2014/02/17 =

* 우커머스 2.1.0 업데이트 호환 대응 처리 (WooCommerce 2.1.2 + Wordpress 3.8.1 에서 테스트 완료)
  Support WooCommerce 2.1.x Now! (Tested WooCommerce 2.1.2)
* 신규 옵션 추가 - 사용자/관리자 환불가능 주문상태, 결제완료/취소시 변경될 주문상태 옵션 추가 (4개 항목 추가)
  New Option Added. User/Admin Possible Refund Status Option and After Payment Complete or Cancel, change Order Status Option. 
* 사용자 내 계정(My-Account) 페이지에서의 환불 요청 처리 기능 추가
  Order Cancel Requset Function added in 'My Account Order Detail View' page. 
* 관리자 페이지 주문 편집시 우측에 '이니시스 결제 주문 환불 처리' 메타 박스 추가 및 환불 처리 기능 추가
  Order Cancel Requset Function Metabox added in 'Order Edit' page.
* 플러그인 셋팅 링크 제거
  Remove 'Settings' Link at Plugins List.


= 1.0.0 - 2014/01/10 =

* 최초 버전 릴리즈. (First version Release)

== Upgrade Notice ==

= 2.0.0 =
주의사항! 1.0.x 버전 사용자 분들은 업그레이드시에 키파일과 로그파일들이 삭제되오니 필히 백업 후에 업데이트를 진행하시기 바랍니다. 
Warning! 1.0.x Version Users, please backup keyfile and log files before update. because, if you keep going update, it remove inside keyfile and log files.

