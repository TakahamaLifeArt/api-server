<?php
/*
*	到着確認メール
*	 1.商品（アンケートページへのリンク有り）
*	 2.資料（保留）
*	お届予定日の2日後の12:00に自動送信
*/

require_once dirname(__FILE__).'/conndb.php';

class Arrival {

	public function __construct(){
	}
	
	
	/*
	*	@mode	1:商品
	*			2:資料
	*/
	public function send($orders, $mode){
		try{
			for($i=0; $i<count($orders); $i++){
				$attach = null;		// 添付ファイル情報
				$email = array();	// メールアドレス
				if(!empty($orders[$i]['email'])){	// PC
					if( preg_match('/@/', $orders[$i]['email']) ) $email[] = $orders[$i]['email'];
				}
				if(!empty($orders[$i]['mobmail'])){	// 携帯
					if( preg_match('/@/', $orders[$i]['mobmail']) ) $email[] = $orders[$i]['mobmail'];
				}
				if(!empty($orders[$i]['reqmail'])){	// 資料請求
					if( preg_match('/@/', $orders[$i]['reqmail']) ) $email[] = $orders[$i]['reqmail'];
				}
				if(empty($email)){
					continue;
				}
				
				if($mode==9){
					// 商品到着（QUOカードあり）
					
					// 件名
					//$mail_subject = '商品はお手元に届きましたでしょうか';
					$mail_subject = 'アンケートにお答えいただいた方全員にQUOカード進呈中！！';
					
					// お客様名
					$customer_name = "";
					if(!empty($orders[$i]['company'])){
						$customer_name .= $orders[$i]['customername']."\n　　　".$orders[$i]['company']."　　様\n\n";
					}else{
						$customer_name = $orders[$i]['customername']."　　様\n\n";
					}
					
					// 顧客ID（一般）
					$number = 'K'.str_pad($orders[$i]['number'], 6, '0', STR_PAD_LEFT);
					
					// アンケートページのアドレス
					$URL = "http://www.takahama428.com/contact/enquete.html?enq=".$orders[$i]['number'];
					
					// 本文
					$mail_contents = $customer_name;
					$mail_contents .= "この度はタカハマライフアートをご利用いただき、誠にありがとうございます。\n";
					$mail_contents .= "ご注文の商品は無事、到着いたしましたでしょうか？　仕上がりはご満足いただけましたでしょうか？\n";
					$mail_contents .= "３ヶ月以内でしたら、お客様の版を保存させていただいておりますので、追加注文にもすぐ対応させていただきます。\n";
					$mail_contents .= "新規のご注文の際には「リピート割」もご利用いただけますので、ぜひご利用下さいませ。\n\n";
					
					$mail_contents .= "★アンケートのお願い★\n";
					$mail_contents .= "タカハマライフアートでは、更なるお客様サービスの向上のため、下記サイトにてアンケートを実施しております。\n";
					$mail_contents .= "アンケートにお答えいただいた全てのお客様に５００円分のクオカードをプレゼントいたします。\n";
					$mail_contents .= "どうぞアンケートにご協力いただきますよう、お願いいたします。\n\n";
					
					$mail_contents .= "アンケートアドレス：　".$URL."\n\n";
					$mail_contents .= "！注意！　アンケートには、".$orders[$i]['customername']." 様の顧客ID（".$number."）が表示されます。\n\n";
					
					$mail_contents .= "また機会がありましたら、お気軽にご相談下さい。\n";
					$mail_contents .= "今後とも、タカハマライフアートをよろしくお願いいたします。\n\n";
					
				}else if($mode==1){
					// 商品到着（QUOカードなし）
					// 2014-01-01 から
					
					// 件名
					//$mail_subject = '商品はお手元に届きましたでしょうか';
					$mail_subject = '【アンケートのお願い】タカハマライフアートにお客様の声を聞かせてください！';
					
					// お客様名
					$customer_name = "";
					if(!empty($orders[$i]['company'])){
						$customer_name .= $orders[$i]['customername']."\n　　　".$orders[$i]['company']."　　様\n\n";
					}else{
						$customer_name = $orders[$i]['customername']."　　様\n\n";
					}
					
					// 顧客ID（一般）
					$number = 'K'.str_pad($orders[$i]['number'], 6, '0', STR_PAD_LEFT);
					
					// アンケートページのアドレス
					$URL = "http://www.takahama428.com/contact/enquete.html?enq=".$orders[$i]['number'];
					
					// 本文
					$mail_contents = $customer_name;
					$mail_contents .= "この度はタカハマライフアートをご利用いただき、誠にありがとうございます。\n";
					$mail_contents .= "ご注文の商品は無事、到着いたしましたでしょうか？　仕上がりはご満足いただけましたでしょうか？\n";
					$mail_contents .= "今回ご注文いただいた同デザインの追加注文につきましては、今後、特別価格にて提供させていただきますので、";
					$mail_contents .= "お気軽にお問い合わせ下さい。\n";
					$mail_contents .= "また、新規のご注文の際には「リピーター割」もございますので、ぜひご利用下さいませ。\n\n";
					
					$mail_contents .= "★アンケートのお願い★\n";
					$mail_contents .= "タカハマライフアートでは、更なるお客様サービスの向上のため、下記サイトにてアンケートを実施しております。\n";
					$mail_contents .= "どうぞアンケートにご協力いただきますよう、お願いいたします。\n\n";
					
					$mail_contents .= "アンケートアドレス：　".$URL."\n\n";
					$mail_contents .= "！注意！　アンケートには、".$orders[$i]['customername']." 様の顧客ID（".$number."）が表示されます。\n\n";
					
					$mail_contents .= "また機会がありましたら、お気軽にご相談下さい。\n";
					$mail_contents .= "今後とも、タカハマライフアートをよろしくお願いいたします。\n\n";
					
				}else{
					// 資料到着
					$mail_subject = '資料はお手元に届きましたでしょうか';	// 件名
					
					// お客様名
					$customer_name = $orders[$i]['requester']."　　様\n\n";
					
					// 本文
					$mail_contents = $customer_name;
					$mail_contents .= "この度は資料請求いただきありがとうございました。\n";
					$mail_contents .= "資料は到着しましたでしょうか？\n\n";
					
					$mail_contents .= "現在、資料請求していただいたお客様からのご注文は、５％割引の特典を実施しております。\n";
					$mail_contents .= "ぜひこの機会に、タカハマライフアートをご利用下さいませ。\n\n";
					
					$mail_contents .= "何かご不明な点、また、お急ぎの場合などございましたら、お電話でお問い合わせいただけると、すぐに対応させていただきます。\n";
					$mail_contents .= "お客様のイメージ通りの作品作りのお手伝いができますことを、心より願っております。\n\n";
					
				}
				
				
				// 休業の告知文
				$mail_contents .= _NOTICE_HOLIDAY;
				
				
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
				
				/*
				$res = unserialize($res);
				$reply = implode(',', $res);
				*/
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


// 10日前の日付を取得（お届日　schedule4）
$jd = new japaneseDate();
$baseSec = time();
$one_day = 86400;
$baseSec -= ($one_day*10);

// メール送信をお届日の２日後から１０日後に変更したため、送信済みを除外するため日付の確認
$checkSec = mktime(0, 0, 0, 9, 20, 2012);
if($baseSec>$checkSec){

	$fin = $jd->makeDateArray($baseSec);
	$arrivalDay = $fin['Year'].'-'.$fin['Month'].'-'.$fin['Day'];


	// 2日前がお届予定日で且つ発送済みの注文データを取得
	$conn = new ConnDB();
	$result = $conn->getOrderInfo(array('schedule4'=>$arrivalDay, 'shipped'=>2));
	if(empty($result)) exit;


	// 商品の到着確認メールを送信
	$inst = new Arrival();
	$inst->send($result, 1);
}


// 3日前の日付を取得（資料発送日　shippedreqdate）
//$baseSec -= $one_day;
//$fin = $jd->makeDateArray($baseSec);
//$shippedDay = $fin['Year'].'-'.$fin['Month'].'-'.$fin['Day'];

// 3日前に資料発送したユーザー情報を取得
//$result = $conn->getRequestInfo($shippedDay);
//if(empty($result)) exit;


// 資料の到着確認メールを送信
//$inst->send($result, 2);

?>
