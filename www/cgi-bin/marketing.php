<?php
/*
*	マーケティング　クラス
*	charset UTF-8
*	log:	2015-03-18 created
*			2016-11-01 CSV ダウンロード用データ集計を実装
*/
require_once dirname(__FILE__).'/MYDB2.php';
class Marketing Extends MYDB2 {

	public function __construct(){
		parent::__construct();
	}
	
	
	private static function validDate($args, $defDate='2011-06-05'){
		if(empty($args)){
			return $defDate;
		}else{
			$args = str_replace("/", "-", $args);
			$d = explode('-', $args);
			if(checkdate($d[1], $d[2], $d[0])==false){
				return $defDate;
			}else{
				return $args;
			}
		}
	}
	
	
	/*
	*	受注データ、CSV出力用
	*	@start	受注入力登録日による検索開始日
	*	@end	受注入力登録日による検索終了日
	*	@id		受注No.
	*
	*	reutrn	[受注情報]
	*/
	public static function getOrderList($start=null, $end=null, $id=null) {
		try{
			$sql = "select orders.id as ordersid, staffname, ordertype, progressname, maintitle, pack_yes_volume, pack_nopack_volume, order_amount, ";
			$sql .= " carriage, boxnumber, factory, schedule1, schedule2, schedule3, schedule4, noprint, exchink_count, manuscript, ";
			$sql .= " discount1, discount2, staffdiscount, extradiscount, extradiscountname, free_discount, reductionname, ";
			$sql .= " additionalname, payment, ";
			$sql .= " (case when coalesce(expressfee,0)=0 then 0 else round(expressfee/(productfee+printfee+exchinkfee+packfee+discountfee+designfee),1)+1 end) as express, ";
			$sql .= " carriage, deliverytime, purpose, orders.job as job, repeatdesign, ";
			$sql .= " productfee, printfee, silkprintfee, colorprintfee, digitprintfee, inkjetprintfee, cuttingprintfee, ";
			$sql .= " discountfee, reductionfee, exchinkfee, estimatedetails.additionalfee as additionalfee, packfee, expressfee, carriagefee, designfee, codfee, creditfee, salestax, basefee, ";
			$sql .= " estimated, ";
			$sql .= " (case when customer.cstprefix='k' then concat('K', lpad(customer.number,6,'0')) else concat('G', lpad(customer.number,4,'0')) end) as customer_num, "; 
			$sql .= " customername, customerruby, company as dept, companyruby as deptruby, ";
			$sql .= " zipcode, addr0, addr1, addr2, addr3, addr4, ";
			$sql .= " tel as tel1, mobile as tel2, email as email1, mobmail as email2, fax";
			$sql .= " from (((((orders ";
			$sql .= " inner join acceptstatus on orders.id=acceptstatus.orders_id) ";
			$sql .= " inner join acceptprog on progress_id=aproid) ";
			$sql .= " left join estimatedetails on orders.id=estimatedetails.orders_id) ";
			$sql .= " left join staff on reception=staff.id) ";
			$sql .= " left join customer on customer_id=customer.id) ";
			$sql .= " where progress_id!=6";
			if($id){
				$sql .= " and orders.id=?";
			}
			$sql .= " and created between ? and ?";
			$sql .= " order by schedule3, orders.id";
			
			$start = self::validDate($start);
			$end = self::validDate($end, date('Y-m-d'));
			$conn = self::db_connect();
			$stmt = $conn->prepare($sql);
			if($id){
				$stmt->bind_param("iss", $id, $start, $end);
			}else{
				$stmt->bind_param("ss", $start, $end);
			}
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
	
	
	
	/*
	*	プリントデータ、CSV出力用
	*	@start	受注入力登録日による検索開始日
	*	@end	受注入力登録日による検索終了日
	*	@id		受注No.
	*
	*	reutrn	[プリント情報]
	*/
	public static function getPrintList($start=null, $end=null, $id=null) {
		try{
			$sql = "select orders.id as ordersid, ink_count, print_type, print_option, jumbo_plate, design_type, selective_name from (((orders ";
			$sql .= "inner join acceptstatus on orders.id=acceptstatus.orders_id) ";
			$sql .= "left join orderprint on orders.id=orderprint.orders_id) ";
			$sql .= "left join orderarea on orderprint.id=orderprint_id) ";
			$sql .= "left join orderselectivearea on areaid=orderarea_id ";
			$sql .= " where progress_id!=6";
			if($id){
				$sql .= " and orders.id=?";
			}
			$sql .= " and created between ? and ?";
			$sql .= " order by schedule3, orders.id";
			$conn = self::db_connect();
			$stmt = $conn->prepare($sql);
			$start = self::validDate($start);
			$end = self::validDate($end, date('Y-m-d'));
			if($id){
				$stmt->bind_param("iss", $id, $start, $end);
			}else{
				$stmt->bind_param("ss", $start, $end);
			}
			$stmt->execute();
			$stmt->store_result();
			$rs = self::fetchAll($stmt);
			
		}catch(Exception $e){
			$rs = "";
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}
	
	
	
	/*
	*	注文商品データ
	*	@start	受注入力登録日による検索開始日
	*	@end	受注入力登録日による検索終了日
	*	@id		受注No.
	*	@mode	NULL(default) or otherwise
	*
	*	reutrn	[注文商品情報]
	*/
	public static function getOrderItemList($start=null, $end=null, $id=null, $mode=null) {
		try{
			if(empty($mode)){
				$sql = "select orders.id as ordersid, coalesce(case orderitemext.item_id 
					 when 100000 then '持込' 
					 when 99999 then '転写シート' 
					 when 0 then 'その他' 
					 else null end, category_name) as catname, ";
				$sql .= " case when item_code is null then '' else item_code end as item_code, ";
				$sql .= " coalesce(item.item_name, orderitemext.item_name) as item_name, ";
				$sql .= " coalesce(size.size_name, orderitemext.size_name) as size_name, ";
				$sql .= " color_code, coalesce(itemcolor.color_name, orderitemext.item_color) as color_name, ";
				$sql .= " coalesce(maker.maker_name, orderitemext.maker) as maker_name,";
				$sql .= " amount, ";
				$sql .= " coalesce(orderitemext.price, orderitem.item_cost) as item_cost";
				$sql .= " from ((((((((orders";
				$sql .= " left join orderitem on orders.id=orderitem.orders_id)";
				$sql .= " left join orderitemext on orderitem.id=orderitemext.orderitem_id)";
				$sql .= " inner join acceptstatus on orders.id=acceptstatus.orders_id)";
				$sql .= " left join size on orderitem.size_id=size.id)";
				$sql .= " left join catalog on orderitem.master_id=catalog.id)";
				$sql .= " left join category on catalog.category_id=category.id)";
				$sql .= " left join item on catalog.item_id=item.id)";
				$sql .= " left join maker on item.maker_id=maker.id)";
				$sql .= " left join itemcolor on catalog.color_id=itemcolor.id";
				$sql .= " where progress_id!=6";
			}else{
				$sql = "select additionalestimate.orders_id as ordersid, addsummary, addamount, addcost, addprice";
				$sql .= " from (orders";
				$sql .= " inner join additionalestimate on orders.id=additionalestimate.orders_id)";
				$sql .= " inner join acceptstatus on orders.id=acceptstatus.orders_id";
				$sql .= " where progress_id!=6";
			}
			if($id){
				$sql .= " and orders.id=?";
			}
			$sql .= " and created between ? and ?";
			$sql .= " order by orders.id ";	
			$conn = self::db_connect();
			$stmt = $conn->prepare($sql);
			$start = self::validDate($start);
			$end = self::validDate($end, date('Y-m-d'));
			if($id){
				$stmt->bind_param("iss", $id, $start, $end);
			}else{
				$stmt->bind_param("ss", $start, $end);
			}
			$stmt->execute();
			$stmt->store_result();
			$rs = self::fetchAll($stmt);
		}catch(Exception $e){
			$rs = array();
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}
	
	
	
	/*
	*	ユーザー情報
	*	@start	注文確定日による検索開始日
	*	@end	注文確定日による検索終了日
	*	@id		ユーザーID
	*
	*	reutrn	[ユーザー情報]
	*/
	public static function getCustomerList($start=null, $end=null, $id=null) {
		try{
			$sql = "select (case when customer.cstprefix='k' then concat('K', lpad(customer.number,6,'0')) else concat('G', lpad(customer.number,4,'0')) end) as customer_num,";
			$sql .= " customername, customerruby, company as dept, companyruby as deptruby,";
			$sql .= " insert(zipcode, 4, 0, '-') as zipcode, addr0, addr1, addr2, addr3, addr4,";
			$sql .= " tel as tel1, mobile as tel2, email as email1, mobmail as email2, fax,";
			$sql .= " sum(estimated) as total_price, count(orders.id) as order_count,";
			$sql .= " (case when count(orders.id)>1 then 1 else 0 end) as repeater,";
			$sql .= " min(schedule2) as first_order, max(schedule2) as recent_order from (orders";
			$sql .= " inner join acceptstatus on orders.id=acceptstatus.orders_id)";
			$sql .= " inner join customer on orders.customer_id=customer.id";
			$sql .= " where created>'2011-06-05' and progress_id=4";
			$sql .= " and schedule2 between ? and ?";
			if($id){
				$sql .= " and customer_id=?";
			}
			$sql .= " group by customer_id";
			$sql .= " order by cstprefix desc, order_count desc, total_price desc";
			
			if($start){
				$start = str_replace("/", "-", $start);
				$d = explode('-', $start);
				if(checkdate($d[1], $d[2], $d[0])==false){
					$start = "2011-06-05";
				}
			}else{
				$start = "2011-06-05";
			}
			
			if($end){
				$end = str_replace("/", "-", $end);
				$d = explode('-', $end);
				if(checkdate($d[1], $d[2], $d[0])==false){
					$end = date('Y-m-d');
				}
			}else{
				$end = date('Y-m-d');
			}
			
			$conn = self::db_connect();
			$stmt = $conn->prepare($sql);
			if($id){
				$stmt->bind_param("ssi", $start, $end, $id);
			}else{
				$stmt->bind_param("ss", $start, $end);
			}
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
	
	
	/*
	*	受注データ（未使用）
	*	@start	受注入力登録日による検索開始日
	*	@end	受注入力登録日による検索終了日
	*	@id		受注No.
	*
	*	reutrn	[販売情報]
	*/
	public static function getSalesList($start=null, $end=null, $id=null) {
		try{
			$sql = "select orders.id as ordersid, staffname, ordertype, maintitle, pack_yes_volume, pack_nopack_volume, order_amount, ";
			$sql .= " carriage, boxnumber, factory, schedule1, schedule2, schedule3, schedule4, noprint, exchink_count, manuscript, ";
			$sql .= " discount1, discount2, staffdiscount, extradiscount, extradiscountname, free_discount, reductionname, ";
			$sql .= " additionalname, payment, ";
			$sql .= " (case when coalesce(expressfee,0)=0 then 0 else round(expressfee/(productfee+printfee+exchinkfee+packfee+discountfee+designfee),1)+1 end) as express, ";
			$sql .= " carriage, deliverytime, purpose, orders.job as job, repeatdesign, ";
			$sql .= " productfee, printfee, silkprintfee, colorprintfee, digitprintfee, inkjetprintfee, cuttingprintfee, ";
			$sql .= " discountfee, reductionfee, exchinkfee, estimatedetails.additionalfee as additionalfee, packfee, expressfee, carriagefee, designfee, codfee, creditfee, salestax, basefee, ";
			$sql .= " estimated, ";
			$sql .= " customer_id";
			
			$sql .= " from ((((orders ";
			$sql .= " inner join acceptstatus on orders.id=acceptstatus.orders_id) ";
			$sql .= " left join estimatedetails on orders.id=estimatedetails.orders_id) ";
			$sql .= " left join staff on reception=staff.id) ";
			$sql .= " left join customer on customer_id=customer.id) ";
			
			$sql .= " where created>'2011-06-05' and progress_id=4";
			if($id){
				$sql .= " and orders.id=?";
			}
			$sql .= " and created between ? and ?";
			$sql .= " order by schedule3, orders.id";
			
			if($start){
				$start = str_replace("/", "-", $start);
				$d = explode('-', $start);
				if(checkdate($d[1], $d[2], $d[0])==false){
					$start = "2011-06-05";
				}
			}else{
				$start = "2011-06-05";
			}
			
			if($end){
				$end = str_replace("/", "-", $end);
				$d = explode('-', $end);
				if(checkdate($d[1], $d[2], $d[0])==false){
					$end = date('Y-m-d');
				}
			}else{
				$end = date('Y-m-d');
			}
			
			$conn = self::db_connect();
			$stmt = $conn->prepare($sql);
			if($id){
				$stmt->bind_param("iss", $id, $start, $end);
			}else{
				$stmt->bind_param("ss", $start, $end);
			}
			$stmt->execute();
			$stmt->store_result();
			$rs = self::fetchAll($stmt);
			
			$cnt = count($rs);
			
			// discount info
			for($i=0; $i<$cnt; $i++){
				$rs[$i]["discount0"] = self::getDiscountInfo($rs[$i]["ordersid"]);
			}
			
			// order item
			for($i=0; $i<$cnt; $i++){
				$tmp = self::getOrderItem($rs[$i]["ordersid"], $rs[$i]["ordertype"]);
				$rs[$i]["orderitem"] = $tmp["orderitem"];
				$rs[$i]["additional"] = $tmp["additional"];
			}
			
			// print info
			for($i=0; $i<$cnt; $i++){
				$rs[$i]["printinfo"] = self::getPrintInfo($rs[$i]["ordersid"]);
			}
			
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}
	
	
	/*
	*	割引データ
	*	@id		受注No.
	*
	*	reutrn	[割引情報]
	*/
	public static function getDiscountInfo($id) {
		try{
			$sql = "select discount_name from discount where orders_id=? and discount_state=1";
			$conn = self::db_connect();
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("i", $id);
			$stmt->execute();
			$stmt->store_result();
			$rs = self::fetchAll($stmt);
			
		}catch(Exception $e){
			$rs = "";
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}
	
	
	/*
	*	注文商品データ
	*	@id			受注No.
	*	@ordertype	general(default) or industry
	*
	*	reutrn	[注文商品情報]
	*/
	public static function getOrderItem($id, $ordertype="general") {
		try{
			$sql = "select coalesce(case orderitemext.item_id 
				 when 100000 then '持込' 
				 when 99999 then '転写シート' 
				 when 0 then 'その他' 
				 else null end, category_name) as catname, ";
			$sql .= " case when item_code is null then '' else item_code end as item_code, ";
			$sql .= " coalesce(item.item_name, orderitemext.item_name) as item_name, ";
			$sql .= " coalesce(size.size_name, orderitemext.size_name) as size_name, ";
			$sql .= " color_code, coalesce(itemcolor.color_name, orderitemext.item_color) as color_name, ";
			$sql .= " coalesce(maker.maker_name, orderitemext.maker) as maker_name,";
			$sql .= " amount, ";
			$sql .= " coalesce(orderitemext.price, orderitem.item_cost) as item_cost";
			$sql .= " from ((((((orderitem";
			$sql .= " left join orderitemext on orderitem.id=orderitemext.orderitem_id)";
			$sql .= " left join size on orderitem.size_id=size.id)";
			$sql .= " left join catalog on orderitem.master_id=catalog.id)";
			$sql .= " left join category on catalog.category_id=category.id)";
			$sql .= " left join item on catalog.item_id=item.id)";
			$sql .= " left join maker on item.maker_id=maker.id)";
			$sql .= " left join itemcolor on catalog.color_id=itemcolor.id";
			$sql .= " where orderitem.orders_id=?";
			$conn = self::db_connect();
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("i", $id);
			$stmt->execute();
			$stmt->store_result();
			$rs_items = self::fetchAll($stmt);
			
			$rs_aditional = array();
			if($ordertype!="general"){
				$sql = "select addsummary, addamount, addcost, addprice from additionalestimate where orders_id=?";
				$stmt = $conn->prepare($sql);
				$stmt->bind_param("i", $id);
				$stmt->execute();
				$stmt->store_result();
				$rec = self::fetchAll($stmt);
				$rs_aditional = $rec;
			}
			
			$rs = array("orderitem"=>$rs_items, "additional"=>$rs_aditional);
		}catch(Exception $e){
			$rs = array("orderitem"=>array(), "additional"=>array());
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}
	
	
	/*
	*	プリントデータ（未使用）
	*	@id		受注No.
	*
	*	reutrn	[プリント情報]
	*/
	public static function getPrintInfo($id) {
		try{
			$sql = "select orderprint.id as orderprintid, printposition_id, printposition.item_type as printpositino_type";
			$sql .= " from orderprint left join printposition on orderprint.printposition_id=printposition.id";
			$sql .= " where orderprint.orders_id=?";
			$conn = self::db_connect();
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("i", $id);
			$stmt->execute();
			$stmt->store_result();
			$rs_print = self::fetchAll($stmt);
			
			$sql = "select areaid, area_name, selective_name, print_name, repeat_check, areasize_from, areasize_to, areasize_id,";
			$sql .= " print_option, design_size, jumbo_plate, design_type";
			$sql .= " from (orderarea";
			$sql .= " left join printtype on orderarea.print_type=printtype.print_key)";
			$sql .= " left join orderselectivearea on orderarea.areaid=orderselectivearea.orderarea_id";
			$sql .= " where orderarea.orderprint_id=?";
			$stmt_area = $conn->prepare($sql);
			
			$sql = "select ink_code, ink_name from orderink";
			$sql .= " where orderink.orderarea_id=?";
			$stmt_ink = $conn->prepare($sql);
			
			$rs = array();
			$cnt = count($rs_print);
			for($i=0; $i<$cnt; $i++){
				$stmt_area->bind_param("i", $rs_print[$i]["orderprintid"]);
				$stmt_area->execute();
				$stmt_area->store_result();
				$rs_area = self::fetchAll($stmt_area);
				
				$cnt_area = count($rs_area);
				for($t=0; $t<$cnt_area; $t++){
					$stmt_ink->bind_param("i", $rs_area[$t]["areaid"]);
					$stmt_ink->execute();
					$stmt_ink->store_result();
					$rs_area[$t]["inks"] = self::fetchAll($stmt_ink);
				}
				$rs[$i]["printpositino_type"] = $rs_print[$i][printpositino_type];
				$rs[$i]["area"] = $rs_area;
			}
		}catch(Exception $e){
			$rs = "";
		}
		
		$stmt->close();
		$stmt_area->close();
		$stmt_ink->close();
		$conn->close();
		return $rs;
	}
	
	
	/*
	*	売上伝票データ（未使用）
	*	@start	発送日による検索開始日
	*	@end	発送日による検索終了日
	*	@id		受注No.
	*
	*	reutrn	[売上台帳へのインポートデータ]
	*/
	public static function getSalesLedger($start=null, $end=null, $id=null) {
		try{
			$sql = "select orders.id as ordersId, schedule3, customer_id, ordertype, order_amount, ";
			$sql .= " productfee, printfee, silkprintfee, colorprintfee, digitprintfee, inkjetprintfee, cuttingprintfee, ";
			$sql .= " discountfee, reductionfee, exchinkfee, estimatedetails.additionalfee as additionalfee, packfee, ";
			$sql .= " expressfee, carriagefee, designfee, codfee, creditfee, salestax, basefee, estimated, ";
			$sql .= " bill";
			$sql .= " from ((((orders ";
			$sql .= " inner join acceptstatus on orders.id=acceptstatus.orders_id) ";
			$sql .= " left join estimatedetails on orders.id=estimatedetails.orders_id) ";
			$sql .= " left join staff on reception=staff.id) ";
			$sql .= " left join customer on customer_id=customer.id) ";
			
			$sql .= " where created>'2011-06-05' and progress_id=4";
			if($id){
				$sql .= " and orders.id=?";
			}
			$sql .= " and schedule3 between ? and ?";
			$sql .= " order by schedule3, orders.id";
			
			if($start){
				$start = str_replace("/", "-", $start);
				$d = explode('-', $start);
				if(checkdate($d[1], $d[2], $d[0])==false){
					$start = "2011-06-05";
				}
			}else{
				$start = "2011-06-05";
			}
			
			if($end){
				$end = str_replace("/", "-", $end);
				$d = explode('-', $end);
				if(checkdate($d[1], $d[2], $d[0])==false){
					$end = date('Y-m-d');
				}
			}else{
				$end = date('Y-m-d');
			}
			
			$conn = self::db_connect();
			$stmt = $conn->prepare($sql);
			if($id){
				$stmt->bind_param("iss", $id, $start, $end);
			}else{
				$stmt->bind_param("ss", $start, $end);
			}
			$stmt->execute();
			$stmt->store_result();
			$data = self::fetchAll($stmt);
			
			$cnt = count($data);
			
			// detail field
			$fld = array(
						"silkprintfee", "colorprintfee", "digitprintfee", "inkjetprintfee", "cuttingprintfee",
						"discountfee", "reductionfee", "exchinkfee", "additionalfee", "packfee",
						"expressfee", "carriagefee", "designfee", "codfee", "creditfee",
						);
			$fldCount = count($fld);
				
			// order item
			for($i=0; $i<$cnt; $i++){
				$tmp = self::getOrderItem($data[$i]["ordersid"], $data[$i]["ordertype"]);
				$data[$i]["orderitem"] = $tmp["orderitem"];
				$data[$i]["additional"] = $tmp["additional"];
				
				$detailID = 0;	// 明細行番号
				
				// 商品
				for($t=0; $t<count($tmp["orderitem"]); $t++){
					$detailID++;
					$args = array(
							'id' => $data[$i]['ordersId'],
							'orderDate' => $data[$i]['schedule3'],
							'customerId' => $data[$i]['customer_id'],
							'bill' => $data[$i]['bill'],
							'detailId' => $detailID,
							'itemCode' => 0,
							'itemName' => $tmp["orderitem"][$t]["catname"],
							'note' => array("name"=>$tmp["orderitem"][$t]["item_name"],
											"color"=>$tmp["orderitem"][$t]["color_name"],
											"size"=>$tmp["orderitem"][$t]["size_name"],
											),
							'amount' => $tmp["orderitem"][$t]["amount"],
							'cost' => $tmp["orderitem"][$t]["item_cost"],
							'price' => $tmp["orderitem"][$t]["item_cost"] * $tmp["orderitem"][$t]["amount"],
						);
					$rs[] = self::getHash($args);
				}
				
				// 手入力商品データ
				for($t=0; $t<count($tmp["additional"]); $t++){
					$detailID++;
					$args = array(
							'id' => $data[$i]['ordersId'],
							'orderDate' => $data[$i]['schedule3'],
							'customerId' => $data[$i]['customer_id'],
							'bill' => $data[$i]['bill'],
							'detailId' => $detailID,
							'itemCode' => 0,
							'itemName' => $tmp["additional"][$t]["addsummary"],
							'note' => "",
							'amount' => $tmp["additional"][$t]["addamount"],
							'cost' => $tmp["additional"][$t]["addcost"],
							'price' => $tmp["additional"][$t]["addprice"],
						);
					$rs[] = self::getHash($args);
				}
				
				// 金額明細
				for($t=0; $t<$fldCount; $t++){
					if(empty($data[$i][$fld[$t]])) continue;
					$detailID++;
					$args = array(
							'id' => $data[$i]['ordersId'],
							'orderDate' => $data[$i]['schedule3'],
							'customerId' => $data[$i]['customer_id'],
							'bill' => $data[$i]['bill'],
							'detailId' => $detailID,
							'itemCode' => 0,
							'itemName' => "",
							'note' => "",
							'amount' => 1,
							'cost' => $data[$i][$fld[$t]],
							'price' => $data[$i][$fld[$t]],
						);
					$rs[] = self::getHash($args);
				}
			}
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}
	
	
	/*
	*	販売管理データ用のハッシュを生成（未使用）
	*	@data	
	*
	*	reutrn	[]
	*/
	private static function getHash($data) {
		try{
			$tmp = array();
			$tmp[] = "1";	// 1.削除マーク（1:通常伝票）
			$tmp[] = "1";	// 2.締めフラグ（1:今回）
			$tmp[] = "0";	// 3.消込チェック（0:未消込）
			$tmp[] = $data['orderDate'];	// 4.伝票日付（発送日）
			$tmp[] = $data['id'];		// 5.伝票番号
			$tmp[] = "24";		// 6.伝票種別（売上）
			$tmp[] = $data['bill']==1? 4: 1;	// 7.取引区分（1:掛売り、4:都度請求）
			$tmp[] = "1";	// 8.税転嫁（外税/伝票計）
			$tmp[] = "1";	// 9.金額端数処理（切り捨て）
			$tmp[] = "1";	// 10.税端数処理（切り捨て）
			$tmp[] = $data['customerId'];	// 11.得意先コード（顧客ID）
			$tmp[] = "";	// 12.納入先コード
			$tmp[] = "";	// 13.担当者コード
			$tmp[] = $data['detailId'];	// 14.明細行番号
			$tmp[] = "1";	// 15.明細区分（1:通常）
			$tmp[] = $data['itemCode'];		// 16.商品コード
			$tmp[] = "";	// 17.入金区分コード（空白）
			$tmp[] = "";	// 18.商品名
			$tmp[] = "12";	// 19.課税区分（12: 8%）
			$tmp[] = "";	// 20.単位
			$tmp[] = "";	// 21.入数
			$tmp[] = "";	// 22.ケース
			$tmp[] = "";	// 23.倉庫コード
			$tmp[] = $data['amount'];		// 24.数量
			$tmp[] = $data['cost'];			// 25.単価
			$tmp[] = $data['price'];		// 26.金額
			$tmp[] = "";	// 27.回収予定日
			$tmp[] = "";	// 28.税抜額
			$tmp[] = "";	// 29.原価
			$tmp[] = "";	// 30.原単価
			$tmp[] = "";	// 31.備考
			$tmp[] = "";	// 32数量少数桁
			$tmp[] = "";	// 33.単価少数桁
			$tmp[] = "";	// 34.規格・型番
			$tmp[] = "";	// 35.色
			$tmp[] = "";	// 36.サイズ
			$tmp[] = "";	// 37.納入期日
			$tmp[] = "";	// 38.分類コード
			$tmp[] = "";	// 39.伝票区分
			$tmp[] = "";	// 40.得意先名称
			$tmp[] = "";	// 41.プロジェクト主コード
			$tmp[] = "";	// 42.プロジェクト副コード
			$tmp[] = "";	// 43.予備1
			$tmp[] = "";	// 44.予備2
			$tmp[] = "";	// 45.予備3
			$tmp[] = "";	// 46.予備4
			$tmp[] = "";	// 47.予備5
			$tmp[] = "";	// 48.予備6
			$tmp[] = "";	// 49.予備7
			$tmp[] = "";	// 50.予備8
			$tmp[] = "";	// 51.予備9
			$tmp[] = "";	// 52.予備10
		}catch(Exception $e){
			$tmp = '';
		}
		
		return $tmp;
	}
	
}
?>