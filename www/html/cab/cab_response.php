<?php
/*
*	キャブEDI発注の回答ファイルの確認とデータベースの発注状況を更新
*	log		: 2014-11-15 created
*			: 2019-05-29 例外のメッセージを更新
*/

/*
// エラーコード
$error_status = array(
	"30"=>"お客様管理No.",
	"31"=>"直送先、郵便番号、都道府県、住所、電話番号のいずれか",
	"32"=>"ヘッダの明細件数と発注明細の件数が相違",
	"33"=>"入金方法",
	"34"=>"不足の場合の処理",
	"35"=>"直送先都道府県が実在しない",
	"39"=>"発注ヘッダに関するその他エラー",
	"40"=>"JAN、商品、カラー、サイズコード",
	"41"=>"同じ管理No.内に重複する明細No.が存在する",
	"42"=>"発注数が0以下",
	"48"=>"発注受付不可",
	"49"=>"発注明細に関するその他エラー",
);

// ヘッダー
$header_offset = array(1,2,10,16,34,74,78,108,116,124,154,184,198,202,203,205);

// 明細
$detail_offset = array(1,2,10,16,34,38,51,58,62,64,68,72,76,78);
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
	$res = array();
	
	$cab = new Cab();
	// 発注ファイル取得
	$list = $cab->getFileList("./order", "M[0-9]????????????[0-9]");
	if(empty($list)){
		throw new Exception('no such order file exists.');
	}
	$orderfile = array();
	$cnt = count($list);
	for($i=0; $i<$cnt; $i++){
		$data = file($list[$i], FILE_IGNORE_NEW_LINES);
		$orders_id = rtrim(substr($data[0], 1, 18));
		$orderfile[$orders_id] = $list[$i];
	}
	
	// ローカルの回答ファイルを削除
	$root_path = dirname(__FILE__).'/response';
	foreach (glob("$root_path/*") as $filename){
		unlink($filename);
	}
	
	// リモートの回答ファイルをダウンロード
	$ftp = new FtpTransmission();
	if($ftp->connect(_HOST, _USERNAME, _PASS, _PORT, _TIME_OUT)){
		$ls = $ftp->nlist(_REMOTE_PATH);
		$cnt = count($ls);
		for($i=0; $i<$cnt; $i++){
			if(strpos($ls[$i], "J")!==1) continue;	// /J???
			if(!$ftp->download(_REMOTE_PATH.$ls[$i], _LOCAL_PATH)){
				// DEBUG
				throw new Exception($ls[$i]);
			}
		}
		$ftp->close();
	}else{
		throw new Exception('FTP Error.');
	}
	
	$list = $cab->getFileList("./response", "J[0-9]????????????[0-9]");
	if(empty($list)){
		// throw new Exception('no such response file exists.');
	}
	
	// 発注回答を確認してデータベース更新
	$responsefile = array();
	$cnt = count($list);
	for($i=0; $i<$cnt; $i++){
		$data = file($list[$i], FILE_IGNORE_NEW_LINES);
		$orders_id = rtrim(substr($data[0], 15, 18));
		$error_code = substr($data[0], 202, 2);
		if(!array_key_exists($orders_id, $orderfile)){
			$responsefile[] = basename($list[$i]);
			continue;
		}
		
		// データベース更新
		$result = $cab->update_response($orders_id, $error_code);
		if($result!=1){
			// データベース更新エラー
			$res[] = $orders_id;
		}else{
			if($error_code==0){
				// 発注完了の場合に発注ファイルを削除
				unlink($orderfile[$orders_id]);
				$responsefile[] = basename($list[$i]);
			}else{
				// 発注エラーはpendingディレクトリに移動
				rename($list[$i], "./pending/".basename($list[$i]));
			}
		}
	}
	
	// 発注完了した注文のリモートの受付通知結果ファイルを削除
	if(!empty($responsefile)){
		if($ftp->connect(_HOST, _USERNAME, _PASS, _PORT, _TIME_OUT)){
			$cnt = count($responsefile);
			for($i=0; $i<$cnt; $i++){
				$ftp->delete_file(_REMOTE_PATH.$responsefile[$i]);
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
?>
