<?php
/******************
メール自動送信に必要なデータの抽出
	
作業開始：注文確定日（schedule2）の翌営業日の9:30
到着確認：商品のお届日（schedule4）の2日後の12:00
		資料の発送日（shippedreqdate）の3日後の12:00
		発送済みがチェックされていること
フォローメール：商品のお届日（schedule4）の30,60,90,120,150日後の18:00
引取確認：発送日（Schedule3）の10:00
		工場渡しの場合に全てのプリント完了、又はプリントなしで入荷済みになっている注文
注文未確定：注文確定日（schedule2）の10:00
				
*******************/

require_once dirname(__FILE__).'/../config.php';
require_once dirname(__FILE__).'/../MYDB2.php';
require_once dirname(__FILE__).'/../package/DateJa/vendor/autoload.php';

class ConnDB extends MYDB2{

	public function __construct(){
	}
	
	
	/*
	*	注文確定データを返す
	*	@data	schedule2:	 注文確定日
	*			schedule4:	お届予定日
	*			shipped:	発送状況　(2：発送済み)
	*
	*	return	受注データの配列
	*/
	public function getOrderInfo($data){
		try{
			$rs = null;
			$conn = parent::db_connect();
			
			$sql = 'SELECT * FROM (((orders LEFT JOIN customer ON orders.customer_id=customer.id)
				 LEFT JOIN progressstatus ON orders.id=progressstatus.orders_id)
				 LEFT JOIN acceptstatus ON orders.id=acceptstatus.orders_id)
				 LEFT JOIN acceptprog ON acceptstatus.progress_id=acceptprog.aproid 
				 WHERE created>"2011-06-05" and orders.ordertype="general"';
			
			if(!empty($data['pending'])){// 注文確定日「注文確定していない連絡」
				$sql .= ' and (progress_id!=4 and and progress_id!=6)';
				$sql .= ' and cancelpendingmail = 0';
				$sql .= ' and schedule2 = "'.$data['pending'].'"';
			}
			if(!empty($data['schedule2'])){// 注文確定日「制作開始」
				$sql .= ' and progress_id=4 and canceljobmail = 0';
				$sql .= ' and schedule2 = "'.$data['schedule2'].'"';
			}
			if(!empty($data['shipped'])){// 発送済み（2）の場合
				$sql .= ' and progress_id=4 and shipped = '.$data['shipped'];
			}
			if(!empty($data['schedule4'])){// お届日
				$sql .= ' and progress_id=4 and cancelarrivalmail = 0';
				$sql .= ' and schedule4 = "'.$data['schedule4'].'"';
			}
			
			if($result = $conn->query($sql)){
				while ($row = $result->fetch_assoc()) {
        			$rs[] = $row;
    			}
    			$result->free();
			}
		}catch(Exception $e){
			$rs = null;
		}
		
		$conn->close();
		return $rs;
	}
	
	
	/*
	*	資料を発送したユーザーデータを返す
	*	@data	shippedreqdate:	発送済みをチェックした日付
	*
	*	return	資料発送済みユーザーデータの配列
	*/
	public function getRequestInfo($data){
		try{
			if(empty($data)) return null;
			$rs = null;
			$conn = parent::db_connect();
			
			$sql = sprintf('SELECT * FROM requestmail WHERE phase=2 and shippedreqdate="%s"', $data);
			
			if($result = $conn->query($sql)){
				while ($row = $result->fetch_assoc()) {
        			$rs[] = $row;
    			}
    			$result->free();
			}
		}catch(Exception $e){
			$rs = null;
		}
		
		$conn->close();
		return $rs;
	}
	
	
	/*
	*	フォローメール
	*	Aメール：	サービス開始から初めての注文
	*	Bメール：	前回の注文からの経過日が180日未満（A若しくはBメールが進行中の時）
	*	Cメール：	サービス開始から1度以上注文があるが直近の注文から180日以上経過している
	*
	*	@data	schedule4:	お届予定日
	*			mode:		arrival:到着確認
	*
	*	return	メール種類（A,B,C）ごとの顧客データの配列[number, customername, company, email, mobmail]
	*/
	public function getFollowMailInfo($data){
		try{
			if(empty($data)) return null;
			$rs = null;
			$conn = parent::db_connect();
			
			$launch = '2013-10-28';			// お届け予定日の検索開始日付
			$term = 180;					// フォローメールの期間
			$baseSec = time();				// メール送信日のタイムスタンプ
			$startdate = array();			// フォローメールの起算日更新用
			$rs = array();
			$tmp_a = array();
			$tmp_b = array();
			$tmp_c = array();
			
			/* 指定日に注文確定した同一ユーザーの注文回数を取得
			*	注文1回：　		Aメール（初めての注文）
			*	注文2回以上：	Bメール（フォローメール中の到着確認メールのみ）
			*					Cメール（フォローメール終了後30日以上経過後の新規注文で開始）
			*/
			$sql = 'SELECT count(distinct schedule4) as cnt, max(schedule4) as schedule, followmailstartdate, customer_id, number, customername, company, email, mobmail FROM ((orders
				 INNER JOIN customer ON orders.customer_id=customer.id)
				 INNER JOIN acceptstatus ON orders.id=acceptstatus.orders_id)
				 INNER JOIN progressstatus ON orders.id=progressstatus.orders_id
				 WHERE created>"2011-06-05" and progress_id=4 and orders.ordertype="general"
				 and cancelarrivalmail=0 and cancelfollowmail=0 and shipped=2
				 and schedule4>="'.$launch.'" and schedule4<="'.$data['schedule4'].'"
				 group by customer_id having schedule="'.$data['schedule4'].'"';
				 
			if($result = $conn->query($sql)){
				while ($row = $result->fetch_assoc()) {
        			if($row['cnt']==1){
						$tmp_a[] = $row;
						if($row['followmailstartdate']=='0000-00-00'){
							$startdate[] = $row['customer_id'];
						}
					}else{
						$startSec = strtotime($row['followmailstartdate']);
						$endSec = $startSec + (86400*$term);
						if($startSec<=$baseSec and $baseSec<$endSec){
							if($row['followmailstartdate']==$data['schedule4']){
								$tmp_c[] = $row;
							}else if($data['mode']=='arrival'){
								$tmp_b[] = $row;
							}
						}else if($endSec<=$baseSec){
							$tmp_c[] = $row;
							$startdate[] = $row['customer_id'];
						}
					}
    			}
			}
			
			// フォローメールの起算日を更新
			if(count($startdate)>0){
				$sql = 'update customer set followmailstartdate="'.$data['schedule4'].'" where id in('.implode(',', $startdate).')';
				$conn->query($sql);
			}
			
			// 返り値を設定
			$rs = array('a'=>$tmp_a, 'b'=>$tmp_b, 'c'=>$tmp_c);
			
		}catch(Exception $e){
			$rs = null;
		}
		
		$result->free();
		$conn->close();
		return $rs;
	}
	
	
	/*
	*	引取確認のため工場渡しの注文データを返す
	*	受注No.が指定されている時は、発送日当日の10：00過ぎのみ実行
	*
	*	@args		受注No.
	*
	*	return	受注データの配列
	*/
	public function getHandoverInfo($args=null){
		try{
			$rs = array();
			$conn = parent::db_connect();
			
			/*
			$isReturn = false;
			if(!is_null($args)){
				// 受注No.指定あり
				$sql = sprintf('select * from orders where id=%d', $args);
				if($result = $conn->query($sql)){
					while($row = $result->fetch_assoc()) {
						$d = explode('-', $row['schedule3']);
						if(checkdate($d[1], $d[2], $d[0])==false){
							$isReturn = true;		// 発送日が未指定
						}else if(mktime(10, 0, 1, $d[1], $d[2], $d[0]) > time() ){
							$isReturn = true;		// 発送日の10:00過ぎの時だけ実行
						}
					}
				}else{
					$isReturn = true;		// 該当する受注No.なし
				}
				
				if($isReturn){
					$result->free();
					$conn->close();
					return;
				}
			}
			*/
			
			/*
			*	一般のみ
			*	確定注文
			*	引取確認メール未送信
			*	配送方法が工場渡し
			*	今日が発送日、または受注No.指定
			*	全てのプリントが終了、またはプリントなしで入荷済み
			*/
			$sql = 'select *, orders.id as ordersid from (((orders';
			$sql .= ' inner join customer on customer_id=customer.id)';
			$sql .= ' inner join printstatus on orders.id=printstatus.orders_id)';
			$sql .= ' inner join acceptstatus on orders.id=acceptstatus.orders_id)';
			$sql .= ' inner join progressstatus on orders.id=progressstatus.orders_id';
			$sql .= ' where created>"2011-06-05" and ordertype="general" and progress_id=4 and carriage="accept"';
			
			if(is_null($args)){
				$sql .= ' and schedule3="'.date('Y-m-d').'"';
				$sql .= ' order by orders.id';
			}else{
				$sql .= ' and orders.id=%d';
				$sql = sprintf($sql, $args);
			}
			
			if($result = $conn->query($sql)){
				$data = array();
				$tmp1 = array();
				$curid = null;
				$isFin = true;
				$isNoprint = null;
				while($row = $result->fetch_assoc()) {
					$data[$row['ordersid']] = $row;
					if($row['ordersid']!=$curid){
						if(!is_null($curid)){
							if( $isNoprint>0 || (is_null($isNoprint) && $isFin) ){
								$tmp1[] = $curid;
							}
						}
						$curid = $row['ordersid'];
						$isFin = true;
						$isNoprint = null;
					}
					switch($row['printtype_key']){
						case 'silk':	if($row['fin_5']==0) $isFin = false;
										break;
						case 'inkjet':	if($row['fin_6']==0) $isFin = false;
										break;
						case 'noprint':	$isNoprint = $row['state_7'];
										break;
						default:		if($row['fin_4']==0) $isFin = false;;
										break;
					}
				}
				if( !is_null($curid) && ($isNoprint>0 || (is_null($isNoprint) && $isFin)) ){
					$tmp1[] = $curid;
				}
				$result->free();
				
				if(is_null($args)){
   	    			/*
					*	時間指定の自動送信の場合
					*	引取確認メールが未送信の受注No.を取得
					*/
	    			$sql = 'select orders_id from mailhistory where subject=4 and orders_id in(';
					$sql .= implode(',', $tmp1).')';
					$tmp2 = array();
					if($result = $conn->query($sql)){
						while ($row = $result->fetch_assoc()) {
		        			$tmp2[] = $row['orders_id'];
		    			}
		    			$ids = array_diff($tmp1, $tmp2);
		    			$result->free();
					}
					
					/*
					*	条件に合致するレコードを取得
					*/
					if(!empty($ids)){
						foreach($ids as $orders_id){
							$rs[] = $data[$orders_id];
						}
					}
				}else{
					// 受注システムで作業終了チェックの場合
					if(!empty($data)) $rs[] = $data[$args];
				}
			}
		}catch(Exception $e){
			$rs = null;
		}
		
		$conn->close();
		return $rs;
	}
	
	
	/*
	*	メール送信履歴を登録
	*
	*	@args		{'subject'=>件名ID,
	*				'mailbody'=>nl2br(本文),
	*				'mailaddr'=>住所,
	*				'orders_id'=>受注No.,
	*				'cst_number'=>顧客番号,
	*				'cst_prefix'=>k or g,
	*				'cst_name'=>顧客名,
	*				'sendmaildate'=>発送日時('Y-m-d H:i:s'),
	*				'staff_id'=>担当者ID}
	*/
	public function setMailHistory($args){
		try{
			$conn = parent::db_connect();
			
			$sql = "insert into mailhistory (subject,mailbody,mailaddr,orders_id,cst_number,cst_prefix,cst_name,sendmaildate,staff_id) values(?,?,?,?,?,?,?,?,?)";
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("issiisssi", $args['subject'],$args['mailbody'],$args['mailaddr'],$args['orders_id'],$args['cst_number'],$args['cst_prefix'],$args['cst_name'],$args['sendmaildate'],$args['staff_id']);
			$stmt->execute();
			
		}catch(Exception $e){
			
		}
		$stmt->close();
		$conn->close();
	}
	
}

?>