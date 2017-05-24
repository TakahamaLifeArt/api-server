<?php
/*
*	トムスの在庫確認メールの受信処理
*	- postfixで受信したメールアカウントに連動して自動起動
*	log		: 2014-09-08 created
*/

require_once dirname(__FILE__).'/../mail/http.php';
require_once dirname(__FILE__).'/../MYDB2.php';

class TomsStock Extends MYDB2 {

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
			$mail_subject = 'トムス在庫確認';			// 件名
			
			$mail_contents = $body;
			// 本文
			$mail_contents .= "トムス在庫確認\n\n";
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
	*	トムス商品の在庫情報を更新
	*
	*	@args		{'item_code','color_code','size_name','amount','jancode'}
	*/
	public function update_toms_stockdata($args){
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
			$sql3 = "select catalog.id as master_id, item.id as itemid, size_from from ((catalog";
			$sql3 .= " inner join item on catalog.item_id=item.id)";
			$sql3 .= " inner join itemsize on item.id=itemsize.item_id)";
			$sql3 .= " inner join size on size_from=size.id";
			$sql3 .= " where item_code like ? and color_code=? and size_name=? and maker_id=1";
			$sql3 .= " and catalog.size_series=itemsize.series and catalogdate>? and itemdate>? and itemsizedate>?";
			$stmt_choose = $conn->prepare($sql3);
			// 新規登録
			$sql4 = "insert into itemstock(stock_master_id,stock_item_id,stock_size_id,stock_volume,stock_maker,jan_code,stock_updated) values(?,?,?,?,?,?,?)";
			$stmt_insert = $conn->prepare($sql4);
			// 在庫数を更新
			for($i=0; $i<count($args); $i++){
				$stmt_choose->bind_param("ssssss", $args[$i]['item_code'], $args[$i]['color_code'], $args[$i]['size_name'], $curdate, $curdate, $curdate);
				$stmt_choose->execute();
				$stmt_choose->store_result();
				$rec = parent::fetchAll($stmt_choose);
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
							$maker_id = 1;
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
			
			// トムスの未更新アイテムを削除
			$sql = "delete from itemstock where stock_maker=1 and stock_updated<'".$update_time."'";
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


require_once 'Mail/mimeDecode.php';
mb_language('ja');

// メール取得
$raw_mail = '';
/*
if ( ($stdin=fopen("php://stdin",'r')) == true ){
	while( !feof($stdin) ){
		$raw_mail .= fgets($stdin,4096);
	}
}
*/

if ( STDIN == true ){		// stdinへのオープン済みストリーム
	while( !feof(STDIN) ){
		$raw_mail .= fgets(STDIN,4096);
	}
}

require_once dirname(__FILE__).'/stock_response.php';

$inst = new TomsStock();
if(empty($data)){
	$args = '不明なメール: '.$decoder->attachments[0]['binary']."\n\n";
}else{
	// データベース更新
	$res = $inst->update_toms_stockdata($data);
	
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
			default:	$args = "Error: Others.\n\n";
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

?>
