<?php
/*
*	受渡し確認メールの送信
*/
require_once dirname(__FILE__).'/http.php';
require_once dirname(__FILE__).'/conndb.php';

class Exwmail extends ConnDB {

	public function __construct(){
		parent::__construct();
	}
	
	
	/*
	*	引取確認のため工場渡しの注文データを取得
	*/
	public function getExwOrder($args=null){
		$result = parent::getHandoverInfo($args);
		
		return $result;
	}
	
	
	/*
	*	メール送信
	*/
	public function send($orders){
		try{
			$res = array();
			for($i=0; $i<count($orders); $i++){
				// メールアドレス
				$email = array();
				if(!empty($orders[$i]['email'])){
					if( preg_match('/@/', $orders[$i]['email'] )) $email[] = $orders[$i]['email'];
				}
				if(!empty($orders[$i]['mobmail'])){
					if( preg_match('/@/', $orders[$i]['mobmail'] )) $email[] = $orders[$i]['mobmail'];
				}
				if(empty($email)){
					continue;
				}
				
				// 引渡し時間
				if(empty($orders[$i]['handover'])){
					continue;
				}
				$deliverytime = $orders[$i]['handover'];
				
				// 顧客ID
				if(!empty($orders[$i]['number'])){
					if($orders[$i]['cstprefix']=='g'){
						$customer_num = 'G'.sprintf('%04d', $orders[$i]['number']);
					}else{
						$customer_num = 'K'.sprintf('%06d', $orders[$i]['number']);
					}
				}
				
				// お客様名
				if(empty($orders[$i]['company'])){
					$customer_name = $orders[$i]['customername']."　　様\n\n";
				}else{
					$customer_name = $orders[$i]['customername']."\n　　　".$orders[$i]['company']."　　様\n\n";
				}
				
				$mail_subject = '本日お引取りの商品ができあがりました';
				
				$mail_contents = $customer_name;
				$mail_contents .= "この度はタカハマライフアートにご注文いただき、誠にありがとうございます。\n\n";
				$mail_contents .= "本日、お引取り予定の商品の制作が終了いたしました。\n";
				$mail_contents .= "お約束の".$deliverytime."に引き取りにいらっしゃるのを、スタッフ一同心よりお待ちして申し上げております。\n";
				$mail_contents .= "どうぞお気をつけてご来社下さい。\n";
				
				// 休業の告知文
				$mail_contents .= _NOTICE_HOLIDAY;
				
				$mail_contents .= "\n※ご不明な点やお気づきのことがございましたら、ご遠慮なくお問い合わせください。\n";
				$mail_contents .= "■営業時間　10:00 - 18:00　　■定休日：　土日祝\n\n";
				$mail_contents .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
				$mail_contents .= "\n";
				$mail_contents .= "オリジナルＴシャツ屋　タカハマライフアート\n";
				$mail_contents .= "　〒124-0025　東京都葛飾区西新小岩3-14-26\n";
				$mail_contents .= "　Phone ：　　"._OFFICE_TEL."\n";
				$mail_contents .= "　Fax   ：　　"._OFFICE_FAX."\n";
				$mail_contents .= "　E-Mail：　　"._INFO_EMAIL."\n";
				$mail_contents .= "　Web site：　http://www.takahama428.com/\n";
				$mail_contents .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
				
				$http = new HTTP('http://www.takahama428.com/v1/via_mailer.php');
				$param = array(
					'mail_subject'=>$mail_subject,
					'mail_contents'=>$mail_contents,
					'sendto'=>$email,
					'reply'=>0
				);
				$res = $http->request('POST', $param);
				$res = unserialize($res);
				
				if($res[0]=='SUCCESS'){
					// メール履歴を登録
					$args = array(
						'subject'=>4,
						'mailbody'=>nl2br($mail_contents),
						'mailaddr'=>$orders[$i]['email'],
						'orders_id'=>$orders[$i]['ordersid'],
						'cst_number'=>$orders[$i]['number'],
						'cst_prefix'=>$orders[$i]['cstprefix'],
						'cst_name'=>$orders[$i]['customername'],
						'sendmaildate'=>date('Y-m-d H:i:s'),
						'staff_id'=>$orders[$i]['reception']
						);
					parent::setMailHistory($args);
				}
			}
		}catch (Exception $e) {
			$res = array('Error');
		}
		
		return $res;
	}
	
	
	/*
	*	メール送信履歴を登録
	*
	private function setMailHistory($args){
		try{
			$conn = parent::getConnection();
			
			$sql = "insert into mailhistory (subject,mailbody,mailaddr,orders_id,cst_number,cst_prefix,cst_name,sendmaildate,staff_id) values(?,?,?,?,?,?,?,?,?)";
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("issiisssi", $args['subject'],$args['mailbody'],$args['mailaddr'],$args['orders_id'],$args['cst_number'],$args['cst_prefix'],$args['cst_name'],$args['sendmaildate'],$args['staff_id']);
			$stmt->execute();
			
		}catch(Exception $e){
			
		}
		$stmt->close();
		$conn->close();
	}
	*/
}
?>
