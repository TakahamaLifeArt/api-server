<?php
/*
 *	トムスEDI発注の回答メール受信処理
 *	- postfixで受信したメールアカウントに連動して自動起動
 *	log		: 2013-11-22 created
 *			: 2014-01-10 発注回答の欠品状況からデータベースを更新
 *			: 2014-04-09 mysqliの使用と旧タイプの発注フラグ更新処理を追加
 * 			: 2016-05-27 発注回答メールの添付ファイル名変更により判別箇所を修正
 */

require_once dirname(__FILE__).'/../mail/http.php';
require_once dirname(__FILE__).'/../MYDB2.php';

class TomsEDI extends MYDB2 {

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
			$mail_subject = 'トムスEDI発注回答';			// 件名
			
			$mail_contents = $body;
			// 本文
			$mail_contents .= "トムスEDI発注回答\n\n";
			$mail_contents .= "オリジナルＴシャツ屋　タカハマライフアート\n";
			$mail_contents .= "〒124-0025　東京都葛飾区西新小岩3-14-26\n";
			$mail_contents .= "（TEL）03-5670-0787\n";
			$mail_contents .= "（FAX）03-5670-0730\n";
			$mail_contents .= "E-mail："._INFO_EMAIL."\n";
			$mail_contents .= "URL：http://www.takahama428.com\n";
			
			
			// メール送信
			//$this->send_mail($mail_subject, $mail_contents, $email, $attach);
			
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
	*	トムス発注回答の内容を登録
	*
	*	@orders_id		受注No.
	*	@short			欠品数の配列
	*	@error			トムスのエラーコード
	*/
	public function update_toms_response($orders_id, $short, $error){
		try{
			if(empty($orders_id) || !is_array($short)) return;
			$response = max($short);
			if(!empty($error) || !empty($response)){
				$response = 2;	// エラーまたは、欠品あり
			}else {
				$response = 1;	// 発注完了
			}
			$conn = parent::db_connect();
			$sql = "update progressstatus set toms_response=? where orders_id=?";
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("ii", $response, $orders_id);
			$stmt->execute();
			
			// 旧バージョンの発注フラグ
			if($response==1){
				// 発注担当者IDを取得
				$sql = "SELECT * from progressstatus where orders_id=?";
				$stmt = $conn->prepare($sql);
				$stmt->bind_param("i", $orders_id);
				$stmt->execute();
				$stmt->store_result();
				$rec = parent::fetchAll($stmt);
				
				// プリント進捗テーブルを更新
				$sql = "update printstatus set state_0=? where orders_id=?";
				$stmt = $conn->prepare($sql);
				$stmt->bind_param("ii", $rec[0]['ordering'], $orders_id);
				$stmt->execute();
			}
			
			/*
			db_connect();
			$sql = "update progressstatus set toms_response=%d where orders_id=%d";
			$sql = sprintf($sql, $response, $orders_id);
			$result = exe_sql($sql);
			*/
		}catch (Exception $e) {
			
		}
		$stmt->close();
		$conn->close();
		//mysql_close();
	}
}


require_once 'Mail/mimeDecode.php';
mb_language('ja');

// メール取得
if ( ($stdin=fopen("php://stdin",'r')) == true ){
	while( !feof($stdin) ){
		$raw_mail .= fgets($stdin,4096);
	}
}

require_once dirname(__FILE__).'/edi_response.php';

$inst = new TomsEDI();
if(!empty($file_number[1]) && strtolower($file_number[0])=='toms'){
	// データベース更新
	$inst->update_toms_response($orders_id, $short, $error_code);
	
	$args = "受注No. ".$orders_id."\n";
	$args .= "欠品数: ".implode(', ', $short)."\n";
	$args .= "エラー: ".$error_code."\n\n";
	
}else{
	$args = '不明なメール: '.$decoder->attachments[0]['binary']."\n\n";
}

// テストメールを送信
//$inst->send($args);

?>
