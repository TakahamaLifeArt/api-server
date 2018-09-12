<?php
/**
*	タカハマラフアート
*	プリント代計算　クラス
*	charset utf-8
*/

require_once dirname(__FILE__).'/MYDB2.php';
require_once dirname(__FILE__).'/master.php';

class Calc extends Master {

/**
 * printfee2			シルクとデジタル転写及びインクジェットで最安のプリント代を返す。但しインクジェットはプリント可能なアイテムのみ
 * calcSilkPrintFee		シルクスクリーンのプリント代を返す
 * calcInkjetFee		インクジェット（白Ｔ:inkjetと黒Ｔ:darkinkjet）のプリント代を返す
 * calcCuttingFee		カッティングのプリント代を返す
 * calcDigitFee			デジタル転写のプリント代を返す
 * calcEmbroideryFee	刺繍のプリント代を返す
 *
 * ----- 2017-05-25 プリント代計算の仕様変更に伴い廃止
 * estimateEach			（廃止）アイテムごとに、シルクとデジタル転写とインクジェットで最安の見積りを計算
 * printfee				（廃止）シルクとデジタル転写及びインクジェットで最安のプリント代を返す。但しインクジェットはプリント可能なアイテムのみ
 * calcTransFee			（廃止）転写のプレス代を返す（デジタル:digit、カラー（白Ｔ:transと黒Ｔ:darktrans））
 * calcTransCommonFee	（廃止）転写の版代とシート代を返す（デジタル:digit、カラー（白Ｔ:transと黒Ｔ:darktrans））
 *
 *	(private)
 * getSheetCount		（廃止）(Static)転写のシート数と版数を返す（デジタル、カラー（白Ｔと黒Ｔ））
 * getPermutation		（廃止）プリント方法の組み合わせパターンを返す
 * permute				（廃止）重複順列のパターンを取得する再帰モジュール
 * getPrintRatio		（廃止）アイテムのプリント割増率を返す
 * getIteminfo			（廃止）アイテムIDから商品情報を返す(category_idを取得)
 */
	
//	private $max_ink_count = 3;							// インクが3色より多い場合は転写
//	private $sheet_size = 1;							// 転写のデザインサイズ（シートに対する割合）1:大　0.5:中　0.25:小
//	private $design_size = 0;							// インクジェットのデザインの大きさ。0:大　1:中　2:小
//	private $calcType = array('setting'=>'2014-03-01');	// この日より組付け代に割増率を適用しない
	
	private $curdate;
	private $conn;
	
	public function __construct($curdate='', $sheetsize='1', $designsize='0'){
//		if($sheetsize=='1' || $sheetsize=='0.5' || $sheetsize=='0.25'){
//			$this->sheet_size = $sheetsize;
//		}
//		if($designsize=='0' || $designsize=='1' || $designsize=='2'){
//			$this->design_size = $designsize;
//		}
		$this->curdate = parent::validdate($curdate);
		$this->conn = MYDB2::getConnection();
	}
	
	
	public function __destruct(){
		$this->conn->close();
	}
	
	
	/**
	*	Webサイトからのリクエストで、シルクとデジタル転写とインクジェットで最安のプリント代合計を返す
	*	デジ転とインクジェットのデザインサイズは{0:大}で固定。タオルのシルクプリントは{2:スーパージャンボ版}で固定。
	*	@param {array} args [アイテムID、枚数、インク色数、プリント位置、デザインサイズ、インクジェットオプション[0(淡色):枚数,1(濃色):枚数]、インクジェット[1:可,0:不可]]
	*						[itemid, amount, ink, pos, size, option, inkjet][...]
	*
	*	@return {array|NULL} ['printfee':プリント代合計金額, 'printing':[箇所名-デザインサイズID-インク色数:[プリント方法:プリント代]], 'tax':消費税率]　引数に配列以外を設定した時はNULL
	*/
	public function printfee2($args){
		if (!is_array($args) || empty($args)) return null;
		
		try {
			// アイテム毎の枚数
			$len = count($args);
			for ($i=0; $i<$len; $i++) {
				if (empty($args[$i]['itemid'])) throw new Exception();
				$itemAmount[$args[$i]['itemid']] = $args[$i]['amount'];
			}
			$itemLen = count($itemAmount);
			
			// 枚数レンジ分類、シルク同版分類、カテゴリーを取得
			$sql = 'SELECT item_id, item_code, item_group1_id, item_group2_id, category_id FROM item inner join catalog on item.id=item_id';
			$sql .= ' where item.id in('.implode( ' , ', array_fill(0, $itemLen, '?') ).') and lineup=1 and lineup=1 and itemapply<=? and itemdate>? group by item.id';
			$stmt = $this->conn->prepare($sql);
			$marker = '';
			$arr = array();
			$stmtParams = array();
			foreach ($itemAmount as $id=>$vol) {
				$marker .= 'i';
				$arr[] = $id;
			}
			array_push($arr, $this->curdate,$this->curdate);
			$marker .= 'ss';
			array_unshift($arr, $marker);
			foreach ($arr as $key => $value) {
				$stmtParams[$key] =& $arr[$key];	// bind_paramへの引数を参照渡しにする
			}
			call_user_func_array(array($stmt, 'bind_param'), $stmtParams);
			$stmt->execute();
			$stmt->store_result();
			$r = MYDB2::fetchAll($stmt);
			$stmt->close();
			if (empty($r)) throw new Exception();
			$recLen = count($r);
			for ($i=0; $i<$recLen; $i++) {
				$group1[$r[$i]['item_id']] = $r[$i]['item_group1_id'];		// 枚数レンジ分類
				$group2[$r[$i]['item_id']] = $r[$i]['item_group2_id'];		// シルク同版分類
				$category[$r[$i]['item_id']] = $r[$i]['category_id'];		// カテゴリー
				$code[$r[$i]['item_id']] = $r[$i]['item_code'];				// アイテムコード
			}
			
			// パラメータを整形
			// param[print_type][pos_name][sect][grp] = {'ids':{}, 'vol':0, 'ink':0, 'size':0, 'opt':[], 'repeat',{}};
			$param = array();
			for ($i=0; $i<$len; $i++) {
				$v = $args[$i];
				$sect = $v['size'].'-'.$v['ink'];
				// タオルの場合はスーパージャンボ版にする
				// 但し519-htと540-hktは除く
				if ($category[$v['itemid']]==8 && ($code[$v['itemid']]!='519-ht' && $code[$v['itemid']]!='540-hkt')) {
					$v['size'] = 2;
				}
				$param[ $v['pos'] ][$sect][ $group1[$v['itemid']] ]['ids'][$v['itemid']] = $itemAmount[$v['itemid']];
				$param[ $v['pos'] ][$sect][ $group1[$v['itemid']] ]['vol'] += $v['amount'];
				$param[ $v['pos'] ][$sect][ $group1[$v['itemid']] ]['ink'] = $v['ink'];
				$param[ $v['pos'] ][$sect][ $group1[$v['itemid']] ]['size'] = $v['size'];
				$param[ $v['pos'] ][$sect][ $group1[$v['itemid']] ]['repeatSilk'][ $group2[$v['itemid']] ] = 0;
				$param[ $v['pos'] ][$sect][ $group1[$v['itemid']] ]['repeat'] = 0;
				foreach ($v['option'] as $idx=>$amount) {
					$param[ $v['pos'] ][$sect][ $group1[$v['itemid']] ]['opt'][$idx] += $amount;
				}
				if (empty($v['inkjet'])) {
					// すべてのアイテムがインクジェット対応可能な場合だけ、インクジェットのプリント代を計算する
					$param[ $v['pos'] ][$sect][ $group1[$v['itemid']] ]['inkjet'] = false;
				} else if ($param[ $v['pos'] ][$sect][ $group1[$v['itemid']] ]['inkjet']!==false) {
					$param[ $v['pos'] ][$sect][ $group1[$v['itemid']] ]['inkjet'] = true;
				}
			}
			
			// 最安のプリント方法を判定
			$min_tot = 0;			// プリント代合計
			$printing = array();	// プリント方法毎のプリント代
			$detail = array();		// 詳細情報
			foreach ($param as $posName=>$sect) {			// プリント箇所
				foreach ($sect as $design=>$group) {		// デザイン（インク数、版種類、オプションの別）
					$g1Count = 0;							// デジ転の枚数レンジの種類のカウント用
					$digitPlateCharge = array();			// デジ転の版代集計用
					$g2 = array();							// シルク同版分類IDのチェック用
					$silkPlateCharge = array();				// シルク同版分類による版代集計用
					$print_fee = array();					// プリント方法毎に集計
					foreach ($group as $rangeId=>$val) {	// 枚数レンジ分類
						$tmp = array('silk'=>0, 'digit'=>0, 'inkjet'=>0);
						
						// シルク同版分類をチェック
						foreach ($val['repeatSilk'] as $g2Id=>$repeat) {
							if (isset($g2[$g2Id])) {
								$val['repeatSilk'][$g2Id] = 2;
							} else {
								$g2[$g2Id] = true;
							}
						}
						
						// シルク
						$tmp['silk'] = $this->calcSilkPrintFee($val['vol'], $val['ink'], $val['ids'], $val['size'], $val['repeatSilk']);
						
						// シルクの版代を一時集計、同版分類が同じで且つ枚数レンジ分類が違うアイテムに対応するため
//						foreach ($tmp['plates'] as $g2Id=>$charge) {
//							$silkPlateCharge[$g2Id]['fee'] += $charge;
//							$len = count($tmp['group2'][$g2Id]);
//							for ($i=0; $i<$len; $i++) {
//								$itemId = $tmp['group2'][$g2Id][$i];
//								$silkPlateCharge[$g2Id]['vol'] += $val['ids'][$itemId];
//							}
//							if (empty($silkPlateCharge[$g2Id]['item'])) {
//								$silkPlateCharge[$g2Id]['item'] = $val['ids'];
//							} else {
//								$silkPlateCharge[$g2Id]['item'] += $val['ids'];
//							}
//						}
						
						// デジ転で同じデザインで枚数レンジ分類が違うアイテムは版代を除く
						if (++$g1Count>1) {
							$val['repeat'] = 2;
						}
						
						// デジタル転写
						$tmp['digit'] = $this->calcDigitFee($val['vol'], 0, $val['ids'], $val['repeat']);
						// デジタル転写の版代をアイテム毎に按分
//						if (!empty($tmp['plates'])) {
//							$per = $tmp['plates'] / $val['vol'];
//							foreach ($val['ids'] as $itemId=>$vol) {
//								$print_fee['item'][$itemId]['fee'] += $per * $vol;
//							}
//						}
						
						// インクジェット
						if ($val['inkjet'] === true) {
							for ($i=0; $i<2; $i++) {
								if (empty($val['opt'][$i])) continue;
								$dat = $this->calcInkjetFee($i, $val['opt'][$i], 0, $val['ids']);
								if (empty($tmp['inkjet'])) {
									$tmp['inkjet'] = $dat;
								} else {
									$tmp['inkjet']['tot'] += $dat['tot'];
									$tmp['inkjet']['press'] += $dat['press'];
									foreach ($dat['extra'] as $itemId => $charge) {
										$tmp['inkjet']['extra'][$itemId] += $charge;
									}
								}
							}
						}
						
						// プリント方法毎の合計
						foreach ($tmp as $printMethod => $dat) {
							if (empty($dat)) continue;
							$print_fee[$printMethod] += $dat['tot'];
							
							// アイテム毎に集計
//							$pressPer = $tmp['press'] / $val['vol'];
//							foreach ($val['ids'] as $itemId=>$vol) {
//								$print_fee['item'][$itemId]['fee'] += $pressPer * $vol;
//								if (!empty($tmp['extra'][$itemId])) {
//									$print_fee['item'][$itemId]['fee'] += $tmp['extra'][$itemId];
//								}
//							}
						}
					}
					
					// シルクの版代をアイテム毎に按分
					// 同版分類が同じで且つ枚数レンジ分類が違うアイテムに対応
//					if (count($silkPlateCharge) > 0) {
//						foreach ($silkPlateCharge as $g2Id=>$v) {
//							$per = $v['fee'] / $v['vol'];
//							foreach ($v['item'] as $itemId=>$amount) {
//								$print_fee['item'][$itemId]['fee'] += $per * $amount;
//							}
//						}
//					}
					
					// 箇所毎（サイズとインク色数別）に最安プリント方法を判定
					asort($print_fee, SORT_NUMERIC);
					$printName = key($print_fee);
					$fee = current($print_fee);
					$min_tot += $fee;
					$printing[$posName.'-'.$design][$printName] += $fee;
					
					// プリント方法毎の代金を保持
//					foreach($print_fee as $printName => $fee){
//						$detail[$printName] += $fee;
//					}
					
					// アイテム毎に集計
//					foreach($print_fee['item'] as $itemId=> $val){
//						$detail['item'][$itemId] = $val['fee'];
//					}
				}
			}
			
			// アイテムごとに浮動小数点を丸める
//			foreach($print_fee['item'] as &$val){
//				$val['fee'] = round($val['fee']);
//			}
//			unset($val);
			
		} catch(Exception $e) {
			$min_tot = '0';
		}

		// 消費税率
		$tax = parent::getSalesTax($this->curdate);

		return array('printfee'=>$min_tot, 'printing'=>$printing, 'tax'=>$tax);
	}
	
	
	
	/**
	 * 割増金額を抽出
	 * @param {array} itemid アイテムIDをキーにした配列
	 * @return {array|boolean} 結果の配列を返す。失敗の場合は{@code FALSE}を返す
	 */
	private function getExtraCharge($itemid){
		try {
//			$conn = parent::db_connect();
			$len = count($itemid);
			$sql = 'SELECT item.id as item_id, item_group2_id, price FROM item
				 inner join print_group on print_group.id=print_group_id
				 where item.id in('.implode( ' , ', array_fill(0, $len, '?') ).')
				 and itemapply<=? and itemdate>? and print_group_apply<=? and print_group_stop>?';
			$stmt = $this->conn->prepare($sql);
			$marker = '';
			$arr = array();
			$stmtParams = array();
			foreach ($itemid as $id=>$val) {
				$marker .= 'i';
				$arr[] = $id;
			}
			array_push($arr, $this->curdate,$this->curdate,$this->curdate,$this->curdate);
			$marker .= 'ssss';
			array_unshift($arr, $marker);
			foreach ($arr as $key => $value) {
				$stmtParams[$key] =& $arr[$key];	// bind_paramへの引数を参照渡しにする
			}
			call_user_func_array(array($stmt, 'bind_param'), $stmtParams);
			$stmt->execute();
			$stmt->store_result();
			$r = MYDB2::fetchAll($stmt);
		} catch (Exception $e) {
			$r = FALSE;
		}
		$stmt->close();
//		$conn->close();
		return $r;
	} 
	
	
	
	/**
	 *	シルクスクリーンのプリント代を返す
	 *		@amount		数量
	 *		@inkcount	インク色数
	 *		@itemid		アイテムIDをキーにした当該アイテムの枚数の配列
	 *		@size		0:通常　1:ジャンボ版　2:スーパージャンボ
	 *		@repeat		同版分類IDをキーにした版代を計上するかどうか判別する値、0:版代を計上　1:版代を引く（リピート）　2:版代を引く（同版）の配列
	 *		@return		{'tot':プリント代合計, 'plates':{同版分類ID:版代}, 'press':インク代計, 'extra':{アイテムID:割増金額}, 'group2':{同版分類ID:[アイテムID]}}
	 */
	public function calcSilkPrintFee($amount, $inkcount, $itemid, $size=0, $repeat=0){
		try{
			if ($inkcount<1 || empty($itemid) || !is_array($itemid)) throw new Exception();
			
			// 割増金額を取得
			$r1 = $this->getExtraCharge($itemid);
			if (empty($r1)) throw new Exception();

			// 割増金額をアイテム毎に算出
			// 同版分類でアイテムIDを集計
			$rs['extra'] = array();
			$extraCharge = 0;
			$vol = 0;
			$len = count($r1);
			for ($i=0; $i<$len; $i++) {
				$amountOfItem = $itemid[ $r1[$i]['item_id'] ];
				$vol += $amountOfItem;
				// 同版分類
				$rs['group2'][ $r1[$i]['item_group2_id'] ][] = $r1[$i]['item_id'];
				// 割増金額
				if (empty($r1[$i]['price'])) continue;
				$rs['extra'][$r1[$i]['item_id']] = $r1[$i]['price'] * $amountOfItem * $inkcount;
				$extraCharge += $rs['extra'][$r1[$i]['item_id']];
			}
			
			if ($amount==0) $amount = $vol;
			
			// プリント代計算の単価を取得
			$plateName = array( 'silk-normal', 'silk-jumbo', 'silk-spjumbo' );
			$mode = $plateName[$size];
			$sql = 'select plate_charge.price as plateCharge, print_cost.price as inkFee from (print_method
				 inner join print_cost on print_method.id=print_cost.print_method_id)
				 left join plate_charge on print_method.id=plate_charge.print_method_id
				 where mode=? and num_over<=? and (num_less>=? or num_less=0) and 
				 print_method_apply<=? and print_method_stop>? and print_cost_apply<=? and print_cost_stop>?
				 and plate_charge_apply<=? and plate_charge_stop>? order by operand_index asc';
//			$conn = parent::db_connect();
			$stmt = $this->conn->prepare($sql);
			$stmt->bind_param("siissssss", $mode, $amount, $amount, $this->curdate, $this->curdate, $this->curdate, $this->curdate, $this->curdate, $this->curdate);
			$stmt->execute();
			$stmt->store_result();
			$r2 = MYDB2::fetchAll($stmt);
//			if (empty($r2)) throw new Exception();

			// インク代
			$tot = 0;
			$tot += $r2[0]['inkFee'] * $amount;	// 1色目
			if ($inkcount>1) {
				$tot += $r2[1]['inkFee'] * $amount * ($inkcount - 1);	// 2色目以降
			}
			$rs['press'] = $tot;
			
			// 版代
			$plates = 0;
			if (is_array($repeat)) {
				// 同版分類IDをキーにした版代の配列
				foreach ($repeat as $group2Id => $isRepeat) {
					$rs['plates'][$group2Id] = $isRepeat==0? $r2[0]['plateCharge'] * $inkcount: 0;
					$plates += $rs['plates'][$group2Id];
				}
			} else if ($repeat!=0) {
				$rs['plates'] = 0;
				$plates = $rs['plates'];
			} else if (count($rs['group2'])>1) {
				foreach ($rs['group2'] as $group2Id => $ary) {
					$rs['plates'][$group2Id] = $r2[0]['plateCharge'] * $inkcount;
					$plates += $rs['plates'][$group2Id];
				}
			} else {
				$rs['plates'] = $r2[0]['plateCharge'] * $inkcount;
				$plates = $rs['plates'];
			}

			// プリント代合計
			$rs['tot'] = $rs['press'] + $plates + $extraCharge;
		} catch(Exception $e) {
			$rs['tot'] = 0;
		}

		$stmt->close();
//		$conn->close();
		return $rs;
	}


	/**
	 *	インクジェットのプリント代を返す
	 *		@option		淡色:0, 濃色:1
	 *		@amount		数量
	 *		@size		プリントサイズ（0:大、1:中、2:小）
	 *		@itemid		アイテムIDをキーにしたカラー名毎の枚数の配列
	 *		@return		{'tot':プリント代合計, 'press':プレス代計, 'extra':{アイテムID:割増金額}}
	 */
	public function calcInkjetFee($option, $amount, $size, $itemid){
		try{
			if (empty($itemid) || !is_array($itemid)) throw new Exception();

			// 淡色のアイテムカラー名を取得
			$paleColor = array();
			if ($result = $this->conn->query('select color_name from itemcolor where inkjet_option=1')) {
				while ($row = $result->fetch_assoc()) {
					$paleColor[$row['color_name']] = true;
				}
				$result->close();
			}
			
			// 枚数を集計
			$numOfOption = array(0,0);		// [淡色の枚数, 濃色の枚数]
			foreach ($itemid as $id=>$ary) {
				$item[$id] = 0;
				if (is_array($ary)) {
					foreach ($ary as $colorName=>$num) {

						// アイテムID毎に枚数を合算
						$item[$id] += $num;

						// 濃淡色で枚数を分ける
						if (isset($paleColor[$colorName])) {
							$numOfOption[0] += $num;
						} else {
							$numOfOption[1] += $num;
						}
					}
				} else {
					$num = intval($ary);
					$item[$id] += $num;
					$numOfOption[$option] += $num;
				}
			}
			
			// 割増金額を取得
			$r1 = $this->getExtraCharge($item);
			if (empty($r1)) throw new Exception();

			// 割増金額をアイテム毎に算出
			$rs['extra'] = array();
			$extraCharge = 0;
			$vol = 0;
			$len = count($r1);
			for ($i=0; $i<$len; $i++) {
				$amountOfItem = $item[ $r1[$i]['item_id'] ];
				$vol += $amountOfItem;
				if (empty($r1[$i]['price'])) continue;
				$rs['extra'][$r1[$i]['item_id']] = $r1[$i]['price'] * $amountOfItem;
				$extraCharge += $rs['extra'][$r1[$i]['item_id']];
			}
			
			if ($amount==0) $amount = $vol;

			// プリント代計算の単価を取得
			$rs['press'] = 0;
			$plateName = array( 'inkjet-pale', 'inkjet-deep' );
			$sql = 'select print_cost.price as fee from print_method
				 inner join print_cost on print_method.id=print_cost.print_method_id
				 where mode=? and operand_index=? and num_over<=? and (num_less>=? or num_less=0) and 
				 print_method_apply<=? and print_method_stop>? and print_cost_apply<=? and print_cost_stop>?';
			$stmt = $this->conn->prepare($sql);
			
			for ($i=0; $i<2; $i++) {
				if (empty($numOfOption[$i])) continue;
				$stmt->bind_param("siiissss", $plateName[$i], $size, $amount, $numOfOption[$i], $this->curdate, $this->curdate, $this->curdate, $this->curdate);
				$stmt->execute();
				$stmt->store_result();
				$r2 = MYDB2::fetchAll($stmt);
				
				// プリント代
				$rs['press'] += $r2[0]['fee'] * $numOfOption[$i];
			}

			// プリント代合計
			$rs['tot'] = $rs['press'] + $extraCharge;
		} catch(Exception $e) {
			$rs['tot'] = 0;
		}

		$stmt->close();
		return $rs;
	}


	/**
	 *		カッティングのプリント代を返す
	 *		@amount		数量
	 *		@size		プリントサイズ（0:大、1:中、2:小）
	 *		@itemid		アイテムIDをキーにした当該アイテムの枚数の配列
	 *		@return		{'tot':プリント代合計, 'press':プレス代計, 'extra':{アイテムID:割増金額}}
	 */
	public function calcCuttingFee($amount, $size, $itemid){
		try{
			if (empty($itemid) || !is_array($itemid)) throw new Exception();

			// 割増金額を取得
			$r1 = $this->getExtraCharge($itemid);
			if (empty($r1)) throw new Exception();

			// 割増金額をアイテム毎に算出
			$rs['extra'] = array();
			$extraCharge = 0;
			$vol = 0;
			$len = count($r1);
			for ($i=0; $i<$len; $i++) {
				$amountOfItem = $itemid[ $r1[$i]['item_id'] ];
				$vol += $amountOfItem;
				if (empty($r1[$i]['price'])) continue;
				$rs['extra'][$r1[$i]['item_id']] = $r1[$i]['price'] * $amountOfItem;
				$extraCharge += $rs['extra'][$r1[$i]['item_id']];
			}
			
			if ($amount==0) $amount = $vol;

			// プリント代計算の単価を取得
			$mode = 'cutting';
			$sql = 'select print_cost.price as fee from print_method
				 inner join print_cost on print_method.id=print_cost.print_method_id
				 where mode=? and operand_index=? and num_over<=? and (num_less>=? or num_less=0) and 
				 print_method_apply<=? and print_method_stop>? and print_cost_apply<=? and print_cost_stop>?';
//			$conn = parent::db_connect();
			$stmt = $this->conn->prepare($sql);
			$stmt->bind_param("siiissss", $mode, $size, $amount, $amount, $this->curdate, $this->curdate, $this->curdate, $this->curdate);
			$stmt->execute();
			$stmt->store_result();
			$r2 = MYDB2::fetchAll($stmt);
//			if (empty($r2)) throw new Exception();

			// プリント代
			$rs['press'] = $r2[0]['fee'] * $amount;

			// プリント代合計
			$rs['tot'] = $rs['press'] + $extraCharge;
		} catch(Exception $e) {
			$rs['tot'] = 0;
		}

		$stmt->close();
//		$conn->close();
		return $rs;
	}


	/**
	 *		デジタル転写のプリント代を返す
	 *		@amount		数量
	 *		@size		プリントサイズ（0:大、1:中、2:小）
	 *		@itemid		アイテムIDをキーにした当該アイテムの枚数の配列
	 *		@repeat		0:版代を計上　1:版代を引く（リピート） 2:版代を引く（枚数レンジは違うが同版とみなす）
	 *		@return		{'tot':プリント代合計, 'press':プレス代計, 'plates':版代, 'extra':{アイテムID:割増金額}}
	 */
	public function calcDigitFee($amount, $size, $itemid, $repeat=0){
		try{
			if (empty($itemid) || !is_array($itemid)) throw new Exception();

			// 割増金額を取得
			$r1 = $this->getExtraCharge($itemid);
			if (empty($r1)) throw new Exception();

			// 割増金額をアイテム毎に算出
			$rs['extra'] = array();
			$extraCharge = 0;
			$vol = 0;
			$len = count($r1);
			for ($i=0; $i<$len; $i++) {
				$amountOfItem = $itemid[ $r1[$i]['item_id'] ];
				$vol += $amountOfItem;
				if (empty($r1[$i]['price'])) continue;
				$rs['extra'][$r1[$i]['item_id']] = $r1[$i]['price'] * $amountOfItem;
				$extraCharge += $rs['extra'][$r1[$i]['item_id']];
			}
			
			if ($amount==0) $amount = $vol;

			// プリント代計算の単価を取得
			$mode = 'trans';
			$sql = 'select plate_charge.price as plateCharge, print_cost.price as fee from (print_method
				 inner join print_cost on print_method.id=print_cost.print_method_id)
				 left join plate_charge on print_method.id=plate_charge.print_method_id and operand_index=plate_index
				 where mode=? and operand_index=? and num_over<=? and (num_less>=? or num_less=0) and 
				 print_method_apply<=? and print_method_stop>? and print_cost_apply<=? and print_cost_stop>?
				 and plate_charge_apply<=? and plate_charge_stop>?';
//			$conn = parent::db_connect();
			$stmt = $this->conn->prepare($sql);
			$stmt->bind_param("siiissssss", $mode, $size, $amount, $amount, $this->curdate, $this->curdate, $this->curdate, $this->curdate, $this->curdate, $this->curdate);
			$stmt->execute();
			$stmt->store_result();
			$r2 = MYDB2::fetchAll($stmt);
//			if (empty($r2)) throw new Exception();

			// プリント代
			$rs['press'] = $r2[0]['fee'] * $amount;

			// 版代
			$rs['plates'] = $repeat==0? $r2[0]['plateCharge'] : 0;

			// プリント代合計
			$rs['tot'] = $rs['press'] + $rs['plates'] + $extraCharge;
		} catch(Exception $e) {
			$rs['tot'] = 0;
		}

		$stmt->close();
//		$conn->close();
		return $rs;
	}


	/**
	 *		刺繍のプリント代を返す
	 *		@option		0:オリジナル, 1:ネーム
	 *		@amount		数量
	 *		@size		プリントサイズ（0:大、1:中、2:小、3:極小）
	 *		@itemid		アイテムIDをキーにした当該アイテムの枚数の配列
	 *		@repeat		0:版代を計上　1:版代を引く（リピート） 2:版代を引く（枚数レンジは違うが同版とみなす）
	 *		@return		{'tot':プリント代合計, 'press':プレス代計, 'plates':版代, 'extra':{アイテムID:割増金額}}
	 */
	public function calcEmbroideryFee($option, $amount, $size, $itemid, $repeat=0){
		try{
			if (empty($itemid) || !is_array($itemid)) throw new Exception();

			// 割増金額を取得
			$r1 = $this->getExtraCharge($itemid);
			if (empty($r1)) throw new Exception();

			// 割増金額をアイテム毎に算出
			$rs['extra'] = array();
			$extraCharge = 0;
			$vol = 0;
			$len = count($r1);
			for ($i=0; $i<$len; $i++) {
				$amountOfItem = $itemid[ $r1[$i]['item_id'] ];
				$vol += $amountOfItem;
				if (empty($r1[$i]['price'])) continue;
				$rs['extra'][$r1[$i]['item_id']] = $r1[$i]['price'] * $amountOfItem;
				$extraCharge += $rs['extra'][$r1[$i]['item_id']];
			}
			
			if ($amount==0) $amount = $vol;

			// プリント代の単価を取得
			$plateName = array( 'embroidery-org', 'embroidery-name' );
			$mode = $plateName[$option];
			$sql = 'select print_method_id, print_cost.price as fee from print_method
				 inner join print_cost on print_method.id=print_cost.print_method_id
				 where mode=? and operand_index=? and num_over<=? and (num_less>=? or num_less=0) and 
				 print_method_apply<=? and print_method_stop>? and print_cost_apply<=? and print_cost_stop>?';
//			$conn = parent::db_connect();
			$stmt = $this->conn->prepare($sql);
			$stmt->bind_param("siiissss", $mode, $size, $amount, $amount, $this->curdate, $this->curdate, $this->curdate, $this->curdate);
			$stmt->execute();
			$stmt->store_result();
			$r2 = MYDB2::fetchAll($stmt);
//			if (empty($r2)) throw new Exception();

			// 型代を取得
			$sql = 'select coalesce(plate_charge.price, 0) as plateCharge from plate_charge
				 where print_method_id=? and plate_index=? and plate_charge_apply<=? and plate_charge_stop>?';
			$stmt = $this->conn->prepare($sql);
			$stmt->bind_param("iiss", $r2[0]['print_method_id'], $size, $this->curdate, $this->curdate);
			$stmt->execute();
			$stmt->store_result();
			$r3 = MYDB2::fetchAll($stmt);
			if (empty($r3)) {
				$rs['plates'] = 0;
			} else {
				$rs['plates'] = $repeat==0? $r3[0]['plateCharge'] : 0;
			}

			// プリント代
			$rs['press'] = $r2[0]['fee'] * $amount;

			// プリント代合計
			$rs['tot'] = $rs['press'] + $rs['plates'] + $extraCharge;
		} catch(Exception $e) {
			$rs['tot'] = 0;
		}

		$stmt->close();
//		$conn->close();
		return $rs;
	}
	
	
	
	
	
	
	
	

	
/*============ 旧バージョン =================================================================================*/

	
	/**
	*	アイテム情報を返す（未使用）
	*	@itemid			アイテムのID
	*
	*	@return			catalog データの配列
	*
	private function getIteminfo($itemid){
		try{
			$curdate = date('Y-m-d');
			$sql = "select * from catalog where catalogapply<=? and catalogdate>? and item_id=? group by item_id";
			$conn = MYDB2::getConnection();
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("ssi", $curdate, $curdate, $itemid);
			$stmt->execute();
			$stmt->store_result();
			$rec = MYDB2::fetchAll($stmt);
		}catch(Exception $e){
			$rec = '';
		}

		$stmt->close();
		$conn->close();
		return $rec;
	}
	*/
	
	
	
	/**
	*	シルクとデジタル転写とインクジェットで最安のプリント代合計を返す
	*	@data		[アイテムID、合計枚数、インク色数、プリント位置、{インクジェットオプション:対象枚数}]　[itemid, amount, ink, pos, option][...]
	*
	*	@return		['printfee':プリント代合計金額, 'volume':合計枚数, 'tax':消費税率]　引数に配列以外を設定した時はNULL
	*/
	public function printfee($data){
		if(!is_array($data) || empty($data)) return null;
		try{
			$area = count($data);
			$total_amount = 0;
			$item = array();
			
			// 同じアイテムで複数のプリント箇所がある場合の合計枚数を算出
			// プリント割増率とスウェット再割増を設定
			for($i=0; $i<$area; $i++){
				$id = $data[$i]['itemid'];
				//$amount = $data[$i]['amount'];
				//$pos = $data[$i]['pos'];
				//$ink = $data[$i]['ink'];
				//$ratio = $this->getPrintRatio($id);
				$extra = empty($data[$i]['extra'])? 1: $data[$i]['extra'];	// sweat extra ratio
				
				/*
				if(!isset($item[$id])){
					$total_amount += $amount;
				}
				 */
				//$data[$i]['extra'] = $extra;
				//$data[$i]['ratio'] = $ratio;
				
				$isInkjet = $data[$i]['inkjet']? 1: NULL;	// // inkjet可能かどうかのフラグ
				
				if(isset($item[$id])){
					$item[$id]['ink'][$data[$i]['pos']] = $data[$i]['ink'];
					$item[$id]['extra'][$data[$i]['pos']] = $extra;
				}else{
					//$info = $this->getIteminfo($id);
					$ratio = $this->getPrintRatio($id);
					//$part = $info['category_id'].'_'.($ratio*10);
					$item[$id] = array(
						'amount'=>$data[$i]['amount'], 
						'ink'=>array($data[$i]['pos']=>$data[$i]['ink']), 
						'ratio'=>$ratio, 
						'extra'=>array($data[$i]['pos']=>$extra),
						'inkjet'=>$isInkjet,
						'option'=>$data[$i]['option'],
						);
					$total_amount += $data[$i]['amount'];
				}
			}
			
			// プリント割増率、プリント位置ごとの色数が同じ商品を同じデザインとして枚数を合計
			$ary = array();
			foreach($item as $itemid=>$dat){
				foreach($dat['ink'] as $pos=>$ink){
					$key = mb_convert_encoding($pos, 'utf-8', 'euc-jp').'_'.$ink.'_'.($dat['ratio']*10).'_'.($dat['extra'][$pos]*10);
					if (array_key_exists($key, $ary)) {
						$ary[$key]['amount'] += intval($dat['amount'], 10);
						foreach ($dat['option'] as $optionValue => $volume) {
							$ary[$key]['option'][$optionValue] += $volume;
						}
						if(!$dat['inkjet']){
							$ary[$key]['inkjet'] = $dat['inkjet'];	// inkjet不可のアイテムがある場合は全て不可にする
						}
					} else {
						$ary[$key] = array( 'itemid'=>$itemid, 
											'amount'=>$dat['amount'], 
											'ink'=>$ink, 
											'ratio'=>$dat['ratio'], 
											'extra'=>$dat['extra'][$pos],
											'inkjet'=>$dat['inkjet'],
											'option'=>$dat['option'],
											);
					}
				}
			}
					
			/* プリント位置ごとの配列に変換
			$area = 0;
			foreach($target as $key=>$dat){
				// 色数とスウェット割増率は箇所ごと
				foreach($dat['ink'] as $pos=>$ink){
					$ary[] = array('amount'=>$dat['amount'], 'ink'=>$dat['ink'][$pos], 'ratio'=>$dat['ratio'], 'extra'=>$dat['extra'][$pos], 'group'=>$key);
				}
			}
			 */
			
			
			
			$area = count($ary);
			$print_pattern = array('silk','inkjet','digit');
			$pattern = $this->getPermutation($print_pattern, $area);
			$min_tot = 0;
			$jumbo = 0;		// 0 is not jumbo.
			//$option = 0; 	// インクジェット　0:淡色、1:濃色
			$plates = 'a';	// 販の種類（固定）
			for($i=0; $i<count($pattern); $i++){
				$tot = 0;
				$c = 0;
				$commonData = array();		// 転写の版代とシート代用
				$transData = array();		// 転写のプレス代用
				foreach($ary as $v){
					if($pattern[$i][$c]=="inkjet" && $v['inkjet']){
						foreach($v['option'] as $optionValue=>$volume){
							$fee = $this->t_calcInkjetFee($volume, 1, $this->design_size, NULL, $v['ratio'], $optionValue, $v['extra']);
							$tot += $fee['tot'];
						}
					}else if($pattern[$i][$c]=="digit"){
						// 版代とシート代用
						$commonData['sheet'][$plates][] = $this->sheet_size;
						$commonData['shot'][$plates][] = $v['amount'];
						
						// プレス代用
						$transData['amount'][] = $v['amount'];
						$transData['ratio'][] = $v['ratio'];
						$transData['extra'][] = $v['extra'];
						$transData['press'][] = 0; // 固定
						
						$c++;
						
						// $tmp[] = array('amount'=>$v['amount'], 'sheet'=>array($this->sheet_size), 'extra'=>array($v['extra']), 'ratio'=>$v['ratio']);
						
						/*
						$group = $ary[$c]['group'];
						if(isset($tmp[$group])){
							$tmp[$group]['sheet'][] = $this->sheet_size;
							$tmp[$group]['extra'][] = $ary[$c]['extra'];
						}else{
							$tmp[$group] = array('amount'=>$ary[$c]['amount'], 'sheet'=>array($this->sheet_size), 'extra'=>array($ary[$c]['extra']), 'ratio'=>$ary[$c]['ratio']);
						}
						*/
						/* デザインのサイズ指定がある場合
						switch($_POST['size'][$r]){
							case '0': $sheet[] = '1'; break;
							case '1': $sheet[] = '0.5'; break;
							case '2': $sheet[] = '0.25'; break;
						}
						*/
					}else{
						// silk
						$fee = $this->t_calcSilkPrintFee($v['amount'], 1, $v['ink'], NULL, $v['ratio'], $jumbo, $v['extra']);
						$tot += $fee['tot'];
					}
				}
				
				// デジタル転写
				if(!empty($commonData)){
					// 転写の版代とシート代を返す
					$common_cost = $this->calcTransCommonFee('digit', $commonData['sheet'], $commonData['shot']);
					$cost = $common_cost[0]+$common_cost[1];
					$tot += $cost;
					
					// 転写のプレス代を返す
					$tot += $this->calcTransFee('digit', $transData['amount'], $transData['extra'], NULL, $transData['ratio'], $transData['press']);
				}
				
				// 最安値を更新
				if( ($min_tot==0 || $min_tot>$tot) && $tot>0 ){
					$min_tot = $tot;
				}
			}
		}catch(Exception $e){
			$min_tot = '0';
		}
		
		// 計算日時点の消費税率
		$curdate = date('Y-m-d');
		$tax = parent::getSalesTax($curdate);
		
		return array('printfee'=>$min_tot, 'volume'=>$total_amount, 'tax'=>$tax);
	}
	
	
	/*
	*	プリント方法の組合せパターンを配列に格納
	*	@pattern		並べる要素の配列　[a,b, ...]
	*	@count			並べる桁数
	*
	*	@return			patternの組合せを配列で返す （patternが[a,b]、countが2の時、[a,a][a,b][b,a][b,b]）　
	*/
	private function getPermutation($pattern, $count){
		$digit = pow(count($pattern),$count);
		$res = $this->permute($pattern, $digit, $digit, $ary);
		return $res;
	}
	
	/*
	*	重複順列のパターンを取得する再帰モジュール
	*	@pattern		並べる要素の配列
	*	@digit			パターンの総数（再起呼出時に並べる数（桁数）の算出に使用される）
	*	@index			パターンの総数
	*	@res			結果を代入する配列
	*
	*	@return			順列の配列
	*/
	private function permute($pattern, $digit, $index, $res){
		$patterns = count($pattern);
		$digit /= $patterns;
		if($digit!=1) $res = $this->permute($pattern, $digit, $index, $res);
		$d=0;
		$a=1;
		for($i=0; $i<$index; $i++){
			$res[$i][] = $pattern[$d];
			if($a==$digit){
				$a=1;
				$d=$d==$patterns-1? 0: ++$d;
			}else{
				$a++;
			}
		}
		return $res;
	}
	
	
	
	/**
	 *		プリント割増率を返す
	 *		@itemid			アイテムのID
	 *		@ratioid		割増率ID（default is NULL）
	 *
	 *		return			割増率
	 */
	private function getPrintRatio($itemid, $ratioid=null){
		try{
			if(is_null($ratioid)){
				$param = $itemid;
				$sql= "SELECT * FROM item inner join printratio on item.printratio_id=printratio.ratioid WHERE item.id=? and printratioapply<=? order by printratioapply desc limit 1";
			}else{
				$param = $ratioid;
				$sql= "SELECT * FROM printratio WHERE ratioid=? and printratioapply<=? order by printratioapply desc limit 1";
			}
			//$conn = MYDB2::getConnection();
			$stmt = $this->conn->prepare($sql);
			$stmt->bind_param("is", $param, $this->curdate);
			$stmt->execute();
			$stmt->store_result();
			$rec = MYDB2::fetchAll($stmt);
			$rs = $rec[0]['ratio'];
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		//$conn->close();
		return $rs;
	}
	
	
	/**
	*	アイテムごとに、シルクとデジタル転写とインクジェットで最安の見積りを計算（プリント位置は1ヵ所）して最安の商品単価から見積り
	*	@data		アイテムコード、枚数、インク色数、プリント位置の配列　[itemcode, amount, ink, pos][...]
	*
	*	@return		[item_code:['price':見積額, 'perone':1枚あたり]][...]　引数に配列以外を設定した時はNULL
	*/
	public function estimateEach($data){
		if(!is_array($data) || empty($data)) return null;
		try{
			// 計算日時点の消費税率
			$curdate = date('Y-m-d');
			$tax = parent::getSalesTax($curdate);
			$tax /= 100;

			// インクジェット可能なアイテムIDを取得
			$ids = parent::itemIdOf(85, NULL, "tag");

			$area = 1;
			//$sheet = array($this->sheet_size);
			//$extra = array(1);
			$plates = 0;
			for($i=0; $i<count($data); $i++){
				$idx = 0;
				$ary = array();
				$id = parent::getItemID($data[$i]['itemcode']);
				$cost = parent::getItemprice($id);
				$ratio = $this->getPrintRatio($id);	
				$silk = $this->t_calcSilkPrintFee($data[$i]['amount'], $area, $data[$i]['ink'], NULL, $ratio);
				$ary[$idx++] = $silk['tot'];
				if(in_array($id, $ids)){
					$inkjet = $this->t_calcInkjetFee($data[$i]['amount'], $area, $this->design_size, NULL, $ratio);
					$ary[$idx++] = $inkjet['tot'];
				}
				// 版代とシート代用
				$commonData['sheet'][$plates][] = $this->sheet_size;
				$commonData['shot'][$plates][] = $data[$i]['amount'];
				// プレス代用
				$transData['amount'][] = $data[$i]['amount'];
				$transData['ratio'][] = $ratio;
				$transData['extra'][] = 1;
				$transData['press'][] = 0; // 固定
				// 転写の版代とシート代を返す
				$common_cost = $this->calcTransCommonFee('digit', $commonData['sheet'], $commonData['shot']);
				$trans = $common_cost[0]+$common_cost[1];
				// 転写のプレス代を返す
				$trans += $this->calcTransFee('digit', $transData['amount'], $transData['extra'], NULL, $transData['ratio'], $transData['press']);
				$ary[$idx++] = $trans;

				$fee = min($ary) + ($cost[0]['price_white']*$data[$i]['amount']);
				$price = floor($fee*(1+$tax));
				$res[$data[$i]['itemcode']]['price'] = $price;
				$res[$data[$i]['itemcode']]['perone'] = ceil($price/$data[$i]['amount']);
			}
		}catch(Exception $e){
			$res = '0';
		}

		return $res;
	}
	
	
	/**
	 *		シルクスクリーンのプリント代を返す
	 *		@amount		数量
	 *		@area		プリント箇所数
	 *		@inkcount	インク色数
	 *		@itemid		アイテムID
	 *		@ratio		割増率ID（@itemidがNULLの場合は割増率）
	 *		@size		0:通常　1:ジャンボ版　2:スーパージャンボ
	 *		@extra		スウェットの割増適用箇所の場合　default 1　（通常のカテゴリごとの割増率ratioを再割増）
	 *		@repeat		0：新版
	 *					1：リピート版		版代とデザイン代を引く
	 *					99：				版代とデザイン代と組付け代を引く
	 *
	 *		return		{'tot':プリント代, 'plates':版代＋デザイン代, 'setting':組付け代, 'press':インク代}
	 */
	public function t_calcSilkPrintFee($amount, $area, $inkcount, $itemid=0, $ratio=1, $size=0, $extra=1, $repeat=0){
		try{
			if($area<1 || $inkcount<1 || $amount<1) return 0;
			
			if(!empty($itemid)){
				$ratio = $this->getPrintRatio($itemid);
			}else if(!is_null($itemid)){
				$ratio = $this->getPrintRatio(0, $ratio);
			}
			$ratio *= $extra;
			$superjumbo = $size==2? 2: 1;	// スーパージャンボは版代とプリント代とインク代を2倍
			
			$sql = "SELECT * FROM silkprice where silkapply<=? order by silkapply desc limit 1";
			//$conn = MYDB2::getConnection();
			$stmt = $this->conn->prepare($sql);
			$stmt->bind_param("s", $this->curdate);
			$stmt->execute();
			$stmt->store_result();
			$r = MYDB2::fetchAll($stmt);
			if(empty($r)) return 0;
			$rec = $r[0];
			
			if($repeat==0){
				$plates = $rec['plate']*$superjumbo + $rec['design'];
				$design = $rec['design'];
			}else{
				$plates = 0;
				$design = 0; 
			}
			
			$setting = $rec['operationcost'];
			if($repeat!=99){
				$setting += $rec['setting'];
			}
			
			$ink = ($rec['print']+$rec['ink'])*$superjumbo;
			if($size==1){
				$ink *= 1.5;
				$plates *= 1.5;
				$design *= 1.5;
			}
			$inkfee = ceil( (($ink*$amount) * $ratio) / 10 ) * 10;
			if(strtotime($this->calcType['setting'])<=strtotime($this->curdate)){	// 組付け代に割増率を適用しない
				$printfee = $setting + $inkfee;
			}else{
				$setting = ceil( ($setting * $ratio) / 10 ) * 10;
				$printfee = $setting + $inkfee;
			}
			$tot = ($plates + $printfee) * $area;	// 1色目
			
			// 2色以上ある場合
			$inkfee2 = 0;
			if($area<$inkcount){
				$rest = $inkcount-$area;
				$ink = ($rec['print']/2+$rec['ink'])*$superjumbo;
				if($size==1) $ink *= 1.5;
				$inkfee2 = ceil( (($ink*$amount) * $ratio) / 10 ) * 10 * $rest;
				$tot += ($plates + $setting)*$rest + $inkfee2;
			}
			// プリント代合計
			$rs['tot'] = $tot;
			// デザイン代
			$rs['design'] = $design*$inkcount;
			// 版代とデザイン代
			$rs['plates'] = $plates*$inkcount;
			// 組付け代
			$rs['setting'] = $setting*$inkcount;
			// インク代
			$rs['press'] = $inkfee+$inkfee2;
		}catch(Exception $e){
			$rs = 0;
		}
		
		$stmt->close();
		//$conn->close();
		return $rs;
	}
	
	
	/**
	 *		インクジェット（白Ｔ:inkjetと黒Ｔ:darkinkjet）のプリント代を返す
	 *		@amount		数量
	 *		@area		プリント箇所数
	 *		@size		プリントサイズ（0:大、1:中、2:小）
	 *		@itemid		アイテムID
	 *		@ratio		割増率ID（@itemidがNULLの場合は割増率）
	 * 		@option		白Ｔ:0(default), 黒Ｔ:1
	 *		@extra		スウェットの割増適用箇所の場合　default 1　（通常のカテゴリごとの割増率ratioを再割増）
	 *		@repeat		0：新版
	 *					1：リピート版		デザイン代を引く
	 *					99：				デザイン代と組付け代を引く
	 *
	 *		return		{'tot':プリント代, 'plates':版代＋デザイン代, 'setting':組付け代, 'press':プレス代}
	 */
	public function t_calcInkjetFee($amount, $area, $size, $itemid=0, $ratio=1, $option=0, $extra=1, $repeat=0){
		try{
			if($amount<1) return 0;
			if(!empty($itemid)){
				$ratio = $this->getPrintRatio($itemid);
			}else if(!is_null($itemid)){
				$ratio = $this->getPrintRatio(0, $ratio);
			}
			$ratio *= $extra;
			
			$sql = "SELECT * FROM inkjetprice where inkjetapply<=? order by inkjetapply desc limit 1";
			//$conn = MYDB2::getConnection();
			$stmt = $this->conn->prepare($sql);
			$stmt->bind_param("s", $this->curdate);
			$stmt->execute();
			$stmt->store_result();
			$r = MYDB2::fetchAll($stmt);
			if(empty($r)) return 0;
			$rec = $r[0];
			
			if($repeat==0){
				if($option==1){	// 黒T
					$design = $rec['design_1'];
				}else{
					$design = $rec['design'];
				}
			}else{
				$design = 0;
			}
			
			$setting = 0;
			if($repeat!=99){
				if($option==1){	// 黒T
					$setting += $rec['setting_1'];
				}else{
					$setting += $rec['setting'];
				}
			}
			
			$ink = "ink_".$size;
			$pressfee = $rec['press_0']*$amount;
			$printfee = $rec['print_0']+$rec[$ink];
			if($option==1){	// 黒T
				$printfee += $rec['paste']+$rec['press_1']+$rec['print_1']+$rec[$ink];
			}
			$printfee *= $amount;
			$press = ceil( (($pressfee+$printfee)*$ratio)/10 )*10 * $area;
			if(strtotime($this->calcType['setting'])<=strtotime($this->curdate)){	// 組付け代に割増率を適用しない
				$tot = ($design + $setting)*$area + $press;
			}else{
				$setting = ceil( (($setting)*$ratio)/10 )*10;
				$tot = ($design + $setting)*$area + $press;
			}
			// プリント代合計
			$rs['tot'] = $tot;
			// デザイン代
			$rs['plates'] = $design;
			$rs['design'] = $design;
			// 組付け代
			$rs['setting'] = $setting;
			// プレス代
			$rs['press'] = $press;
		}catch(Exception $e){
			$rs = 0;
		}
		
		$stmt->close();
		//$conn->close();
		return $rs;
	}
	
	
	/**
	 *		カッティングのプリント代を返す
	 *		@amount		数量
	 *		@area		プリント箇所数
	 *		@size		プリントサイズ（0:大、1:中、2:小）
	 *		@itemid		アイテムID
	 *		@ratio		割増率（@itemidがNULLの場合は割増率）
	 *		@extra		スウェットの割増適用箇所の場合　default 1　（通常のカテゴリごとの割増率ratioを再割増）
	 *		@repeat		0：新版
	 *					1：リピート版		デザイン代を引く
	 *					99：				デザイン代と組付け代とプレス準備代を引く
	 *
	 *		return		{'tot':プリント代, 'plates':版代＋デザイン代, 'setting':組付け代, 'press':プレス代}
	 */
	public function t_calcCuttingFee($amount, $area, $size, $itemid=0, $ratio=1, $extra=1, $repeat=0){
		try{
			if($amount<1) return 0;
			if(!empty($itemid)){
				$ratio = $this->getPrintRatio($itemid);
			}else if(!is_null($itemid)){
				$ratio = $this->getPrintRatio(0, $ratio);
			}
			$ratio *= $extra;
			
			$sql = "SELECT * FROM cuttingprice where cuttingapply<=? order by cuttingapply desc limit 1";
			//$conn = MYDB2::getConnection();
			$stmt = $this->conn->prepare($sql);
			$stmt->bind_param("s", $this->curdate);
			$stmt->execute();
			$stmt->store_result();
			$r = MYDB2::fetchAll($stmt);
			if(empty($r)) return 0;
			$rec = $r[0];
			
			if($repeat==0){
				$design = $rec['design'];
			}else{
				$design = 0;
			}
			
			$setting = 0;
			$press = 0;
			if($repeat!=99){
				$setting += $rec['setting'];
				$press += $rec['prepress'];	//	2014-08-29 Tシャツと一部絵型でプレス準備を共有
			}
			/*	2014-08-29 プレス準備代への割増率の計上を廃止
			*	$press = ceil( (($rec['prepress']+$rec['press']*$amount)*$ratio)/10 ) * 10;
			*/
			$press += ceil( (($rec['press']*$amount)*$ratio)/10 ) * 10;
			if(strtotime($this->calcType['setting'])<=strtotime($this->curdate)){	// 組付け代に割増率を適用しない
				$pressfee = $setting + $press;
			}else{
				$setting = ceil( (($setting)*$ratio)/10 ) * 10;
				$pressfee = $setting + $press;
			}
			$sheetfee = ($rec['sheet_'.$size]+$rec['detach']+$rec['inpfee']+$rec['cutting']) * $amount;
			$tot = ($design+$pressfee+$sheetfee) * $area;
			// プリント代合計
			$rs['tot'] = $tot;
			// デザイン代
			$rs['plates'] = $design;
			$rs['design'] = $design;
			// 組付け代
			$rs['setting'] = $setting;
			// プレス代
			$rs['press'] = $press+$sheetfee;
		}catch(Exception $e){
			$rs = 0;
		}
		
		$stmt->close();
		//$conn->close();
		return $rs;
	}
	
	
	/**
	 *		転写のプレス代を返す（デジタル:digit、カラー（白Ｔ:transと黒Ｔ:darktrans））
	 *		@tablename	プリント方法
	 *		@amount[]	プリント箇所ごとの枚数
	 *		@extra[]	スウェットの割増の配列、　default 1　（通常のカテゴリごとの割増率ratioを再割増）
	 *		@itemid		アイテムID
	 *		@ratio[]	割増率ID（@itemidがNULLの場合は割増率）の配列
	 *		@press[]	プリント箇所ごとのプレス準備代の有無（990,991: プレス準備代なし）
	 *
	 * 		return		プレス代
	 */
	public function calcTransFee($tablename, $amount, $extra, $itemid=0, $ratio=1, $press=array()){
		try{
			if(max($amount)<1) return;
			if(!empty($itemid)){
				$tmp = $this->getPrintRatio($itemid);
			}else if(!is_null($itemid)){
				$tmp = $this->getPrintRatio(0, $ratio);
			}
			// プリント箇所ごとの割増率の指定がない場合
			if(!empty($tmp)){
				for ($i=0; $i < count($amount); $i++) { 
					$ratio[] = $tmp;
				}
			}
			
			if($tablename=='digit'){
				$sql = "SELECT * FROM digitprice where digitapply<=? order by digitapply desc limit 1";
				$paper = 'paper';
			}else{
				$sql = "SELECT * FROM colorprice where colorapply<=? order by colorapply desc limit 1";
				if(preg_match('/^dark/', $tablename)){
					$paper = 'paper_1';
				}else{
					$paper = 'paper_0';
				}
			}
			//$conn = MYDB2::getConnection();
			$stmt = $this->conn->prepare($sql);
			$stmt->bind_param("s", $this->curdate);
			$stmt->execute();
			$stmt->store_result();
			$r = MYDB2::fetchAll($stmt);
			if(empty($r)) return 0;
			$rec = $r[0];
			
			/*
			if($repeat==0){
				$plate = $rec['plate'];
				$design = $rec['design'];
			}else{
				$plate = 0;
				$design = 0;
			}
			*/
			
			/* 版代＋デザイン代＋組付け代
			if(strtotime($this->calcType['setting'])<=strtotime($this->curdate)){	// 組付け代に割増率を適用しない
				//$platefee = $plate+$design + $rec['setting'];
				$setting = $rec['setting'];
			}else{
				//$platefee = $plate+$design + ceil( ($rec['setting']*$ratio)/10 ) * 10;
				$setting = ceil( ($rec['setting']*$ratio)/10 ) * 10;
			}
			*/
			
			// シート代
			//$sheetfee = $rec['ink']+$rec[$paper]+$rec['printer']+$rec['print'];
			
			// プレス代（箇所ごと）
			for($i=0; $i<count($amount); $i++){
				if(empty($amount[$i])) continue;
				/* 2014-07-26 仕様変更、プレス準備代に割増率をかけない
				*	$pressfee += ($rec['prepress']+$rec['press']*$amount[$i])*($ratio * $extra[$i]);
				*/
				
				// Tシャツと一部絵型でプレス準備を共有（990,991）
				if($press[$i]<990){
					$pressfee += $rec['prepress'];
				}
				$pressfee += ($rec['press']*$amount[$i])*($ratio[$i] * $extra[$i]);
			}
			$pressfee = ceil($pressfee/10)*10;
			
			/* [版数,シート数]
			if(empty($hash)){
				$hash = $this->getSheetCount($sheet, $shot);
			}
			*/
			/*
			$charge = $setting;
			$charge += $pressfee;
			$charge += $rec['presheet'];
			
			$sheetfee *= $hash[1];
			$rs = $charge;
			*/
			
			$rs = $pressfee;
		}catch(Exception $e){
			$rs = 0;
		}
		
		$stmt->close();
		//$conn->close();
		return $rs;
	}
	
	
	/**
	 *		転写の版代とシート代を返す（デジタル:digit、カラー（白Ｔ:transと黒Ｔ:darktrans））
	 *		@tablename	プリント方法
	 *		@sheet[]	版ごとのプリント位置をキーにしたデザインの大きさのシートに対する割合（1, 0.5, 0.25）絵型に関係なく同じプリント位置を同デザインとみなす
	 *		@shot[]		版ごとのプリント箇所ごとの枚数
	 *		@repeat		0：新版
	 *					1：リピート版		版代（デジタル）とデザイン代を引く
	 *
	 * 		return		[版代, シート代, デザイン代, プリント作業売上]
	 */
	public function calcTransCommonFee($tablename, $sheet, $shot, $repeat=0){
		try{
			if($tablename=='digit'){
				$sql = "SELECT * FROM digitprice where digitapply<=? order by digitapply desc limit 1";
				$paper = 'paper';
			}else{
				$sql = "SELECT * FROM colorprice where colorapply<=? order by colorapply desc limit 1";
				if(preg_match('/^dark/', $tablename)){
					$paper = 'paper_1';
				}else{
					$paper = 'paper_0';
				}
			}
			//$conn = MYDB2::getConnection();
			$stmt = $this->conn->prepare($sql);
			$stmt->bind_param("s", $this->curdate);
			$stmt->execute();
			$stmt->store_result();
			$r = MYDB2::fetchAll($stmt);
			if(empty($r)) return 0;
			$rec = $r[0];
			
			// 版数とシート数を取得
			$hash = self::getSheetCount($sheet, $shot);
			
			// 版代とデザイン代
			if($repeat==0){
				$platefee = $rec['plate']+$rec['design'];
				$design = $rec['design'];
			}else{
				$platefee = 0;
				$design = 0;
			}
			
			// 組付け代
			if(strtotime($this->calcType['setting'])<=strtotime($this->curdate)){	// 組付け代に割増率を適用しない
				$setting += $rec['setting'];
			}else{
				$setting += ceil( ($rec['setting']*$ratio)/10 ) * 10;
			}
			$platefee += $setting;
			
			// シート準備代
			$platefee += $rec['presheet'];
			
			// 版数をかける
			$platefee *= $hash[0];
			$design *= $hash[0];
			
			// シート代
			$sheetfee = $rec['ink']+$rec[$paper]+$rec['printer']+$rec['print'];
			$sheetfee *= $hash[1];
			
			// プリント作業売上
			$printwork = ($setting+$rec['presheet'])*$hash[0] + $sheetfee;
			
			$rs = array($platefee, $sheetfee, $design, $printwork);
		}catch(Exception $e){
			$rs = array(0, 0, 0, 0);
		}
		
		$stmt->close();
		//$conn->close();
		return $rs;
	}
	
	
	/**
	 *		転写のシート数と版数を返す（デジタル、カラー（白Ｔと黒Ｔ））(Static)
	 *		@sheet[]	版ごとのプリント位置をキーにしたデザインの大きさのシートに対する割合（1, 0.5, 0.25）絵型に関係なく同じプリント位置を同デザインとみなす
	 *		@shot[]		版ごとのプリント箇所ごとの枚数
	 *
	 *		return		[版数,シート数]
	 */
	private static function getSheetCount($sheet, $shot){
		try{
			foreach($sheet as $plates=>$val){
				// デザインの大きい順、枚数の多い順でソート
				$tmp = array();
				foreach($val as $pos=>$size){
					$tmp[] = array('size'=>$size, 'volume'=>$shot[$plates][$pos]);
				}
				for($i=0; $i<count($tmp); $i++){
					$a[$i] = $tmp[$i]['size'];
					$b[$i] = $tmp[$i]['volume'];
				}
				array_multisort($a,SORT_DESC, $b,SORT_DESC, $tmp);
				
				// 版数
				$base = array();	// 面付けされた各デザインの枚数
				for($i=0; $i<count($tmp); $i++){
					$court += $tmp[$i]['size'];
					$idx = floor($court);	// 版数-1
					if(fmod($court,1)==0) $idx--;
					$base[$idx][] = $tmp[$i]['volume'];
					
					//$sheets += $shot[$plates][$pos]*$size; // シート数
				}
			}
			
			// シート数
			$sheets = 0;
			$cnt = count($base)-1;
			for($i=0; $i<$cnt; $i++){
				$sheets += max($base[$i]);
			}
			// 面付けで端数の部分
			$a = fmod($court,1);	// 端数
			if($a==0.25){		// 小
				$sheets += ceil($base[$cnt][0]/4);
			}else if($a==0.5){
				if(count($base[$cnt])==1){	// 中
					$sheets += ceil($base[$cnt][0]/2);
				}else{						// 小,小
					if($base[$cnt][0]!=$base[$cnt][1]){
						$max = max($base[$cnt]);
						$min = min($base[$cnt]);
						$s1 = ceil(max($base[$cnt])/2);	// 面付け「2,2」
						$s2 = min($base[$cnt]);			// 面付け「1,3」
						$sheets += min($s1, $s2); // シート数が少なくなる面付けを適用したシート数
					}else{
						$sheets += ceil($base[$cnt][0]/2);
					}
				}
			}else if($a==0.75){
				if(count($base[$cnt])==2){	// 中,小
					$sheets += max($base[$cnt][0], ceil($base[$cnt][1]/2));
				}else{						// 小,小,小
					// 一番枚数の多いデザインを2面付け
					$sheets += max(ceil($base[$cnt][0]/2), $base[$cnt][1], $base[$cnt][2]);
				}
			}else{
				$sheets += max($base[$cnt]);
			}
			
			// 基シート数（版数）
			$base = ceil($court);
			
			$res = array($base, $sheets);
			
			/*
			$a = fmod($court,1);	// 端数
			$b = floor($court);		// 整数値
			if($a==0.75){
				$sheets = $volume + $b*$volume;
				if($volume>3 && $platefee+$rec['presheet'] < floor($volume/4)*$sheetfee){
					$sheets = $volume-floor($volume/4) + $b*$volume;
					$base++;
				}
			}else{
				$sheets = ceil($a*$amount[0]) + $b*$amount[0];
			}
			*/
		}catch(Exception $e){
			$res = array(0, 0);;
		}
		return $res;
	}
	
}
?>
