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
 * estimateEach			アイテムごとに、シルクとデジタル転写とインクジェットで最安の見積りを計算
 * printfee				シルクとデジタル転写及びインクジェットで最安のプリント代を返す。但しインクジェットはプリント可能なアイテムのみ
 * calcSilkPrintFee		シルクスクリーンのプリント代を返す
 * calcInkjetFee		インクジェット（白Ｔ:inkjetと黒Ｔ:darkinkjet）のプリント代を返す
 * calcCuttingFee		カッティングのプリント代を返す
 * calcTransFee			転写のプレス代を返す（デジタル:digit、カラー（白Ｔ:transと黒Ｔ:darktrans））
 * calcTransCommonFee	転写の版代とシート代を返す（デジタル:digit、カラー（白Ｔ:transと黒Ｔ:darktrans））
 *
 *	(private)
 * getSheetCount		(Static)転写のシート数と版数を返す（デジタル、カラー（白Ｔと黒Ｔ））
 * getPermutation		プリント方法の組み合わせパターンを返す
 * permute				重複順列のパターンを取得する再帰モジュール
 * getPrintRatio		アイテムのプリント割増率を返す
 * getIteminfo（廃止）	アイテムIDから商品情報を返す(category_idを取得)
 */	
	
	private $max_ink_count = 3;							// インクが3色より多い場合は転写
	private $sheet_size = 1;							// 転写のデザインサイズ（シートに対する割合）1:大　0.5:中　0.25:小
	private $design_size = 0;							// インクジェットのデザインの大きさ。0:大　1:中　2:小
	private $calcType = array('setting'=>'2014-03-01');	// この日より組付け代に割増率を適用しない
	private $curdate;
	private $conn;
	
	public function __construct($sheetsize='1', $designsize='0'){
		if($sheetsize=='1' || $sheetsize=='0.5' || $sheetsize=='0.25'){
			$this->sheet_size = $sheetsize;
		}
		if($designsize=='0' || $designsize=='1' || $designsize=='2'){
			$this->design_size = $designsize;
		}
		$this->curdate = date('Y-m-d');
		$this->conn = MYDB2::getConnection();
	}
	
	
	public function __destruct(){
		$this->conn->close();
	}
	
	
	/**
	*	アイテム情報を返す
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
			$rec = self::fetchAll($stmt);
		}catch(Exception $e){
			$rec = '';
		}
		
		$stmt->close();
		$conn->close();
		return $rec;
	}
	*/
	
	
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
				$silk = $this->calcSilkPrintFee($data[$i]['amount'], $area, $data[$i]['ink'], NULL, $ratio);
				$ary[$idx++] = $silk['tot'];
				if(in_array($id, $ids)){
					$inkjet = $this->calcInkjetFee($data[$i]['amount'], $area, $this->design_size, NULL, $ratio);
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
							$fee = $this->calcInkjetFee($volume, 1, $this->design_size, NULL, $v['ratio'], $optionValue, $v['extra']);
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
						$fee = $this->calcSilkPrintFee($v['amount'], 1, $v['ink'], NULL, $v['ratio'], $jumbo, $v['extra']);
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
	public function calcSilkPrintFee($amount, $area, $inkcount, $itemid=0, $ratio=1, $size=0, $extra=1, $repeat=0){
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
	public function calcInkjetFee($amount, $area, $size, $itemid=0, $ratio=1, $option=0, $extra=1, $repeat=0){
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
	public function calcCuttingFee($amount, $area, $size, $itemid=0, $ratio=1, $extra=1, $repeat=0){
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
