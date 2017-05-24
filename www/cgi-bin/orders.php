<?php
/*
*	タカハマライフアート
*	データベースの操作クラス
*	charset UTF-8
*/

require_once dirname(__FILE__).'/config.php';
require_once dirname(__FILE__).'/MYDB.php';
//require_once dirname(__FILE__).'/http.php';
class Orders{
	private $print_codename = array(
					'silk'=>array('name'=>'シルク','abbr'=>'S','index'=>0),
					'inkjet'=>array('name'=>'IJ','abbr'=>'I','index'=>1),
					'digit'=>array('name'=>'デジ','abbr'=>'D','index'=>2),
					'trans'=>array('name'=>'TS','abbr'=>'T','index'=>3),
					'cutting'=>array('name'=>'CS','abbr'=>'C','index'=>4),
					'noprint'=>array('name'=>'商品のみ','abbr'=>'N','index'=>5),
				);
				
				
	/**
	*	db		処理の分岐
	*			insert, update, delete, search
	*/
	
	/***************************************************************************************************************
	*		注文伝票データベースの操作
	*		@func		処理内容
	*		@table		テーブル名
	*		@param		引数の配列
	*
	*		return		返り値
	*/
	public function db($func, $table, $param){
		try{
			$conn = db_connect();
			switch($func){
			case 'insert':
				mysqli_query($conn, 'BEGIN');
				$result = $this->insert($conn, $table, $param);
				if(!is_null($result) && strpos($result, "Error")===false) mysqli_query($conn, 'COMMIT');
				break;
			case 'update':
				mysqli_query($conn, 'BEGIN');
				$result = $this->update($conn, $table, $param);
				if(!is_null($result)) mysqli_query($conn, 'COMMIT');
				break;
			case 'delete':
				mysqli_query($conn, 'BEGIN');
				$result = $this->delete($conn, $table, $param);
				if(!is_null($result)) mysqli_query($conn, 'COMMIT');
				break;
			case 'search':
				$result = $this->search($conn, $table, $param);
				break;
			}
		}catch(Exception $e){
			$result = null;
		}

		mysqli_close($conn);

		return $result;
	}


	/**--------------- private function -------------------------------------
	*
	* 	update			修正更新
	*	addnew			新規追加
	*	delete			削除
	*	search			検索
	*	sort_size		サイズ名でソートする
	*------------------------------------------------------------------------/
	
	/***************************************************************************************************************
	*	レコードの新規追加
	*	@table		テーブル名
	*	@data		追加データの配列、若しくは注文伝票ID
	*
	*	return		ID
	*/
	private function insert($conn, $table, $data){
		try{
			switch($table){
			case 'order':
				/**
				 *	data1	顧客
				 *	data2	お届け先
				 *	data3	受注伝票
				 *	data4	注文商品
				 *	data5	業者の時の見積追加行
				 *	data6	プリント情報（orderprint）
				 *	data7	プリント位置（orderarea）
				 *	data8	プリントポジション（orderselectivearea）
				 *	data9	インク（orderink）
				 *	data10	インク色替え（exchink）
				 *	data12	発送元
				 *
				 *	return	受注ID, 顧客ID, 顧客Number | プリント位置ID,, | インクID,, | インク色替えID,, | 見積追加行ID,,
				 */
				list($data1, $data2, $data3, $data4, $data5, $data6, $data7, $data8, $data9, $data10, $data12) = $data;
				$customer_id = 0;
				$deli_id = 0;
				$ship_id=0;

				if(!empty($data1)){
					list($customer_id, $number) = $this->insert($conn, 'customer', $data1);
					if(empty($customer_id)){
						mysqli_query($conn, 'ROLLBACK');
						return "Error: data1";
					}
				}

				if(isset($data2)){
					if(empty($data2['delivery_id'])){
						$deli_id = $this->insert($conn, 'delivery', $data2);
					}else if($data3["phase"]!="accept"){
						$deli_id = $this->update($conn, 'delivery', $data2);
					}else{
						$deli_id = $data2['delivery_id'];
					}
					if(empty($deli_id)){
						mysqli_query($conn, 'ROLLBACK');
						return "Error: data2";
					}
				}
				$delivery_id = empty($data2['delivery_id'])? $deli_id: $data2['delivery_id'];

				if(isset($data12)){
					$ship_id = $this->insert($conn, 'shipfrom', $data12);
					if(empty($ship_id)){
						mysqli_query($conn, 'ROLLBACK');
						return "Error: data12";
					}
				}

				foreach($data3 as $key=>$val){
					$info3[$key] = quote_smart($conn, $val);
				}
				if($data3["phase"]=="accept"){
					$progress_id = 8;	// 注文受付
				}else{
					$progress_id = 1;	// 問い合わせ中
				}
				if(empty($info3["customer_id"]) || $customer_id!=0) $info3["customer_id"] = $customer_id;
				$info3["delivery_id"] = $delivery_id;
				$info3["shipfrom_id"] = $ship_id;
				$info3['created'] = date("Y-m-d");
				$info3['lastmodified'] = date("Y-m-d");
				$sql = sprintf("INSERT INTO orders(reception, ordertype, applyto, maintitle, schedule1, schedule2, schedule3, schedule4, destination, arrival,
					carriage, check_amount, noprint, design, manuscript, discount1, discount2, reduction, reductionname, handover, 
					freeshipping, payment, order_comment, invoicenote, billnote, phase, budget, customer_id, delivery_id, created, 
					lastmodified, estimated, order_amount, paymentdate, exchink_count, deliver, deliverytime, manuscriptdate, purpose, purpose_text, 
					job, designcharge, repeater, reuse, free_discount, free_printfee, completionimage, contact_number, additionalname, additionalfee, 
					extradiscountname, extradiscount, shipfrom_id, package_yes, package_no, package_nopack, pack_yes_volume, pack_nopack_volume, boxnumber, factory, 
					destcount, repeatdesign, allrepeat, staffdiscount)
								VALUES(%d,'%s',%d,'%s','%s','%s','%s','%s',%d,'%s',
								'%s',%d,%d,'%s','%s','%s','%s',%d,'%s','%s',
								%d,'%s','%s','%s','%s','%s',%d,%d,%d,'%s',
								'%s',%d,%d,'%s',%d,%d,%d,'%s','%s','%s',
								'%s',%d,%d,%d,%d,%d,%d,'%s','%s',%d,
								'%s',%d,%d,%d,%d,%d,%d,%d,%d,%d,
								%d,%d,%d,%d)",
								$info3["reception"],
								$info3["ordertype"],
								$info3["applyto"],
								$info3["maintitle"],
								$info3["schedule1"],
								$info3["schedule2"],
								$info3["schedule3"],
								$info3["schedule4"],
								$info3["destination"],
								$info3["arrival"],
								$info3["carriage"],
								$info3["check_amount"],
								$info3["noprint"],
								$info3["design"],
								$info3["manuscript"],
								$info3["discount1"],
								$info3["discount2"],
								$info3["reduction"],
								$info3["reductionname"],
								$info3['handover'],
								$info3["freeshipping"],
								$info3["payment"],
								$info3["order_comment"],
								$info3["invoicenote"],
								$info3["billnote"],
								$info3["phase"],
								$info3["budget"],
								$info3["customer_id"],
								$info3["delivery_id"],
								$info3["created"],
								$info3["lastmodified"],
								$info3["estimated"],
								$info3["order_amount"],
								$info3["paymentdate"],
								$info3["exchink_count"],
								$info3["deliver"],
								$info3["deliverytime"],
								$info3["manuscriptdate"],
								$info3["purpose"],
								$info3["purpose_text"],
								$info3["job"],
								$info3["designcharge"],
								$info3["repeater"],
								$info3["reuse"],
								$info3["free_discount"],
								$info3["free_printfee"],
								$info3["completionimage"],
								$info3["contact_number"],
								$info3["additionalname"],
								$info3["additionalfee"],
								$info3["extradiscountname"],
								$info3["extradiscount"],
								$info3["shipfrom_id"],
								$info3["package_yes"],
								$info3["package_no"],
								$info3["package_nopack"],
								$info3["pack_yes_volume"],
								$info3["pack_nopack_volume"],
								$info3["boxnumber"],
								$info3["factory"],
								$info3["destcount"],
								$info3["repeatdesign"],
								$info3["allrepeat"],
								$info3["staffdiscount"]
								
								);

				if(exe_sql($conn, $sql)){
					$rs = mysqli_insert_id();
					$orders_id = $rs;

					/* reuse 2014-12-10 仕様変更、版元のreuseへの255の設定を廃止
					if($info3['repeater']!=0){
						$sql= sprintf("UPDATE orders SET reuse=%d WHERE id=%d", 255, $info3["repeater"]);
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}
					*/

					// orderprint
					$orderareaid = array();
					$orderinkid = array();
					$exchinkid = array();
					for($i=0; $i<count($data6); $i++){
						$sql = sprintf("INSERT INTO orderprint(orders_id,category_id,printposition_id,subprice) VALUES(%d,%d,'%s',%d)",
								$orders_id,
								$data6[$i]['category_id'],
								$data6[$i]['printposition_id'],
								$data6[$i]['subprice']);
						if(exe_sql($conn, $sql)){
							$orderprint_id = mysqli_insert_id();
						}else{
							mysqli_query($conn, 'ROLLBACK');
							return "Error: data6";
						}

						// orderarea
						for($t=0; $t<count($data7); $t++){
							if($data7[$t]['print_id']!=$i) continue;
							$sql = sprintf("INSERT INTO orderarea(orderprint_id,area_path,area_name,origin,ink_count,print_type,
								areasize_from,areasize_to,areasize_id,print_option,jumbo_plate,design_plate,design_type,design_size,repeat_check,silkmethod)
								VALUES(%d,'%s','%s',%d,%d,'%s',%d,%d,%d,%d,%d,%d,'%s','%s',%d,%d)",
								$orderprint_id,
								'txt/'.$data7[$t]['area_path'].'/'.$data7[$t]['area_name'].'.txt',
								$data7[$t]['area_name'],
								$data7[$t]['origin'],
								$data7[$t]['ink_count'],
								$data7[$t]['print_type'],
								$data7[$t]['areasize_from'],
								$data7[$t]['areasize_to'],
								$data7[$t]['areasize_id'],
								$data7[$t]['print_option'],
								$data7[$t]['jumbo_plate'],
								$data7[$t]['design_plate'],
								$data7[$t]['design_type'],
								$data7[$t]['design_size'],
								$data7[$t]['repeat_check'],
								$data7[$t]['silkmethod']
								);
							if(exe_sql($conn, $sql)){
								$orderarea_id = mysqli_insert_id();
								$orderareaid[$t] = $orderarea_id;
							}else{
								mysqli_query('ROLLBACK');
								return "Error: data7";
							}

							// orderselectivearea
							for($s=0; $s<count($data8); $s++){
								if($data8[$s]['area_id']==$t){
									$sql = sprintf("INSERT INTO orderselectivearea(orderarea_id,selective_key,selective_name) VALUES(%d,'%s','%s')",
										$orderarea_id,
										$data8[$s]['selective_key'],
										$data8[$s]['selective_name']);
									if(!exe_sql($conn, $sql)){
										mysqli_query($conn, 'ROLLBACK');
										return "Error: data8";
									}
									break;
								}
							}

							// orderink
							if(isset($data9)){
								for($s=0; $s<count($data9); $s++){
									if($data9[$s]['area_id']!=$t) continue;
									$sql = sprintf("INSERT INTO orderink(orderarea_id,ink_name,ink_code,ink_position) VALUES(%d,'%s','%s','%s')",
									$orderarea_id, $data9[$s]['ink_name'], $data9[$s]['ink_code'], $data9[$s]['ink_position']);
									if(exe_sql($conn, $sql)){
										$orderink_id = mysqli_insert_id();
										$orderinkid[$s] = mysqli_insert_id();
									}else{
										mysqli_query($conn, 'ROLLBACK');
										return "Error: data9";
									}
									
									// exchange ink
									/*
									for($a=0; $a<count($data10); $a++){
										if($data10[$a]['ink_id']!=$s) continue;
										$sql = sprintf("INSERT INTO exchink(orderink_id,exchink_name,exchink_code,exchink_volume) VALUES(%d,'%s','%s',%d)",
										$orderink_id, $data10[$a]['exchink_name'], $data10[$a]['exchink_code'], $data10[$a]['exchink_volume']);
										if(exe_sql($conn, $sql)){
											$exchinkid[$a] = mysqli_insert_id();
										}else{
											mysqli_query($conn, 'ROLLBACK');
											return null;
										}
									}
									*/
									
								}
							}
						}
					}

					/*
					if(!empty($info3['media'])){
						$tmp = explode(',', $info3['media']);
						for($i=0; $i<count($tmp); $i++){
							$media = explode('|', $tmp[$i]);
							if($media[0]=='mediacheck02'){
								$mediacheck02 = $media[1];
							}
						}
					}
					$sql = sprintf("INSERT INTO contactchecker(orders_id,firstcontactdate,staff_id,medianame,attr) VALUES(%d,'%s',%d,'%s','%s')",
						$orders_id, date("Y-m-d"), $info3["reception"], $mediacheck02, $info3["purpose"]);
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					*/
					
					$result = $this->insert($conn, 'orderitem', array($orders_id, $info3["ordertype"], $data4));
					if(empty($result)){
						mysqli_query($conn, 'ROLLBACK');
						return $data4;
					}

					$sql = sprintf("INSERT INTO acceptstatus(orders_id,progress_id) VALUES(%d, %d)", $orders_id, $progress_id);
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return "Error: acceptstatus";
					}
					
					$sql = sprintf("INSERT INTO progressstatus(orders_id,rakuhan) VALUES(%d,%d)", $orders_id, $info3['rakuhan']);
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return "Error: progressstatus";
					}

					$sql = sprintf("select print_type from 
						 (orderprint inner join orderarea on orderprint.id=orderarea.orderprint_id)
						 right join orderselectivearea on orderarea.areaid=orderselectivearea.orderarea_id
						 where orders_id=%d group by orders_id, print_type", $orders_id);
					$result = exe_sql($conn, $sql);
					$f = $info3['factory'];
					if(mysqli_num_rows($result)>0){
						if($info3['repeater']==0){
							$sql = "INSERT INTO printstatus(orders_id,printtype_key,factory_2,factory_3,factory_4,factory_5,factory_6,factory_7) VALUES";
							while($res = mysqli_fetch_assoc($result)){
								$sql .= "(".$orders_id.",'".$res['print_type']."',".$f.",".$f.",".$f.",".$f.",".$f.",".$f."),";
							}
							if($info3['noprint']==1){
								$sql .= "(".$orders_id.",'noprint',".$f.",".$f.",".$f.",".$f.",".$f.",".$f."),";
							}
						}else{
							$sql = "INSERT INTO printstatus(orders_id,printtype_key,state_1,state_2,factory_2,factory_3,factory_4,factory_5,factory_6,factory_7) VALUES";
							while($res = mysqli_fetch_assoc($result)){
								if($res['print_type']=='silk' || $res['print_type']=='digit'){
									$sql .= "(".$orders_id.",'".$res['print_type']."',28,28,".$f.",".$f.",".$f.",".$f.",".$f.",".$f."),";
								}else{
									$sql .= "(".$orders_id.",'".$res['print_type']."',43,0,".$f.",".$f.",".$f.",".$f.",".$f.",".$f."),";
								}
							}
							if($info3['noprint']==1){
							$sql .= "(".$orders_id.",'noprint',28,28,".$f.",".$f.",".$f.",".$f.",".$f.",".$f."),";
						}
						}
						
						$sql = substr($sql, 0, -1);
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return "Error: printstatus";
						}
					}else if($info3['noprint']==1){
						$sql = "INSERT INTO printstatus(orders_id,printtype_key,factory_2,factory_3,factory_4,factory_5,factory_6,factory_7) VALUES";
						$sql .= "(".$orders_id.",'noprint',".$f.",".$f.",".$f.",".$f.",".$f.",".$f.")";
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return "Error: noprint";
						}
					}
					
					// シルク作業予定レコードを新規追加
					$sql = sprintf("select * from printstatus where orders_id=%d and printtype_key='silk'", $orders_id);
					$result = exe_sql($conn, $sql);
					if(mysqli_num_rows($result)>0){
						$res = mysqli_fetch_assoc($result);
						$sql = "INSERT INTO workplan(orders_id, prnstatus_id, wp_printkey, quota) VALUES";
						$sql .= "(".$orders_id.", ".$res['prnstatusid'].", 'silk', 100)";
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return "Error: workplan";
						}
					}
						
					if(!empty($info3["discount"])){
						$result = $this->insert($conn, 'discount', array("orders_id"=>$orders_id, "discount"=>$info3["discount"]));
						if(is_null($result)){
							mysqli_query($conn, 'ROLLBACK');
							return "Error: discount";
						}
					}

					if(!empty($info3['media']) || !empty($info3['media_other'])){
						$result = $this->insert($conn, 'media', array("orders_id"=>$orders_id, "media"=>array($info3["media"], $info3['media_other'])));
						if(is_null($result)){
							mysqli_query($conn, 'ROLLBACK');
							return "Error: media";
						}
					}

					if($info3['ordertype']=='general'){
						$sql = sprintf("INSERT INTO estimatedetails(productfee,printfee,
								silkprintfee,colorprintfee,digitprintfee,inkjetprintfee,cuttingprintfee,
								exchinkfee,packfee,expressfee,discountfee,reductionfee,carriagefee,
								extracarryfee,designfee,codfee,basefee,salestax,creditfee,orders_id)
							   VALUES(%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d)",
							   $info3["productfee"],
						 	   $info3["printfee"],
						 	   $info3["silkprintfee"],
						 	   $info3["colorprintfee"],
						 	   $info3["digitprintfee"],
						 	   $info3["inkjetprintfee"],
						 	   $info3["cuttingprintfee"],
						 	   $info3["exchinkfee"],
						 	   $info3["packfee"],
						 	   $info3["expressfee"],
						 	   $info3["discountfee"],
						 	   $info3["reductionfee"],
						 	   $info3["carriagefee"],
						 	   $info3["extracarryfee"],
						 	   $info3["designfee"],
						 	   $info3["codfee"],
						 	   $info3["basefee"],
						 	   $info3["salestax"],
						 	   $info3["creditfee"],
						 	   $orders_id);
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return "Error: estimatedetails";
						}

					}else{
						if(!empty($data5)){
							$addestid = array();	// 見積追加行のIDを代入
							$sql = "INSERT INTO additionalestimate(addsummary,addamount,addcost,addprice,orders_id) VALUES";
							for($i=0; $i<count($data5); $i++){
								$sql .= "('".quote_smart($conn, $data5[$i]['addsummary'])."'";
								$sql .= ",".quote_smart($conn, $data5[$i]['addamount']);
								$sql .= ",".quote_smart($conn, $data5[$i]['addcost']);
								$sql .= ",".quote_smart($conn, $data5[$i]['addprice']);
								$sql .= ",".$orders_id."),";
							}
							$sql = substr($sql, 0, -1);
							if(!exe_sql($conn, $sql)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}

							// 登録したIDを取得
							$sql = sprintf("select addestid from additionalestimate where orders_id=%d", $orders_id);
							$res = exe_sql($conn, $sql);
							if(!$res){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
							while($rec = mysqli_fetch_assoc($res)){
								$addestid[] = $rec['addestid'];
							}
						}
					}

				}else{
					mysqli_query($conn, 'ROLLBACK');
					return "Error: data3";
				}
				
				// SESSIONに受注No.を登録
				//$_SESSION['edited'][$orders_id] = time();
				
				$area_ids = '|';
				if(!empty($orderareaid)){
					$area_ids .= implode(',', $orderareaid);
				}
				$ink_ids = '|';
				if(!empty($orderinkid)){
					$ink_ids .= implode(',', $orderinkid);
				}
				$exch_ids = '|';
				if(!empty($exchinkid)){
					$exch_ids .= implode(',', $exchinkid);
				}
				$addest_ids = '|';
				if(!empty($addestid)){
					$addest_ids .= implode(',', $addestid);
				}
				
				//return $orders_id.','.$customer_id.','.$delivery_id.','.$number.$area_ids.$ink_ids.$exch_ids.$addest_ids;
				return TRUE;
				break;
			
			case 'customer':
				foreach($data as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
				}
				$sql = sprintf("select number from customer where cstprefix='%s' order by number desc limit 1 for update", $info['cstprefix']);
				$result = exe_sql($conn, $sql);
				if(!mysqli_num_rows($result)){
					$number = 1;
				}else{
					$res = mysqli_fetch_assoc($result);
					$number = $res['number']+1;
				}
				
				$sql = sprintf("INSERT INTO customer(number,cstprefix,customername,customerruby,zipcode,addr0,addr1,addr2,addr3,addr4,tel,fax,email,mobmail,
				company,companyruby,mobile,job,customernote,bill,remittancecharge,cyclebilling,cutofday,paymentday,consumptiontax)
							   VALUES(%d,'%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s',%d,%d,%d,%d,%d,%d)",
							   $number,
							   $info['cstprefix'],
							   $info["customername"],
						 	   $info["customerruby"],
						 	   $info["zipcode"],
						 	   $info["addr0"],
						 	   $info["addr1"],
						 	   $info["addr2"],
						 	   $info["addr3"],
						 	   $info["addr4"],
						 	   $info["tel"],
						 	   $info["fax"],
						 	   $info["email"],
						 	   $info["mobmail"],
						 	   $info["company"],
						 	   $info["companyruby"],
						 	   $info['mobile'],
						 	   $info["job"],
						 	   $info['customernote'],
						 	   $info['bill'],
						 	   $info['remittancecharge'],
						 	   $info['cyclebilling'],
						 	   $info['cutofday'],
						 	   $info['paymentday'],
						 	   2
								);

				if(exe_sql($conn, $sql)){
					$newid = mysqli_insert_id();
					$rs = array($newid, $number);
				}else{
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}

				return $rs;
				break;
			
			case 'delivery':
				foreach($data as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
				}
				$sql = sprintf("INSERT INTO delivery(organization,agent,team,teacher,delizipcode,deliaddr0,deliaddr1,deliaddr2,deliaddr3,deliaddr4,delitel)
							   VALUES('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",
							   $info["organization"],
						 	   $info["agent"],
						 	   $info["team"],
						 	   $info["teacher"],
						 	   $info["delizipcode"],
						 	   $info["deliaddr0"],
						 	   $info["deliaddr1"],
						 	   $info["deliaddr2"],
						 	   $info["deliaddr3"],
						 	   $info["deliaddr4"],
						 	   $info["delitel"]);
				break;

			case 'shipfrom':
				foreach($data as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
				}
				$sql = sprintf("INSERT INTO shipfrom(shipfromname,shipfromruby,shipzipcode,shipaddr0,shipaddr1,shipaddr2,shipaddr3,shipaddr4,shiptel,shipfax,shipemail)
							   VALUES('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",
							   $info["shipfromname"],
						 	   $info["shipfromruby"],
						 	   $info["shipzipcode"],
						 	   $info["shipaddr0"],
						 	   $info["shipaddr1"],
						 	   $info["shipaddr2"],
						 	   $info["shipaddr3"],
						 	   $info["shipaddr4"],
						 	   $info["shiptel"],
						 	   $info["shipfax"],
						 	   $info["shipemail"]
						 	   );
				break;

			case 'orderitem':
				list($orders_id, $ordertype, $data2) = $data;
				if(empty($data2)) return $orders_id;

				if($ordertype=='general'){	// general
						
					for($c=0; $c<count($data2); $c++){
						$val = $data2[$c];
						if(empty($val['choice'])) continue;
						
						if( preg_match('/^mst/',$val['master_id']) ){
							$prm = explode('_', $val['master_id']);
							$val['item_name'] = $prm[2];
							$val['item_color'] = $prm[3];
							$ppID = $prm[1].'_'.$prm[2];
							
							$item_id = $prm[1]==0? 0: 100000;
							
							// orderprintのIDを取得
							$sql = sprintf("select orderprint.id as print_id from orderprint where orders_id=%d and printposition_id='%s' and category_id=%d limit 1", 
								$orders_id, $ppID, $prm[1]);
							$result = exe_sql($conn, $sql);
							if(!mysqli_num_rows($result)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
							$res = mysqli_fetch_assoc($result);
	
							$sql = sprintf("INSERT INTO orderitem(master_id,size_id,amount,plateis,orders_id,print_id,item_cost,item_printfee,item_printone) VALUES(0,0,%d,%d,%d,%d,%d,%d,%d)",
									$val['amount'], $val['plateis'], $orders_id, $res['print_id'],$val['item_cost'],$val['item_printfee'],$val['item_printone']);
							if(!exe_sql($conn, $sql)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
							$latest = mysqli_insert_id();
							$sql = sprintf("INSERT INTO orderitemext(item_id,item_name,stock_number,maker,size_name,item_color,price,orderitem_id)
							VALUES(%d,'%s','%s','%s','%s','%s','%s',%d)",
									$item_id,
									$val['item_name'],
									$val['stock_number'],
									$val['maker'],
									$val['size_name'],
									$val['item_color'],
									$val['price'],
									$latest);
							$rs = exe_sql($conn, $sql);
							if(!$rs){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
						
						}else{
							
							// orderprintのIDを取得
							$sql = sprintf("select orderprint.id as print_id from (orderprint inner join catalog
									 on orderprint.category_id=catalog.category_id)
									 inner join item on orderprint.printposition_id=item.printposition_id
									 where catalog.item_id=item.id and orders_id=%d and catalog.id=%d",
									$orders_id, $val['master_id']);

							$result = exe_sql($conn, $sql);
							if(!mysqli_num_rows($result)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
							$res = mysqli_fetch_assoc($result);

							$sql2 = sprintf("INSERT INTO orderitem(master_id,size_id,amount,plateis,orders_id,print_id,item_cost,item_printfee,item_printone) VALUES(%d,%d,%d,%d,%d,%d,%d,%d,%d)", 
								$val['master_id'],$val['size_id'],$val['amount'],$val['plateis'],$orders_id,$res['print_id'],$val['item_cost'],$val['item_printfee'],$val['item_printone']);
							$rs = exe_sql($sql2);
							if(!$rs){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
						}
					}
					
				}else{	// industry
					for($i=0; $i<count($data2); $i++){
						foreach($data2[$i] as $key=>$val){
							$info[$key]	= quote_smart($conn, $val);
						}
						
						// orderprintのIDを取得
						if(strpos($info['position_id'], '_')!==false){	// その他商品または持込
							$tmp = explode('_', $info['position_id']);
							$sql = sprintf("select orderprint.id as print_id from orderprint where orders_id=%d and printposition_id='%s' and category_id=%d limit 1",
								$orders_id, $info['position_id'], $tmp[0]);
						}else if($info['item_id']=='99999'){	// 転写シート
							$sql = sprintf("select orderprint.id as print_id from orderprint where orders_id=%d and category_id=99 limit 1",
								$orders_id);
						}else{
							$sql = sprintf("select orderprint.id as print_id from item inner join orderprint
								 on item.printposition_id=orderprint.printposition_id where orders_id=%d and item.id=%d limit 1",
								$orders_id, $info['item_id']);
						}
						$result = exe_sql($conn, $sql);
						if(!mysqli_num_rows($result)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
						$res = mysqli_fetch_assoc($result);

						$sql = sprintf("INSERT INTO orderitem(master_id,size_id,amount,plateis,orders_id,print_id) VALUES(%d,%d,%d,%d,%d,%d)",
								$info['master_id'], $info['size_id'], $info['amount'], $info['plateis'], $orders_id, $res['print_id']);
						$rs = exe_sql($conn, $sql);
						if(!$rs){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
						$latest = mysqli_insert_id();
						$sql = sprintf("INSERT INTO orderitemext(item_id,item_name,stock_number,maker,size_name,item_color,price,orderitem_id)
						VALUES(%d,'%s','%s','%s','%s','%s','%s',%d)",
								$info['item_id'],
								$info['item_name'],
								$info['stock_number'],
								$info['maker'],
								$info['size_name'],
								$info['item_color'],
								$info['price'],
								$latest);
						$rs = exe_sql($conn, $sql);
						if(!$rs){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}
				}

				return $rs;
				break;
				
			case 'discount':
				/**
				*	data	"orders_id"=>n, "discount"=>ディスカウント名(カンマ区切り)
				*/
				foreach($data as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
				}
				$tmp = explode(',', $info['discount']);

				$sql = "INSERT INTO discount(discount_name,discount_state,orders_id) VALUES";
				for($t=0; $t<count($tmp); $t++){
					$discount_name = substr($tmp[$t], 0, -1);
					$state         = substr($tmp[$t],-1);
					if($state==1){
						$sql2 .= "('".$discount_name."',1,".$info["orders_id"]."),";
					}
				}

				if(empty($sql2)) return 0;
				$sql .= substr($sql2, 0, -1);

				break;

			case 'estimatedetails':
				foreach($data as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
				}
				$sql = sprintf("INSERT INTO estimatedetails(productfee,printfee,
								silkprintfee,colorprintfee,digitprintfee,inkjetprintfee,cuttingprintfee,
								exchinkfee,packfee,expressfee,discountfee,reductionfee,carriagefee,
								extracarryfee,designfee,codfee,basefee,salestax,creditfee,orders_id)
							   VALUES(%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d)",
							   $info["productfee"],
						 	   $info["printfee"],
						 	   $info["silkprintfee"],
						 	   $info["colorprintfee"],
						 	   $info["digitprintfee"],
						 	   $info["inkjetprintfee"],
						 	   $info["cuttingprintfee"],
						 	   $info["exchinkfee"],
						 	   $info["packfee"],
						 	   $info["expressfee"],
						 	   $info["discountfee"],
						 	   $info["reductionfee"],
						 	   $info["carriagefee"],
						 	   $info["extracarryfee"],
						 	   $info["designfee"],
						 	   $info["codfee"],
						 	   $info["basefee"],
						 	   $info["salestax"],
						 	   $info["creditfee"],
						 	   $info["orders_id"]);
				break;

			case 'media':
				/*
				* data	"orders_id"=>n,
				* 		"media"=>[name値|value値のカンマ区切り, その他のテキスト]
				*/
				$orders_id = quote_smart($conn, $data['orders_id']);
				list($list1, $media_other) = $data['media'];
				$tmp = explode(',', $list1);

				$sql = 'INSERT INTO media(media_type, media_value, orders_id) VALUES';
				for($i=0; $i<count($tmp); $i++){
					$media = explode('|', $tmp[$i]);
					$sql2 .= '("'.$media[0].'","'.$media[1].'",'.$orders_id.'),';
				}
				if(!empty($media_other)){
					$sql2 .= '("mediacheck03","'.quote_smart($conn, $media_other).'",'.$orders_id.'),';
				}
				if(empty($sql2)) return 0;
				$sql .= substr($sql2, 0, -1);

				break;
			
			case 'additionalestimate':
				$sql = "INSERT INTO additionalestimate(addsummary,addamount,addcost,addprice,orders_id) VALUES";
				for($i=0; $i<count($data); $i++){
					$sql .= "('".quote_smart($conn, $data[$i]['addsummary'])."'";
					$sql .= ",".quote_smart($conn, $data[$i]['addamount']);
					$sql .= ",".quote_smart($conn, $data[$i]['addcost']);
					$sql .= ",".quote_smart($conn, $data[$i]['addprice']);
					$sql .= ",".quote_smart($conn, $data[$i]['orders_id'])."),";
				}
				$sql = substr($sql, 0, -1);
				break;
			
			case 'firstcontact':
				foreach($data as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
				}
				$info['firstcontactdate'] = date("Y-m-d");
				$sql = sprintf("INSERT INTO contactchecker(orders_id,firstcontactdate,staff_id,medianame,attr)
							   VALUES(%d,'%s',%d,'%s','%s')",
							   $info["orders_id"],
						 	   $info["firstcontactdate"],
						 	   $info["staff_id"],
						 	   $info["medianame"],
						 	   $info["attr"]
						 	   );
				break;

			case 'supplier':
				foreach($data as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
				}
				$sql = sprintf("INSERT INTO supplier(suppliername,represent,zipcode,addr1,addr2,tel,fax,email,weburl,
				contactname,contactmobile,contactemail,classify,outsource,articles,suppliernote)
							   VALUES('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",
							   $info["suppliername"],
						 	   $info["represent"],
						 	   $info["zipcode"],
						 	   $info["addr1"],
						 	   $info["addr2"],
						 	   $info["tel"],
						 	   $info["fax"],
						 	   $info["email"],
						 	   $info["weburl"],
						 	   $info['contactname'],
						 	   $info['contactmobile'],
						 	   $info['contactemail'],
						 	   $info['classify'],
						 	   $info['outsource'],
						 	   $info['articles'],
						 	   $info['suppliernote']
								);
				break;

			case 'direction':
				/*
				*	製作指示書の登録（作成）
				*/
				$orders_id = quote_smart($conn, $data['orders_id'] );
				$ordertype = quote_smart($conn, $data['ordertype'] );


				// 既存の製作指示書データを確認
				$products = array();		// プリント方法（print_key）をキーにしたハッシュ
				$del_product_id = array();	// 削除する product テーブルのID検出用
				$del_pinfo_id = array();	// 削除する printinfo テーブルのID検出用
				$del_padj_id = array();		// 削除する printadj テーブルのID検出用
				
				$res = $this->search($conn, 'printinfo', array('orders_id'=>$orders_id));
				if(empty($res)){
				// 新規の製作指示書
					$sync_envelope = 0;
					//$sync_boxnumber = 0;
					$sync_shipnote = "";
				}else{
				// 既存の製作指示書がある
					$sync_envelope = $res[0]['envelope'];
					//$sync_boxnumber = $res[0]['boxnumber'];
					$sync_shipnote = $res[0]['ship_note'];
					for($i=0; $i<count($res); $i++){
						$del_product_id[$res[$i]['product_id']] = $res[$i]['product_id'];
						$del_pinfo_id[$res[$i]['pinfoid']] = $res[$i]['pinfoid'];
						$del_padj_id[$res[$i]['padjid']] = $res[$i]['padjid'];
						$products[$res[$i]['print_key']][] = $res[$i];
					}
				}
				
				// 受注伝票のデータを集計
				$res = $this->search($conn, 'product', array('orders_id'=>$orders_id, 'order_type'=>$ordertype));
				if(!empty($res)){
					$designrepeat = empty($res[0]['repeater'])? 0: 1;
					
					// リピート版で新規の保存の場合に受注元の製作指示書をコピー
					$isRepeat = false;
					if(empty($products) && $designrepeat==1){
						$isRepeat = true;
						$res2 = $this->search($conn, 'printinfo', array('orders_id'=>$res[0]['repeater']));
						for($i=0; $i<count($res2); $i++){
							$origin[$res2[$i]['print_key']][] = $res2[$i];
						}
					}
					
					$noprint = $res[0]['noprint'];
					for($i=0; $i<count($res); $i++){
						$prn[$res[$i]['print_type']] = $res[$i]['printtypeid'];
						$orders[$res[$i]['print_type']][$res[$i]['areaid']] = $res[$i];
						$sizeinfo[$res[$i]['print_type']][$res[$i]['areaid']][$res[$i]['size_name']] = true;
						
						
						if($noprint){
							// プリント無しの場合
							$printinghash[$res[$i]['print_type']] = '商品のみ';
						}else{
							// print_keyをKEYにしたprint_nameのハッシュ
							$printinghash[$res[$i]['print_type']] = $res[$i]['print_name'];
						}
					}
					

					/* プリント方法ごとにレコードを検証して関連テーブルも含めて更新する */
					foreach($prn as $print_key=>$printtype_id){						
						$isNewproduct = true;				// 登録済みデータの確認フラグ
						
						if(!empty($products[$print_key])){	// 当該プリント方法のデータが既にある場合
							$product_id = $products[$print_key][0]['product_id'];
							$isNewproduct = false;
							unset($del_product_id[$product_id]);
						}

						// 製作指示書が新規若しくは新しいプリント方法が追加されていた場合
						if($isNewproduct){
						
							if($isRepeat){
								$mesh = $origin[$print_key][0]['mesh'];
								$medome = $origin[$print_key][0]['medome'];
								$plates = $origin[$print_key][0]['plates'];
								$arrange = $origin[$print_key][0]['arange'];
								$workshop_note = quote_smart($conn, $origin[$print_key][0]['workshop_note']);
								$platescheck = $origin[$print_key][0]['platescheck'];
								$pastesheet = $origin[$print_key][0]['pastesheet'];
								$edge = $origin[$print_key][0]['edge'];
								$edgecolor = $origin[$print_key][0]['edgecolor'];
								$cutpattern = $origin[$print_key][0]['cutpattern'];
								$sheetcount = $origin[$print_key][0]['sheetcount'];
								$platescount = $origin[$print_key][0]['platescount'];
								$envelope = 0;
								//$boxnumber = 0;
								$ret_note = $origin[$print_key][0]['ret_note'];
								$ship_note = "";
							}else{
								if($print_key=="silk"){
									$mesh = 120;
									$medome = "油性";
									$plates = 'ゾル';
								}else if($print_key=="digit"){
									$mesh = 100;
									$medome = "水性";
									$plates = '転写';
								}else{
									$mesh = "";
									$medome = "";
									$plates = 'ダイレクト';
								}
								
								$arrange = 1;
								$workshop_note = "";
								$platescheck = 1;
								$pastesheet = 1;
								$edge = 1;
								$edgecolor = "";
								$cutpattern = 0;
								$sheetcount = 0;
								$platescount = 0;
								$envelope = $sync_envelope;
								//$boxnumber = $sync_boxnumber;
								$ret_note = "";
								$ship_note = $sync_shipnote;
							}
							
							$sql = sprintf("INSERT INTO product(orders_id, plate_id, printtype, arrange, designrepeat, product_note, office_note, workshop_note, mesh, 
								platescheck, pastesheet, edge, edgecolor, cutpattern, sheetcount, platescount, medome, plates,
								envelope, shipment, ret_note, ship_note) VALUES
								(%d,%d,%d,%d,%d,'%s','%s','%s','%s',%d,%d,%d,'%s',%d,%d,%d,'%s','%s','%s','%s','%s','%s')", 
								$orders_id, 1, $printtype_id, $arrange, $designrepeat, "", "", $workshop_note, $mesh,
								$platescheck, $pastesheet, $edge, $edgecolor, $cutpattern, $sheetcount, $platescount, $medome, $plates,
								$envelope, "", $ret_note, $ship_note
								);
							if(!exe_sql($conn, $sql)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
							$product_id = mysqli_insert_id();
							
							// リピート版注文で製作指示書を新規で登録する場合
							if($isRepeat){
								if($print_key=="digit" && $platescheck==2){
								// デジタル転写で版元が再版の場合にprintstatusを「再」にする
									$sql = sprintf("select prnstatusid from printstatus where orders_id=%d and printtype_key='digit'", $orders_id);
									$result = exe_sql($conn, $sql);
									if($result===false){
										mysqli_query($conn, 'ROLLBACK');
										return null;
									}
									$res = mysqli_fetch_assoc($result);
									
									$sql = sprintf("update printstatus set state_1=%d, state_2=%d where prnstatusid=%d",43,43,$res['prnstatusid']);
									if(!exe_sql($conn, $sql)){
										mysqli_query($conn, 'ROLLBACK');
										return null;
									}
								}else if($print_key!="silk" && $print_key!="digit"){
								// インクジェット、カッティング、カラー転写は「版下」を「再」にする
									$sql = sprintf("select prnstatusid from printstatus where orders_id=%d and printtype_key='%s'", $orders_id, $print_key);
									$result = exe_sql($conn, $sql);
									if($result===false){
										mysqli_query($conn, 'ROLLBACK');
										return null;
									}
									$res = mysqli_fetch_assoc($result);
									
									$sql = sprintf("update printstatus set state_1=%d where prnstatusid=%d",43,$res['prnstatusid']);
									if(!exe_sql($conn, $sql)){
										mysqli_query($conn, 'ROLLBACK');
										return null;
									}
								}
							}
						}

						foreach($orders[$print_key] as $areaid=>$val){

							$isNew = true;			// 当該タブ名の登録の有無
							$adj_size = array();	// 登録済みのサイズ名をキー、padjid を値にしたハッシュ
							
							if(!$isNewproduct){		// プリント位置のタブ情報の有無を確認
								for($r=0; $r<count($products[$print_key]); $r++){
									$cur = $products[$print_key][$r];
									if($cur['print_category_id']==$val['category_id'] && 
									   $cur['print_posid']==$val['printposition_id'] && 
									   $cur['area_key']==$val['area_name'] &&
									   $cur['print_posname']==$val['selective_name'])
									{
										$isNew = false;
										$pinfoid = $cur['pinfoid'];
										$adj_size[$cur['sizename']] = $cur['padjid'];
										unset($del_pinfo_id[$pinfoid]);	// 使用している printinfo IDを除外
									}
								}
							}
							
							$area_size = $sizeinfo[$print_key][$areaid];
							if($isNew){
							// プリント位置のタブごとのデータを登録
								$isReprint = false;	// 再販かどうかのチェック
								if($isRepeat){
									$isExist = false;
									for($r=0; $r<count($origin[$print_key]); $r++){
										$cur = $origin[$print_key][$r];
										if($cur['print_category_id']==$val['category_id'] && 
										   $cur['print_posid']==$val['printposition_id'] && 
										   $cur['area_key']==$val['area_name'] &&
										   $cur['print_posname']==$val['selective_name'])
										{
											$remark = $cur['remark'];
											$reprint = empty($cur['reprint'])? 1: $cur['reprint'];
											$platesinfo = empty($cur['platesinfo'])? 'ゾル': $cur['platesinfo'];
											$meshinfo = empty($cur['meshinfo'])? '120': $cur['meshinfo'];
											$attrink = empty($cur['attrink'])? '油性': $cur['attrink'];
											$platesnumber = empty($cur['platesnumber'])? 0: $cur['platesnumber'];
											$adj_size[$cur['sizename']] = array('vert'=>$cur['vert'], 'hori'=>$cur['hori']);
											
											$isExist = true;
											if($reprint==2) $isReprint = true;
										}
									}
									
									// 元受注には無いプリント位置の場合は初期値
									if(!$isExist){
										$remark = "";
										$reprint = 1;
										$platesinfo = 'ゾル';
										$meshinfo = '120';
										$attrink = '油性';
										$platesnumber = 0;
									}
								}else{
									$remark = "";
									$reprint = 1;
									$platesinfo = 'ゾル';
									$meshinfo = '120';
									$attrink = '油性';
									$platesnumber = 0;
								}
								$sql = "insert into printinfo(product_id, remark, print_category_id, print_posid, area_key, print_posname,";
								$sql .= "reprint, platesinfo, meshinfo, attrink, platesnumber) values";
								$sql .= "(".$product_id.", '".$remark."', ".$val['category_id'].", '".$val['printposition_id']."', '".$val['area_name']."', '".$val['selective_name']."',";
								$sql .= $reprint.", '".$platesinfo."', '".$meshinfo."', '".$attrink."', ".$platesnumber.")";
								if(!exe_sql($conn, $sql)){
									mysqli_query($conn, 'ROLLBACK');
									return null;
								}
								$pinfoid = mysqli_insert_id();

								// サイズを登録
								$sql = "insert into printadj(printinfo_id,sizename,vert,hori) values";
								foreach($area_size as $size_name=>$chk2){
									if(!empty($adj_size[$size_name])){
										$vert = $adj_size[$size_name]['vert'];
										$hori = $adj_size[$size_name]['hori'];
									}else{
										$vert = 0;
										$hori = 0;
									}
									$sql .= "(".$pinfoid.",'".$size_name."',".$vert.",".$hori."),";
								}
								$sql = substr($sql, 0, -1);
								if(!exe_sql($conn, $sql)){
									mysqli_query($conn, 'ROLLBACK');
									return null;
								}
								
								// リピート版注文でシルクの製作指示書を新規登録で、
								// 版元が再版の場合にprintstatusを「再」にする
								if($isRepeat && $print_key=="silk" && $isReprint){
									$sql = sprintf("select prnstatusid from printstatus where orders_id=%d and printtype_key='silk'", $orders_id);
									$result = exe_sql($conn, $sql);
									if($result===false){
										mysqli_query($conn, 'ROLLBACK');
										return null;
									}
									$res = mysqli_fetch_assoc($result);
									
									$sql = sprintf("update printstatus set state_1=%d, state_2=%d where prnstatusid=%d",43,43,$res['prnstatusid']);
									if(!exe_sql($conn, $sql)){
										mysqli_query($conn, 'ROLLBACK');
										return null;
									}
								}
						
							}else{
							// 既存のタブ情報がある場合は、サイズの増加分のみ登録
								$add='';
								foreach($area_size as $size_name=>$chk2){
									if(empty($adj_size[$size_name])){
										$add .= "(".$pinfoid.",'".$size_name."',0,0),";
									}else{
										unset($del_padj_id[$adj_size[$size_name]]);	// 使用している printadj IDを除外
									}
								}
								if(!empty($add)){
									$add = substr($add, 0, -1);
									$sql = "insert into printadj(printinfo_id,sizename,vert,hori) values".$add;
									if(!exe_sql($conn, $sql)){
										mysqli_query($conn, 'ROLLBACK');
										return null;
									}
								}
							}
						}
					}
				}
				
				// 未使用になったレコードを削除
				if(!empty($del_product_id)){
					$r = exe_sql("delete from product where id in(".implode(',',$del_product_id).")");
					if(!$r){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}
				
				if(!empty($del_pinfo_id)){
					$r = exe_sql("delete from printinfo where pinfoid in(".implode(',',$del_pinfo_id).")");
					if(!$r){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}
				if(!empty($del_padj_id)){
					$r = exe_sql("delete from printadj where padjid in(".implode(',',$del_padj_id).")");
					if(!$r){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}

				if(isset($data['printinghash'])){
					return $printinghash;
				}else{
					return $product_id;
				}
				
				break;

			case 'cashbook':
				$orders_id = quote_smart( $data[0]['orders_id'] );
				$recdate = quote_smart( $data[0]['recdate'] );
				$sql = "INSERT INTO cashbook(recdate,bankname,summary,classification,netsales,receiptmoney,orders_id) VALUES";
				for($t=0; $t<count($data); $t++){
					$sql .= "('".$recdate."'";
					$sql .= ",".quote_smart($conn, $data[$t]['bankname']);
					$sql .= ",'".quote_smart($conn, $data[$t]['summary'])."'";
					$sql .= ",'".quote_smart($conn, $data[$t]['classification'])."'";
					$sql .= ",".quote_smart($conn, $data[$t]['netsales']);
					$sql .= ",".quote_smart($conn, $data[$t]['receiptmoney']);
					$sql .= ",".$orders_id."),";
				}
				$sql = substr($sql, 0, -1);

				if(exe_sql($conn, $sql)){
				// 消込確認
					$rs = mysqli_insert_id();
					$sql = sprintf("select sum(netsales) - sum(receiptmoney) as balance from cashbook where orders_id='%s'",$orders_id);
					$result = exe_sql($conn, $sql);
					if(empty($result)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}

					$rec = mysqli_fetch_assoc($result);
					if($rec['balance']>0){	// 未消込
						$result = $this->update($conn, 'progressstatus', array('orders_id'=>$orders_id, 'deposit'=>1));
					}else{
						$result = $this->update($conn, 'progressstatus', array('orders_id'=>$orders_id, 'deposit'=>2));
					}
					if(empty($result)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}else{
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}
				return $rs;
				break;

			case 'customerlog':
				$sql = "insert into customerlog (orders_id,cstlog_date,cstlog_staff,cstlog_text) values";
				$sql .= "(".quote_smart($conn, $data['orders_id']);
				$sql .= ",'".quote_smart($conn, $data['cstlog_date'])."'";
				$sql .= ",".quote_smart($conn, $data['cstlog_staff']);
				$sql .= ",'".quote_smart($conn, $data['cstlog_text'])."')";

				break;

			case 'mailhistory':
				$sql = "insert into mailhistory (subject,mailbody,mailaddr,orders_id,cst_number,cst_prefix,cst_name,sendmaildate,staff_id) values";
				$sql .= "(".quote_smart($conn, $data['subject']);
				$sql .= ",'".quote_smart($conn, $data['mailbody'])."'";
				$sql .= ",'".quote_smart($conn, $data['mailaddr'])."'";
				$sql .= ",".quote_smart($conn, $data['orders_id']);
				$sql .= ",".quote_smart($conn, $data['cst_number']);
				$sql .= ",'".quote_smart($conn, $data['cst_prefix'])."'";
				$sql .= ",'".quote_smart($conn, $data['cst_name'])."'";
				$sql .= ",'".quote_smart($conn, $data['sendmaildate'])."'";
				$sql .= ",".quote_smart($conn, $data['staff_id']).")";
				
				break;
				
			case 'userreview':
				$sql = sprintf('insert into userreview (item_id, item_name, printkey, amount, reason, impression, staff_comment, vote_1, vote_2, vote_3, vote_4, posted) values("%s","%s","%s",%d,"%s","%s","%s",%d,%d,%d,%d,"%s")',
						$data['item_id'], $data['item_name'], $data['printkey'], $data['amount'], quote_smart($conn, $data['reason']), quote_smart($conn, $data['impression']), quote_smart($conn, $data['staff_comment']),
						$data['vote_1'], $data['vote_2'], $data['vote_3'], $data['vote_4'], $data['posted']);
				break;
				
			case 'itemreview':
				$sql = sprintf('insert into itemreview (item_id, item_name, printkey, amount, review, vote) values(%d,"%s","%s",%d,"%s",%d)',
						$data['item_id'], $data['item_name'], $data['printkey'], $data['amount'], quote_smart($conn, $data['review']), $data['vote']);
				break;
			}


			if(exe_sql($conn, $sql)){
				$rs = mysqli_insert_id();
			}else{
				mysqli_query($conn, 'ROLLBACK');
				return null;
			}

		}catch(Exception $e){
			mysqli_query($conn, 'ROLLBACK');
			$rs = null;
		}

		return $rs;
	}


	/***************************************************************************************************************
	*	レコードの修正更新
	*	@table		テーブル名
	*	@data		更新データの配列
	*
	*	return		成功したら更新されたレコード数
	*/
	private function update($conn, $table, $data){
		try{
			$flg= true;
			switch($table){
			case 'order':
				/**
				 *	data1	顧客
				 *	data2	お届け先
				 *	data3	受注伝票
				 *	data4	業者の時の注文商品
				 *	data5	業者の時の見積追加行
				 *	data6	プリント情報（orderprint）
				 *	data7	プリント位置（orderarea）
				 *	data8	プリントポジション（orderselectivearea）
				 *	data9	インク（orderink）
				 *	data10	インク色替え（exchink）
				 *	data12	発送元
				 *
				 *	return	受注ID, 顧客ID, 顧客Number | プリント位置ID,, | 見積追加行ID,,
				 */
				list($data1, $data2, $data3, $data4, $data5, $data6, $data7, $data8, $data9, $data10, $data12) = $data;
				$sql = sprintf("select * from orders left join customer on customer_id=customer.id where orders.id=%d", $data3['id']);
				$rs = exe_sql($conn, $sql);
				if(!$rs){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}
				$cur = mysqli_fetch_array($rs);
				$ship_id = $cur['shipfrom_id'];
				$number = '';
				
				$orders_id = $data3['id'];
				$bill_type = $data3['bill'];
				
				$isBundle = $cur['bundle'];
				if($isBundle==1){
					$bundle1 = $this->search($conn, 'bundlecount', array('orders_id'=>$orders_id));
				}else{
					$bundle1 = array();
				}
				
				if(isset($data1)){
					list($customer_id, $number) = $this->insert($conn, 'customer', $data1);
					$data3["customer_id"] = $customer_id;
					$bill_type = $data1['bill'];
					if(empty($customer_id)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}else{
					$customer_id = $data3["customer_id"];
				}
				
				$delivery_id = 0;
				if(empty($data2['delivery_id'])){
					$isData = false;
					foreach($data2 as $val){
						if(!empty($val)) $isData = true;
					}
					if($isData){
						$delivery_id = $this->insert($conn, 'delivery', $data2);
						if(empty($delivery_id)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}
				}else{
					$delivery_id = $data2['delivery_id'];
					if(!$this->update($conn, 'delivery', $data2)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}

				if(isset($data12)){
					if($ship_id==0){
						$ship_id = $this->insert($conn, 'shipfrom', $data12);
						if(empty($ship_id)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}else{
						$data12['shipid'] = $ship_id;
						if(!$this->update($conn, 'shipfrom', $data12)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}
				}

				$data3["delivery_id"] = $delivery_id;
				$data3["shipfrom_id"] = $ship_id;
				$data3['lastmodified'] = date("Y-m-d");
				$sql= sprintf("UPDATE orders SET reception=%d,ordertype='%s',applyto=%d,maintitle='%s',
							schedule1='%s',schedule2='%s',schedule3='%s',schedule4='%s',destination=%d,arrival='%s',handover='%s',
							carriage='%s',check_amount=%d,noprint=%d,design='%s',manuscript='%s',discount1='%s',discount2='%s',
							reduction=%d,reductionname='%s',freeshipping=%d,payment='%s',order_comment='%s',invoicenote='%s',billnote='%s',phase='%s',budget=%d,customer_id=%d,delivery_id=%d,lastmodified='%s',
							estimated=%d,order_amount=%d,paymentdate='%s',exchink_count=%d,deliver=%d,deliverytime=%d,manuscriptdate='%s',
							purpose='%s',purpose_text='%s',job='%s',designcharge=%d,repeater=%d,reuse=%d,free_discount=%d,free_printfee=%d,
							completionimage=%d, contact_number='%s', additionalname='%s', additionalfee=%d, extradiscountname='%s', extradiscount=%d, shipfrom_id=%d,
							package_yes=%d,package_no=%d,package_nopack=%d,pack_yes_volume=%d,pack_nopack_volume=%d,boxnumber=%d,factory=%d,destcount=%d,repeatdesign=%d,allrepeat=%d, staffdiscount=%d
							 WHERE id=%d",
						   	$data3["reception"],
					 	   	$data3["ordertype"],
					 	   	$data3["applyto"],
					 	   	$data3["maintitle"],
					 	   	$data3["schedule1"],
					 	   	$data3["schedule2"],
					 	   	$data3["schedule3"],
					 	   	$data3["schedule4"],
					 	   	$data3["destination"],
							$data3["arrival"],
							$data3["handover"],
					 	   	$data3["carriage"],
					 	   	$data3["check_amount"],
					 	   	$data3["noprint"],
					 	   	$data3["design"],
					 	   	$data3["manuscript"],
					 	   	$data3["discount1"],
					 	   	$data3["discount2"],
					 	   	$data3["reduction"],
					 	   	$data3["reductionname"],
					 	   	$data3["freeshipping"],
					 	   	$data3["payment"],
					 	   	$data3["order_comment"],
					 	   	$data3["invoicenote"],
					 	   	$data3["billnote"],
					 	   	$data3["phase"],
					 	   	$data3["budget"],
					 	   	$data3["customer_id"],
					 	   	$data3["delivery_id"],
					 	   	$data3["lastmodified"],
					 	   	$data3["estimated"],
					 	   	$data3["order_amount"],
					 	   	$data3["paymentdate"],
					 	   	$data3["exchink_count"],
					 	   	$data3["deliver"],
					 	   	$data3["deliverytime"],
					 	   	$data3["manuscriptdate"],
					 	   	$data3["purpose"],
					 	   	$data3["purpose_text"],
					 	   	$data3["job"],
					 	   	$data3["designcharge"],
					 	   	$data3["repeater"],
					 	   	$data3["reuse"],
					 	   	$data3["free_discount"],
					 	   	$data3["free_printfee"],
					 	   	$data3["completionimage"],
					 	   	$data3["contact_number"],
					 	   	$data3["additionalname"],
					 	   	$data3["additionalfee"],
					 	   	$data3["extradiscountname"],
					 	   	$data3["extradiscount"],
					 	   	$data3["shipfrom_id"],
					 	   	$data3["package_yes"],
							$data3["package_no"],
							$data3["package_nopack"],
							$data3["pack_yes_volume"],
							$data3["pack_nopack_volume"],
							$data3["boxnumber"],
							$data3["factory"],
							$data3["destcount"],
							$data3["repeatdesign"],
							$data3["allrepeat"],
							$data3["staffdiscount"],
					 	   	$data3["id"]);
				$rs = exe_sql($conn, $sql);
				if(!$rs){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}
				
				// 同梱指定があった場合に更新後の同梱状態を再確認
				$bundle_check = 0;
				$bundle2 = $this->search($conn, 'bundlecount', array('orders_id'=>$orders_id));
				if($isBundle==1){
					if(count($bundle2)<2){
						// 当該注文の同梱数が1以下の場合
						if(count($bundle1)==2){
							// 更新前の同梱数が2の場合は全て外す
							for($i=0; $i<2; $i++){
								$sql2 = sprintf("update orders set bundle=0, contact_number='' where id=%d", $bundle1[$i]['id']);
								if(!exe_sql($sql2)){
									mysqli_query($conn, 'ROLLBACK');
									return null;
								}
							}
						}else{
							// 当該注文のみ同梱チェックを外す
							$sql2 = sprintf("update orders set bundle=0, contact_number='' where id=%d", $orders_id);
							if(!exe_sql($sql2)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
						}
					}else{
						/* 2014-11-14 変更
						$bundle_check = 1;
						if(count($bundle1)==2 && $bundle1!=$bundle2){
							// 更新前の同梱数が2で且つ当該注文が別の注文との同梱に変わった場合、他方のチェックのみを外す
							for($i=0; $i<2; $i++){
								if($bundle1[$i]['id']==$orders_id) continue;
								$sql2 = sprintf("update orders set bundle=0 where id=%d", $bundle1[$i]['id']);
								if(!exe_sql($sql2)){
									mysqli_query($conn, 'ROLLBACK');
									return null;
								}
							}
						}
						*/
						
						if($bundle1==$bundle2){
							$bundle_check = 1;
						}else{
							// 同梱指定条件が変更した場合
							if(count($bundle1)==2){
								// 更新前の同梱数が2の場合は全て外す
								for($i=0; $i<2; $i++){
									$sql2 = sprintf("update orders set bundle=0, contact_number='' where id=%d", $bundle1[$i]['id']);
									if(!exe_sql($sql2)){
										mysqli_query($conn, 'ROLLBACK');
										return null;
									}
								}
							}else{
								// 当該注文のみ同梱チェックを外す、同梱条件の合う別の注文があった場合も一旦チェックを外す
								$sql2 = sprintf("update orders set bundle=0, contact_number='' where id=%d", $orders_id);
								if(!exe_sql($sql2)){
									mysqli_query($conn, 'ROLLBACK');
									return null;
								}
							}
						}
					}
					
					// 同梱指定に変更あり
					if($bundle_check==0){
						$bundle_id = array();
						for($i=0; $i<count($bundle1); $i++){
							$bundle_id[] = $bundle1[$i]['id'];
						}
						$sql2 = "update orders set contact_number='' where id in(".implode(",", $bundle_id).")";
						if(!exe_sql($sql2)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}
				}
				
				// 同梱指定後に問い合わせ番号を更新した場合
				if($bundle_check==1){
					$bundle_id = array();
					for($i=0; $i<count($bundle2); $i++){
						$bundle_id[] = $bundle2[$i]['id'];
					}
					$sql2 = "select contact_number from orders where contact_number!='' and id in(".implode(",", $bundle_id).")";
					$result = exe_sql($sql2);
					if($result===false){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					$rec = mysqli_fetch_assoc($result);
					$contact_number = $rec['contact_number'];
					if(!empty($contact_number)){
						$sql2 = "update orders set contact_number='".$contact_number."' where id in(".implode(",", $bundle_id).")";
						if(!exe_sql($sql2)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}
				}
				
				// 既存のプリント情報を取得して削除されたデータを確認
				$tmp = array();			// 既存のデータを一時格納
				$del_id = array();		// 削除するID
				$print_id = array();	// orderareaの検索時に渡すorderprint_id
				$sql = sprintf("select * from orderprint where orders_id=%d", $orders_id);
				$res = exe_sql($conn, $sql);
				while($rec=mysqli_fetch_assoc($res)){
					$tmp[] = $rec;
				}
				for($i=0; $i<count($tmp); $i++){
					$isExist = false;
					for($t=0; $t<count($data6); $t++){
						if($data6[$t]['category_id']==$tmp[$i]['category_id'] && $data6[$t]['printposition_id']==$tmp[$i]['printposition_id']){
							$isExist = true;
							$orderprint[] = $tmp[$i];
							$print_id[] = $tmp[$i]['id'];
							break;
						}
					}
					if(!$isExist){
						$del_id[] = $tmp[$i]['id'];
					}
				}
				if(!empty($del_id)){
					$sql = 'delete from orderprint where id in ('.implode(',', $del_id). ')';
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}


				$area_id = array();	// orderselectiveareaとorderinkの検索時に渡すorderarea_id
				if(!empty($print_id)){
					$tmp = array();
					$del_id = array();
					$res = exe_sql('select * from orderarea where orderprint_id in('.implode(',', $print_id).')');
					while($rec=mysqli_fetch_assoc($res)){
						$tmp[] = $rec;
					}
					for($i=0; $i<count($tmp); $i++){
						$isExist = false;
						for($t=0; $t<count($data7); $t++){
							if($data7[$t]['areaid']==$tmp[$i]['areaid']){
								$isExist = true;
								$orderarea[] = $tmp[$i];
								$area_id[] = $tmp[$i]['areaid'];
								break;
							}
						}
						if(!$isExist){
							$del_id[] = $tmp[$i]['areaid'];
						}
					}
					if(!empty($del_id)){
						$sql = 'delete from orderarea where areaid in ('.implode(',', $del_id). ')';
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}
				}


				$ink_id = array();	// exchinkの検索時に渡すorderink_id
				if(!empty($area_id)){
					$tmp = array();
					$del_id = array();
					$res = exe_sql('select * from orderselectivearea where orderarea_id in('.implode(',', $area_id).')');
					while($rec=mysqli_fetch_assoc($res)){
						$tmp[] = $rec;
					}
					for($i=0; $i<count($tmp); $i++){
						$isExist = false;
						for($t=0; $t<count($data8); $t++){
							if($data8[$t]['areaid']==$tmp[$i]['orderarea_id']){
								$isExist = true;
								$orderselective[$tmp[$i]['orderarea_id']] = $tmp[$i];	// 1つのorderareaで指定できるプリント位置は1ヵ所なのでorderarea_idをキーにする
								break;
							}
						}
						if(!$isExist){
							$del_id[] = $tmp[$i]['selectiveid'];
						}
					}
					if(!empty($del_id)){
						$sql = 'delete from orderselectivearea where selectiveid in ('.implode(',', $del_id). ')';
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}


					$tmp = array();
					$del_id = array();
					$res = exe_sql('select * from orderink where orderarea_id in('.implode(',', $area_id).')');
					while($rec=mysqli_fetch_assoc($res)){
						$tmp[] = $rec;
					}
					for($i=0; $i<count($tmp); $i++){
						$isExist = false;
						for($t=0; $t<count($data9); $t++){
							if($data9[$t]['inkid']==$tmp[$i]['inkid']){
								$isExist = true;
								$orderink[] = $tmp[$i];
								$ink_id[] = $tmp[$i]['inkid'];
								break;
							}
						}
						if(!$isExist){
							$del_id[] = $tmp[$i]['inkid'];
						}
					}
					if(!empty($del_id)){
						$sql = 'delete from orderink where inkid in ('.implode(',', $del_id). ')';
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}
				}
				
				/*
				if(!empty($ink_id)){
					$tmp = array();
					$del_id = array();
					$res = exe_sql('select * from exchink where orderink_id in('.implode(',', $ink_id).')');
					while($rec=mysqli_fetch_assoc($res)){
						$tmp[] = $rec;
					}
					for($i=0; $i<count($tmp); $i++){
						$isExist = false;
						for($t=0; $t<count($data10); $t++){
							if($data10[$t]['exchid']==$tmp[$i]['exchid']){
								$isExist = true;
								$exchink[] = $tmp[$i];
								break;
							}
						}
						if(!$isExist){
							$del_id[] = $tmp[$i]['exchid'];
						}
					}
					if(!empty($del_id)){
						$sql = 'delete from exchink where exchid in ('.implode(',', $del_id). ')';
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return 'error delete exchink';
						}
					}
				}
				*/

				// プリント情報の追加と修正
				$orderareaid = array();
				$orderinkid = array();
				$exchinkid = array();
				for($i=0; $i<count($data6); $i++){

					// orderprint 既存データの確認
					$orderprint_id = 0;
					for($t=0; $t<count($orderprint); $t++){
						if($data6[$i]['category_id']==$orderprint[$t]['category_id'] && $data6[$i]['printposition_id']==$orderprint[$t]['printposition_id']){
							$orderprint_id = $orderprint[$t]['id'];
							break;
						}
					}
					
					// 新規追加
					if($orderprint_id==0){
						$sql = sprintf("INSERT INTO orderprint(orders_id,category_id,printposition_id,subprice) VALUES(%d,%d,'%s',%d)",
								$orders_id,
								quote_smart($conn, $data6[$i]['category_id']),
								quote_smart($conn, $data6[$i]['printposition_id']),
								quote_smart($conn, $data6[$i]['subprice']));
						if(exe_sql($conn, $sql)){
							$orderprint_id = mysqli_insert_id();
						}else{
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
						
						// orderarea
						for($t=0; $t<count($data7); $t++){
							if($data7[$t]['print_id']!=$i) continue;
							$sql = sprintf("INSERT INTO orderarea(orderprint_id,area_path,area_name,origin,ink_count,print_type,
								areasize_from,areasize_to,areasize_id,print_option,jumbo_plate,design_plate,design_type,design_size,repeat_check,silkmethod)
								VALUES(%d,'%s','%s',%d,%d,'%s',%d,%d,%d,%d,%d,%d,'%s','%s',%d,%d)",
								$orderprint_id,
								'txt/'.$data7[$t]['area_path'].'/'.$data7[$t]['area_name'].'.txt',
								$data7[$t]['area_name'],
								$data7[$t]['origin'],
								$data7[$t]['ink_count'],
								$data7[$t]['print_type'],
								$data7[$t]['areasize_from'],
								$data7[$t]['areasize_to'],
								$data7[$t]['areasize_id'],
								$data7[$t]['print_option'],
								$data7[$t]['jumbo_plate'],
								$data7[$t]['design_plate'],
								$data7[$t]['design_type'],
								$data7[$t]['design_size'],
								$data7[$t]['repeat_check'],
								$data7[$t]['silkmethod']
								);
							if(exe_sql($conn, $sql)){
								$orderarea_id = mysqli_insert_id();
								$orderareaid[$t] = $orderarea_id;
							}else{
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}

							// orderselectivearea
							for($s=0; $s<count($data8); $s++){
								if($data8[$s]['area_id']==$t){
									$sql = sprintf("INSERT INTO orderselectivearea(orderarea_id,selective_key,selective_name) VALUES(%d,'%s','%s')",
										$orderarea_id,
										$data8[$s]['selective_key'],
										$data8[$s]['selective_name']);
									if(!exe_sql($conn, $sql)){
										mysqli_query($conn, 'ROLLBACK');
										return null;
									}
									break;		// 同時に指定できるプリント位置は1ヶ所のため
								}
							}

							// orderink
							for($s=0; $s<count($data9); $s++){
								if($data9[$s]['area_id']!=$t) continue;
								$sql = sprintf("INSERT INTO orderink(orderarea_id,ink_name,ink_code,ink_position) VALUES(%d,'%s','%s','%s')",
								$orderarea_id, $data9[$s]['ink_name'], $data9[$s]['ink_code'], $data9[$s]['ink_position']);
								if(exe_sql($conn, $sql)){
									$orderink_id = mysqli_insert_id();
									$orderinkid[$s] = $orderink_id;
								}else{
									mysqli_query($conn, 'ROLLBACK');
									return null;
								}
								
								// exchange ink
								/*
								for($a=0; $a<count($data10); $a++){
									if($data10[$a]['ink_id']!=$s) continue;
									$sql = sprintf("INSERT INTO exchink(orderink_id,exchink_name,exchink_code,exchink_volume) VALUES(%d,'%s','%s',%d)",
									$orderink_id, $data10[$a]['exchink_name'], $data10[$a]['exchink_code'], $data10[$a]['exchink_volume']);
									if(exe_sql($conn, $sql)){
										$exchinkid[$a] = mysqli_insert_id();
									}else{
										mysqli_query($conn, 'ROLLBACK');
										return 'error update exchink1';
									}
								}
								*/
							}
							
						}
					}else{
						// 既存データの更新
						$sql = sprintf("update orderprint set subprice=%d where id=%d", $data6[$i]['subprice'], $orderprint_id);
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}

						// orderarea 既存データの確認
						for($t=0; $t<count($data7); $t++){
							if($data7[$t]['print_id']!=$i) continue;
							// 新規のorderarea
							if($data7[$t]['areaid']==0){
								$sql = sprintf("INSERT INTO orderarea(orderprint_id,area_path,area_name,origin,ink_count,print_type,
									areasize_from,areasize_to,areasize_id,print_option,jumbo_plate,design_plate,design_type,design_size,repeat_check,silkmethod)
									VALUES(%d,'%s','%s',%d,%d,'%s',%d,%d,%d,%d,%d,%d,'%s','%s',%d,%d)",
									$orderprint_id,
									'txt/'.$data7[$t]['area_path'].'/'.$data7[$t]['area_name'].'.txt',
									$data7[$t]['area_name'],
									$data7[$t]['origin'],
									$data7[$t]['ink_count'],
									$data7[$t]['print_type'],
									$data7[$t]['areasize_from'],
									$data7[$t]['areasize_to'],
									$data7[$t]['areasize_id'],
									$data7[$t]['print_option'],
									$data7[$t]['jumbo_plate'],
									$data7[$t]['design_plate'],
									$data7[$t]['design_type'],
									$data7[$t]['design_size'],
									$data7[$t]['repeat_check'],
									$data7[$t]['silkmethod']
									);
								if(exe_sql($conn, $sql)){
									$orderarea_id = mysqli_insert_id();
									$orderareaid[$t] = $orderarea_id;
								}else{
									mysqli_query($conn, 'ROLLBACK');
									return null;
								}

								// orderselectivearea
								for($s=0; $s<count($data8); $s++){
									if($data8[$s]['area_id']==$t){
										$sql = sprintf("INSERT INTO orderselectivearea(orderarea_id,selective_key,selective_name) VALUES(%d,'%s','%s')",
											$orderarea_id,
											$data8[$s]['selective_key'],
											$data8[$s]['selective_name']);
										if(!exe_sql($conn, $sql)){
											mysqli_query($conn, 'ROLLBACK');
											return null;
										}
										break;		// 同時に指定できるプリント位置は1ヶ所のため
									}
								}

								// orderink
								for($s=0; $s<count($data9); $s++){
									if($data9[$s]['area_id']!=$t) continue;
									$sql = sprintf("INSERT INTO orderink(orderarea_id,ink_name,ink_code,ink_position) VALUES(%d,'%s','%s','%s')",
									$orderarea_id, $data9[$s]['ink_name'], $data9[$s]['ink_code'], $data9[$s]['ink_position']);
									if(exe_sql($conn, $sql)){
										$orderink_id = mysqli_insert_id();
										$orderinkid[$s] = $orderink_id;
									}else{
										mysqli_query($conn, 'ROLLBACK');
										return null;
									}
									
									// exchange ink
									/*
									for($a=0; $a<count($data10); $a++){
										if($data10[$a]['ink_id']!=$s) continue;
										$sql = sprintf("INSERT INTO exchink(orderink_id,exchink_name,exchink_code,exchink_volume) VALUES(%d,'%s','%s',%d)",
										$orderink_id, $data10[$a]['exchink_name'], $data10[$a]['exchink_code'], $data10[$a]['exchink_volume']);
										if(exe_sql($conn, $sql)){
											$exchinkid[$a] = mysqli_insert_id();
										}else{
											mysqli_query($conn, 'ROLLBACK');
											return null;
										}
									}
									*/
								}
							

							}else{
								// 修正更新
								$orderarea_id = $data7[$t]['areaid'];
								$sql = sprintf("update orderarea set area_path='%s',area_name='%s',origin=%d,ink_count=%d,print_type='%s',
									areasize_from=%d,areasize_to=%d,areasize_id=%d,print_option=%d,jumbo_plate=%d,design_plate=%d,design_type='%s',design_size='%s',repeat_check=%d,silkmethod=%d where areaid=%d",
									'txt/'.$data7[$t]['area_path'].'/'.$data7[$t]['area_name'].'.txt',
									$data7[$t]['area_name'],
									$data7[$t]['origin'],
									$data7[$t]['ink_count'],
									$data7[$t]['print_type'],
									$data7[$t]['areasize_from'],
									$data7[$t]['areasize_to'],
									$data7[$t]['areasize_id'],
									$data7[$t]['print_option'],
									$data7[$t]['jumbo_plate'],
									$data7[$t]['design_plate'],
									$data7[$t]['design_type'],
									$data7[$t]['design_size'],
									$data7[$t]['repeat_check'],
									$data7[$t]['silkmethod'],
									$orderarea_id
									);
								if(!exe_sql($conn, $sql)){
									mysqli_query($conn, 'ROLLBACK');
									return null;
								}
								$orderareaid[$t] = $orderarea_id;

								// orderselectivearea
								for($s=0; $s<count($data8); $s++){
									if($data8[$s]['area_id']==$t){
										// プリント位置データの有無を確認
										if(isset($orderselective[$orderarea_id])){
											$sql = sprintf("update orderselectivearea set selective_key='%s', selective_name='%s' where orderarea_id=%d",
												$data8[$s]['selective_key'],
												$data8[$s]['selective_name'],
												$orderarea_id
												);
											if(!exe_sql($conn, $sql)){
												mysqli_query($conn, 'ROLLBACK');
												return null;
											}
										}else{
											$sql = sprintf("INSERT INTO orderselectivearea(orderarea_id,selective_key,selective_name) VALUES(%d,'%s','%s')",
												$orderarea_id,
												$data8[$s]['selective_key'],
												$data8[$s]['selective_name']);
											if(!exe_sql($conn, $sql)){
												mysqli_query($conn, 'ROLLBACK');
												return null;
											}
										}
										break;		// 同時に指定できるプリント位置は1ヶ所
									}
								}

								// orderink
								for($s=0; $s<count($data9); $s++){
									if($data9[$s]['area_id']==$t){
										if($data9[$s]['inkid']==0){
											// インクの新規登録
											$sql = sprintf("INSERT INTO orderink(orderarea_id,ink_name,ink_code,ink_position) VALUES(%d,'%s','%s','%s')",
												$orderarea_id,
												$data9[$s]['ink_name'],
												$data9[$s]['ink_code'],
												$data9[$s]['ink_position']
												);
											if(exe_sql($conn, $sql)){
												$orderink_id = mysqli_insert_id();
												$orderinkid[$s] = $orderink_id;
											}else{
												mysqli_query($conn, 'ROLLBACK');
												return null;
											}
											
											// exchange ink
											/*
											for($a=0; $a<count($data10); $a++){
												if($data10[$a]['ink_id']!=$s) continue;
												$sql = sprintf("INSERT INTO exchink(orderink_id,exchink_name,exchink_code,exchink_volume) VALUES(%d,'%s','%s',%d)",
												$orderink_id, $data10[$a]['exchink_name'], $data10[$a]['exchink_code'], $data10[$a]['exchink_volume']);
												if(exe_sql($conn, $sql)){
													$exchinkid[$a] = mysqli_insert_id();
												}else{
													mysqli_query($conn, 'ROLLBACK');
													return null;
												}
											}
											*/
										
										}else{
											// 登録済みインクの更新
											$sql = sprintf("update orderink set ink_name='%s', ink_code='%s', ink_position='%s' where inkid=%d",
												$data9[$s]['ink_name'],
												$data9[$s]['ink_code'],
												$data9[$s]['ink_position'],
												$data9[$s]['inkid']
												);
											if(exe_sql($conn, $sql)){
												$orderink_id = $data9[$s]['inkid'];
												$orderinkid[$s] = $orderink_id;
											}else{
												mysqli_query($conn, 'ROLLBACK');
												return null;
											}
											
											// exchange ink
											/*
											for($a=0; $a<count($data10); $a++){
												if($data10[$a]['ink_id']!=$s) continue;
												if($data10[$a]['exchid']==0){
													$sql = sprintf("INSERT INTO exchink(orderink_id,exchink_name,exchink_code,exchink_volume) VALUES(%d,'%s','%s',%d)",
													$orderink_id, $data10[$a]['exchink_name'], $data10[$a]['exchink_code'], $data10[$a]['exchink_volume']);
													if(exe_sql($conn, $sql)){
														$exchinkid[$a] = mysqli_insert_id();
													}else{
														mysqli_query($conn, 'ROLLBACK');
														return null;
													}
												}else{
													$sql = sprintf("update exchink set exchink_name='%s', exchink_code='%s', exchink_volume='%s' where exchid=%d",
														$data10[$a]['exchink_name'],
														$data10[$a]['exchink_code'],
														$data10[$a]['exchink_volume'],
														$data10[$a]['exchid']
														);
													if(exe_sql($conn, $sql)){
														$exchinkid[$a] = $data10[$a]['exchid'];
													}else{
														mysqli_query($conn, 'ROLLBACK');
														return null;
													}
												}
											}
											*/
										}
									}
								}
							}
						}
					}
				}

				$result = $this->update($conn, 'orderitem', array($orders_id, $data3['ordertype'], $data4));
				if(is_null($result)){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}


				// プリント方法ごとの進捗テーブルを更新

				// 更新した受注伝票のプリント方法を取得
				$sql = sprintf("select print_type, noprint from ((orders inner join orderprint on orders.id=orderprint.orders_id)
						 inner join orderarea on orderprint.id=orderarea.orderprint_id)
						 right join orderselectivearea on orderarea.areaid=orderselectivearea.orderarea_id
						 where orders.id=%d
						 group by orders.id, print_type", $orders_id);
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$printkey[] = $res['print_type'];
				}
				if($data3['noprint']==1) {
					$printkey[] = 'noprint';
				}

				// 既存のプリント作業進捗レコードを取得
				$f = $data3['factory'];
				$sql = sprintf("select * from printstatus where orders_id=%d", $orders_id);
				$result = exe_sql($conn, $sql);
				if(mysqli_num_rows($result)>0){
					while($res = mysqli_fetch_assoc($result)){
						$printstatus[$res['printtype_key']] = $res;
					}

					// プリント方法の追加・削除を確認
					for($i=0; $i<count($printkey); $i++){
						if(!isset($printstatus[$printkey[$i]])){
							$add_printstatus[] = $printkey[$i];
						}else{
							unset($printstatus[$printkey[$i]]);
						}
					}
					// 使用されなくなったプリント方法を削除
					if(!empty($printstatus)){
						foreach($printstatus as $key=>$val){
							$parm[] = $val['prnstatusid'];
						}
						$sql = sprintf("delete from printstatus where prnstatusid in(%s)", implode(',', $parm));
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}
					// 新しいプリント方法を追加
					if(!empty($add_printstatus)){
						if($data3['repeater']==0){
							$sql = "insert into printstatus(orders_id,printtype_key,factory_2,factory_3,factory_4,factory_5,factory_6,factory_7) value";
							for($i=0; $i<count($add_printstatus); $i++){
								$sql .= "(".$orders_id.",'".$add_printstatus[$i]."',".$f.",".$f.",".$f.",".$f.",".$f.",".$f."),";
							}
						}else{
							// リピート版の場合
							$sql = "insert into printstatus(orders_id,printtype_key,state_1,state_2,factory_2,factory_3,factory_4,factory_5,factory_6,factory_7) value";
							for($i=0; $i<count($add_printstatus); $i++){
								if($add_printstatus[$i]=='silk' || $add_printstatus[$i]=='digit'){
									$sql .= "(".$orders_id.",'".$add_printstatus[$i]."',28,28,".$f.",".$f.",".$f.",".$f.",".$f.",".$f."),";
								}else{
									$sql .= "(".$orders_id.",'".$add_printstatus[$i]."',43,0,".$f.",".$f.",".$f.",".$f.",".$f.",".$f."),";
								}
							}
						}
						$sql = substr($sql,0,-1);
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
						
						// シルク作業予定レコードを新規追加
						$sql = sprintf("select * from printstatus where orders_id=%d and printtype_key='silk'", $orders_id);
						$result = exe_sql($conn, $sql);
						if(mysqli_num_rows($result)>0){
							$res = mysqli_fetch_assoc($result);
							$sql = "INSERT INTO workplan(orders_id, prnstatus_id, wp_printkey, quota) VALUES";
							$sql .= "(".$orders_id.", ".$res['prnstatusid'].", 'silk', 100)";
							if(!exe_sql($conn, $sql)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
						}
					}
					
				}else if(!empty($printkey)){
					// 新たにプリント作業進捗レコードを作成
					if($data3['repeater']==0){
						$sql = "insert into printstatus(orders_id,printtype_key,factory_2,factory_3,factory_4,factory_5,factory_6,factory_7) value";
						for($i=0; $i<count($printkey); $i++){
							$sql .= "(".$orders_id.",'".$printkey[$i]."',".$f.",".$f.",".$f.",".$f.",".$f.",".$f."),";
						}
					}else{
						// リピート版の場合
						$sql = "insert into printstatus(orders_id,printtype_key,state_1,state_2,factory_2,factory_3,factory_4,factory_5,factory_6,factory_7) value";
						for($i=0; $i<count($printkey); $i++){
							if($printkey[$i]=='silk' || $printkey[$i]=='digit'){
								$sql .= "(".$orders_id.",'".$printkey[$i]."',28,28,".$f.",".$f.",".$f.",".$f.",".$f.",".$f."),";
							}else{
								$sql .= "(".$orders_id.",'".$printkey[$i]."',43,0,".$f.",".$f.",".$f.",".$f.",".$f.",".$f."),";
							}
						}
					}
					$sql = substr($sql,0,-1);
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}
				
				// 工場指定を更新
				$sql = sprintf("update printstatus set factory_2=%d, factory_3=%d, factory_4=%d, factory_5=%d, factory_6=%d, factory_7=%d where orders_id=%d",
					$f,$f,$f,$f,$f,$f,$orders_id);
				if(!exe_sql($conn, $sql)){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}
				/*
				if($data3["phase"]=="copy"){
					$sql = "UPDATE acceptstatus SET progress_id=2 where orders_id=".$orders_id;
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}
				*/
				// 受注が確定している場合は、既存の売掛伝票の書換
				$sql = "select * from cashbook where orders_id=".$orders_id." and netsales>0";
				$result = exe_sql($conn, $sql);
				if(mysqli_num_rows($result)>0){
					$fld = mysqli_fetch_assoc($result);
					$sql= sprintf("UPDATE cashbook SET netsales='%s' WHERE recid='%s'", $data3["estimated"], $fld['recid']);
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}
				
				// 支払方法が現金か代引きの場合は発送可にする
				if($data3["payment"]=='cash' || $data3["payment"]=='cod' || $bill_type==2){
					$readytoship=1;	// 発送可
				}else{
					$readytoship=0;	// 発送不可
				}
				
				// 支払区分が月締めの場合は入金をチェック済みにする
				if($bill_type==2){
					$deposit=2;	// 入金済み
				}else{
					$deposit=1;	// 未入金
				}
				
				$sql = sprintf("update progressstatus set readytoship=%d, deposit=%d, rakuhan=%d where orders_id=%d", 
						$readytoship, $deposit, $data3['rakuhan'], $orders_id);
				if(!exe_sql($conn, $sql)){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}


				$res = $this->update($conn, 'discount', array("orders_id"=>$orders_id, "discount"=>$data3["discount"]));
				if(is_null($result)){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}

				$res = $this->update($conn, 'media', array("orders_id"=>$orders_id, "media"=>array($data3["media"], $data3['media_other'])));
				if(is_null($result)){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}

				if($data3['ordertype']=='general'){
					$sql = sprintf("UPDATE estimatedetails
							SET productfee=%d,printfee=%d,silkprintfee=%d,colorprintfee=%d,digitprintfee=%d,
							inkjetprintfee=%d,cuttingprintfee=%d,exchinkfee=%d,additionalfee=%d,packfee=%d,
							expressfee=%d,discountfee=%d,reductionfee=%d,carriagefee=%d,extracarryfee=%d,
							designfee=%d,codfee=%d,basefee=%d,salestax=%d,creditfee=%d WHERE orders_id=%d",
						   	$data3["productfee"],
						 	$data3["printfee"],
						 	$data3["silkprintfee"],
						 	$data3["colorprintfee"],
						 	$data3["digitprintfee"],
						 	$data3["inkjetprintfee"],
						 	$data3["cuttingprintfee"],
						 	$data3["exchinkfee"],
						 	$data3["additionalfee"],
						 	$data3["packfee"],
						 	$data3["expressfee"],
						 	$data3["discountfee"],
						 	$data3["reductionfee"],
						 	$data3["carriagefee"],
						 	$data3["extracarryfee"],
						 	$data3["designfee"],
						 	$data3["codfee"],
						 	$data3["basefee"],
						 	$data3["salestax"],
						 	$data3["creditfee"],
						 	$orders_id);
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}else{
					if(empty($data5)){
						// 当該受注の業者見積りを全件削除
						$sql = sprintf("delete from additionalestimate where orders_id=%d", $orders_id);
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}else{

						// 既存の業者見積りデータを取得
						$sql = sprintf("select * from additionalestimate where orders_id=%d", $orders_id);
						$result = exe_sql($conn, $sql);
						if(mysqli_num_rows($result)>0){
							while($res = mysqli_fetch_assoc($result)){
								$additional[$res['addestid']] = $res;
							}
						}

						$addestid = array();	// 見積追加行のIDを代入
						for($i=0; $i<count($data5); $i++){
							if($data5[$i]['addestid']==0){
								// 新規登録
								$sql = sprintf("INSERT INTO additionalestimate(addsummary,addamount,addcost,addprice,orders_id) VALUES('%s',%d,%d,%d,%d)",
									quote_smart($conn, $data5[$i]['addsummary']),
									$data5[$i]['addamount'],
									$data5[$i]['addcost'],
									$data5[$i]['addprice'],
									$orders_id
									);
								if(!exe_sql($conn, $sql)){
									mysqli_query($conn, 'ROLLBACK');
									return null;
								}
								$addestid[] = mysqli_insert_id();
							}else{
								// 修正
								$sql = sprintf("update additionalestimate set addsummary='%s', addamount=%d,
									addcost=%d, addprice=%d where addestid=%d and orders_id=%d",
									quote_smart($conn, $data5[$i]['addsummary']),
									$data5[$i]['addamount'],
									$data5[$i]['addcost'],
									$data5[$i]['addprice'],
									$data5[$i]['addestid'],
									$orders_id
								);
								if(!exe_sql($conn, $sql)){
									mysqli_query($conn, 'ROLLBACK');
									return null;
								}
								$addestid[] = $data5[$i]['addestid'];
							}

							// 業者見積りの削除を確認
							if(isset($additional[$data5[$i]['addestid']])){
								unset($additional[$data5[$i]['addestid']]);
							}
						}

						// 使用されなくなった業者見積りを削除
						if(!empty($additional)){
							foreach($additional as $key=>$val){
								$parm[] = $key;
							}
							$sql = sprintf("delete from additionalestimate where addestid in(%s)", implode(',', $parm));
							if(!exe_sql($conn, $sql)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
						}
					}
				}
				
				// SESSIONに受注No.を登録
				$_SESSION['edited'][$orders_id] = time();
				
				$flg = false;
				$area_ids = '|';
				if(!empty($orderareaid)){
					$area_ids .= implode(',', $orderareaid);
				}
				$ink_ids = '|';
				if(!empty($orderinkid)){
					$ink_ids .= implode(',', $orderinkid);
				}
				$exch_ids = '|';
				if(!empty($exchinkid)){
					$exch_ids .= implode(',', $exchinkid);
				}
				$addest_ids = '|';
				if(!empty($addestid)){
					$addest_ids .= implode(',', $addestid);
				}
				$bundle_status = '|'.$bundle_check;
				
				$rs = $orders_id.','.$customer_id.','.$delivery_id.','.$number.$area_ids.$ink_ids.$exch_ids.$addest_ids.$bundle_status;
				break;

			case 'orderitem':
				list($orders_id, $ordertype, $data2) = $data;
				
				if( empty($data2) ){
					$sql = sprintf("SELECT * FROM acceptstatus WHERE orders_id=%d", $orders_id);
					$result = exe_sql($conn, $sql);
					if(!$result){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					$res = mysqli_fetch_assoc($result);
					if($res['progress_id']==4){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					
					$sql = sprintf("DELETE FROM orderitem WHERE orders_id=%d", $orders_id);
					exe_sql($conn, $sql);
					return $orders_id;
				}
				
				$sql = sprintf("SELECT * FROM orderitem LEFT JOIN orderitemext ON orderitem.id=orderitemext.orderitem_id WHERE orderitem.orders_id=%d", $orders_id);
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$db[] = $res;
				}
				
				if($ordertype=='general'){
					$temp = $data2;
					for($i=0; $i<count($db); $i++){
						$isExist=false;
						for($c=0; $c<count($data2); $c++){
							$cart = $data2[$c];
							if(empty($cart['choice'])){
								$temp[$c] = null;
								continue;
							}
							if( preg_match('/^mst/',$cart['master_id']) ){
								$prm = explode('_', $cart['master_id']);
								$itemid = $prm[1]==0? 0: 100000;
								if($db[$i]['item_name']==$prm[2] && $db[$i]['size_name']==$cart['size_name'] && $db[$i]['item_color']==$prm[3] && 
									$db[$i]['stock_number']==$cart['stock_number'] && $db[$i]['maker']==$cart['maker'] && $db[$i]['item_id']==$itemid)
								{
									$isExist=true;
									$temp[$c] = null;
									if($db[$i]['amount']!=$cart['amount'] || $db[$i]['plateis']!=$cart['plateis'] || $db[$i]['price']!=$cart['price'] || $db[$i]['item_cost']!=$cart['item_cost'] || $db[$i]['item_printfee']!=$cart['item_printfee']){
										$sql = sprintf("update orderitem,orderitemext set orderitem.amount=%d, orderitem.plateis=%d, orderitemext.price=%d,item_cost=%d, item_printfee=%d , item_printone=%d 
										 where orderitem.id=orderitemext.orderitem_id and orderitem.id=%d", 
										 $cart['amount'],$cart['plateis'],$cart['price'],$cart['item_cost'],$cart['item_printfee'],$cart['item_printone'],$db[$i]['id']);
										if(!exe_sql($conn, $sql)){
											mysqli_query($conn, 'ROLLBACK');
											return null;
										}
									}
								}
							
							}else{
								if($db[$i]['master_id']==$cart['master_id'] && $db[$i]['size_id']==$cart['size_id']){
									$isExist=true;
									$temp[$c] = null;	
									// orderprintのIDを取得
									$sql = sprintf("select orderprint.id as print_id from
											 (orderprint inner join catalog on orderprint.category_id=catalog.category_id)
											 inner join item on orderprint.printposition_id=item.printposition_id
											 where catalog.item_id=item.id and orders_id=%d and catalog.id=%d",
											$orders_id, $cart['master_id']);
									$result = exe_sql($conn, $sql);
									if(!mysqli_num_rows($result)){
										mysqli_query($conn, 'ROLLBACK');
										return null;
									}
									$res = mysqli_fetch_assoc($result);
									$print_id = $res['print_id'];
									
									if($db[$i]['amount']!=$cart['amount'] || $db[$i]['plateis']!=$cart['plateis'] || $db[$i]['print_id']!=$print_id || $db[$i]['item_cost']!=$cart['item_cost'] || $db[$i]['item_printfee']!=$cart['item_printfee']){
										$sql = sprintf("update orderitem set amount=%d, plateis=%d, print_id=%d,item_cost=%d, item_printfee=%d, item_printone=%d where id=%d", $cart['amount'],$cart['plateis'],$print_id,$cart['item_cost'],$cart['item_printfee'],$cart['item_printone'],$db[$i]['id']);
										if(!exe_sql($conn, $sql)){
											mysqli_query($conn, 'ROLLBACK');
											return null;
										}
									}
								}
							}
						}
						if(!$isExist){
							$sql = sprintf("DELETE FROM orderitem WHERE id=%d", $db[$i]['id']);
							exe_sql($conn, $sql);
						}
					}

					for($c=0; $c<count($temp); $c++){
						if(is_null($temp[$c])) continue;
						if(strpos($temp[$c]['master_id'], 'mst')!==false){
							$info = $temp[$c];
							$prm = explode('_', $temp[$c]['master_id']);
							$info['item_name'] = $prm[2];
							$info['item_color'] = $prm[3];
							$ppID = $prm[1].'_'.$prm[2];
							$itemid = $prm[1]==0? 0: 100000;
							// orderprintのIDを取得
							$sql = sprintf("select orderprint.id as print_id from orderprint where orders_id=%d and 
									 category_id=%d and printposition_id='%s'", $orders_id, $prm[1], $ppID);
							$result = exe_sql($conn, $sql);
							if(!mysqli_num_rows($result)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
							$res = mysqli_fetch_assoc($result);
							
							$sql = sprintf("INSERT INTO orderitem(master_id,size_id,amount,plateis,orders_id,print_id,item_cost,item_printfee,item_printone) VALUES(0,0,%d,%d,%d,%d,%d,%d,%d)",
									$info['amount'], $info['plateis'], $orders_id, $res['print_id'], $info['item_cost'], $info['item_printfee'], $info['item_printone']);
							if(!exe_sql($conn, $sql)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
							$latest = mysqli_insert_id();
							$sql = sprintf("INSERT INTO orderitemext(item_id,item_name,stock_number,maker,size_name,item_color,price,orderitem_id)
							VALUES(%d,'%s','%s','%s','%s','%s','%s',%d)",
									$itemid,
									$info['item_name'],
									$info['stock_number'],
									$info['maker'],
									$info['size_name'],
									$info['item_color'],
									$info['price'],
									$latest);
							if(!exe_sql($conn, $sql)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
						}else{
							// orderprintのIDを取得
							$sql = sprintf("select orderprint.id as print_id from
									 (orderprint inner join catalog on orderprint.category_id=catalog.category_id)
									 inner join item on orderprint.printposition_id=item.printposition_id
									 where catalog.item_id=item.id and orders_id=%d and catalog.id=%d",
									$orders_id, $temp[$c]['master_id']);
							$result = exe_sql($conn, $sql);
							if(!mysqli_num_rows($result)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
							$res = mysqli_fetch_assoc($result);
							
							$sql = sprintf("INSERT INTO orderitem(master_id,size_id,amount,plateis,orders_id,print_id,item_cost,item_printfee,item_printone) VALUES(%d,%d,%d,%d,%d,%d,%d,%d,%d)",
									$temp[$c]['master_id'],
									$temp[$c]['size_id'],
									$temp[$c]['amount'],
									$temp[$c]['plateis'],
									$orders_id,
									$res['print_id'],
									$temp[$c]['item_cost'],
									$temp[$c]['item_printfee'],
									$temp[$c]['item_printone']
									);
							if(!exe_sql($conn, $sql)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
						}
					}
				}else{
					for($i=0; $i<count($data2); $i++){
						foreach($data2[$i] as $key=>$val){
							$data2[$i][$key] = quote_smart($conn, $val);
						}
					}
					$rs = "1";
					$temp = $data2;
					for($i=0; $i<count($db); $i++){
						$isExist=false;
						for($c=0; $c<count($data2); $c++){
							
							if($db[$i]['item_name']==$data2[$c]['item_name'] && $db[$i]['size_name']==$data2[$c]['size_name'] && $db[$i]['item_color']==$data2[$c]['item_color'] && 
								$db[$i]['stock_number']==$data2[$c]['stock_number'] && $db[$i]['maker']==$data2[$c]['maker'] && $db[$i]['item_id']==$data2[$c]['item_id'])
							{
								$isExist=true;
								$temp[$c] = null;
								if($db[$i]['amount']!=$data2[$c]['amount'] || $db[$i]['plateis']!=$data2[$c]['plateis'] || $db[$i]['price']!=$data2[$c]['price']){
									$sql = sprintf("update orderitem,orderitemext set orderitem.amount=%d, orderitem.plateis=%d, orderitemext.price=%d
									 where orderitem.id=orderitemext.orderitem_id and orderitem.id=%d", $data2[$c]['amount'],$data2[$c]['plateis'],$data2[$c]['price'],$db[$i]['id']);
									if(!exe_sql($conn, $sql)){
										mysqli_query($conn, 'ROLLBACK');
										return null;
									}
								}
							}
						}
						if(!$isExist){
							$sql = sprintf("DELETE FROM orderitem WHERE id='%s'", $db[$i]['id']);
							if(!exe_sql($conn, $sql)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
						}
					}
					
					for($c=0; $c<count($temp); $c++){
						if(is_null($temp[$c])) continue;
						$info = $temp[$c];
						
						// orderprintのIDを取得
						if($info['item_id']=='0' || $info['item_id']=='100000'){	// その他または持込
							$categoryid = $info['item_id']==0? 0: 100;
							$sql = sprintf("select orderprint.id as print_id from orderprint where orders_id=%d and 
									 category_id=%d and printposition_id='%s'", $orders_id, $categoryid, $info['position_id']);
						}else if($info['item_id']=='99999'){						// 転写シート
							$sql = sprintf("select orderprint.id as print_id from orderprint where orders_id=%d and category_id=99 limit 1",
								$orders_id);
						}else{
							$sql = sprintf("select orderprint.id as print_id from item inner join orderprint
								 on item.printposition_id=orderprint.printposition_id where orders_id=%d and item.id=%d limit 1",
								$orders_id, $info['item_id']);
						}
						$result = exe_sql($conn, $sql);
						if(!mysqli_num_rows($result)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
						$res = mysqli_fetch_assoc($result);
						
						$sql = sprintf("INSERT INTO orderitem(master_id,size_id,amount,plateis,orders_id,print_id) VALUES(%d,%d,%d,%d,%d,%d)",
								$info['master_id'], $info['size_id'], $info['amount'], $info['plateis'], $orders_id, $res['print_id']);
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
						$latest = mysqli_insert_id();
						$sql = sprintf("INSERT INTO orderitemext(item_id,item_name,stock_number,maker,size_name,item_color,price,orderitem_id)
						VALUES(%d,'%s','%s','%s','%s','%s','%s',%d)",
								$info['item_id'],
								$info['item_name'],
								$info['stock_number'],
								$info['maker'],
								$info['size_name'],
								$info['item_color'],
								$info['price'],
								$latest);
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}
				}
				
				$flg = false;
				$rs = $orders_id;
				break;
				
			case 'discount':
				/**
				*	data	"orders_id"=>n, "discount"=>ディスカウント名+（0or1）,・,・,・
				*/
				foreach($data as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
				}
				$tmp = explode(',', $info['discount']);

				$sql = sprintf("SELECT * FROM discount WHERE orders_id=%d", $info["orders_id"]);
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$db[$res["discount_name"]] = array('id'=>$res["discountid"], "state"=>$res["discount_state"]);
				}

				for($i=0; $i<count($tmp); $i++){
					$sql = '';
					$discount_name = substr($tmp[$i],0,-1);
					$state = substr($tmp[$i],-1);
					if( isset($db[$discount_name]) ){
						$rec = $db[$discount_name];
						if($rec["state"]!=$state){
							$sql = sprintf("update discount set discount_state='%s' where discountid=%d",
							$state, $rec["id"]);
						}
					}else if($state==1){
						$sql = "INSERT INTO discount(discount_name,discount_state,orders_id) VALUES";
						$sql .= "('".$discount_name."',1,".$info["orders_id"].")";
					}
					if(empty($sql)) continue;
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}

				$rs = true;
				$flg = false;
				break;

			case 'media':
				/*
				* data	"orders_id"=>n,
				* 		"media"=>[name値|value値のカンマ区切り, その他のテキスト]
				*/
				$orders_id = quote_smart($conn, $data['orders_id']);
				list($list1, $media_other) = $data['media'];
				$tmp = explode(',', $list1);

				// 既存データを取得
				$sql = sprintf("SELECT * FROM media WHERE orders_id=%d", $orders_id);
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$db[$res["media_type"]] = array('id'=>$res["mediaid"], "value"=>$res["media_value"]);
				}

				// データの更新
				for($i=0; $i<count($tmp); $i++){
					$media = explode('|', $tmp[$i]);
					if(isset($db[$media[0]])){
						$rec = $db[$media[0]];
						$sql = sprintf("update media set media_value='%s' where mediaid=%d", $media[1], $rec["id"]);
					}else if(!empty($media[0])){
						$sql = 'INSERT INTO media(media_type, media_value, orders_id) VALUES';
						$sql .= '("'.$media[0].'","'.$media[1].'",'.$orders_id.')';
					}
					if(empty($sql)) continue;
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}

				// その他の場合
				if(!empty($media_other)){
					if(isset($db['mediacheck03'])){
						$rec = $db['mediacheck03'];
						$sql = sprintf("update media set media_value='%s' where mediaid=%d", quote_smart($conn, $media_other), $rec["id"]);
					}else{
						$sql = 'INSERT INTO media(media_type, media_value, orders_id) VALUES';
						$sql .= '("mediacheck03","'.quote_smart($conn, $media_other).'",'.$orders_id.')';
					}
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}

				$rs = true;
				$flg = false;
				break;

			case 'estimatedetails':
				foreach($data as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
				}
				$sql = sprintf("UPDATE estimatedetails
								SET productfee=%d,printfee=%d,silkprintfee=%d,colorprintfee=%d,digitprintfee=%d,
								inkjetprintfee=%d,cuttingprintfee=%d,exchinkfee=%d,additionalfee=%d,packfee=%d,
								expressfee=%d,discountfee=%d,reductionfee=%d,carriagefee=%d,extracarryfee=%d,
								designfee=%d,codfee=%d,basefee=%d,salestax=%d,creditfee=%d WHERE orders_id=%d",
							   	$info["productfee"],
							 	$info["printfee"],
							 	$info["silkprintfee"],
							 	$info["colorprintfee"],
							 	$info["digitprintfee"],
							 	$info["inkjetprintfee"],
							 	$info["cuttingprintfee"],
							 	$info["exchinkfee"],
							 	$info["additionalfee"],
							 	$info["packfee"],
							 	$info["expressfee"],
							 	$info["discountfee"],
							 	$info["reductionfee"],
							 	$info["carriagefee"],
							 	$info["extracarryfee"],
							 	$info["designfee"],
							 	$info["codfee"],
							 	$info["basefee"],
							 	$info["salestax"],
							 	$info["creditfee"],
							 	$info["orders_id"]);
				$rs = exe_sql($conn, $sql);
				if(!$rs){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}

				// 更新したレコードのPrimaryIDを返す
				$sql = sprintf("SELECT * FROM estimatedetails WHERE orders_id=%d", $info["orders_id"]);
				$result = exe_sql($conn, $sql);
				$res = mysqli_fetch_assoc($result);
				$rs = $res['estid'];

				$flg = false;
				break;

			case 'additionalestimate':
				// 全削除して新規登録
				$this->delete($conn, 'additionalestimate', array('orders_id'=>$data[0]['orders_id']));
				$rs = $this->insert($conn, 'additionalestimate', $data);

				$flg = false;
				break;

			case 'customer':
				/*
				foreach($data as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
				}
				*/
				if(isset($data['cancel'])){		// 受注伝票との関連付けを取り消す 2013-11-02 廃止
					// $sql = sprintf("UPDATE orders SET customer_id='0' WHERE id='%s'", $data["id"]);
				}else{
					$sql = sprintf("UPDATE customer
							   SET customername='%s',customerruby='%s',
							   zipcode='%s',addr0='%s',addr1='%s',addr2='%s',addr3='%s',addr4='%s',tel='%s',fax='%s',email='%s',mobmail='%s',
							   company='%s',companyruby='%s',mobile='%s',job='%s',customernote='%s',
							   bill=%d,remittancecharge=%d,cyclebilling=%d,
							   cutofday=%d,paymentday=%d,consumptiontax=%d WHERE id=%d",
							   $data["customername"],
						 	   $data["customerruby"],
						 	   $data["zipcode"],
						 	   $data["addr0"],
						 	   $data["addr1"],
						 	   $data["addr2"],
						 	   $data["addr3"],
						 	   $data["addr4"],
						 	   $data["tel"],
						 	   $data["fax"],
						 	   $data["email"],
						 	   $data["mobmail"],
						 	   $data["company"],
						 	   $data["companyruby"],
						 	   $data["mobile"],
						 	   $data["job"],
						 	   $data["customernote"],
						 	   $data["bill"],
						 	   $data["remittancecharge"],
						 	   $data["cyclebilling"],
						 	   $data["cutofday"],
						 	   $data["paymentday"],
						 	   2,
						 	   $data["customer_id"]);
				}
				$rs = exe_sql($conn, $sql);
				if(!$rs){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}
				$rs = mysqli_affected_rows();
				
				// 支払区分が月締めの場合は、入金を済みにし発送可にする
				if(isset($data['orders_id'])){
					if($data["bill"]==2){		// 支払区分（1:都度請求　2:月〆請求）
						$readytoship=1;	// 発送可
						$deposit=2;		// 入金済み
					}else{
						$sql2 = "select * from orders inner join customer on customer_id=customer.id where orders.id=".$data['orders_id'];
						$result = exe_sql($sql2);
						if($result===false){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
						$rec = mysqli_fetch_assoc($result);
						$payment = $rec['payment'];
						if($payment=='cash' || $payment=='cod'){
							$readytoship=1;	// 発送可
						}else{
							$readytoship=0;	// 発送不可
						}
						$deposit=1;		// 未入金
					}
					
					$sql = sprintf("update progressstatus set readytoship=%d, deposit=%d where orders_id=%d", $readytoship, $deposit, $data['orders_id']);
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}
				
				$flg = false;
				break;
				
			case 'supplier':
				/*
				foreach($data as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
				}
				*/
				$sql = sprintf("UPDATE supplier
						   SET suppliername='%s',represent='%s',
						   zipcode='%s',addr1='%s',addr2='%s',tel='%s',fax='%s',email='%s',
						   weburl='%s',contactname='%s',contactmobile='%s',contactemail='%s',
						   classify='%s',outsource='%s',articles='%s',suppliernote='%s'
						   WHERE supplyid=%d",
						   $data["suppliername"],
					 	   $data["represent"],
					 	   $data["zipcode"],
					 	   $data["addr1"],
					 	   $data["addr2"],
					 	   $data["tel"],
					 	   $data["fax"],
					 	   $data["email"],
					 	   $data["weburl"],
					 	   $data["contactname"],
					 	   $data["contactmobile"],
					 	   $data["contactemail"],
					 	   $data["classify"],
					 	   $data["outsource"],
					 	   $data["articles"],
					 	   $data["suppliernote"],
					 	   $data["supplyid"]);
				break;
			case 'delivery':
				if(isset($data['delivery_id'])){
					$id = $data['delivery_id'];
				}else{
					$id = $data['id'];
				}
				if(isset($data['modify'])){
					// delivery_idの付け替え
					$sql= sprintf("UPDATE orders SET delivery_id=%d WHERE delivery_id=%d", $data["modify"],$id);
				}else{
					$sql= sprintf("UPDATE delivery
								   SET organization='%s',
								   agent='%s',
								   team='%s',
								   teacher='%s',
								   delizipcode='%s',
								   deliaddr0='%s',
								   deliaddr1='%s',
								   deliaddr2='%s',
								   deliaddr3='%s',
								   deliaddr4='%s',
								   delitel='%s' WHERE id=%d",
								   $data["organization"],
							 	   $data["agent"],
							 	   $data["team"],
							 	   $data["teacher"],
							 	   $data["delizipcode"],
							 	   $data["deliaddr0"],
							 	   $data["deliaddr1"],
							 	   $data["deliaddr2"],
							 	   $data["deliaddr3"],
							 	   $data["deliaddr4"],
							 	   $data["delitel"],
							 	   $id);
				}
				break;
				
			case 'shipfrom':
				$sql= sprintf("UPDATE shipfrom
							   SET shipfromname='%s',
							   shipfromruby='%s',
							   shipzipcode='%s',
							   shipaddr0='%s',
							   shipaddr1='%s',
							   shipaddr2='%s',
							   shipaddr3='%s',
							   shipaddr4='%s',
							   shiptel='%s',
							   shipfax='%s', 
							   shipemail='%s'
							   WHERE shipid=%d",
							   $data["shipfromname"],
						 	   $data["shipfromruby"],
						 	   $data["shipzipcode"],
						 	   $data["shipaddr0"],
						 	   $data["shipaddr1"],
						 	   $data["shipaddr2"],
						 	   $data["shipaddr3"],
						 	   $data["shipaddr4"],
						 	   $data["shiptel"],
						 	   $data["shipfax"],
						 	   $data["shipemail"],
						 	   $data['shipid']);
				break;

			case 'direction':
				/**
				 *	data1	基本タブ
				 *	data4	デザイン画像パス
				 *	data5	プリントデータ（printinfo）
				 *	data6	プリント調整位置（printadj）
				 *		2012-04-18 廃止
				 *		2012-06-01 復活
				 *	data7	商品の備考（orderitem）
				 *	data8	面付け情報（cutpattern）
				 *
				 *	return	product_id
				 */
				list($data1, $data4, $data5, $data6, $data7, $data8) = $data;
				/*
				foreach($data1 as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
				}
				*/
				$directions = $this->search($conn, 'direction', array('orders_id'=>$data1['orders_id'],'printtype_key'=>$data1['printtype']));
				$product_id = $directions[0]['id'];

				$data1['printtype'] = $directions[0]['printtype'];
				$sql = sprintf("update product set 
		                workshop_note='%s', envelope='%s', ship_note='%s', platescount=%d, 
		                platescheck=%d, pastesheet=%d, edge=%d, edgecolor='%s' where id=%d",
						$data1['workshop_note'],
						$data1['envelope'],
						$data1['ship_note'],
						$data1['platescount'],
						$data1['platescheck'],
						$data1['pastesheet'],
						$data1['edge'],
						$data1['edgecolor'],
						$product_id);
				if(!exe_sql($conn, $sql)){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}else{
					$rs = $product_id;
				}
				
				// 封筒、備考を同じ注文で同期
				$sql = sprintf("update product set envelope='%s', ship_note='%s' where orders_id=%d",
						$data1['envelope'],
						$data1['ship_note'],
						$data1['orders_id']);
				if(!exe_sql($conn, $sql)){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}
				
				// デジタル転写の再版確認とprintstatusの更新
				if($data1['printtype']==3){
					$sql = sprintf("select prnstatusid,repeater,printtype_key,state_1,state_2,fin_1,fin_2 from orders right join printstatus on orders.id=orders_id where id=%d and printtype_key='digit'", $data1['orders_id']);
					$result = exe_sql($conn, $sql);
					if($result===false){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					$res = mysqli_fetch_assoc($result);
					
					// リピ版,新版,再販で作業担当の指定を変更
					if($data1['platescheck']==2){		// 再版
						$s1 = 43;
						$s2 = 43;
					}else if($data1['platescheck']==0){	// リピ版
						$s1 = 28;
						$s2 = 28;
					}else{								// 新版
						$s1 = 0;
						$s2 = 0;
					}
					
					/* 終了チェック判定
						- リピ版は終了チェックを入れる
						- リピ版を除き、変更があれば未指定にする
					*/
					if($s1==28){
						$f1 = 1;
						$f2 = 1;
					}else{
						$f1 = $res['state_1']!=$s1? 0: $res['fin_1'];
						$f2 = $res['state_2']!=$s2? 0: $res['fin_2'];
					}
						
					$sql = sprintf("update printstatus set state_1=%d, state_2=%d, fin_1=%d, fin_2=%d where prnstatusid=%d",
						$s1,$s2,$f1,$f2,$res['prnstatusid']);
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}

				// プリントデザインの画像ファイルへのパスを登録
				if(!empty($data4)){
					for($i=0; $i<count($data4); $i++){
						$sql = sprintf("update orderselectivearea set designpath='%s' where selectiveid=%d",
							quote_smart($conn, $data4[$i]['designpath']), $data4[$i]['selectiveid']);
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}
				}


				// プリント位置ごとの情報
				if(!empty($data5)){
					if(isset($data5[0]['reprint'])){	// シルク
						$reprint = array(0,0,0);	// [リピ版,新版,再販]
						for($i=0; $i<count($data5); $i++){
							$sql = sprintf("update printinfo set remark='%s', reprint=%d, platesinfo='%s', meshinfo='%s', attrink='%s', platesnumber=%d where pinfoid=%d",
									quote_smart($conn, $data5[$i]['remark']),
									$data5[$i]['reprint'],
									quote_smart($conn, $data5[$i]['platesinfo']),
									quote_smart($conn, $data5[$i]['meshinfo']),
									quote_smart($conn, $data5[$i]['attrink']),
									quote_smart($conn, $data5[$i]['platesnumber']),
									$data5[$i]['pinfoid']);
							if(!exe_sql($conn, $sql)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
							
							$reprint[$data5[$i]['reprint']] += 1;
						}
						
						// シルクの場合にリピート版注文の再版確認とprintstatusの更新
						$sql = sprintf("select prnstatusid,repeater,printtype_key,state_1,state_2,fin_1,fin_2 from orders right join printstatus on orders.id=orders_id where id=%d and printtype_key='silk'", $data1['orders_id']);
						$result = exe_sql($conn, $sql);
						if($result===false){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
						$res = mysqli_fetch_assoc($result);
						
						/*　リピ版,新版,再販で作業担当の指定を変更
							- リピ版,再販は全てのプリント位置が同じ指定のときのみ有効
							- 新版が1つでもあれば、「未指定」
							- 新版がなく且つリピ版,再販の混合の場合は「再」
						*/
						if(empty($reprint[1])){		// リピ版と再販のどちらか
							if($reprint[0]>0 && $reprint[2]==0){		// 全てリピ版
								$s1 = 28;
								$s2 = 28;
							}else if($reprint[2]>0){					// 再販がある
								$s1 = 43;
								$s2 = 43;
							}else{					// 例外
								$s1 = 0;
								$s2 = 0;
							}
						}else{						// 新版が1つ以上ある
							$s1 = 0;
							$s2 = 0;
						}
						
						/* 終了チェック判定
							- リピ版は終了チェックを入れる
							- リピ版を除き、変更があれば未指定にする
						*/
						if($s1==28){
							$f1 = 1;
						}else{
							$f1 = $res['state_1']!=$s1? 0: $res['fin_1'];
						}
						if($s2==28){
							$f2 = 1;
						}else{
							$f2 = $res['state_2']!=$s2? 0: $res['fin_2'];
						}
						
						$sql = sprintf("update printstatus set state_1=%d, state_2=%d, fin_1=%d, fin_2=%d where prnstatusid=%d",
							$s1,$s2,$f1,$f2,$res['prnstatusid']);
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
						
					}else{
						for($i=0; $i<count($data5); $i++){
							$sql = sprintf("update printinfo set remark='%s' where pinfoid=%d",
									quote_smart($conn, $data5[$i]['remark']),
									$data5[$i]['pinfoid']);
							if(!exe_sql($conn, $sql)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
						}
					}
				}

				// サイズごとのプリントする位置の調整
				if(!empty($data6)){
					for($t=0; $t<count($data6); $t++){
						$sizename = quote_smart($conn, $data6[$t]['sizename']);
						$sql = sprintf("update printadj set sizename='%s',vert='%s',hori='%s' where printinfo_id=%d and sizename='%s'",
								$sizename,
								quote_smart($conn, $data6[$t]['vert']),
								quote_smart($conn, $data6[$t]['hori']),
								quote_smart($conn, $data6[$t]['pinfoid']),
								$sizename);
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}
				}
				
				// 商品の備考
				if(!empty($data7)){
					if(empty($data7[0]['id'])){
						for($t=0; $t<count($data7); $t++){
							$note = quote_smart($conn, $data7[$t]['item_note']);
							$sql = sprintf("update orderitem set item_note='%s' where orders_id=%d and master_id=%d and size_id=%d",
									$note, $data7[$t]['orders_id'], $data7[$t]['master_id'], $data7[$t]['size_id']);
							if(!exe_sql($conn, $sql)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
						}
					}else{
						for($t=0; $t<count($data7); $t++){
							$note = quote_smart($conn, $data7[$t]['item_note']);
							$sql = sprintf("update orderitem set item_note='%s' where id=%d",
									$note, $data7[$t]['id']);
							if(!exe_sql($conn, $sql)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
						}
					}
				}
				
				// 面付け情報（全削除して新規追加）
				if(!empty($data8)){
					$sql = sprintf("delete from cutpattern where product_id=%d", $product_id);
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					$sql = "insert into cutpattern(product_id,shotname,shot,sheets) values";
					for($i=0; $i<count($data8); $i++){
						$sql .= "(".$product_id;
						$sql .= ",'".quote_smart($conn, $data8[$i]['shotname'])."'";
						$sql .= ",".quote_smart($conn, $data8[$i]['shot']);
						$sql .= ",".quote_smart($conn, $data8[$i]['sheets']);
						$sql .= "),";
					}
					$sql = substr($sql, 0, -1);
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					
				}
				

				$flg = false;
				break;

			case 'progressstatus':
				/*
				*	注文の状態チェック
				*/
				$isShipped = 0;
				foreach($data as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
					if($key!='orders_id' && $key!='bundle') $field[]=$key;
					if($key=='shipped') $isShipped = $val;
				}
				
				/*
				*	旧タイプの発注状態を更新
				*	- 発注画面で不足分を発注済みにする
				*	- トムスとキャブ双方なしの注文の発注
				*/
				if($info['toms_response']==1 || $info['cab_response']==1 || (!($info['toms_order'] || $info['cab_order']) && $info['ordering']>0)){
					$sql2 = sprintf('update printstatus set state_0=%d where orders_id=%d', $info['ordering'], $info['orders_id']);
					$result = exe_sql($sql2);
					if($result===false){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					
					if($info['cab_response']==1){
						/* キャブの発注保留分を済みにする 2015-07-14 一時保留
						$http = new HTTP('http://takahamalifeart.com/cab/garbage.php');
						$param = array('orders_id'=>$info['orders_id']);
						$reply = $http->request('POST', $param);
						$reply = unserialize($reply);
						if(!empty($reply)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
						*/
					}
				}
				
				/*
				*	発送チェックで同梱指定ありの場合
				*/
				if($isShipped!=0){
					$sql = "UPDATE progressstatus SET ";
					for($r=0; $r<count($field); $r++){
						$sql .= $field[$r]."='".$info[$field[$r]]."',";
					}
					$sql = substr($sql,0,-1);
					
					$rec = $this->search($conn, 'bundlecount', array('orders_id'=>$info['orders_id']));
					if(count($rec)>0){
						$ids = array();
						for($i=0; $i<count($rec); $i++){
							$ids[] = $rec[$i]['id'];
						}
						$sql .= " where orders_id in(".implode(',', $ids).")";
					}else{
						$sql .= " where orders_id = ".$info['orders_id'];
					}
				}else{
					$sql2 = sprintf("select * from progressstatus where orders_id=%d", $info['orders_id']);
					$result = exe_sql($sql2);
					if(mysqli_num_rows($result)>0){
						$sql = "UPDATE progressstatus SET ";
						for($r=0; $r<count($field); $r++){
							$sql .= $field[$r]."='".$info[$field[$r]]."',";
						}
						$sql = substr($sql,0,-1);
						$sql .= " where orders_id=".$info['orders_id'];
						
					}else{
						$sql = sprintf("INSERT INTO progressstatus(orders_id, %s) VALUES(%d,%d)",
								$field[0], $info['orders_id'], $info[$field[0]]);
					}
				}
				
				if(empty($sql)){
					$flg = false;
					$rs = 1;
				}
				
				break;

			case 'printstatus':
				/*
				*	注文一覧と各作業画面のプリント方法ごとの進捗チェック
				*/
				$date_name = null;
				$isExwmail = false;	// 引取確認メールの有無
				$resExw = null;	// 引取確認メールの送信結果
				foreach($data as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
					if($key=='workday') $date_name=$val;
					if( ($key=='fin_4' || $key=='fin_5' || $key=='fin_6' || $key=='state_7') && $val>0) $isExwmail = true;
					if($key!='orders_id' && $key!='printtype_key') $field[]=$key;
				}
				
				if(!is_null($date_name)){ 	// 作業予定日の更新
					$d = explode('-', $info[$date_name]);
					if( checkdate($d[1], $d[2], $d[0]) || empty($info[$date_name]) ){
						$sql = "update printstatus set ".$date_name."='".$info[$date_name]."' where orders_id=".$info['orders_id']." and printtype_key='".$info['printtype_key']."'";
						$result = exe_sql($conn, $sql);
						if($result===false){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
						$rs = 1;
					}else{
						$rs = 0;
					}
				}else{
					
					$sql = "UPDATE printstatus SET ";
					for($r=0; $r<count($field); $r++){
						$sql .= $field[$r]."='".$info[$field[$r]]."',";
					}
					$sql = substr($sql,0,-1);
					$sql .= " where orders_id=".$info['orders_id'];
					if(isset($info['printtype_key']) && $info['printtype_key']!='image') $sql .= " and printtype_key='".$info['printtype_key']."'";
					$result = exe_sql($conn, $sql);
					if($result===false){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					
					// イメ画完了の担当指定の場合は受付進捗を更新
					if($info['printtype_key']=='image' and isset($info['state_image'])){
						if(empty($info['state_image'])){
						// 担当指定を外す
							$sql = "update acceptstatus set progress_id=5 where orders_id=".$info['orders_id'];
						}else{
						// イメ画完了
							$sql = "update acceptstatus set progress_id=7 where orders_id=".$info['orders_id'];
						}
						$result = exe_sql($conn, $sql);
						if($result===false){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}
					
					// 全てのプリント終了、またはプリントなしで入荷チェックの場合に引取確認メールの送信確認
					if($isExwmail){
						$sql = sprintf('select * from orders where id=%d', $info['orders_id']);
						$result = exe_sql($conn, $sql);
						if($result===false){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
						$isReturn = false;
						while($rec = mysqli_fetch_assoc($result)){
							$d = explode('-', $rec['schedule3']);
							if(checkdate($d[1], $d[2], $d[0])==false){
								$isReturn = true;		// 発送日が未指定
							}else if(mktime(10, 0, 1, $d[1], $d[2], $d[0]) > time() ){
								$isReturn = true;		// 発送日の10:00過ぎの時だけ実行
							}else if($rec['carriage']!='accept'){
								$isReturn = true;		// 工場渡しのみ
							}
						}
						if($isReturn) return 1;	// 処理中止
						
						$sql = sprintf('select * from printstatus where orders_id=%d', $info['orders_id']);
						$result = exe_sql($conn, $sql);
						if($result===false){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
						$isFin = true;
						$isNoprint = null;
						while($rec = mysqli_fetch_assoc($result)){
							switch($rec['printtype_key']){
								case 'silk':	if($rec['fin_5']==0) $isFin = false;
												break;
								case 'inkjet':	if($rec['fin_6']==0) $isFin = false;
												break;
								case 'noprint':	$isNoprint = $rec['state_7'];
												break;
								default:		if($rec['fin_4']==0) $isFin = false;;
												break;
							}
						}
						
						if( $isNoprint>0 || (is_null($isNoprint) && $isFin) ){
							// 引取確認メールの送信状況を確認
							$sql = sprintf('select count(*) as cnt from mailhistory where subject=4 and orders_id=%d', $info['orders_id']);
							$result = exe_sql($conn, $sql);
							if($result===false){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
							// 引取確認メール送信
							$rec = mysqli_fetch_assoc($result);
							//$recs = mysqli_num_rows($result);
							
							/*  2015-07-14 一時保留
							if(empty($rec['cnt'])){
								$http = new HTTP(_API);
								$res = $http->request('POST', array('act'=>'exwmail', 'args'=>$info['orders_id']));
								$resExw = unserialize($res);
							}
							 */
						}
					}
					
					// シルクが終了チェックの場合に作業予定の実績割合を設定
					if($info['printtype_key']=='silk' && $info['fin_5']==1){
						
						// 未実装
						
					}
					
					if(!is_null($resExw)){
						$rs = $resExw;	// 引取確認メールありの場合の結果判定
					}else{
						$rs = 1;
					}
				}
				
				$flg = false;
				break;

			case 'acceptstatus':
				/*
				*	注文状況
				*	progress_id=1：確定解除、イメ画取消
				*	progress_id=4：注文確定
				*	progress_id=5：イメ画製作
				*	progress_id=6：注文取消
				*/
				$progress = false;
				$completionimage = null;
				$cancelorder = false;
				foreach($data as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
					if($key!='orders_id' && $key!='ordertype' && $key!='orderdate') $field[]=$key;
					if($key=='progress_id'){
						switch($val){
							case '1':	$completionimage = 0;
										$cancelorder = true;
										break;
							case '4':	$progress = true;
										break;
							case '5':	$completionimage = 1;
										break;
							case '6':	$cancelorder = true;
										break;
						}
					}
				}
				
				if(!is_null($completionimage)){
					// イメ画確定の状態更新
					$sql2 = sprintf('update orders set completionimage=%d where id=%d', $completionimage, $info['orders_id']);
					$result = exe_sql($sql2);
					if($result===false){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}
				if($cancelorder){
					// 注文取消または注文確定の解除でイメ画製作を未終了
					$sql2 = "update printstatus set state_image=0 where orders_id=".$info['orders_id'];
					$result = exe_sql($sql2);
					if($result===false){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					
					// 同梱指定の確認
					$rec = $this->search($conn, 'bundlecount', array('orders_id'=>$info['orders_id'], 'progress'=>0));
					if(count($rec)==2){
						// 同梱指定の注文が2つの場合は他方のチェックも外す
						for($i=0; $i<2; $i++){
							$sql2 = sprintf("update orders set bundle=0, contact_number='' where id=%d", $rec[$i]['id']);
							if(!exe_sql($sql2)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
						}
					}else if(!empty($rec)){
						// 注文確定の解除で当該注文の同梱チェックを外す
						$sql2 = sprintf("update orders set bundle=0, contact_number='' where id=%d", $info['orders_id']);
						if(!exe_sql($sql2)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
						
						// 同梱注文全ての問い合わせ番号を削除
						$bundle_id = array();
						for($i=0; $i<count($rec); $i++){
							$bundle_id[] = $rec[$i]['id'];
						}
						$sql2 = "update orders set contact_number='' where id in(".implode(",", $bundle_id).")";
						if(!exe_sql($sql2)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}
					
					/* 注文取消しで発注状態を初期化 2014-04-17 廃止
					$sql2 = "update progressstatus set ordering=0, toms_order=0, toms_response=0 where orders_id=".$info['orders_id'];
					$result = exe_sql($sql2);
					if($result===false){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					*/
				}
				if($progress){
					// 注文確定時に現金出納帳を作成
					$sql2 = "select * from cashbook where orders_id=".$info['orders_id']." and netsales>0";
					$result = exe_sql($sql2);
					if($result===false){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					$res_count = mysqli_num_rows($result);
					
					$sql2 = "select * from orders inner join customer on customer_id=customer.id where orders.id=".$info['orders_id'];
					$result = exe_sql($sql2);
					if($result===false){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					$rec = mysqli_fetch_assoc($result);
					$summary = quote_smart($conn, $rec['maintitle']);
					$netsales = $rec['estimated'];
					$payment = $rec['payment'];
					$bill_type = $rec['bill'];	// 支払区分（1:都度請求　2:月〆請求）
					
					$repeater = $rec['repeater'];	// 版元の受注No.
					$reuse = $rec['reuse'];			// リピート版割のタイプ（1:初回割　2:リピート版割）
					
					// 現金出納帳は既存の物があれば上書
					if($res_count==0){
						$sql2 = sprintf("INSERT INTO cashbook(recdate,bankname,summary,classification,netsales,receiptmoney,orders_id) VALUES('%s',%d,'%s','%s',%d,%d,%d)",
							$info['orderdate'],
							0,
							$summary,
							null,
							$netsales,
							0,
							$info['orders_id']);
						$result = exe_sql($sql2);
					}else{
						$sql2 = sprintf("update cashbook set recdate='%s',summary='%s',netsales=%d where orders_id=%d and receiptmoney=0",
							$info['orderdate'],
							$summary,
							$netsales,
							$info['orders_id']);
						$result = exe_sql($sql2);
					}
					if(!$result){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					
					// 「リピ版」で注文を起こしたが、新版で注文確定した場合
					if($repeater>0 && $reuse==0 && $info['ordertype']=='general'){
						// 版元IDを0にする
						$sql2 = sprintf("update orders set repeater=0 where id=%d", $info['orders_id']);
						if(!exe_sql($sql2)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
						
						/* 2014-12-10 仕様変更、版元のreuseへの255の設定を廃止
						 当該注文の版元からのリピ注文が他にない場合は版元のreuse値を0にする
						$sql2 = sprintf('SELECT * FROM orders inner join acceptstatus on orders.id=orders_id WHERE repeater=%d and id!=%d', $repeater,$info['orders_id']);
						$result = exe_sql($sql2);
						if(!$result){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
						if(mysqli_num_rows($result)==0){
							$sql2 = sprintf("update orders set reuse=0 where id=%d", $repeater);
							if(!exe_sql($sql2)){
								mysqli_query($conn, 'ROLLBACK');
								return null;
							}
						}
						*/
					}
					
					// 製作指示書データの登録
					$chk = $this->insert($conn, 'direction', array('orders_id'=>$info['orders_id'], 'ordertype'=>$info['ordertype']));
					
					
					// 支払方法が現金か代引または、支払区分が月締めの場合は、発送可にする
					if($payment=='cash' || $payment=='cod' || $bill_type==2){
						$readytoship=1;
					}else{
						$readytoship=0;
					}
					
					// 支払区分が月締めの場合は入金をチェック済みにする
					if($bill_type==2){
						$deposit=2;	// 入金済み
					}else{
						$deposit=1;	// 未入金
					}
					$sql2 = sprintf("update progressstatus set readytoship=%d, deposit=%d where orders_id=%d", $readytoship, $deposit, $info['orders_id']);
					if(!exe_sql($sql2)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					
				}
				
				$sql = "UPDATE acceptstatus SET ";
				for($r=0; $r<count($field); $r++){
					$sql .= $field[$r]."='".$info[$field[$r]]."',";
				}
				$sql = substr($sql,0,-1);
				$sql .= " where orders_id=".$info['orders_id']." limit 1";
				
				break;
				
			case 'bundle':
				/*
				*	同梱指定
				*/
				$rs = 0;
				
				// 問い合わせ番号を取得
				$ids = array();
				for($i=0; $i<count($data); $i++){
					$ids[] = $data[$i]['id'];
				}
				$sql = "select contact_number from orders where contact_number!='' and id in(".implode(",", $ids).")";
				$result = exe_sql($conn, $sql);
				if($result===false){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}
				$rec = mysqli_fetch_assoc($result);
				$contact_number = $rec['contact_number'];
				
				// 同梱指定及び問い合わせ番号を更新
				for($i=0; $i<count($data); $i++){
					if($data[$i]['bundle']==1){
						$contact = $contact_number;
					}else{
						$contact = "";
					}
					$sql = sprintf("update orders set bundle=%d, contact_number='%s' where id=%d", $data[$i]['bundle'], $contact, $data[$i]['id']);
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					if($data[$i]['bundle']==1) $rs++;
				}
				
				$flg = false;
				break;
				
			case 'requestmail':
				/*
				*	資料請求の一覧で発送状況を更新し、発送日を返す
				*/
				if($data['phase']==1){
					$today="0000-00-00";
				}else{
					$today = date('Y-m-d');
				}
				$sql = sprintf("update requestmail set phase=%d,shippedreqdate='%s' where reqid=%d", 
					$data['phase'], $today, $data['reqid']);
				if(!exe_sql($conn, $sql)){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}
				
				$sql = sprintf('select shippedreqdate from requestmail where reqid=%d', $data['reqid']);
				$result = exe_sql($conn, $sql);
				if(!$result){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}
				
				$res = mysqli_fetch_assoc($result);
				$rs = $res['shippedreqdate'];
				
				$flg = false;
				break;
				
			case 'sendmailcheck':
				/*
				*	製作開始と到着確認のメール送信中止のチェック
				*/
				if($data['fldname']=='canceljobmail'){
					$sql = sprintf('update orders set canceljobmail=%d where id=%d', $data['check'], $data['orders_id']);
				}else if($data['fldname']=='cancelarrivalmail'){
					$sql = sprintf('update orders set cancelarrivalmail=%d where id=%d', $data['check'], $data['orders_id']);
				}else if($data['fldname']=='cancelshipmail'){
					$sql = sprintf('update orders set cancelshipmail=%d where id=%d', $data['check'], $data['orders_id']);
				}else if($data['fldname']=='cancelpendingmail'){
					$sql = sprintf('update orders set cancelpendingmail=%d where id=%d', $data['check'], $data['orders_id']);
				}
				break;
			
			case 'cashbook':
				for($i=0; $i<count($data); $i++){
					foreach($data[$i] as $key=>$val){
						$info[$key]	= quote_smart($conn, $val);
						if($key!='recid') $field[]=$key;
					}

					$sql = "UPDATE cashbook SET ";
					for($r=0; $r<count($field); $r++){
						$tmp .= $field[$r]."='".$info[$field[$r]]."',";
					}
					$sql .= substr($tmp,0,-1);
					$sql .= " where recid=".$info['recid']." and orders_id=".$info['orders_id'];
					$rs = exe_sql($conn, $sql);
					if(!$rs){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}

				// 消込確認
				$sql = sprintf("select sum(netsales) - sum(receiptmoney) as balance from cashbook where orders_id='%s'",$info['orders_id']);
				$result = exe_sql($conn, $sql);
				if(empty($result)){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}

				$rec = mysqli_fetch_assoc($result);
				if($rec['balance']>0){	// 未消込
					$result = $this->update($conn, 'progressstatus', array('orders_id'=>$info['orders_id'], 'deposit'=>1));
				}else{
					$result = $this->update($conn, 'progressstatus', array('orders_id'=>$info['orders_id'], 'deposit'=>2));
				}
				if(empty($result)){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}

				$flg = false;
				break;
				
			case 'workplan':
				/*
				*	シルクの作業予定と実績
				*	@data	[{'orders_id','scheduled','auota','results'}][][]
				*/
				for($i=0; $i<count($data); $i++){
					foreach($data[$i] as $key=>$val){
						$info[$i][$key]	= quote_smart($conn, $val);
					}
					
					$id[$i] = $info[$i]['orders_id'];
					
					// 更新するレコードのprnstatus_id値を取得
					$sql = sprintf("select * from printstatus where orders_id=%d and printtype_key='silk'", $info[$i]['orders_id']);
					$result = exe_sql($conn, $sql);
					if(!$result){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					$res = mysqli_fetch_assoc($result);
					$info[$i]['prnstatus_id'] = $res['prnstatusid'];
				}
				
				/* 作業予定レコードが新規の場合
				for($i=0; $i<count($id); $i++){
					$sql = sprintf("select * from workplan where wp_printkey='silk' and orders_id=%d", $id[$i]);
					$result = exe_sql($conn, $sql);
					if(mysqli_num_rows($result)==0){
						$sql = sprintf("select * from printstatus where orders_id=%d and printtype_key='silk'", $id[$i]);
						$result = exe_sql($conn, $sql);
						$res = mysqli_fetch_assoc($result);
						$sql = "INSERT INTO workplan(orders_id, prnstatus_id, wp_printkey, quota) VALUES";
						$sql .= "(".$id[$i].", ".$res['prnstatusid'].", 'silk', 100)";
						if(!exe_sql($conn, $sql)){
							mysqli_query($conn, 'ROLLBACK');
							return null;
						}
					}
				}
				
				// 更新するレコードのprnstatus_id値を取得
				$sql = "select * from workplan where wp_printkey='silk' and orders_id in (".implode(',', $id).")";
				$result = exe_sql($conn, $sql);
				
				while($rec = mysqli_fetch_assoc($result)){
					$statusid[$rec['orders_id']] = $rec['prnstatus_id'];
				}
				*/
				
				// 更新対象の受注Noのレコードを全削除
				$sql = "delete from workplan where orders_id in (".implode(',', $id).")";
				if(!exe_sql($conn, $sql)){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}
				
				// 作業予定レコードの再追加
				$val = $info[0];
				$sql = "INSERT INTO workplan(orders_id, prnstatus_id, wp_printkey, scheduled, quota, results, worker) VALUES";
				$sql .= "(".$val['orders_id'].", ".$val['prnstatus_id'].", 'silk', ";
				$sql .= "'".$val['scheduled']."', ".$val['quota'].", ".$val['results'].", ".$val['worker'].")";
				for($i=1; $i<count($info); $i++){
					$val = $info[$i];
					$sql .= ",(".$val['orders_id'].", ".$val['prnstatus_id'].", 'silk', '";
					$sql .= $val['scheduled']."', ".$val['quota'].", ".$val['results'].", ".$val['worker'].")";
				}
				
				break;
				
			case 'actualwork':
				/*
				*	シルクの実作業時間
				*/
				$sql = sprintf('update orderprint set actualwork=%d where id=%d', $data['actualwork'], $data['print_id']);
				
				break;
				
			case 'adjworktime':
				/*
				*	シルクの仕事量調整
				*/
				$sql = sprintf('update orderprint set adjworktime=%d where id=%d', $data['adjworktime'], $data['print_id']);
				
				break;
				
			case 'adjprintcount':
				/*
				*	シルクの刷数調整
				*	return 仕事量
				*/
				$sql = sprintf('update orderprint set adjprintcount=%d where id=%d', $data['adjprintcount'], $data['print_id']);
				$result = exe_sql($conn, $sql);
				if(!$result){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}
				
				$sql = sprintf('select orders.id as id, exchink_count, ink_count, inkid, areaid, 
				orderprint.printposition_id as ppid, platesnumber, print_id,
				orderitem.amount as volume, orderitem.id as orderitemid, adjworktime, adjprintcount, 
				catalog.item_id as itemid, orderprint.category_id as categoryid, 
				coalesce(category.category_name,orderitemext.item_name) as item 
				 from ((((((((((((orders 
				 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
				 inner join printstatus on orders.id=printstatus.orders_id) 
				 inner join progressstatus on orders.id=progressstatus.orders_id) 
				 inner join orderprint on orders.id=orderprint.orders_id) 
				 inner join orderarea on orderprint.id=orderprint_id) 
				 inner join orderselectivearea on areaid=orderarea_id) 
				 inner join orderink on areaid=orderink.orderarea_id) 
				 inner join product on orders.id=product.orders_id) 
				 inner join printinfo on product.id=product_id) 
				 inner join orderitem on orderprint.id=print_id) 
				 left join orderitemext on orderitem.id=orderitem_id) 
				 left join category on orderprint.category_id=category.id) 
				 left join catalog on orderitem.master_id=catalog.id 
				 where created>"2011-06-05" and progress_id=4 and noprint=0 and printinfo.print_posname=selective_name 
				 and orderarea.print_type="silk" and orderarea.ink_count>0 and selectiveid is not null
				 and fin_5=0 and shipped=1
				 and orders.id=%d and print_id=%d 
				 group by orderitem.id, print_id, areaid, inkid order by schedule3, orders.id, item, areaid, orderitem.id', 
				 $data['orders_id'], $data['print_id']);
									
				$i = -1;
				$result = exe_sql($conn, $sql);
				if(!$result){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}
				
				while($rec = mysqli_fetch_assoc($result)){
					if($curarea==$rec['areaid']){
						if($curitemid!=$rec['orderitemid']){
							$rs[$i]['volume'] += $rec['volume'];
						}
					}else{
						$curarea = $rec['areaid'];
						$platesnumber += $rec['platesnumber'];
						$i++;
						$rs[$i] = $rec;
					}
					$curitemid = $rec['orderitemid'];
				}

				/* 作業時間（分）
				*
				*　倍率
				*	Tシャツ:		1
				*	スウェット:		1.3
				*	ポロシャツ:		1
				*	スポーツウェア:	1.3
				*	レディース:		1（廃止カテゴリー）
				*	アウター:		1.5
				*	キャップ:		1.5
				*	タオル:			1
				*	バッグ:			1
				*	エプロン:		1
				*	ワークウェア	1.3
				*	グッズ			1.3
				*	ロングT			1
				*	ベビー			1
				*
				*	パンツ:			1.5
				*	厚手ブルゾン:	2
				*/
				$ratio = array(1, 1, 1.3, 1, 1.3, 1, 1.5, 1.5, 1, 1, 1, 1.3, 1.3, 1, 1);
				$setting = array(0,5,15,20,25);		// 組付け
				$wt = 0;
				for($i=0; $i<count($rs); $i++){
					if(empty($rs[$i]['ink_count'])) break;
					$set = $setting[$rs[$i]['ink_count']];
					$printcount = $i==0? $rs[$i]['platesnumber']+$rs[$i]['adjprintcount']: $rs[$i]['platesnumber'];
					$suri = $printcount>0? (0.5 + ($printcount*0.5)) * $rs[$i]['volume']: 0;
					$age = 0.3 * $rs[$i]['volume'];
					$tume = 0.2 * $rs[$i]['volume'];
					$irokae = 10 * $rs[$i]['exchink_count'];
					if($rs[$i]['ppid']==8 || $rs[$i]['ppid']==9 || $rs[$i]['ppid']==17){
						$rat = 1.5;	// パンツ
					}else if($rs[$i]['itemid']==159){
						$rat = 2;	// アクティブベンチコート
					}else{
						$rat = $ratio[$rs[$i]['categoryid']];
					}
					$wt += ($set+$suri+$age+$tume+$irokae) * $rat;
				}
				
				$rs = round($wt);
				
				$flg = false;
				break;
				
			case 'customerlog':
				foreach($data as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
					if($key!='cstlogid') $field[]=$key;
				}
				$sql = "update customerlog set ";
				for($i=0; $i<count($field); $i++){
					$sql .= $field[$i]."='".$info[$field[$i]]."',";
				}
				$sql = substr($sql,0,-1);
				$sql .= " where cstlogid=".$info['cstlogid'];
				
				break;
				
			case 'userreview':
			/*
			*	ユーザーレビュー（428HP）
			*/
				foreach($data as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
					if($key!='urid') $field[]=$key;
				}
				$sql = "update userreview set ";
				for($i=0; $i<count($field); $i++){
					$sql .= $field[$i]."='".$info[$field[$i]]."',";
				}
				$sql = substr($sql,0,-1);
				$sql .= " where urid=".$info['urid'];
				
				break;
				
			case 'itemreview':
			/*
			*	アイテムレビュー（428HP）
			*/
				foreach($data as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
					if($key!='irid') $field[]=$key;
				}
				$sql = "update itemreview set ";
				for($i=0; $i<count($field); $i++){
					$sql .= $field[$i]."='".$info[$field[$i]]."',";
				}
				$sql = substr($sql,0,-1);
				$sql .= " where irid=".$info['irid'];
				
				break;
			}
			
			if($flg){
				$rs = exe_sql($conn, $sql);
				if(!$rs){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}
			}
			
		}catch(Exception $e){
			mysqli_query($conn, 'ROLLBACK');
			$rs = null;
		}
		
		return $rs;
	}



	/***************************************************************************************************************
	*	レコードの削除
	*	@table		テーブル名
	*	@data		削除データの配列
	*
	*	return		成功したら削除したレコード数
	*/
	private function delete($conn, $table, $data){
		try{
			switch($table){
			case 'direction':
				$sql = sprintf("delete from product where id=%d", $data['id']);
				break;

			case 'exchink':
				$sql = sprintf("DELETE FROM exchink WHERE exchid=%d", $data['exchid']);
				break;

			case 'printinfo':
				$sql = "delete from printinfo where pinfoid=".quote_smart($conn, $data[0]['pinfoid']);
				for($i=1; $i<count($data); $i++){
					$sql .= " or pinfoid=".quote_smart($conn, $data[$i]['pinfoid']);
				}
				break;

			case 'printadj':
				$sql = "delete from printadj where padjid=".quote_smart($conn, $data[0]['padjid']);
				for($i=1; $i<count($data); $i++){
					$sql .= " or padjid=".quote_smart($conn, $data[$i]['padjid']);
				}
				break;

			case 'orderprint':
				$sql = "delete from orderprint where id=".quote_smart($conn, $data[0]['id']);
				for($i=1; $i<count($data); $i++){
					$sql .= " or id=".quote_smart($conn, $data[$i]['id']);
				}
				break;

			case 'orderarea':
				$sql = "delete from orderarea where areaid=".quote_smart($conn, $data[0]['areaid']);
				for($i=1; $i<count($data); $i++){
					$sql .= " or areaid=".quote_smart($conn, $data[$i]['areaid']);
				}
				break;

			case 'orderselectivearea':
				$sql = "delete from orderselectivearea where selectiveid=".quote_smart($conn, $data[0]['selectiveid']);
				for($i=1; $i<count($data); $i++){
					$sql .= " or selectiveid=".quote_smart($conn, $data[$i]['selectiveid']);
				}
				break;

			case 'orderink':
				$sql = "delete from orderink where inkid=".quote_smart($conn, $data[0]['inkid']);
				for($i=1; $i<count($data); $i++){
					$sql .= " or inkid=".quote_smart($conn, $data[$i]['inkid']);
				}
				break;

			case 'customerlog':
				$sql = sprintf("delete from customerlog where cstlogid=%d", $data['cstlogid']);
				break;

			case 'additionalestimate':
				$sql = sprintf("delete from additionalestimate where orders_id=%d", $data['orders_id']);
				break;

			case 'cashbook':
				for($i=0; $i<count($data); $i++){
					$sql = sprintf("delete from cashbook where recid=%d and orders_id=%d",
						$data[$i]['recid'], $data[$i]['orders_id']);
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}

				// 消込確認
				$sql = sprintf("select sum(netsales) - sum(receiptmoney) as balance from cashbook where orders_id=%d",$data[0]['orders_id']);
				$result = exe_sql($conn, $sql);
				if(!$result){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}

				$rec = mysqli_fetch_assoc($result);
				if($rec['balance']>0){	// 未消込
					$sql = sprintf("update progressstatus set deposit=1 where orders_id=%d",$data[0]['orders_id']);
				}else{
					$sql = sprintf("update progressstatus set deposit=2 where orders_id=%d",$data[0]['orders_id']);
				}

				break;
				
			case 'userreview':
				$sql = sprintf("delete from userreview where urid=%d", $data['urid']);
				break;
				
			case 'itemreview':
				$sql = sprintf("delete from itemreview where irid=%d", $data['irid']);
				break;
				
			case 'customer':
				$sql = sprintf("select orders.id as orders_id, delivery_id from customer inner join orders on customer.id=customer_id where customer_id=%d", $data['customer_id']);
				$result = exe_sql($conn, $sql);
				if(!$result){
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}
				$tmp_delivery = array();
				$tmp_orders = array();
				while($res = mysqli_fetch_assoc($result)){
					if(!empty($res['orders_id'])) $tmp_orders[] = $res['orders_id'];
					if(!empty($res['delivery_id'])) $tmp_delivery[] = $res['delivery_id'];
				}
				
				// お届け先
				if(!empty($tmp_delivery)){
					$sql = "delete from delivery where id in (".implode(',', $tmp_delivery).")";
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}
				
				if(!empty($tmp_orders)){
					// 製作指示書
					$sql = "delete from product where orders_id in (".implode(',', $tmp_orders).")";
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					
					// 注文商品
					$sql = "delete from orderitem where orders_id in (".implode(',', $tmp_orders).")";
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					
					// プリント位置
					$sql = "delete from orderprint where orders_id in (".implode(',', $tmp_orders).")";
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					
					// 受注ログ
					$sql = "delete from customerlog where orders_id in (".implode(',', $tmp_orders).")";
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
					
					// 受注伝票
					$sql = "delete from orders where id in (".implode(',', $tmp_orders).")";
					if(!exe_sql($conn, $sql)){
						mysqli_query($conn, 'ROLLBACK');
						return null;
					}
				}
				
				// 顧客情報
				$sql = sprintf("delete from customer where id=%d", $data['customer_id']);
				
				break;
			}

			$rs = exe_sql($conn, $sql);
			if(!$rs){
				mysqli_query($conn, 'ROLLBACK');
				return null;
			}

		}catch(Exception $e){
			mysqli_query($conn, 'ROLLBACK');
			$rs = null;
		}

		return $rs;
	}



	/***************************************************************************************************************
	*	レコードの検索
	*	@table		テーブル名
	*	@data		検索キーの配列
	*
	*	return		健作結果の配列
	*/
	private function search($conn, $table, $data){
		/*
		*	転写紙とプレスの作業時間　（秒数）
		*	(press * shot) + (pack * amount) + prepare + finish + {sheetfor + cleaner}
		*/
		$time_unit = array(
			'press'=>array(		// プレス時間 (index is the Category ID)
				array('digit'=>60, 'ts'=>180),	// others
				array('digit'=>60, 'ts'=>180),	// T-shirts
				array('digit'=>120, 'ts'=>240),	// sweat
				array('digit'=>90, 'ts'=>210),	// polo-shirts
				array('digit'=>60, 'ts'=>180),	// sportswear
				array('digit'=>1, 'ts'=>1),		// dummy(ladys)
				array('digit'=>150, 'ts'=>300), // outer
				array('digit'=>120, 'ts'=>240),	// cap
				array('digit'=>60, 'ts'=>180),	// towel
				array('digit'=>60, 'ts'=>180),	// tote-bag
				array('digit'=>90, 'ts'=>210),	// apron
				array('digit'=>60, 'ts'=>180),	// long-shirts
				array('digit'=>150, 'ts'=>180),	// workwear
				array('digit'=>60, 'ts'=>180),	// baby
				array('digit'=>240, 'ts'=>270)	// goods
			),
			'pack'=>array(		// 袋詰ありのとき
				40,	// others
				40,	// T-shirts
				60,	// sweat
				50,	// polo-shirts
				40,	// sportswear
				1,	// dummy(ladys)
				60, 	// outer
				30,	// cap
				50,	// towel
				50,	// tote-bag
				50,	// apron
				40, // long-shirts
				45, // workwear
				40, // baby
				90  // goods
			),
			'prepare'=>600,		// 準備
			'finish'=>600,		// 片付け
			'sheetfor'=>90,		// シート作成（デジタルのみ）
			'cleaner'=>15		// 掃除機がチェックのとき（デジタルのみ）
		);
		
		try{
			if(isset($data) && !is_array($data)){
				foreach($data as $key=>$val){
					$data[$key] = quote_smart($conn, $val);
				}
			}
			$rs = array();
			$flg = true;
			switch($table){
			case 'graph':
				/*
				*	作業量のグラフ生成（2012-09-12 廃止）
				*	シルクで受注が完了していて且つ「印刷プレス」未終了のデータが対象
				*	今日から30日先までを抽出
				*	仕事量（秒）
				*
				*	作業量の計算方法
				*		jumbo版は通常インクの2倍の作業量（sum(ink+ink*jumbo)）
				*		volume = 180+((120+600)*sum(ink))+(36.5*amount)+(30*amount*sum(ink+ink*jumbo))+(600*exchink)
				*/
				$sql = 'SELECT orders.id as id, schedule3, sum(ink_count) as ink, order_amount, jumbo_plate, exchink_count,
				order_amount*36.5+720*sum(ink_count)+30*order_amount*sum(ink_count+ink_count*jumbo_plate)+600*exchink_count+180 as capacity FROM
				 (((((orders INNER JOIN progressstatus ON orders.id=progressstatus.orders_id)
				 INNER JOIN printstatus ON orders.id=printstatus.orders_id)
				 INNER JOIN acceptstatus ON orders.id=acceptstatus.orders_id)
				 INNER JOIN orderprint ON orders.id=orderprint.orders_id)
				 INNER JOIN category ON orderprint.category_id=category.id)
				 INNER JOIN orderarea on orderprint.id=orderarea.orderprint_id
				 WHERE progressstatus.shipped != 2 and progress_id=4 and orderarea.ink_count>0
				 and fin_5=0
				 and schedule3 between "'.$data['shipping'].'" and adddate("'.$data['shipping'].'", interval 30 day)
				 group by orders.id order by schedule3';
				
				break;
				
			case 'enquete1':
				/*
				*	アンケート集計
				*/
				$sql = 'select enq1id,enq1date,customer_number,enq1name,ans2,ans8,ans9,ans10,ans11,ans12 from enquete1 order by enq1id desc';
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$rs1[] = $res;
				}
				
				// 択一選択（radio, checkbox）の結果集計
				$sql = 'select count(*) as cnt,
				 count(case when ans1=5 then 1 else null end) as ans1_5,
				 count(case when ans1=4 then 1 else null end) as ans1_4,
				 count(case when ans1=3 then 1 else null end) as ans1_3,
				 count(case when ans1=2 then 1 else null end) as ans1_2,
				 count(case when ans1=1 then 1 else null end) as ans1_1,
				 count(case when ans1=0 then 1 else null end) as ans1_0,
				 count(case when ans3=0 then 1 else null end) as ans3_0,
				 count(case when ans3=1 then 1 else null end) as ans3_1,
				 count(case when ans5=5 then 1 else null end) as ans5_5,
				 count(case when ans5=4 then 1 else null end) as ans5_4,
				 count(case when ans5=3 then 1 else null end) as ans5_3,
				 count(case when ans5=2 then 1 else null end) as ans5_2,
				 count(case when ans5=1 then 1 else null end) as ans5_1,
				 count(case when ans5=0 then 1 else null end) as ans5_0,
				 count(case when ans6=5 then 1 else null end) as ans6_5,
				 count(case when ans6=4 then 1 else null end) as ans6_4,
				 count(case when ans6=3 then 1 else null end) as ans6_3,
				 count(case when ans6=2 then 1 else null end) as ans6_2,
				 count(case when ans6=1 then 1 else null end) as ans6_1,
				 count(case when ans6=0 then 1 else null end) as ans6_0,
				 count(case when ans7=5 then 1 else null end) as ans7_5,
				 count(case when ans7=4 then 1 else null end) as ans7_4,
				 count(case when ans7=3 then 1 else null end) as ans7_3,
				 count(case when ans7=2 then 1 else null end) as ans7_2,
				 count(case when ans7=1 then 1 else null end) as ans7_1,
				 count(case when ans7=0 then 1 else null end) as ans7_0
				 from enquete1';
				
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$rs2[] = $res;
				}
				
				$rs = array($rs1, $rs2);
				$flg = false;
				break;
			case 'analysis':
				/*
				* 	受注分析グラフ　月毎に集計
				* 	受注件数
				* 	問合せ件数
				* 	問合せに対する成約率
				*/
				switch($data['assort']){
				case '0':	// 受注件数
					$sql = 'select DATE_FORMAT(schedule3, "%Y-%m") as month,count(*) as capacity from orders
					 left join acceptstatus on orders.id=acceptstatus.orders_id
					 where schedule3 between "'.$data['datefrom'].'" and "'.$data['dateto'].'"
					 and progress_id=4 group by DATE_FORMAT(schedule3, "%Y%m")';
					break;
				case '1':	// 新規問い合わせ件数
					// 受注未登録の新規問い合わせ件数
					$sql = 'select DATE_FORMAT(firstcontactdate, "%Y-%m") as month,count(*) as capacity from contactchecker
					 where firstcontactdate between "'.$data['datefrom'].'" and "'.$data['dateto'].'" and orders_id=0
					 group by DATE_FORMAT(firstcontactdate, "%Y%m")';
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$rs1[$res['month']] = $res;
					}
					// 受注件数
					$sql = 'select DATE_FORMAT(created, "%Y-%m") as month,count(*) as capacity from orders
					 where created between "'.$data['datefrom'].'" and "'.$data['dateto'].'"
					 group by DATE_FORMAT(created, "%Y%m")';
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$res['capacity'] += $rs1[$res['month']]['capacity'];
						$rs[] = $res;
					}
					$flg = false;
					break;
				case '2':	// 問い合わせに対する成約率
					// 新規問い合わせ件数を取得
					$sql = 'select DATE_FORMAT(firstcontactdate, "%Y-%m") as month,count(*) as contact from contactchecker
					 where firstcontactdate between "'.$data['datefrom'].'" and "'.$data['dateto'].'" and orders_id=0
					 group by DATE_FORMAT(firstcontactdate, "%Y%m")';
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$rs1[$res['month']] = $res;
					}

					$sql = 'select DATE_FORMAT(created, "%Y-%m") as month,count(*) as contact from orders
					 where created between "'.$data['datefrom'].'" and "'.$data['dateto'].'"
					 group by DATE_FORMAT(created, "%Y%m")';
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$rs2[$res['month']]['contact'] = $res['contact'] + $rs1[$res['month']]['contact'];
					}
					// 成約件数を問い合わせが有った月で集計
					$sql = 'select DATE_FORMAT(created, "%Y-%m") as month,count(*) as contract from orders
					 left join acceptstatus on orders.id=acceptstatus.orders_id
					 where created between "'.$data['datefrom'].'" and "'.$data['dateto'].'"
					 and progress_id=4 group by DATE_FORMAT(created, "%Y%m")';
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$res['contact'] = $rs2[$res['month']]['contact'];
						$res['capacity'] = round($res['contract']*100/$res['contact'],2);
						$rs[] = $res;
					}
					$flg = false;
					break;
				}

				break;
			case 'top':
				/****************************
				*	受注書のデータ（受注入力画面、製作指示書の印刷、他の画面から直接呼出し）
				*****************************/
				$sql = 'SELECT *, orders.job as job FROM ((((((((((orders LEFT JOIN customer ON orders.customer_id=customer.id)
						 left join shipfrom on orders.shipfrom_id=shipid)
						 LEFT JOIN billtype ON customer.bill=billtype.billid)
						 LEFT JOIN salestype ON customer.sales=salestype.salesid)
						 LEFT JOIN receipttype ON customer.receipt=receipttype.receiptid)
						 LEFT JOIN delivery ON orders.delivery_id=delivery.id)
						 LEFT JOIN progressstatus ON orders.id=progressstatus.orders_id)
						 LEFT JOIN staff ON orders.reception=staff.id)
						 LEFT JOIN estimatedetails on orders.id=estimatedetails.orders_id)
						 LEFT JOIN printstatus ON orders.id=printstatus.orders_id)
						 LEFT JOIN acceptstatus ON orders.id=acceptstatus.orders_id';
				$flg = false;
				if(isset($data['id']) && $data['id']!=""){
					$sql .= ' WHERE orders.id = '.$data['id'];
					$flg = true;
				}
				if(!empty($data['created'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' created = "'.$data['created'].'"';
					$flg = true;
				}
				if(!empty($data['lastmodified'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' lastmodified = "'.$data['lastmodified'].'"';
					$flg = true;
				}
				if(!empty($data['ordertype'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' ordertype = "'.$data['ordertype'].'"';
					$flg = true;
				}
				if(!empty($data['maintitle'])){
					$sql .= $flg? ' and': ' WHERE';
					
					$zenkaku = mb_convert_encoding(mb_convert_kana($data['maintitle'],"AKV"),'utf-8','euc-jp');
					$hankaku = mb_convert_encoding(mb_convert_kana($data['maintitle'],"ak"),'utf-8','euc-jp');
					
					$sql .= ' (maintitle LIKE "%'.$data['maintitle'].'%"';
					$sql .= ' or maintitle LIKE "%'.$zenkaku.'%"';
					$sql .= ' or maintitle LIKE "%'.$hankaku.'%")';
					
					$flg = true;
				}
				if(!empty($data['customer_id'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' customer_id = '.$data['customer_id'];
					$flg = true;
				}
				if(!empty($data['customername'])){
					$sql .= $flg? ' and': ' WHERE';
					
					$zenkaku_space = mb_convert_kana($data['customername'],"S", 'utf-8');
					$hankaku_space = mb_convert_kana($data['customername'],"s", 'utf-8');
					
					$sql .= ' (customername LIKE "%'.$data['customername'].'%"';
					$sql .= ' or customername LIKE "%'.$zenkaku_space.'%"';
					$sql .= ' or customername LIKE "%'.$hankaku_space.'%")';
					
					$flg = true;
				}
				if(!empty($data['customerruby'])){
					$ruby = mb_convert_encoding($data['customerruby'], 'euc-jp', 'utf-8');
					$ruby_hira = mb_convert_encoding(mb_convert_kana($ruby,"HVc"),'utf-8','euc-jp');
					$ruby_zenkata = mb_convert_encoding(mb_convert_kana($ruby,"KVC"),'utf-8','euc-jp');
					$ruby_hankata = mb_convert_encoding(mb_convert_kana($ruby,"kh"),'utf-8','euc-jp');

					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' (customerruby LIKE "%'.$ruby_hira.'%" OR';
					$sql .= ' customerruby LIKE "%'.$ruby_zenkata.'%" OR';
					$sql .= ' customerruby LIKE "%'.$ruby_hankata.'%")';
					$flg = true;
				}
				if(isset($data['alive'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' progress_id < 6';
					$flg = true;
				}
				$sql .= ' GROUP BY orders.id';
				if(!empty($data['sort'])){
					$sql .= ' ORDER BY orders.id DESC';
				}else{
					$sql .= ' ORDER BY orders.id ASC';
				}
				
				break;

			case 'accepting':
				/****************************
				*	注文の受付一覧
				*	orderplates を除外　platesid is null
				*****************************/
				$sql = 'select orders.id as ordersid, applyto, maintitle, created, schedule3, customer_id, factory, ordertype, bundle, 
						 number, cstprefix, company, customername, shipped, rakuhan, order_amount,completionimage, repeater, reuse, repeatdesign, 
						 progress_id, progressname, staffname, coalesce(coalesce(category.category_name,orderitemext.item_name),"") as category_name, 
						 canceljobmail, cancelarrivalmail, cancelshipmail, cancelpendingmail, 
						 coalesce(category_id,"") as category_id, print_type, repeat_check,
						 (case when coalesce(expressfee,0)=0 then 0 else round(expressfee/(productfee+printfee+exchinkfee+packfee+discountfee+designfee),1)+1 end) as express
						 from ((((((((((((orders
						 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
						 inner join acceptprog on progress_id=aproid) 
						 inner join progressstatus on orders.id=progressstatus.orders_id) 
						 left join estimatedetails on orders.id=estimatedetails.orders_id)
						 left join staff on reception=staff.id) 
						 left join customer on customer_id=customer.id) 
						 left join orderitem on orders.id=orderitem.orders_id) 
						 left join orderitemext on orderitem.id=orderitem_id) 
						 left join orderplates on orders.id=orderplates.orders_id) 
						 left join orderprint on orders.id=orderprint.orders_id) 
						 left join category on orderprint.category_id=category.id) 
						 left join orderarea on orderprint.id=orderprint_id) 
						 left join orderselectivearea on areaid=orderarea_id
						 where created>"2011-06-05" and platesid is null';
				if(!empty($data['progress_id'])){
					$sql .= ' and progress_id = '.$data['progress_id'];
				}else if(isset($data['alive'])){
					$sql .= ' and progress_id < 6';
				}else{
					$sql .= ' and progress_id != 6';
				}
				if(isset($data['id']) && $data['id']!=""){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if($data['number']!=""){
					$sql .= ' and cstprefix = "'.substr($data['number'],0,1).'"';
					$num = substr($data['number'],1);
					if(!empty($num)) $sql .= ' and number = '.$num;
				}
				if(!empty($data['lm_from'])){
					$sql .= ' and lastmodified >= "'.$data['lm_from'].'"';
				}
				if(!empty($data['lm_to'])){
					$sql .= ' and lastmodified <= "'.$data['lm_to'].'"';
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				if(!empty($data['staff_id'])){
					$sql .= ' and orders.reception = '.$data['staff_id'];
				}
				if(!empty($data['maintitle'])){
					$zenkaku = mb_convert_encoding(mb_convert_kana($data['maintitle'],"AKV"),'utf-8','euc-jp');
					$hankaku = mb_convert_encoding(mb_convert_kana($data['maintitle'],"ak"),'utf-8','euc-jp');
					
					$sql .= ' and (maintitle LIKE "%'.$data['maintitle'].'%"';
					$sql .= ' or maintitle LIKE "%'.$zenkaku.'%"';
					$sql .= ' or maintitle LIKE "%'.$hankaku.'%")';
				}
				if(!empty($data['company'])){
					$sql .= ' and company LIKE "%'.$data['company'].'%"';
				}
				if(!empty($data['companyruby'])){
					$ruby = mb_convert_encoding($data['companyruby'], 'euc-jp', 'utf-8');
					$ruby_hira = mb_convert_encoding(mb_convert_kana($ruby,"HVc"),'utf-8','euc-jp');
					$ruby_zenkata = mb_convert_encoding(mb_convert_kana($ruby,"KVC"),'utf-8','euc-jp');
					$ruby_hankata = mb_convert_encoding(mb_convert_kana($ruby,"kh"),'utf-8','euc-jp');

					$sql .= ' and (companyruby LIKE "%'.$ruby_hira.'%" OR';
					$sql .= ' companyruby LIKE "%'.$ruby_zenkata.'%" OR';
					$sql .= ' companyruby LIKE "%'.$ruby_hankata.'%")';
				}
				if(!empty($data['customername'])){
					$zenkaku_space = mb_convert_kana($data['customername'],"S", 'utf-8');
					$hankaku_space = mb_convert_kana($data['customername'],"s", 'utf-8');
					
					$sql .= ' and (customername LIKE "%'.$data['customername'].'%"';
					$sql .= ' or customername LIKE "%'.$zenkaku_space.'%"';
					$sql .= ' or customername LIKE "%'.$hankaku_space.'%")';
				}
				if(!empty($data['customerruby'])){
					$ruby = mb_convert_encoding($data['customerruby'], 'euc-jp', 'utf-8');
					$ruby_hira = mb_convert_encoding(mb_convert_kana($ruby,"HVc"),'utf-8','euc-jp');
					$ruby_zenkata = mb_convert_encoding(mb_convert_kana($ruby,"KVC"),'utf-8','euc-jp');
					$ruby_hankata = mb_convert_encoding(mb_convert_kana($ruby,"kh"),'utf-8','euc-jp');

					$sql .= ' and (customerruby LIKE "%'.$ruby_hira.'%" OR';
					$sql .= ' customerruby LIKE "%'.$ruby_zenkata.'%" OR';
					$sql .= ' customerruby LIKE "%'.$ruby_hankata.'%")';
				}
				if(!empty($data['print_key'])){
					$sql .= ' and orderarea.print_type = "'.$data['print_key'].'"';
					$sql .= ' and orderselectivearea.selectiveid is not null';
				}
				if(!empty($data['category_key'])){
					$sql .= ' and category.category_key = "'.$data['category_key'].'"';
				}
				if(!empty($data['factory'])){
					$sql .= ' and orders.factory = '.$data['factory'];
				}
				$sql .= ' having category_name is not null';
				$sql .= ' order by orders.id';
				
				// 受注No.ごとに集計
				$rs = array();
				$idx = 0;
				$curid = null;
				$isAllRepeat = true;	// 全ての版がリピ版の場合にtrue
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					/*
					if($res['category_id']===0){
						$category_name = 'その他';
					}else if($res['category_id']==100){
						$category_name = '持込';
					}else if($res['category_id']==99){
						$category_name = "転写シート";
					}else{
						$category_name = $res['category_name'];
					}
					$res['category_name'] = $category_name;
					*/
					if($res['ordersid']!=$curid){
						if(!is_null($curid)){
							// でき転
							if(count($tmp[$curid]['mixture'])>1){
								ksort($tmp[$curid]['mixture']);
								$rs[$pre]['mixture'] = implode('', $tmp[$curid]['mixture']);
							}else{
								$rs[$pre]['mixture'] = '';
							}
							// リピ版チェック
							if($isAllRepeat){
								$rs[$pre]['all_repeat'] = 1;
							}else{
								$rs[$pre]['all_repeat'] = 0;
							}
						}
						$rs[$idx++] = $res;
						$curid = $res['ordersid'];
						$pre = $idx-1;
						$isAllRepeat = true;
					}
					// でき転の集計
					if(!empty($res['print_type'])){
						$index = $this->print_codename[$res['print_type']]['index'];
						$tmp[$curid]['mixture'][$index] = $this->print_codename[$res['print_type']]['abbr'];
					}
					// 商品名の集計
					if(!empty($res['category_name']) && strpos($rs[$pre]['category_name'], $res['category_name'])===false){
						$rs[$pre]['category_name'] .= "<br>".$res['category_name'];
					}
					// 当該注文で1つでも新版があればfalse、全てリピ版の場合はtrue
					if(empty($res['repeat_check'])) $isAllRepeat = false;
				}
				if($idx>0){
					$pre = $idx-1;
					// でき転
					if(count($tmp[$curid]['mixture'])>1){
						ksort($tmp[$curid]['mixture']);
						$rs[$pre]['mixture'] = implode('', $tmp[$curid]['mixture']);
					}else{
						$rs[$pre]['mixture'] = '';
					}
					// リピ版チェック
					if($isAllRepeat){
						$rs[$pre]['all_repeat'] = 1;
					}else{
						$rs[$pre]['all_repeat'] = 0;
					}
				}
				
				// 編集時間を含むマルチソート
				for($i=0; $i<count($rs); $i++){
					if(isset($_SESSION['edited'][$rs[$i]['ordersid']])){
						$rs[$i]['edited'] = $_SESSION['edited'][$rs[$i]['ordersid']];
					}else{
						$rs[$i]['edited'] = 0;
					}
					$a[] = $rs[$i]['edited'];
					$b[] = $rs[$i]['schedule3'];
					$c[] = $rs[$i]['customer_id'];
					$d[] = $rs[$i]['ordersid'];
				}
				
				// 編集した時間の遅い順にソート
				if(empty($data['sort'])){
					// 受注NO.の降順
					array_multisort($a,SORT_DESC, $b,SORT_ASC, $c,SORT_DESC, $d,SORT_DESC, $rs);
				}else{
					// 受注NO.の昇順
					array_multisort($a,SORT_DESC, $b,SORT_DESC, $c,SORT_DESC, $d,SORT_DESC, $rs);
				}
				
				$flg = false;
				break;

			case 'completedorder':
				/****************************
				*	確定注文情報（自動送信メール）
				*	一般のみ
				*****************************/
				$sql = 'SELECT * FROM (((orders LEFT JOIN customer ON orders.customer_id=customer.id)
					 LEFT JOIN progressstatus ON orders.id=progressstatus.orders_id)
					 LEFT JOIN acceptstatus ON orders.id=acceptstatus.orders_id)
					 LEFT JOIN acceptprog ON acceptstatus.progress_id=acceptprog.aproid 
					 WHERE created>"2011-06-05" and progress_id=4 and orders.ordertype="general"';
				
				if(!empty($data['shipped'])){// 発送済み（2）の場合
					$sql .= ' and shipped = '.$data['shipped'];
				}
				if(!empty($data['schedule3'])){// 発送日
					$sql .= ' and schedule3 = "'.$data['schedule3'].'"';
				}
				if(!empty($data['schedule4'])){// お届日
					$sql .= ' and schedule4 = "'.$data['schedule4'].'"';
				}
				
				break;
				
			case 'orderlist':
				/****************************
				*	注文一覧（進捗）
				*****************************/
				if(!empty($data['print_key'])){
					// 複数のプリント位置の中に、指定されたプリント方法が1つでもある受注IDを抽出
					$sql = 'select orders.id as id from (orders inner join orderprint on orders.id=orderprint.orders_id)
						 inner join orderarea on orderprint.id=orderarea.orderprint_id 
						 where created>"2011-06-05"';
					if($data['print_key']=="alltrans"){	//alltrans カラー・デジタル・カッティングを転写でまとめたキー
						$sql .= ' and (print_type = "trans"';
						$sql .= ' or print_type = "digit"';
						$sql .= ' or print_type = "cutting")';
					}else if($data['print_key']=="silk"){	// シルクで且つインクが指定されている
						$sql .= ' and print_type = "'.$data['print_key'].'" and ink_count>0';
					}else if($data['print_key']=="noprint"){
						$sql .= ' and noprint = 1';		// プリントなし
					}else{
						$sql .= ' and print_type = "'.$data['print_key'].'"';
					}
					$sql .= ' group by orders.id';
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$tmp[] = $res['id'];
					}
					$id_list = implode(',', $tmp);
				}
				$tmp = array();

				// 受注が完了していて且つ取り消しになっていないデータ
				// 業者でプリントの無い注文も含む
				$sql = 'SELECT *, 
					(case when coalesce(expressfee,0)=0 then 0 else round(expressfee/(productfee+printfee+exchinkfee+packfee+discountfee+designfee),1)+1 end) as express,
					coalesce(category.category_name,orderitemext.item_name) as category_name,
					bundle
					 FROM (((((((((((((((((orders LEFT JOIN customer ON orders.customer_id=customer.id)
					 LEFT JOIN billtype ON customer.bill=billtype.billid)
					 LEFT JOIN salestype ON customer.sales=salestype.salesid)
					 LEFT JOIN receipttype ON customer.receipt=receipttype.receiptid)
					 LEFT JOIN staff ON orders.reception=staff.id)
					 LEFT JOIN progressstatus ON orders.id=progressstatus.orders_id)
					 LEFT JOIN printstatus ON orders.id=printstatus.orders_id)
					 LEFT JOIN estimatedetails ON orders.id=estimatedetails.orders_id)
					 
					 LEFT JOIN orderitem ON orders.id=orderitem.orders_id)
					 LEFT JOIN orderitemext ON orderitem.id=orderitemext.orderitem_id)
					 
					 LEFT JOIN orderprint ON orderitem.print_id=orderprint.id)
					 LEFT JOIN category ON orderprint.category_id=category.id)
					 LEFT JOIN orderarea on orderprint.id=orderarea.orderprint_id)
					 LEFT JOIN printtype on orderarea.print_type=printtype.print_key)
					 LEFT JOIN orderselectivearea on orderarea.areaid=orderselectivearea.orderarea_id)
					 LEFT JOIN acceptstatus ON orders.id=acceptstatus.orders_id)
					 LEFT JOIN acceptprog ON acceptstatus.progress_id=acceptprog.aproid) ';
				/*
				if(!empty($data['proc_3'])){	// 版作成
					$sql .= 'LEFT JOIN product on orders.id=product.orders_id ';
				}
				*/
				$sql .= 'WHERE created>"2011-06-05" and progress_id = 4';
				$sql .= ' and ((orders.ordertype = "industry" and orderprint.id is null)';
				$sql .= ' or (orderarea.print_type=printstatus.printtype_key and orderselectivearea.selectiveid is not null)';
				$sql .= ' or (noprint=1 and orderitem.id is not null))';
				if(isset($data['id']) && $data['id']!=""){
					$sql .= ' and orders.id = '.$data['id'];
				}else if(!empty($data['print_key'])){
					$sql .= ' and orders.id IN ('.$id_list.')';
				}

				// 版作成　版作成が未チェックで且つシルクとデジタル転写のみ
				/*
				if(!empty($data['proc_3'])){
					$sql .= ' and';
					if($data['proc_3']=='-1'){
						$sql .= ' proc_3 = 0';
					}elseif($data['proc_3']=='9999'){
						$sql .= ' proc_3 > 0 ';
					}else{
						$sql .= ' proc_3 = '.$data['proc_3'];
					}
					$sql .= ' and ((print_type="silk" and ink_count>0) or print_type="digit")';
				}
				*/

				if($data['number']!=""){
					$sql .= ' and cstprefix = "'.substr($data['number'],0,1).'"';
					$num = substr($data['number'],1);
					if(!empty($num)) $sql .= ' and number = '.$num;
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				if(!empty($data['maintitle'])){
					$zenkaku = mb_convert_encoding(mb_convert_kana($data['maintitle'],"AKV"),'utf-8','euc-jp');
					$hankaku = mb_convert_encoding(mb_convert_kana($data['maintitle'],"ak"),'utf-8','euc-jp');
					
					$sql .= ' and (maintitle LIKE "%'.$data['maintitle'].'%"';
					$sql .= ' or maintitle LIKE "%'.$zenkaku.'%"';
					$sql .= ' or maintitle LIKE "%'.$hankaku.'%")';
				}
				if(!empty($data['company'])){
					$sql .= ' and company LIKE "%'.$data['company'].'%"';
				}
				if(!empty($data['companyruby'])){
					$ruby = mb_convert_encoding($data['companyruby'], 'euc-jp', 'utf-8');
					$ruby_hira = mb_convert_encoding(mb_convert_kana($ruby,"HVc"),'utf-8','euc-jp');
					$ruby_zenkata = mb_convert_encoding(mb_convert_kana($ruby,"KVC"),'utf-8','euc-jp');
					$ruby_hankata = mb_convert_encoding(mb_convert_kana($ruby,"kh"),'utf-8','euc-jp');

					$sql .= ' and (companyruby LIKE "%'.$ruby_hira.'%" OR';
					$sql .= ' companyruby LIKE "%'.$ruby_zenkata.'%" OR';
					$sql .= ' companyruby LIKE "%'.$ruby_hankata.'%")';
				}
				if(!empty($data['customername'])){
					$zenkaku_space = mb_convert_kana($data['customername'],"S", 'utf-8');
					$hankaku_space = mb_convert_kana($data['customername'],"s", 'utf-8');
					
					$sql .= ' and (customername LIKE "%'.$data['customername'].'%"';
					$sql .= ' or customername LIKE "%'.$zenkaku_space.'%"';
					$sql .= ' or customername LIKE "%'.$hankaku_space.'%")';
				}
				if(!empty($data['customerruby'])){
					$ruby = mb_convert_encoding($data['customerruby'], 'euc-jp', 'utf-8');
					$ruby_hira = mb_convert_encoding(mb_convert_kana($ruby,"HVc"),'utf-8','euc-jp');
					$ruby_zenkata = mb_convert_encoding(mb_convert_kana($ruby,"KVC"),'utf-8','euc-jp');
					$ruby_hankata = mb_convert_encoding(mb_convert_kana($ruby,"kh"),'utf-8','euc-jp');

					$sql .= ' and (customerruby LIKE "%'.$ruby_hira.'%" OR';
					$sql .= ' customerruby LIKE "%'.$ruby_zenkata.'%" OR';
					$sql .= ' customerruby LIKE "%'.$ruby_hankata.'%")';
				}
				if(!empty($data['shipped'])){
					$sql .= ' and shipped = '.$data['shipped'];
				}
				if(!empty($data['ordertype'])){
					$sql .= ' and ordertype = "'.$data['ordertype'].'"';
				}
				if(!empty($data['factory'])){
					$sql .= ' and orders.factory = '.$data['factory'];
				}
				
				$sql .= ' GROUP BY orders.id, print_name, orderitem.id, printposition_id';
				$sql .= ' having category_name is not null';

				// 一番古い注文確定日をスタッフの抽出の条件で使用するため
				$sql2 = $sql.' order by schedule3 limit 1';
				$result = exe_sql($sql2);
				$rec = mysqli_fetch_assoc($result);
				$oldestdate = $rec['schedule3'];
				
				
				$sql .= ' ORDER BY schedule3, customer.id, bundle desc, orders.id, printtypeid';
				$result = exe_sql($conn, $sql);
				$r = -1;
				$plates_name = array();
				while($rec = mysqli_fetch_assoc($result)){
					// 一番古い注文確定日
					$rec['oldestdate'] = $oldestdate;
					/* 商品名の集計
					if($rec['category_id']==0){
						$category_name = 'その他';
					}else if($rec['category_id']==100){
						$category_name = '持込';
					}else if($rec['category_id']==99){
						$category_name = "転写シート";
					}else{
						$category_name = $rec['category_name'];
					}
					*/
					
					if($rs[$r]['orders_id']!=$rec['orders_id']){
						$r++;
						$rs[$r] = $rec;
					}else if(strpos($rs[$r]['print_name'], $rec['print_name'])===false){
						$r++;
						$rs[$r] = $rec;
					}else{
						// 複数の商品名がある場合
						if(strpos($rs[$r]['category_name'], $rec['category_name'])===false){
							$rs[$r]['category_name'] .= "<br>".$rec['category_name'];
						}
					}
				}
				
				$flg = false;
				break;
				
			case 'ordering':
				/****************************
				*	発注
				*	確定注文で未発送
				*****************************/
				
				// キャブの発注受付通知結果を取得してデータを更新
				$flg = false;
				/*  2015-07-14 一時保留
				$http = new HTTP('http://takahamalifeart.com/cab/cab_response.php');
				$param = array();
				$reply = $http->request('POST', $param);
				$rs = unserialize($reply);
				if(!empty($rs)){
					if(is_array($rs)){
						array_unshift($rs, "Error");	// データベース更新エラーの場合
					}
					break;
				}
				*/
				
				$sql = "select orders.id as ordersid, schedule2, customer_id, reception, staffname, maintitle, customername, order_amount, factory,
				 ordering, toms_order, toms_response, cab_order, cab_response,
				 coalesce(maker.maker_name, orderitemext.maker) as makername,
				 coalesce(case orderitemext.item_id 
				 when 100000 then '持込' 
				 when 99999 then '転写シート' 
				 when 0 then 'その他' 
				 else null end, category_name) as catname, 
				 case when item_code is null then '' else item_code end as item_code,
				 coalesce(item.item_name, orderitemext.item_name) as itemname,
				 coalesce(size.size_name, orderitemext.size_name) as sizename,
				 coalesce(itemcolor.color_name, orderitemext.item_color) as color,
				 color_code,
				 amount
				 from (((((((((((orders
				 inner join customer on customer_id=customer.id)
				 inner join orderitem on orders.id=orderitem.orders_id)
				 inner join staff on orders.reception=staff.id)
				 left join orderitemext on orderitem.id=orderitem_id)
				 left join size on orderitem.size_id=size.id)
				 left join catalog on orderitem.master_id=catalog.id)
				 left join category on catalog.category_id=category.id)
				 left join item on catalog.item_id=item.id)
				 left join maker on item.maker_id=maker.id)
				 left join itemcolor on catalog.color_id=itemcolor.id)
				 inner join acceptstatus on orders.id=acceptstatus.orders_id)
				 inner join progressstatus on orders.id=progressstatus.orders_id
				 where created>'2011-06-05' and progress_id=4 and shipped=1
				 and catalogapply<=schedule2 and catalogdate>schedule2 and itemapply<=schedule2 and itemdate>schedule2";
				 
				if(!empty($data['staff'])){
					$sql .= ' and reception='.$data['staff'];
				}
				if($data['state']==1){
					// 発注回答待ち
					$sql .= ' and ((toms_order=1 && toms_response=0) || (cab_order=1 && cab_response=0))';
				}else if($data['state']==2){
					// 発注回答で不足分ありのため発注中止となっている分
					$sql .= ' and ((toms_order=1 && toms_response=2) || (cab_order=1 && cab_response=2))';
				}else{
					// 未発注または発注済で且つ回答が済んでいないもの（不足分で発注中止も含む）
					$sql .= ' and (ordering=0 || (toms_order=1 && toms_response!=1) || (cab_order=1 && cab_response!=1))';
				}
				if(!empty($data['factory'])){
					$sql .= ' and orders.factory = '.$data['factory'];
				}
				$sql .= ' order by schedule2, customer.id';
				
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$rs[] = $res;
				}
				$tmp=array(
					'70'=>1,'80'=>2,'90'=>3,'100'=>4,'110'=>5,'120'=>6,'130'=>7,'140'=>8,'150'=>9,'160'=>10,
					'JS'=>11,'JM'=>12,'JL'=>13,'WS'=>14,'WM'=>15,'WL'=>16,'GS'=>17,'GM'=>18,'GL'=>19,
					'XS'=>20,'S'=>21,'M'=>22,'L'=>23,'XL'=>24,'XXL'=>25,'3L'=>26,'4L'=>27,'5L'=>28,'6L'=>29,'7L'=>30,'8L'=>31);
				
				for($i=0; $i<count($rs); $i++){
					$a[$i] = $rs[$i]['schedule_2'];
					$b[$i] = $rs[$i]['customer_id'];
					$c[$i] = $rs[$i]['ordersid'];
					$d[$i] = $rs[$i]['catname'];
					$e[$i] = $rs[$i]['itemname'];
					$f[$i] = $rs[$i]['color_code'];
					$g[$i] = $tmp[$rs[$i]['sizename']];
				}
				array_multisort($a,$b,$c,$d,$e,$f,$g, $rs);
				
				break;
				
			case 'arrivalsheet':
				/****************************
				*	入荷票とチェックシートの印刷
				*		発注済の注文
				*****************************/
				$sql = 'select orders.id as id, ordertype, state_7, schedule2, schedule3, schedule4, company, customername, maintitle, 
					dateofsilk, dateoftrans, dateofinkjet, arrival, package_yes, package_no, package_nopack, payment, arrange, boxnumber, printtype_key, 
					coalesce(maker_name,maker) as maker, 
					coalesce(item.item_name,orderitemext.item_name) as item, 
					coalesce(item_color, color_name) as color, 
					coalesce(orderitemext.size_name, size.size_name) as size, amount, 
					repeater, completionimage, coalesce(expressfee,"0") as express 
					 from ((((((((((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 inner join progressstatus on orders.id=progressstatus.orders_id) 
					 inner join customer on orders.customer_id=customer.id) 
					 inner join product on orders.id=product.orders_id) 
					 left join estimatedetails on orders.id=estimatedetails.orders_id)
					 left join orderitem on orders.id=orderitem.orders_id) 
					 left join orderitemext on orderitem.id=orderitem_id) 
					 left join catalog on master_id=catalog.id) 
					 left join item on catalog.item_id=item.id) 
					 left join maker on item.maker_id=maker.id) 
					 left join itemcolor on catalog.color_id=itemcolor.id) 
					 left join size on size_id=size.id 
					 where created>"2011-06-05" and progress_id=4 and state_0>0';
				
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				if(!empty($data['arrival'])){
					$sql .= ' and arrival = "'.$data['arrival'].'"';
				}
				if(!empty($data['maker'])){
					$sql .= ' and (maker_name like "%'.$data['maker'].'%"';
					$sql .= ' or maker like "%'.$data['maker'].'%")';
				}
				if($data['fin_7']==1){	// 未入荷で未発送
					$sql .= ' and state_7=0 and shipped=1';
				}else if($data['fin_7']==2){	// 入荷済みで未発送
					if(!empty($data['state_7']) && $data['fin_7']!=1){
						$sql .= ' and state_7 = '.$data['state_7'];
					}else{
						$sql .= ' and state_7>0';
					}
					$sql .= ' and shipped=1';
				}
				if(!empty($data['factory'])){
					$sql .= ' and orders.factory = '.$data['factory'];
				}
				$sql .= ' group by orders.id, printtype_key';
				$sql .= ' order by schedule3, customer.id, orders.id';
				
				$result = exe_sql($conn, $sql);
				while($rec = mysqli_fetch_assoc($result)){
					$index = $this->print_codename[$rec['printtype_key']]['index'];
					$tmp[$rec['id']][$index] = $this->print_codename[$rec['printtype_key']]['abbr'];
					$rs2[] = $rec;
				}
				
				// プリント方法
				$t = -1;
				for($i=0; $i<count($rs2); $i++){
					//$printname = $this->print_codename[$rs2[$i]['printtype_key']]['name'];
					//if(empty($printname)) $printname = 'プリントなし';
					if($rs2[$i]['id']!=$curid){
						$curid = $rs2[$i]['id'];
						if(count($tmp[$curid])>1){
							ksort($tmp[$curid]);
							$rs2[$i]['mixture'] = implode('', $tmp[$curid]);
						}else{
							$rs2[$i]['mixture'] = '';
						}
						$rs[++$t] = $rs2[$i];
						//$rs[$t]['print_name'] = $printname;
						
					}
					/*
					else{
						$rs[$t]['print_name'] .= ', '.$printname;
					}
					*/
				}
				
				$flg = false;
				break;
				
			case 'arrivalstatement':
				/****************************
				*	入荷明細書の印刷
				*		未発注済の注文を含む
				*****************************/
				$sql = 'SELECT * FROM (((((((((orders LEFT JOIN customer ON orders.customer_id=customer.id)
						 LEFT JOIN billtype ON customer.bill=billtype.billid)
						 LEFT JOIN salestype ON customer.sales=salestype.salesid)
						 LEFT JOIN receipttype ON customer.receipt=receipttype.receiptid)
						 LEFT JOIN delivery ON orders.delivery_id=delivery.id)
						 LEFT JOIN progressstatus ON orders.id=progressstatus.orders_id)
						 LEFT JOIN staff ON orders.reception=staff.id)
						 LEFT JOIN estimatedetails on orders.id=estimatedetails.orders_id)
						 LEFT JOIN printstatus ON orders.id=printstatus.orders_id)
						 LEFT JOIN acceptstatus ON orders.id=acceptstatus.orders_id';
				$sql .= ' WHERE orders.id in ('.$data['id'].')';
				$sql .= ' order by orders.id';
				
				$result = exe_sql($conn, $sql);
				$tmp = array();
				while($res = mysqli_fetch_assoc($result)){
					$index = $this->print_codename[$res['printtype_key']]['index'];
					$tmp[$res['orders_id']][$index] = $this->print_codename[$res['printtype_key']]['abbr'];
					
					$rs[] = $res;
				}
				
				// 混合プリントをチェック
				for($i=0; $i<count($rs); $i++){
					if(count($tmp[$rs[$i]['orders_id']])>1){
						ksort($tmp[$rs[$i]['orders_id']]);
						$rs[$i]['mixture'] = implode('', $tmp[$rs[$i]['orders_id']]);
					}else{
						$rs[$i]['mixture'] = '';
					}
				}
				
				$flg = false;
				break;
				
			case 'stocklist':
		 		/****************************
				*	入荷
				*****************************/
			 	// 作業予定日
				$sql = 'select orders.id as id, dateofsilk, dateofpress, dateofinkjet 
					from ((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id)  
					 inner join progressstatus on orders.id=progressstatus.orders_id 
					 where created>"2011-06-05" and progress_id=4';
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				if($data['fin_7']==1){	// 未入荷で未発送
					$sql .= ' and state_7=0 and shipped=1';
				}else if($data['fin_7']!=1){	// 入荷済みまたは全て
					if(empty($data['state_7'])){
						$sql .= ' and state_7>0';
					}else{
						$sql .= ' and state_7 = '.$data['state_7'];
					}
				}
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					if(empty($rs1[$res['id']])) $rs1[$res['id']] = $res;
					if($rs1[$res['id']]['dateofsilk']=='0000-00-00') $rs1[$res['id']]['dateofsilk'] = $res['dateofsilk'];
					if($rs1[$res['id']]['dateofpress']=='0000-00-00') $rs1[$res['id']]['dateofpress'] = $res['dateofpress'];
					if($rs1[$res['id']]['dateofinkjet']=='0000-00-00') $rs1[$res['id']]['dateofinkjet'] = $res['dateofinkjet'];
				}
				
				
				// 基本データ
				// 業者の転写シートを除外
				// 版ごとのデータを除外　platesid is null
				$sql = 'select orders.id as id, state_0, state_7, schedule2, schedule3, company, customername, maintitle, carriage, noprint,
					dateofsilk, dateofpress, dateofinkjet, arrival, arrange, repeater, reuse, repeatdesign, allrepeat, completionimage, factory,
					coalesce(maker_name,maker) as maker, 
					coalesce(item.item_name,orderitemext.item_name) as item, 
					coalesce(item_color, color_name) as color, 
					coalesce(orderitemext.size_name, size.size_name) as size, amount, 
					orderitemext.item_id as ext_itemid, 
					ordering, toms_order, toms_response, 
					(case when coalesce(expressfee,0)=0 then 0 else round(expressfee/(productfee+printfee+exchinkfee+packfee+discountfee+designfee),1)+1 end) as express
					 from ((((((((((((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id)  
					 inner join progressstatus on orders.id=progressstatus.orders_id) 
					 left join estimatedetails on orders.id=estimatedetails.orders_id)
					 inner join customer on orders.customer_id=customer.id) 
					 inner join orderitem on orders.id=orderitem.orders_id) 
					 left join printtype on printstatus.printtype_key=print_key)
					 left join orderitemext on orderitem.id=orderitem_id) 
					 left join product on orders.id=product.orders_id) 
					 left join catalog on master_id=catalog.id) 
					 left join item on catalog.item_id=item.id) 
					 left join maker on item.maker_id=maker.id) 
					 left join itemcolor on catalog.color_id=itemcolor.id) 
					 left join size on size_id=size.id) 
					 left join orderplates on orders.id=orderplates.orders_id
					 where created>"2011-06-05" and progress_id=4 
					 and (orderitemext.item_id is null or orderitemext.item_id!=99999)
					 and platesid is null';
				
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				if(!empty($data['arrival'])){
					$sql .= ' and arrival = "'.$data['arrival'].'"';
				}
				if(!empty($data['maker'])){
					$sql .= ' and (maker_name like "%'.$data['maker'].'%"';
					$sql .= ' or maker like "%'.$data['maker'].'%")';
				}
				if($data['fin_7']==1){	// 未入荷で未発送
					$sql .= ' and state_7=0 and shipped=1';
				}else if($data['fin_7']!=1){	// 入荷済みまたは全て
					if(empty($data['state_7'])){
						$sql .= ' and state_7>0';
					}else{
						$sql .= ' and state_7 = '.$data['state_7'];
					}
				}
				if(!empty($data['factory'])){
					$sql .= ' and orders.factory = '.$data['factory'];
				}
				
				$sql .= ' group by orders.id, orderitem.id';
				$sql .= ' order by schedule3, customer.id, orders.id, item, color, size.id';
				
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					if( !empty($rs1[$res['id']]) ){
						$res['dateofsilk'] = $rs1[$res['id']]['dateofsilk'];
						$res['dateofpress'] = $rs1[$res['id']]['dateofpress'];
						$res['dateofinkjet'] = $rs1[$res['id']]['dateofinkjet'];
					}
					$rs[] = $res;
				}
				
				// プリント方法
				$sql = 'select orders.id as id, coalesce(print_name, printtype_key) as printname
					 from (((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id)   
					 inner join progressstatus on orders.id=progressstatus.orders_id) 
					 
					 left join printtype on printtype_key=print_key 
					 where progress_id=4';
				
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				if($data['fin_7']==1){	// 未入荷で未発送
					$sql .= ' and state_7=0 and shipped=1';
				}else if($data['fin_7']!=1){	// 入荷済みまたは全て
					if(empty($data['state_7'])){
						$sql .= ' and state_7>0';
					}else{
						$sql .= ' and state_7 = '.$data['state_7'];
					}
				}
				
				$sql .= ' group by orders.id, printtype_key';
				
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$printname = $res['printname']!='noprint'? $res['printname']: 'プリントなし';
					if(isset($rs2[$res['id']])){
						$rs2[$res['id']] .= '<br>'.$printname;
					}else{
						$rs2[$res['id']] = $printname;
					}
				}
				
				for($i=0; $i<count($rs); $i++){
					if(isset($rs2[$rs[$i]['id']])){
						$rs[$i]['print_name'] = $rs2[$rs[$i]['id']];
					}else{
						$rs[$i]['print_name'] = '';
					}
				}
				
				$flg = false;
				break;
			
			case 'artworklist1':
		 		/****************************
				*	版下　年度集計
				*	版数合計と
				*	IJ,TS,CS,イメ画の受注件数
				*****************************/
				if(empty($data['FY']) || $data['FY']<2011) return;
				$tmp = array('han'=>0,'inkjet'=>0,'trans'=>0,'cutting'=>0,'img'=>0);
				$startdate = $data['FY'].'-04-01';
			 	$enddate = ($data['FY']+1).'-03-31';
				
				for($i=0; $i<13; $i++){
					$rs['total'][$i] = $tmp;
				}
				
				// 版数合計
				$sql = 'select date_format(schedule3, "%c") as month, orders.id as ordersid, product.id as proid, printtype, platesinfo, plates, platescount, platesnumber, staffname 
					 from ((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 inner join product on orders.id=product.orders_id) 
					 inner join printinfo on product.id=product_id) 
					 left join staff on state_1=staff.id 
					 where created>"2011-06-05" and progress_id=4 and print_posname!="" 
					 and ((printtype=1 and printinfo.platesnumber>0) or (printtype=3 and product.platescount>0)) 
					 and (printtype_key="silk" or printtype_key="digit")
					 and schedule3 between "'.$startdate.'" and "'.$enddate.'"';
				$sql .= ' GROUP BY orders.id, product.id, pinfoid';
				
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$staff = empty($res['staffname'])? '-': $res['staffname'];
					if(!isset($rs[$staff])){
						for($i=0; $i<13; $i++){
							$rs[$staff][$i] = $tmp;
						}
					}
					if($res['printtype']==1){
						$rs[$staff][$res['month']]['han'] += $res['platesnumber'];
						$rs['total'][$res['month']]['han'] += $res['platesnumber'];
					}else if($curid!=$res['proid']){
						$rs[$staff][$res['month']]['han'] += $res['platescount'];
						$rs['total'][$res['month']]['han'] += $res['platescount'];
					}
					
					$curid = $res['proid'];
				}
				
				
				// IJ,TS,CS,イメ画の受注件数
				$curid = 0;
				$sql = 'select date_format(schedule3, "%c") as month, orders.id as ordersid, completionimage, staffname, printtype_key 
					 from ((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 left join staff on state_1=staff.id 
					 where created>"2011-06-05" and progress_id=4 and estimated!=0 
					 and ( (printtype_key="inkjet" or printtype_key="trans" or printtype_key="cutting") 
					 or completionimage=1 )
					 and schedule3 between "'.$startdate.'" and "'.$enddate.'"';
				$sql .= ' GROUP BY orders.id, prnstatusid';
				
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$staff = empty($res['staffname'])? '-': $res['staffname'];
					if(!isset($rs[$staff])){
						for($i=0; $i<13; $i++){
							$rs[$staff][$i] = $tmp;
						}
					}
					
					// inkjet, trans, cutting の枚数
					if($res['printtype_key']=='inkjet' || $res['printtype_key']=='trans' || $res['printtype_key']=='cutting'){
						$rs[$staff][$res['month']][$res['printtype_key']] += 1;
						$rs['total'][$res['month']][$res['printtype_key']] += 1;
					}
					
					// イメ画ありの枚数
					if($curid!=$res['ordersid'] && $res['completionimage']==1){
						$rs[$staff][$res['month']]['img'] += 1;
						$rs['total'][$res['month']]['img'] += 1;
					}
					
					$curid = $res['ordersid'];
				}
				
				$flg = false;
				break;
				
			case 'artworklist2':
		 		/****************************
				*	版下　月次集計
				*	版数合計と
				*	IJ,TS,CS,イメ画の受注件数
				*****************************/
				if(empty($data['FY2']) || $data['FY2']<2011) return;
				if($data['monthly']<1 || $data['monthly']>12) return;
				$r = array();
				$tmp = array('han'=>0,'inkjet'=>0,'trans'=>0,'cutting'=>0,'img'=>0);
				$startdate = date('Y-m-d', mktime(0,0,0,$data['monthly'],1,$data['FY2']));	// 今月1日
				$enddate =   date('Y-m-d', mktime(0,0,0,$data['monthly']+1,0,$data['FY2']));	// 今月末
				
				// 版数合計
				$sql = 'select product.id as proid, printtype, platesinfo, plates, platescount, platesnumber, staffname 
					 from ((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 inner join product on orders.id=product.orders_id) 
					 inner join printinfo on product.id=product_id) 
					 left join staff on state_1=staff.id 
					 where created>"2011-06-05" and progress_id=4 and print_posname!="" 
					 and ((printtype=1 and printinfo.platesnumber>0) or (printtype=3 and product.platescount>0)) 
					 and (printtype_key="silk" or printtype_key="digit")
					 and schedule3 between "'.$startdate.'" and "'.$enddate.'"';
				$sql .= ' GROUP BY orders.id, product.id, pinfoid';
				
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$staff = empty($res['staffname'])? '-': $res['staffname'];
					if(!isset($r[$staff])) $r[$staff] = $tmp;
					
					if($res['printtype']==1){
						$r[$staff]['han'] += $res['platesnumber'];
					}else if($curid!=$res['proid']){
						$r[$staff]['han'] += $res['platescount'];
					}
					
					$curid = $res['proid'];
				}
				
				// IJ,TS,CS,イメ画の受注件数
				$curid = 0;
				$sql = 'select orders.id as ordersid, completionimage, staffname, printtype_key 
					 from ((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 left join staff on state_1=staff.id 
					 where created>"2011-06-05" and progress_id=4 and estimated!=0 
					 and ( (printtype_key="inkjet" or printtype_key="trans" or printtype_key="cutting") 
					 or completionimage=1 )
					 and schedule3 between "'.$startdate.'" and "'.$enddate.'"';
				$sql .= ' GROUP BY orders.id, prnstatusid';
				
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$staff = empty($res['staffname'])? '-': $res['staffname'];
					if(!isset($r[$staff])) $r[$staff] = $tmp;
					
					// inkjet, trans, cutting の枚数
					if($res['printtype_key']=='inkjet' || $res['printtype_key']=='trans' || $res['printtype_key']=='cutting'){
						$r[$staff][$res['printtype_key']] += 1;
					}
					
					// イメ画ありの枚数
					if($curid!=$res['ordersid'] && $res['completionimage']==1){
						$r[$staff]['img'] += 1;
					}
					
					$curid = $res['ordersid'];
				}
				
				ksort($r);
				$rs = array($r);
				
				$flg = false;
				break;
				
			case 'artworklist':
		 		/****************************
				*	版下
				*	プリントありのみ、プリント方法ごと
				*****************************/
				
				// デジタルのシート数
				$sql = 'select orders.id as id, product.id as proid, sheets
					 from (((((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 inner join progressstatus on orders.id=progressstatus.orders_id) 
					 inner join orderprint on orders.id=orderprint.orders_id) 
					 inner join orderarea on orderprint.id=orderprint_id) 
					 inner join orderselectivearea on areaid=orderarea_id)
					 inner join product on orders.id=product.orders_id) 
					 inner join cutpattern on product.id=cutpattern.product_id 
					 where created>"2011-06-05" and progress_id=4 and orders.noprint=0 
					 and selectiveid is not null and orderarea.print_type=printstatus.printtype_key 
					 and printtype=3';
					 
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				if(!empty($data['print_type'])){
					$sql .= ' and print_type = "'.$data['print_type'].'"';
				}
				if($data['fin_1']==1){
					$sql .= ' and fin_1=0';
				}else if($data['fin_1']==2){
					$sql .= ' and fin_1=1 and shipped=1';
				}
				$sql .= ' group by orders.id, product.id, cutid';
				$result = exe_sql($conn, $sql);
				$rs2 = array();
				while($rec = mysqli_fetch_assoc($result)){
					$rs2[$rec['proid']] += $rec['sheets'];
				}
				
				
				// イメ画確定のレコードを抽出
				$sql = 'select *, orders.id as id from (((orders
				 inner join customer on orders.customer_id=customer.id)
				 inner join acceptstatus on orders.id=acceptstatus.orders_id)
				 inner join printstatus on orders.id=printstatus.orders_id)
				 inner join progressstatus on orders.id=progressstatus.orders_id
				 where created>"2011-06-05"';
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				/*
				if(!empty($data['term_from'])){
					$sql .= ' and schedule2 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule2 <= "'.$data['term_to'].'"';
				}
				*/
				if($data['fin_image']==1){
					$sql .= ' and progress_id=5 and state_image=0';	// イメ画未終了（defalt）
				}else if($data['fin_image']==2){
					$sql .= ' and progress_id=7 and shipped=1';	// イメ画終了で未確定で且つ未発送
				}
				if(!empty($data['factory'])){
					$sql .= ' and orders.factory = '.$data['factory'];
				}
				$sql .= ' group by orders.id, schedule3';
				$result = exe_sql($conn, $sql);
				$rs = array();
				while($rec = mysqli_fetch_assoc($result)){
					$rec['print_type'] = 'イメ画製作';
					$rec['express'] = '0';
					$rec['design_type'] = '';
					$rec['areaid'] = '';
					$rec['plates'] = '';
					$rec['platesinfo'] = '';
					$rec['edge'] = '';
					$rec['platescount'] = '';
					$rec['sheetcount'] = '';
					$rec['platesnumber'] = '';
					$rs[] = $rec;
				}
				
				// 版下情報の抽出
				$sql = 'select orders.id as id, product.id as proid, schedule2, schedule3, company, customername, maintitle, factory, ordertype, bundle,
					dateofartwork, dateofsilk, dateoftrans, dateofinkjet, print_type, design_type, areaid, 
					state_1, fin_1, note_artwork, plates, platesinfo, edge, platescount, sheetcount, platesnumber, 
					repeater, reuse, repeatdesign, allrepeat, completionimage, coalesce(expressfee,"0") as express
					 from ((((((((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 inner join progressstatus on orders.id=progressstatus.orders_id) 
					 left join estimatedetails on orders.id=estimatedetails.orders_id)
					 inner join customer on orders.customer_id=customer.id) 
					 inner join orderprint on orders.id=orderprint.orders_id) 
					 inner join orderarea on orderprint.id=orderprint_id) 
					 inner join orderselectivearea on areaid=orderarea_id) 
					 inner join product on orders.id=product.orders_id) 
					 inner join printinfo on product.id=printinfo.product_id) 
					 inner join printtype on printtype.printtypeid=product.printtype 
					 where created>"2011-06-05" and progress_id=4 and orders.noprint=0 
					 and selectiveid is not null and printinfo.print_category_id=orderprint.category_id
					 and orderarea.print_type=printstatus.printtype_key
					 and printtype.print_key=printstatus.printtype_key
					 and orderselectivearea.selective_name=printinfo.print_posname';
					 
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				if(!empty($data['print_type'])){
					$sql .= ' and print_type = "'.$data['print_type'].'"';
				}
				if($data['fin_1']==1){
					$sql .= ' and fin_1=0';
					$sql .= ' and printinfo.reprint!=0';	// 未終了の場合、リピ版を外す
				}else if($data['fin_1']==2){
					$sql .= ' and fin_1=1 and shipped=1';
				}
				if(!empty($data['factory'])){
					$sql .= ' and orders.factory = '.$data['factory'];
				}
				$sql .= ' group by orders.id, print_type, areaid order by schedule3, customer.id, orders.id, print_type';
				
				$n = count($rs)-1;
				$result = exe_sql($conn, $sql);
				while($rec = mysqli_fetch_assoc($result)){
					$designtype = empty($rec['design_type'])? '-': $rec['design_type'];
					//if($rs[$n]['id']==$rec['id'] && $rs[$n]['print_type']==$rec['print_type']){
					if($rs[$n]['proid']==$rec['proid']){
						if($i<4){
							$rs[$n]['designtype_'.$i] = $designtype;
							$i++;
						}else if($i==4){
							$rs[$n]['designtype_4'] = '他';
						}
						
						if($rec['print_type']=='silk'){
							$rs[$n]['platesinfo'] .= '<br>'.$rec['platesinfo'];
							$rs[$n]['platesnumber'] += $rec['platesnumber'];
						}
					}else{
						$n++;
						$rs[$n] = $rec;
						$rs[$n]['designtype_1'] = $designtype;
						$rs[$n]['designtype_2'] = '-';
						$rs[$n]['designtype_3'] = '-';
						$rs[$n]['designtype_4'] = '-';
						$rs[$n]['sheets'] = empty($rs2[$rec['proid']])? '-': $rs2[$rec['proid']];
						$i = 2;
					}
				}
				
				$flg = false;
				break;
				
			case 'platelist1':
		 		/****************************
				*	製版　(シルクとデジタル転写)
				*	版種類ごとの版数の年度集計
				*****************************/
				if(empty($data['FY']) || $data['FY']<2011) return;
				$rs = array();
				$plates_name = array(
					'ダイレクト'=>'p1',
					'裏ゾル'=>'p2',
					'ゾル'=>'p3',
					'転写'=>'p4',
					'ジャンボ'=>'p5',
					'帽子'=>'p6',
					'長台ダイレクト'=>'p7',
					'長台ゾル'=>'p8',
					'長台裏ゾル'=>'p9',
				);
				$tmp = array('p1'=>0,'p2'=>0,'p3'=>0,'p4'=>0,'p5'=>0,'p6'=>0,'p7'=>0,'p8'=>0,'p9'=>0);
				for($i=0; $i<13; $i++){
					$rs['total'][$i] = $tmp;
				}
						
				$startdate = $data['FY'].'-04-01';
			 	$enddate = ($data['FY']+1).'-03-31';
				$sql = 'select date_format(schedule3, "%c") as month, product.id as proid, printtype, platesinfo, plates, platescount, platesnumber, staffname 
					 from ((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 inner join product on orders.id=product.orders_id) 
					 inner join printinfo on product.id=product_id) 
					 left join staff on state_2=staff.id 
					 where created>"2011-06-05" and progress_id=4 and print_posname!="" 
					 and ((printtype=1 and printinfo.platesnumber>0) or (printtype=3 and product.platescount>0)) 
					 and schedule3 between "'.$startdate.'" and "'.$enddate.'"';
				$sql .= ' GROUP BY orders.id, product.id, pinfoid';
				
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$staff = empty($res['staffname'])? '-': $res['staffname'];
					if(!isset($rs[$staff])){
						for($i=0; $i<13; $i++){
							$rs[$staff][$i] = $tmp;
						}
					}
					if($res['printtype']==1){
						$key = $plates_name[$res['platesinfo']];
						$rs[$staff][$res['month']][$key] += $res['platesnumber'];
						$rs['total'][$res['month']][$key] += $res['platesnumber'];
					}else if($curid!=$res['proid']){
						$key = $plates_name[$res['plates']];
						$rs[$staff][$res['month']][$key] += $res['platescount'];
						$rs['total'][$res['month']][$key] += $res['platescount'];
					}
					
					$curid = $res['proid'];
				}
				
				$flg = false;
				break;
				
			case 'platelist2':
		 		/****************************
				*	製版　(シルクとデジタル転写)
				*	担当者ごとの版数の月次集計
				*****************************/
				if(empty($data['FY2']) || $data['FY2']<2011) return;
				if($data['monthly']<1 || $data['monthly']>12) return;
				$r = array();
				$plates_name = array(
					'ダイレクト'=>'p1',
					'裏ゾル'=>'p2',
					'ゾル'=>'p3',
					'転写'=>'p4',
					'ジャンボ'=>'p5',
					'帽子'=>'p6',
					'長台ダイレクト'=>'p7',
					'長台ゾル'=>'p8',
					'長台裏ゾル'=>'p9',
				);
				$tmp = array('p1'=>0,'p2'=>0,'p3'=>0,'p4'=>0,'p5'=>0,'p6'=>0,'p7'=>0,'p8'=>0,'p9'=>0);
				
				$startdate = date('Y-m-d', mktime(0,0,0,$data['monthly'],1,$data['FY2']));	// 今月1日
				$enddate =   date('Y-m-d', mktime(0,0,0,$data['monthly']+1,0,$data['FY2']));	// 今月末
				
				$sql = 'select product.id as proid, printtype, platesinfo, plates, platescount, platesnumber, staffname 
					 from ((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 inner join product on orders.id=product.orders_id) 
					 inner join printinfo on product.id=product_id) 
					 left join staff on state_2=staff.id 
					 where created>"2011-06-05" and progress_id=4 and print_posname!="" 
					 and ((printtype=1 and printinfo.platesnumber>0) or (printtype=3 and product.platescount>0)) 
					 and (printtype_key="silk" or printtype_key="digit")
					 and schedule3 between "'.$startdate.'" and "'.$enddate.'"';
				$sql .= ' GROUP BY orders.id, product.id, pinfoid';
					//GROUP BY date_format(schedule2, '%Y-%m')";
				
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$staff = empty($res['staffname'])? '-': $res['staffname'];
					if(!isset($r[$staff])) $r[$staff] = $tmp;
					if($res['printtype']==1){
						$key = $plates_name[$res['platesinfo']];
						$r[$staff][$key] += $res['platesnumber'];
					}else if($curid!=$res['proid']){
						$key = $plates_name[$res['plates']];
						$r[$staff][$key] += $res['platescount'];
					}
					
					$curid = $res['proid'];
				}
				
				ksort($r);
				$rs = array($r);
				
				$flg = false;
				break;
				
			case 'platelist3':
		 		/****************************
				*	製版　(シルクとデジタル転写)
				*	- 担当者別で版種類毎の版数を日計
				*****************************/
				if(empty($data['daily'])) return;
				$r = array();
				$plates_name = array(
					'ダイレクト'=>'p1',
					'裏ゾル'=>'p2',
					'ゾル'=>'p3',
					'転写'=>'p4',
					'ジャンボ'=>'p5',
					'帽子'=>'p6',
					'長台ダイレクト'=>'p7',
					'長台ゾル'=>'p8',
					'長台裏ゾル'=>'p9',
				);
				$tmp = array('p1'=>0,'p2'=>0,'p3'=>0,'p4'=>0,'p5'=>0,'p6'=>0,'p7'=>0,'p8'=>0,'p9'=>0);
				
				$sql = 'select product.id as proid, printtype, platesinfo, plates, platescount, platesnumber, staffname 
					 from ((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 inner join product on orders.id=product.orders_id) 
					 inner join printinfo on product.id=product_id) 
					 left join staff on state_2=staff.id 
					 where created>"2011-06-05" and progress_id=4 and print_posname!="" 
					 and ((printtype=1 and printinfo.platesnumber>0) or (printtype=3 and product.platescount>0)) 
					 and (printtype_key="silk" or printtype_key="digit")
					 and schedule3 = "'.$data['daily'].'"';
				$sql .= ' GROUP BY orders.id, product.id, pinfoid';
				
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$staff = empty($res['staffname'])? '-': $res['staffname'];
					if(!isset($r[$staff])) $r[$staff] = $tmp;
					if($res['printtype']==1){
						$key = $plates_name[$res['platesinfo']];
						$r[$staff][$key] += $res['platesnumber'];
					}else if($curid!=$res['proid']){
						$key = $plates_name[$res['plates']];
						$r[$staff][$key] += $res['platescount'];
					}
					
					$curid = $res['proid'];
				}
				
				ksort($r);
				$rs = array($r);
				
				$flg = false;
				break;
				
			case 'platelist':
		 		/****************************
				*	製版　
				*	シルクとデジタル転写で版ありを指定してプリントありの注文
				*****************************/
				$rs1=array();
				$rs2=array();
				
				$sql = 'select orders.id as id, product.id as proid, state_1, state_2, fin_2, schedule2, schedule3, company,customername, 
					maintitle, factory, ordertype, bundle,
					dateofartwork, dateofsilk, dateoftrans, plates, mesh, platescount, printtype, print_name, print_key,
					platesinfo, meshinfo, attrink, platesnumber, staffname, 
					repeater, reuse, repeatdesign, allrepeat, completionimage, coalesce(expressfee,"0") as express
					 from (((((((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 inner join progressstatus on orders.id=progressstatus.orders_id) 
					 left join estimatedetails on orders.id=estimatedetails.orders_id)
					 inner join customer on orders.customer_id=customer.id) 
					 inner join orderitem on orders.id=orderitem.orders_id) 
					 inner join product on orders.id=product.orders_id) 
					 inner join printinfo on product.id=product_id) 
					 inner join printtype on printstatus.printtype_key=printtype.print_key) 
					 left join staff on state_1=staff.id 
					 where created>"2011-06-05" and progress_id=4 and print_posname!="" 
					 and ((printtype=1 and printinfo.platesnumber>0) or (printtype=3 and product.platescount>0)) 
					 and product.printtype=printtype.printtypeid';
					 
					 // and (printtype=3 and platescheck>0) 
					 
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				if(!empty($data['plates'])){
					$sql .= ' and ( (platesinfo like "%'.$data['plates'].'%" and printtype=1)';
					$sql .= ' or (plates like "%'.$data['plates'].'%" and printtype=3) )';
				}
				if(!empty($data['factory'])){
					$sql .= ' and orders.factory = '.$data['factory'];
				}
				if(!empty($data['state_1'])){
					$sql .= ' and state_1 = '.$data['state_1'];
				}
				if(!empty($data['state_2'])){
					$sql .= ' and state_2 = '.$data['state_2'];
				}
				if($data['fin_1']==1){
					$sql .= ' and (fin_1=0';
					$sql .= ' and printinfo.reprint!=0)';	// 未終了の場合、リピ版を外す
				}else if($data['fin_1']==2){
					$sql .= ' and fin_1=1 and shipped=1';
				}
				if($data['fin_2']==1){
					$sql .= ' and (fin_2=0';
					$sql .= ' and printinfo.reprint!=0)';	// 未終了の場合、リピ版を外す
				}else if($data['fin_2']==2){
					$sql .= ' and fin_2=1 and shipped=1';
				}
				if(isset($data['check'])){
					// 終了チェックの場合は受注No.順にする
					$sql .= ' group by product.id, pinfoid order by orders.id, schedule3, printtype';
				}else{
					$sql .= ' group by product.id, pinfoid order by schedule3, customer.id, orders.id, printtype';
				}
				
				$result = exe_sql($conn, $sql);
				
				$plates_name = array(
					'ダイレクト'=>'p1',
					'裏ゾル'=>'p2',
					'ゾル'=>'p3',
					'転写'=>'p4',
					'ジャンボ'=>'p5',
					'帽子'=>'p6',
					'長台ダイレクト'=>'p7',
					'長台ゾル'=>'p8',
					'長台裏ゾル'=>'p9',
				);
				$rs2 = array('p1'=>0,'p2'=>0,'p3'=>0,'p4'=>0,'p5'=>0,'p6'=>0,'p7'=>0,'p8'=>0,'p9'=>0);
					
				$i=-1;
				while($res = mysqli_fetch_assoc($result)){
					if($curid!=$res['proid']){
						$i++;
						if(is_null($res['staffname'])) $res['staffname']='-';
						$rs1[$i] = $res;
						if($res['printtype']==1){
							$key = $plates_name[$res['platesinfo']];
							$rs2[$key] += $res['platesnumber'];
						}else{
							$key = $plates_name[$res['plates']];
							$rs2[$key] += $res['platescount'];
						}
					}else{
						if($rs1[$i]['printtype']==1){
							$rs1[$i]['platesinfo'] .= '<br>'.$res['platesinfo'];
							$rs1[$i]['meshinfo'] .= '<br>'.$res['meshinfo'];
							$rs1[$i]['platesnumber'] .= '<br>'.$res['platesnumber'];
							
							$key = $plates_name[$res['platesinfo']];
							$rs2[$key] += $res['platesnumber'];
						}
					}
					$curid = $res['proid'];
				}
				
				
				
				
				// 版種類ごとの版数の集計
				/*
				$sql = 'select 
					 sum(case plates when "ダイレクト" then platescount else 0 end) as p1, 
					 sum(case plates when "ゾル" then platescount else 0 end) as p2, 
					 sum(case plates when "転写" then platescount else 0 end) as p3, 
					 sum(case plates when "ジャンボ" then platescount else 0 end) as p4, 
					 sum(case plates when "帽子" then platescount else 0 end) as p5 
					 from (((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join progressstatus on orders.id=progressstatus.orders_id) 
					 inner join product on orders.id=product.orders_id) 
					 inner join printinfo on product.id=product_id 
					 where created>"2011-06-05" and progress_id=4 and print_posname!="" 
					 and platescheck=1 and (printtype=1 or printtype=3)';
				*/
				
				/*
				$sql = 'select plates,platescount
					 from ((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id)  
					 inner join progressstatus on orders.id=progressstatus.orders_id) 
					 inner join product on orders.id=product.orders_id) 
					 inner join printinfo on product.id=product_id 
					 where created>"2011-06-05" and progress_id=4 and platescount>0 
					 and print_posname!="" and platescheck>0 and (printtype=1 or printtype=3)';
					 
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule2 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule2 <= "'.$data['term_to'].'"';
				}
				if(!empty($data['plates'])){
					$sql .= ' and plates like "%'.$data['plates'].'%"';
				}
				if(!empty($data['state_1']) && $data['fin_1']!=1){
					$sql .= ' and state_1 = '.$data['state_1'];
				}
				if(!empty($data['state_2']) && $data['fin_2']!=1){
					$sql .= ' and state_2 = '.$data['state_2'];
				}
				if($data['fin_1']==1){
					$sql .= ' and fin_1=0 and shipped=1';
				}else if($data['fin_1']==2){
					$sql .= ' and fin_1=1 and shipped=1';
				}
				if($data['fin_2']==1){
					$sql .= ' and state_2=0 and shipped=1';
				}else if($data['fin_2']==2){
					$sql .= ' and state_2>0 and shipped=1';
				}
				
				$sql .= ' group by product.id, pinfoid';
				
				$plates_name = array(
					'ダイレクト'=>'p1',
					'ゾル'=>'p2',
					'転写'=>'p3',
					'ジャンボ'=>'p4',
					'帽子'=>'p5'
				);
				$rs2 = array('p1'=>0,'p2'=>0,'p3'=>0,'p4'=>0,'p5'=>0);
				
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$key = $plates_name[$res['plates']];
					$rs2[$key] += $res['platescount'];
				}
				*/
				
				$rs = array($rs1, $rs2);
				
				$flg = false;
				break;
				
			case 'silklist':
		 		/****************************
				*	シルク
				*	状況確認、作業予定、印刷、仕事量（分）グラフ
				*	
				*****************************/
				
				// ドライのタグが付いているアイテムを取得
				$result = exe_sql('select * from itemtag where tag_id=2');
				while($rec = mysqli_fetch_assoc($result)){
					$isDry[$rec['tag_itemid']] = true;
				}
				
				// スタッフデータを取得
				$result = exe_sql('select * from staff');
				while($rec = mysqli_fetch_assoc($result)){
					$staffdata[$rec['id']] = $rec['staffname'];
				}
				
				// 全てのプリント方法を対象に抽出
				$sql = 'select * from (orders inner join printstatus on orders.id=printstatus.orders_id) inner join acceptstatus on orders.id=acceptstatus.orders_id
					 where created>"2011-06-05" and progress_id=4';
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				$result = exe_sql($conn, $sql);
				while($rec = mysqli_fetch_assoc($result)){
					$orders[$rec['orders_id']][] = $this->print_codename[$rec['printtype_key']]['name'];
				}
				
				
				$sql = 'select orders.id as id, schedule2, schedule3, company, customername, maintitle, arrival, carriage, noprint,
					package_yes, package_no, package_nopack, factory, ordertype, bundle,
					dateofsilk, state_5, state_5_1, fin_5, fin_1, state_2, state_7, note_silk, note_silk2, staffname,
					exchink_count, ink_count, inkid, ink_code, ink_name, areaid, orderprint.printposition_id as ppid, 
					area_path, selective_key, selective_name, actualwork, 
					orderitem.amount as volume, platesnumber, adjprintcount, print_id, orderitem.id as orderitemid, 
					catalog.item_id as itemid, adjworktime, orderprint.category_id as categoryid, 
					repeater, reuse, repeatdesign, allrepeat, completionimage, coalesce(expressfee,"0") as express,
					coalesce(category.category_name,orderitemext.item_name) as item, 
					coalesce(itemcolor.color_name,orderitemext.item_color) as itemcolor 
					 from ((((((((((((((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 inner join progressstatus on orders.id=progressstatus.orders_id) 
					 left join estimatedetails on orders.id=estimatedetails.orders_id)
					 inner join customer on orders.customer_id=customer.id) 
					 inner join orderprint on orders.id=orderprint.orders_id) 
					 inner join orderarea on orderprint.id=orderprint_id) 
					 inner join orderselectivearea on areaid=orderarea_id) 
					 inner join orderink on areaid=orderink.orderarea_id) 
					 inner join product on orders.id=product.orders_id) 
					 inner join printinfo on product.id=product_id) 
					 inner join orderitem on orderprint.id=print_id) 
					 left join orderitemext on orderitem.id=orderitem_id) 
					 left join category on orderprint.category_id=category.id) 
					 left join catalog on orderitem.master_id=catalog.id) 
					 left join itemcolor on catalog.color_id=itemcolor.id) 
					 left join staff on state_5=staff.id 
					 where created>"2011-06-05" and progress_id=4 and noprint=0 and printinfo.print_posname=selective_name 
					 and orderarea.print_type="silk" and orderarea.ink_count>0 and selectiveid is not null 
					 and printstatus.printtype_key="silk"';
					 
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				/*
				if(!empty($data['schedule_from'])){
					$sql .= ' and dateofsilk >= "'.$data['schedule_from'].'"';
				}
				if(!empty($data['schedule_to'])){
					$sql .= ' and dateofsilk <= "'.$data['schedule_to'].'"';
				}
				*/
				if(!empty($data['factory'])){
					$sql .= ' and orders.factory = '.$data['factory'];
				}
				if(!empty($data['state_5'])){
					$sql .= ' and state_5 = '.$data['state_5'];
				}
				if($data['fin_5']==1){
					$sql .= ' and fin_5=0';
				}else if($data['fin_5']==2){
					$sql .= ' and fin_5=1 and shipped=1';
				}
				/* 仕事量のグラフ用データ抽出
				if(!empty($data['sipping'])){
					$sql .= ' and fin_5=0 and shipped=1';
					$sql .= ' and schedule3 between "'.$data['shipping'].'" and adddate("'.$data['shipping'].'", interval 30 day)';
				}
				*/
				
				$sql .= ' group by orderitem.id, print_id, areaid, inkid order by schedule3, customer.id, orders.id, item, areaid, orderitem.id';
				
				$i = -1;
				$ids = array();
				$result = exe_sql($conn, $sql);
				while($rec = mysqli_fetch_assoc($result)){
					if($rec['id']!=$curid){
						$curid = $rec['id'];
						$ids[] = $rec['id'];
						$curarea = $rec['areaid'];
						$curitemid = array($rec['orderitemid']);
						$a = 1;
						$i++;
						$rs[$i] = $rec;
						$rs[$i]['inkcount1'] = $rec['ink_count'];
						$rs[$i]['inkcount2'] = 0;
						$rs[$i]['inkcount3'] = 0;
						$rs[$i]['inkcount4'] = 0;
						$rs[$i]['suri1'] = $rec['platesnumber'];
						$rs[$i]['suri2'] = 0;
						$rs[$i]['suri3'] = 0;
						$rs[$i]['suri4'] = 0;
						$rs[$i]['ink1'] = $rec['ink_code'].' '.$rec['ink_name'];
						$rs[$i]['ink2'] = '-';
						$rs[$i]['ink3'] = '-';
						$rs[$i]['ink4'] = '-';
						$rs[$i]['pos1'] = $rec['area_path'].' '.$rec['selective_name'].' '.$rec['selective_key'];
						$rs[$i]['pos2'] = '';
						$rs[$i]['pos3'] = '';
						$rs[$i]['pos4'] = '';
						
					}else{
						if($rs[$i]['item']==$rec['item'] && $rs[$i]['print_id']==$rec['print_id']){
							if(!in_array($rec['orderitemid'], $curitemid)){
								$rs[$i]['volume'] += $rec['volume'];
								$curitemid[] = $rec['orderitemid'];
							}
							if($a<5){
								if($curarea==$rec['areaid']){
									//if($rs[$i]['inkid']!=$rec['inkid']){
									if( !preg_match('/'.$rec['ink_code'].'/',$rs[$i]['ink'.$a]) ){
										$rs[$i]['ink'.$a] .= ',  '.$rec['ink_code'].' '.$rec['ink_name'];
									}
									/*
									if($curitemid!=$rec['orderitemid'] && $rs[$i]['areaid']==$rec['areaid']){
										$rs[$i]['volume'] += $rec['volume'];
									}
									*/
									if( !preg_match('/'.$rec['itemcolor'].'/',$rs[$i]['itemcolor']) ){
										$rs[$i]['itemcolor'] .= ',  '.$rec['itemcolor'];
									}
								}else{
									$curarea = $rec['areaid'];
									$a++;
									$rs[$i]['platesnumber'] += $rec['platesnumber'];
									$rs[$i]['inkcount'.$a] = $rec['ink_count'];
									$rs[$i]['suri'.$a] = $rec['platesnumber'];
									$rs[$i]['ink'.$a] = $rec['ink_code'].' '.$rec['ink_name'];
									$rs[$i]['pos'.$a] = $rec['area_path'].' '.$rec['selective_name'].' '.$rec['selective_key'];
								}
							}
							
						}else{
							$curarea = $rec['areaid'];
							$curitemid = array($rec['orderitemid']);
							$a = 1;
							$i++;
							$rs[$i] = $rec;
							$rs[$i]['inkcount1'] = $rec['ink_count'];
							$rs[$i]['inkcount2'] = 0;
							$rs[$i]['inkcount3'] = 0;
							$rs[$i]['inkcount4'] = 0;
							$rs[$i]['suri1'] = $rec['platesnumber'];
							$rs[$i]['suri2'] = 0;
							$rs[$i]['suri3'] = 0;
							$rs[$i]['suri4'] = 0;
							$rs[$i]['ink1'] = $rec['ink_code'].' '.$rec['ink_name'];
							$rs[$i]['ink2'] = '-';
							$rs[$i]['ink3'] = '-';
							$rs[$i]['ink4'] = '-';
							$rs[$i]['pos1'] = $rec['area_path'].' '.$rec['selective_name'].' '.$rec['selective_key'];
							$rs[$i]['pos2'] = '';
							$rs[$i]['pos3'] = '';
							$rs[$i]['pos4'] = '';
						}
					}
				}
				
				// 作業予定日の指定がある場合の絞込み
				$sql = "";
				if(!empty($data['schedule_from'])){
					$sql .= 'select * from workplan where wp_printkey="silk" and scheduled>="'.$data['schedule_from'].'"';
				}
				if(!empty($data['schedule_to'])){
					$sql .= ' and scheduled <= "'.$data['schedule_to'].'"';
				}
				if($sql!=""){
					$tmp = array();
					$sql .= ' and orders_id in ('.implode(',', $ids).')';
					$result = exe_sql($conn, $sql);
					while($rec = mysqli_fetch_assoc($result)){
						$tmp[$rec['orders_id']] = $rec;
					}
					
					$rs3 = array();
					$cnt = count($rs);
					for($i=0; $i<$cnt; $i++){
						if(isset($tmp[$rs[$i]['id']])){
							$rs3[] = $rs[$i];
						}
					}
					$rs = $rs3;
				}
				
				
				// 作業予定日データを取得
				if(!empty($ids)){
					$wp = array();
					$sql = 'select * from workplan left join staff on worker=staff.id where wp_printkey="silk" and orders_id in ('.implode(',', $ids).') order by orders_id, worker, scheduled';
					$result = exe_sql($conn, $sql);
					while($rec = mysqli_fetch_assoc($result)){
						$wp[$rec['orders_id']][] = $rec;
					}
				}
				
				
				/* 作業時間（分）
				*
				*　倍率
				*	Tシャツ:		1
				*	スウェット:		1.3
				*	ポロシャツ:		1
				*	スポーツウェア:	1.3
				*	レディース:		1（廃止カテゴリー）
				*	アウター:		1.5
				*	キャップ:		1.5
				*	タオル:			1
				*	バッグ:			1
				*	エプロン:		1
				*	ワークウェア	1.3
				*	グッズ			1.3
				*	ロングT			1
				*	ベビー			1
				*
				*	パンツ:			1.5
				*	厚手ブルゾン:	2
				*/
				$ratio = array(1, 1, 1.3, 1, 1.3, 1, 1.5, 1.5, 1, 1, 1, 1.3, 1.3, 1, 1);
				$setting = array(0,5,15,20,25);		// 組付け
				$cnt = count($rs);
				for($i=0; $i<$cnt; $i++){
					$wt = 0;
					for($a=1; $a<5; $a++){
						if(empty($rs[$i]['inkcount'.$a])) break;
						$set = $setting[$rs[$i]['inkcount'.$a]];
						$printcount = $a==1? $rs[$i]['suri'.$a]+$rs[$i]['adjprintcount']: $rs[$i]['suri'.$a];
						$suri = $printcount>0? (0.5 + ($printcount*0.5)) * $rs[$i]['volume']: 0;
						$age = 0.3 * $rs[$i]['volume'];
						$tume = 0.2 * $rs[$i]['volume'];
						$irokae = 10 * $rs[$i]['exchink_count'];
						if($rs[$i]['ppid']==8 || $rs[$i]['ppid']==9 || $rs[$i]['ppid']==17){
							$rat = 1.5;	// パンツ
						}else if($rs[$i]['itemid']==159){
							$rat = 2;	// アクティブベンチコート
						}else if($rs[$i]['categoryid']==100){
							$rat = 1;	// 持込の場合、暫定的にTシャツと同じ倍率にする
						}else{
							$rat = $ratio[$rs[$i]['categoryid']];
						}
						$wt += ($set+$suri+$age+$tume+$irokae) * $rat;
					}
					$rs[$i]['capacity'] = round($wt);
				}
				
				$cnt = count($rs);
				if($cnt>0){
					$rs2 = array();
					$tmp = array();
					$i = -1;
					$curid = 0;
					
					for($a=0; $a<$cnt; $a++){
						if($rs[$a]['id']!=$curid){
							if($isDry[$rs[$a]['itemid']]) $rs[$a]['item'] .= '[DRY]';	// ドライ判定
							$curid = $rs[$a]['id'];
							$i++;
							$tmp[$i] = $rs[$a];
							
							// 作業予定データを追加
							$tmp[$i]['wp'] = $wp[$rs[$a]['id']];
							
							// 複合プリントを抽出
							if(isset($orders[$curid])){
								$printname = implode(' ', $orders[$curid]);
								$tmp[$i]['mixedprint'] = trim(str_replace('シルク', '', $printname));
							}
						}else{
							$tmp[$i]['capacity'] += $rs[$a]['capacity'];
							$tmp[$i]['adjworktime'] += $rs[$a]['adjworktime'];
							$tmp[$i]['volume'] += $rs[$a]['volume'];
							if(strpos($tmp[$i]['item'], $rs[$a]['item'])===false){
								if($isDry[$rs[$a]['itemid']]) $rs[$a]['item'] .= '[DRY]';	// ドライ判定
								$tmp[$i]['item'] .= '<br>'.$rs[$a]['item'];
							}
						}
						
						// 仕事量
						if(empty($rs[$a]['staffname'])) $staffname = '未定';
						else $staffname = $rs[$a]['staffname'];
						
						$rs2['worktime'][$staffname] += ($rs[$a]['capacity']+$rs[$a]['adjworktime']);
						$rs2['actualtime'][$staffname] += $rs[$a]['actualwork'];
						
						
						// 作業予定
						if(empty($tmp[$i]['wp'])){
							$staffname2 = '未定';
							$rs2['workplan'][$staffname2] += ($rs[$a]['capacity']+$rs[$a]['adjworktime']);
						}else{
							for($t=0; $t<count($tmp[$i]['wp']); $t++){
								if(empty($tmp[$i]['wp'][$t]['staffname'])) $staffname2 = '未定';
								else $staffname2 = $tmp[$i]['wp'][$t]['staffname'];
								
								$rs2['workplan'][$staffname2] += ($rs[$a]['capacity']+$rs[$a]['adjworktime']);
							}
						}
						
					}
					
					$tmp[0]['worktime'] = $rs2['worktime'];
					$tmp[0]['workplan'] = $rs2['workplan'];
					$tmp[0]['actualtime'] = $rs2['actualtime'];
					$rs = $tmp;
				}
				
				
				// チャートの場合
				if($data['mode']=='chart' && !empty($rs)){
					$tmp = array();
					$max = 0;
					for($i=0; $i<count($rs); $i++){
						$w = ($rs[$i]['capacity']+$rs[$i]['adjworktime']);
						$quota = 0;
						$results = 0;
						
						$tmp[$rs[$i]['schedule3']]['shipping'] += $w;
						if($rs[$i]['volume']>=100) $tmp[$rs[$i]['schedule3']]['on_100'] += $w;
						/*
						if($rs[$i]['package']=='yes') $tmp[$rs[$i]['schedule3']]['on_pack'] += $w;
						if(  ) $tmp[$rs[$i]['schedule3']]['on_compo'] += $w;
						*/
						
						$cnt = count($rs[$i]['wp']);
						if($cnt>0){
							for($j=0; $j<$cnt; $j++){
								if($rs[$i]['wp'][$j]['scheduled']=="0000-00-00") continue;
								if(!empty($rs[$i]['wp'][$j]['quota'])){
									$tmp[ $rs[$i]['wp'][$j]['scheduled'] ]['quota'] += round($w * ($rs[$i]['wp'][$j]['quota']/100));
								}
								if(!empty($rs[$i]['wp'][$j]['results'])){
									$tmp[ $rs[$i]['wp'][$j]['scheduled'] ]['results'] += round($w * ($rs[$i]['wp'][$j]['results']/100));
								}
							}
							$j--;
							$quota = $tmp[ $rs[$i]['wp'][$j]['scheduled'] ]['quota'];
							$results = $tmp[ $rs[$i]['wp'][$j]['workingday'] ]['results'];
						}else{
							if($rs[$i]['dateofsilk']!="0000-00-00"){
								$tmp[ $rs[$i]['dateofsilk'] ]['quota'] += $w;
								$quota = $tmp[ $rs[$i]['dateofsilk'] ]['quota'];
							}
						}
						
						$max = max($max, $tmp[$rs[$i]['schedule3']]['shipping'], $quota, $results);
					}
					
					ksort($tmp);
					$rs = array();
					$rs[] = $max;
					$rs[] = $tmp;
				}
				
				$flg = false;
				break;
				
			case 'translist':
		 		/****************************
				*	転写紙
				*	デジタル転写のみ
				*****************************/
				// 作業時間(wt)の集計（分）
				$sql = 'select orders.id as id, orderitem.id as itemid, item_name, category_id, printtype_key, cleaner, sum(amount) as amount, package_yes 
					 from (((((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 inner join progressstatus on orders.id=progressstatus.orders_id) 
					 inner join orderitem on orders.id=orderitem.orders_id) 
					 left join orderitemext on orderitem.id=orderitem_id) 
					 inner join orderprint on orderitem.print_id=orderprint.id) 
					 inner join orderarea on orderitem.print_id=orderprint_id) 
					 left join orderselectivearea on areaid=orderarea_id 
					 where created>"2011-06-05" and progress_id=4 and noprint=0 
					 and selectiveid is not null 
					 and print_type="digit" and printstatus.printtype_key="digit"';
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				if(!empty($data['factory'])){
					$sql .= ' and orders.factory = '.$data['factory'];
				}
				if($data['fin_3']==1){
					$sql .= ' and fin_3=0';
				}else if($data['fin_3']==2){
					$sql .= ' and fin_3=1 and shipped=1';
				}
				$sql .= ' group by orders.id, areaid order by orders.id, orderitem.id';
				$result = exe_sql($conn, $sql);
				
				$shot = 0;	// ショット数（枚数*プリント個所数）
				$vol = 0;	// 枚数
				while($rec = mysqli_fetch_assoc($result)){
					if($curid!=$rec['id']){
						if($shot!=0){
							$worktime = $time_unit['press'][$category_id][$rec['printtype_key']] * $shot;
							if($package==1) $worktime += $time_unit['pack'][$category_id] * $vol;
							$worktime += $time_unit['prepare'];
							$worktime += $time_unit['finish'];
							$worktime += $time_unit['sheetfor'];
							if($cleaner==1) $worktime += $time_unit['cleaner'];
							$sub[$curid]['wt'] = round($worktime/60);	// 単位を分に変換
						}
						$curid = $rec['id'];
						$shot = 0;
						$vol = 0;
						$package = $rec['package_yes'];
						$category_id = $rec['category_id'];
						$cleaner = $rec['cleaner'];
						$sub[$curid]['sheet'] = $rec['item_name']=='転写シート'? 1: 0;
					}
					$key = $rec['id'].'_'.$rec['itemid'];
					if($pre_item!=$key){
						$pre_item = $key;
						$vol += $rec['amount'];
					}
					$shot += $rec['amount'];
				}
				$worktime = $time_unit['press'][$category_id][$rec['printtype_key']] * $shot;
				if($package==1) $worktime += $time_unit['pack'][$category_id] * $vol;
				$worktime += $time_unit['prepare'];
				$worktime += $time_unit['finish'];
				$worktime += $time_unit['sheetfor'];
				if($cleaner==1) $worktime += $time_unit['cleaner'];
				$sub[$curid]['wt'] = round($worktime/60);	// 単位を分に変換
				
				// 集計
				$sql = 'select orders.id as id, schedule2, schedule3, company, customername, maintitle, factory, ordertype, bundle, 
					dateoftrans, state_3, fin_3, state_2, note_trans, edge, cleaner, adjtime_trans as adjtime, 
					coalesce( sum(sheets), 0) as sheet, printtype_key, state_prepress,
					repeater, reuse, repeatdesign, allrepeat, completionimage, coalesce(expressfee,"0") as express
					 from (((((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 inner join progressstatus on orders.id=progressstatus.orders_id) 
					 left join estimatedetails on orders.id=estimatedetails.orders_id)
					 inner join customer on orders.customer_id=customer.id) 
					 inner join product on orders.id=product.orders_id) 
					 inner join printtype on printstatus.printtype_key=print_key) 
					 left join cutpattern on product.id=cutpattern.product_id
					 where created>"2011-06-05" and progress_id=4 and noprint=0 
					 and product.printtype=printtype.printtypeid';
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				if(!empty($data['factory'])){
					$sql .= ' and orders.factory = '.$data['factory'];
				}
				if($data['fin_3']==1){
					$sql .= ' and fin_3=0';
				}else if($data['fin_3']==2){
					$sql .= ' and fin_3=1 and shipped=1';
				}
				
				$sql .= ' group by orders.id, printstatus.printtype_key order by schedule3, orders.id';
				
				$rs = array();
				$result = exe_sql($conn, $sql);
				$curid = 0;
				while($rec = mysqli_fetch_assoc($result)){
					if(!isset($sub[$rec['id']])) continue;
					if($rec['id']!=$curid){
						$curid = $rec['id'];
						
						if(isset($tmp['digit'])){
							$tmp['digit']['mixedprint'] = trim(str_replace('デジ ', '', $printname));
							$rs[] = $tmp['digit'];
						}
						$tmp = array();
						$printname = '';
					}
					
					$rec['wt'] = $sub[$curid]['wt'];
					$rec['sheetonly'] = $sub[$curid]['sheet'];
					$tmp[$rec['printtype_key']] = $rec;
					$printname .= $this->print_codename[$rec['printtype_key']]['name'].' ';
				}
				if(isset($tmp['digit'])){
					$tmp['digit']['mixedprint'] = trim(str_replace('デジ ', '', $printname));
					$rs[] = $tmp['digit'];
				}
				
				$flg = false;
				break;
				
			case 'presslist':
		 		/****************************
				*	プレス
				*	デジタル転写、カラー転写、カッティング
				*	転写紙（デジタル転写）の未終了とシートのみを除外
				*****************************/
				// 作業時間の集計（分）
				$sql = 'select orders.id as id, orderitem.id as itemid, category_id, printtype_key, cleaner, sum(amount) as amount, package_yes from 
					 (((((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 inner join progressstatus on orders.id=progressstatus.orders_id) 
					 inner join orderitem on orders.id=orderitem.orders_id) 
					 left join orderitemext on orderitem.id=orderitem_id)
					 inner join orderprint on orderitem.print_id=orderprint.id) 
					 inner join orderarea on orderitem.print_id=orderprint_id) 
					 left join orderselectivearea on areaid=orderarea_id 
					 where created>"2011-06-05" and progress_id=4 and noprint=0 
					 and selectiveid is not null 
					 and (orderitemext.item_id is null or orderitemext.item_id!=99999) 
					 and ((print_type="digit" and printstatus.printtype_key="digit" and fin_3=1) 
					 or (print_type="trans" and printstatus.printtype_key="trans") 
					 or (print_type="cutting" and printstatus.printtype_key="cutting"))';
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				if(!empty($data['print_type'])){
					$sql .= ' and printstatus.printtype_key = "'.$data['print_type'].'"';
				}
				if(!empty($data['factory'])){
					$sql .= ' and orders.factory = '.$data['factory'];
				}
				if($data['fin_4']==1){
					$sql .= ' and fin_4=0';
				}else if($data['fin_4']==2){
					$sql .= ' and fin_4=1 and shipped=1';
				}
				
				$sql .= ' group by orders.id, areaid order by orders.id, printstatus.printtype_key, orderitem.id';
				$result = exe_sql($conn, $sql);
				
				$shot = 0;	// ショット数（枚数*プリント個所数）
				$vol = 0;	// 枚数
				while($rec = mysqli_fetch_assoc($result)){
					if($curid!=$rec['id'] || $curprint!=$rec['printtype_key']){
						if($shot!=0){
							$worktime = $time_unit['press'][$category_id][$rec['printtype_key']] * $shot;
							if($package==1) $worktime += $time_unit['pack'][$category_id] * $vol;
							$worktime += $time_unit['prepare'];
							$worktime += $time_unit['finish'];
							$worktime += $time_unit['sheetfor'];
							if($cleaner==1) $worktime += $time_unit['cleaner'];
							$wt[$curid][$curprint] = round($worktime/60);	// 単位を分に変換
						}
						$curid = $rec['id'];
						$curprint = $rec['printtype_key'];
						$shot = 0;
						$vol = 0;
						$package = $rec['package_yes'];
						$category_id = $rec['category_id'];
						$cleaner = $rec['cleaner'];
					}
					$key = $rec['id'].'_'.$rec['itemid'];
					if($pre_item!=$key){
						$pre_item = $key;
						$vol += $rec['amount'];
					}
					$shot += $rec['amount'];
				}
				$worktime = $time_unit['press'][$category_id][$rec['printtype_key']] * $shot;
				if($package==1) $worktime += $time_unit['pack'][$category_id] * $vol;
				$worktime += $time_unit['prepare'];
				$worktime += $time_unit['finish'];
				$worktime += $time_unit['sheetfor'];
				if($cleaner==1) $worktime += $time_unit['cleaner'];
				$wt[$curid][$curprint] = round($worktime/60);	// 単位を分に変換
				
				// 集計
				$sql = 'select orders.id as id, schedule2, schedule3, company, customername, maintitle, arrival, carriage, noprint, factory, ordertype, bundle, 
					dateofpress, dateoftrans, state_4, fin_4, fin_1, state_2, fin_3, state_7, note_press, 
					package_yes, package_no, package_nopack, areaid, 
					sum(orderitem.amount) as volume, printtype_key, adjtime_press as adjtime, 
					coalesce(category.category_name,orderitemext.item_name) as item,
					repeater, reuse, repeatdesign, allrepeat, completionimage, coalesce(expressfee,"0") as express
					 from ((((((((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 inner join progressstatus on orders.id=progressstatus.orders_id) 
					 left join estimatedetails on orders.id=estimatedetails.orders_id) 
					 inner join customer on orders.customer_id=customer.id) 
					 inner join orderprint on orders.id=orderprint.orders_id) 
					 inner join orderarea on orderprint.id=orderprint_id) 
					 inner join orderselectivearea on areaid=orderarea_id) 
					 inner join orderitem on orderprint.id=print_id) 
					 left join orderitemext on orderitem.id=orderitem_id) 
					 left join category on orderprint.category_id=category.id  
					 where created>"2011-06-05" and progress_id=4 and noprint=0 
					 and printstatus.printtype_key=orderarea.print_type
					 and selectiveid is not null';
					 
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				if(!empty($data['print_type'])){
					$sql .= ' and printstatus.printtype_key = "'.$data['print_type'].'"';
				}
				if($data['fin_4']==1){
					$sql .= ' and fin_4=0';
				}else if($data['fin_4']==2){
					$sql .= ' and fin_4=1 and shipped=1';
				}
				
				$sql .= ' group by orders.id,printstatus.printtype_key,item,areaid order by schedule3, orders.id, printstatus.printtype_key, areaid';
				
				$i = -1;
				$result = exe_sql($conn, $sql);
				$curid = 0;
				while($rec = mysqli_fetch_assoc($result)){
					if(!isset($wt[$rec['id']])) continue;
					if($rec['id']!=$curid || $rs1[$i]['printname']!=$this->print_codename[$rec['printtype_key']]['name']){
						$curid = $rec['id'];
						if($i>-1){
							$rs1[$i]['shot'] += $rs1[$i]['vol_item'] * $rs1[$i]['area_item'];
						}
						$i++;
						$rs1[$i] = $rec;
						$rs1[$i]['area'] = 1;
						$rs1[$i]['printname'] = $this->print_codename[$rec['printtype_key']]['name'];
						
						$rs1[$i]['area_item'] = 1;
						$rs1[$i]['vol_item'] = $rec['volume'];
						$rs1[$i]['shot'] = 0;
					}else{						
						if($rs1[$i]['item']==$rec['item']){
							$rs1[$i]['area'] += 1;
							$rs1[$i]['area_item'] += 1;
						}else{
							$rs1[$i]['shot'] += $rs1[$i]['vol_item'] * $rs1[$i]['area_item'];
							$rs1[$i]['area_item'] = 1;
							$rs1[$i]['vol_item'] = $rec['volume'];
							
							$rs1[$i]['area'] += 1;
							
							
							if( !preg_match('/'.$rec['item'].'/',$rs1[$i]['item']) ){
								$rs1[$i]['item'] .= ', '.$rec['item'];
								$rs1[$i]['volume'] += $rec['volume'];
							}
						}
					}
				}
				if($i>-1) $rs1[$i]['shot'] += $rs1[$i]['vol_item'] * $rs1[$i]['area_item'];
				
				// 混合プリントの集計
				$curid = 0;
				$rs = array();
				$cnt = count($rs1);
				for($i=0; $i<$cnt; $i++){
					if($rs1[$i]['id']!=$curid){
						
						if($i!=0){
							foreach($tmp as $key=>$val){
								if(!($key=='digit' || $key=='trans' || $key=='cutting')) continue;
								if(!isset($wt[$curid][$key])) continue;
								$tmp[$key]['mixedprint'] = trim(str_replace($this->print_codename[$key]['name'].' ', '', $printname));
								$rs[] = $tmp[$key];
							}
						}
						
						$curid = $rs1[$i]['id'];
						$tmp = array();
						$printname = '';
					}
					$rs1[$i]['wt'] = $wt[$curid][$rs1[$i]['printtype_key']];
					$tmp[$rs1[$i]['printtype_key']] = $rs1[$i];
					$printname .= $rs1[$i]['printname'].' ';
				}
				if($i!=0){
					foreach($tmp as $key=>$val){
						if(!($key=='digit' || $key=='trans' || $key=='cutting')) continue;
						if(!isset($wt[$curid][$key])) continue;
						$tmp[$key]['mixedprint'] = trim(str_replace($this->print_codename[$key]['name'].' ', '', $printname));
						$rs[] = $tmp[$key];
					}
				}
				
				$flg = false;
				break;
				
			case 'translist_old':
		 		/****************************
				*	転写紙	2013-09-17 廃止
				*	デジタル転写のみ
				*****************************/
				$sql = 'select orders.id as id, schedule2, schedule3, company, customername, maintitle,
					dateoftrans, state_3, fin_3, state_2, note_trans, edge, 
					coalesce( sum(sheets), sheetcount) as sheet 
					 from (((((((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 inner join progressstatus on orders.id=progressstatus.orders_id) 
					 inner join customer on orders.customer_id=customer.id) 
					 inner join orderprint on orders.id=orderprint.orders_id) 
					 inner join orderarea on orderprint.id=orderprint_id) 
					 inner join orderselectivearea on areaid=orderarea_id) 
					 inner join product on orders.id=product.orders_id) 
					 inner join printtype on printstatus.printtype_key=print_key) 
					 left join cutpattern on product.id=cutpattern.product_id
					 where created>"2011-06-05" and progress_id=4 and noprint=0 
					 and orderarea.print_type="digit" and product.printtype=3 and selectiveid is not null
					 and product.printtype=printtype.printtypeid';
					 
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule2 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule2 <= "'.$data['term_to'].'"';
				}
				if($data['fin_3']==1){
					$sql .= ' and fin_3=0 and shipped=1';
				}else if($data['fin_3']==2){
					$sql .= ' and fin_3=1 and shipped=1';
				}
				
				$sql .= ' group by orders.id order by schedule3, customer.id, orders.id';
				
				break;
				
			case 'presslist_old':
		 		/****************************
				*	プレス	2013-09-17 廃止
				*	デジタル転写、カラー転写、カッティング
				*****************************/
				$sql = 'select orders.id as id, schedule2, schedule3, company, customername, maintitle, arrival,
					dateofpress, dateoftrans, state_4, fin_4, fin_1, state_2, fin_3, state_7, note_press, package, areaid, 
					sum(orderitem.amount) as volume, print_type,
					coalesce(category.category_name,orderitemext.item_name) as item 
					 from (((((((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 inner join progressstatus on orders.id=progressstatus.orders_id)  
					 inner join customer on orders.customer_id=customer.id) 
					 inner join orderprint on orders.id=orderprint.orders_id) 
					 inner join orderarea on orderprint.id=orderprint_id) 
					 inner join orderselectivearea on areaid=orderarea_id) 
					 inner join orderitem on orderprint.id=print_id) 
					 left join orderitemext on orderitem.id=orderitem_id) 
					 left join category on orderprint.category_id=category.id  
					 where created>"2011-06-05" and progress_id=4 and noprint=0 
					 and (orderarea.print_type="digit" or orderarea.print_type="trans" or orderarea.print_type="cutting") 
					 and printstatus.printtype_key=orderarea.print_type
					 and selectiveid is not null';
					 
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule2 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule2 <= "'.$data['term_to'].'"';
				}
				if(!empty($data['print_type'])){
					$sql .= ' and print_type = "'.$data['print_type'].'"';
				}
				if($data['fin_4']==1){
					$sql .= ' and fin_4=0 and shipped=1';
				}else if($data['fin_4']==2){
					$sql .= ' and fin_4=1 and shipped=1';
				}
				
				$sql .= ' group by orders.id,print_type,item,areaid order by schedule3, customer.id, orders.id, print_type, areaid';
				
				$prn = array(
					'digit'=>'デジタル転写',
					'trans'=>'カラー転写',
					'cutting'=>'カッティング'
					);
				$i = -1;
				$result = exe_sql($conn, $sql);
				while($rec = mysqli_fetch_assoc($result)){
					if($rec['state_2']>0) $rec['state_2']=1;	// 製版
					if($rec['state_7']>0) $rec['state_7']=1;	// 入荷
					
					if($rec['id']!=$curid || $rs[$i]['printname']!=$prn[$rec['print_type']]){
						$curid = $rec['id'];
						$curarea = $rec['areaid'];
						$i++;
						$rs[$i] = $rec;
						$rs[$i]['area'] = 1;
						$rs[$i]['printname'] = $prn[$rec['print_type']];
					}else{
						if($rs[$i]['item']==$rec['item']){
							$rs[$i]['area'] += 1;
							/*
							if( !preg_match('/'.$prn[$rec['print_type']].'/',$rs[$i]['printname']) ){
								$rs[$i]['printname'] .= '<br>'.$prn[$rec['print_type']];
							}
							*/
						}else{
							$curarea = $rec['areaid'];
							$i++;
							$rs[$i] = $rec;
							$rs[$i]['area'] = 1;
							$rs[$i]['printname'] = $prn[$rec['print_type']];
						}
					}
				}
				
				$flg = false;
				break;
				
			case 'inkjetlist':
		 		/****************************
				*	インクジェット
				*****************************/
				// 全てのプリント方法を対象に抽出
				$sql = 'select * from (orders inner join printstatus on orders.id=printstatus.orders_id) inner join acceptstatus on orders.id=acceptstatus.orders_id
					 where created>"2011-06-05" and progress_id=4';
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				$result = exe_sql($conn, $sql);
				while($rec = mysqli_fetch_assoc($result)){
					$orders[$rec['orders_id']][] = $this->print_codename[$rec['printtype_key']]['name'];
				}
				
				// 集計
				$sql = 'select orders.id as id, schedule2, schedule3, company, customername, maintitle, arrival, carriage, noprint, factory, ordertype, bundle, 
					dateofinkjet, dateofartwork, state_6, fin_6, fin_1, note_inkjet, package_yes, package_no, package_nopack, areaid, print_option, 
					sum(orderitem.amount) as volume,
					coalesce(category.category_name,orderitemext.item_name) as item,
					repeater, reuse, repeatdesign, allrepeat, completionimage, coalesce(expressfee,"0") as express 
					 from ((((((((((orders 
					 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
					 inner join printstatus on orders.id=printstatus.orders_id) 
					 inner join progressstatus on orders.id=progressstatus.orders_id) 
					 left join estimatedetails on orders.id=estimatedetails.orders_id)
					 inner join customer on orders.customer_id=customer.id) 
					 inner join orderprint on orders.id=orderprint.orders_id) 
					 inner join orderarea on orderprint.id=orderprint_id) 
					 inner join orderselectivearea on areaid=orderarea_id) 
					 inner join orderitem on orderprint.id=print_id) 
					 left join orderitemext on orderitem.id=orderitem_id) 
					 left join category on orderprint.category_id=category.id  
					 where created>"2011-06-05" and progress_id=4 and noprint=0 
					 and orderarea.print_type="inkjet" 
					 and selectiveid is not null';
					 
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				if(!empty($data['factory'])){
					$sql .= ' and orders.factory = '.$data['factory'];
				}
				if($data['fin_6']==1){
					$sql .= ' and fin_6=0';
				}else if($data['fin_6']==2){
					$sql .= ' and fin_6=1 and shipped=1';
				}
				
				$sql .= ' group by orders.id,item,areaid order by schedule3, customer.id, orders.id, areaid';
				
				$i = -1;
				$result = exe_sql($conn, $sql);
				while($rec = mysqli_fetch_assoc($result)){
					if($rec['id']!=$curid){
						$curid = $rec['id'];
						$curarea = $rec['areaid'];
						
						// 複合プリントを抽出
						if(isset($orders[$curid])){
							$printname = implode(' ', $orders[$curid]);
							$rec['mixedprint'] = trim(str_replace('IJ', '', $printname));
						}
							
						$i++;
						$rs[$i] = $rec;
						$rs[$i]['area'] = 1;
					}else{
						if($rs[$i]['item']==$rec['item']){
							$rs[$i]['area'] += 1;
						}else{
							$curarea = $rec['areaid'];
							$i++;
							$rs[$i] = $rec;
							$rs[$i]['area'] = 1;
						}
					}
				}
				
				$flg = false;
				break;
				
			case 'shippinglist':
				/*****************************
				*	発送確認画面の一覧
				*	発送予定一覧の印刷
				*/
				$sql = 'SELECT *, orders.id as ordersid FROM (((((((((orders
					 LEFT JOIN customer ON orders.customer_id=customer.id)
					 LEFT JOIN delivery ON orders.delivery_id=delivery.id)
					 LEFT JOIN progressstatus ON orders.id=progressstatus.orders_id)
					 LEFT JOIN acceptstatus ON orders.id=acceptstatus.orders_id)
					 LEFT JOIN acceptprog ON acceptstatus.progress_id=acceptprog.aproid)
					 LEFT JOIN printstatus ON orders.id=printstatus.orders_id)
					 LEFT JOIN orderitem ON orders.id=orderitem.orders_id)
					 LEFT JOIN orderitemext ON orderitem.id=orderitemext.orderitem_id)
					 LEFT JOIN catalog ON master_id=catalog.id)
					 LEFT JOIN category ON catalog.category_id=category.id';
					 
				$sql .= ' WHERE created>"2011-06-05" and progress_id = 4';
				
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				if(!empty($data['carriage'])){
					$sql .= ' and carriage = "'.$data['carriage'].'"';
				}
				if($data['readytoship']!=""){
					$sql .= ' and readytoship = "'.$data['readytoship'].'"';
				}
				if(!empty($data['shipped'])){
					$sql .= ' and shipped = '.$data['shipped'];
				}
				if(!empty($data['pack'])){
					$sql .= ' and package_yes = 1';
				}
				if(!empty($data['factory'])){
					$sql .= ' and orders.factory = '.$data['factory'];
				}
				
				$sql .= ' order by schedule3, customer.id, bundle desc, carriage';
				$result = exe_sql($conn, $sql);
				$r=-1;
				while($rec = mysqli_fetch_assoc($result)){
					// 商品名の集計
					if($rec['master_id']==0){
						$category_name = $rec['item_name'];	// 持ち込み、その他、転写シート;
					}else{
						$category_name = $rec['category_name'];
					}
					
					// プリント方法の名称を短縮
					$printname = $this->print_codename[$rec['printtype_key']]['name'];
					if(empty($printname)) $printname = 'プリントなし';
					
					if($rs[$r]['ordersid']!=$rec['ordersid']){
						// 前回発送日を取得
						$param = array( 'recent'=>1,
										'customer_id'=>$rec['customer_id'],
										'schedule3'=>$rec['schedule3'],
										'organization'=>$rec['organization']
										);
						$tmp = $this->search($conn, 'delivery', $param);
						if(count($tmp)>0){
							$rec['recent'] = $tmp[0]['schedule3'];
						}else{
							$rec['recent'] = '-';
						}
						$r++;
						$rs[$r] = $rec;
						$rs[$r]['category_name'] = $category_name;
						$rs[$r]['print_name'] = $printname;
					}else{
						if(strpos($rs[$r]['category_name'], $category_name)===false){
							$rs[$r]['category_name'] .= "<br>".$category_name;
						}
						if(strpos($rs[$r]['print_name'], $printname)===false){
							$rs[$r]['print_name'] .= "<br>".$printname;
						}
					}
				}
				
				$flg = false;
				break;
				
			case 'addup':
		 		/****************************
		 		*	年度集計
				*	- シルク、プレス、インクジェットの作業をおこなった商品数
				*	- 転写紙の作業をおこなったシート数
				*****************************/
				if(empty($data['FY']) || $data['FY']<2011) return;
				$rs = array();
				for($i=0; $i<13; $i++){
					$rs['total'][$i] = 0;
				}
				
				$startdate = '"'.$data['FY'].'-04-01"';
			 	$enddate = '"'.($data['FY']+1).'-03-31"';
				
				if($data['printtype']=='digit'){
				// シート数（転写紙）
					$sql = 'select date_format(schedule3, "%c") as month, coalesce( sum(sheets), 0) as sheet, staffname 
						 from ((((orders 
						 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
						 inner join printstatus on orders.id=printstatus.orders_id) 
						 left join staff on '.$data['state'].'=staff.id) 
						 inner join product on orders.id=product.orders_id) 
						 left join cutpattern on product.id=cutpattern.product_id 
						 where created>"2011-06-05" and progress_id=4 
						 and printtype_key="digit" and product.printtype=3 
						 and schedule3 between '.$startdate.' and '.$enddate;
					$sql .= ' GROUP BY orders.id';
					
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$staff = empty($res['staffname'])? '-': $res['staffname'];
						if(!isset($rs[$staff])){
							for($i=0; $i<13; $i++){
								$rs[$staff][$i] = 0;
							}
						}
						$rs[$staff][$res['month']] += $res['sheet'];
						$rs['total'][$res['month']] += $res['sheet'];
					}
				}else if($data['printtype']=='press'){
				// 商品数（プレス）
					$sql = 'select date_format(schedule3, "%c") as month, order_amount, staffname 
						 from ((((orders 
						 inner join orderitem on orders.id=orderitem.orders_id)
						 left join orderitemext on orderitem.id=orderitem_id)
						 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
						 inner join printstatus on orders.id=printstatus.orders_id) 
						 left join staff on '.$data['state'].'=staff.id 
						 where created>"2011-06-05" and progress_id=4 and estimated!=0 and fin_4=1 
						 and (printtype_key="digit" or printtype_key="trans" or printtype_key="cutting") 
						 and (orderitemext.item_id is null or orderitemext.item_id!=99999) 
						 and schedule3 between '.$startdate.' and '.$enddate;
					$sql .= ' GROUP BY orders.id ORDER BY rowid6';
					
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$staff = empty($res['staffname'])? '-': $res['staffname'];
						if(!isset($rs[$staff])){
							for($i=0; $i<13; $i++){
								$rs[$staff][$i] = 0;
							}
						}
						$rs[$staff][$res['month']] += $res['order_amount'];
						$rs['total'][$res['month']] += $res['order_amount'];
					}
				}else if($data['printtype']=='inkjet'){
				// 商品数（インクジェット）
					$sql = 'select date_format(schedule3, "%c") as month, order_amount, staffname 
						 from ((orders 
						 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
						 inner join printstatus on orders.id=printstatus.orders_id) 
						 left join staff on '.$data['state'].'=staff.id 
						 where created>"2011-06-05" and progress_id=4 and estimated!=0 
						 and printtype_key="'.$data['printtype'].'" and schedule3 between '.$startdate.' and '.$enddate;
					$sql .= ' GROUP BY orders.id ORDER BY rowid6';
					
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$staff = empty($res['staffname'])? '-': $res['staffname'];
						if(!isset($rs[$staff])){
							for($i=0; $i<13; $i++){
								$rs[$staff][$i] = 0;
							}
						}
						$rs[$staff][$res['month']] += $res['order_amount'];
						$rs['total'][$res['month']] += $res['order_amount'];
					}
				}else if($data['printtype']=='silk'){
				/*
				*	商品数（シルク）
				*	- 作業が途中の場合は実績（％）で案分
				*/
					$sql = 'select orders.id as orderid, date_format(schedule3, "%c") as month, scheduled, quota, order_amount, staffname, rowid6, fin_5 
						 from (((orders 
						 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
						 inner join printstatus on orders.id=printstatus.orders_id) 
						 inner join workplan on orders.id=workplan.orders_id)
						 left join staff on '.$data['state'].'=staff.id 
						 where created>"2011-06-05" and progress_id=4 and estimated!=0 
						 and printtype_key="'.$data['printtype'].'" and schedule3 between '.$startdate.' and '.$enddate;
					$sql .= ' GROUP BY orders.id, wpid ORDER BY orders.id, worker, scheduled';
					
					$result = exe_sql($conn, $sql);
					while($rec = mysqli_fetch_assoc($result)){
						$staff = empty($rec['staffname'])? '-': $rec['staffname'];
						if(!isset($rs[$staff])){
							for($i=0; $i<13; $i++){
								$rs[$staff][$i] = 0;
							}
						}
						
						if($rec['orderid']!=$orderid){
							if(!empty($orderid) && !empty($worker)){
							// 作業が終了している注文の場合
								$rs['total'][$mm] += $worker[$pre_staff];	// その日の合計
								
								foreach($worker as $key=>$val){
									$rs[$key][$mm] += round($val / $cnt);	// 担当者ごとに商品枚数を案分
								}
							}
							
						// 変数を初期化
							$cnt = 1;
							$worker = array();
							$mm = $rec['month'];
							$orderid = $rec['orderid'];
							
						}else if($staff!=$pre_staff){
							$cnt++;	// 複数の担当者がいる場合
						}
						
						if($rec['fin_5']==0){
						// 未終了は実績(%)から枚数を算出
							$volume = 0;
							if($rec['results']>0){
								$volume = round( $rec['order_amount'] * ($rec['results']/100) );
							}
							
							$rs[$staff][$mm] += $vollume;
							$rs['total'][$mm] += $vollume;
							
						}else if(empty($worker[$staff])){
						/* 
						*	終了は注文数すべて
						*	同じスタッフが2日以上にわたって作業していても1回だけカウント
						*/
							$worker[$staff] = $rec['order_amount'];
						}
						
						$pre_staff = $staff;
						
					}
				}
				
				$flg = false;
				break;
				
			case 'daily':
		 		/****************************
		 		*	日計
				*	- シルク：担当者別で日毎の作業をおこなった商品数を月間集計
				*				作業が途中の場合は実績（％）で案分
				*****************************/
				if(empty($data['FY']) || $data['FY']<2011) return;
				
				$lastday = date('t', mktime(0, 0, 0, $data['monthly'], 1, $data['FY']));
				$startdate = '"'.$data['FY'].'-'.$data['monthly'].'-01"';
			 	$enddate = '"'.$data['FY'].'-'.$data['monthly'].'-'.$lastday.'"';
				
				$rs = array();
				for($i=0; $i<$lastday; $i++){
					$rs['total'][$i] = 0;
				}
				
				if($data['printtype']=='silk'){
					$sql = 'select orders.id as orderid, date_format(schedule3, "%d") as dd, scheduled, quota, order_amount, staffname, rowid6, fin_5 
						 from (((orders 
						 inner join acceptstatus on orders.id=acceptstatus.orders_id) 
						 inner join printstatus on orders.id=printstatus.orders_id) 
						 inner join workplan on orders.id=workplan.orders_id)
						 left join staff on workplan.worker=staff.id 
						 where created>"2011-06-05" and progress_id=4 and estimated!=0 
						 and printtype_key="'.$data['printtype'].'" and schedule3 between '.$startdate.' and '.$enddate;
					$sql .= ' GROUP BY orders.id, wpid ORDER BY orders.id, worker, scheduled';
					
					$result = exe_sql($conn, $sql);
					$orderid = null;
					while($rec = mysqli_fetch_assoc($result)){
						$staff = empty($rec['staffname'])? '-': $rec['staffname'];
						if(!isset($rs[$staff])){
						// 当該月の日数分の配列を作成
							for($i=0; $i<$lastday; $i++){
								$rs[$staff][$i] = 0;
							}
						}
						
						if($rec['orderid']!=$orderid){
							if(!empty($orderid) && !empty($worker)){
							// 作業が終了している注文の場合
								$rs['total'][$dd] += $worker[$pre_staff];	// その日の合計
								
								foreach($worker as $key=>$val){
									$rs[$key][$dd] += round($val / $cnt);	// 担当者ごとに商品枚数を案分
								}
							}
							
							// 変数を初期化
							$cnt = 1;
							$worker = array();
							$dd = intval($rec['dd'], 10);
							$orderid = $rec['orderid'];
							
						}else if($staff!=$pre_staff){
							$cnt++;	// 複数の担当者がいる場合
						}
						
						if($rec['fin_5']==0){
						// 未終了は実績(%)から枚数を算出
							$volume = 0;
							if($rec['results']>0){
								$volume = round( $rec['order_amount'] * ($rec['results']/100) );
							}
							
							$rs[$staff][$dd] += $vollume;
							$rs['total'][$dd] += $vollume;
							
						}else if(empty($worker[$staff])){
						/* 
						*	終了は注文数すべて
						*	同じスタッフが2日以上にわたって作業していても1回だけカウント
						*/
							$worker[$staff] = $rec['order_amount'];
						}
						
						$pre_staff = $staff;
						
					}
				}
				
				$flg = false;
				break;
				
			case 'order':
				$sql = 'SELECT * FROM orders WHERE';
				$flg = false;
				if(!empty($data['id'])){
					$sql .= ' id = '.$data['id'];
					$flg = true;
				}
				if(!empty($data['created'])){
					if($flg) $sql .= ' and';
					$sql .= ' created = "'.$data['created'].'"';
					$flg = true;
				}
				if(!empty($data['lastmodified'])){
					if($flg) $sql .= ' and';
					$sql .= ' lastmodified = "'.$data['lastmodified'].'"';
					$flg = true;
				}
				break;
				
			case 'reuse':
			/*
			*	当該受注No.からのリピート版の確定注文を取得し初回割適用の判別に使用
			*/
				$sql = sprintf('SELECT * FROM orders inner join acceptstatus on orders.id=orders_id WHERE progress_id=4 and (repeater=%d || (orders.id=%d && reuse!=0))', $data['id'], $data['id']);
				//$sql = sprintf('SELECT * FROM orders inner join acceptstatus on orders.id=orders_id WHERE progress_id=4 and repeater=%d', $data['id']);
				break;
				
			case 'dedupe':			// deduplication
				$flg = false;
				if(isset($data['customer'])){
					$sql = 'SELECT * FROM customer';
					if(!empty($data['customer_id'])){
						$sql .= ' WHERE id!='.$data['customer_id'];
						$flg = true;
					}
					if(!empty($data['company'])){
						$sql .= $flg? ' and': ' WHERE';
						$sql .= ' (company LIKE "%'.$data['company'].'%" or company="")';
						$flg = true;
						$tmp[] = 'company';
					}
					if(!empty($data['customername'])){
						$sql .= $flg? ' and': ' WHERE';
						
						$zenkaku_space = mb_convert_kana($data['customername'],"S", 'utf-8');
						$hankaku_space = mb_convert_kana($data['customername'],"s", 'utf-8');
						
						$sql .= ' (customername LIKE "%'.$data['customername'].'%"';
						$sql .= ' or customername LIKE "%'.$zenkaku_space.'%"';
						$sql .= ' or customername LIKE "%'.$hankaku_space.'%")';
					
						$flg = true;
						$tmp[] = 'customername';
					}
					if($data['tel']!=""){
						$sql .= $flg? ' and': ' WHERE';
						$sql .= ' (tel LIKE "%'.$data['tel'].'%" or tel="")';
						$flg = true;
						$tmp[] = 'tel';
					}
					if($data['mobile']!=""){
						$sql .= $flg? ' and': ' WHERE';
						$sql .= ' (mobile LIKE "%'.$data['mobile'].'%" or mobile="")';
						$flg = true;
						$tmp[] = 'mobile';
					}
					if(!empty($data['email'])){
						$sql .= $flg? ' and': ' WHERE';
						$sql .= ' (email LIKE "%'.$data['email'].'%" or email="")';
						$flg = true;
						$tmp[] = 'email';
					}
					if($flg){
						$sql .= ' and ('.$tmp[0].'<>""';
						for($i=1;$i<count($tmp);$i++){
							$sql .= ' or '.$tmp[$i].'<>""';
						}
						$sql .= ')';
					}
				}else if(isset($data['delivery'], $data['organization'])){
					$flg = true;
					$sql = 'SELECT * FROM orders inner join delivery on orders.delivery_id=delivery.id where';
					$sql .= ' organization="'.$data['organization'].'"';
					
					if($data['deliaddr0']!=""){
						$sql .= ' and deliaddr0="'.$data['deliaddr0'].'"';
					}
					if($data['deliaddr1']!=""){
						$sql .= ' and deliaddr1="'.$data['deliaddr1'].'"';
					}
					if($data['deliaddr2']!=""){
						$sql .= ' and deliaddr2="'.$data['deliaddr2'].'"';
					}
					if($data['deliaddr3']!=""){
						$sql .= ' and deliaddr3="'.$data['deliaddr3'].'"';
					}
					if($data['deliaddr4']!=""){
						$sql .= ' and deliaddr4="'.$data['deliaddr4'].'"';
					}
					
					/*
					if(!empty($data['organization'])){
						$zenkaku_space = mb_convert_kana($data['organization'],"S", 'utf-8');
						$hankaku_space = mb_convert_kana($data['organization'],"s", 'utf-8');
						
						$sql .= ' (organization LIKE "%'.$data['organization'].'%"';
						$sql .= ' or organization LIKE "%'.$zenkaku_space.'%"';
						$sql .= ' or organization LIKE "%'.$hankaku_space.'%")';

						$tmp[] = 'organization';
					}
					if($data['deliaddr0']!=""){
						$sql .= ' and';
						$sql .= ' (deliaddr0 LIKE "%'.$data['deliaddr0'].'%" or deliaddr0="")';
						$tmp[] = 'deliaddr0';
					}
					if($data['deliaddr1']!=""){
						$sql .= ' and';
						$sql .= ' (deliaddr1 LIKE "%'.$data['deliaddr1'].'%" or deliaddr1="")';
						$tmp[] = 'deliaddr1';
					}
					if($data['deliaddr2']!=""){
						$sql .= ' and';
						$sql .= ' (deliaddr2 LIKE "%'.$data['deliaddr2'].'%" or deliaddr2="")';
						$tmp[] = 'deliaddr2';
					}
					if($data['deliaddr3']!=""){
						$sql .= ' and';
						$sql .= ' (deliaddr3 LIKE "%'.$data['deliaddr3'].'%" or deliaddr3="")';
						$tmp[] = 'deliaddr3';
					}
					if($data['deliaddr4']!=""){
						$sql .= ' and';
						$sql .= ' (deliaddr4 LIKE "%'.$data['deliaddr4'].'%" or deliaddr4="")';
						$tmp[] = 'deliaddr4';
					}
					
					$sql .= ' and ('.$tmp[0].'<>""';
					for($i=1;$i<count($tmp);$i++){
						$sql .= ' or '.$tmp[$i].'<>""';
					}
					$sql .= ')';
					*/
					$sql .= ' GROUP BY organization, deliaddr0, deliaddr1, deliaddr2, deliaddr3, deliaddr4';
					$sql .= ' order by delivery.id';
				}else if(isset($data['supplier'])){
					$sql = 'SELECT * FROM supplier';
					if(!empty($data['suppliername'])){
						$sql .= $flg? ' and': ' WHERE';
						$sql .= ' (suppliername LIKE "%'.$data['suppliername'].'%" or suppliername="")';
						$flg = true;
						$tmp[] = 'suppliername';
					}
					if($data['tel']!=""){
						$sql .= $flg? ' and': ' WHERE';
						$sql .= ' (tel LIKE "%'.$data['tel'].'%" or tel="")';
						$flg = true;
						$tmp[] = 'tel';
					}
					if(!empty($data['email'])){
						$sql .= $flg? ' and': ' WHERE';
						$sql .= ' (email LIKE "%'.$data['email'].'%" or email="")';
						$flg = true;
						$tmp[] = 'email';
					}
					if($flg){
						$sql .= ' and ('.$tmp[0].'<>""';
						for($i=1;$i<count($tmp);$i++){
							$sql .= ' or '.$tmp[$i].'<>""';
						}
						$sql .= ')';
					}
				}
				break;
			case 'customer':
				if(isset($data['updateform'])){	// 顧客一覧画面の詳細
					$sql = 'SELECT * FROM ((((customer 
						 LEFT JOIN orders ON customer.id=orders.customer_id)
						 LEFT JOIN billtype ON customer.bill=billtype.billid)
						 LEFT JOIN salestype ON customer.sales=salestype.salesid)
						 LEFT JOIN receipttype ON customer.receipt=receipttype.receiptid)
						 LEFT JOIN delivery ON orders.delivery_id=delivery.id';
					if(!empty($data['id'])){
						$sql .= ' WHERE customer.id = '.$data['id'];
					}
					$sql .= ' group by customer.id, delivery.id';
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$rs[] = $res;
					}
					return $rs;
				}

				// 顧客テーブルのみの情報
				$flg = false;
				$sql = 'SELECT * FROM customer';
				if(!empty($data['customer_id'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' id = '.$data['customer_id'];
					$flg = true;
				}else if(!empty($data['id'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' id = '.$data['id'];
					$flg = true;
				}
				
				if($data['number']!=""){
					$sql .= $flg? ' and': ' WHERE';						
					$sql .= ' cstprefix = "'.substr($data['number'],0,1).'"';
					$num = substr($data['number'],1);
					if(!empty($num)) $sql .= ' and number = '.ltrim($num, '0');
					$flg = true;
				}
				if(!empty($data['company'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' company LIKE "%'.$data['company'].'%"';
					$flg = true;
				}
				if(!empty($data['companyruby'])){
					$ruby = mb_convert_encoding($data['companyruby'], 'euc-jp', 'utf-8');
					$ruby_hira = mb_convert_encoding(mb_convert_kana($ruby,"HVc"),'utf-8','euc-jp');
					$ruby_zenkata = mb_convert_encoding(mb_convert_kana($ruby,"KVC"),'utf-8','euc-jp');
					$ruby_hankata = mb_convert_encoding(mb_convert_kana($ruby,"kh"),'utf-8','euc-jp');

					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' (companyruby LIKE "%'.$ruby_hira.'%" OR';
					$sql .= ' companyruby LIKE "%'.$ruby_zenkata.'%" OR';
					$sql .= ' companyruby LIKE "%'.$ruby_hankata.'%")';
					$flg = true;
				}
				if(!empty($data['customername'])){
					$sql .= $flg? ' and': ' WHERE';
					$zenkaku_space = mb_convert_kana($data['customername'],"S", 'utf-8');
					$hankaku_space = mb_convert_kana($data['customername'],"s", 'utf-8');
					
					$sql .= ' (customername LIKE "%'.$data['customername'].'%"';
					$sql .= ' or customername LIKE "%'.$zenkaku_space.'%"';
					$sql .= ' or customername LIKE "%'.$hankaku_space.'%")';
					$flg = true;
				}
				if(!empty($data['customerruby'])){
					$ruby = mb_convert_encoding($data['customerruby'], 'euc-jp', 'utf-8');
					$ruby_hira = mb_convert_encoding(mb_convert_kana($ruby,"HVc"),'utf-8','euc-jp');
					$ruby_zenkata = mb_convert_encoding(mb_convert_kana($ruby,"KVC"),'utf-8','euc-jp');
					$ruby_hankata = mb_convert_encoding(mb_convert_kana($ruby,"kh"),'utf-8','euc-jp');

					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' (customerruby LIKE "%'.$ruby_hira.'%" OR';
					$sql .= ' customerruby LIKE "%'.$ruby_zenkata.'%" OR';
					$sql .= ' customerruby LIKE "%'.$ruby_hankata.'%")';
					$flg = true;
				}
				if($data['tel']!=""){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' tel LIKE "%'.$data['tel'].'%"';
					$flg = true;
				}
				if($data['fax']!=""){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' fax LIKE "%'.$data['fax'].'%"';
					$flg = true;
				}
				if($data['mobile']!=""){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' mobile LIKE "%'.$data['mobile'].'%"';
					$flg = true;
				}
				if(!empty($data['email'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' email LIKE "%'.$data['email'].'%"';
					$flg = true;
				}
				if(!empty($data['mobmail'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' mobmail LIKE "%'.$data['mobmail'].'%"';
					$flg = true;
				}
				if(!empty($data['zipcode'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' zipcode LIKE "%'.$data['zipcode'].'%"';
					$flg = true;
				}
				if(!empty($data['addr1'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' addr1 LIKE "%'.$data['addr1'].'%"';
					$flg = true;
				}
				if(!empty($data['addr2'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' addr2 LIKE "%'.$data['addr2'].'%"';
					$flg = true;
				}
				if(!empty($data['companynote'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' companynote LIKE "%'.$data['companynote'].'%"';
					$flg = true;
				}
				
				break;
			case 'supplier':		// 仕入先
				$flg = false;
				$sql = 'select * from supplier left join supplierclass on supplier.classify=supplierclass.classifyid';
				if(!empty($data['suppliername'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' suppliername LIKE "%'.$data['suppliername'].'%"';
					$flg = true;
				}
				if(!empty($data['represent'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' represent LIKE "%'.$data['represent'].'%"';
					$flg = true;
				}
				if(!empty($data['addr'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' (addr1 LIKE "%'.$data['addr'].'%" OR';
					$sql .= ' addr2 LIKE "%'.$data['addr'].'%")';
					$flg = true;
				}
				if($data['tel']!=""){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' tel LIKE "%'.$data['tel'].'%"';
					$flg = true;
				}
				if($data['fax']!=""){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' fax LIKE "%'.$data['fax'].'%"';
					$flg = true;
				}
				if(!empty($data['email'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' email LIKE "%'.$data['email'].'%"';
					$flg = true;
				}
				if(!empty($data['weburl'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' weburl LIKE "%'.$data['weburl'].'%"';
					$flg = true;
				}
				if(!empty($data['contactname'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' contactname LIKE "%'.$data['contactname'].'%"';
					$flg = true;
				}
				if(!empty($data['contactmobile'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' contactname LIKE "%'.$data['contactmobile'].'%"';
					$flg = true;
				}if(!empty($data['contactemail'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' contactname LIKE "%'.$data['contactemail'].'%"';
					$flg = true;
				}

				if(!empty($data['classify'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' supplierclass.classifyid = '.$data['classify'];
					$flg = true;
				}
				if(!empty($data['outsource'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' outsource LIKE "%'.$data['outsource'].'%"';
					$flg = true;
				}
				if(!empty($data['articles'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' articles LIKE "%'.$data['articles'].'%"';
					$flg = true;
				}
				if(!empty($data['suppliernote'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' suppliernote LIKE "%'.$data['suppliernote'].'%"';
					$flg = true;
				}
				$sql .= ' order by supplyid';
				
				break;

			case 'delivery':
				if(isset($data['recent'])){
				// 同じお届け先への直近の発送日(shippinglist)
					$sql = 'SELECT * FROM ((orders left join delivery on orders.delivery_id=delivery.id)
					 inner join acceptstatus on orders.id=acceptstatus.orders_id)
					 inner join progressstatus on orders.id=progressstatus.orders_id
					 where progress_id=4 and shipped=2 and customer_id=%d and schedule3<"%s"';
					$sql = sprintf($sql, $data['customer_id'], $data['schedule3']);
					if(!empty($data['organization'])){
						$sql .= ' and organization="%s"';
						$sql = sprintf($sql, $data['organization']);
					}
					$sql .= ' order by schedule3 desc limit 1';
				}else{
				// お届け先一覧
					$sql = 'SELECT * FROM orders INNER JOIN delivery ON orders.delivery_id=delivery.id';
					if(!empty($data['customer_id'])){
						$sql .= ' where customer_id = '.$data['customer_id'];
					}
					if(!empty($data['delivery_id'])){
						$sql .= ' where delivery_id = '.$data['delivery_id'];
					}
					$sql .= ' GROUP BY organization, deliaddr0, deliaddr1, deliaddr2, deliaddr3, deliaddr4';
				}
				
				break;
				
			case 'shipfrom':
				$sql = 'SELECT * FROM orders INNER JOIN shipfrom ON orders.shipfrom_id=shipid';
				if(!empty($data['customer_id'])){
					$sql .= ' where customer_id = '.$data['customer_id'];
				}
				$sql .= ' GROUP BY shipfromname, shipaddr0, shipaddr1';
				
				break;
				
			case 'orderitem_old':
				$sql = 'SELECT * FROM orderitem LEFT JOIN orderitemext ON orderitem.id=orderitemext.orderitem_id';
				$sql .= ' left join orderprint on orderprint.id=print_id';
				if(!empty($data['orders_id'])){
					$sql .= ' where orderitem.orders_id = '.$data['orders_id'];
				}
				$sql .= ' order by category_id, master_id, item_name, size_id';

				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$rs[] = $res;
				}
				
				if(empty($rs[0]['master_id'])){
					usort($rs, array('orders', 'multiSort2'));
				}
				
				$flg = false;
				break;
			case 'orderitem':
			/*
			*	mypage.jp, acceptingorderform.php(受注票印刷)
			*/
				$sql = 'SELECT *, orderitem.id as orderitemid,
				coalesce(catalog.item_id, orderitemext.item_id) as item_id,
				coalesce(item.item_name, orderitemext.item_name) as item_name,
				coalesce(size.size_name, orderitemext.size_name) as size_name,
				(case when orderitem.size_id=0 then orderitemext.size_name else orderitem.size_id end) as size_id,
				coalesce(catalog.color_code, orderitemext.item_color) as color_code,
				coalesce(itemcolor.color_name, orderitemext.item_color) as color_name,
				coalesce(orderitemext.price, item_cost) as cost,
				coalesce(maker.maker_name, orderitemext.maker) as maker_name,
				coalesce(orderitemext.stock_number, "") as stock_number
				 FROM (((((orderitem
				 left join orderitemext on orderitem.id=orderitemext.orderitem_id)
				 left join size on orderitem.size_id=size.id)
				 left join catalog on master_id=catalog.id)
				 left join itemcolor on catalog.color_id=itemcolor.id)
				 left join item on catalog.item_id=item.id)
				 left join maker on item.maker_id=maker.id';
				if(!empty($data['orders_id'])){
					$sql .= ' where orderitem.orders_id = '.$data['orders_id'];
				}
				$sql .= ' order by master_id, item.item_name, size_id';
				
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$rs[] = $res;
				}
				
				$rs = $this->multiSort($rs);
				
				$flg = false;
				break;
			case 'orderitemlist':
				/*
				*	リピート版の初回割適用アイテムの判別
				*	mypage.js で使用
				*/
				$sql = 'SELECT coalesce(catalog.item_id,orderitemext.item_id) as item_id, color_code FROM orderitem LEFT JOIN orderitemext ON orderitem.id=orderitemext.orderitem_id';
				$sql .= ' left join catalog on master_id=catalog.id';
				if(!empty($data['orders_id'])){
					$sql .= ' where orderitem.orders_id = '.$data['orders_id'];
				}
				// $sql .= ' group by item_id';
				
				break;
			case 'numberOfBox':
			/*
			*	1箱あたりの最大枚数
			*	mypage.js で使用
			*	$data1	{schedule2,package}
			*	$data2	[{アイテムID,サイズID,枚数},{}, ...]
			*/
				list($data1, $data2) = $data;
				if(empty($data1['curdate'])){
					$data1['curdate'] = date('Y-m-d');
				}else{
					$d = explode('-', $data1['curdate']);
					if(checkdate($d[1], $d[2], $d[0])==false){
						$data1['curdate'] = date('Y-m-d');
					}
				}
				$box = 0;
				$rs = 0;
				for($i=0; $i<count($data2); $i++){
					$sql = "SELECT * FROM itemsize where itemsizeapply<='%s' and itemsizedate>'%s' and item_id=%d and size_from=%d limit 1";
					$sql = sprintf($sql, $data1['curdate'],$data1['curdate'],$data2[$i]['item_id'],$data2[$i]['size_id']);
					$result = exe_sql($conn, $sql);
					while($rec = mysqli_fetch_assoc($result)){
						if(empty($rec['numberpack'])) continue;
						if($data1['package']=='yes'){
							$box += $data2[$i]['amount']/$rec['numberpack'];
						}else{
							$box += $data2[$i]['amount']/$rec['numbernopack'];
						}
					}
				}
				$rs = ceil($box);
				
				$flg = false;
				break;
			case 'orderprint':
				/*
				*	リピート版の初回割適用アイテムの判別
				*	mypage.js で使用
				*/
				$sql = 'select * from ((orders';
				$sql .= ' inner join orderprint on orders.id=orderprint.orders_id)';
				$sql .= ' left join orderarea on orderprint.id=orderarea.orderprint_id)';
				$sql .= ' left join orderselectivearea on areaid=orderarea_id';
				$sql .= ' where selective_key is not null';
				if(!empty($data['orders_id'])){
					$sql .= ' and orderprint.orders_id = '.$data['orders_id'];
				}
				$sql .= ' order by category_id, printposition_id';
				
				break;
			case 'orderarea':
				$flg = false;
				$sql = 'SELECT * FROM (orderprint LEFT JOIN orderarea ON orderprint.id=orderarea.orderprint_id)
				LEFT JOIN printposition ON orderprint.printposition_id=printposition.id';
				if(!empty($data['orders_id'])){
					$sql .= ' where orderprint.orders_id = '.$data['orders_id'];
					$flg = true;
				}
				if(!empty($data['print_key'])){
					$sql .= $flg? ' and': ' where';
					$sql .= ' print_type = "'.$data['print_key'].'"';
					$flg = true;
				}
				if(!empty($data['category_id'])){
					$sql .= $flg? ' and': ' where';
					$sql .= ' category_id = "'.$data['category_id'].'"';
					$flg = true;
				}
				if(!is_null($data['printposition_id'])){
					$sql .= $flg? ' and': ' where';
					$sql .= ' orderprint.printposition_id = "'.$data['printposition_id'].'"';
					$flg = true;
				}

				break;
			case 'orderposition':
				$sql = 'SELECT * FROM orderselectivearea WHERE';
				if(!empty($data['orderarea_id'])){
					$sql .= ' orderarea_id = '.$data['orderarea_id'];
				}
				break;
			case 'orderink':
				$flg = false;
				$sql = 'SELECT * FROM (((orderink INNER JOIN orderarea ON orderink.orderarea_id=areaid)
				 INNER JOIN orderprint ON orderarea.orderprint_id=orderprint.id)
				 left join orderselectivearea on areaid=orderselectivearea.orderarea_id)
				 LEFT JOIN exchink ON exchink.orderink_id=orderink.inkid';
				if(!empty($data['orders_id'])){
					$sql .= ' where orderprint.orders_id = '.$data['orders_id'];
					$flg = true;
				}
				if(!empty($data['orderarea_id'])){
					$sql .= $flg? ' and': ' where';
					$sql .= ' areaid = '.$data['orderarea_id'];
					$flg = true;
				}
				if(!empty($data['ink_id'])){
					$sql .= $flg? ' and': ' where';
					$sql .= ' inkid = '.$data['ink_id'];
					$flg = true;
				}
				$sql .= " order by areaid";
				break;
			case 'direction':
				$flg = false;
				$sql = 'select * from product inner join printtype on product.printtype=printtypeid where';
				if(!empty($data['orders_id'])){
					$sql .= ' orders_id = '.$data['orders_id'];
					$flg = true;
				}
				if(!empty($data['printtype_key'])){
					if($flg) $sql .= ' and';
					$sql .= ' printtype.print_key = "'.$data['printtype_key'].'"';
					$flg = true;
				}
				break;
			case 'product':
				/*
				*	プリント位置指定あり若しくは業者のその他商品が検索対象
				*	一般でプリント無しがチェックされている場合は全て（2012-05-04）
				*	業者もプリント無しがチェックされている場合は全て（2014-08-15）
				*/
				if($data['order_type']=='industry'){
					$sql = "select * from (((((((((((orderitem inner join orders on orders.id=orderitem.orders_id)";
					$sql .= " inner join orderitemext on orderitem.id=orderitemext.orderitem_id)";
					$sql .= " left join customer ON orders.customer_id=customer.id)";
					$sql .= " left join delivery ON orders.delivery_id=delivery.id)";
					$sql .= " left join staff on orders.reception=staff.id)";
					$sql .= " inner join orderprint on orderitem.print_id=orderprint.id)";
					$sql .= " left join orderarea on orderprint.id=orderarea.orderprint_id)";
					$sql .= " left join printtype on orderarea.print_type=printtype.print_key)";
					$sql .= " left join orderselectivearea on orderarea.areaid=orderselectivearea.orderarea_id)";
					$sql .= " left join orderink on orderarea.areaid=orderink.orderarea_id)";
					$sql .= " left join category on orderprint.category_id=category.id)";
					$sql .= " left join exchink on orderink.inkid=exchink.orderink_id";
					$sql .= " where ((orderitemext.item_id!=0 and selectiveid is not null) or orderitemext.item_id=0 or orders.noprint=1)";
					if(!empty($data['orders_id'])){
						$sql .= ' and orders.id = '.$data['orders_id'];
					}
					if(!empty($data['print_type'])){
						$sql .= ' and print_type = "'.$data['print_type'].'"';
					}
					$sql .= " order by orderprint.category_id, orderitemext.size_name";
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$res['color_code'] = $this->search($conn, 'getColorcode',array('item_id'=>$res['item_id'], 'item_color'=>$res['item_color']));
						$rs[] = $res;
					}
					//usort($rs, array('orders', 'multiSort'));
					$rs = $this->multiSort($rs);
					
					$flg = false;
					
				}else{
					$sql = "select * from ((((((((((((((((orderitem inner join orders on orders.id=orderitem.orders_id)";
					$sql .= " left join orderitemext on orderitem.id=orderitemext.orderitem_id)";
					$sql .= " left join customer on orders.customer_id=customer.id)";
					$sql .= " left join delivery on orders.delivery_id=delivery.id)";
					$sql .= " left join staff on orders.reception=staff.id)";
					$sql .= " inner join catalog on orderitem.master_id=catalog.id)";
					$sql .= " left join item on catalog.item_id=item.id)";
					$sql .= " left join category on catalog.category_id=category.id)";
					$sql .= " left join size on orderitem.size_id=size.id)";
					$sql .= " left join itemcolor on catalog.color_id=itemcolor.id)";
					$sql .= " left join maker on item.maker_id=maker.id)";
					$sql .= " inner join orderprint on orderitem.print_id=orderprint.id)";
					$sql .= " left join orderarea on orderprint.id=orderarea.orderprint_id)";
					$sql .= " left join printtype on orderarea.print_type=printtype.print_key)";
					$sql .= " left join orderselectivearea on orderarea.areaid=orderselectivearea.orderarea_id)";
					$sql .= " left join orderink on orderarea.areaid=orderink.orderarea_id)";
					$sql .= " left join exchink on orderink.inkid=exchink.orderink_id";
					$sql .= " where (selectiveid is not null or orders.noprint=1)";
					if(!empty($data['orders_id'])){
						$sql .= " and orders.id = ".$data['orders_id'];
					}
					if(!empty($data['print_type'])){
						$sql .= " and print_type = '".$data['print_type']."'";
					}
					$sql .= " order by orderprint.category_id, size.id";
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$rs[] = $res;
					}
					
					// その他商品
					$sql = "select * from ((((((((((((((((orderitem inner join orders on orders.id=orderitem.orders_id)";
					$sql .= " left join customer on orders.customer_id=customer.id)";
					$sql .= " left join delivery on orders.delivery_id=delivery.id)";
					$sql .= " left join staff on orders.reception=staff.id)";
					$sql .= " left join catalog on orderitem.master_id=catalog.id)";
					$sql .= " left join item on catalog.item_id=item.id)";
					$sql .= " left join category on catalog.category_id=category.id)";
					$sql .= " left join size on orderitem.size_id=size.id)";
					$sql .= " left join itemcolor on catalog.color_id=itemcolor.id)";
					$sql .= " left join maker on item.maker_id=maker.id)";
					$sql .= " inner join orderprint on orderitem.print_id=orderprint.id)";
					$sql .= " left join orderarea on orderprint.id=orderarea.orderprint_id)";
					$sql .= " left join printtype on orderarea.print_type=printtype.print_key)";
					$sql .= " left join orderselectivearea on orderarea.areaid=orderselectivearea.orderarea_id)";
					$sql .= " left join orderink on orderarea.areaid=orderink.orderarea_id)";
					$sql .= " inner join orderitemext on orderitem.id=orderitemext.orderitem_id)";
					$sql .= " left join exchink on orderink.inkid=exchink.orderink_id";
					$sql .= " where (selectiveid is not null or orders.noprint=1)";
					if(!empty($data['orders_id'])){
						$sql .= " and orders.id = ".$data['orders_id'];
					}
					if(!empty($data['print_type'])){
						$sql .= " and print_type = '".$data['print_type']."'";
					}
					$sql .= " order by orderprint.category_id, orderitemext.size_name";
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$res['color_code'] = $res['item_color'];
						$rs[] = $res;
					}
					
					//usort($rs, array('orders', 'multiSort'));
					$rs = $this->multiSort($rs);
					$flg = false;
				}
				break;
			
			case 'printinfo':
				/************************************************
				*	製作指示書
				*************************************************/
				$flg = false;
				$sql = "select * from ((((orders inner join product on orders.id=product.orders_id)
						inner join printinfo on product.id=printinfo.product_id)
						inner join printadj on printinfo.pinfoid=printadj.printinfo_id)
						inner join printtype on product.printtype=printtypeid)
						left join size on printadj.sizename=size.size_name";
				if(!empty($data['orders_id'])){
					$sql .= ' where orders_id = '.$data['orders_id'];
					$flg = true;
				}
				if(!empty($data['printtype'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' product.printtype = '.$data['printtype'];
					$flg = true;
				}
				if(!empty($data['print_key'])){
					$sql .= $flg? ' and': ' WHERE';
					$sql .= ' printtype.print_key = "'.$data['print_key'].'"';
					$flg = true;
				}
				$sql .= ' group by printtype.print_key, print_category_id, print_posid, area_key, print_posname, sizename order by printtype, size.id';
				
			 	break;

			case 'cutpattern':
				/************************************************
				*	面付け情報
				*************************************************/
				$sql = "select * from cutpattern where product_id=".$data['product_id'];
				
				break;
				
			case 'printselective':
				/************************************************
				*	注文確定メールで使用
				*************************************************/
				$flg = false;
				$sql = 'SELECT * FROM ((((orderprint left join orderarea on orderprint.id=orderarea.orderprint_id)
				 inner join orderselectivearea on orderarea.areaid=orderselectivearea.orderarea_id)
				 inner join printtype on orderarea.print_type=printtype.print_key)
				 left join category on orderprint.category_id=category.id)
				 left join printposition on orderprint.printposition_id=printposition.id';
				if(!empty($data['orders_id'])){
					$sql .= ' where orderprint.orders_id = '.$data['orders_id'];
					$flg = true;
				}
				if(!empty($data['print_key'])){
					$sql .= $flg? ' and': ' where';
					$sql .= ' print_type = "'.$data['print_key'].'"';
					$flg = true;
				}
				if(!is_null($data['printposition_id'])){
					$sql .= $flg? ' and': ' where';
					$sql .= ' orderprint.printposition_id = "'.$data['printposition_id'].'"';
					$flg = true;
				}
				if(!empty($data['category_key'])){
					$sql .= $flg? ' and': ' where';
					$sql .= ' category.category_key = "'.$data['category_key'].'"';
					$flg = true;
				}
				
				break;
			
			case 'printform':
				/************************************************
				*	請求・見積・納品書・メール（見積、注文、発送）
				*************************************************/
				$sql = "select * from orders where id=".$data['orders_id'];
				$result = exe_sql($conn, $sql);
				if(mysqli_num_rows($result)===false) return null;

				$res = mysqli_fetch_assoc($result);
				if($res['ordertype']=="general"){
					$sql = sprintf("select * from ((((((((((((orders
					 inner join acceptstatus on orders.id=acceptstatus.orders_id)
					 inner join customer on orders.customer_id=customer.id)
					 left join staff on orders.reception=staff.id)
					 left join delivery on orders.delivery_id=delivery.id)
					 left join shipfrom on orders.shipfrom_id=shipid)
					 inner join estimatedetails on orders.id=estimatedetails.orders_id)
					 inner join orderitem on orders.id=orderitem.orders_id)
					 left join orderitemext on orderitem.id=orderitemext.orderitem_id)
					 inner join orderprint on print_id=orderprint.id)
					 left join size on orderitem.size_id=size.id)
					 left join catalog on master_id=catalog.id)
					 left join item on catalog.item_id=item.id)
					 left join itemcolor on catalog.color_id=itemcolor.id
					 where orderitemext.extid is null and orders.id=%d", $data['orders_id']);
					
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$rs[] = $res;
					}
					
					// その他商品
					$sql = sprintf("select * from ((((((((((((orders
					 inner join acceptstatus on orders.id=acceptstatus.orders_id)
					 inner join customer on orders.customer_id=customer.id)
					 left join staff on orders.reception=staff.id)
					 left join delivery on orders.delivery_id=delivery.id)
					 left join shipfrom on orders.shipfrom_id=shipid)
					 inner join estimatedetails on orders.id=estimatedetails.orders_id)
					 inner join orderitem on orders.id=orderitem.orders_id)
					 inner join orderprint on print_id=orderprint.id)
					 left join size on orderitem.size_id=size.id)
					 left join catalog on master_id=catalog.id)
					 left join item on catalog.item_id=item.id)
					 left join itemcolor on catalog.color_id=itemcolor.id)
					 inner join orderitemext on orderitem.id=orderitemext.orderitem_id
					 where orderitemext.extid is not null and orders.id=%d", $data['orders_id']);
					
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$rs[] = $res;
					}
					$flg = false;
				}else{
					$sql = sprintf("select * from (((((((orders
					 inner join acceptstatus on orders.id=acceptstatus.orders_id)
					 inner join customer on orders.customer_id=customer.id)
					 left join staff on orders.reception=staff.id)
					 left join delivery on orders.delivery_id=delivery.id)
					 left join shipfrom on orders.shipfrom_id=shipid)
					 left join orderitem on orders.id=orderitem.orders_id)
					 left join orderitemext on orderitem.id=orderitemext.orderitem_id)
					 left join orderprint on print_id=orderprint.id
					 where orders.id=%d", $data['orders_id']);
				}
				
			 	break;

			case 'cashbook':
				/****************************
				*	入金処理
				*****************************/
				$sql = 'select * from (((((((orders inner join cashbook on cashbook.orders_id=orders.id)
						inner join acceptstatus ON orders.id=acceptstatus.orders_id)
						inner join acceptprog ON acceptstatus.progress_id=acceptprog.aproid)
						inner join progressstatus ON orders.id=progressstatus.orders_id)
						left join customer on orders.customer_id=customer.id)
						left join billtype ON customer.bill=billtype.billid)
						left join salestype ON customer.sales=salestype.salesid)
						left join receipttype ON customer.receipt=receipttype.receiptid
						left join banklist ON cashbook.bankname=banklist.bankid
						where progress_id=4';
				if(isset($data['orders_id']) && $data['orders_id']!=""){
					$sql .= ' and cashbook.orders_id = '.$data['orders_id'];
				}else if(isset($data['id']) && $data['id']!=""){
					$sql .= ' and orders.id = '.$data['id'];
				}else if(isset($data['customer_id']) && $data['customer_id']!=""){
					$sql .= ' and orders.customer_id = '.$data['customer_id'];
				}
				
				if($data['number']!=""){
					$sql .= ' and cstprefix = "'.substr($data['number'],0,1).'"';
					$num = substr($data['number'],1);
					if(!empty($num)) $sql .= ' and number = '.$num;
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				if(!empty($data['customername'])){
					$zenkaku_space = mb_convert_kana($data['customername'],"S", 'utf-8');
					$hankaku_space = mb_convert_kana($data['customername'],"s", 'utf-8');
					
					$sql .= ' and (customername LIKE "%'.$data['customername'].'%"';
					$sql .= ' or customername LIKE "%'.$zenkaku_space.'%"';
					$sql .= ' or customername LIKE "%'.$hankaku_space.'%")';
				}
				if(!empty($data['maintitle'])){
					$zenkaku = mb_convert_encoding(mb_convert_kana($data['maintitle'],"AKV"),'utf-8','euc-jp');
					$hankaku = mb_convert_encoding(mb_convert_kana($data['maintitle'],"ak"),'utf-8','euc-jp');
					
					$sql .= ' and (maintitle LIKE "%'.$data['maintitle'].'%"';
					$sql .= ' or maintitle LIKE "%'.$zenkaku.'%"';
					$sql .= ' or maintitle LIKE "%'.$hankaku.'%")';
				}
				if(!empty($data['netsales'])){		// 売上額
					$sql .= ' and netsales = '.$data['netsales'];
				}
				if(!empty($data['deposit'])){	// 未入金：1　入金済み：2
					$sql .= ' and deposit = '.$data['deposit'];
				}
				if(!empty($data['payment'])){		// 支払方法（受注伝票の支払方法が優先）
					$sql .= ' and (orders.payment = "'.$data['payment'].'" or';
					$sql .= ' (orders.payment = "0" and receipttype.receipt_key = "'.$data['payment'].'"))';
				}
				if(!empty($data['billtype'])){			// 請求区分
					$sql .= ' and customer.bill = '.$data['billtype'];
				}
				
				$sql .= ' order by customer.id, recdate';
				
				if(isset($data['recdate'])){
					$d = explode('-', $data['recdate']);
					$netsales = 0;
					$receiptmoney = 0;
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						if( strtotime($res['recdate'])<mktime(0,0,0,$d[1],1,$d[0]) ){
							$netsales += $res['netsales'];
							$receiptmoney += $res['receiptmoney'];
							$tmp = $res;
						}else if( strtotime($res['recdate'])<mktime(0,0,0,$d[1]+1,1,$d[0]) ){
							$rs[] = $res;
						}
					}
					if($netsales!=0 || $receiptmoney!=0){
						$tmp['recdate'] = '';
						$tmp['netsales'] = $netsales;
						$tmp['receiptmoney'] = $receiptmoney;
						$tmp['classification'] = 'carryover';
						$tmp['bankname'] = 0;
						$tmp['summary'] = '';
						array_unshift($rs, $tmp);
					}
					
					$flg = false;
					
				}
				
				break;

			case 'billstate':
				/****************************
				*	請求管理
				*****************************/
				$sql = 'select customer.id as num, customer.company, customername, cstprefix, number,
				 sum(receiptmoney) as totreceipt,sum(netsales) as nextsales, sum(netsales) as currsales, sum(netsales) as balance,
				 schedule2,cutofday,paymentday,cyclebilling,bill_state,receipt_name
				 from ((((cashbook inner join orders on cashbook.orders_id=orders.id)
				 inner join customer on orders.customer_id=customer.id)
				 inner join acceptstatus on orders.id=acceptstatus.orders_id)
				 inner join progressstatus on orders.id=progressstatus.orders_id)
				 inner join receipttype on customer.receipt=receipttype.receiptid
				 where  progress_id=4';
				if(isset($data['id']) && $data['id']!=""){
					$sql .= ' and customer.id = '.$data['id'];
				}
				if($data['number']!=""){
					$sql .= ' and cstprefix = "'.substr($data['number'],0,1).'"';
					$num = substr($data['number'],1);
					if(!empty($num)) $sql .= ' and number = '.$num;
				}
				if(!empty($data['company'])){
					$sql .= ' and company LIKE "%'.$data['company'].'%"';
				}
				if(!empty($data['customername'])){
					$zenkaku_space = mb_convert_kana($data['customername'],"S", 'utf-8');
					$hankaku_space = mb_convert_kana($data['customername'],"s", 'utf-8');
					
					$sql .= ' and (customername LIKE "%'.$data['customername'].'%"';
					$sql .= ' or customername LIKE "%'.$zenkaku_space.'%"';
					$sql .= ' or customername LIKE "%'.$hankaku_space.'%")';
				}
				if(!empty($data['billtype'])){		// 〆請求
					$sql .= ' and customer.bill = '.$data['billtype'];
				}
				if(!empty($data['bill_state'])){	// 伝票（請求書発行）状態
					$sql .= ' and progressstatus.bill_state = '.$data['bill_state'];
				}
				if(!empty($data['receipt'])){		// 入金区分
					$sql .= ' and customer.receipt = '.$data['receipt'];
				}

				if(!empty($data['monthly'])){	// 月次指定は必須
					if(empty($data['extract'])){
						/*****************************
						*	当月分回収予定
						*/
						$currdate = $data['FY'].'-'.$data['monthly'].'-01';
						// 〆日が月末以外
						$sql2 = ' and schedule2<adddate(adddate("'.$currdate.'", interval customer.cutofday day), interval -(customer.cyclebilling-1) month)';
						$sql2 .= ' and schedule2>=adddate(adddate("'.$currdate.'", interval customer.cutofday day), interval -customer.cyclebilling month)';
						$sql2 .= ' and customer.cutofday<31';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						while($res = mysqli_fetch_assoc($result)){
							$res['balance'] = 0;
							$res['nextsales'] = 0;
							$res['totreceipt'] = 0;
							$tmp[$res['num']] = $res;
						}

						// 〆日が月末
						$sql2 = ' and schedule2<=last_day( adddate("'.$currdate.'", interval -(customer.cyclebilling-1) month) )';
						$sql2 .= ' and schedule2>=adddate("'.$currdate.'", interval -(customer.cyclebilling-1) month)';
						$sql2 .= ' and customer.cutofday=31';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						while($res = mysqli_fetch_assoc($result)){
							$res['balance'] = 0;
							$res['nextsales'] = 0;
							$res['totreceipt'] = 0;
							$tmp[$res['num']] = $res;
						}

						/*****************************
						*	翌月分回収予定
						*/
						$currdate = date('Y-m-d', mktime(0,0,0,$data['monthly']+1,1,$data['FY']));
						// 〆日が月末以外
						$sql2 = ' and schedule2<adddate(adddate("'.$currdate.'", interval customer.cutofday day), interval -(customer.cyclebilling-1) month)';
						$sql2 .= ' and schedule2>=adddate(adddate("'.$currdate.'", interval customer.cutofday day), interval -customer.cyclebilling month)';
						$sql2 .= ' and customer.cutofday<31';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						while($res = mysqli_fetch_assoc($result)){
							$res['balance'] = 0;
							$res['currsales'] = 0;
							$res['totreceipt'] = 0;
							$tmp2[$res['num']] = $res;
						}

						// 〆日が月末
						$sql2 = ' and schedule2<=last_day( adddate("'.$currdate.'", interval -(customer.cyclebilling-1) month) )';
						$sql2 .= ' and schedule2>=adddate("'.$currdate.'", interval -(customer.cyclebilling-1) month)';
						$sql2 .= ' and customer.cutofday=31';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						while($res = mysqli_fetch_assoc($result)){
							$res['balance'] = 0;
							$res['currsales'] = 0;
							$res['totreceipt'] = 0;
							$tmp2[$res['num']] = $res;
						}
						if(!empty($tmp2)){
							foreach($tmp2 as $key=>$val){
								if(isset($tmp[$key])) $tmp[$key]['nextsales'] = $tmp2[$key]['nextsales'];
								else $tmp[$key] = $val;
							}
						}


						/*****************************
						*	前月末残
						*
						*	2013-12-19 DEBUG 消し込み賀されていない為すべての注文データが出てしまうため、暫定的に外す
						$data['extract'] = "balance";
						$tmp2 = $this->search('billstate', $data);
						if(!empty($tmp2)){
							foreach($tmp2 as $key=>$val){
								if(isset($tmp[$key])) $tmp[$key]['balance'] = $tmp2[$key]['balance'];
								else $tmp[$key] = $val;
							}
						}
						*/


						/*****************************
						*	回収額
						*/
						$data['extract'] = "receipt";
						$tmp2 = $this->search($conn, 'billstate', $data);
						if(!empty($tmp2)){
							foreach($tmp2 as $key=>$val){
								if(isset($tmp[$key])) $tmp[$key]['totreceipt'] = $tmp2[$key]['totreceipt'];
								else $tmp[$key] = $val;
							}
						}


						/*****************************
						*	インデックスの付け替え
						*/
						ksort($tmp);
						foreach($tmp as $val){
							$rs[] = $val;
						}

					}else if($data['extract']=="balance"){
						/*****************************
						*	前月末時点での未回収・前受分　balanceのみ返す、他は0
						*/
						$currdate = date('Y-m-d', mktime(0,0,0,$data['monthly']-1,1,$data['FY']));

						/*
						*	前月までに回収予定の請求総額
						*/
						// 〆日が月末以外
						$sql2 = ' and schedule2<adddate(adddate("'.$currdate.'", interval customer.cutofday day), interval -(customer.cyclebilling-1) month)';
						$sql2 .= ' and customer.cutofday<31';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						while($res = mysqli_fetch_assoc($result)){
							$res['nextsales'] = 0;
							$rs[$res['num']] = $res;
						}

						// 〆日が月末
						$sql2 = ' and schedule2<=last_day( adddate("'.$currdate.'", interval -(customer.cyclebilling-1) month) )';
						$sql2 .= ' and customer.cutofday=31';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						while($res = mysqli_fetch_assoc($result)){
							$res['nextsales'] = 0;
							$rs[$res['num']] = $res;
						}

						/*
						*	前月末までの入金総額、〆日が月末でない場合翌月回収分の前受が発生するケースあり
						*/
						$sql2 = ' and cashbook.recdate<=last_day("'.$currdate.'")';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						while($res = mysqli_fetch_assoc($result)){
							$res['currsales'] = 0;
							$res['nextsales'] = 0;
							$res['balance'] = 0;
							$rs2[$res['num']] = $res;
						}

						/*
						*	前月末時点での未回収分を代入、請求があって入金がある場合に差額を計上
						*/
						foreach($rs as $key=>$val){
							if(isset($rs2[$key])) $rs[$key]['balance'] = $rs[$key]['currsales']-$rs2[$key]['totreceipt'];
							$rs[$key]['currsales'] = 0;
							$rs[$key]['totreceipt'] = 0;
						}
						/*
						*	前月末時点での前受分を代入、請求書の回収月前に入金がある場合
						*/
						foreach($rs2 as $key=>$val){
							if(!isset($rs[$key])){
								$val['balance'] -= $val['totreceipt'];
								$rs[$key] = $val;
								$rs[$key]['totreceipt'] = 0;
							}
						}

					}else if($data['extract']=="receipt"){
						/*****************************
						*	回収額（当該月の1日から月末）totreceipt のみを返す、他は0
						*/
						$currdate = $data['FY'].'-'.$data['monthly'].'-01';
						$sql2 = ' and cashbook.recdate between "'.$currdate.'" and last_day("'.$currdate.'")';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						while($res = mysqli_fetch_assoc($result)){
							$res['currsales'] = 0;
							$res['nextsales'] = 0;
							$res['balance'] = 0;
							$rs[$res['num']] = $res;
						}
					}else if($data['extract']=="bill"){
						/*****************************
						*	請求額（月締め請求予定）
						*/
						$sql .= ' and customer.bill=2';
						$currdate = $data['FY'].'-'.$data['monthly'].'-01';
						if(!empty($data['cutofday'])){
							if($data['cutofday']=='31'){
								$sql2 = ' and schedule2 between "'.$currdate.'" and last_day("'.$currdate.'")';
								$sql2 .= ' and customer.cutofday=31';
								$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
								$result = exe_sql($sql.$sql2);
								while($res = mysqli_fetch_assoc($result)){
									$rs[] = $res;
								}
							}else{
								$currdate = $data['FY'].'-'.$data['monthly'].'-'.$data['cutofday'];
								$sql2 = ' and schedule2<"'.$currdate.'"';
								$sql2 .= ' and schedule2>=adddate("'.$currdate.'", interval -1 month)';
								$sql2 .= ' and customer.cutofday='.$data['cutofday'];
								$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
								$result = exe_sql($sql.$sql2);
								while($res = mysqli_fetch_assoc($result)){
									$rs[] = $res;
								}
							}
						}else{
							$sql2 = ' and schedule2<adddate("'.$currdate.'", interval customer.cutofday day)';
							$sql2 .= ' and schedule2>=adddate(adddate("'.$currdate.'", interval customer.cutofday day), interval -1 month)';
							$sql2 .= ' and customer.cutofday<31';
							$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
							$result = exe_sql($sql.$sql2);
							while($res = mysqli_fetch_assoc($result)){
								$rs[] = $res;
							}

							$sql2 = ' and schedule2 between "'.$currdate.'" and last_day("'.$currdate.'")';
							$sql2 .= ' and customer.cutofday=31';
							$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
							$result = exe_sql($sql.$sql2);
							while($res = mysqli_fetch_assoc($result)){
								$rs[] = $res;
							}
						}
					}else if($data['extract']=="overdue"){
						/*****************************
						*	滞留額　月毎　totreceipt のみを返す
						*/
						$currdate = $data['FY'].'-'.$data['monthly'].'-01';
						// 〆日が月末以外
						$sql2 = ' and schedule2<adddate(adddate("'.$currdate.'", interval customer.cutofday day), interval -(customer.cyclebilling-1) month)';
						$sql2 .= ' and schedule2>=adddate(adddate("'.$currdate.'", interval customer.cutofday day), interval -(customer.cyclebilling) month)';
						$sql2 .= ' and customer.cutofday<31';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						while($res = mysqli_fetch_assoc($result)){
							$res['totreceipt'] = $res['nextsales'] - $res['totreceipt'];
							$rs[] = $res;
						}

						// 〆日が月末
						$sql2 = ' and schedule2<=last_day( adddate("'.$currdate.'", interval -(customer.cyclebilling-1) month) )';
						$sql2 .= ' and schedule2>=adddate("'.$currdate.'", interval -(customer.cyclebilling-1) month)';
						$sql2 .= ' and customer.cutofday=31';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						while($res = mysqli_fetch_assoc($result)){
							$res['totreceipt'] = $res['nextsales'] - $res['totreceipt'];
							$rs[] = $res;
						}


					}else if($data['extract']=="alloverdue"){
						/*****************************
						*	滞留額　当該月の前月までの累計　totreceipt のみを返す
						*/
						$currdate = date('Y-m-d', mktime(0,0,0,$data['monthly']-1,1,$data['FY']));
						// 〆日が月末以外
						$sql2 = ' and schedule2<adddate(adddate("'.$currdate.'", interval customer.cutofday day), interval -(customer.cyclebilling-1) month)';
						$sql2 .= ' and customer.cutofday<31';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						while($res = mysqli_fetch_assoc($result)){
							$res['totreceipt'] = $res['nextsales'] - $res['totreceipt'];
							$rs[] = $res;
						}

						// 〆日が月末
						$sql2 = ' and schedule2<=last_day( adddate("'.$currdate.'", interval -(customer.cyclebilling-1) month) )';
						$sql2 .= ' and customer.cutofday=31';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						while($res = mysqli_fetch_assoc($result)){
							$res['totreceipt'] = $res['nextsales'] - $res['totreceipt'];
							$rs[] = $res;
						}

					}

					$flg = false;
					return $rs;
				}

				break;

			case 'billresults':
				/****************************
				*	回収実績一覧　月毎の請求額と滞留額
				*****************************/
				
				if($data['number']!=""){
					$sql2 .= ' and cstprefix = "'.substr($data['number'],0,1).'"';
					$num = substr($data['number'],1);
					if(!empty($num)) $sql2 .= ' and number = '.$num;
				}
				if(isset($data['id']) && $data['id']!=""){
					$sql2 .= ' and customer.id = '.$data['id'];
				}
				if(!empty($data['customername'])){
					$zenkaku_space = mb_convert_kana($data['customername'],"S", 'utf-8');
					$hankaku_space = mb_convert_kana($data['customername'],"s", 'utf-8');
					
					$sql .= ' and (customername LIKE "%'.$data['customername'].'%"';
					$sql .= ' or customername LIKE "%'.$zenkaku_space.'%"';
					$sql .= ' or customername LIKE "%'.$hankaku_space.'%")';
				}
				if(!empty($data['billtype'])){		// 〆請求
					$sql2 .= ' and customer.bill = '.$data['billtype'];
				}
				if(!empty($data['bill_state'])){	// 伝票（請求書発行）状態
					$sql2 .= ' and progressstatus.bill_state = '.$data['bill_state'];
				}
				if(!empty($data['receipt'])){		// 入金区分
					$sql2 .= ' and customer.receipt = '.$data['receipt'];
				}

				for($i=1; $i<13; $i++){
					$currdate = "'".$data['FY']."-".$i."-1'";
					$sql[0] = "select customer.id as num, cstprefix, number, customername, sum(netsales) as bill, sum(netsales)-sum(receiptmoney) as balance, month($currdate) as mm
		 				from ((((cashbook inner join orders on cashbook.orders_id=orders.id)
						 inner join customer on orders.customer_id=customer.id)
						 inner join acceptstatus on orders.id=acceptstatus.orders_id)
						 inner join progressstatus on orders.id=progressstatus.orders_id)
						 inner join receipttype on customer.receipt=receipttype.receiptid 
						 where progress_id=4";
					$sql[0] .= $sql2;
					$sql[0] .= " and schedule2<adddate(adddate($currdate, interval customer.cutofday day), interval -(customer.cyclebilling-1) month)
						 and schedule2>=adddate(adddate($currdate, interval customer.cutofday day), interval -(customer.cyclebilling) month)
						 and customer.cutofday<31
						 GROUP BY customer.id ORDER BY customer.id";

		 			$sql[1] = "select customer.id as num, cstprefix, number, customername, sum(netsales) as bill, sum(netsales)-sum(receiptmoney) as balance, month($currdate) as mm
		 				from ((((cashbook inner join orders on cashbook.orders_id=orders.id)
						 inner join customer on orders.customer_id=customer.id)
						 inner join acceptstatus on orders.id=acceptstatus.orders_id)
						 inner join progressstatus on orders.id=progressstatus.orders_id)
						 inner join receipttype on customer.receipt=receipttype.receiptid 
						 where progress_id=4";
					$sql[1] .= $sql2;
					$sql[1] .= " and schedule2<=last_day( adddate($currdate, interval -(customer.cyclebilling-1) month) )
						 and schedule2>=adddate($currdate, interval -(customer.cyclebilling-1) month)
						 and customer.cutofday=31
						 GROUP BY customer.id ORDER BY customer.id";


		 			for($s=0; $s<2; $s++){
						$result = exe_sql($sql[$s]);
						if(mysqli_num_rows($result)){
							while($res = mysqli_fetch_assoc($result)){
								if(isset($tmp[$res['num']])){
									$tmp[$res['num']]['bill'.$res['mm']] = $res['bill'];
									$tmp[$res['num']]['balance'.$res['mm']] = $res['balance'];
								}else{
									$tmp[$res['num']] = array('num'=>$res['num'],'cstprefix'=>$res['cstprefix'],'number'=>$res['number'],'customername'=>$res['customername'],'carryover'=>'0');
									for($m=1; $m<13; $m++){
										if($m==$res['mm']){
											$tmp[$res['num']]['bill'.$m] = $res['bill'];
											$tmp[$res['num']]['balance'.$m] = $res['balance'];
										}else{
											$tmp[$res['num']]['bill'.$m] = "0";
											$tmp[$res['num']]['balance'.$m] = "0";
										}
									}
								}
							}
						}
					}
				}

				/*****************************
				*	滞留額の前年繰越を取得
				*
				*	2013-12-19 DEBUG
				*
				$data['extract'] = "alloverdue";
				$data['monthly'] = "4";
				$tmp2 = $this->search('billstate',$data);
				for($i=0; $i<count($tmp2); $i++){
					$num = $tmp2[$i]['num'];
					if(isset($tmp[$num])){
						$tmp[$num]['carryover'] = $tmp2[$i]['totreceipt'];
					}else{
						$tmp[$num] = array('num'=>$num,'company'=>$tmp2[$i]['company'],'customername'=>$tmp2[$i]['customername'],'carryover'=>$tmp2[$i]['totreceipt']);
						for($m=1; $m<13; $m++){
							$tmp[$num]['bill'.$m] = "0";
							$tmp[$num]['balance'.$m] = "0";
						}
					}
				}
				*/


				/*****************************
				*	インデックスの付け替え
				*/
				ksort($tmp);
				foreach($tmp as $val){
					$rs[] = $val;
				}

				$flg = false;
				break;

		 	case 'customerledger':
		 		/****************************
				*	得意先元帳
				*	注文確定のみ
				*****************************/
				if($data['number']!=""){
					$sql .= ' and cstprefix = "'.substr($data['number'],0,1).'"';
					$num = substr($data['number'],1);
					if(!empty($num)) $sql .= ' and number = '.$num;
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
				}
				if(!empty($data['maintitle'])){
					$zenkaku = mb_convert_encoding(mb_convert_kana($data['maintitle'],"AKV"),'utf-8','euc-jp');
					$hankaku = mb_convert_encoding(mb_convert_kana($data['maintitle'],"ak"),'utf-8','euc-jp');
					
					$sql .= ' and (maintitle LIKE "%'.$data['maintitle'].'%"';
					$sql .= ' or maintitle LIKE "%'.$zenkaku.'%"';
					$sql .= ' or maintitle LIKE "%'.$hankaku.'%")';
				}
				if(!empty($data['customername'])){
					$zenkaku_space = mb_convert_kana($data['customername'],"S", 'utf-8');
					$hankaku_space = mb_convert_kana($data['customername'],"s", 'utf-8');
					
					$sql .= ' and (customername LIKE "%'.$data['customername'].'%"';
					$sql .= ' or customername LIKE "%'.$zenkaku_space.'%"';
					$sql .= ' or customername LIKE "%'.$hankaku_space.'%")';
				}
				if(!empty($data['bill_state'])){	// 伝票（請求書発行）状態
					$sql .= ' and progressstatus.bill_state = '.$data['bill_state'];
				}
				if(!empty($data['bill'])){		// 請求区分
					$sql .= ' and customer.bill = '.$data['bill'];
				}
				/*
				if(!empty($data['receipt'])){		// 入金区分
					$sql .= ' and customer.receipt = '.$data['receipt'];
				}
				if(!empty($data['sales'])){		// 取引区分
					$sql .= ' and customer.sales = '.$data['sales'];
				}
				*/
				
				// 業者
				$sql1 = 'select orders.customer_id as customer_id, number, cstprefix, customer.company as company, customername,
				 maintitle, schedule2 as orderdate, bill_name, 
				 orderitem.amount, sum(cashbook.receiptmoney) as totreceipt,orders.id as id, progressstatus.bill_state,
				 extid, orderitemext.stock_number as item_code, orderitemext.item_name,
				 orderitemext.size_name, orderitemext.item_color as color_name, orderitemext.maker as maker_name, orderitemext.price,
				 netsales,order_amount,
				 addestid, addsummary, addamount, addcost, addprice,
				 orders.ordertype
				 from (((((((orderitem inner join orderitemext on orderitem.id=orderitemext.orderitem_id)
				 inner join orders on orders.id=orderitem.orders_id)
				 left join additionalestimate on orders.id=additionalestimate.orders_id)
				 inner join progressstatus on orders.id=progressstatus.orders_id)
				 inner join acceptstatus on orders.id=acceptstatus.orders_id)
				 inner join customer on orders.customer_id=customer.id)
				 inner join billtype on customer.bill=billtype.billid)
				 inner join cashbook on orders.id=cashbook.orders_id
				 where orders.ordertype="industry" and progress_id=4';

				$sql1 .= $sql.' group by customer.id, orders.id, extid, addestid order by customer.id, orders.id';
				$result = exe_sql($sql1);
				while($res = mysqli_fetch_assoc($result)){
					$rs[] = $res;
				}

				// 一般
				$sql2 = 'select orders.customer_id as customer_id, number, cstprefix, customer.company as company, customername,
				 maintitle, schedule2 as orderdate, bill_name, item.item_code, item.item_name,
				 orderitem.amount, sum(cashbook.receiptmoney) as totreceipt,orders.id as id, progressstatus.bill_state,
				 size.size_name,itemcolor.color_name,maker.maker_name, 
				 truncate(itemprice.price_0*itemprice.margin_pvt*(1+(taxratio/100))+9,-1) as price, truncate(itemprice.price_1*itemprice.margin_pvt*(1+(taxratio/100))+9,-1) as price_white,
				 productfee,printfee,exchinkfee,packfee,expressfee,discountfee,reductionfee,
				 carriagefee,extracarryfee,designfee,codfee,netsales,order_amount,
				 orders.ordertype
				 from salestax, (((((((((((((orderitem inner join orders on orders.id=orderitem.orders_id)
				 inner join progressstatus on orders.id=progressstatus.orders_id)
				 inner join acceptstatus on orders.id=acceptstatus.orders_id)
				 inner join estimatedetails on orders.id=estimatedetails.orders_id)
				 inner join customer on orders.customer_id=customer.id)
				 inner join billtype on customer.bill=billtype.billid)
				 inner join cashbook on orders.id=cashbook.orders_id)
				 inner join catalog on orderitem.master_id=catalog.id)
				 inner join item on catalog.item_id=item.id)
				 inner join category on catalog.category_id=category.id)
				 inner join itemprice on item.id=itemprice.item_id)
				 left join size on orderitem.size_id=size.id)
				 left join itemcolor on catalog.color_id=itemcolor.id)
				 left join maker on item.maker_id=maker.id
				 where itemprice.size_from=size.id and progress_id=4 and itempriceapply<=schedule2 and itempricedate>schedule2
				 and catalogapply<=schedule2 and catalogdate>schedule2 and itemapply<=schedule2 and itemdate>schedule2
				 and taxapply=(select max(taxapply) from salestax where taxapply<=schedule2)';

				$sql2 .= $sql.' group by customer.id, orders.id, size_id order by customer.id, orders.id';
				$result = exe_sql($sql2);
				while($res = mysqli_fetch_assoc($result)){
					$rs[] = $res;
				}
				
				/*
				for($i=0; $i<count($rs); $i++){
					$a[$i] = $rs[$i]['schedule2'];
					$b[$i] = $rs[$i]['customer_id'];
				}
				array_multisort($a,$b, $rs);
				*/
				
				$flg=false;
		 		break;
			
		 	case 'bill':
				/****************************
				*	月締め繰越額込み請求書　（前月分、入金額、繰越額、今月額）
				*****************************/
				$flg = false;
				$sql = 'select customer.id as num, customer.company, customername,
				 sum(netsales) as prevsales, sum(receiptmoney) as totreceipt, sum(netsales)-sum(receiptmoney) as overdue, sum(netsales) as currsales
				from ((cashbook inner join orders on cashbook.orders_id=orders.id)
				inner join customer on orders.customer_id=customer.id)
				inner join acceptstatus on orders.id=acceptstatus.orders_id';
				if(isset($data['id']) && $data['id']!=""){
					$sql .= ' WHERE customer.id = '.$data['id'];
					$flg = true;
				}
				if(!empty($data['monthly'])){
					$sql .= $flg? ' and': ' WHERE';
					$currdate = $data['FY'].'-'.$data['monthly'].'-01';
					if($data['cutofday']<31){
						/*****************************
						*	当月分売上額
						*/
						$sql2 = ' schedule2<adddate("'.$currdate.'", interval customer.cutofday day)';
						$sql2 .= ' and schedule2>=adddate(adddate("'.$currdate.'", interval customer.cutofday day), interval -1 month)';
						$sql2 .= ' and customer.cutofday<31';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						$res = mysqli_fetch_assoc($result);
						$rs['currsales'] = $res['currsales'];
						/*****************************
						*	前月分売上額と入金額
						*/
						$currdate 	 = date('Y-m-d', mktime(0,0,0,$data['monthly']-1,1,$data['FY']));	// 前月1日
						$receiptdate = date('Y-m-d', mktime(0,0,0,$data['monthly']+1,0,$data['FY']));	// 今月末
						$sql2 = ' schedule2<adddate("'.$currdate.'", interval customer.cutofday day)';
						$sql2 .= ' and schedule2>=adddate(adddate("'.$currdate.'", interval customer.cutofday day), interval -1 month)';
						$sql2 .= ' and cashbook.recdate<="'.$receiptdate.'"';
						$sql2 .= ' and customer.cutofday<31';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						$res = mysqli_fetch_assoc($result);
						$rs['prevsales'] = $res['prevsales'];
						$rs['totreceipt'] = $res['totreceipt'];
						/*****************************
						*	繰越額
						*/
						$sql2 = ' schedule2<adddate("'.$currdate.'", interval customer.cutofday day)';
						$sql2 .= ' and cashbook.recdate<="'.$receiptdate.'"';
						$sql2 .= ' and customer.cutofday<31';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						$res = mysqli_fetch_assoc($result);
						$rs['overdue'] = $res['overdue'];
					}else{
						/*****************************
						*	当月分売上額
						*/
						$sql2 = ' schedule2 between "'.$currdate.'" and last_day("'.$currdate.'")';
						$sql2 .= ' and customer.cutofday=31';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						$res = mysqli_fetch_assoc($result);
						$rs['currsales'] = $res['currsales'];
						/*****************************
						*	前月分売上額と入金額
						*/
						$currdate 	 = date('Y-m-d', mktime(0,0,0,$data['monthly']-1,1,$data['FY']));	// 前月1日
						$receiptdate = date('Y-m-d', mktime(0,0,0,$data['monthly']+1,0,$data['FY']));	// 今月末
						$sql2 = ' schedule2 between "'.$currdate.'" and last_day("'.$currdate.'")';
						$sql2 .= ' and cashbook.recdate<="'.$receiptdate.'"';
						$sql2 .= ' and customer.cutofday=31';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						$res = mysqli_fetch_assoc($result);
						$rs['prevsales'] = $res['prevsales'];
						$rs['totreceipt'] = $res['totreceipt'];
						/*****************************
						*	繰越額
						*/
						$sql2 = ' schedule2<last_day("'.$currdate.'")';
						$sql2 .= ' and cashbook.recdate<="'.$receiptdate.'"';
						$sql2 .= ' and customer.cutofday=31';
						$sql2 .= ' GROUP BY customer.id ORDER BY customer.id';
						$result = exe_sql($sql.$sql2);
						$res = mysqli_fetch_assoc($result);
						$rs['overdue'] = $res['overdue'];
					}
				}

				$flg=false;
				break;

			case 'saleslist':
				/****************************
				*	売上推移表　年度別
				*****************************/
				$startdate = "'".$data['FY_to']."-04-01'";
				$enddate = "'".($data['FY_to']+1)."-03-31'";
				switch($data['assort']){
				case '0':	// 年度
					for($y=$data['FY_from']; $y<=$data['FY_to']; $y++){
						$startdate = "'".$y."-04-01'";
				 		$enddate = "'".($y+1)."-03-31'";
						$tmp[$y] = array(0,0,0,0,0,0,0,0,0,0,0,0);
						$sql = "select date_format(schedule3, '%c') as month, sum(estimated) as sales
							 from orders 
							 inner join acceptstatus on orders.id=acceptstatus.orders_id
							 where progress_id=4 and schedule3 between $startdate and $enddate
							 GROUP BY date_format(schedule3, '%Y-%m')";
						$result = exe_sql($conn, $sql);
						while($res = mysqli_fetch_assoc($result)){
							$m = $res['month']-1;
							$tmp[$y][$m] = $res['sales'];
						}
					}
					break;

				 case '1':	// 商品別
				 	$maxCategoryid = 0;
				 	$result = exe_sql('select * from category');
					while($res = mysqli_fetch_assoc($result)){
						$id = $res['id'];
						$tmp[$id] = array(0,0,0,0,0,0,0,0,0,0,0,0);
						$tmp[$id]['id'] = $id;
						$tmp[$id]['categoryname'] = $res['category_name'];
						$maxCategoryid = max($maxCategoryid, $id);
					}
					// その他、持込、転写シート
					$categoryname = array('その他','持込','転写シート');
					$id = $maxCategoryid;
					for($i=0; $i<count($categoryname); $i++){
						$id++;
						$tmp[$id] = array(0,0,0,0,0,0,0,0,0,0,0,0);
						$tmp[$id]['id'] = $id;
						$tmp[$id]['categoryname'] = $categoryname[$i];
					}
				 	$sql = "select orders.id, orderitem.id, date_format(schedule3, '%c') as month,
						coalesce(category_id,orderitemext.item_id) as category_id,
						coalesce(category_name,orderitemext.item_name) as category_name,
						sum(orderitem.amount) as volume,
						sum(coalesce( orderitemext.price*orderitem.amount,
						( case when color_id!=59 or (color_id!=42 and (catalog.item_id=112 or catalog.item_id=212))
						 then truncate(itemprice.price_0*itemprice.margin_pvt*(1+(taxratio/100))+9,-1)*orderitem.amount
						 else truncate(itemprice.price_1*itemprice.margin_pvt*(1+(taxratio/100))+9,-1)*orderitem.amount end ) ) ) as price
						 from salestax,
						 (((((orders inner join orderitem on orders.id=orderitem.orders_id)
						 left join orderitemext on orderitem.id=orderitem_id)
						 left join acceptstatus on orderitem.orders_id=acceptstatus.orders_id)
						 left join catalog on orderitem.master_id=catalog.id)
						 left join category on catalog.category_id=category.id)
						 left join itemprice on catalog.item_id=itemprice.item_id
						 where progress_id=4 
						 and schedule3 between $startdate and $enddate
						 and ( ((size_id between itemprice.size_from and itemprice.size_to) 
						 and itempriceapply<schedule2 and itempricedate>=schedule2) or extid is not null )
						 and taxapply=(select max(taxapply) from salestax where taxapply<=schedule2)
						 group by date_format(schedule3, '%Y-%m'), category_id";
				 	$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$m = $res['month']-1;
						switch($res['category_id']){
							case '0':
								$idx = $maxCategoryid+1;
								$tmp[$idx][$m] += $res['price'];
								break;
							case '100000':
								$idx = $maxCategoryid+2;
								$tmp[$idx][$m] += $res['price'];
								break;
							case '99999':
								$idx = $maxCategoryid+3;
								$tmp[$idx][$m] += $res['price'];
								break;
							default:
								$tmp[$res['category_id']][$m] += $res['price'];
								break;
						}
					}

					// プリント代とその他諸経費
					$idx = $maxCategoryid + count($categoryname) + 1;
					$articles = array('silkprintfee'=>array($idx++,'シルク'),
				 					  'colorprintfee'=>array($idx++,'カラー転写'),
				 					  'digitprintfee'=>array($idx++,'デジタル転写'),
				 					  'inkjetprintfee'=>array($idx++,'インクジェット'),
				 					  'cuttingprintfee'=>array($idx++,'カッティング'),
				 					  'exchinkfee'=>array($idx++,'インク色替え代'),
				 					  'packfee'=>array($idx++,'袋詰め代'),
				 					  'expressfee'=>array($idx++,'特急料金'),
				 					  'discountfee'=>array($idx++,'割引金額'),
				 					  'reductionfee'=>array($idx++,'値引金額'),
				 					  'carriagefee'=>array($idx++,'送料'),
				 					  'designfee'=>array($idx++,'デザイン代'),
				 					  'codfee'=>array($idx++,'代引き手数料'),
				 					  'creditfee'=>array($idx++,'カード手数料')
				 					);
				 	foreach($articles as $key=>$val){
				 		$id = $val[0];
						$tmp[$id] = array(0,0,0,0,0,0,0,0,0,0,0,0);
						$tmp[$id]['id'] = $id;
						$tmp[$id]['categoryname'] = $val[1];
					}
					$sql = "select date_format(schedule3, '%c') as month,
						sum(exchinkfee) as exchinkfee,sum(packfee) as packfee,sum(expressfee) as expressfee,sum(discountfee) as discountfee,
						sum(reductionfee) as reductionfee,sum(carriagefee) as carriagefee,sum(extracarryfee) as extracarryfee,
						sum(designfee) as designfee,sum(codfee) as codfee,sum(creditfee) as creditfee,
						sum(silkprintfee) as silkprintfee,sum(colorprintfee) as colorprintfee,sum(digitprintfee) as digitprintfee,
						sum(inkjetprintfee) as inkjetprintfee,sum(cuttingprintfee) as cuttingprintfee
						 from
						 (orders inner join estimatedetails on orders.id=estimatedetails.orders_id)
						 inner join acceptstatus on orders.id=acceptstatus.orders_id
						 where progress_id=4 and schedule3 between $startdate and $enddate
						 group by date_format(schedule3, '%Y-%m')";
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$m = $res['month']-1;
						foreach($articles as $key=>$val){
							$id = $val[0];
							$tmp[$id][$m] = $res[$key];
						}
					}

					break;

				 case '2':	// 担当者別
					$articles = array(array('silkprintfee','シルク'),
									  array('colorprintfee','カラー転写'),
									  array('digitprintfee','デジタル転写'),
									  array('inkjetprintfee','インクジェット'),
									  array('cuttingprintfee','カッティング'),
									  array('productfee','商品代金')
									);

					$sql = "select date_format(schedule3, '%c') as month,reception,staffname,
						sum(estimated) as estimated,sum(productfee) as productfee,
						sum(silkprintfee) as silkprintfee,sum(colorprintfee) as colorprintfee,sum(digitprintfee) as digitprintfee,
						sum(inkjetprintfee) as inkjetprintfee,sum(cuttingprintfee) as cuttingprintfee
						 from
						 ((orders inner join estimatedetails on orders.id=estimatedetails.orders_id)
						 inner join acceptstatus on orders.id=acceptstatus.orders_id)
						 inner join staff on orders.reception=staff.id
						 where progress_id=4 and schedule3 between $startdate and $enddate
						 group by date_format(schedule3, '%Y-%m'), staff.rowid";
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$m = $res['month']-1;
						$id = $res['reception']*10;
						if(!isset($tmp[$id])){
							for($i=0; $i<count($articles); $i++,$id++){
								$tmp[$id] = array(0,0,0,0,0,0,0,0,0,0,0,0);
								$tmp[$id]['staffname'] = $articles[$i][1]."　".$res['staffname'];
								$tmp[$id][$m] = $res[$articles[$i][0]];
							}
							$tmp[$id] = array(0,0,0,0,0,0,0,0,0,0,0,0);
						}else{
							for($i=0; $i<count($articles); $i++,$id++){
								$tmp[$id]['staffname'] = $articles[$i][1]."　".$res['staffname'];
								$tmp[$id][$m] = $res[$articles[$i][0]];
							}
						}
						$tmp[$id]['staffname'] = "【　売上計　】".$res['staffname'];
						$tmp[$id][$m] = $res['estimated'];
					}

					break;

				 case '3':	// 得意先別
				 	$sql = "select customer.id as num, company, customername, sum(netsales) as sales, date_format(schedule3, '%c') as month
		 				from (((cashbook inner join orders on cashbook.orders_id=orders.id)
						 inner join customer on orders.customer_id=customer.id)
						 inner join acceptstatus on orders.id=acceptstatus.orders_id)
						 inner join receipttype on customer.receipt=receipttype.receiptid
						 where progress_id=4 and schedule3 between $startdate and $enddate
						 and customer.bill=2
						 group by date_format(schedule3, '%Y-%m'), customer.id ORDER BY customer.id";
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$m = $res['month']-1;
						$key = $res['num'];
						if(isset($tmp[$key])){
							$tmp[$key][$m] = $res['sales'];
						}else{
							$tmp[$key] = array(0,0,0,0,0,0,0,0,0,0,0,0);
							$tmp[$key][$m] = $res['sales'];
							$tmp[$key]['num'] = $key;
							$tmp[$key]['company'] = $res['company'];
							$tmp[$key]['customername'] = $res['customername'];
						}
					}

					break;

				case '4':	// 確定注文一覧
					$sql = 'select * from ((((orders 
							inner join acceptstatus ON orders.id=acceptstatus.orders_id)
							inner join acceptprog ON acceptstatus.progress_id=acceptprog.aproid)
							inner join progressstatus ON orders.id=progressstatus.orders_id)
							left join customer on orders.customer_id=customer.id)
							left join billtype ON customer.bill=billtype.billid
							where progress_id=4';
					
					if(!empty($data['term_from'])){
						$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
					}
					if(!empty($data['term_to'])){
						$sql .= ' and schedule3 <= "'.$data['term_to'].'"';
					}
					if(!empty($data['sales']) || $data['sales']=="0"){
						$sql .= ' and estimated = '.$data['sales'];
					}
					if(!empty($data['reduction'])){
						$sql .= ' and reductionname = "'.$data['reduction'].'"';
					}
					
					$sql .= ' order by cstprefix, customer.id, schedule3';
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$rs[] = $res;
					}
					break;
					
				default: return null;
				}

				/*****************************
				*	インデックスの付け替え
				*/
				if($data['assort']!=4){
					ksort($tmp);
					foreach($tmp as $val){
						$rs[] = $val;
					}
				}
				
				$flg = false;
			 	break;

			case 'customerlog':
				/*****************************
				*	受注書ごとのログを取得
				*/
				$sql = sprintf("select * from customerlog left join staff on customerlog.cstlog_staff=staff.id where orders_id=%d order by cstlog_date", $data['orders_id']);
				
				break;

			case 'searchlog':
				/*****************************
				*	受付ログの全文検索
				*/
				$sql = "select * from customerlog left join staff on customerlog.cstlog_staff=staff.id ";
				$sql .= "where orders_id=".$data['orders_id']." and cstlog_text like '%".$data['against']."%'";
				
				break;

			case 'progress':
				$sql = sprintf("select progress_id from acceptstatus where orders_id=%d", $data['orders_id']);
				$result = exe_sql($conn, $sql);
				$res = mysqli_fetch_assoc($result);
				$rs = $res['progress_id'];
				
				$flg = false;
				break;

			case 'discount':
				$sql = sprintf("select discount_name from discount where discount_state=1 and orders_id=%d", $data['orders_id']);
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$rs[] = $res['discount_name'];
				}
				
				$flg = false;
				break;

			case 'media':
				$sql = sprintf("select media_type, media_value from media where orders_id=%d", $data['orders_id']);
				
				break;

			case 'estimatedetails':
				$sql = sprintf("select * from additionalestimate where orders_id=%d", $data['orders_id']);
				
				break;

			case 'contact';
				$sql = 'select * from ((((contactinfo inner join customer on contact_refid=customer.id)
				 inner join contactphase on phase_id=phaseid)
				 inner join website on site_id=siteid)
				 inner join classification on class_id=classid)
				 left join attachinfo on contactinfo.contactid=attachinfo.contact_id
				 where class_id<999';
				if(!empty($data['term_from'])){
					$sql .= ' and contactdate >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and contactdate <= "'.$data['term_to'].'"';
				}
				if(!empty($data['customername'])){
					$zenkaku_space = mb_convert_kana($data['customername'],"S", 'utf-8');
					$hankaku_space = mb_convert_kana($data['customername'],"s", 'utf-8');
					
					$sql .= ' and (customername LIKE "%'.$data['customername'].'%"';
					$sql .= ' or customername LIKE "%'.$zenkaku_space.'%"';
					$sql .= ' or customername LIKE "%'.$hankaku_space.'%")';
				}
				if(!empty($data['site_id'])){
					$sql .= ' and site_id = '.$data['site_id'];
				}
				if(!empty($data['phase_id'])){
					$sql .= ' and phase_id = '.$data['phase_id'];
				}
				if(!empty($data['class_id'])){
					$sql .= ' and class_id = '.$data['class_id'];
				}
				$sql .= ' order by contactdate, phase_id';
				
				break;

			case 'requestmail':
				/*****************************
				*	資料請求メールの一覧
				*	タックシールの宛名用
				*	資料請求から問い合わせ/注文確定になった割合 [ユーザーリスト,資料請求数]
				*/
				$sql = 'select * from requestmail inner join website on site_id=siteid';
				
				if(!empty($data['term_from'])){
					$sql .= ' and requestdate >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and requestdate <= "'.$data['term_to'].'"';
				}
				if(!empty($data['requester'])){
					$sql .= ' and requester LIKE "%'.$data['requester'].'%"';
				}
				if(!empty($data['site_id'])){
					$sql .= ' and site_id = '.$data['site_id'];
				}
				if(!empty($data['phase'])){
					$sql .= ' and phase = '.$data['phase'];
				}
				
				$sql .= ' order by requestdate, phase';
				
				if(isset($data['conversion'])){
					$flg = false;
					$result = exe_sql($conn, $sql);
					$tot = mysqli_num_rows($result);
					
					$sql = 'select (case when customer.cstprefix="k" then concat("K", lpad(customer.number,6,"0")) else concat("G", lpad(customer.number,4,"0")) end) as customer_num, 
						 customername, requestdate, created, schedule2, progress_id, sitename, orders_id from (((
						 customer inner join requestmail on email=reqmail)
						 inner join orders on orders.customer_id=customer.id)
						 inner join acceptstatus on orders.id=acceptstatus.orders_id)
						 inner join website on site_id=siteid
						 where email!="" and progress_id=4';
					
					if(!empty($data['term_from'])){
						$sql .= ' and requestdate >= "'.$data['term_from'].'"';
					}
					if(!empty($data['term_to'])){
						$sql .= ' and requestdate <= "'.$data['term_to'].'"';
					}
					if(!empty($data['requester'])){
						$sql .= ' and requester LIKE "%'.$data['requester'].'%"';
					}
					if(!empty($data['site_id'])){
						$sql .= ' and site_id = '.$data['site_id'];
					}
					if(!empty($data['phase'])){
						$sql .= ' and requestmail.phase = '.$data['phase'];
					}
					
					$sql .= ' group by reqid order by requestdate';
					
					$result = exe_sql($conn, $sql);
					$rs1 = array();
					while($res = mysqli_fetch_assoc($result)){
						$rs1[] = $res;
					}
					
					$rs = array($rs1, $tot);
				}
				
				break;
				
			case 'useranalyze':
				/*****************************
				*	顧客分析
				*	
				*/
				$sql = 'SELECT orders.id as orders_id, schedule3, cstprefix, number, customername, company, email, mobmail, tel, purpose, job, 
					discount1, discount2,extradiscountname, extradiscount, progressname FROM (((((orders
					 LEFT JOIN contactchecker ON orders.id=contactchecker.orders_id)
					 LEFT JOIN customer ON orders.customer_id=customer.id)
					 LEFT JOIN progressstatus ON orders.id=progressstatus.orders_id)
					 LEFT JOIN acceptstatus ON orders.id=acceptstatus.orders_id)
					 LEFT JOIN acceptprog ON acceptstatus.progress_id=acceptprog.aproid)
					 LEFT JOIN printstatus ON orders.id=printstatus.orders_id';
				$sql .= ' WHERE created>"2011-06-05"';
				
				if(!empty($data['progress_id'])){
					$sql .= ' and progress_id = '.$data['progress_id'];
				}
				if(!empty($data['id'])){
					$sql .= ' and orders.id = '.$data['id'];
				}
				if(!empty($data['term_from'])){
					$sql .= ' and schedule3 >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql .= ' and contactdate <= "'.$data['term_to'].'"';
				}
				if($data['number']!=""){
					$sql .= ' and cstprefix = "'.substr($data['number'],0,1).'"';
					$num = substr($data['number'],1);
					if(!empty($num)) $sql .= ' and number = '.ltrim($num, '0');
				}
				if(!empty($data['company'])){
					$sql .= ' and company LIKE "%'.$data['company'].'%"';
				}
				if(!empty($data['companyruby'])){
					$ruby = mb_convert_encoding($data['companyruby'], 'euc-jp', 'utf-8');
					$ruby_hira = mb_convert_encoding(mb_convert_kana($ruby,"HVc"),'utf-8','euc-jp');
					$ruby_zenkata = mb_convert_encoding(mb_convert_kana($ruby,"KVC"),'utf-8','euc-jp');
					$ruby_hankata = mb_convert_encoding(mb_convert_kana($ruby,"kh"),'utf-8','euc-jp');

					$sql .= ' and (companyruby LIKE "%'.$ruby_hira.'%" OR';
					$sql .= ' companyruby LIKE "%'.$ruby_zenkata.'%" OR';
					$sql .= ' companyruby LIKE "%'.$ruby_hankata.'%")';
				}
				if(!empty($data['customername'])){
					$zenkaku_space = mb_convert_kana($data['customername'],"S", 'utf-8');
					$hankaku_space = mb_convert_kana($data['customername'],"s", 'utf-8');
					
					$sql .= ' and (customername LIKE "%'.$data['customername'].'%"';
					$sql .= ' or customername LIKE "%'.$zenkaku_space.'%"';
					$sql .= ' or customername LIKE "%'.$hankaku_space.'%")';
				}
				if(!empty($data['customerruby'])){
					$ruby = mb_convert_encoding($data['customerruby'], 'euc-jp', 'utf-8');
					$ruby_hira = mb_convert_encoding(mb_convert_kana($ruby,"HVc"),'utf-8','euc-jp');
					$ruby_zenkata = mb_convert_encoding(mb_convert_kana($ruby,"KVC"),'utf-8','euc-jp');
					$ruby_hankata = mb_convert_encoding(mb_convert_kana($ruby,"kh"),'utf-8','euc-jp');

					$sql .= ' and (customerruby LIKE "%'.$ruby_hira.'%" OR';
					$sql .= ' customerruby LIKE "%'.$ruby_zenkata.'%" OR';
					$sql .= ' customerruby LIKE "%'.$ruby_hankata.'%")';
				}
				
				/* 新タイプに移行後に適用
				if(!empty($data['medianame'])){
					$sql .= ' and medianame = "'.$data['medianame'].'"';
				}
				if(!empty($data['contactby'])){
					$sql .= ' and contactby = "'.$data['contactby'].'"';
				}
				if(!empty($data['contactfor'])){
					$sql .= ' and contactfor = "'.$data['contactfor'].'"';
				}
				$sql .= ' group by orders.id order by schedule3, medianame,contactfor,contactby';
				*/
				
				$sql .= ' group by orders.id order by schedule3';
				
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$rs[] = $res;
					$r1[$res['orders_id']] = $res;
					$ordersid[] = $res['orders_id'];
				}
				if(empty($r1)) return array();
				
				/* 旧タイプに対応 */
				if(!empty($data['medianame']) || !empty($data['contactby']) || !empty($data['contactfor'])){
					$sql = 'select * from media where orders_id in ('.implode(',',$ordersid).')';
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$r2[$res['orders_id']][$res['media_type']] = $res['media_value'];
					}
					
					$rs = array();
					$ordersid = array();
					
					foreach($r1 as $id=>$line){
						if(!empty($r2[$id])){
							$isCHK = 0;
							if(!empty($data['medianame'])){
								if($r2[$id]['mediacheck01']==$data['medianame']) $isCHK = 1;
								else $isCHK = -1;
							}
							if(!empty($data['contactby']) && $isCHK>-1){
								if($r2[$id]['mediacheck02']==$data['contactby']) $isCHK = 1;
								else $isCHK = -1;
							}
							if(!empty($data['contactfor']) && $isCHK>-1){
								if($data['contactfor']!='others'){
									if($r2[$id]['mediacheck03']==$data['contactfor']) $isCHK = 1;
									else $isCHK = -1;
								}else{
									if(	$r2[$id]['mediacheck03']!='estimate' &&
										$r2[$id]['mediacheck03']!='order' &&
										$r2[$id]['mediacheck03']!='delivery' &&
										$r2[$id]['mediacheck03']!=''
									){
										$isCHK = 1;
									}else{
										$isCHK = -1;
									}
								}
							}
							if($isCHK==1){
								$line['medianame'] = $r2[$id]['mediacheck01'];
								$line['contactby'] = $r2[$id]['mediacheck02'];
								$line['contactfor'] = $r2[$id]['mediacheck03'];
								
								$rs[] = $line;
								$ordersid[] = $line['orders_id'];
							}
						}
					}
				}
				
				
				// CSVファイル
				if($data['mode']=='print'){
					
					// 割引単独
					$sql = 'select discount_name from discount where discount_state=1 and orders_id in ('.implode(',', $ordersid).')';
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						$r3[$res['orders_id']][] = $res['discount_name'];
					}
					
					
					// 商品種類
					$sql = 'SELECT * FROM ((orderitem';
					$sql .= ' left join orderitemext ON orderitem.id=orderitemext.orderitem_id)';
					$sql .= ' left join catalog on orderitem.master_id=catalog.id)';
					$sql .= ' left join category on catalog.category_id=category.id';
					$sql .= ' where orderitem.orders_id in ('.implode(',', $ordersid).')';
					$result = exe_sql($conn, $sql);
					while($res = mysqli_fetch_assoc($result)){
						if($res['master_id']==0){
							$category_name = $res['item_name'];
						}else{
							$category_name = $res['category_name'];
						}
						
						if(strpos($r4[$res['orders_id']]['name'], $category_name)===false){
							$r4[$res['orders_id']]['name'] = '\n'.$category_name;
						}
						
						$r4[$res['orders_id']]['vol'] += $res['amount'];
					}
					
					
					for($i=0; $i<count($ordersid); $i++){
						$id = $ordersid[$i];
						if(!empty($r3[$id])){
							$rs[$i]['discount_name'] = implode("\n", $r3[$id]);
						}else{
							$rs[$i]['discount_name'] = '';
						}
						if(!empty($r4[$id])){
							$rs[$i]['category_name'] = substr($r4[$id]['name'],2);
							$rs[$i]['item_volume'] = $r4[$id]['vol'];
						}else{
							$rs[$i]['category_name'] = '';
							$rs[$i]['item_volume'] = '0';
						}
					}
				}
				
				$flg = false;
				break;
				
			case 'stafflist':
				/*****************************
				*	スタッフリストの取得
				*	@rowid		1:受注、2:版下、3:製版。4:転写紙、5:入荷、6:プリント
				*	@tarm_from	検索期間開始
				*	@term_to	検索期間終了
				*
				*	return [担当者データ]
				*/
				$sql = sprintf('SELECT * FROM staff where staffapply<="%s" and staffdate>"%s" and %s>0 order by %s ASC', 
								$data['term_to'], $data['term_from'], $data['rowid'], $data['rowid']);
				
				break;
				
			case 'itemtag':
				/*****************************
				*	タグの取得
				*	@tagid
				*/
				$sql = sprintf('select * from itemtag where tag_id=%d', $data['tagid']);
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$rs[$res['tag_itemid']] = $res;
				}
				
				$flg = false;
				break;
				
			case 'getColorcode':
				/*****************************
				*	アイテムカラー名とアイテムIDからカラーコードを返す
				*	@item_id		アイテムID
				*	@item_color		カラー名
				*	
				*	return			カラーコード
				*/
				$sql = sprintf("select color_code from catalog inner join itemcolor on color_id=itemcolor.id where item_id=%d and color_name='%s'", $data['item_id'], $data['item_color']);
				$result = exe_sql($conn, $sql);
				$rec = mysqli_fetch_array($result);
				$rs = $rec['color_code'];
				
				$flg = false;
				break;
				
			case 'mailhistory':
				$sql = 'select * from mailhistory left join staff on staff_id=staff.id';
				
				$sql2 = array();
				if(!empty($data['term_from'])){
					$sql2[] = 'sendmaildate >= "'.$data['term_from'].'"';
				}
				if(!empty($data['term_to'])){
					$sql2[] = 'sendmaildate <= "'.$data['term_to'].'"';
				}
				if(!empty($data['subject'])){
					$sql2[] = ' subject = "'.$data['subject'].'"';
				}
				if(!empty($data['email'])){
					$sql2[] = ' mailaddr = "'.$data['email'].'"';
				}
				if($data['number']!=""){
					$sql2[] = ' cst_prefix = "'.substr($data['number'],0,1).'"';
					$num = substr($data['number'],1);
					if(!empty($num)) $sql2[] = ' cst_number = '.ltrim($num, '0');
				}
				if(!empty($data['customername'])){
					$zenkaku_space = mb_convert_kana($data['customername'],"S", 'utf-8');
					$hankaku_space = mb_convert_kana($data['customername'],"s", 'utf-8');
					$sql2[] = ' (cst_name LIKE "%'.$data['customername'].'%" or cst_name LIKE "%'.$zenkaku_space.'%" or cst_name LIKE "%'.$hankaku_space.'%")';
				}
				if(!empty($data['staff_id'])){
					$sql2[] = ' staff_id = '.$data['staff_id'];
				}
				if(!empty($data['orders_id'])){
					$sql2[] = ' orders_id = '.$data['orders_id'];
				}
				
				if(!empty($sql2)){
					$sql .= ' where '.implode(' and ', $sql2);
				}
				
				$sql .= ' order by sendmaildate';
				
				break;
				
			case 'handover':
			/*
			*	引渡し確認メール送信の確認、一般のみ
			*	return		1:送信可、　0:送信不可
			*/
				$rs = 0;
				$param = array( 'orders_id'=>$data['orders_id'],
								'subject'=>4,
								);
				$tmp = $this->search($conn, 'mailhistory', $param);
				if(!empty($tmp)){
					return 2;		// 送信済み
				}
				
				$sql = 'select count(*) as count from (((orders';
				$sql .= ' inner join customer on customer_id=customer.id)';
				$sql .= ' inner join printstatus on orders.id=printstatus.orders_id)';
				$sql .= ' inner join acceptstatus on orders.id=acceptstatus.orders_id)';
				$sql .= ' inner join progressstatus on orders.id=progressstatus.orders_id';
				$sql .= ' where created>"2011-06-05" and progress_id=4 and carriage="accept" and schedule3="'.date('Y-m-d').'"';
				$sql .= ' and ordertype="general"';
				$sql .= ' and ( (printtype_key="noprint" and state_7>0) or (printtype_key="silk" and fin_5>0) or (printtype_key="inkjet" and fin_6>0)';
				$sql .= ' or (printtype_key!="silk" and printtype_key!="inkjet" and fin_4>0) ) and orders.id=%d';
				$sql = sprintf($sql, $data['orders_id']);
				
				$result = exe_sql($conn, $sql);
				$res = mysqli_fetch_assoc($result);
				if($res['count']>0){
					$now = time();
					$year  = date("Y", $now);
					$month = date("m", $now);
					$day   = date("d", $now);
					$target = mktime(10, 0, 0, $month, $day, $year);	// 発送日の10:00
					
					if($now>$target){
						$rs = 1;
					}
				}
				
				$flg = false;
				break;
				
			case 'userreview':
			/*
			*	ユーザーレビュー（428HP）
			*/
				$sql = 'select * from userreview';
				if(!empty($data['urid'])){
					$query[] = 'urid='.$data['urid'];
				}
				if(!empty($data['yy'])){
					if(empty($data['mm'])){
						$query[] = '(posted>="'.$data['yy'].'-01-01" and posted<="'.$data['yy'].'-12-31")';
					}else{
						$query[] = '(posted>="'.$data['yy'].'-'.$data['mm'].'-01" and posted<=last_day("'.$data['yy'].'-'.$data['mm'].'-01"))';
					}
				}
				if(!empty($data['item_code'])){
					$query[] = 'userreview.item_name like "%'.$data['item_code'].'%"';
				}
				if(!empty($data['vote_1'])){
					$query[] = 'vote_1='.$data['vote_1'];
				}
				if(!empty($data['vote_2'])){
					$query[] = 'vote_2='.$data['vote_2'];
				}
				if(!empty($data['vote_3'])){
					$query[] = 'vote_3='.$data['vote_3'];
				}
				if(!empty($data['vote_4'])){
					$query[] = 'vote_4='.$data['vote_4'];
				}
				if(!empty($data['printkey'])){
					$query[] = 'printkey like "%'.$data['printkey'].'%"';
				}
				if(!empty($query)){
					if($isWhere==true){
						$sql .= ' and '.implode(' and ', $query);
					}else{
						$sql .= ' where '.implode(' and ', $query);
					}
				}
				$sql .= ' group by urid';
				$sql .= ' order by posted, urid';
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$ids = explode('|', $res['item_id']);
					for($i=0; $i<count($ids); $i++){
						if($ids[$i]==0){
							$res['category_id'][] = 0;
						}else{
							$today = date('Y-m-d');
							$sql2 = 'select category_id from catalog inner join item on item_id=item.id';
							$sql2 .= ' where catalogapply<="%s" and catalogdate>"%s" and itemapply<="%s" and itemdate>"%s" and item_id=%d';
							$sql2 .= ' group by item_id';
							$sql2 = sprintf($sql2, $today,$today,$today,$today,$ids[$i]);
							$r = exe_sql($sql2);
							if(mysqli_num_rows($r)>0){
								$rec = mysqli_fetch_assoc($r);
								$res['category_id'][] = $rec['category_id'];
							}else{
								$res['category_id'][] = 0;
							}
						}
					}
					$rs[] = $res;
				}
				
				$flg = false;
				break;
				
			case 'itemreview':
			/*
			*	アイテムレビュー（428HP）
			*	検索日時点の取扱商品のみを対象
			*/
				$today = date('Y-m-d');
				$query = array();
				$sql = 'select *, itemreview.item_name as item_name, ';
				$sql .= 'coalesce(item.item_code, "") as itemcode, coalesce(category_id, 0) as category_id from ((itemreview';
				$sql .= ' left join item on itemreview.item_id=item.id)';
				$sql .= ' left join catalog on item.id=catalog.item_id)';
				$sql .= ' left join category on catalog.category_id=category.id';
				$sql .= ' where catalogapply<="'.$today.'" and catalogdate>"'.$today.'" and itemapply<="'.$today.'" and itemdate>"'.$today.'"';
				$isWhere = true;
				
				if(!empty($data['irid'])){
					$query[] = 'irid='.$data['irid'];
				}
				/*
				if(!empty($data['yy'])){
					if(empty($data['mm'])){
						$query[] = '(posted>="'.$data['yy'].'-01-01" and posted<="'.$data['yy'].'-12-31")';
					}else{
						$query[] = '(posted>="'.$data['yy'].'-'.$data['mm'].'-01" and posted<=last_day("'.$data['yy'].'-'.$data['mm'].'-01"))';
					}
				}
				if(!empty($data['item_code']) && $data['category_id']!=99){
					$query[] = 'item_code="'.mb_convert_kana($data['item_code'], 'as', 'utf-8').'"';
				}
				*/
				if(!empty($data['item_name'])){
					$query[] = 'itemreview.item_name like "%'.$data['item_name'].'%"';
				}
				if(!empty($data['vote'])){
					$query[] = 'vote='.$data['vote'];
				}
				if(!empty($data['printkey'])){
					$query[] = 'printkey like "%'.$data['printkey'].'%"';
				}
				if(!empty($query)){
					if($isWhere==true){
						$sql .= ' and '.implode(' and ', $query);
					}else{
						$sql .= ' where '.implode(' and ', $query);
					}
				}
				$sql .= ' group by irid';
				$sql .= ' order by irid';
				
				break;
				
			case 'bundlelist':
			/*
			*	同梱可能リスト
			*/
				if(empty($data['orders_id'])) return;
				
				$sql = sprintf('select id, schedule3, customer_id, delivery_id from orders where id=%d', $data['orders_id']);
				$r = exe_sql($conn, $sql);
				if(mysqli_num_rows($r)>0){
					$rec = mysqli_fetch_assoc($r);
				}else{
					return;
				}
				$sql = 'select * from (orders';
				$sql .= ' inner join acceptstatus on orders.id=acceptstatus.orders_id)';
				$sql .= ' inner join progressstatus on orders.id=progressstatus.orders_id';
				$sql .= ' where progress_id=4 and shipped=1 and schedule3="%s" and customer_id=%d and delivery_id=%d';
				$sql .= ' order by orders.id';
				
				$sql = sprintf($sql, $rec['schedule3'],$rec['customer_id'],$rec['delivery_id']);
				
				break;
				
			case 'bundlecount':
			/*
			*	同梱する注文データの配列または空配列
			*/
				$sql = sprintf('select id, schedule3, customer_id, delivery_id from orders where bundle=1 and id=%d', $data['orders_id']);
				$r = exe_sql($conn, $sql);
				if(mysqli_num_rows($r)>0){
					$rec = mysqli_fetch_assoc($r);
				}else{
					return array();
				}
				if(isset($data['all'])){
					$sql = 'select * from (orders';
				}else{
					$sql = 'select id from (orders';
				}
				$sql .= ' inner join acceptstatus on orders.id=acceptstatus.orders_id)';
				$sql .= ' inner join progressstatus on orders.id=progressstatus.orders_id';
				$sql .= ' where bundle=1 and schedule3="%s" and customer_id=%d and delivery_id=%d';
				if(isset($data['progress'])){
					if($data['progress']!=0){
						$sql .= ' and progress_id='.$data['progress'];
					}
				}else{
					$sql .= ' and progress_id=4';
				}
				if(isset($data['shipped'])){
					$sql .= ' and shipped='.$data['shipped'];
				}
				$sql .= ' order by orders.id';
				
				$sql = sprintf($sql, $rec['schedule3'],$rec['customer_id'],$rec['delivery_id']);
				
				break;
				
			}

			if($flg){
				$result = exe_sql($conn, $sql);
				while($res = mysqli_fetch_assoc($result)){
					$rs[] = $res;
				}
			}
		}catch(Exception $e){
			$rs = null;
		}

		return $rs;
	}

	/***************************************************************************************************************
	*	サイズ名でソートする
	*	usortのユーザー定義関数
	*	search product で使用
	*/
	private function sort_size($a, $b){
		$tmp=array(
	    	'70'=>1,'80'=>2,'90'=>3,'100'=>4,'110'=>5,'120'=>6,'130'=>7,'140'=>8,'150'=>9,'160'=>10,
	    	'JS'=>11,'JM'=>12,'JL'=>13,'WS'=>14,'WM'=>15,'WL'=>16,'GS'=>17,'GM'=>18,'GL'=>19,
	    	'SSS'=>20,'SS'=>21,'XS'=>22,
	    	'S'=>23,'M'=>24,'L'=>25,'XL'=>26,
	    	'XXL'=>27,
	    	'O'=>28,'XO'=>29,'2XO'=>30,'YO'=>31,
	    	'3L'=>32,'4L'=>33,'5L'=>34,'6L'=>35,'7L'=>36,'8L'=>37);
		return ($tmp[$a["size"]] == $tmp[$b["size"]]) ? 0 : ($tmp[$a["size"]] < $tmp[$b["size"]]) ? -1 : 1;
	}
	
	// カテゴリー、アイテム名、カラー、サイズ（search product で使用）
	private function multiSort($args){
		$tmp=array(
			'70'=>1,'80'=>2,'90'=>3,'100'=>4,'110'=>5,'120'=>6,'130'=>7,'140'=>8,'150'=>9,'160'=>10,
	    	'JS'=>11,'JM'=>12,'JL'=>13,'WS'=>14,'WM'=>15,'WL'=>16,'GS'=>17,'GM'=>18,'GL'=>19,
	    	'SSS'=>20,'SS'=>21,'XS'=>22,
	    	'S'=>23,'M'=>24,'L'=>25,'XL'=>26,
	    	'XXL'=>27,
	    	'O'=>28,'XO'=>29,'2XO'=>30,'YO'=>31,
	    	'3L'=>32,'4L'=>33,'5L'=>34,'6L'=>35,'7L'=>36,'8L'=>37);
		
		/*
		$k1 = 'category_id';
		if($a[$k1] == $b[$k1]){
			$k2 = 'size_name';
			return ($tmp[$a[$k2]] == $tmp[$b[$k2]]) ? 0 : ($tmp[$a[$k2]] < $tmp[$b[$k2]]) ? -1 : 1;
		}else{
			return ($a[$k1] < $b[$k1]) ? -1 : 1;
		}
		*/
		
		for($i=0; $i<count($args); $i++){
			$a[$i] = $args[$i]['category_id'];
			$b[$i] = $args[$i]['item_name'];
			$c[$i] = $args[$i]['color_code'];
			$d[$i] = $tmp[$args[$i]['size_name']];
		}
		array_multisort($a,$b,$c,$d, $args);
		
		return $args;
	}
	
	// カテゴリー、アイテム、サイズ（search orderitem で使用）
	private function multiSort2($a, $b){
	    $tmp=array(
	    	'70'=>1,'80'=>2,'90'=>3,'100'=>4,'110'=>5,'120'=>6,'130'=>7,'140'=>8,'150'=>9,'160'=>10,
	    	'JS'=>11,'JM'=>12,'JL'=>13,'WS'=>14,'WM'=>15,'WL'=>16,'GS'=>17,'GM'=>18,'GL'=>19,
	    	'SSS'=>20,'SS'=>21,'XS'=>22,
	    	'S'=>23,'M'=>24,'L'=>25,'XL'=>26,
	    	'XXL'=>27,
	    	'O'=>28,'XO'=>29,'2XO'=>30,'YO'=>31,
	    	'3L'=>32,'4L'=>33,'5L'=>34,'6L'=>35,'7L'=>36,'8L'=>37);
	    $k1 = 'category_id';
	    if($a[$k1] == $b[$k1]){
	    	$k1 = 'master_id';
	    	if($a[$k1] == $b[$k1]){
	    		$k1 = 'item_name';
	    		if($a[$k1] == $b[$k1]){
	    			$k1 = 'size_name';
		    		return ($tmp[$a[$k1]] == $tmp[$b[$k1]]) ? 0 : ($tmp[$a[$k1]] < $tmp[$b[$k1]]) ? -1 : 1;
	    		}else{
	    			return ($a[$k1] < $b[$k1]) ? -1 : 1;
	    		}
	    	}else{
	    		return ($a[$k1] < $b[$k1]) ? -1 : 1;
	    	}
	    }else{
	    	return ($a[$k1] < $b[$k1]) ? -1 : 1;
	    }
	}
	
	/* 
	*	絵型の表示をソート(public)
	*	order by printposition_id, selective_key
	*/
	public function sortSelectivekey($args){
		$tmp = array(
			"mae"=>1,
			"mae_mini"=>1,
			"jacket_mae_mini"=>1,
			"mae_mini_2"=>1,
			"parker_mae_mini_2"=>1,
			"parker_mae_mini_zip "=>1,
			"apron_mae"=>1,
			"tote_mae"=>1,
			"short_apron_mae"=>1,
			"cap_mae"=>1,
			"visor_mae "=>1,
			"active_mae"=>1,
			"army_mae"=>1,
			
			"mae_hood"=>2,
			"short_apron_ue"=>2,
			
			"mune_right"=>3,
			"parker_mune_right"=>3,
			"active_mune_right"=>3,
			"cap_mae_right"=>3,
			"boxerpants_right"=>3,
			"shirt_mune_right"=>3,
			"game_pants_suso_right"=>3,
			
			"pocket"=>4,
			"parker_mae_pocket"=>4,
			"apron_pocket"=>4,
			"short_apron_pocket"=>4,
			
			"mune_left"=>5,
			"parker_mune_left"=>5,
			"active_mune_left"=>5,
			"polo_mune_left"=>5,
			"cap_mae_left"=>5,
			"boxerpants_left"=>5,
			"game_pants_suso_left"=>5,
			
			"suso_left"=>6,
			"apron_suso_left"=>6,
			"shirt_suso_left"=>6,
			
			"suso_mae"=>7,
			
			"suso_right"=>8,
			"shirt_suso_right"=>8,
			
			
			"mae_right"=>9,
			"workwear_mae_right"=>9,
			
			"mae_suso_right"=>10,
			"boxerpants_suso_right"=>10,
			
			"mae_momo_right"=>11,
			"workwear_mae_momo_right"=>11,
			
			"mae_hiza_right"=>12,
			"workwear_mae_hiza_right"=>12,
			
			"mae_asi_right"=>13,
			"workwear_mae_asi_right"=>13,
			
			
			"mae_left"=>14,
			"workwear_mae_left"=>14,
			
			"mae_suso_left"=>15,
			"boxerpants_suso_left"=>15,
			
			"mae_momo_left"=>16,
			"workwear_mae_momo_left"=>16,
			
			"mae_hiza_left"=>17,
			"workwear_mae_hiza_left"=>17,
			
			"mae_asi_left"=>18,
			"workwear_mae_asi_left"=>18,
			
			"happi_sode_left"=>19,
			"happi_mune_left"=>19,
			"happi_maetate_left"=>19,
			"happi_sode_right"=>19,
			"happi_mune_right"=>19,
			"happi_maetate_right"=>19,
			
			"towel_center"=>20,
			"towel_left"=>20,
			"towel_right"=>20,
			
			
			
			"usiro"=>21,
			"usiro_mini"=>21,
			"parker_usiro"=>21,
			"bench_usiro"=>21,
			"best_usiro"=>21,
			"tote_usiro"=>21,
			"cap_usiro"=>21,
			"active_cap_usiro"=>21,
			
			"eri"=>22,
			"kubi_usiro"=>22,
			"shirt_long_kubi_usiro"=>22,
			"shirt_short_kubi_usiro"=>22,
			
			"usiro_suso_left"=>23,
			"shirt_usiro_suso_left"=>23,
			
			"usiro_suso"=>24,
			
			"usiro_suso_right"=>25,
			"shirt_usiro_suso_right"=>25,
			
			"osiri"=>26,
			"pants_osiri"=>26,
			"boxerpants_osiri"=>26,
			
			
			"usiro_left"=>27,
			"pants_usiro_left"=>27,
			"workwear_usiro_left"=>27,
			
			"pants_usiro_suso_left"=>28,
			"boxerpants_usiro_suso_left"=>28,
			"game_pants_usiro_suso_left"=>28,
			
			"usiro_momo_left"=>29,
			"workwear_usiro_momo_left"=>29,
			
			"usiro_hiza_left"=>30,
			"workwear_usiro_hiza_left"=>30,
			
			"usiro_asi_left"=>31,
			"workwear_usiro_asi_left"=>31,
			
			"usiro_right"=>32,
			"pants_usiro_right"=>32,
			"workwear_usiro_right"=>32,
			
			"pants_usiro_suso_right"=>33,
			"boxerpants_usiro_suso_right"=>33,
			"game_pants_usiro_suso_right"=>33,
			
			"usiro_momo_right"=>34,
			"workwear_usiro_momo_right"=>34,
			
			"usiro_hiza_right"=>35,
			"workwear_usiro_hiza_right"=>35,
			
			"usiro_asi_right"=>36,
			"workwear_usiro_asi_right"=>36,
			
			
			
			"sode_right"=>37,
			"sode_right2"=>37,
			
			"hood_right"=>38,
			
			"long_sode_right"=>39,
			"trainer_sode_right"=>39,
			"parker_sode_right"=>39,
			"blouson_sode_right"=>39,
			"coat_sode_right"=>39,
			"boxerpants_side_right"=>39,
			"shirt_sode_right"=>39,
			"shirt_long_sode_right"=>39,
			
			"long_ude_right"=>40,
			"trainer_ude_right"=>40,
			"parker_ude_right"=>40,
			"blouson_ude_right"=>40,
			"coat_ude_right"=>40,
			"shirt_long_ude_right"=>40,
			
			"long_sodeguti_right"=>41,
			"trainer_sodeguti_right"=>41,
			
			"long_waki_right"=>42,
			"waki_right"=>42,
			"waki_right2"=>42,
			
			"sode_left"=>43,
			"sode_left2"=>43,
			
			"hood_left"=>44,
			
			"long_sode_left"=>45,
			"trainer_sode_left"=>45,
			"parker_sode_left"=>45,
			"blouson_sode_left"=>45,
			"coat_sode_left"=>45,
			"boxerpants_side_left"=>45,
			"shirt_sode_left"=>45,
			"shirt_long_sode_left"=>45,
			
			"long_ude_left"=>46,
			"trainer_ude_left"=>46,
			"parker_ude_left"=>46,
			"blouson_ude_left"=>46,
			"coat_ude_left"=>46,
			"shirt_long_ude_left"=>46,
			
			"long_sodeguti_left"=>47,
			"trainer_sodeguti_left"=>47,
			
			"long_waki_left"=>48,
			"waki_left"=>48,
			"waki_left2"=>48,
			
			"cap_side_right"=>49,
			"active_cap_side_right"=>49,
			
			"cap_side_left"=>50,
			"active_cap_side_left"=>50
		);
		
		
		foreach($args as $key=>$val){
			$a[$key] = $val['printposition_id'];
			$b[$key] = $tmp[$val['selective_key']];
		}
		array_multisort($a,$b, $args);
		
		return $args;
	}
	
}
?>