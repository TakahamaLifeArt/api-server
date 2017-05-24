<?php
/*
*	キャブの在庫確認
*	FTPでCSVファイルをダウンロードしてデータベースを更新
*	log		: 2014-11-09 created
*			  2014-12-17 サイズリストに無いサイズの商品を除外する
*/
require_once dirname(__FILE__).'/FtpTransmission.php';
require_once dirname(__FILE__).'/../mail/http.php';
require_once dirname(__FILE__).'/../MYDB2.php';

class CabStock Extends MYDB2 {

	public function __construct(){
	}
	
	
	/*
	*	メール送信
	*
	*/
	public function send($body){
		try{
			
			$attach = null;								// 添付ファイル情報
			$email = array('test@takahama428.com');		// メールアドレス
			$mail_subject = 'キャブ在庫確認';			// 件名
			
			$mail_contents = $body;
			// 本文
			$mail_contents .= "キャブ在庫確認\n\n";
			$mail_contents .= "オリジナルＴシャツ屋　タカハマライフアート\n";
			$mail_contents .= "〒124-0025　東京都葛飾区西新小岩3-14-26\n";
			$mail_contents .= "（TEL）03-5670-0787\n";
			$mail_contents .= "（FAX）03-5670-0730\n";
			$mail_contents .= "E-mail："._INFO_EMAIL."\n";
			$mail_contents .= "URL：http://www.takahama428.com\n";
			
			
			// メール送信
			$http = new HTTP('http://www.takahama428.com/v1/via_mailer.php');
			$param = array(
				'mail_subject'=>$mail_subject,
				'mail_contents'=>$mail_contents,
				'sendto'=>$email,
				'reply'=>0
			);
			$res = $http->request('POST', $param);
			
			/*
			$res = unserialize($res);
			$reply = implode(',', $res);
			*/

		}catch (Exception $e) {
			//$reply = 'ERROR: メールの送信が出来ませんでした。';
		}
	}
	
	
	
	/*
	*	キャブ商品の在庫情報を更新
	*
	*	@args		{'item_code','color_code','size_name','amount','jancode'}
	*/
	public function update_stockdata($args){
		try{
			if(empty($args)) return;
			$rs = null;
			$err = array();
			$conn = parent::db_connect();
			// 在庫数を初期化
			$update_time = date('Y-m-d H:i:s');
			$curdate = date('Y-m-d');
			/*
			$sql = "update itemstock set stock_volume=0, stock_updated='".date('Y-m-d H:i:s')."'";
			$result = $conn->query($sql);
			if(!$result) throw new Exception('initialize');
			*/
			//$result->free();
			// 既存の在庫レコードの有無を確認
			//$sql1 = "select count(*) as cnt from (itemstock inner join catalog on catalog.id=stock_master_id) inner join item on item_id=item.id where jan_code=? and catalog.color_code=? and catalogdate='3000-01-01' and itemdate='3000-01-01'";
			$sql1 = "select count(*) as cnt from itemstock where jan_code=? and stock_master_id=?";
			$stmt_count = $conn->prepare($sql1);
			// 既存の在庫レコードがあれば更新
			$sql2 = "update itemstock set stock_volume=?, stock_updated=? where jan_code=? limit 1";
			$stmt_update = $conn->prepare($sql2);
			// 該当する登録データを取得
			$sql3 = "select catalog.id as master_id, item.id as itemid, size_from, item_code from ((catalog";
			$sql3 .= " inner join item on catalog.item_id=item.id)";
			$sql3 .= " inner join itemsize on item.id=itemsize.item_id)";
			$sql3 .= " inner join size on size_from=size.id";
			$sql3 .= " where (case when item_code like ? then item_code=? else item_code=? end) and color_code=? and size_name=?";
			$sql3 .= " and catalog.size_series=itemsize.series and catalogdate>? and itemdate>? and itemsizedate>?";
			$stmt_choose = $conn->prepare($sql3);
			// 新規登録
			$sql4 = "insert into itemstock(stock_master_id,stock_item_id,stock_size_id,stock_volume,stock_maker,jan_code,stock_updated) values(?,?,?,?,?,?,?)";
			$stmt_insert = $conn->prepare($sql4);
			// 在庫数を更新
			for($i=0; $i<count($args); $i++){
				$param1 = $args[$i]['item_code']."-%";
				$param2 = $args[$i]['item_code']."-".$args[$i]['item_subcode'];
				$stmt_choose->bind_param("ssssssss", $param1, $param2, $args[$i]['item_code'], $args[$i]['color_code'], $args[$i]['size_name'], $curdate, $curdate, $curdate);
				$stmt_choose->execute();
				$stmt_choose->store_result();
				$rec = parent::fetchAll($stmt_choose);
				$tmp = array();
				for($t=0; $t<count($rec); $t++){
					if(strpos($rec[$t]["item_code"], "-")!==false){
						$tmp[] = $rec[$t];
					}
				}
				if(!empty($tmp)) $rec = $tmp;
				for($t=0; $t<count($rec); $t++){
					if(empty($rec[$t]['master_id'])){
						$err[] = $args[$i]['item_code'].' - '.$args[$i]['color_code'].' - '.$args[$i]['size_name'];
					}else{
						$stmt_count->bind_param("ss", $args[$i]['jancode'],$rec[$t]['master_id']);
						$stmt_count->execute();
						$stmt_count->store_result();
						$rec2 = parent::fetchAll($stmt_count);
						if(empty($rec2[0]['cnt'])){
							$dat = $rec[$t];
							$stmt_insert->bind_param("iiiiiss", $dat['master_id'], $dat['itemid'], $dat['size_from'], $args[$i]['amount'], $maker_id, $args[$i]['jancode'], date('Y-m-d H:i:s'));
							$maker_id = 2;
							$stmt_insert->execute();
						}else{
							$stmt_update->bind_param("iss", $args[$i]['amount'], date('Y-m-d H:i:s'), $args[$i]['jancode']);
							$stmt_update->execute();
						}
					}
				}
			}
			if(empty($err)){
				$rs = 1;
			}else{
				$rs = $err;
			}
			
			// キャブの未更新アイテムを削除
			$sql = "delete from itemstock where stock_maker=2 and stock_updated<'".$update_time."'";
			$result = $conn->query($sql);
			if(!$result) throw new Exception('initialize');
		}catch (Exception $e) {
			if($e->getMessage()=='initialize'){
				$rs = 9;
			}else{
				$rs = 2;
			}
		}
		$stmt_count->close();
		$stmt_update->close();
		$stmt_choose->close();
		$stmt_insert->close();
		$conn->close();
		
		return $rs;
	}
}

// 在庫ファイル名
define('_TEMP_STOCK_FILE_NAME_', 'CBDZAIKO.CSV');

define('_HOST', 'ftp.cabclothing.jp');
define('_PORT', 21);
define('_TIME_OUT', 30);
define('_USERNAME', '00404900');
define('_PASS', 'c2Ymiudv');
define('_LOCAL_PATH', '/opt/lampp/htdocs/takahama/dev_takahamalifeart.com/www/html/cab/stock/');
define('_REMOTE_PATH', '/');

try{
	$ftp = new FtpTransmission();
	if($ftp->connect(_HOST, _USERNAME, _PASS, _PORT, _TIME_OUT)){
		if(!$ftp->download(_REMOTE_PATH._TEMP_STOCK_FILE_NAME_, _LOCAL_PATH)){
			$res = 'FTP Error: '._REMOTE_PATH._TEMP_STOCK_FILE_NAME_.".  LOCAL_PATH: "._LOCAL_PATH."  -  ".time();
		}
		$ftp->close();
	}else{
		$res = 'FTP Error.';
	}
}catch(Exception $e){
	echo $e->getMessage() . "\n";
	exit;
}


// CSVファイル定義
define('_DELIMITER', ',');	//データ区切り(カンマ)
define('_ENCLOSURE', '"');	//データ囲み文字(ダブルクォーテーション)

// サイズコード
$cab_size = array(
	'01'=>'XS','02'=>'S','03'=>'M','04'=>'L','05'=>'XL','06'=>'3L','07'=>'4L','08'=>'5L','09'=>'F',
	'47'=>'2L','48'=>'3L','49'=>'4L','50'=>'5L',
	'57'=>'70','58'=>'80','59'=>'90','60'=>'100','61'=>'110','62'=>'120','63'=>'130','64'=>'140','65'=>'150','66'=>'160',
	'74'=>'GF','75'=>'GS','76'=>'GM','77'=>'GL',
	'90'=>'JS','91'=>'JM','92'=>'JL',
);

try{
	// マルチパートのデータを取得する
	setlocale(LC_ALL,'ja_JP.euc-jp');	// fgetcsv で全角文字を使用するため
	
	$fpath = _LOCAL_PATH._TEMP_STOCK_FILE_NAME_;
	chmod($fpath, 0644);
	if($fp = fopen($fpath, 'r')){
		$orders_id = 0;
		$error_code = '';
		$short = array();
		//fgetcsv($fp, 4096, _DELIMITER, _ENCLOSURE);	// 1行目にタイトル行がある場合に除外
		while ($fld = fgetcsv($fp, 4096, _DELIMITER, _ENCLOSURE)) {
			if(is_null($fld[0])) continue;	// 空行
			if(empty($fld[3])) continue;	// JANコードが無い
			if(strpos($fld[5], '0')===0){
				$color_code = substr($fld[5],1);
			}else{
				$color_code = $fld[5];
			}
			if(!array_key_exists($fld[6], $cab_size)) continue;
			$data[] = array(
						'item_code'=>intval(substr($fld[4], 0, 5),10),
						'item_subcode'=>substr($fld[4], -2),
						'color_code'=>$color_code,
						'size_name'=>$cab_size[$fld[6]],
						'amount'=>intval($fld[7],10),
						'jancode'=>$fld[3],
					);
		}
	}

/* DEBUG
echo "DEBUG<br><br>";
for($i=0; $i<10; $i++){
	foreach($data[$i] as $key=>$val){
		echo $key.' - '.$val."<br>";
	}
	echo "------<br>";
}

exit(0);
*/


	$inst = new CabStock();
	if(empty($data)){
		$args = "不明なファイル\n\n";
	}else{
		// データベース更新
		$res = $inst->update_stockdata($data);
		
		if(is_array($res)){
			$args = "更新日時: ".date('Y-m-d H:i:s')."\n\n";
			$args .= "未更新アイテム\n";
			for($i=0; $i<count($res); $i++){
				$args .= "data: ".$res[$i]."\n";
			}
		}else{
			switch($res){
				case 1:		$args = "更新日時: ".date('Y-m-d H:i:s')."\n\n";
							
							/* DEBUG
							for($i=2494; $i<2505; $i++){
								$args .= "data: ".$data[$i]['jancode']."  -  ".$data[$i]['item_code']."  -  ".$data[$i]['color_code']."  -  ".$data[$i]['size_name']."\n";
							}
							*/
							
							break;
				case 2:		$args = "Error: Exception.\n\n";
							break;
				case 3:		$args = "Error: No data.\n\n";
							break;
				case 9:		$args = "Error: Initialization.\n\n";
							break;
				default:	$args = "Error: Others.\n".$res."\n\n";
			}
		}
	}
	
	/* debug
	$start = 0;
	for($i=$start; $i<$start+10; $i++){
		$args .= "data: ".$data[$i]['item_code']." - ".$data[$i]['color_code']." - ".$data[$i]['size_name']." - ".$data[$i]['amount']." - ".$data[$i]['jancode']."\n";
	}
	*/
	
	// 更新結果を送信
	//$inst->send($args);
}catch(Exception $e){
	exit('Exception');
}
?>
