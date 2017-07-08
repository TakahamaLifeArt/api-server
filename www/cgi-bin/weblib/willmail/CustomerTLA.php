<?php
/*
 * REST API 顧客レコード登録更新
 * takahamalifeart用
 * @package willmail
 * @author (c) 2014 ks.desk@gmail.com
 */

require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/weblib/willmail/proparty.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/weblib/willmail/Customer.php';

class CustomerTLA extends Customer
{
	/**
	 * constructor
	 * @param {string} args レコード比較で使用する一意に識別できるフィールドコード
	 */
	public function __construct($args)
	{
		parent::__construct($args);
	}
	
	
	/**
	 * 顧客データ抽出
	 * @param {string} args 検索クエリで使用する最終更新日付、YYY-MM-DDやISO8601などのフォーマットにも対応
	 * @return {array} 顧客レコード
	 * @throws 差分データ取得で失敗した場合はエラーメッセージ
	 */
	public function getRecord($args)
	{
		try {
			// 配達時間
			$deliTime = array(
				'', '午前中', '12-14', '14-16', '16-18', '18-20', '19-21', 
			);
			// 支払い方法
			$payment = array(
				'wiretransfer' =>'振込',
				'credit' =>'カード',
				'conbi' =>'コンビニ決済',
				'cod' =>'代金引換',
				'cash' =>'現金',
				'other' =>'その他',
			);
			// プリント方法
			$printMethod = array(
				'silk'=>0, 
				'inkjet'=>0, 
				'trans'=>0, 
				'cutting'=>0, 
				'embroidery'=>0,
			);
			$l = count($printMethod);
			
			// 指定日以降に更新があった顧客データを取得
//			$tmp = $this->prepared(_SQL_LAST_MODIFY, array('ss', $args, $args));
//			$listModify = array();
//			$recordCount = -1;
//			$len = count($tmp);
//			for ($i = 0; $i < $len; $i++) {
//				$schedule1 = $tmp[$i]['schedule1']=='0000-00-00'? '': $tmp[$i]['schedule1'];
//				$schedule2 = $tmp[$i]['schedule2']=='0000-00-00'? '': $tmp[$i]['schedule2'];
//				$schedule3 = $tmp[$i]['schedule3']=='0000-00-00'? '': $tmp[$i]['schedule3'];
//				$schedule4 = $tmp[$i]['schedule4']=='0000-00-00'? '': $tmp[$i]['schedule4'];
//				if ($recordCount === -1 || $tmp[$i]['email'] !== $listModify[$recordCount]['email']) {
//					$recordCount++;
//					$listModify[$recordCount] = $tmp[$i];
//					$listModify[$recordCount]['order_count'] = 1;
//					if (!empty($schedule2)) {
//						$listModify[$recordCount]['first_order'] = $schedule2;
//						$listModify[$recordCount]['recent_order'] = $schedule2;
//					}
//					$listModify[$recordCount]['deliverytime'] = $deliTime[$tmp[$i]['deliverytime']];
//					$listModify[$recordCount]['payment'] = $payment[$tmp[$i]['payment']];
//					unset($listModify[$recordCount]['repeater']);
//					unset($listModify[$recordCount]['yy']);
//				} else {
//					$listModify[$recordCount]['customer_num'] = $tmp[$i]['customer_num'];
//					$listModify[$recordCount]['total_price'] += $tmp[$i]['total_price'];
//					$listModify[$recordCount]['order_count'] += 1;
//					$listModify[$recordCount]['order_amount'] += $tmp[$i]['order_amount'];
//					$listModify[$recordCount]['expressfee'] += $tmp[$i]['expressfee'];
//					$listModify[$recordCount]['express'] = $tmp[$i]['express'];
//					if (!empty($schedule2)) $listModify[$recordCount]['recent_order'] = $schedule2;
//					if (!empty($schedule1)) $listModify[$recordCount]['schedule1'] = $schedule1;
//					if (!empty($schedule3)) $listModify[$recordCount]['schedule3'] = $schedule3;
//					if (!empty($schedule4)) $listModify[$recordCount]['schedule4'] = $schedule4;
//					$listModify[$recordCount]['order_status'] = $tmp[$i]['order_status'];
//					$listModify[$recordCount]['purpose'] = $tmp[$i]['purpose'];
//					$listModify[$recordCount]['job'] = $tmp[$i]['job'];
//					$listModify[$recordCount]['manuscript'] = $tmp[$i]['manuscript'];
//					$listModify[$recordCount]['addfee'] = $tmp[$i]['addfee'];
//					$listModify[$recordCount]['packfee'] = $tmp[$i]['packfee'];
//					$listModify[$recordCount]['carriagefee'] = $tmp[$i]['carriagefee'];
//					$listModify[$recordCount]['printfee'] = $tmp[$i]['printfee'];
//					$listModify[$recordCount]['designfee'] = $tmp[$i]['designfee'];
//					$listModify[$recordCount]['creditfee'] = $tmp[$i]['creditfee'];
//					$listModify[$recordCount]['exchinkfee'] = $tmp[$i]['exchinkfee'];
//					$listModify[$recordCount]['deliverytime'] = $deliTime[$tmp[$i]['deliverytime']];
//					$listModify[$recordCount]['payment'] = $payment[$tmp[$i]['payment']];
//				}
//			}
			
			// 指定日以降に更新された受注データを取得
			$listRepeat = array();
			$ids = $this->prepared(_SQL_RECENT_ORDER, array('s', $args));
			if (!empty($ids)){
				foreach ($ids as $key => $val) {
					$customerIds[] = $val['customer_id'];
					$orderIds[] = $val['orders_id'];
				}
				$count = count($customerIds);
				$sql = _SQL_REPEAT_ORDER_1;
				$sql .= " and customer_id in(".implode( ' , ', array_fill(0, $count, '?') ).")";
				$sql .= _SQL_REPEAT_ORDER_2;
				$marker = implode( '', array_fill(0, $count, 'i') );
				array_unshift($customerIds, $marker);
				$tmp = $this->prepared($sql, $customerIds);
				$listRepeat = array();
				$recordCount = -1;
				$curId = 0;
				$len = count($tmp);
				for ($i = 0; $i < $len; $i++) {
					$schedule1 = $tmp[$i]['schedule1']=='0000-00-00'? '': $tmp[$i]['schedule1'];
					$schedule2 = $tmp[$i]['schedule2']=='0000-00-00'? '': $tmp[$i]['schedule2'];
					$schedule3 = $tmp[$i]['schedule3']=='0000-00-00'? '': $tmp[$i]['schedule3'];
					$schedule4 = $tmp[$i]['schedule4']=='0000-00-00'? '': $tmp[$i]['schedule4'];
					$printCode = $tmp[$i]['print_type']!=='digit'? $tmp[$i]['print_type']: 'trans';
					if ($recordCount === -1 || $tmp[$i]['email'] !== $listRepeat[$recordCount]['email']) {
						$recordCount++;
						$curId = $tmp[$i]['orderid'];
						$listRepeat[$recordCount] = $tmp[$i];
						$listRepeat[$recordCount]['order_count'] = 1;
						if (!empty($schedule2)) {
							$listRepeat[$recordCount]['first_order'] = $schedule2;
							$listRepeat[$recordCount]['recent_order'] = $schedule2;
						}
						$listRepeat[$recordCount]['deliverytime'] = $deliTime[$tmp[$i]['deliverytime']];
						$listRepeat[$recordCount]['payment'] = $payment[$tmp[$i]['payment']];
						
						$listRepeat[$recordCount]['noprint'] = $tmp[$i]['noprint'];
						if ($tmp[$i]['noprint']!=1) {
							$printMethod[$printCode] = 1;
							foreach ($printMethod as $printKey=>$num) {
								$listRepeat[$recordCount][$printKey] = $num;
							}
							$printMethod[$printCode] = 0;
						}
						
						unset($listRepeat[$recordCount]['repeater']);
						unset($listRepeat[$recordCount]['yy']);
						unset($listRepeat[$recordCount]['orderid']);
					} else {
						if ($curId!==$tmp[$i]['orderid']) {
							$listRepeat[$recordCount]['total_price'] += $tmp[$i]['total_price'];
							$listRepeat[$recordCount]['order_count'] += 1;
							$listRepeat[$recordCount]['order_amount'] += $tmp[$i]['order_amount'];
							$listRepeat[$recordCount]['expressfee'] += $tmp[$i]['expressfee'];
							
							$listRepeat[$recordCount]['item_category'] = $tmp[$i]['item_category'];
						} else {
							if (strpos($listRepeat[$recordCount]['item_category'], $tmp[$i]['item_category'])===false) {
								$listRepeat[$recordCount]['item_category'] += ",".$tmp[$i]['item_category'];
							}
						}
						
						if ($tmp[$i]['noprint']==1) {
							$listRepeat[$recordCount]['noprint'] += 1;
						} else {
							$listRepeat[$recordCount][$printCode] += 1;
						}
						$listRepeat[$recordCount]['customer_num'] = $tmp[$i]['customre_num'];
						$listRepeat[$recordCount]['express'] = $tmp[$i]['express'];
						if (!empty($schedule2)) $listRepeat[$recordCount]['recent_order'] = $schedule2;
						if (!empty($schedule1)) $listRepeat[$recordCount]['schedule1'] = $schedule1;
						if (!empty($schedule3)) $listRepeat[$recordCount]['schedule3'] = $schedule3;
						if (!empty($schedule4)) $listRepeat[$recordCount]['schedule4'] = $schedule4;
						$listRepeat[$recordCount]['order_status'] = $tmp[$i]['order_status'];
						$listRepeat[$recordCount]['purpose'] = $tmp[$i]['purpose'];
						$listRepeat[$recordCount]['job'] = $tmp[$i]['job'];
						$listRepeat[$recordCount]['manuscript'] = $tmp[$i]['manuscript'];
						$listRepeat[$recordCount]['addfee'] = $tmp[$i]['addfee'];
						$listRepeat[$recordCount]['packfee'] = $tmp[$i]['packfee'];
						$listRepeat[$recordCount]['carriagefee'] = $tmp[$i]['carriagefee'];
						$listRepeat[$recordCount]['printfee'] = $tmp[$i]['printfee'];
						$listRepeat[$recordCount]['designfee'] = $tmp[$i]['designfee'];
						$listRepeat[$recordCount]['creditfee'] = $tmp[$i]['creditfee'];
						$listRepeat[$recordCount]['exchinkfee'] = $tmp[$i]['exchinkfee'];
						$listRepeat[$recordCount]['deliverytime'] = $deliTime[$tmp[$i]['deliverytime']];
						$listRepeat[$recordCount]['payment'] = $payment[$tmp[$i]['payment']];
					}
				}
			}

			$result = $listRepeat;
//			foreach ($listModify as $val1) {
//				$isExists = FALSE;
//				foreach ($listRepeat as $key=>$val2) {
//					if ($val1[$this->primaryKey] === $val2[$this->primaryKey]) {
//						$isExists = TRUE;
//						break;
//					}
//				}
//				if (!$isExists) {
//					$result[] = $val1;
//				} else {
//					unset($listRepeat[$key]);
//					array_values($listRepeat);
//				}
//			}
		} catch (Exception $e) {
			$result = $e->getMessage();
			if (empty($result)) {
				$result = 'Error: CustomerTLA::getrecord';
			}
		}
		return $result;
	}
	
	
	/**
	 * 顧客リストの新規登録と修正更新
	 * @param {array} list データの配列
	 * @return {boolean|array} 成功した場合はTRUE
	 * 						   失敗した場合はエラーメッセージ
	 */
	public function upsertRecord($list)
	{
		try {
			if (empty($list)) return TRUE;

			$url = _URL . 'put';
			$header = array(
				'Content-Type: application/json',
				"authorization: Basic MThGNjk0QUZCMkIyNEE1MEI2M0ZCMUMxRjE5N0ExNTk6MzEzU0g3MVFMMjFZSDJRV01aUFpNVllB",
			);
			$len = count($list);
			for ($i=0; $i<$len; $i++) {
				$data = array(
					"field_1" => $list[$i]['customer_num'],	// 顧客ID
					"field_2" => $list[$i]['order_type'],	// 顧客区分
					"field_3" => $list[$i]['customername'],	// 顧客名
					"field_4" => $list[$i]['dept'],			// 担当
					"field_5" => mb_substr($list[$i]['customernote'], 0, 255, 'utf-8'),	// 備考
					"field_6" => $list[$i]['order_count'],	// 注文回数
					"field_7" => $list[$i]['total_price'],	// 注文金額
					"field_8" => $list[$i]['zipcode'],		// 郵便番号
					"field_9" => $list[$i]['addr0'],		// 都道府県
					"field_10" => $list[$i]['addr1'],		// 市区町村
					"field_11" => $list[$i]['addr2'],		// 建物名等
					"field_12" => $list[$i]['tel'],			// 電話番号
					"field_13" => $list[$i]['first_order'],	// 初回注文日
					"field_14" => $list[$i]['recent_order'],// 最終注文日
					"field_15" => $list[$i]['email'],		// メールアドレス
					"field_16" => $list[$i]['expressfee'],	// 特急売上げ累計
					"field_17" => $list[$i]['express'],		// 特急指定「0 or 1」
					"field_18" => $list[$i]['order_amount'],// 注文枚数累計
					"field_19" => $list[$i]['schedule4'],	// お届け日
					"field_20" => $list[$i]['item_category'],// 商品カテゴリー
					"field_21" => $list[$i]['order_status'],// 注文状況「問合せ中 or 注文確定」
					"field_22" => $list[$i]['silk'],		// シルクの注文回数
					"field_23" => $list[$i]['inkjet'],		// インクジェットの注文回数
					"field_24" => $list[$i]['trans'],		// 転写の注文回数
					"field_25" => $list[$i]['cutting'],		// カッティングの注文回数
					"field_26" => $list[$i]['embroidery'],	// 刺繍の注文回数
					"field_27" => $list[$i]['noprint'],		// プリントなしの注文回数
					"field_28" => $list[$i]['purpose'],		// 用途
					"field_29" => $list[$i]['job'],			// 職業
					"field_30" => $list[$i]['schedule1'],	// 入稿締め日
					"field_32" => $list[$i]['schedule3'],	// 発送日
					"field_34" => $list[$i]['manuscript'],	// 入稿方法
					"field_35" => $list[$i]['addfee'],		// 追加料金
					"field_38" => $list[$i]['packfee'],		// 袋詰代
					"field_39" => $list[$i]['carriagefee'],	// 送料
					"field_40" => $list[$i]['payment'],		// 支払い方法
					"field_41" => $list[$i]['printfee'],	// プリント代
					"field_42" => $list[$i]['designfee'],	// デザイン代
					"field_43" => $list[$i]['creditfee'],	// カード手数料
					"field_44" => $list[$i]['exchinkfee'],	// インク色替え代
					"field_45" => $list[$i]['deliverytime'],// 配達時間
				);
				//				$param = $this->json->encode($data);
				$param = json_encode($data);
				$http = new Http($url);
				$result = $http->requestRest('POST', $param, $header);	// WillMailへのメソッドはPOSTで固定
				if (TRUE !== $result) {
					throw new Exception($result);
				}
				$this->upsertCount['update'] = $i+1;
			}
		} catch (Exception $e) {
			$result = $e->getMessage();
			if (empty($result)) {
				$result = 'Error: Customer::upsertRecord';
			}
		}
		return $result;
	}
	
	
	/**
	 * 顧客リストをCSVファイルで新規登録と修正更新
	 * @param {array} list データの配列
	 * @return {boolean|array} 成功した場合はTRUE
	 * 						   失敗した場合はエラーメッセージ
	 */
	public function upsertCSV($list)
	{
		try {
			if (empty($list)) return TRUE;

			$url = _URL . 'import?charset=UTF-8&mode=upsert&emptyCol=keep_value';
			$header = array(
				"authorization: Basic MThGNjk0QUZCMkIyNEE1MEI2M0ZCMUMxRjE5N0ExNTk6MzEzU0g3MVFMMjFZSDJRV01aUFpNVllB",
			);
			$data = array();
			$len = count($list);
			for ($i=0; $i<$len; $i++) {
				$data[] = array(
					$list[$i]['customer_num'],	// 顧客ID
					$list[$i]['order_type'],	// 顧客区分
					$list[$i]['customername'],	// 顧客名
					$list[$i]['dept'],			// 担当
					mb_substr($list[$i]['customernote'], 0, 255, 'utf-8'),	// 備考
					$list[$i]['order_count'],	// 注文回数
					$list[$i]['total_price'],	// 注文金額
					$list[$i]['zipcode'],		// 郵便番号
					$list[$i]['addr0'],		// 都道府県
					$list[$i]['addr1'],		// 市区町村
					$list[$i]['addr2'],		// 建物名等
					$list[$i]['tel'],			// 電話番号
					$list[$i]['first_order'],	// 初回注文日
					$list[$i]['recent_order'],// 最終注文日
					$list[$i]['email'],		// メールアドレス
					$list[$i]['expressfee'],	// 特急売上げ累計
					$list[$i]['express'],		// 特急指定「0 or 1」
					$list[$i]['order_amount'],// 注文枚数累計
					$list[$i]['schedule4'],	// お届け日
					$list[$i]['item_category'],// 商品カテゴリー
					$list[$i]['order_status'],// 注文状況「問合せ中 or 注文確定」
					$list[$i]['silk'],		// シルクの注文回数
					$list[$i]['inkjet'],		// インクジェットの注文回数
					$list[$i]['trans'],		// 転写の注文回数
					$list[$i]['cutting'],		// カッティングの注文回数
					$list[$i]['embroidery'],	// 刺繍の注文回数
					$list[$i]['noprint'],		// プリントなしの注文回数
					$list[$i]['purpose'],		// 用途
					$list[$i]['job'],			// 職業
					$list[$i]['schedule1'],	// 入稿締め日
					$list[$i]['schedule3'],	// 発送日
					$list[$i]['manuscript'],	// 入稿方法
					$list[$i]['addfee'],		// 追加料金
					$list[$i]['packfee'],		// 袋詰代
					$list[$i]['carriagefee'],	// 送料
					$list[$i]['payment'],		// 支払い方法
					$list[$i]['printfee'],	// プリント代
					$list[$i]['designfee'],	// デザイン代
					$list[$i]['creditfee'],	// カード手数料
					$list[$i]['exchinkfee'],	// インク色替え代
					$list[$i]['deliverytime'],// 配達時間
				);
			}
			$filepath = $_SERVER['DOCUMENT_ROOT']."/weblib/customerlist.csv";
			$fp = fopen($filepath, 'wb');
			if($fp==false) {
				fclose($fp);
				throw new Exception('Error: file open');
			}
			$lbl = array('顧客ID','顧客区分','顧客名','担当','備考','注文回数','注文金額','郵便番号','都道府県','市区町村','建物名等','電話番号','初回注文日','最終注文日','メールアドレス','特急売上','特急指定','注文枚数','お届け日','商品カテゴリー','注文状況','シルク','インクジェット','転写','カッティング','刺繍','プリントなし','用途','職業','入稿締め日','発送日','入稿方法','追加料金','袋詰代','送料','支払い方法','プリント代','デザイン代','カード手数料','インク色替え代','配達時間');
			fputcsv($fp, $lbl);
			foreach($data as $line){
				fputcsv($fp, $line);
			}
			fclose($fp);
			
//			$data = file_get_contents($filepath);
//			if ($data === false) {
//				throw new Exception("Can not read file: $path");
//			}
//			
//			$param = json_encode($data);
//			$http = new Http($url);
//			$result = $http->requestRest('POST', $param, $header);	// WillMailへのメソッドはPOSTで固定
//			if (TRUE !== $result) {
//				throw new Exception($result);
//			}
			
			$this->upsertCount['update'] = $len;
			$result = true;
		} catch (Exception $e) {
			$result = $e->getMessage();
			if (empty($result)) {
				$result = 'Error: Customer::upsertCSV';
			}
		}
		return $result;
	}
}

?>