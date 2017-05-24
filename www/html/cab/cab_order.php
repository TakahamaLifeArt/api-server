<?php
/*
*	キャブEDI発注
*	return	1:成功, エラーコード:失敗
*
*	log:	2014-11-14 created
*/
/* DEBUG
define('_HOST', 'print-t.jp');
define('_PORT', 21);
define('_TIME_OUT', 30);
define('_USERNAME', 'print-t');
define('_PASS', 'takahama428_print-t');
//define('_LOCAL_PATH', '/cab/');
define('_REMOTE_PATH', '/home/print-t/www/test/');
*/

define('_HOST', 'ftp.cabclothing.jp');
define('_PORT', 21);
define('_TIME_OUT', 30);
define('_USERNAME', '00404900');
define('_PASS', 'c2Ymiudv');
//define('_LOCAL_PATH', '/var/www/html/cab/');
define('_REMOTE_PATH', '/');


if(!isset($_REQUEST['orders_id'])){
	exit('Error: parameter');
}

require_once 'cab.php';
require_once 'FtpTransmission.php';

$errors = array('その他エラー','',
	'受注データが取得できませんでした',
	'該当するJANコードがありません',
	'発注ファイルの生成に失敗しました'
);

try{
	$cab = new Cab();
	$res = $cab->orderform($_REQUEST);
	if(file_exists($res)){
		$filename = $res;
		$res = 1;
	}else{
		if($res<5){
			exit($errors[$res]);
		}else{
			exit("Error length:".$res);
		}
	}
	$ftp = new FtpTransmission();
	if($ftp->connect(_HOST, _USERNAME, _PASS, _PORT, _TIME_OUT)){
		if(!$ftp->upload($filename, _REMOTE_PATH)){
			$res = 'FTP Error: '.$filename.".  REMOTE_PATH: "._REMOTE_PATH;
		}
		$ftp->close();
	}else{
		$res = 'FTP Error.';
	}
}catch(Exception $e){
	echo "Exception Error; ".$e->getMessage() . "\n";
}
echo $res;
exit;
?>