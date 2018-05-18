<?php

/*
*	トムスEDI発注
*	return	1:成功, 2:該当データなし, 3:その他エラー
*
*	log:	2013-11-01 created
*			2014-01-09 Class Toms を作成
*			2014-01-10 Class SFTPConnection を作成
*			2014-04-30 本稼動用に環境設定を更新
*			2014-06-11 運送業者, 土曜配送, 日曜祝日配送, PP袋有無, の指定を追加
*					   送付先の指定を追加
*			2018-05-18 FTP接続クラスを使用
*/

// define('_HOST', 'sftp.tradinggrid.gxs.com');
// define('_HOST', 'edi-com.cyber-l.co.jp');
// 2017.02.28 修正
define('_HOST', 'edi-com.edi-asp.jp');
define('_PORT', 21);
define('_USERNAME', 'takahama01');
define('_PASS', 'BE24RNIJ');
define('_TIME_OUT', 90);
define('_LOCAL_PATH', '/opt/lampp/htdocs/takahama/dev_takahamalifeart.com/www/html/toms/');
define('_REMOTE_PATH', '/Order/');

if(!isset($_REQUEST['orders_id'])){
	exit('Error: parameter');
}


// 2018-05-18 廃止
//require_once 'SFTPConnection.php';

require_once 'FtpTransmission.php';
require_once 'toms.php';

$errors = array('その他エラー','',
	'受注データが取得できませんでした',
	'該当するJANコードがありません',
	'CSVファイルの生成に失敗しました'
);

$toms = new Toms();
$res = $toms->orderform($_REQUEST);
if($res!=1) exit($errors[$res]);

$order_date = date('Ymd');
$local_path = _LOCAL_PATH.'order'.$order_date.'/';
$filename = 'order'.$order_date.'_'.$_REQUEST['orders_id'].'.csv';

try{
	// $sftp = new SFTPConnection(_HOST, _PORT);
	// $sftp->login(_USERNAME, _PASS);
	// $sftp->uploadFile($local_path.$filename, _REMOTE_PATH.$filename);

// 2018-05-18 SFTPConnection.php を廃止に伴い
//    $ftp = ftp_connect(_HOST, _PORT);
//
//    ftp_login($ftp, _USERNAME, _PASS);
//
//    ftp_pasv($ftp, false);
//
//    ftp_put($ftp, _REMOTE_PATH.$filename, $local_path.$filename, FTP_BINARY);
//
//	ftp_quit($ftp);


	$ftp = new FtpTransmission();
	if($ftp->connect(_HOST, _USERNAME, _PASS, _PORT, _TIME_OUT)){
		if(!$ftp->upload($local_path.$filename, _REMOTE_PATH.$filename, FTP_BINARY)){
			$res = 'FTP Error: '.$filename.".  REMOTE_PATH: "._REMOTE_PATH;
		}
		$ftp->close();
	}else{
		$res = 'FTP Error.';
	}
}catch (Exception $e){
	echo "Exception Error; ".$e->getMessage() . "\n";

}

echo $res;

exit;

?>
