<?php
/*
*	プリント代　クラス
*	charset UTF-8
*	log:	2014-02-10 組付け代への割増率の適用を2014-03-01から廃止
*			2014-08-15 当該クラス未使用
*			2014-11-20 Weblibで試験的に使用
*/
require_once dirname(__FILE__).'/jd/japaneseDate.php';
require_once dirname(__FILE__).'/MYDB2.php';
class Estimate Extends MYDB2 {
/**
*	calcSilkPrintFee			シルクスクリーンのプリント代を返す
*	calcInkjetFee				インクジェットのプリント代を返す
*	calcCuttingFee				カッティングのプリント代を返す
*	calcTransFee				転写のプレス代を返す（デジタル、カラー（白Ｔと黒Ｔ））
*	calcTransCommonFee			転写の版代とシート代を返す（デジタル、カラー（白Ｔと黒Ｔ））
*	getSheetCount				転写のシート数と版数を返す（デジタル、カラー（白Ｔと黒Ｔ））(Static)
*	getPrintRatio				当該アイテムのプリント割増率を返す
*	getWorkDay					今日注文確定とし希望納期までの営業日数を返す
*/

	private $curdate;	// 発送日
	/*
	*	setting	:	組付け代への割増率適用を廃止
	*/
	private $calcType = array(
							  'setting'=>'2014-03-01',
							);
	
	
	public function __construct($args){
		if(empty($args)){
			$this->curdate = date('Y-m-d');
		}else{
			$d = explode('-', $args);
			if(checkdate($d[1], $d[2], $d[0])==false){
				$this->curdate = date('Y-m-d');
			}else{
				$this->curdate = $args;
			}
		}
	}
	
	
	private function setCurdate($args){
		if(empty($args)){
			$this->curdate = date('Y-m-d');
		}else{
			$d = explode('-', $args);
			if(checkdate($d[1], $d[2], $d[0])==false){
				$this->curdate = date('Y-m-d');
			}else{
				$this->curdate = $args;
			}
		}
	}
	
	
	/**
	 *		シルクスクリーンのプリント代を返す
	 *		@amount		数量
	 *		@area		プリント箇所数（1で固定、箇所ごとに計算のため）
	 *		@inkcount	インク色数
	 *		@itemid		アイテムＩＤ
	 *		@ratio		割増率ID
	 *		@size		ジャンボ版であれば　1　そうでなければ　0
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
			if($itemid!=0){
				$ratio = $this->getPrintRatio($itemid);
			}else{
				$ratio = $this->getPrintRatio(0, $ratio);
			}
			$ratio *= $extra;
			
			$sql = "SELECT * FROM silkprice where silkapply<=? order by silkapply desc limit 1";
			$conn = parent::db_connect();
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("s", $this->curdate);
			$stmt->execute();
			$stmt->store_result();
			$r = parent::fetchAll($stmt);
			if(empty($r)) return 0;
			$rec = $r[0];
			
			if($repeat==0){
				$plates = $rec['plate'] + $rec['design'];
				$design = $rec['design'];
			}else{
				$plates = 0;
				$design = 0; 
			}
			
			$setting = $rec['operationcost'];
			if($repeat!=99){
				$setting += $rec['setting'];
			}
			
			$ink = $rec['print']+$rec['ink'];
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
				$ink = ($rec['print']/2+$rec['ink']);
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
		$conn->close();
		return $rs;
	}
	
	
	/**
	 *		インクジェット（白Ｔと黒Ｔ）のプリント代を返す
	 *		@option		白Ｔ:0(default), 黒Ｔ:1
	 *		@amount		数量
	 *		@area		プリント箇所数（1で固定、箇所ごとに計算のため）
	 *		@size		プリントサイズ（0:大、1:中、2:小）
	 *		@itemid		アイテムＩＤ
	 *		@ratio		割増率ID
	 *		@extra		スウェットの割増適用箇所の場合　default 1　（通常のカテゴリごとの割増率ratioを再割増）
	 *		@repeat		0：新版
	 *					1：リピート版		デザイン代を引く
	 *					99：				デザイン代と組付け代を引く
	 *
	 *		return		{'tot':プリント代, 'plates':版代＋デザイン代, 'setting':組付け代, 'press':プレス代}
	 */
	public function calcInkjetFee($option=0, $amount, $area, $size, $itemid=0, $ratio=1, $extra=1, $repeat=0){
		try{
			if($amount<1) return 0;
			if($itemid!=0){
				$ratio = $this->getPrintRatio($itemid);
			}else{
				$ratio = $this->getPrintRatio(0, $ratio);
			}
			$ratio *= $extra;
			
			$sql = "SELECT * FROM inkjetprice where inkjetapply<=? order by inkjetapply desc limit 1";
			$conn = parent::db_connect();
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("s", $this->curdate);
			$stmt->execute();
			$stmt->store_result();
			$r = parent::fetchAll($stmt);
			if(empty($r)) return 0;
			$rec = $r[0];
			
			if($repeat==0){
				$design = $rec['design'];
			}else{
				$design = 0;
			}
			
			$setting = 0;
			if($repeat!=99){
				$setting += $rec['setting'];
			}
			
			$pressfee = $rec['press_0']*$amount;
			$printfee = $rec['print_0']+$rec['ink_'.$size];
			if($option==1){	// 黒T
				$printfee += $rec['paste']+$rec['press_1']+$rec['print_1']+$rec['ink_'.$size];
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
		$conn->close();
		return $rs;
	}
	
	
	/**
	 *		カッティングのプリント代を返す
	 *		@amount		数量
	 *		@area		プリント箇所数（1で固定、箇所ごとに計算のため）
	 *		@size		プリントサイズ（0:大、1:中、2:小）
	 *		@itemid		アイテムＩＤ
	 *		@ratio		割増率
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
			if($itemid!=0){
				$ratio = $this->getPrintRatio($itemid);
			}else{
				$ratio = $this->getPrintRatio(0, $ratio);
			}
			$ratio *= $extra;
			
			$sql = "SELECT * FROM cuttingprice where cuttingapply<=? order by cuttingapply desc limit 1";
			$conn = parent::db_connect();
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("s", $this->curdate);
			$stmt->execute();
			$stmt->store_result();
			$r = parent::fetchAll($stmt);
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
		$conn->close();
		return $rs;
	}
	
	
	/**
	 *		転写のプレス代を返す（デジタル、カラー（白Ｔと黒Ｔ））
	 *		@tablename	プリント方法
	 *		@amount[]	プリント箇所ごとの枚数
	 *		@extra[]	スウェットの割増の配列、　default 1　（通常のカテゴリごとの割増率ratioを再割増）
	 *		@itemid		アイテムＩＤ
	 *		@ratio		割増率ID
	 *		@press[]	プリント箇所ごとのプレス準備代の有無（990,991: プレス準備代なし）
	 *
	 * 		return		プレス代
	 */
	public function calcTransFee($tablename, $amount, $extra, $itemid=0, $ratio=1, $press=0){
		try{
			if(max($amount)<1) return;
			if($itemid!=0){
				$ratio = $this->getPrintRatio($itemid);
			}else{
				$ratio = $this->getPrintRatio(0, $ratio);
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
			$conn = parent::db_connect();
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("s", $this->curdate);
			$stmt->execute();
			$stmt->store_result();
			$r = parent::fetchAll($stmt);
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
				$pressfee += ($rec['press']*$amount[$i])*($ratio * $extra[$i]);
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
		$conn->close();
		return $rs;
	}
	
	
	/**
	 *		転写の版代とシート代を返す（デジタル、カラー（白Ｔと黒Ｔ））
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
			$conn = parent::db_connect();
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("s", $this->curdate);
			$stmt->execute();
			$stmt->store_result();
			$r = parent::fetchAll($stmt);
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
		$conn->close();
		return $rs;
	}
	
	
	/**
	 *		転写のシート数と版数を返す（デジタル、カラー（白Ｔと黒Ｔ））(Static)
	 *		@sheet[]	版ごとのプリント位置をキーにしたデザインの大きさのシートに対する割合（1, 0.5, 0.25）絵型に関係なく同じプリント位置を同デザインとみなす
	 *		@shot[]		版ごとのプリント箇所ごとの枚数
	 *
	 *		return		[版数,シート数]
	 */
	public static function getSheetCount($sheet, $shot){
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
	
	
	/**
	 *		当該アイテムのプリント割増率を返す
	 *		@itemid		アイテムのID
	 *		@ratioid		割増率ID（default is 0）
	 *
	 *		return			割増率
	 */
	public function getPrintRatio($itemid, $ratioid=null){
		try{
			if(is_null($ratioid)){
				$param = $itemid;
				$sql= "SELECT * FROM item inner join printratio on item.printratio_id=printratio.ratioid WHERE item.id=? and printratioapply<=? order by printratioapply desc limit 1";
			}else{
				$param = $ratioid;
				$sql= "SELECT * FROM printratio WHERE ratioid=? and printratioapply<=? order by printratioapply desc limit 1";
			}
			$conn = parent::db_connect();
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("is", $param, $this->curdate);
			$stmt->execute();
			$stmt->store_result();
			$rec = parent::fetchAll($stmt);
			$rs = $rec[0]['ratio'];
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}
	
	
	/*
	*	今日注文確定とし希望納期までの営業日数を返す
	*	depend: class japaneseDate
	*
	*	@targetSec		納期（UNIXタイムスタンプの秒数）
	*	@cuttime		〆時刻（default: 13:00）
	*	@_from_holiday	お休み開始日（UNIXタイムスタンプの秒数）
	*	@_to_holiday	お休み最終日（UNIXタイムスタンプの秒数）
	*
	*	return		営業日数を返す
	*/
	public function getWorkDay($targetSec, $cuttime=13, $_from_holiday=0, $_to_holiday=0){
		$jd = new japaneseDate();
		$workday=0;
		$one_day=86400;
		
		// 作業開始日の00:00のtimestampを取得
		$adjust_hour = 24-$cuttime;							// 13:00からは翌日扱いのため11時間の秒数分を足す
		$time_stamp = time()+(60*60*$cuttime);
		$year  = date("Y", $time_stamp);
		$month = date("m", $time_stamp);
		$day   = date("d", $time_stamp);
		$baseSec = mktime(0, 0, 0, $month, $day, $year);
		
		while($baseSec<$targetSec){
			$fin = $jd->makeDateArray($baseSec);
			if( (($fin['Weekday']>0 && $fin['Weekday']<6) && $fin['Holiday']==0) && ($baseSec<$_from_holiday || $_to_holiday<$baseSec) ){
				$workday++;
			}
			$baseSec += $one_day;
		}
		
		return $workday;
	}
}
?>