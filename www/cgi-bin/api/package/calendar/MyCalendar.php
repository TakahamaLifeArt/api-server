<?php
/**
 * カレンダー
 * 通常表示
 * @package calendar
 * @author (c) 2014 ks.desk@gmail.com
 */
declare(strict_types=1);
namespace package\calendar;
require_once 'Calendar.php';
class MyCalendar extends Calendar {

	private $hi;

	/**
	 * construct
	 * @param {int} year		年
	 * @param {int} month 		月
	 * @param {stinrg} locale	国指定（locale ID）
	 */
	public function __construct(int $year=0, int $month=0, string $locale='ja') {
		parent::__construct($year, $month);
		include_once dirname(__FILE__)."/../holiday/".$locale.DIRECTORY_SEPARATOR.'HolidayInfo.php';
		$hlidayInfoClass = 'package\holiday\\'.$locale.'\HolidayInfo';
		$this->hi = new $hlidayInfoClass;
	}

	/**
	 * カレンダー表示
	 * @param {array} me ユーザー情報
	 * @param {int} mode 0:通常(default)　1:休業日設定モード
	 * @return {array} "calendar"=>カレンダーのHTMLタグ, "pick"=>選択日付yyyy-mm-dd,
	 *					"dayoff"=>[0:sunday, 1:monday, ..., 6:saturday, 7:祝日] 定休日は１、営業は０
	 *					"holiday"=>{yyyy-mm-dd:0|1, ...} 休日は１、営業は０
	 */
	public function render(array $me=null, int $mode=0): array {
		// ユーザー情報を設定
		$bypassWeek = array();	// 定休日のインデックスを保持
		$bypassDate = array();	// 定休日以外の休業日のtimestampをキーにした日付文字列(yyyy-mm-dd)のハッシュ
		$pickTimestamp = 0;		// 選択された日付のタイムスタンプ
		if (empty($me)) {
//			$workday = 4;
//			$deliveryday = 1;
			$pick = 0;
			$dayOff = array(0,0,0,0,0,0,0,0);	// [0:sunday, 1:monday, ..., 6:saturday, 7:祝日] 定休日は１、営業は０
//			$modify = 0;
		} else {
//			$workday = $me['workday'];
//			$deliveryday = $me['deliveryday'];
			
			if (!empty($me['pick'])) {
				$pick = $me['pick'];
				$timestamp = strtotime($pick);
				if ($timestamp) {
					$timestamp = mktime(0, 0, 0, (int)date("m", $timestamp), (int)date("d", $timestamp), (int)date("Y", $timestamp));
					$pickTimestamp = $timestamp;
				}
			}
			
			$dayOff = array(
				$me['onsundays'],
				$me['onmondays'],
				$me['ontuesdays'],
				$me['onwednesdays'],
				$me['onthursdays'],
				$me['onfridays'],
				$me['onsaturdays'],
				$me['onholidays'],
			);
			for ($i=0; $i<7; $i++) { 
				if ($dayOff[$i]==1) {
					$bypassWeek[] = $i;
				}
			}
			foreach ($me['holiday'] as $key=>$val) {
				if ($val==0) continue;
				$timestamp = strtotime($key);
				$timestamp = mktime(0, 0, 0, (int)date("m", $timestamp), (int)date("d", $timestamp), (int)date("Y", $timestamp));
				$bypassDate[$timestamp] = $key;
			}
//			if ($me['holidaysetting']==1) {
//				$modify = ' is-appeared';
//			} else {
//				$modify = '';
//			}
		}

		// 発送日とお届け日の算出
//		$oneday = 60*60*24;
//		$tmp = $this->hi->getWorkingDay($orderTime, $workday, FALSE, $dayOff[7], $bypassWeek, $bypassDate);
//		$ship = end($tmp);
//		$shipTimestamp = $ship['time_stamp'];
//		$deliTimestamp = $shipTimestamp+$oneday*$deliveryday;

		$today = mktime(0, 0, 0, (int)date("n"), (int)date("j"), (int)date("Y"));
		$firstTimestamp = mktime(0, 0, 0, $this->month, 1, $this->year);
		$lastTimestamp = mktime(0, 0, 0, $this->month+1, 0, $this->year);

		// カレンダー情報
		if ($this->firstDay>0) {
			$timestamp = mktime(0, 0, 0, $this->month, 1-$this->firstDay, $this->year);
		} else {
			$timestamp = $firstTimestamp;
		}
		$lim = $this->firstDay + $this->days + (6 - $this->lastDay);
		$cal = $this->hi->getSpanCalendar(date('Y', $timestamp), date('n', $timestamp), date('j', $timestamp), $lim);

		// タグ生成
		$calendar = '';
		$week = 0;
		$len = count($cal);
		for ($i=0; $i < $len; $i++) { 
			if($cal[$i]['week']==0) $calendar .= "<tr>";

			$isHoliday = NULL;

			$calendar .= "<td class=\"ripplable";
			if (!empty($cal[$i]["holiday"])){
				if ($dayOff[7]==1) {
					$calendar .= " off";
				} else {
					$calendar .= " dayoff";
					$isHoliday = FALSE;
				}
			} elseif ($dayOff[$cal[$i]['week']]==1) {
				$calendar .= " off";
			
			} elseif(array_key_exists($cal[$i]["time_stamp"], $bypassDate)) {
				$calendar .= " off";
				$isHoliday = TRUE;
			} else {
				$isHoliday = FALSE;
			}

			if ($cal[$i]['week']==0) {
				$calendar .= " sun";
			} elseif ($cal[$i]['week']==6) {
				$calendar .= " sat";
			}

			if ($cal[$i]['time_stamp']<$firstTimestamp) {
				$calendar .= " pass";
			} elseif ($cal[$i]['time_stamp']>$lastTimestamp) {
				$calendar .= " yet";
			}

			if($cal[$i]["time_stamp"]==$pickTimestamp){
				$calendar .= " pick";
			}
			
			if ($cal[$i]["time_stamp"]==$today) {
				$calendar .= " today\"><div><ins>{$cal[$i]["day"]}</ins></div>";
			} else {
				$calendar .= "\"><ins>{$cal[$i]["day"]}</ins>";
			}

//			if ($mode!=1) {
//				if($cal[$i]["time_stamp"]==$orderTime){
//					$calendar .= "<div class=\"prog_start\"></div>";
//				}else if($orderTime<$cal[$i]["time_stamp"] && $cal[$i]["time_stamp"]<$shipTimestamp){
//					$calendar .= "<div class=\"prog_work\"></div>";
//				}else if($cal[$i]["time_stamp"]==$shipTimestamp){
//					$calendar .= "<div class=\"prog_ship\"></div>";
//				}else if($shipTimestamp<$cal[$i]["time_stamp"] && $cal[$i]["time_stamp"]<$deliTimestamp){
//					$calendar .= "<div class=\"prog_work\"></div>";
//				}else if($cal[$i]["time_stamp"]==$deliTimestamp){
//					$calendar .= "<div class=\"prog_end\"></div>";
//				}
//			}

//			if ($isHoliday === TRUE) {
//				$calendar .= '<i class="material-icons dayoff is-selected'.$modify.'">&#xE837;</i>';
//				$calendar .= '<i class="material-icons dayon">&#xE836;</i>';
//			} elseif ($isHoliday === FALSE) {
//				$calendar .= '<i class="material-icons dayoff">&#xE837;</i>';
//				$calendar .= '<i class="material-icons dayon is-selected'.$modify.'">&#xE836;</i>';
//			}

			$calendar .= "</td>";
			if ($cal[$i]['week']==6) $calendar .= "</tr>";
		}
//		$orderTime = 0;
//		$shipTimestamp = 0;
//		$deliTimestamp = 0;
		return array(
			"calendar"=>$calendar,
			"pick"=>$pick,
//			"order"=>date("Y-m-d",$orderTime),
//			"ship"=>date("Y-m-d",$shipTimestamp),
//			"delivery"=>date("Y-m-d",$deliTimestamp),
//			"workday"=>$workday,
//			"deliveryday"=>$deliveryday,
			"dayoff"=>$dayOff
		);
	}

	/**
	 * 祝日情報の配列を返す
	 * @param {int} timestamp 指定月のタイムスタンプ
	 * @return {array} 当該月の祝日をキーにした一意に識別できる定数のハッシュ
	 */
	public function getHoliday(int $timestamp): array {
		return $this->hi->getHolidayList($timestamp);
	}
}
