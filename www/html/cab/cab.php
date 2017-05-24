<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/MYDB2.php';

class Cab Extends MYDB2 {
/**
*	キャブへのEDI発注処理クラス
*	charset Shift-JIS
*	log				: 2014-11-14 created
*/
	
	public function __construct(){
	}
	
	
	/*
	*	送付先住所
	*/
	private $addresslist = array('',
		array(
			'destination'=>'タカハマライフアート',
			'zipcode'=>'124-0025',
			'addr1'=>'東京都',
			'addr2'=>'葛飾区西新小岩３－１４－２６',
			'tel'=>'03-5670-0787',
		),
		array(
			'destination'=>'タカハマライフアート第２工場',
			'zipcode'=>'124-0025',
			'addr1'=>'東京都',
			'addr2'=>'葛飾区西新小岩３－２９－７',
			'tel'=>'03-5875-7019',
		),
	);
	
	
	/*
	*	発注ファイルの生成
	*	@args {受注No., 送付先住所(1 or 2)}
	*
	*	return	成功:ファイルパス、　失敗:エラーコード
	*/
	public function orderform($args){
		try{
			$record_len = 190;	// レコードのバイト数固定長
			$order_id = htmlspecialchars($args['orders_id'], ENT_QUOTES);
			$note = mb_convert_encoding(htmlspecialchars($args['cab_note'], ENT_QUOTES), 'sjis', 'utf-8');
			$addr = $this->addresslist[$args['destination']];
			if(empty($addr)) $addr = $this->addresslist[1];
			$user_number = str_pad($order_id, 18, " ", STR_PAD_RIGHT);
			
			// header
			$header = array(
				"header_class" => "H",
				"user_number" => $user_number,
				"note" => str_pad($note, 40, " ", STR_PAD_RIGHT),
				"payment" => "0000",	// 未設定
				"destination" => str_pad($addr['destination'], 30, " ", STR_PAD_RIGHT),
				"zipcode" => str_pad($addr['zipcode'], 8, " ", STR_PAD_RIGHT),
				"addr0" => str_pad($addr['addr1'], 8, " ", STR_PAD_RIGHT),
				"addr1" => str_pad($addr['addr2'], 30, " ", STR_PAD_RIGHT),
				"addr2" => str_pad("", 30, " ", STR_PAD_RIGHT),
				"tel" => str_pad($addr['tel'], 14, " ", STR_PAD_RIGHT),
				"detail_number" => 1,
				"shortage" => "0",	// 全明細の発注を中止
				"EOF" => "\r\n",
			);
			
			// details
			$details = array(
				"detail_class" => "S",
				"user_number" => $user_number,
				"detail_number" => 0,
				"jancode" => 0,
				"item_code" => str_pad("", 7, " ", STR_PAD_RIGHT),
				"color_code" => str_pad("", 4, " ", STR_PAD_RIGHT),
				"size_code" => str_pad("", 2, " ", STR_PAD_RIGHT),
				"amount" => 0,
				"filler" => str_pad("", 135, " ", STR_PAD_RIGHT),
				"EOF" => "\r\n",
			);
			
			$conn = parent::db_connect();
			
			// JAN CODE
			$sql ="select jan_code, amount, customername, opp, package_no from ((((((orders
				 inner join customer on customer_id=customer.id)
				 inner join orderitem on orders.id=orderitem.orders_id)
				 inner join acceptstatus on orders.id=acceptstatus.orders_id)
				 inner join progressstatus on orders.id=progressstatus.orders_id)
				 left join itemstock on master_id=stock_master_id)
				 left join catalog on catalog.id=master_id)
				 left join item on catalog.item_id=item.id
				 where created>'2011-06-05' and progress_id=4 and shipped=1 and stock_maker=2 and orders.id=?
				 and orderitem.size_id=stock_size_id
				 group by master_id, jan_code";
			$stmt_order = $conn->prepare($sql);
			$stmt_order->bind_param("i", $order_id);
			$stmt_order->execute();
			$stmt_order->store_result();
			$rec = parent::fetchAll($stmt_order);
			
			// 明細データ生成
			$data = array();
			$opp_amount = array(
				"small"=>array("jan_code"=>"4527078285956", "amount"=>0),
				"big"=>array("jan_code"=>"4527078285970", "amount"=>0),
			);
			for($i=0; $i<count($rec); $i++){
				if(empty($rec[$i]['jan_code'])){
					throw new Exception('empty code');
				}
				
				foreach($details as $key=>&$val){
					switch($key){
						case "detail_number":
							$val++;
							$data[] = str_pad($val, 4, " ", STR_PAD_RIGHT);
							break;
						case "jancode":
							$data[] = $rec[$i]['jan_code'];
							break;
						case "amount":
							$data[] = str_pad($rec[$i]['amount'], 4, " ", STR_PAD_RIGHT);
							break;
						default:
							$data[] = $val;
					}
				}
				
				// OPP袋の枚数確認
				if($rec[$i]['package_no']==0){
					if($rec[$i]['opp']==1){
						$opp_amount['small'][amount] += $rec[$i]['amount'];
					}else if($rec[$i]['opp']==2){
						$opp_amount['big'][amount] += $rec[$i]['amount'];
					}
				}
			}
			unset($val);
			
			// OPP袋の発注データ
			foreach($opp_amount as $key=>$opp){
				if($opp['amount']==0) continue;
				foreach($details as $key=>&$val){
					switch($key){
						case "detail_number":
							$val++;
							$data[] = str_pad($val, 4, " ", STR_PAD_RIGHT);
							break;
						case "jancode":
							$data[] = $opp['jan_code'];
							break;
						case "amount":
							$data[] = str_pad($opp['amount'], 4, " ", STR_PAD_RIGHT);
							break;
						default:
							$data[] = $val;
					}
				}
			}
			unset($val);
			$record_detail = implode("", $data);
			
			// ヘッダーデータ生成
			$record_header = "";
			$customername = mb_convert_kana($rec[0]['customername'], 'ASKV', 'utf-8');	// 全角に変換
			$customername = mb_substr($customername, 0, 18, 'utf-8');					// マルチバイトの切り出し
			$customername = mb_convert_encoding($customername, 'sjis', 'utf-8');		// shift_jisに変換
			$data = array();
			foreach($header as $key=>$val){
				switch($key){
					/*
					case "note":
						$data[] = str_pad($customername." 様", 40, " ", STR_PAD_RIGHT);
						break;
					*/
					case "detail_number":
						$data[] = str_pad($details['detail_number'], 4, " ", STR_PAD_RIGHT);
						break;
					default:
						$data[] = $val;
				}
			}
			$record_header = implode("", $data);
			
			if(!empty($record_detail)){
				$check = $details['detail_number']*$record_len + $record_len;
				$dir_path = "order";
				/*
				if(!file_exists($dir_path)){
					mkdir($dir_path, 0775);
				}
				chmod($dir_path, 0775);		// umaskが指定されている場合に対応
				*/
				$filename = $dir_path."/M".date('YmdHis');
				$len = file_put_contents($filename, $record_header.$record_detail);
				if($len===false){
					$reply = 4;		// ファイル生成に失敗
				}else if($len!=$check){
					$reply = $len;	// ファイル長が合わない
				}else{
					$reply = $filename;		// 成功
				}
			}else{
				$reply = 2;
			}
		
		}catch(Exception $e){
			$reply = 3;
		}
		
		$stmt_order->close();
		$conn->close();
		
		return $reply;
	}
	
	
	/*
	*	指定ディレクトリ内のファイルを再帰的に取得
	*	@dir		ディレクトリパス
	*	@pattern	検索するファイルのパターンを表す文字列（default is *）
	*
	*	return	[ファイルパス]
	*/
	public function getFileList($dir, $pattern="*") {
		$files = glob(rtrim($dir, '/').'/'.$pattern);
		$list = array();
		foreach ($files as $file) {
			if (is_file($file)) {
				$list[] = $file;
			}
			if (is_dir($file)) {
				$list = array_merge($list, getFileList($file.'/'.$pattern));
			}
		}
		
		return $list;
	}
	
	
	
	/*
	*	発注回答の内容を登録
	*
	*	@orders_id		受注No.
	*	@error			エラーコード
	*
	*	return 			1:success
	*/
	public function update_response($orders_id, $error){
		$res = 1;
		try{
			if(empty($orders_id)) return;
			if($error!=0){
				$response = 2;	// エラーまたは、欠品あり
			}else {
				$response = 1;	// 発注完了
			}
			$conn = parent::db_connect();
			$sql = "update progressstatus set cab_response=? where orders_id=?";
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("ii", $response, $orders_id);
			$stmt->execute();
			
			// 旧バージョンの発注フラグ
			if($response==1){
				// 発注担当者IDを取得
				$sql = "SELECT * from progressstatus where orders_id=?";
				$stmt = $conn->prepare($sql);
				$stmt->bind_param("i", $orders_id);
				$stmt->execute();
				$stmt->store_result();
				$rec = parent::fetchAll($stmt);
				
				// プリント進捗テーブルを更新
				$sql = "update printstatus set state_0=? where orders_id=?";
				$stmt = $conn->prepare($sql);
				$stmt->bind_param("ii", $rec[0]['ordering'], $orders_id);
				$stmt->execute();
			}
		}catch (Exception $e) {
			$res = "Exception Error; ".$e->getMessage();
		}
		$stmt->close();
		$conn->close();
		
		return $res;
	}
}

?>
