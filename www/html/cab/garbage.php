<?php
/*
*	発注エラーまたは不足分ありで保留になっている注文を済みにした際の発注及び回答ファイルの削除
*/
require_once dirname(__FILE__).'/cab.php';
require_once dirname(__FILE__).'/FtpTransmission.php';

// FTPパラメーター
define('_HOST', 'ftp.cabclothing.jp');
define('_PORT', 21);
define('_TIME_OUT', 30);
define('_USERNAME', '00404900');
define('_PASS', 'c2Ymiudv');
define('_LOCAL_PATH', '/opt/lampp/htdocs/takahama/dev_takahamalifeart.com/www/html/cab/response/');
define('_REMOTE_PATH', '/');

try{
	$orders_id = $_REQUEST["orders_id"];
	$res = "";
	$cab = new Cab();
	
	// 発注ファイル取得
	$list = $cab->getFileList("./order", "M[0-9]????????????[0-9]");
	if(empty($list)){
		//throw new Exception('no such order file exists.');
	}
	
	// 発注ファイル削除
	$cnt = count($list);
	for($i=0; $i<$cnt; $i++){
		$data = file($list[$i], FILE_IGNORE_NEW_LINES);
		$user_number = rtrim(substr($data[0], 1, 18));
		if($user_number!=$orders_id) continue;
		$orderlist = $list[$i];
	}
	
	// 回答保留ファイル取得
	$list = $cab->getFileList("./pending", "J[0-9]????????????[0-9]");
	if(empty($list)){
		//throw new Exception('no such pending file exists.');
	}
	$pendingorder = "";
	$cnt = count($list);
	for($i=0; $i<$cnt; $i++){
		$data = file($list[$i], FILE_IGNORE_NEW_LINES);
		$user_number = rtrim(substr($data[0], 15, 18));
		if($user_number!=$orders_id) continue;
		$pendingorder = $list[$i];
	}
	
	// リモートの受付通知結果ファイル削除
	if(!empty($pendingorder)){
		$ftp = new FtpTransmission();
		if($ftp->connect(_HOST, _USERNAME, _PASS, _PORT, _TIME_OUT)){
			if($ftp->delete_file(_REMOTE_PATH.basename($pendingorder))){
				if(is_file($pendingorder)) unlink($pendingorder);
				if(is_file($orderlist)) unlink($orderlist);
			}
			$ftp->close();
		}else{
			throw new Exception('FTP Error by Delete.');
		}
	}
}catch(Exception $e){
	$res = "Exception Error; ".$e->getMessage();
}
echo serialize($res);

/*
date_default_timezone_set('Asia/Tokyo');
 
//削除期限
$expire = strtotime("24 hours ago");
 
//ディレクトリ
$dir = dirname(__FILE__) . '/dir/';
 
$list = scandir($dir);
foreach($list as $value){
    $file = $dir . $value;
    if(!is_file($file)) continue;
    $mod = filemtime( $file );
    if($mod < $expire){
        //chmod($file, 0666);
        unlink($file);
    }
}
*/
?>