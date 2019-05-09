<?php
/*
*	注文が未確定であることを伝えるメール
*	注文確定日の10:00に自動送信
*/

require_once dirname(__FILE__).'/conndb.php';
use Alesteq\DateJa\DateJa;

class Pendingorder {

	private $ja;
	
	public function __construct(){
		$this->ja = new DateJa();
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
				
				$date = explode('-',$orders[$i]['schedule4']);
				if($date[0]!="0000"){
					$baseSec = mktime(0, 0, 0, $date[1], $date[2], $date[0]);
					$deli = $this->ja->makeDateArray($baseSec);							// 受渡日付情報
				}else{
					$deli['Weekname'] = "-";
				}
				
				// 件名
				$mail_subject = 'ご注文はまだ確定しておりません';
				
				// お客様名
				$customer_name = "";
				if(!empty($orders[$i]['company'])){
					$customer_name .= $orders[$i]['customername']."\n　　　".$orders[$i]['company']."　　様\n\n";
				}else{
					$customer_name = $orders[$i]['customername']."　　様\n\n";
				}
				
				// 本文
				$mail_contents = $customer_name;
				$mail_contents .= "この度はタカハマライフアートへのお問い合わせ、ありがとうございます。\n\n";
				$mail_contents .= "お問い合わせいただいております、下記の".$orders[$i]['customername']."様のご注文はまだ確定しておりません。\n\n";
				$mail_contents .= "希望納期：".$deli['Month']."月".$deli['Day']."日（".$deli['Weekname']."）\n";
				$mail_contents .= "総枚数：".$orders[$i]['order_amount']."枚\n";
				$mail_contents .= "お見積り金額：".number_format($orders[$i]['estimated'])."円（税込）\n\n";
				$mail_contents .= "ご希望の納期にお届けする為には、本日の13時までに弊社スタッフとの打ち合わせを終えていただく必要がございます。\n";
				$mail_contents .= "注文を希望される場合には、お手数ですが、フリーダイヤル "._TOLL_FREE." までご連絡いただき、打ち合わせをいただきますよう、お願いいたします。\n";
				$mail_contents .= "本日13時を過ぎますと、お伝えしているお見積り金額、または、ご希望金額でのお届けができなくなりますので、ご注意ください。\n";
				$mail_contents .= $orders[$i]['customername']." 様のご連絡を心よりお待ちしております。\n\n";
				
				
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

// （注文確定日）
$fin = $ja->makeDateArray($baseSec);
$orderDay = $fin['Year'].'-'.$fin['Month'].'-'.$fin['Day'];

// 注文確定日を指定して注文データを取得
$conn = new ConnDB();
$result = $conn->getOrderInfo(array('pending'=>$orderDay));

if(empty($result)) exit;

// 送信
$inst = new Pendingorder();
$inst->send($result);
?>
