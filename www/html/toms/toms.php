<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/MYDB2.php';

/**
 *	改行コードをCRLFにするフィルタクラスの定義
 * 	log		: 2016-06-06 created
 */ 
class crlf_filter extends php_user_filter {
    function filter($in, $out, &$consumed, $closing) {
		while ($bucket = stream_bucket_make_writeable($in)) {
		// make sure the line endings aren't already CRLF
			$bucket->data = preg_replace("/(?<!\r)\n/", "\r\n", $bucket->data);
			$consumed += $bucket->datalen;
			stream_bucket_append($out, $bucket);
		}
		return PSFS_PASS_ON;
	}
}

/**
 *	トムスへのEDI発注用のCSVファイルの生成(Shift-JIS)
 *	log		: 2014-05-02 toms_size_nameにGS,GM,GLが無いため変換とJANコードが取得できない時の例外処理
 *			: 2014-06-11 運送業者, 土曜配送, 日曜祝日配送, PP袋有無, の指定を追加
 *			: 2014-06-11 送付先の指定を追加
 *			: 2014-11-15 DBクラスを更新
 * 			: 2016-06-06 Linuxで改行コードをCRLFにするためフィルターを使用
 *			: 2017-03-17 顧客名をフリガナに変更
 */
class Toms Extends MYDB2 {
	
	public function __construct(){
	}

	/*
	*	送付先住所
	*/
	private $addresslist = array('',
		array(
			'code'=>'14240',
			'destination'=>'タカハマライフアート',
			'zipcode'=>'124-0025',
			'addr1'=>'東京都',
			'addr2'=>'葛飾区西新小岩３－１４－２６',
		),
		array(
			'code'=>'242753',
			'destination'=>'タカハマライフアート第２工場',
			'zipcode'=>'124-0025',
			'addr1'=>'東京都',
			'addr2'=>'葛飾区西新小岩３－２９－７',
		),
	);
	
	/*
	*	発注ファイル（CSV）の生成
	*	@args			{'orders_id':受注No., 'deliver':運送業者, 'saturday':土曜配送, 'holiday':日曜祝日配送, 'pack':PP袋有無, 'destination':送付先}
	*
	*	return			1:成功, 2:受注データなし, 3:JANコードなし, 4:その他エラー
	*/
	public function orderform($args){
		try{
			$reply = 0;
			$shipp_number = 1;
			$row_id = 0;
			$order_date = date('Ymd');
			$record = array();
			$order_id = htmlspecialchars($args['orders_id'], ENT_QUOTES);
			$deliver = sprintf("%02d", $args['deliver']);
			$saturday = empty($args['saturday'])? '': 1;
			$holiday = empty($args['holiday'])? '': 1;
			$addr = $this->addresslist[$args['destination']];
			if(empty($addr)) $addr = $this->addresslist[1];
			
			$conn = parent::db_connect();
			
			// JAN CODE
			$sql = "select jan_code, amount, customername, customerruby, staffname, package_no from (((((orders
					 inner join customer on customer_id=customer.id)
					 inner join orderitem on orders.id=orderitem.orders_id)
					 inner join staff on orders.reception=staff.id)
					 inner join acceptstatus on orders.id=acceptstatus.orders_id)
					 inner join progressstatus on orders.id=progressstatus.orders_id)
					 left join itemstock on master_id=stock_master_id
					 where created>'2011-06-05' and progress_id=4 and shipped=1 and stock_maker=1 and orders.id=?
					 and orderitem.size_id=stock_size_id
					 group by master_id, jan_code";
			$stmt_order = $conn->prepare($sql);
			$stmt_order->bind_param("i", $order_id);
			$stmt_order->execute();
			$stmt_order->store_result();
			$rec = parent::fetchAll($stmt_order);
			
			// OPP袋の枚数確認
			$pack = empty($rec[0]['package_no'])? 1: 0;
			
			for($i=0; $i<count($rec); $i++){
				if(empty($rec[$i]['jan_code'])){
					throw new Exception('empty code');
				}
				
				$row_id++;
				$customername = mb_convert_kana($rec[$i]['customerruby'], 'ASKV', 'utf-8');	// 全角に変換
				$customername1 = mb_substr($customername, 0, 16, 'utf-8');					// マルチバイトの切り出し
				$customername1 = mb_convert_encoding($customername1, 'sjis', 'utf-8');		// shift_jisに変換
				$customername2 = mb_substr($customername, 0, 12, 'utf-8');					// マルチバイトの切り出し
				$customername2 = mb_convert_encoding($customername2, 'sjis', 'utf-8');		// shift_jisに変換
				$staffname = mb_convert_kana($rec[$i]['staffname'], 'ASKV', 'utf-8');		// 全角に変換
				$staffname = mb_substr($staffname, 0, 16, 'utf-8');							// マルチバイトの切り出し
				$staffname = mb_convert_encoding($staffname, 'sjis', 'utf-8');				// shift_jisに変換
				
				$rs = array();
				$rs[] = "6372";					//  1.取引先コード,固定
				$rs[] = "A001";					//  2.メッセージ識別子,固定
				$rs[] = $order_id;				//  3.お客様発注番号,
				$rs[] = $row_id;				//  4.お客様発注行No.,
				$rs[] = $shipp_number;			//  5.出荷単位番号(4桁),
				$rs[] = $order_date;			//  6.発注データ送信日,
				$rs[] = "";						//  7.発注回答データ送信日,記載不要
				$rs[] = "01";					//  8.発注区分2,固定
				$rs[] = "01";					//  9.送付先区分,お取引先様
				$rs[] = $addr['code'];			// 10.送付先コード,貯め打ちサービスあ使用
				$rs[] = $addr['destination'];	// 11.送付先宛名,
				$rs[] = $addr['zipcode'];		// 12.送付先郵便番号,
				$rs[] = $addr['addr1'];			// 13.送付先都道府県,
				$rs[] = $addr['addr2'];			// 14.送付先住所,
				$rs[] = "";						// 15.送付先担当部署,
				$rs[] = "";						// 16.送付先担当者名,
				$rs[] = "03-5670-0787";			// 17.送付先電話番号,
				$rs[] = $staffname;				// 18.発注担当者名,
				$rs[] = "";						// 19.出荷主区分,
				$rs[] = "";						// 20.出荷主名,
				$rs[] = "";						// 21.出荷主住所,
				$rs[] = "";						// 22.出荷主電話番号,
				$rs[] = $pack;					// 23.OPP袋,0:不要, 1:必要
				$rs[] = "01";					// 24.商品判断区分,01:ＪＡＮコード, 02:TOMSコード
				$rs[] = $rec[$i]['jan_code'];	// 25.JANコード,
				$rs[] = "";						// 26.TOMS商品コード,
				$rs[] = "";						// 27.TOMSカラーコード,
				$rs[] = "";						// 28.TOMSサイズコード,
				$rs[] = $rec[$i]['amount'];		// 29.発注数,
				$rs[] = $customername1."　様";	// 30.明細備考,顧客名
				$rs[] = "";						// 31.在庫引当数,記載不要
				$rs[] = "";						// 32.欠品数,記載不要
				$rs[] = "";						// 33.入荷予定日1,記載不要
				$rs[] = "";						// 34.入荷予定数1,記載不要
				$rs[] = "";						// 35.入荷予定日2,記載不要
				$rs[] = "";						// 36.入荷予定数2,記載不要
				$rs[] = "";						// 37.受付No.,記載不要
				$rs[] = "02";					// 38.欠品判断単位,注文単位
				$rs[] = "02";					// 39.欠品時処理,全数不要
				$rs[] = "2";					// 40.翌日出荷区分,しない
				$rs[] = "1";					// 41.運送会社指定,する
				$rs[] = $deliver;				// 42.運送会社コード,01:佐川, 02:福山, 03:ヤマト
				$rs[] = "01";					// 43.運送便種コード,元払い
				$rs[] = "";						// 44.代金引換,
				$rs[] = $saturday;				// 45.土曜日配送フラグ,可能
				$rs[] = $holiday;				// 46.日曜日配送フラグ,
				$rs[] = $holiday;				// 47.祝日配送フラグ,
				$rs[] = "午前着　".$customername2."様";	// 48.送り状備考,午前着の指定と顧客名
				$rs[] = "";						// 49.エラーコード,記載不要
				$rs[] = "";						// 50.エラー内容,記載不要
				$rs[] = "";						// 51.予備１,
				$rs[] = "";						// 52.予備２,
				$rs[] = "";						// 53.出荷予定日,記載不要
				
				$record[] = $rs;
			}
			
			if(!empty($record)){
				$dir_path = "./order".$order_date;
				if(!file_exists($dir_path)){
					mkdir($dir_path, 0707);
				}
				chmod($dir_path, 0707);		// umaskが指定されている場合に対応
				$filename = $dir_path."/order".$order_date."_".$order_id.".csv";
				$fp = fopen($filename, 'w');
				
				// フィルタの登録
				stream_filter_register('crlf', 'crlf_filter');
	
				// 出力ファイルへフィルタをアタァッチ
				stream_filter_append($fp, 'crlf');
	
				if($fp==false) return 4;
				for($i=0; $i<count($record); $i++){
					fputcsv($fp, $record[$i]);
				}
				fclose($fp);
				$reply = 1;
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
}

?>
