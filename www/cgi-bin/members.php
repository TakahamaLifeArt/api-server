<?php
/**
*	タカハマラフアート
*	TLAメンバーズ　クラス
*	charset utf-8
*
*	log:	2014-09-15 商品の取扱期間の判断に使用する日付を注文確定の入力日に変更
*/

require_once dirname(__FILE__).'/config.php';
require_once dirname(__FILE__).'/MYDB2.php';

class Members Extends MYDB2 {

/**
 * fetchAl				プリペアドステートメントから結果を取得し、バインド変数に格納する
 * sort_size			サイズ名でソートする、usortのユーザー定義関数
 * getSizerange			指定サイズの商品単価で展開しているサイズリストを返す
 * getOrderHistory		注文履歴を取得
 * getDetailsPrint		プリント情報
 * getProgress			製作の進行状況
 * getPrintform			請求書・領収書・納品書のデータ
 * getEstimation		「2014-08-15 未使用」 当該注文の金額情報とアイテム毎のプリント代を算出した1枚あたり金額
 * setReceiptCount		領収書の発行回数を設定
 */
	
	
	public function __construct()
	{}
	
	
	/**
	*	サイズ名でソートする
	*	usortのユーザー定義関数
	*	getSizerange で使用
	*/
	private function sort_size($a, $b)
	{
		$tmp=array(
	    	'70'=>1,'80'=>2,'90'=>3,'100'=>4,'110'=>5,'120'=>6,'130'=>7,'140'=>8,'150'=>9,'160'=>10,
	    	'JS'=>11,'JM'=>12,'JL'=>13,'WS'=>14,'WM'=>15,'WL'=>16,'GS'=>17,'GM'=>18,'GL'=>19,
	    	'SSS'=>20,'SS'=>21,'XS'=>22,
	    	'S'=>23,'M'=>24,'L'=>25,'XL'=>26,
	    	'XXL'=>27,
	    	'O'=>28,'XO'=>29,'2XO'=>30,'YO'=>31,
	    	'3L'=>32,'4L'=>33,'5L'=>34,'6L'=>35,'7L'=>36,'8L'=>37);
		return ($tmp[$a] == $tmp[$b]) ? 0 : ($tmp[$a] < $tmp[$b]) ? -1 : 1;
	}
	
	
	/**
	*	指定サイズの商品単価で展開しているサイズリストを返す
	*	@item_id
	*	@size		サイズIDまたはサイズ名
	*	@curdate
	*
	*	@return		[サイズ名の配列]
	*/
	private function getSizerange($item_id, $size, $curdate)
	{
		if(empty($curdate)) $curdate = date('Y-m-d');
		$sql = "select * from (item inner join itemprice on item.id=itemprice.item_id) inner join size on size_from=size.id where 
				itemapply<=? and itemdate>? and itempriceapply<=? and itempricedate>? and item_id=? order by price_0, size_from";
		$conn = self::db_connect();
		$stmt = $conn->prepare($sql);
		$stmt->bind_param("ssssi", $curdate,$curdate,$curdate,$curdate,$item_id);
		$stmt->execute();
		$stmt->store_result();
		$rec = self::fetchAll($stmt);
		
		$i=-1;
		$r=0;
		$a=array();
		for($t=0; $t<count($rec); $t++){
			if($i==-1){
				$i++;
				$a[$i]=$rec[$t];
				$a[$i]['range'][] = $rec[$t]['size_name'];
			}else if($a[$i]['price_0']==$rec[$t]['price_0']){
				$a[$i]['range'][] = $rec[$t]['size_name'];
			}else{
				$i++;
				$a[$i]=$rec[$t];
				$a[$i]['range'][] = $rec[$t]['size_name'];
			}
			if($rec[$t]['size_from']==$size || $rec[$t]['size_name']==$size) $r = $i;
		}
		
		usort($a[$r]['range'], array('Members', 'sort_size'));
		
		$stmt->close();
		$conn->close();
		
		return $a[$r]['range'];
	}
	
	
	/**
	*	注文履歴を取得（注文確定）
	*	@args	 customer ID
	*	@orderId 受注No.
	*	@shipped 0:全て, 1:未発送, 2:発送済み
	*
	*	@return	[注文情報]
	*/
	public function getOrderHistory($args, $orderId=0, $shipped=0)
	{
		try {
			$rs = array();
			if(empty($args)) return;
			
			$conn = self::db_connect();
			
			//通常の注文履歴（注文確定のデータのみ）
			if (strpos($args, ",no_progress") == false) {
				$sql = "SELECT orders.id as orderid, schedule2, schedule3, order_amount, amount, payment,
				 shipped, deposit, progress_id, progressname, estimated, imagecheck,
				 coalesce(item.item_name,orderitemext.item_name) as item, 
				 coalesce(item_color, color_name) as color, 
				 coalesce(orderitemext.size_name, size.size_name) as size, size.id as size_id,
				 coalesce(item.id, 0) as itemid, item_code, color_code, category_key, category.id as category_id,
				 orderitemext.price as price,additionalname, orders.additionalfee,
				 printfee, exchinkfee, packfee, expressfee, discountfee, reductionfee, carriagefee, extracarryfee, designfee, codfee,
				 basefee, salestax, creditfee, master_id, orderitemext.price, item_cost, printposition_id,
				 print_group_id, item_group1_id, item_group2_id, catalog.id as master_id
				 FROM salestax, ((((((((((((orders left join customer on orders.customer_id=customer.id)
				 left join estimatedetails on orders.id=estimatedetails.orders_id)
				 left join progressstatus on orders.id=progressstatus.orders_id)
				 left join acceptstatus on orders.id=acceptstatus.orders_id)
				 left join acceptprog on acceptstatus.progress_id=acceptprog.aproid)

				 left join orderitem on orders.id=orderitem.orders_id)
				 left join orderitemext on orderitem.id=orderitemext.orderitem_id)
				 left join size on size_id=size.id)
				 left join catalog on master_id=catalog.id)
				 left join item on catalog.item_id=item.id)
				 left join itemcolor on catalog.color_id=itemcolor.id)
				 left join itemprice on item.id=itemprice.item_id)

				 left join category on catalog.category_id=category.id
				 WHERE created>'2011-06-05' and progress_id=4 and ordertype='general'
				 and (itemprice.size_from=size.id || size.id is null)
				 and ((itemapply<=schedule2 and itemdate>schedule2) || itemapply is null)
				 and ((itempriceapply<=schedule2 and itempricedate>schedule2) || itempriceapply is null)
				 and ((catalogapply<=schedule2 and catalogdate>schedule2) || catalogapply is null)
				 and taxapply=(select max(taxapply) from salestax where taxapply<=schedule3)
				 and customer.id=?";
				if (!empty($orderId)) {
					$sql .= " and orders.id=?";
				}
				if (!empty($shipped)) {
					$sql .= " and shipped=?";
				}
				 $sql .= " order by orders.id";
			} else {
				
			//イメージ画像表示用の注文履歴（注文確定のデータ以外でも検索）
				$sql = "SELECT orders.id as orderid, schedule2, schedule3, imagecheck,
				 coalesce(item.item_name,orderitemext.item_name) as item, 
				 coalesce(item_color, color_name) as color, 
				 coalesce(orderitemext.size_name, size.size_name) as size,
				 coalesce(item.id, 0) as itemid, item_code, color_code, category_key,
				 orderitemext.price as price,additionalname, orders.additionalfee,
				 printfee, exchinkfee, packfee, expressfee, discountfee, reductionfee, carriagefee, extracarryfee, designfee, codfee,
				 basefee, salestax, creditfee, master_id, orderitemext.price, item_cost, printposition_id
				 FROM salestax, (((((((((((orders left join customer on orders.customer_id=customer.id)
				 left join estimatedetails on orders.id=estimatedetails.orders_id)
				 left join acceptstatus on orders.id=acceptstatus.orders_id)
				 left join acceptprog on acceptstatus.progress_id=acceptprog.aproid)

				 left join orderitem on orders.id=orderitem.orders_id)
				 left join orderitemext on orderitem.id=orderitemext.orderitem_id)
				 left join size on size_id=size.id)
				 left join catalog on master_id=catalog.id)
				 left join item on catalog.item_id=item.id)
				 left join itemcolor on catalog.color_id=itemcolor.id)
				 left join itemprice on item.id=itemprice.item_id)

				 left join category on catalog.category_id=category.id
				 WHERE created>'2011-06-05' and ordertype='general'
				 and (itemprice.size_from=size.id || size.id is null)
				 and customer.id=?";
				if (!empty($orderId)) {
					$sql .= " and orders.id=?";
				}
				if (!empty($shipped)) {
					$sql .= " and shipped=?";
				}
				 $sql .= " order by orders.id";
				$args = str_replace(",no_progress","",$args);
			}
			
			$stmt = $conn->prepare($sql);
			if (!empty($orderId)) {
				if (!empty($shipped)) {
					$stmt->bind_param("iii", $args, $orderId, $shipped);
				} else {
					$stmt->bind_param("ii", $args, $orderId);
				}
			} else if (!empty($shipped)) {
				$stmt->bind_param("ii", $args, $shipped);
			} else {
				$stmt->bind_param("i", $args);
			}
			$stmt->execute();
			$stmt->store_result();
			$rec = self::fetchAll($stmt);
			
			$idx = -1;
			for ($i=0, $len=count($rec); $i<$len; $i++) {
				if ($rec[$i]['master_id']==0) {
					$cost = $rec[$i]['price'];
				} else {
					$cost = $rec[$i]['item_cost'];
				}
				
				if ($curid!=$rec[$i]['orderid']) {
					$curid = $rec[$i]['orderid'];
					++$idx;
					$rs[$idx] = $rec[$i];	// common data
					$rs[$idx]['itemlist'][$rec[$i]['item']][$rec[$i]['color']][] = array('volume'=>$rec[$i]['amount'], 
																						 'size'=>$rec[$i]['size'],
																						 'categorykey'=>$rec[$i]['category_key'],
																						 'category_id'=>$rec[$i]['category_id'],
																						 'itemcode'=>$rec[$i]['item_code'],
																						 'colorcode'=>$rec[$i]['color_code'],
																						 'cost'=>$cost,
																						 'itemid'=>$rec[$i]['itemid'],
																						 'printposition_id'=>$rec[$i]['printposition_id'],
																						 'master_id'=>$rec[$i]['master_id'],
																						 'size_id'=>$rec[$i]['size_id'],
																						 'print_group_id'=>$rec[$i]['print_group_id'],
																						 'item_group1_id'=>$rec[$i]['item_group1_id'],
																						 'item_group2_id'=>$rec[$i]['item_group2_id'],
																						);
					$rs[$idx]['itemamount'][$rec[$i]['item']] = $rec[$i]['amount'];	// アイテム毎の枚数
				} else {
					// 同じアイテムをの有無をチェック
					$isExist = false;
					if (isset($rs[$idx]['itemlist'][$rec[$i]['item']][$rec[$i]['color']])) {
						$tmp = $rs[$idx]['itemlist'][$rec[$i]['item']][$rec[$i]['color']];
						for ($t=0; $t<count($tmp); $t++) {
							if ($tmp[$t]['size']==$rec[$i]['size']) {
								$isExist = true;
								break;
							}
						}
					}
					
					// サイズかカラーが違う又は、初めてのアイテムの場合
					if (!$isExist) {
						$rs[$idx]['itemlist'][$rec[$i]['item']][$rec[$i]['color']][] = array('volume'=>$rec[$i]['amount'], 
																							 'size'=>$rec[$i]['size'],
																							 'categorykey'=>$rec[$i]['category_key'],
																							 'category_id'=>$rec[$i]['category_id'],
																							 'itemcode'=>$rec[$i]['item_code'],
																							 'colorcode'=>$rec[$i]['color_code'],
																							 'cost'=>$cost,
																						 	 'itemid'=>$rec[$i]['itemid'],
																							 'printposition_id'=>$rec[$i]['printposition_id'],
																							 'master_id'=>$rec[$i]['master_id'],
																							 'size_id'=>$rec[$i]['size_id'],
																							 'print_group_id'=>$rec[$i]['print_group_id'],
																							 'item_group1_id'=>$rec[$i]['item_group1_id'],
																							 'item_group2_id'=>$rec[$i]['item_group2_id'],
																							);
						$rs[$idx]['itemamount'][$rec[$i]['item']] += $rec[$i]['amount'];
					}
				}
			}
			
		} catch (Exception $e) {
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		
		return $rs;
	}
	
	
	/**
	*	プリント情報
	*	@args	受注No.
	*
	*	@return	[プリント情報]
	*/
	public function getDetailsPrint($args)
	{
		try{
			$rs = array();
			if(empty($args)) return;
			
			$conn = self::db_connect();
			
			$sql = "SELECT * from 
			(((orders
			 left join orderprint on orders.id=orderprint.orders_id)
			 left join orderarea on orderprint.id=orderarea.orderprint_id)
			 left join orderselectivearea on orderarea.areaid=orderselectivearea.orderarea_id)
			 left join category on orderprint.category_id=category.id
			 WHERE 
			 orders.id=?";
			
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("i", $args);
			$stmt->execute();
			$stmt->store_result();
			$rec = self::fetchAll($stmt);
			
			for($i=0; $i<count($rec); $i++){
				// 旧タイプと互換
				$select_key = empty($rec[$i]['selective_key'])? $rec[$i]['selectivekey']: $rec[$i]['selective_key'];
				$select_name = empty($rec[$i]['selective_name'])? $rec[$i]['selectivename']: $rec[$i]['selective_name'];
				$design_path = empty($rec[$i]['designpath'])? $rec[$i]['design_path']: $rec[$i]['designpath'];
				
				if(empty($select_key)) continue;
				
				if($rec[$i]['category_id']==100){
					$category = '持込商品';
				}else if($rec[$i]['category_id']==0){
					$category = 'その他商品';
				}else{
					$category = $rec[$i]['category_name'];
				}
				$rs[$category][] = array('area_path'=>$rec[$i]['area_path'],
										 'select_key'=>$select_key,
										 'select_name'=>$select_name,
										 'design_path'=>$design_path,
										 'category_id'=>$rec[$i]['category_id'],
										 'method'=>$rec[$i]['print_type'],
										 'ink'=>$rec[$i]['ink_count'],
										 'size'=>$rec[$i]['areasize_id'],
										 'option'=>$rec[$i]['print_option'],
										 'jumbo'=>$rec[$i]['jumbo_plate'],
										 'printposition_id'=>$rec[$i]['printposition_id'],
										);
			}
			
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		
		return $rs;
	}
	
	
	/**
	*	製作の進行状況を取得（注文確定で未発送）
	*	@args	[customer ID, order ID]
	*
	*	@return	[進行状況]
	*/
	public function getProgress($args)
	{
		try{
			$rs = array();
			if(empty($args)) return $rs;
			
			$conn = self::db_connect();
			
			$sql = 'SELECT *, orders.id as orderid ,schedule2, schedule4, fin_1 shipped, progress_id, progressname, contact_number, deliver,
			group_concat((case when discount_name="blog" then discount_state else 0 end) separator ",") as blog,
			group_concat(coalesce((case printtype_key when "silk" then fin_5 when "inkjet" then fin_6 else fin_4 end),0)  separator ",") as fin_print
			 FROM (((((orders left join customer on orders.customer_id=customer.id)
			 left join progressstatus on orders.id=progressstatus.orders_id)
			 left join printstatus on orders.id=printstatus.orders_id)
			 left join acceptstatus on orders.id=acceptstatus.orders_id)
			 left join acceptprog ON acceptstatus.progress_id=acceptprog.aproid)
			 left join discount on orders.id=discount.orders_id
			 WHERE created>"2011-06-05" and progress_id=4 and ordertype="general" and shipped=1';
			
			if(empty($args[1])){
				$sql .= ' and customer.id=?';
				$param = $args[0];
			}else{
				$sql .= ' and orders.id=?';
				$param = $args[1];
			}
			$sql .= ' group by orders.id';
			
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("i", $param);
			$stmt->execute();
			$stmt->store_result();
			$rs = self::fetchAll($stmt);
			
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		
		return $rs;
	}
	
	
	/**
	*	請求書・領収書・納品書のデータ
	*	@args	order ID
	*
	*	@return	[出力情報]
	*/
	public function getPrintform($args)
	{
		try{
			$rs = array();
			if(empty($args)) return;
			
			$discount_hash = array(
				'blog'=>'ブログ割',
				'illust'=>'イラレ割',
				'quick'=>'早割',
				'student'=>'学割',
				'team2'=>'2クラス割',
				'team3'=>'3クラス割',
				'repeat'=>'リピート割',
				'introduce'=>'紹介割',
				'vip'=>'VIP割',
				'friend'=>'リピート・紹介割',
			);
			$conn = self::db_connect();
			$sql = "SELECT *, orders.id as orderid, group_concat(discount_name separator ',') as discountname,
			coalesce(item.item_name,orderitemext.item_name) as item, 
			coalesce(item_color, color_name) as color, 
			coalesce(orderitemext.size_name, size.size_name) as size,
			item.id as itemid
			 from salestax, (((((((((((((orders
			 left join customer on orders.customer_id=customer.id)
			 left join delivery on orders.delivery_id=delivery.id)
			 left join estimatedetails on orders.id=estimatedetails.orders_id)
			 left join acceptstatus on orders.id=acceptstatus.orders_id)
			 left join staff on orders.reception=staff.id)
			 left join discount on orders.id=discount.orders_id)
			 left join orderitem on orders.id=orderitem.orders_id)
			 left join orderitemext on orderitem.id=orderitemext.orderitem_id)
			 left join size on size_id=size.id)
			 left join catalog on master_id=catalog.id)
			 left join item on catalog.item_id=item.id)
			 left join itemcolor on catalog.color_id=itemcolor.id)
			 left join itemprice on item.id=itemprice.item_id)
			 left join category on catalog.category_id=category.id
			 
			 WHERE created>'2011-06-05' and progress_id=4 and ordertype='general'
			 and (itemprice.size_from=size.id || size.id is null)
			 and ((itemapply<=schedule2 and itemdate>schedule2) || itemapply is null)
			 and ((itempriceapply<=schedule2 and itempricedate>schedule2) || itempriceapply is null)
			 and ((catalogapply<=schedule2 and catalogdate>schedule2) || catalogapply is null)
			 and taxapply=(select max(taxapply) from salestax where taxapply<=schedule3)
			 and orders.id=?
			 group by orderitem.id";
			
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("i", $args);
			$stmt->execute();
			$stmt->store_result();
			$rec = self::fetchAll($stmt);
			$Premium = $rec[0]["noprint"]==0? 1: 1.1;
			for($i=0; $i<count($rec); $i++){
				// 単価
				if($rec[$i]['master_id']==0){
					$rec[$i]['cost'] = $rec[$i]['price'];
					$rec[$i]['white'] = null;
				}else{
					$rec[$i]['cost'] = $rec[$i]['item_cost'];
					if( ($rec[$i]['color']=='ナチュラル' && ($rec[$i]['itemid']==112 || $rec[$i]['itemid']==212)) || $rec[$i]['color']=='ホワイト'){
						$rec[$i]['white'] = 1;
					}else{
						$rec[$i]['white'] = 0;
					}
				}
				
				/*
				if(isset($rec[$i]['price'])){
					$rec[$i]['cost'] = round($rec[$i]['price']*$Premium+4, -1);
					$rec[$i]['white'] = null;
				}else if($rec[$i]['color']=='ナチュラル' && ($rec[$i]['itemid']==112 || $rec[$i]['itemid']==212)){
					$rec[$i]['cost'] = round($rec[$i]['price_white']*$Premium+4, -1);
					$rec[$i]['white'] = 1;
				}else if($rec[$i]['color']=='ホワイト'){
					$rec[$i]['cost'] = round($rec[$i]['price_white']*$Premium+4, -1);
					$rec[$i]['white'] = 1;
				}else{
					$rec[$i]['cost'] = round($rec[$i]['price_color']*$Premium+4, -1);
					$rec[$i]['white'] = 0;
				}
				*/
				
				// 割引名
				if(!empty($rec[$i]['discount1'])) $rec[$i]['discount'][] = $discount_hash[$rec[$i]['discount1']];
				if(!empty($rec[$i]['discount2'])) $rec[$i]['discount'][] = $discount_hash[$rec[$i]['discount2']];
				$tmp = explode(',', $rec[$i]['discountname']);
				for($a=0; $a<count($tmp); $a++){
					$rec[$i]['discount'][] = $discount_hash[$tmp[$a]];
				}
				if(!empty($rec[$i]['extradiscountname'])){
					$rec[$i]['discount'][] = $rec[$i]['extradiscountname'];
				}
				
				// 単価が同じサイズ展開（取扱商品のみ）
				if(empty($rec[$i]['itemid'])){
					$rec[$i]['range'] = null;
				}else{
					$rec[$i]['range'] = self::getSizerange($rec[$i]['itemid'], $rec[$i]['size'], $rec[$i]['schedule3']);
				}
				
				$rs[] = $rec[$i];
				
				/*
				if($curid!=$rec[$i]['orderid']){
					$curid = $rec[$i]['orderid'];
					++$idx;
					$rs[$idx] = $rec[$i];	// common data
					$rs[$idx]['itemlist'][$rec[$i]['item']][$rec[$i]['color']][] = array( 'volume'=>$rec[$i]['amount'], 
																						'size'=>$rec[$i]['size'],
																						'categorykey'=>$rec[$i]['category_key'],
																						'itemcode'=>$rec[$i]['item_code'],
																						'colorcode'=>$rec[$i]['color_code'],
																						'cost'=>$cost
																						);
					$rs[$idx]['itemamount'][$rec[$i]['item']] = $rec[$i]['amount'];	// アイテム毎の枚数
				}else{
					$isExist = false;
					if(isset($rs[$idx]['itemlist'][$rec[$i]['item']][$rec[$i]['color']])){	// 同じアイテムをの有無をチェック
						$tmp = $rs[$idx]['itemlist'][$rec[$i]['item']][$rec[$i]['color']];
						for($t=0; $t<count($tmp); $t++){
							if($tmp[$t]['size']==$rec[$i]['size']){
								$isExist = true;
								break;
							}
						}
					}
					
					// サイズかカラーが違う又は、初めてのアイテムの場合
					if(!$isExist){
						$rs[$idx]['itemlist'][$rec[$i]['item']][$rec[$i]['color']][] = array( 'volume'=>$rec[$i]['amount'], 
																							'size'=>$rec[$i]['size'],
																							'categorykey'=>$rec[$i]['category_key'],
																							'itemcode'=>$rec[$i]['item_code'],
																							'colorcode'=>$rec[$i]['color_code'],
																							'cost'=>$cost
																							);
						$rs[$idx]['itemamount'][$rec[$i]['item']] += $rec[$i]['amount'];
					}
				}
				*/
			}
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		
		return $rs;
	}
	
	/**
	* 領収書の発行回数を設定
	* @param int $id 受注No.
	*
	* @return 発行回数
	*/
	public function setReceiptCount($id)
	{
		if(empty($id)) return;

		try{
			$conn = self::db_connect();

			$sql = "SELECT progid, receipt_count from progressstatus where orders_id=?";
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("i", $id);
			$stmt->execute();
			$stmt->store_result();
			$rec = self::fetchAll($stmt);

			$sql = "update progressstatus set receipt_count=? where progid=?";
			if ($stmt = $conn->prepare($sql) ) {
				for($i=0; $i<count($rec); $i++){
					$rs = $rec[$i]['receipt_count'] += 1;
					$stmt->bind_param("ii", $rs, $rec[$i]['progid']);
					$stmt->execute();
				}
			} else {
				throw new Exception();
			}
		} catch(Exception $e) {
			$rs = 0;
		}
		
		$stmt->close();
		$conn->close();
		
		return $rs;
	}
}
?>
