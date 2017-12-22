<?php
/**
 * 納期クラス for API3
 * charset utf-8
 *--------------------
 *
 * getWorkDay		今日注文確定とし希望納期までの営業日数を返す
 */
declare(strict_types=1);
namespace package;
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/config.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/api/package/holiday/ja/HolidayInfo.php';
use package\holiday\ja\HolidayInfo;
use \Exception;
class Delivery {

	public function __construct(string $curDate='') {}
	public function __destruct() {}


	/**
	 * 今日注文確定とし希望納期までの営業日数を返す
	 *
	 * @param {int} targetSec 納期（UNIXタイムスタンプの秒数）
	 * @return {int} 営業日数を返す
	 */
	public function getWorkDay($targetSec): int {
		try {
			$_from_holiday = strtotime(_FROM_HOLIDAY);				// お休み開始日
			$_to_holiday	= strtotime(_TO_HOLIDAY);				// お休み最終日
			$hi = new HolidayInfo();
			$workday = 0;
			$one_day = 86400;

			// 作業開始日の00:00のtimestampを取得
			$time_stamp = time()+39600;				// 13:00からは翌日扱いのため11時間の秒数分を足す
			$year  = date("Y", $time_stamp);
			$month = date("m", $time_stamp);
			$day   = date("d", $time_stamp);
			$baseSec = mktime(0, 0, 0, (int)$month, (int)$day, (int)$year);

			while ($baseSec < $targetSec) {
				$fin = $hi->makeDateArray($baseSec);
				if ( (($fin['Weekday']>0 && $fin['Weekday']<6) && $fin['Holiday']==0) && ($baseSec<$_from_holiday || $_to_holiday<$baseSec) ) {
					$workday++;
				}
				$baseSec += $one_day;
			}
		} catch (Exception $e) {
			$res = 0;
		}
		return $workday;
	}


	/**
	 * 今注文確定日からお届け日を計算
	 * ３営業日＋発送日（営業日）＋配達日数（土日含む）
	 * 土日を休業日とする
	 *
	 * @param {int} $baseSec 注文確定日（UNIXタイムスタンプの秒数）{@code null は今日}
	 * @param {int} workday 作業日数（発送日を含む）
	 * @param {int} transport 配送日数（通常は１日、北海道、九州、本州離島、島根隠岐郡は配送に2日）
	 * @param {int} extraday 作業日数に加算する日数
	 * @return {array} お届日付情報 {'year','month','day','weekname'}
	 */
	public function getDelidate(int $baseSec=null, int $workday=4, int $transport=1, int $extraday=0): array {
		$_from_holiday = strtotime(_FROM_HOLIDAY);		// お休み開始日
		$_to_holiday	= strtotime(_TO_HOLIDAY);		// お休み最終日
		$hi = new HolidayInfo();
		$one_day = 86400;
		$counter=0;										// 作業に要する日数をカウント
		$workday += $extraday;
		
		if(empty($baseSec)){
			$time_stamp = time()+39600;							// 13:00からは翌日扱いのため11時間の秒数分を足す
			$year  = date("Y", $time_stamp);
			$month = date("m", $time_stamp);
			$day   = date("d", $time_stamp);
			$baseSec = mktime(0, 0, 0, $month, $day, $year);	// 注文確定日の00:00のtimestampを取得
		}
		
		// 配送日数が1日の地域のお届け日を算出
		while($counter<$workday){
			$fin = $hi->makeDateArray($baseSec);
			if( (($fin['Weekday']>0 && $fin['Weekday']<6) && $fin['Holiday']==0) && ($baseSec<$_from_holiday || $_to_holiday<$baseSec) ){
				$counter++;
			}
			$baseSec += $one_day;
		}

		// 配送日数が1日を超える地域の場合にその超えた日数を足す
		$baseSec += ($one_day * ($transpor-1));
		
		$fin = $hi->makeDateArray($baseSec);

		// 曜日を取得
		$weekday = $hi->viewWeekday($fin['Weekday']);
		$fin['weekname'] = $weekday;

		return $fin;
	}


	/**
	 * お届日を4パターン一括で計算する
	 * @param {int} targetSec 注文確定日（UNIXタイムスタンプの秒数）
	 * @param {int} transport 配送日数（未指定は１日）
	 * @param mode		null以外の場合、袋詰の有無による作業日数の加算をおこなわない
	 *
	 * @return		[通常納期, 2日仕上げ, 翌日仕上げ, 当日仕上げ, 注文確定日(通常締め), 注文確定日(当日締め)]
	 */
//	public function getDeliveryDays () {
//		$hi = new japaneseDate();
//		$one_day = 86400;
//		$_from_holiday = strtotime(_FROM_HOLIDAY);				// お休み開始日
//		$_to_holiday	= strtotime(_TO_HOLIDAY);				// お休み最終
//
//		// 現在の日付
//		$time_stamp = time();
//		$year  = date("Y", $time_stamp);
//		$month = date("m", $time_stamp);
//		$day   = date("d", $time_stamp);
//
//		// 注文確定予定日
//		$post_year  = date("Y", $targetSec);
//		$post_month = date("m", $targetSec);
//		$post_day   = date("d", $targetSec);
//
//		// 当日の場合に計算開始日の00:00のtimestampを取得
//		if($year==$post_year && $month==$post_month && $day==$post_day){
//			$time_stamp = time()+46800;	// 当日仕上げの〆は11:00のため13時間の秒数分を足す
//			$year  = date("Y", $time_stamp);
//			$month = date("m", $time_stamp);
//			$day   = date("d", $time_stamp);
//			$time_stamp = mktime(0, 0, 0, $month, $day, $year);
//			// 休日の場合に翌営業日にする
//			$fin = $hi->makeDateArray($time_stamp);
//			while( (($fin['Weekday']==0 || $fin['Weekday']==6) || $fin['Holiday']!=0) || ($time_stamp>=$_from_holiday && $_to_holiday>=$time_stamp) ){
//				$time_stamp += $one_day;
//				$fin = $hi->makeDateArray($time_stamp);
//			}
//			$baseSec[] = $time_stamp;
//
//			$time_stamp = time()+39600;	// 通常納期、2日仕上げ、翌日仕上げは〆時間（13：00）からは翌日扱いのため11時間の秒数分を足す
//			$year  = date("Y", $time_stamp);
//			$month = date("m", $time_stamp);
//			$day   = date("d", $time_stamp);
//			$time_stamp   = mktime(0, 0, 0, $month, $day, $year);
//			// 休日の場合に翌営業日にする
//			$fin = $hi->makeDateArray($time_stamp);
//			while( (($fin['Weekday']==0 || $fin['Weekday']==6) || $fin['Holiday']!=0) || ($time_stamp>=$_from_holiday && $_to_holiday>=$time_stamp) ){
//				$time_stamp += $one_day;
//				$fin = $hi->makeDateArray($time_stamp);
//			}
//			$baseSec[] = $time_stamp;
//			$baseSec[] = $time_stamp;
//			$baseSec[] = $time_stamp;
//		}else{
//			// 休日の場合に翌営業日にする
//			$time_stamp = $targetSec;
//			$fin = $hi->makeDateArray($time_stamp);
//			while( (($fin['Weekday']==0 || $fin['Weekday']==6) || $fin['Holiday']!=0) || ($time_stamp>=$_from_holiday && $_to_holiday>=$time_stamp) ){
//				$time_stamp += $one_day;
//				$fin = $hi->makeDateArray($time_stamp);
//			}
//			$baseSec = array($time_stamp, $time_stamp, $time_stamp, $time_stamp);
//		}
//
//		$transport = 1;
//		if(isset($_REQUEST['transport'])){
//			if($_REQUEST['transport']==2) $transport = 2;
//		}
//
//		$mode = null;
//		if(isset($_REQUEST['mode'])) $mode = $_REQUEST['mode'];	// null以外の場合、袋詰の有無による作業日数の加算をおこなわない
//
//		// 納期計算
//		for($cnt=4,$i=3; $cnt>0; $cnt--,$i--){
//			$fin = $orders->getDelidate($baseSec[$i], $transport, $cnt, $mode);
//			$dat[] = $fin['Year'].'/'.$fin['Month'].'/'.$fin['Day'];
//		}
//
//		// 注文確定日を返す
//		if(count($dat)>0){
//			// 通常締め時間
//			$year  = date("Y", $baseSec[3]);
//			$month = date("m", $baseSec[3]);
//			$day   = date("d", $baseSec[3]);
//			$dat[] = $year.'/'.$month.'/'.$day;
//			// 当日締め時間
//			$year  = date("Y", $baseSec[0]);
//			$month = date("m", $baseSec[0]);
//			$day   = date("d", $baseSec[0]);
//			$dat[] = $year.'/'.$month.'/'.$day;
//		}
//
//	}
?>