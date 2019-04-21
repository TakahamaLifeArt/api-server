<?php
/**
 * 暦|祝日クラス
 * @package holiday
 * @author <ks.desk@gmail.com>
 *
 * Copyright © 2014 Yasushi Kyoda
 *
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/MIT
 */
declare(strict_types=1);
namespace package\holiday;

date_default_timezone_set('Asia/Tokyo');

/**
 * 祝日定数
 */
define("DJ_NO_HOLIDAY", 0);
define("DJ_NEW_YEAR_S_DAY", 1);
define("DJ_COMING_OF_AGE_DAY", 2);
define("DJ_NATIONAL_FOUNDATION_DAY", 3);
define("DJ_THE_SHOWA_EMPEROR_DIED", 4);
define("DJ_VERNAL_EQUINOX_DAY", 5);
define("DJ_DAY_OF_SHOWA", 6);
define("DJ_GREENERY_DAY", 7);
define("DJ_THE_EMPEROR_S_BIRTHDAY", 8);
define("DJ_CROWN_PRINCE_HIROHITO_WEDDING", 9);
define("DJ_CONSTITUTION_DAY", 10);
define("DJ_NATIONAL_HOLIDAY", 11);
define("DJ_CHILDREN_S_DAY", 12);
define("DJ_COMPENSATING_HOLIDAY", 13);
define("DJ_CROWN_PRINCE_NARUHITO_WEDDING", 14);
define("DJ_MARINE_DAY", 15);
define("DJ_AUTUMNAL_EQUINOX_DAY", 16);
define("DJ_RESPECT_FOR_SENIOR_CITIZENS_DAY", 17);
define("DJ_SPORTS_DAY", 18);
define("DJ_CULTURE_DAY", 19);
define("DJ_LABOR_THANKSGIVING_DAY", 20);
define("DJ_REGNAL_DAY", 21);
define("DJ_MOUNTAIN_DAY", 22);
define("DJ_EMPEROR_ENTHRONEMENT_DAY", 23);

/**
 * 特定月定数
 */
define("DJ_VERNAL_EQUINOX_DAY_MONTH", 3);
define("DJ_AUTUMNAL_EQUINOX_DAY_MONTH", 9);

/**
 * 曜日定数
 */
define("DJ_SUNDAY", 0);
define("DJ_MONDAY", 1);
define("DJ_TUESDAY", 2);
define("DJ_WEDNESDAY", 3);
define("DJ_THURSDAY", 4);
define("DJ_FRIDAY", 5);
define("DJ_SATURDAY", 6);


/**
 * 暦|祝日クラス
 */
class DateJa
{
	private $_holiday_name = array(
		0 => "",
		1 => "元旦",
		2 => "成人の日",
		3 => "建国記念の日",
		4 => "昭和天皇の大喪の礼",
		5 => "春分の日",
		6 => "昭和の日",
		7 => "みどりの日",
		8 => "天皇誕生日",
		9 => "皇太子明仁親王の結婚の儀",
		10 => "憲法記念日",
		11 => "国民の休日",
		12 => "こどもの日",
		13 => "振替休日",
		14 => "皇太子徳仁親王の結婚の儀",
		15 => "海の日",
		16 => "秋分の日",
		17 => "敬老の日",
		18 => "体育の日",
		19 => "文化の日",
		20 => "勤労感謝の日",
		21 => "即位礼正殿の儀",
		22 => "山の日",
		23 => "天皇即位の日",
	);
	private $_weekday_name = array("日", "月", "火", "水", "木", "金", "土");
	private $_month_name = array("", "睦月", "如月", "弥生", "卯月", "皐月", "水無月", "文月", "葉月", "長月", "神無月", "霜月", "師走");
	private $_six_weekday = array("大安", "赤口", "先勝", "友引", "先負", "仏滅");
	private $_oriental_zodiac = array("亥", "子", "丑", "寅", "卯", "辰", "巳", "午", "未", "申", "酉", "戌");
	private $_era_name = array("", "明治", "大正", "昭和", "平成", "令和");
	private $_era_calc = array(0, 1867, 1911, 1925, 1988, 2018);

	/**
	 * コンストラクタ
	 */
	public function __construct()
	{}

	/**
	 * 指定月の祝日リストを取得
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @param {bool} isRecursion 国民の休日を判定する再帰呼び出しの有無
	 * @return {array}
	 */
	public function getHolidayList(int $time_stamp, bool $isRecursion = true): array
	{
		$r = [];
		switch ($this->getMonth($time_stamp)) {
			case 1:
				$r = $this->getJanuaryHoliday($this->getYear($time_stamp), $isRecursion);
				break;
			case 2:
				$r =  $this->getFebruaryHoliday($this->getYear($time_stamp), $isRecursion);
				break;
			case 3:
				$r =  $this->getMarchHoliday($this->getYear($time_stamp), $isRecursion);
				break;
			case 4:
				$r =  $this->getAprilHoliday($this->getYear($time_stamp), $isRecursion);
				break;
			case 5:
				$r =  $this->getMayHoliday($this->getYear($time_stamp), $isRecursion);
				break;
			case 6:
				$r =  $this->getJuneHoliday($this->getYear($time_stamp), $isRecursion);
				break;
			case 7:
				$r =  $this->getJulyHoliday($this->getYear($time_stamp), $isRecursion);
				break;
			case 8:
				$r =  $this->getAugustHoliday($this->getYear($time_stamp), $isRecursion);
				break;
			case 9:
				$r =  $this->getSeptemberHoliday($this->getYear($time_stamp), $isRecursion);
				break;
			case 10:
				$r =  $this->getOctoberHoliday($this->getYear($time_stamp), $isRecursion);
				break;
			case 11:
				$r =  $this->getNovemberHoliday($this->getYear($time_stamp), $isRecursion);
				break;
			case 12:
				$r =  $this->getDecemberHoliday($this->getYear($time_stamp), $isRecursion);
				break;
		}
		return $r;
	}

	/**
	 * 国民の休日を取得
	 * 前日と翌日が祝日の場合に休日とする
	 *
	 * @param {int} $time_stamp  当該月のタイムスタンプ
	 * @param {array} $holiday_list  当該月の休日
	 * @return {array}
	 */
	public function getNationalHoliday(int $time_stamp, array $holiday_list = []): array
	{
		$one_day = 86400;
		$yesterday = 0;
		$res = [];

		/**
		 * ２進数で昨日と一昨日の祝日フラグを立てる
		 * 祝日:1, それ以外:0
		 * １の位：昨日
		 * 十の位：一昨日
		 */
		$holidays = 0;

		// 当該月1日の00:00のtimestamp
		$year  = (int)date("Y", $time_stamp);
		$month = (int)date("m", $time_stamp);
		$baseSec = mktime(0, 0, 0, $month, 1, $year);
		if (empty($holiday_list)) {
			$holiday_list = $this->getHolidayList($baseSec, false);
		}

		// 当該月末日の00:00のtimestamp
		$targetSec = mktime(0, 0, 0, ++$month, 0, $year);

		while ($baseSec <= $targetSec) {
			$day = $this->getDay($baseSec);
			if (isset($holiday_list[$day]) && $holiday_list[$day] != DJ_NO_HOLIDAY && $holiday_list[$day] != DJ_COMPENSATING_HOLIDAY) {
				$isHoliday = 1;
				if ($holidays == 2) {
					// 本日と一昨日が祝日で昨日が平日(２進数で0b10)
					$res[$yesterday] = DJ_NATIONAL_HOLIDAY;
				}
			} else {
				$isHoliday = 0;
			}

			// フラグをシフト
			$holidays = $holidays << 1;
			$holidays += $isHoliday;
			$holidays = $holidays & 3;

			$yesterday = $day;
			$baseSec += $one_day;
		}

		// 翌月1日の祝日判定
		$holiday_hash = $this->getHolidayList($baseSec, false);
		if (isset($holiday_hash[1]) && $holiday_hash[1] != DJ_NO_HOLIDAY && $holiday_hash[1] != DJ_COMPENSATING_HOLIDAY) {
			if ($holidays == 2) {
				// 本日と一昨日が祝日で昨日が平日(２進数で0b10)
				$res[$yesterday] = DJ_NATIONAL_HOLIDAY;
			}
		}

		$res += $holiday_list;

		return $res;
	}

	/**
	 * 元号キーを返す
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @return {int}
	 */
	public function getEraName(int $time_stamp): int
	{
		if (mktime(0, 0, 0, 1, 25, 1868) > $time_stamp) {
			return 0;	// 一世一元の制より前
		} else if (mktime(0, 0, 0, 7, 30, 1912) > $time_stamp) {
			return 1;	// 明治
		} else if (mktime(0, 0, 0, 12, 25, 1926) > $time_stamp) {
			return 2;	// 大正
		} else if (mktime(0, 0, 0, 1, 8, 1989) > $time_stamp) {
			return 3;	// 昭和
		} else if (mktime(0, 0, 0, 5 ,1, 2019) > $time_stamp) {
			return 4;	// 平成
		} else {
			return 5;	// 令和
		}
	}

	/**
	 * 元号を返す
	 *
	 * @param {int} key 元号キー
	 * @return {string}
	 */
	public function viewEraName(int $key): string
	{
		return $this->_era_name[$key];
	}

	/**
	 * 和暦を返す
	 * 元号対応以前（明治より前）は西暦を返す
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @return {int}
	 */
	public function getEraYear(int $time_stamp): int
	{
		$key = $this->getEraName($time_stamp);

		return $this->getYear($time_stamp)-$this->_era_calc[$key];
	}

	/**
	 * 西暦を数値化して返す
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @return {int}
	 */
	public function getYear(int $time_stamp): int
	{
		return (int)date("Y", $time_stamp);
	}

	/**
	 * 月を数値化して返す
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @return {int}
	 */
	public function getMonth(int $time_stamp): int
	{
		return (int)date("n", $time_stamp);
	}

	/**
	 * 日を数値化して返す
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @return {int}
	 */
	public function getDay(int $time_stamp): int
	{
		return (int)date("j", $time_stamp);
	}

	/**
	 * 休日名を返す
	 *
	 * @param {int} key 休日キー
	 * @return {string}
	 */
	public function viewHoliday(int $key): string
	{
		return $this->_holiday_name[$key];
	}

	/**
	 * 七曜を数値化して返す
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @return {int} 0:日, 1:月, 2:火, 3:水, 4:木, 5:金, 6:土
	 */
	public function getWeekday(int $time_stamp): int
	{
		return (int)date("w", $time_stamp);
	}

	/**
	 * 曜日名を返す
	 *
	 * @param {int} key 曜日キー
	 * @return {string}
	 */
	public function viewWeekday(int $key): string
	{
		return $this->_weekday_name[$key];
	}

	/**
	 * 旧暦月名を返す
	 *
	 * @param {int} key 月キー
	 * @return {string}
	 */
	public function viewMonth(int $key): string
	{
		return $this->_month_name[$key];
	}

	/**
	 * 六曜名を返す
	 *
	 * @param {int} key 六曜キー
	 * @return {string}
	 */
	public function viewSixWeekday(int $key): string
	{
		return array_key_exists($key, $this->_six_weekday) ? $this->_six_weekday[$key] : "";
	}

	/**
	 * 干支キーを返す
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @return {int}
	 */
	public function getOrientalZodiac(int $time_stamp): int
	{
		$res = ($this->getYear($time_stamp)+9)%12;
		return $res;
	}

	/**
	 * 干支を返す
	 *
	 * @param {int} key 干支キー
	 * @return {string}
	 */
	public function viewOrientalZodiac(int $key): string
	{
		return $this->_oriental_zodiac[$key];
	}

	/**
	 * 春分の日を取得
	 * 1851〜2150 の間の予測
	 *
	 * @param {int} year 西暦
	 * @return {int} タイムスタンプ
	 */
	public function getVrenalEquinoxDay(int $year): int
	{
		if ($year < 1851) {
			return 0;
		} else if ($year <= 1899) {
			$day = floor(19.8277 + (0.242194 * ($year - 1980)) - floor(($year - 1980) / 4));
		} else if ($year <= 1979) {
			$day = floor(20.8357 + (0.242194 * ($year - 1980)) - floor(($year - 1980) / 4));
		} else if ($year <= 2099) {
			$day = floor(20.8431 + (0.242194 * ($year - 1980)) - floor(($year - 1980) / 4));
		} else if ($year <= 2150) {
			$day = floor(21.851 + (0.242194 * ($year - 1980)) - floor(($year - 1980) / 4));
		} else {
			return 0;
		}
		return mktime(0, 0, 0, DJ_VERNAL_EQUINOX_DAY_MONTH, (int)$day, $year);
	}

	/**
	 * 秋分の日を取得
	 * 1851〜2150 の間の予測
	 *
	 * @param {int} year 西暦
	 * @return {int} タイムスタンプ
	 */
	public function getAutumnEquinoxDay(int $year): int
	{
		if ($year < 1851) {
			return 0;
		} else if ($year <= 1899) {
			$day = floor(22.2588 + (0.242194 * ($year - 1980)) - floor(($year - 1980) / 4));
		} else if ($year <= 1979) {
			$day = floor(23.2588 + (0.242194 * ($year - 1980)) - floor(($year - 1980) / 4));
		} else if ($year <= 2099) {
			$day = floor(23.2488 + (0.242194 * ($year - 1980)) - floor(($year - 1980) / 4));
		} else if ($year <= 2150) {
			$day = floor(24.2488 + (0.242194 * ($year - 1980)) - floor(($year - 1980) / 4));
		} else {
			return 0;
		}
		return mktime(0, 0, 0, DJ_AUTUMNAL_EQUINOX_DAY_MONTH, (int)$day, $year);
	}

	/**
	 * タイムスタンプを展開して、日付の詳細配列を取得
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @return {array}
	 */
	public function makeDateArray(int $time_stamp): array
	{
		$holiday = $this->getHolidayList($time_stamp);
		$day = $this->getDay($time_stamp);
		$week = $this->getWeekday($time_stamp);
		$res = array(
			"Year"    => $this->getYear($time_stamp),
			"Month"   => $this->getMonth($time_stamp),
			"Day"     => $day,
			"Weekday" => $week,
			"Weekname"=> $this->viewWeekday($week),
			"Holiday" => isset($holiday[$day]) ? $holiday[$day] : DJ_NO_HOLIDAY,
		);

		return $res;
	}

	/**
	 * タイムスタンプを展開して、日付情報を返す
	 *
	 * @access private
	 * @param {int} time_stamp タイムスタンプ
	 * @return {array}
	 */
	private function parseTime(int $time_stamp): array
	{
		$holiday = $this->getHolidayList($time_stamp);
		$day = $this->getDay($time_stamp);
		$res = array(
			"time_stamp" => $time_stamp,
			"year"       => $this->getYear($time_stamp),
			"month"      => date("m", $time_stamp),
			"strday"     => date("d", $time_stamp),
			"day"        => $day,
			"week"       => $this->getWeekday($time_stamp),
			"holiday"    => isset($holiday[$day]) ? $holiday[$day] : DJ_NO_HOLIDAY,
		);
		return $res;
	}

	/**
	 * 祝日判定
	 *
	 * @param {int} year 年
	 * @param {int} month 月
	 * @param {int} day 日
	 * @return {bool} Returns TRUE if it is a holiday, FALSE otherwise.
	 */
	public function isHoliday(int $year, int $month, int $day): bool
	{
		$res = $this->parseTime(mktime(0, 0, 0, $month, $day, $year));
		return $res['holiday'] !== DJ_NO_HOLIDAY;
	}

	/**
	 * １月の祝日判定
	 *
	 * @access private
	 * @param {int} year 西暦
	 * @param {bool} isRecursion 国民の休日を判定する再帰呼び出しの有無
	 * @return {array}
	 */
	private function getJanuaryHoliday(int $year, bool $isRecursion): array
	{
		$res[1] = DJ_NEW_YEAR_S_DAY;
		//振替休日確認
		if ($this->getWeekDay(mktime(0, 0, 0, 1, 1, $year)) == DJ_SUNDAY) {
			$res[2] = DJ_COMPENSATING_HOLIDAY;
		}
		if ($year >= 2000) {
			//2000年以降は第二月曜日に変更
			$second_monday = $this->getDayByWeekly($year, 1, DJ_MONDAY, 2);
			$res[$second_monday] = DJ_COMING_OF_AGE_DAY;

		} else {
			$res[15] = DJ_COMING_OF_AGE_DAY;
			//振替休日確認
			if ($this->getWeekDay(mktime(0, 0, 0, 1, 15, $year)) == DJ_SUNDAY) {
				$res[16] = DJ_COMPENSATING_HOLIDAY;
			}
		}

		if ($isRecursion) {
			$res = $this->getNationalHoliday(mktime(0, 0, 0, 1, 1, $year), $res);
		}

		return $res;
	}

	/**
	 * ２月の祝日判定
	 *
	 * @access private
	 * @param {int} year 西暦
	 * @param {bool} isRecursion 国民の休日を判定する再帰呼び出しの有無
	 * @return {array}
	 */
	private function getFebruaryHoliday(int $year, bool $isRecursion): array
	{
		$res[11] = DJ_NATIONAL_FOUNDATION_DAY;
		//振替休日確認
		if ($this->getWeekDay(mktime(0, 0, 0, 2, 11, $year)) == DJ_SUNDAY) {
			$res[12] = DJ_COMPENSATING_HOLIDAY;
		}
		if ($year == 1989) {
			$res[24] = DJ_THE_SHOWA_EMPEROR_DIED;
		}
		if ($year >= 2020) {
			$res[23] = DJ_THE_EMPEROR_S_BIRTHDAY;
			//振替休日確認
			if ($this->getWeekDay(mktime(0, 0, 0, 2, 23, $year)) == DJ_SUNDAY) {
				$res[24] = DJ_COMPENSATING_HOLIDAY;
			}
		}

		if ($isRecursion) {
			$res = $this->getNationalHoliday(mktime(0, 0, 0, 2, 1, $year), $res);
		}

		return $res;
	}

	/**
	 * ３月の祝日判定
	 *
	 * @param {int} year 西暦
	 * @param {bool} isRecursion 国民の休日を判定する再帰呼び出しの有無
	 * @return {array}
	 */
	private function getMarchHoliday(int $year, bool $isRecursion): array
	{
		$VrenalEquinoxDay = $this->getVrenalEquinoxDay($year);
		if ($VrenalEquinoxDay==0) return array();

		$res[$this->getDay($VrenalEquinoxDay)] = DJ_VERNAL_EQUINOX_DAY;
		//振替休日確認
		if ($this->getWeekDay($VrenalEquinoxDay) == DJ_SUNDAY) {
			$res[$this->getDay($VrenalEquinoxDay)+1] = DJ_COMPENSATING_HOLIDAY;
		}

		if ($isRecursion) {
			$res = $this->getNationalHoliday(mktime(0, 0, 0, 3, 1, $year), $res);
		}

		return $res;
	}

	/**
	 * ４月の祝日判定
	 *
	 * @access private
	 * @param {int} year 西暦
	 * @param {bool} isRecursion 国民の休日を判定する再帰呼び出しの有無
	 * @return {array}
	 */
	private function getAprilHoliday(int $year, bool $isRecursion): array
	{
		$res = array();
		if ($year == 1959) {
			$res[10] = DJ_CROWN_PRINCE_HIROHITO_WEDDING;
		}
		if ($year >= 2007) {
			$res[29] = DJ_DAY_OF_SHOWA;
		} else if ($year >= 1989) {
			$res[29] = DJ_GREENERY_DAY;
		} else {
			$res[29] = DJ_THE_EMPEROR_S_BIRTHDAY;
		}
		//振替休日確認
		if ($this->getWeekDay(mktime(0, 0, 0, 4, 29, $year)) == DJ_SUNDAY) {
			$res[30] = DJ_COMPENSATING_HOLIDAY;
		}

		if ($isRecursion) {
			$res = $this->getNationalHoliday(mktime(0, 0, 0, 4, 1, $year), $res);
		}

		return $res;
	}

	/**
	 * ５月の祝日判定
	 *
	 * @access private
	 * @param {int} year 西暦
	 * @param {bool} isRecursion 国民の休日を判定する再帰呼び出しの有無
	 * @return {array}
	 */
	private function getMayHoliday(int $year, bool $isRecursion): array
	{
		$res[3] = DJ_CONSTITUTION_DAY;
		if ($year >= 2007) {
			$res[4] = DJ_GREENERY_DAY;
		} else if ($year >= 1986) {
			// 5/4が日曜日の場合はそのまま､月曜日の場合はは『憲法記念日の振替休日』(2006年迄)
			if ($this->getWeekday(mktime(0, 0, 0, 5, 4, $year)) > DJ_MONDAY) {
				$res[4] = DJ_NATIONAL_HOLIDAY;
			} else if ($this->getWeekday(mktime(0, 0, 0, 5, 4, $year)) == DJ_MONDAY)  {
				$res[4] = DJ_COMPENSATING_HOLIDAY;
			}
		}
		$res[5] = DJ_CHILDREN_S_DAY;
		if ($this->getWeekDay(mktime(0, 0, 0, 5, 5, $year)) == DJ_SUNDAY) {
			$res[6] = DJ_COMPENSATING_HOLIDAY;
		}
		if ($year >= 2007) {
			// [5/3,5/4が日曜]なら、振替休日
			if (($this->getWeekday(mktime(0, 0, 0, 5, 4, $year)) == DJ_SUNDAY) || ($this->getWeekday(mktime(0, 0, 0, 5, 3, $year)) == DJ_SUNDAY)) {
				$res[6] = DJ_COMPENSATING_HOLIDAY;
			}
		}
		if ($year == 2019) {
			// 天皇即位
			$res[1] = DJ_EMPEROR_ENTHRONEMENT_DAY;
		}

		if ($isRecursion) {
			$res = $this->getNationalHoliday(mktime(0, 0, 0, 5, 1, $year), $res);
		}

		return $res;
	}

	/**
	 * ６月の祝日判定
	 *
	 * @access private
	 * @param {int} year 西暦
	 * @param {bool} isRecursion 国民の休日を判定する再帰呼び出しの有無
	 * @return {array}
	 */
	private function getJuneHoliday(int $year, bool $isRecursion): array
	{
		$res = array();
		if ($year == "1993") {
			$res[9] = DJ_CROWN_PRINCE_NARUHITO_WEDDING;
		}

		if ($isRecursion) {
			$res = $this->getNationalHoliday(mktime(0, 0, 0, 6, 1, $year), $res);
		}

		return $res;
	}

	/**
	 * ７月の祝日判定
	 *
	 * @param {int} year 西暦
	 * @param {bool} isRecursion 国民の休日を判定する再帰呼び出しの有無
	 * @return {array}
	 */
	private function getJulyHoliday(int $year, bool $isRecursion): array
	{
		$res = array();
		if ($year >= 2003) {
			$third_monday = $this->getDayByWeekly($year, 7, DJ_MONDAY, 3);
			$res[$third_monday] = DJ_MARINE_DAY;
		} else if ($year >= 1996) {
			$res[20] = DJ_MARINE_DAY;
			//振替休日確認
			if ($this->getWeekDay(mktime(0, 0, 0, 7, 20, $year)) == DJ_SUNDAY) {
				$res[21] = DJ_COMPENSATING_HOLIDAY;
			}
		}

		if ($isRecursion) {
			$res = $this->getNationalHoliday(mktime(0, 0, 0, 7, 1, $year), $res);
		}

		return $res;
	}

	/**
	 * ８月の祝日判定
	 *
	 * @access private
	 * @param {int} year 西暦
	 * @param {bool} isRecursion 国民の休日を判定する再帰呼び出しの有無
	 * @return {array}
	 */
	private function getAugustHoliday(int $year, bool $isRecursion): array
	{
		$res = array();
		if ($year >= 2016) {
			$res[11] = DJ_MOUNTAIN_DAY;
			//振替休日確認
			if ($this->getWeekDay(mktime(0, 0, 0, 8, 11, $year)) == DJ_SUNDAY) {
				$res[12] = DJ_COMPENSATING_HOLIDAY;
			}
		}

		if ($isRecursion) {
			$res = $this->getNationalHoliday(mktime(0, 0, 0, 8, 1, $year), $res);
		}

		return $res;
	}

	/**
	 * ９月の祝日判定
	 *
	 * @access private
	 * @param {int} year 西暦
	 * @param {bool} isRecursion 国民の休日を判定する再帰呼び出しの有無
	 * @return {array}
	 */
	private function getSeptemberHoliday(int $year, bool $isRecursion): array
	{
		$autumnEquinoxDay = $this->getAutumnEquinoxDay($year);
		if ($autumnEquinoxDay==0) return array();

		$res[$this->getDay($autumnEquinoxDay)] = DJ_AUTUMNAL_EQUINOX_DAY;
		//振替休日確認
		if ($this->getWeekDay($autumnEquinoxDay) == 0) {
			$res[$this->getDay($autumnEquinoxDay)+1] = DJ_COMPENSATING_HOLIDAY;
		}

		if ($year >= 2003) {
			$third_monday = $this->getDayByWeekly($year, 9, DJ_MONDAY, 3);
			$res[$third_monday] = DJ_RESPECT_FOR_SENIOR_CITIZENS_DAY;

			//敬老の日と、秋分の日の間の日は休みになる
			if (($this->getDay($autumnEquinoxDay) - 1) == ($third_monday + 1)) {
				$res[($this->getDay($autumnEquinoxDay) - 1)] = DJ_NATIONAL_HOLIDAY;
			}

		} else if ($year >= 1966) {
			$res[15] = DJ_RESPECT_FOR_SENIOR_CITIZENS_DAY;
			//振替休日確認
			if ($this->getWeekDay(mktime(0, 0, 0, 9, 15, $year)) == DJ_SUNDAY) {
				$res[16] = DJ_COMPENSATING_HOLIDAY;
			}
		}

		if ($isRecursion) {
			$res = $this->getNationalHoliday(mktime(0, 0, 0, 9, 1, $year), $res);
		}

		return $res;
	}

	/**
	 * １０月の祝日判定
	 *
	 * @access private
	 * @param {int} year 西暦
	 * @param {bool} isRecursion 国民の休日を判定する再帰呼び出しの有無
	 * @return {array}
	 */
	private function getOctoberHoliday(int $year, bool $isRecursion): array
	{
		$res = array();
		if ($year >= 2000) {
			//2000年以降は第二月曜日に変更
			$second_monday = $this->getDayByWeekly($year, 10, DJ_MONDAY, 2);
			$res[$second_monday] = DJ_SPORTS_DAY;
		} else if ($year >= 1966) {
			$res[10] = DJ_SPORTS_DAY;
			//振替休日確認
			if ($this->getWeekDay(mktime(0, 0, 0, 10, 10, $year)) == DJ_SUNDAY) {
				$res[11] = DJ_COMPENSATING_HOLIDAY;
			}
		}

		if ($year == 2019) {
			// 即位礼正殿の儀
			$res[22] = DJ_REGNAL_DAY;
		}

		if ($isRecursion) {
			$res = $this->getNationalHoliday(mktime(0, 0, 0, 10, 1, $year), $res);
		}

		return $res;
	}

	/**
	 * １１月の祝日判定
	 *
	 * @param {int} year 西暦
	 * @param {bool} isRecursion 国民の休日を判定する再帰呼び出しの有無
	 * @return {array}
	 */
	private function getNovemberHoliday(int $year, bool $isRecursion): array
	{
		$res[3] = DJ_CULTURE_DAY;
		//振替休日確認
		if ($this->getWeekDay(mktime(0, 0, 0, 11, 3, $year)) == DJ_SUNDAY) {
			$res[4] = DJ_COMPENSATING_HOLIDAY;
		}

		if ($year == 1990) {
			$res[12] = DJ_REGNAL_DAY;
		}

		$res[23] = DJ_LABOR_THANKSGIVING_DAY;
		//振替休日確認
		if ($this->getWeekDay(mktime(0, 0, 0, 11, 23, $year)) == DJ_SUNDAY) {
			$res[24] = DJ_COMPENSATING_HOLIDAY;
		}

		if ($isRecursion) {
			$res = $this->getNationalHoliday(mktime(0, 0, 0, 11, 1, $year), $res);
		}

		return $res;
	}

	/**
	 * １２月の祝日判定
	 *
	 * @access private
	 * @param {int} year 西暦
	 * @param {bool} isRecursion 国民の休日を判定する再帰呼び出しの有無
	 * @return {array}
	 */
	private function getDecemberHoliday(int $year, bool $isRecursion): array
	{
		$res = array();
		if ($year >= 1989 && $year < 2019) {
			$res[23] = DJ_THE_EMPEROR_S_BIRTHDAY;
		}
		if ($this->getWeekDay(mktime(0, 0, 0, 12, 23, $year)) == DJ_SUNDAY) {
			$res[24] = DJ_COMPENSATING_HOLIDAY;
		}

		if ($isRecursion) {
			$res = $this->getNationalHoliday(mktime(0, 0, 0, 12, 1, $year), $res);
		}

		return $res;
	}

	/**
	 * 指定月の第n X曜日（e.g. 第３月曜日）の日付を取得
	 *
	 * @param {int} year 年
	 * @param {int} month 月
	 * @param {int} weekly 曜日
	 * @param {int} renb 第×か
	 * @return {int}
	 */
	public function getDayByWeekly(int $year, int $month, int $weekly, int $renb = 1): int
	{
		switch ($weekly) {
			case 0:
				$map = array(7,1,2,3,4,5,6,);
				break;
			case 1:
				$map = array(6,7,1,2,3,4,5,);
				break;
			case 2:
				$map = array(5,6,7,1,2,3,4,);
				break;
			case 3:
				$map = array(4,5,6,7,1,2,3,);
				break;
			case 4:
				$map = array(3,4,5,6,7,1,2,);
				break;
			case 5:
				$map = array(2,3,4,5,6,7,1,);
				break;
			case 6:
				$map = array(1,2,3,4,5,6,7,);
				break;
		}

		$renb = 7*$renb+1;
		return $renb - $map[$this->getWeekday(mktime(0,0,0,$month,1,$year))];
	}

	/**
	 * 指定月のカレンダー配列を取得
	 *
	 * @param {int} year 年
	 * @param {int} month 月
	 * @return {array}
	 */
	public function getCalendar(int $year, int $month): array
	{
		$lim = (int)date("t", mktime(0, 0, 0, $month, 1, $year));
		return $this->getSpanCalendar($year, $month, 1, $lim);
	}

	/**
	 * 指定範囲のカレンダー配列を取得す
	 *
	 * @param {int} year 年
	 * @param {int} month 月
	 * @param {int} str 開始日
	 * @param {int} lim 期間(日)
	 * @return {array}
	 */
	public function getSpanCalendar(int $year, int $month, int $str, int $lim): array
	{
		$res = array();
		if ($lim <= 0) {
			return $res;
		}

		$time_stamp = mktime(0, 0, 0, $month, $str-1, $year);

		while ($lim != 0) {
			$time_stamp = mktime(0, 0, 0, (int)date("n", $time_stamp), (int)date("j", $time_stamp) + 1, (int)date("Y", $time_stamp));
			$res[] = $this->parseTime($time_stamp);
			$lim--;
		}
		return $res;
	}

	/**
	 * 営業日を取得
	 *
	 * @param {int} time_stamp 取得開始日
	 * @param {int} lim_day 取得日数（マイナスも可）
	 * @param {bool} is_closed_holiday 祝日を休業日とする場合はtrue、休まない場合はfalse(optional)
	 * @param {array} closed_week 休業する曜日定数の配列 (optional)
	 * @param {array} closed_date 休業する日付（Y-m-d）若しくはタイムスタンプの配列 (optional)
	 * @return {array} 営業日の配列
	 */
	public function getWorkingDay(int $time_stamp, int $lim_day, bool $is_closed_holiday = true, array $closed_week = array(), array $closed_date = array() ): array
	{
		if (!empty($closed_week)) {
			$closed_week = array_flip($closed_week);
		}
		if (!empty($closed_date)) {
			$gc = array();
			foreach ($closed_date as $value) {
				if (preg_match("/^[1-9][0-9]*$/", $value)!==1) {
					$value = strtotime($value);
				}
				$gc[mktime(0, 0, 0, (int)date("n", $value), (int)date("j", $value), (int)date("Y", $value))] = 1;
			}
			$closed_date = $gc;
		}

		$res = array();
		$adjust = $lim_day>0? 1: -1;
		$i = 0;
		$job = 0;
		$year  = (int)date("Y", $time_stamp);
		$month = (int)date("n", $time_stamp);
		$day   = (int)date("j", $time_stamp);
		while ($job != $lim_day) {
			$time_stamp = mktime(0, 0, 0, $month, $day + $i, $year);
			$gc = $this->parseTime($time_stamp);
			if (
				(array_key_exists($gc["week"], $closed_week) == false) && 
				(array_key_exists($gc["time_stamp"], $closed_date) == false) && 
				($is_closed_holiday ? $gc["holiday"] == DJ_NO_HOLIDAY : true)
			) {
				$res[] = $gc;
				$job += $adjust;
			}
			$i += $adjust;
		}
		return $res;
	}
}
?>