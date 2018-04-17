<?php
define('_ALL_EMAIL', 'all@takahama428.com');
define('_INFO_EMAIL', 'info@takahama428.com');
define('_ORDER_EMAIL', 'order@takahama428.com');
define('_REQUEST_EMAIL', 'request@takahama428.com');
define('_ESTIMATE_EMAIL', 'estimate@takahama428.com');

define('_OFFICE_TEL', '03-5670-0787');
define('_OFFICE_FAX', '03-5670-0730');
define('_TOLL_FREE', '0120-130-428');

define('_MARGIN_1', 1.6);		// 149-299枚までの仕入れ値に対する掛け率
define('_MARGIN_2', 1.35);		// 300枚以上の仕入れ値に対する掛け率

define('_BEGINNING_OF_PERIOD', '4');
define('_APPLY_TAX_CLASS', '2014-05-26');	// 発送日が2014-05-26以降は外税方式を適用

require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/HolidayInfo.php';
$hol = new HolidayInfo();
$holidayHash = $hol->getData(array('notice'=>true));

define('_FROM_HOLIDAY', $holidayHash['start']);	// start day of the holiday
define('_TO_HOLIDAY', $holidayHash['end']);		// end day of the holiday
define('_NOTICE_HOLIDAY', $holidayHash['notice']);
define('_EXTRA_NOTICE', $holidayHash['notice-ext']);

/*
写真館ＡＰＩ
2017.2.13
*/
define('_DOC_ROOT', $_SERVER['DOCUMENT_ROOT'].'/');

define('_USER_PICTURE', 'picture/');
define('_USER_THUMB', 'thumbnail/');
define('_USER_ICON', 'uicon/');

define('_THUMB_WIDTH', 210);
define('_MAXIMUM_SIZE', 10485760);		// max upload file size is 10MB(1024*1024*10).
define('_ICON_WIDTH', 30);

define('_NUMBER_OF_PHOTO', 15);			// Number of photos per page
define('_MAXIMUM_NUMBER_OF_FILE', 20);

// PASSWORD KEY
define('_PASSWORD_SALT', 'Rxjo:akLK(SEs!8E');
define('_MAGIC_PASS', 'f629e76fcf2154594bde9199893a96e6e7d53e4e');

// TLA API
define('_ACCESS_TOKEN', 'cuJ5yaqUqufruSPasePRazasUwrevawU');
define('_HTTP_HEADER_KEY', 'X-TLA-Access-Token');
define('_MEMBER_HOSTS', array(_ACCESS_TOKEN=>array('www.takahama428.com', 'test.takahama428.com', 'original-sweat.com', 'test.original-sweat.com')));

// SQL
define('_DB_USER', 'tlauser');
define('_DB_PASS', 'crystal428');
define('_DB_HOST', 'localhost');
define('_DB_NAME', 'tladata1');			// 公開
define('_DB_NAME_DEV', 'tladata2');		// テスト開発環境

// Sales Tax 0:非課税, 1:外税, 2:内税
define('_TAX_CLASS', 1);
?>
