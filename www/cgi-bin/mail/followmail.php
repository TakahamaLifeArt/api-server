<?php
/*
*	フォローメール
*	お届予定日の3日後の18:00に自動送信
*
*	2013-10-28 お届け予定日から開始
*	Aメール：	サービス開始から初めての注文
*	Bメール：	前回の注文からの経過日が180日未満（A若しくはBメールが進行中の時）に注文があった場合
*	Cメール：	サービス開始から1度以上注文があるが直近の注文から180日以上経過している
*
*	廃止
*/

require_once 'http.php';
require_once 'conndb.php';
require_once 'followmailtext.php';

class Followmail {

	public function __construct(){
	}
	
	
	/*
	*	フォローメール送信
	*	@orders		ユーザー情報
	*	@mode		メール種類（a,b,c）
	*	@step		0：到着確認、1-5:　AとCメールのステップ
	*/
	public function send($orders, $mode, $step){
		$debug = array();
		try{
			for($i=0; $i<count($orders); $i++){
				$email = array();	// メールアドレス
				if(!empty($orders[$i]['email'])){	// PC
					if( preg_match('/@/', $orders[$i]['email']) ) $email[] = $orders[$i]['email'];
				}
				if(!empty($orders[$i]['mobmail'])){	// 携帯
					if( preg_match('/@/', $orders[$i]['mobmail']) ) $email[] = $orders[$i]['mobmail'];
				}
				if(empty($email)){
					continue;
				}
				
				$r = array();
				switch($mode){
					case 'a':	$r = Followmailtext::type_a($orders[$i], $step);
								break;
					case 'b':	$r = Followmailtext::type_b($orders[$i]);
								break;
					case 'c':	$r = Followmailtext::type_c($orders[$i], $step);
								break;
				}
				
				if(empty($r)) continue;
				
				$mail_subject = $r['title'];
				$mail_contents = $r['txt'];
				
				// メール送信
				$http = new HTTP('http://www.takahama428.com/v1/via_mailer.php');
				$param = array(
					'mail_subject'=>$mail_subject,
					'mail_contents'=>$mail_contents,
					'sendto'=>$email,
					'reply'=>0
				);
				$res = $http->request('POST', $param);
				
				$debug[] = $orders[$i]['customer_id'];
			}
			
			// 確認用
			$mail_subject = $mode." Mail Step".$step;
			$email = array();
			$email[] = "test@takahama428.com";
			$mail_contents = $mode." Mail Step".$step."\n\n";
			$mail_contents .= "Customer ID:\n";
			if(empty($debug)){
				$mail_contents .= "0 datas\n";
			}else{
				$mail_contents .= implode("\n", $debug)."\n";
			}
			
			$http = new HTTP('http://www.takahama428.com/v1/via_mailer.php');
			$param = array(
				'mail_subject'=>$mail_subject,
				'mail_contents'=>$mail_contents,
				'sendto'=>$email,
				'reply'=>0
			);
			$res = $http->request('POST', $param);
			
		}catch (Exception $e) {
			//$reply = 'ERROR: メールの送信が出来ませんでした。';
		}
	}

}



exit;



// お届日（schedule4）を基準
$inst = new Followmail();
$ja = new DateJa();
$conn = new ConnDB();
$one_day = 86400;

// 到着確認メール（3日前にお届け予定になっている確定注文）
$baseSec = time();
$baseSec -= ($one_day*3);
$fin = $ja->makeDateArray($baseSec);
$targetDay = $fin['Year'].'-'.$fin['Month'].'-'.$fin['Day'];

$result = $conn->getFollowMailInfo(array('schedule4'=>$targetDay, 'mode'=>'arrival'));
foreach($result as $mode=>$data){
	if(empty($result[$mode])) continue;
	$inst->send($data, $mode, 0);
}

// フォローメール1回目から5回目まで
for($m=1; $m<6; $m++){
	$term = 30*$m;	// 30日間隔
	$baseSec = time();
	$baseSec -= ($one_day*$term);
	$fin = $ja->makeDateArray($baseSec);
	$targetDay = $fin['Year'].'-'.$fin['Month'].'-'.$fin['Day'];
	
	$result = $conn->getFollowMailInfo(array('schedule4'=>$targetDay, 'mode'=>null));
	foreach($result as $mode=>$data){
		if(empty($result[$mode]) || $mode=='b') continue;
		$inst->send($data, $mode, $m);
	}
}

?>
