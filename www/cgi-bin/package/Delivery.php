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
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/DateJa/vendor/autoload.php';
use Alesteq\DateJa\DateJa;
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
			$ja = new DateJa();
			$workday = 0;
			$one_day = 86400;

			// 作業開始日の00:00のtimestampを取得
			$time_stamp = time()+39600;				// 13:00からは翌日扱いのため11時間の秒数分を足す
			$year  = date("Y", $time_stamp);
			$month = date("m", $time_stamp);
			$day   = date("d", $time_stamp);
			$baseSec = mktime(0, 0, 0, (int)$month, (int)$day, (int)$year);

			while ($baseSec < $targetSec) {
				$fin = $ja->makeDateArray($baseSec);
				if ( (($fin['Weekday']>0 && $fin['Weekday']<6) && $fin['Holiday']==0) && ($baseSec<$_from_holiday || $_to_holiday<$baseSec) ) {
					$workday++;
				}
				$baseSec += $one_day;
			}
		} catch (Exception $e) {
			$workday = 0;
		}
		return $workday;
	}


	/**
	 * 今注文確定日からお届け日を計算
	 * ３営業日＋発送日（営業日）＋配達日数（土日含む）
	 * 土日を休業日とする
	 *
	 * @param {int} baseSec 注文確定日（UNIXタイムスタンプの秒数）{@code 0 は今日}
	 * @param {int} workday 作業日数（発送日を含む）
	 * @param {int} transport 配送日数（通常は１日、北海道、九州、本州離島、島根隠岐郡は配送に2日）
	 * @param {int} extraday 作業日数に加算する日数
	 * @return {array} お届日付情報 {'year','month','day','weekname'}
	 */
	public function getDelidate(int $baseSec=0, int $workday=4, int $transport=1, int $extraday=0): array {
		try {
			$_from_holiday = strtotime(_FROM_HOLIDAY);		// お休み開始日
			$_to_holiday	= strtotime(_TO_HOLIDAY);		// お休み最終日
			$ja = new DateJa();
			$one_day = 86400;
			$counter=0;										// 作業に要する日数をカウント
			$workday += $extraday;

			if(empty($baseSec)){
				$time_stamp = time()+39600;					// 13:00からは翌日扱いのため11時間の秒数分を足す
				$year  = date("Y", $time_stamp);
				$month = date("m", $time_stamp);
				$day   = date("d", $time_stamp);
				$baseSec = mktime(0, 0, 0, (int)$month, (int)$day, (int)$year);
			}

			// 発送日を算出
			while($counter<$workday){
				$fin = $ja->makeDateArray($baseSec);
				if( (($fin['Weekday']>0 && $fin['Weekday']<6) && $fin['Holiday']==0) && ($baseSec<$_from_holiday || $_to_holiday<$baseSec) ){
					$counter++;
				}
				$baseSec += $one_day;
			}

			// 配送日数（通常は作業日数に配送日数の１日も入っているため）
			$baseSec += ($one_day * (--$transport));

			// お届け日の日付情報
			$fin = $ja->makeDateArray($baseSec);
		} catch (Exception $e) {
			$fin = array();
		}
		return $fin;
	}


	/**
	 * 指定月のカレンダー配列を取得します
	 *
	 * @param {int} year 年
	 * @param {int} month 月
	 * @return {array}
	 */
	public function getCalendar(int $year, int $month): array
	{
		$ja = new DateJa();
		return $ja->getCalendar($year, $month);
	}
}
?>