<?php
/**
 * Calendar abstract class
 * @package calendar
 * @author (c) 2014 ks.desk@gmail.com
 */
declare(strict_types=1);
namespace package\calendar;
abstract class Calendar {
	protected $_year = 0;			// 当該年
	protected $_month = 0;			// 当該月
	protected $_firstDay = null;	// １日の曜日(0:sunday, 1:monday, ..., 6:saturday)
	protected $_lastDay = null;		// 月末の曜日(0:sunday, 1:monday, ..., 6:saturday)
	protected $_days = null;		// 当該月の日数

	/**
	 * constructor
	 * @param {int} year	年
	 * @param {int} month 	月
	 */
	protected function __construct(int $year=0, int $month=0) {
		if (!checkdate($month, 1, $year)) {
			$year = (int)date('Y');
			$month = (int)date('m');
		}
		$timestamp = mktime(0, 0, 0, $month, 1, $year);
		$this->_year = $year;
		$this->_month = $month;
		$this->_firstDay = (int)date('w', $timestamp);
		$this->_lastDay = (int)date('w', mktime(0, 0, 0, $month+1, 0, $year));
		$this->_days = (int)date('t', $timestamp);
	}

	/**
	 * カレンダー表示
	 */
	abstract protected function render();
	
	/**
	 * 祝日情報の配列を返す
	 * @param {int} timestamp 指定月のタイムスタンプ
	 * @return {array} 当該月の祝日をキーにした一意に識別できる定数のハッシュ
	 */
	abstract protected function getHoliday(int $timestamp): array;
	
	
	/**
	 * 今日のカレンダー情報の配列を返す
	 * @return	{array} "time_stamp" => タイムスタンプ
	 * 					"year"       => 西暦
	 * 					"month"      => 月、01から12
	 * 					"day"        => 日、01から31
	 * 					"mm"		 => 月、1から12
	 * 					"dd"	     => 日、1から31
	 * 					"week"       => 曜日(0:sunday, 1:monday, ..., 6:saturday)
	 * 					"holiday"    => 祝日は定数、それ以外は0
	 */
	public function todayIs(){
		$today = mktime(0, 0, 0, (int)date("n"), (int)date("j"), (int)date("Y"));
		$holiday = $this->getHoliday($today);
		$dd = date("j");
		$res = array(
			"time_stamp" => $time_stamp, 
			"year" => date("Y"), 
			"month" => date("m"), 
			"day" => date("d"),
			"mm" => date("n"),
			"dd" => $dd,
			"week" => date('w'),
			"holiday" => isset($holiday[$dd]) ? $holiday[$dd] : 0, 
		);
		return $res;
	}
	
}
