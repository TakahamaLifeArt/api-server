<?php
/*
*	制作（プリント中）開始しましたメール
*	注文確定日の翌営業日の9:30に自動送信
*/

require_once dirname(__FILE__).'/conndb.php';
use package\holiday\DateJa;

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
				$mail_contents .= "URL：https://www.takahama428.com\n";
				
				
				
				// メール送信
				//$this->send_mail($mail_subject, $mail_contents, $email, $attach);
				
				require_once dirname(__FILE__).'/http.php';
				$http = new HTTP('https://www.takahama428.com/v1/via_mailer.php');
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
}


// 休業日の場合は何もしない
$ja = new DateJa();
$_from_holiday = strtotime(_FROM_HOLIDAY);
$_to_holiday	= strtotime(_TO_HOLIDAY);
$baseSec = time();
$fin = $ja->makeDateArray($baseSec);
if( (($fin['Weekday']==0 || $fin['Weekday']==6) || $fin['Holiday']!=0) || ($baseSec>=$_from_holiday && $_to_holiday>=$baseSec) ){
	exit;
}


// 今日より前の営業日を取得（注文確定日　schedule2）
$one_day = 86400;
$baseSec -= $one_day;
$fin = $ja->makeDateArray($baseSec);
while( (($fin['Weekday']==0 || $fin['Weekday']==6) || $fin['Holiday']!=0) || ($baseSec>=$_from_holiday && $_to_holiday>=$baseSec) ){
	$baseSec -= $one_day;
	$fin = $ja->makeDateArray($baseSec);
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
