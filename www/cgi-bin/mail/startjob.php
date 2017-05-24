<?php
/*
*	制作（プリント中）開始しましたメール
*	注文確定日の翌営業日の9:30に自動送信
*/

require_once dirname(__FILE__).'/conndb.php';

class Startjob {

	public function __construct(){
	}
	
	
	public function send($orders){
		try{
			for($i=0; $i<count($orders); $i++){
				$attach = null;		// 添付ファイル情報
				$email = array();	// PCと携帯のメールアドレス
				if(!empty($orders[$i]['email'])){
					if( preg_match('/@/', $orders[$i]['email']) ) $email[] = $orders[$i]['email'];
				}
				if(!empty($orders[$i]['mobmail'])){
					if( preg_match('/@/', $orders[$i]['mobmail']) ) $email[] = $orders[$i]['mobmail'];
				}
				if(empty($email)){
					continue;
				}	
				
				// 件名	
				$mail_subject = '商品の制作を開始いたしました';
				
				// お客様名
				$customer_name = "";
				if(!empty($orders[$i]['company'])){
					$customer_name .= $orders[$i]['customername']."\n　　　".$orders[$i]['company']."　　様\n\n";
				}else{
					$customer_name = $orders[$i]['customername']."　　様\n\n";
				}
				
				// 本文
				$mail_contents = $customer_name;
				$mail_contents .= "この度はタカハマライフアートにご注文いただき、誠にありがとうございます。\n";
				$mail_contents .= "只今、ご注文いただいております、お客様の商品制作をスタートいたしました。\n";
				$mail_contents .= $orders[$i]['customername']." 様のイメージ通りの商品になるよう、スタッフ一同、心をこめて、制作いたします。\n";
				$mail_contents .= "商品到着まで、楽しみにお待ち下さいませ。\n\n";
				
				
				// 休業の告知文
				$mail_contents .= _NOTICE_HOLIDAY;
				
				
				$mail_contents .= "何かご不明な点やお気づきのことがございましたら、ご遠慮なくお問い合わせ下さい。\n";
				$mail_contents .= "■営業時間　10:00-18:00　■定休日　土日祝。\n\n";
				
				$mail_contents .= "オリジナルＴシャツ屋　タカハマライフアート\n";
				$mail_contents .= "〒124-0025　東京都葛飾区西新小岩3-14-26\n";
				$mail_contents .= "（TEL）03-5670-0787\n";
				$mail_contents .= "（FAX）03-5670-0730\n";
				$mail_contents .= "E-mail："._INFO_EMAIL."\n";
				$mail_contents .= "URL：http://www.takahama428.com\n";
				
				
				
				// メール送信
				//$this->send_mail($mail_subject, $mail_contents, $email, $attach);
				
				require_once dirname(__FILE__).'/http.php';
				$http = new HTTP('http://www.takahama428.com/v1/via_mailer.php');
				$param = array(
					'mail_subject'=>$mail_subject,
					'mail_contents'=>$mail_contents,
					'sendto'=>$email,
					'reply'=>0
				);
				$res = $http->request('POST', $param);
				
			}

		}catch (Exception $e) {
			//$reply = 'ERROR: メールの送信が出来ませんでした。';
		}
	}




	/** 2013-03-07 廃止
	*	メール送信
	*	@mail_subject	題名
	*	@mail_contents	メール本文
	*	@sendto			返信先のメールアドレス
	*	@attach			添付ファイル情報
	*	返り値			true:送信成功 , false:送信失敗
	*/

	private function send_mail($mail_subject, $mail_contents, $sendto, $attach){
		mb_language("japanese");
		mb_internal_encoding("EUC-JP");

/* 2012-06-22
		$fromname = "タカハマライフアート";
		$from = mb_encode_mimeheader(mb_convert_encoding($fromname,"JIS","utf-8"))."<"._INFO_EMAIL.">";
*/
		$from = _INFO_EMAIL;
		$subject  = "$mail_subject";						// 件名
		$msg = "";											// 送信文
		$boundary = md5(uniqid(rand())); 					// バウンダリー文字（メールメッセージと添付ファイルの境界とする文字列を設定）

		$header = "From: $from\n";
		$header .= "Reply-To: $from\n";
		//$header .= 'Bcc: '._ORDER_EMAIL."\n";
		$header .= "X-Mailer: PHP/".phpversion()."\n";
		$header .= "MIME-version: 1.0\n";

		if(!empty($attach)){ 		// 添付ファイルがあり
			$header .= "Content-Type: multipart/mixed;\n";
			$header .= "\tboundary=\"$boundary\"\n";
			$msg .= "This is a multi-part message in MIME format.\n\n";
			$msg .= "--$boundary\n";
			$msg .= "Content-Type: text/plain; charset=ISO-2022-JP\n";
			$msg .= "Content-Transfer-Encoding: 7bit\n\n";
		}else{												// 添付ファイルなし
			$header .= "Content-Type: text/plain; charset=ISO-2022-JP\n";
			$header .= "Content-Transfer-Encoding: 7bit\n";
		}

		$msg .= mb_convert_encoding($mail_contents,"JIS","utf-8");	// ここで注文情報をエンコードして設定

		if(!empty($attach)){		// 添付ファイル情報
			for($i=0; $i<count($attach); $i++){
				$msg .= "\n\n--$boundary\n";
				$msg .= "Content-Type: " . $attach[$i]['type'] . ";\n";
				$msg .= "\tname=\"".$attach[$i]['name']."\"\n";
				$msg .= "Content-Transfer-Encoding: base64\n";
				$msg .= "Content-Disposition: attachment;\n";
				$msg .= "\tfilename=\"".$attach[$i]['name']."\"\n\n";
				$msg .= $attach[$i]['file']."\n";
			}
			$msg .= "--$boundary--";
		}

		// 件名のマルチバイトをエンコード
		$subject  = mb_encode_mimeheader(mb_convert_encoding($subject,"JIS","utf-8"));

		// メール送信
		$res = true;
		for($i=0; $i<count($sendto); $i++){
			if(!mail($sendto[$i], $subject, $msg, $header)){
				$res = false;
				break;
			}
		}
		
        return $res;
	}

}


// 休業日の場合は何もしない
$jd = new japaneseDate();
$_from_holiday = strtotime(_FROM_HOLIDAY);
$_to_holiday	= strtotime(_TO_HOLIDAY);
$baseSec = time();
$fin = $jd->makeDateArray($baseSec);
if( (($fin['Weekday']==0 || $fin['Weekday']==6) || $fin['Holiday']!=0) || ($baseSec>=$_from_holiday && $_to_holiday>=$baseSec) ){
	exit;
}


// 今日より前の営業日を取得（注文確定日　schedule2）
$one_day = 86400;
$baseSec -= $one_day;
$fin = $jd->makeDateArray($baseSec);
while( (($fin['Weekday']==0 || $fin['Weekday']==6) || $fin['Holiday']!=0) || ($baseSec>=$_from_holiday && $_to_holiday>=$baseSec) ){
	$baseSec -= $one_day;
	$fin = $jd->makeDateArray($baseSec);
}
$orderDay = $fin['Year'].'-'.$fin['Month'].'-'.$fin['Day'];


// 注文確定日を指定して注文データを取得
$conn = new ConnDB();
$result = $conn->getOrderInfo(array('schedule2'=>$orderDay));
if(empty($result)) exit;


// 送信
$inst = new Startjob();
$inst->send($result);

?>
