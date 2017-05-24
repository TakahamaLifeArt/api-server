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

	define('_FROM_HOLIDAY', '2016/08/12');	// start day of the holiday
	define('_TO_HOLIDAY', '2016/08/12');		// end day of the holiday
	
	$_NOTICE_HOLIDAY = "\n<==========  夏季休業のお知らせ  ==========>\n";
	$_NOTICE_HOLIDAY .= "8月11日(木)から8月14日(日)の間、休業とさせて頂きます。\n";
	$_NOTICE_HOLIDAY .= "休業期間中に頂きましたお問合せにつきましては、8月15日(月)以降対応させて頂きます。\n";
	$_NOTICE_HOLIDAY .= "お急ぎの方はご注意下さい。何卒よろしくお願い致します。\n\n";
	
	$_NOTICE_HOLIDAY = '';
	
	define('_NOTICE_HOLIDAY', $_NOTICE_HOLIDAY);

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


?>
