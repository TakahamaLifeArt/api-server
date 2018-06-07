<?php
/*
*	到着確認メール
*	 1.商品（アンケートページへのリンク有り）
*	 2.資料（保留）
*	お届予定日の2日後の12:00に自動送信
*
*	廃止
*/

require_once dirname(__FILE__).'/conndb.php';
use package\holiday\DateJa;

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
				
				if($mode==1){
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
					$URL = "https://www.takahama428.com/contact/enquete.html?enq=".$orders[$i]['number'];
					
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
				
				/*
				$res = unserialize($res);
				$reply = implode(',', $res);
				*/
			}

		}catch (Exception $e) {
			//$reply = 'ERROR: メールの送信が出来ませんでした。';
		}
	}


// 10日前の日付を取得（お届日）
$ja = new DateJa();
$baseSec = time();
$one_day = 86400;
$baseSec -= ($one_day*10);

// メール送信をお届日の２日後から１０日後に変更したため、送信済みを除外するため日付の確認
$checkSec = mktime(0, 0, 0, 9, 20, 2012);
if($baseSec>$checkSec){

	$fin = $ja->makeDateArray($baseSec);
	$arrivalDay = $fin['Year'].'-'.$fin['Month'].'-'.$fin['Day'];


	// 2日前がお届予定日で且つ発送済みの注文データを取得
	$conn = new ConnDB();
	$result = $conn->getOrderInfo(array('schedule4'=>$arrivalDay, 'shipped'=>2));
	if(empty($result)) exit;


	// 商品の到着確認メールを送信
	$inst = new Arrival();
	$inst->send($result, 1);
}
?>
