<?php
/*------------------------------------------------------------

	File_name	: viamaler.php
	Description	: 他ドメインからメールデータを受け取り送信
	Charset		: utf-8
	Log			: 2014-12-19 created
				  
-------------------------------------------------------------- */

require_once dirname(__FILE__).'/phonenumber.php';
require_once dirname(__FILE__).'/http.php';

class ViaMailer {

	public function __construct(){}
	
	
	private $company_name = "";
	
	
	/**
	*	メール本文を返す
	*	@args			注文データのハッシュ
	*	@attach			添付ファイル
	*	
	*	return			{mail_subject: 題名, mail_contents: 本文}
	*/
	public function getContents($args, $attach){
		$printitem = $args["printitem"];
		$design = $args["design"];
		$user = $args["userinfo"];
		$estimate = $args["estimate"];
		
		$txt = "☆━━━━━━━━【　ご注文内容　】━━━━━━━━☆\n\n";
		$txt .= "ご注文日時： ".date("Y-m-d H:i")."\n\n";
		
		$txt .= "┏━━━━━━━━┓\n";
		$txt .= "◆　　ご希望納期\n";
		$txt .= "┗━━━━━━━━┛\n";
		if(empty($estimate['delidate'])){
			$txt .= "◇　納期指定なし\n";
		}else{
			$txt .= "◇　納期　：　".$estimate['delidate']."\n";
		}
		$txt .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
		
		$txt .= "┏━━━━━━━━┓\n";
		$txt .= "◆　　お客様情報\n";
		$txt .= "┗━━━━━━━━┛\n";
		$txt .= "◇お名前：　".$user['username']."　様\n";
		$txt .= "◇ご住所：　〒".$user['zipcode']."\n";
		$txt .= "　　　　　　　　".$user['addr1']."\n";
		$txt .= "　　　　　　　　".$user['addr2']."\n";
		$txt .= "◇TEL：　".PhoneNumber::format_phone_number($user['tel'])."\n";
		$txt .= "◇E-Mail：　".$user['email']."\n";
		$txt .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
		
		if(isset($user['message'])){
			$txt .= "┏━━━━━━━┓\n";
			$txt .= "◆　　コメント\n";
			$txt .= "┗━━━━━━━┛\n";
			if(empty($user['message'])){
				$txt .= "なし\n";
			}else{
				$txt .= "◇コメント：\n".implode("\n", $user['message'])."\n";
			}
			$txt .= "━━━━━━━━━━━━━━━━━━━━━\n\n\n";
		}
		
		$txt .= "┏━━━━━━━┓\n";
		$txt .= "◆　　商品情報\n";
		$txt .= "┗━━━━━━━┛\n";
		$sub_amount = 0;
		$sub_cost = 0;
		for($p=0; $p<count($printitem); $p++){
			$itemname = $printitem[$p]['code']." ".$printitem[$p]['name'];
			for($i=0; $i<count($printitem[$p]["color"]); $i++){
				$colorcode = $printitem[$p]["color"][$i]["code"];
				$colorname = $printitem[$p]["color"][$i]["name"];
				foreach($printitem[$p]["color"][$i]["amount"] as $size=>$amount){
					if($amount==0) continue;
					$item_cost = $amount * $printitem[$p]["color"][$i]["cost"][$size];
					$sub_amount += $amount;
					$sub_cost += $item_cost;
					$txt .= "◇アイテム：　".$itemname."\n";
					$txt .= "◇カラー：　".$colorcode." ".$colorname."\n";
					$txt .= "◇サイズ：　".$size."\n";
					$txt .= "◇枚　数：　".number_format($amount)."枚\n";
					//$txt .= "◇商品代：　".number_format($item_cost)."円\n";
					$txt .= "--------------------\n";
				}
			}
		}
		$txt .= "\n";
		//$txt .= "◆商品代合計：　".number_format($estimate['base'])." 円\n";
		$txt .= "◆枚数合計：　".number_format($sub_amount)." 枚\n";
		$txt .= "━━━━━━━━━━━━━━━━━━━━━\n\n\n";
		
		$txt .= "┏━━━━━━━━━┓\n";
		$txt .= "◆　　プリント情報\n";
		$txt .= "┗━━━━━━━━━┛\n";
		$printarea = array("front"=>"前", "back"=>"後");
		foreach($design as $area=>$val){
			for($i=0; $i<count($val); $i++){
				if(empty($val[$i]["src"])) continue;
				$txt .= "◇プリント箇所：　".$printarea[$area]."\n";
				$txt .= "◇ファイル名：　".$val[$i]["filename"]."\n";
				$txt .= "--------------------\n";
			}
		}
		$txt .= "\n━━━━━━━━━━━━━━━━━━━━━\n\n\n";
		
		$txt .= "┏━━━━━━━┓\n";
		$txt .= "◆　　デザイン\n";
		$txt .= "┗━━━━━━━┛\n";
		if(empty($attach)){
			$txt .= "なし\n";
		}else{
			for($a=0; $a<count($attach); $a++){
				$txt .= "◇".$printarea[$attach[$a]["area"]]."：　".mb_convert_encoding($attach[$a]['name'], 'utf-8')."\n";
			}
		}
		$txt .= "━━━━━━━━━━━━━━━━━━━━━\n\n\n";
		
		
		$txt .= "┏━━━━━━━━┓\n";
		$txt .= "◆　　お見積情報\n";
		$txt .= "┗━━━━━━━━┛\n";
		$txt .= "◇商品代：　".number_format($estimate['base'])."円\n";
		if(!empty($estimate['extra'])){
			$txt .= "◇割増料金：　".number_format($estimate['extra'])."円\n";
		}
		if(!empty($estimate['cod'])){
			$txt .= "◇代引手数料：　".number_format($estimate['cod'])."円\n";
		}
		$txt .= "◇送料：　".number_format($estimate['carriage'])."円\n";
		$txt .= "◇消費税：　".number_format($estimate['tax'])."円\n";
		$txt .= "------------------------------------------\n\n";
		if(!empty($estimate['credit'])){
			$txt .= "◇カード決済手数料：　".number_format($estimate['credit'])."円\n";
		}
		$txt .= "◇合計：　".number_format($estimate['tot'])."円\n";
		$txt .= "------------------------------------------\n\n";
		
		$payment = array("銀行振込","代金引換","現金でお支払い（工場でお受取）","カード決済");
		$txt .= "◇お支払方法：　".$payment[$estimate['payment']]."\n\n";
		$txt .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
		
		$res = array(
			"mail_contents"=>$txt,
		);
		
		
		
		/*
		
		$delitime = array(
			'なし',
			'午前中', 
			'12:00-14:00',
			'14:00-16:00',
			'16:00-18:00',
			'18:00-20:00',
			'20:00-21:00'
		);
		$txt .= "◇　配達時間指定　：　".$delitime[$opts['deliverytime']]."\n\n";
		$txt .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
		
		if(empty($opts['pack'])){
			$txt .= "◇たたみ・袋詰め：　希望しない\n";
		}else{
			$txt .= "◇たたみ・袋詰め：　希望する\n";
		}
		
		//$txt .= "◇デザインの入稿方法：　".$opts['ms']."\n\n";
		//$txt .= "◇プリントカラー：　\n".$opts['note_printcolor']."\n\n";
		//$txt .= "◇文字入力の確認：　\n".$opts['note_write']."\n\n";
		$txt .= "━━━━━━━━━━━━━━━━━━━━━\n\n\n";

		$txt .= "┏━━━━━━━━━┓\n";
		$txt .= "◆　　添付ファイル\n";
		$txt .= "┗━━━━━━━━━┛\n";
		if(empty($attach)){
			$txt .= "添付なし\n";
		}else{
			for($a=0; $a<count($attach); $a++){
				$txt .= "◇ファイル名：　".mb_convert_encoding($attach[$a]['img']['name'], 'utf-8')."\n";
			}
		}
		$txt .= "━━━━━━━━━━━━━━━━━━━━━\n\n\n";
		
		
		$txt .= "┏━━━━━┓\n";
		$txt .= "◆　　割引\n";
		$txt .= "┗━━━━━┛\n";
		
		// 学割
		if(!empty($opts['student'])){
			switch($opts['student']){
				case '3':	$discountname[] = "学割";
							break;
				case '5':	$discountname[] = "2クラス割";
							break;
				case '7':	$discountname[] = "3クラス割";
							break;
			}
		}
		
		// ブログ割
		if(!empty($opts['blog'])){
			$discountname[] = "ブログ割";
		}
		
		// イラレ割
		if(!empty($opts['illust'])){
			$discountname[] = "イラレ割";
		}
		
		// 紹介割
		if(!empty($opts['intro'])){
			$discountname[] = "紹介割";
		}
		
		if(empty($discountname)){
			$txt .= "◇割引：　なし\n";
		}else{
			$txt .= "◇割引：　".implode(', ', $discountname)."\n";
		}
		
		$txt .= "━━━━━━━━━━━━━━━━━━━━━\n\n\n";
		
		$txt .= "◇弊社ご利用について：　";
		if($user['repeater']==1){
			$txt .= "初めてのご利用\n\n";
		}else if($user['repeater']==2){
			$txt .= "以前にも注文したことがある\n\n";
		}else{
			$txt .= "-\n\n";
		}
		
		if(empty($opts['blog'])){
			$txt .= "◇デザイン掲載：　掲載不可\n\n";
		}else{
			$txt .= "◇デザイン掲載：　掲載可\n\n";
		}
		$txt .= "◇デザインについてのご要望など：\n";
		if(empty($user['note_design'])){
			$txt .= "なし\n";
		}else{
			$txt .= $user['note_design']."\n";
		}
		$txt .= "------------------------------------------\n\n";
		
		$txt .= "◇プリントするデザインの色：\n";
		$txt .= $user['note_printcolor']."\n";
		$txt .= "------------------------------------------\n\n";
		
		$txt .= "◇ご要望・ご質問など：\n";
		if(empty($user['comment'])){
			$txt .= "なし\n\n";
		}else{
			$txt .= $user['comment']."\n\n";
		}
		$txt .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
		*/
		
		/*
		$txt .= "┏━━━━━━━┓\n";
		$txt .= "◆　　お届け先\n";
		$txt .= "┗━━━━━━━┛\n";
		if(!empty($user['deli'])){
			$txt .= "◇宛名：　".$user['organization']."　様\n";
			$txt .= "◇ご住所：　〒".$user['delizipcode']."\n";
			$txt .= "　　　　　　　　　".$user['deliaddr1']." ".$info['deliaddr2']."\n";
		}else{
			$txt .= "（上記ご連絡先と同じ場所にお届けする）\n";
		}
		$txt .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
		*/
		
		/* 2013-11-25 廃止
		if(empty($user['payment'])){
			$txt .= "┏━━━━━━━┓\n";
			$txt .= "◆　　お振込先\n";
			$txt .= "┗━━━━━━━┛\n";
			$txt .= "振込口座：　三菱東京ＵＦＪ銀行\n";
			$txt .= "新小岩支店744　普通 3716333\n";
			$txt .= "口座名義：　ユ）タカハマライフアート\n";
			$txt .= "━━━━━━━━━━━━━━━━━━━━━\n";
			$txt .= "※お振込み手数料は、お客様のご負担とさせて頂いております。\n\n";
		}
		*/
		
		return $res;
	}
	
	
	/**
	*	メール送信
	*	@mail_subject	題名
	*	@mail_contents	メール本文
	*	@fromname		送信元の名前
	*	@sendfrom		送信元のメールアドレス
	*	@sendto[]		送信先のメールアドレスの配列
	*	@attach[]		添付ファイル情報[{file,name,type},{},...]
	*	@reply			_ORDER_EMAILへの返信の有無　0:なし（default）　1:{name:返信先の名前,email:返信先メールアドレス}
	*	
	*	返り値			[SUCCESS]:送信成功 , [送信できなかったアドレス, ...]:送信失敗
	*/
	public function send($mail_subject, $mail_contents, $fromname, $sendfrom, $sendto, $attach, $reply=0){
		mb_language("japanese");
		mb_internal_encoding("EUC-JP");
		$msg = "";											// 送信文
		$reply_msg = "";									// 返信文
		$attach_data = "";									// 添付ファイル情報
		$boundary = md5(uniqid(rand())); 					// バウンダリー文字（メールメッセージと添付ファイルの境界とする文字列を設定）
		$from = mb_encode_mimeheader(mb_convert_encoding($fromname,"JIS","UTF-8"))."<".$sendfrom.">";
		$header = "From: $from\n";
		$header .= "Reply-To: $from\n";
		$header .= "X-Mailer: PHP/".phpversion()."\n";
		$header .= "MIME-version: 1.0\n";
		
		if(!empty($attach)){ 		// 添付ファイルがあり
			$header .= "Content-Type: multipart/mixed;\n";
			$header .= "\tboundary=\"$boundary\"\n";
			
			$msg .= "This is a multi-part message in MIME format.\n\n";
			$msg .= "--$boundary\n";
			$msg .= "Content-Type: text/plain; charset=ISO-2022-JP\n";
			$msg .= "Content-Transfer-Encoding: 7bit\n\n";
			
			// 添付ファイル情報
			for($i=0; $i<count($attach); $i++){
				$attach_data .= "\n\n--$boundary\n";
				$attach_data .= "Content-Type: " . $attach[$i]['type'] . ";\n";
				$attach_data .= "\tname=\"".$attach[$i]['name']."\"\n";
				$attach_data .= "Content-Transfer-Encoding: base64\n";
				$attach_data .= "Content-Disposition: attachment;\n";
				$attach_data .= "\tfilename=\"".$attach[$i]['name']."\"\n\n";
				$attach_data .= $attach[$i]['file']."\n";
			}
			$attach_data .= "--$boundary--";
		}else{												// 添付ファイルなし
			$header .= "Content-Type: text/plain; charset=ISO-2022-JP\n";
			$header .= "Content-Transfer-Encoding: 7bit\n";
		}
		
		if($reply){
			// 返信用
			$reply_msg .= $reply["name"]."　様\n";
			$reply_msg .= "このたびは、タカハマライフアートをご利用いただき誠にありがとうございます。\n";
			$reply_msg .= "セルフスタイルプリントでのご注文を承りました。\n";
			$reply_msg .= "このメールはご注文いただいたお客様へ、内容確認の自動返信となっております。\n\n";
			
			$reply_msg .= "ご注文いただいた内容で在庫の確認、データの確認を行いますので、ご注文確定メール到着をお待ち下さい。\n";
			$reply_msg .= "ご注文確定メールは営業時間内で順次お送りしておりますが、お急ぎの場合、また、なかなか届かない場合には、\n";
			$reply_msg .= "お手数ですが、フリーダイヤル0120-130-428までご連絡ください。\n";
			$reply_msg .= "（営業時間：平日10：00～18：00 ※お急ぎの場合でも営業時間内での対応となります。予めご了承下さい。）\n\n";
			
			$reply_msg .= "《お支払いにつきまして》\n";
			$reply_msg .= "ご注文確定メールが届き、間違いが無いかご確認の上、確認メールに記載の方法でお支払いください。\n";
			$reply_msg .= "引き続き、どうぞよろしくお願いいたします。\n\n\n";
			
			$reply_msg .= $mail_contents;
			
			/*
			$reply_msg = $msg.mb_convert_encoding($reply_msg.$mail_contents,"JIS","UTF-8");	// ここで返信文をエンコードして設定
			$reply_msg .= $attach_data;
			*/
		}
		
		$msg .= mb_convert_encoding($mail_contents,"JIS","UTF-8");	// ここで注文情報をエンコードして設定
		$msg .= $attach_data;
		
		// 件名のマルチバイトをエンコード
		$subject  = mb_encode_mimeheader(mb_convert_encoding($mail_subject,"JIS","UTF-8"));
		
		// メール送信
		$res = array();
		if(strpos($sendto[0], "@")===false){
			$res[] = $sendto[0];
		}else if(!mail($sendto[0], $subject, $msg, $header)){
			$res[] = $sendto[0];	// 失敗したアドレス
		}
		
		if(!empty($res)) return $res;
		
		if($reply){
			if(strpos($reply["email"], "@")===false){
				$res[] = $reply["email"];
			}else{
				$via_data = array(
					'mail_subject'=>$reply["subject"],
					'mail_contents'=>$reply_msg,
					'sendto'=>array($reply["email"]),
					'attach'=>$attach,
					'reply'=>0
				);
				
				$r = $this->via_send($reply["via"], $via_data);
				$r = unserialize($r);
				if($r[0]!="SUCCESS"){
					$res[] = $r[0];
				}
				
			}
			/*
			if(!mail($reply["email"], $subject, $reply_msg, $header)){
				$res[] = $reply["email"];	// 失敗したアドレス
			}
			*/
		}
		
		
		if(empty($res)) $res[] = 'SUCCESS';
		
		return $res;
	}
	
	
	/**
	*	別ドメインからメール送信
	*	@url			転送先URL
	*	@param			'mail_subject'=>件名,
						'mail_contents'=>本文,
						'sendto'=>送信先アドレス,
						'reply'=>0
	*	
	*	返り値			[SUCCESS]:送信成功 , [送信できなかったアドレス, ...]:送信失敗
	*/
	public function via_send($url, $param){
		try{
			$http = new HTTP($url);
			/*
			$param = array(
				'mail_subject'=>$mail_subject,
				'mail_contents'=>$mail_contents,
				'sendto'=>$email,
				'reply'=>0
			);
			*/
			$res = $http->request('POST', $param);
		}catch (Exception $e) {
			//$reply = 'ERROR: メールの送信が出来ませんでした。';
		}
		
		return $res;
	}
}
?>
