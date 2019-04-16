<?php
/**
 * 暦|祝日クラス
 * @package holiday
 * @author <ks.desk@gmail.com>
 *
 * Copyright © 2014 Kyoda Yasushi
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
define("JD_NO_HOLIDAY", 0);
define("JD_NEW_YEAR_S_DAY", 1);
define("JD_COMING_OF_AGE_DAY", 2);
define("JD_NATIONAL_FOUNDATION_DAY", 3);
define("JD_THE_SHOWA_EMPEROR_DIED", 4);
define("JD_VERNAL_EQUINOX_DAY", 5);
define("JD_DAY_OF_SHOWA", 6);
define("JD_GREENERY_DAY", 7);
define("JD_THE_EMPEROR_S_BIRTHDAY", 8);
define("JD_CROWN_PRINCE_HIROHITO_WEDDING", 9);
define("JD_CONSTITUTION_DAY", 10);
define("JD_NATIONAL_HOLIDAY", 11);
define("JD_CHILDREN_S_DAY", 12);
define("JD_COMPENSATING_HOLIDAY", 13);
define("JD_CROWN_PRINCE_NARUHITO_WEDDING", 14);
define("JD_MARINE_DAY", 15);
define("JD_AUTUMNAL_EQUINOX_DAY", 16);
define("JD_RESPECT_FOR_SENIOR_CITIZENS_DAY", 17);
define("JD_SPORTS_DAY", 18);
define("JD_CULTURE_DAY", 19);
define("JD_LABOR_THANKSGIVING_DAY", 20);
define("JD_REGNAL_DAY", 21);
define("JD_MOUNTAIN_DAY", 22);
define("JD_EMPEROR_ENTHRONEMENT_DAY", 23);
define("JD_NATIONAL_HOLIDAYS", 24);

/**
 * 特定月定数
 */
define("JD_VERNAL_EQUINOX_DAY_MONTH", 3);
define("JD_AUTUMNAL_EQUINOX_DAY_MONTH", 9);

/**
 * 曜日定数
 */
define("JD_SUNDAY",    0);
define("JD_MONDAY",    1);
define("JD_TUESDAY",   2);
define("JD_WEDNESDAY", 3);
define("JD_THURSDAY",  4);
define("JD_FRIDAY",    5);
define("JD_SATURDAY",  6);


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
		24 => "国民の祝日",
	);
	private $_weekday_name = array("日", "月", "火", "水", "木", "金", "土");
	private $_month_name = array("", "睦月", "如月", "弥生", "卯月", "皐月", "水無月", "文月", "葉月", "長月", "神無月", "霜月", "師走");
	private $_six_weekday = array("大安", "赤口", "先勝", "友引", "先負", "仏滅");
	private $_oriental_zodiac = array("亥", "子", "丑", "寅", "卯", "辰", "巳", "午", "未", "申", "酉", "戌");
	private $_era_name = array("昭和", "平成");
	private $_era_calc = array(1925, 1988);

	/**
	 * コンストラクタ
	 */
	public function __construct()
	{}

	/**
	 * 指定月の祝日リストを取得する
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @return {array}
	 */
	public function getHolidayList(int $time_stamp): array
	{
		switch ($this->getMonth($time_stamp)) {
			case 1:
			return $this->getJanuaryHoliday($this->getYear($time_stamp));
			case 2:
			return $this->getFebruaryHoliday($this->getYear($time_stamp));
			case 3:
			return $this->getMarchHoliday($this->getYear($time_stamp));
			case 4:
			return $this->getAprilHoliday($this->getYear($time_stamp));
			case 5:
			return $this->getMayHoliday($this->getYear($time_stamp));
			case 6:
			return $this->getJuneHoliday($this->getYear($time_stamp));
			case 7:
			return $this->getJulyHoliday($this->getYear($time_stamp));
			case 8:
			return $this->getAugustHoliday($this->getYear($time_stamp));
			case 9:
			return $this->getSeptemberHoliday($this->getYear($time_stamp));
			case 10:
			return $this->getOctoberHoliday($this->getYear($time_stamp));
			case 11:
			return $this->getNovemberHoliday($this->getYear($time_stamp));
			case 12:
			return $this->getDecemberHoliday($this->getYear($time_stamp));
		}
	}
	
	/**
	 * 国民の休日を返す
	 * 前日と翌日が祝日の場合に休日とする
	 *
	 * @param {int} $time_stamp 当該月のタイムスタンプ
	 * @return {array}
	 */
	public function getNationalHoliday(int $time_stamp): array {
		try {
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

			// 前月末日の00:00のtimestampを取得
			$year  = (int)date("Y", $time_stamp);
			$month = (int)date("m", $time_stamp);
			$baseSec = mktime(0, 0, 0, $month, 0, $year);

			// 翌月1日のtimestamp
			$month++;
			$targetSec = mktime(0, 0, 0, $month, 1, $year);
			
			while ($baseSec <= $targetSec) {
				$fin = $this->makeDateArray($baseSec);
				if ($fin['Holiday'] != 0 && $fin['Holiday'] != JD_COMPENSATING_HOLIDAY) {
					$isHoliday = 1;
					if ($holidays == 2) {
						// 本日が祝日で且つ一昨日が祝日で昨日が平日(２進数で0b10)
						$res[$yesterday] = JD_NATIONAL_HOLIDAYS;
					}
				} else {
					$isHoliday = 0;
				}
				
				$holidays = $holidays << 1;
				$holidays += $isHoliday;
				$holidays = $holidays & 3;
				
				$yesterday = $fin['Day'];
				$baseSec += $one_day;
			}
		} catch (Exception $e) {
			$res = [];
		}
		return $res;
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
	 * 年号キーを返す
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @return {int}
	 */
	public function getEraName(int $time_stamp): int
	{
		if (mktime(0, 0, 0, 1 , 7, 1989) >= $time_stamp) {
			//昭和
			return 0;
		} else {
			//平成
			return 1;
		}
	}

	/**
	 * 和暦を返す
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @param {int} key 和暦モード(空にすると、自動取得)
	 * @return {int}
	 */
	public function getEraYear(int $time_stamp, int $key = -1): int
	{
		if ($key == -1) {
			$key = $this->getEraName($time_stamp);
		}
		return $this->getYear($time_stamp)-$this->_era_calc[$key];
	}
	
	/**
	 * 日本語フォーマットされた休日名を返す
	 *
	 * @param {int} key 休日キー
	 * @return {string}
	 */
	public function viewHoliday(int $key): string
	{
		return $this->_holiday_name[$key];
	}
	
	/**
	 * 日本語フォーマットされた曜日名を返す
	 *
	 * @param {int} key 曜日キー
	 * @return {string}
	 */
	public function viewWeekday(int $key): string
	{
		return $this->_weekday_name[$key];
	}
	
	
	/**
	 * 日本語フォーマットされた旧暦月名を返す
	 *
	 * @param {int} key 月キー
	 * @return {string}
	 */
	public function viewMonth(int $key): string
	{
		return $this->_month_name[$key];
	}
	
	
	/**
	 * 日本語フォーマットされた六曜名を返す
	 *
	 * @param {int} key 六曜キー
	 * @return {string}
	 */
	public function viewSixWeekday(int $key): string
	{
		return array_key_exists($key, $this->_six_weekday) ? $this->_six_weekday[$key] : "";
	}
	
	/**
	 * 日本語フォーマットされた干支を返す
	 *
	 * @param {int} key 干支キー
	 * @return {string}
	 */
	public function viewOrientalZodiac(int $key): string
	{
		return $this->_oriental_zodiac[$key];
	}
	
	/**
	 * 日本語フォーマットされた年号を返す
	 *
	 * @param {int} key 年号キー
	 * @return {string}
	 */
	public function viewEraName(int $key): string
	{
		return $this->_era_name[$key];
	}
	
	/**
	 * 春分の日を取得
	 *
	 * @param {int} year 西暦
	 * @return {int} タイムスタンプ
	 */
	public function getVrenalEquinoxDay(int $year): int
	{
		if ($year <= 1979) {
			$day = floor(20.8357 + (0.242194 * ($year - 1980)) - floor(($year - 1980) / 4));
		} else if ($year <= 2099) {
			$day = floor(20.8431 + (0.242194 * ($year - 1980)) - floor(($year - 1980) / 4));
		} else if ($year <= 2150) {
			$day = floor(21.851 + (0.242194 * ($year - 1980)) - floor(($year - 1980) / 4));
		} else {
			return 0;
		}
		return mktime(0, 0, 0, JD_VERNAL_EQUINOX_DAY_MONTH, (int)$day, $year);
	}
	
	/**
	 * 秋分の日を取得
	 *
	 * @param {int} year 西暦
	 * @return {int} タイムスタンプ
	 */
	public function getAutumnEquinoxDay(int $year): int
	{
		if ($year <= 1979) {
			$day = floor(23.2588 + (0.242194 * ($year - 1980)) - floor(($year - 1980) / 4));
		} else if ($year <= 2099) {
			$day = floor(23.2488 + (0.242194 * ($year - 1980)) - floor(($year - 1980) / 4));
		} else if ($year <= 2150) {
			$day = floor(24.2488 + (0.242194 * ($year - 1980)) - floor(($year - 1980) / 4));
		} else {
			return 0;
		}
		return mktime(0, 0, 0, JD_AUTUMNAL_EQUINOX_DAY_MONTH, (int)$day, $year);
	}
	
	/**
	 * タイムスタンプを展開して、日付の詳細配列を取得する
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @return {array}
	 */
	public function makeDateArray(int $time_stamp): array
	{
		$res = array(
			"Year"    => $this->getYear($time_stamp),
			"Month"   => $this->getMonth($time_stamp),
			"Day"     => $this->getDay($time_stamp),
			"Weekday" => $this->getWeekday($time_stamp),
		);
		
		$holiday_list = $this->getHolidayList($time_stamp);
		$res["Holiday"] = isset($holiday_list[$res["Day"]]) ? $holiday_list[$res["Day"]] : JD_NO_HOLIDAY;
		return $res;
	}
	
	/**
	 * 七曜を数値化して返します
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @return {int} 0:日, 1:月, 2:火, 3:水, 4:木, 5:金, 6:土
	 */
	public function getWeekday(int $time_stamp): int
	{
		return (int)date("w", $time_stamp);
	}

	/**
	 * 年を数値化して返します
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @return {int}
	 */
	public function getYear(int $time_stamp): int
	{
		return (int)date("Y", $time_stamp);
	}

	/**
	 * 月を数値化して返します
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @return {int}
	 */
	public function getMonth(int $time_stamp): int
	{
		return (int)date("n", $time_stamp);
	}
	
	/**
	 * 日を数値化して返します
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @return {int}
	 */
	public function getDay(int $time_stamp): int
	{
		return (int)date("j", $time_stamp);
	}
	
	/**
	 * 祝日判定ロジック一月
	 *
	 * @param {int} year 西暦
	 * @return {array}
	 */
	public function getJanuaryHoliday(int $year): array
	{
		$res[1] = JD_NEW_YEAR_S_DAY;
		//振替休日確認
		if ($this->getWeekDay(mktime(0, 0, 0, 1, 1, $year)) == JD_SUNDAY) {
			$res[2] = JD_COMPENSATING_HOLIDAY;
		}
		if ($year >= 2000) {
			//2000年以降は第二月曜日に変更
			$second_monday = $this->getDayByWeekly($year, 1, JD_MONDAY, 2);
			$res[$second_monday] = JD_COMING_OF_AGE_DAY;
			
		} else {
			$res[15] = JD_COMING_OF_AGE_DAY;
			//振替休日確認
			if ($this->getWeekDay(mktime(0, 0, 0, 1, 15, $year)) == JD_SUNDAY) {
				$res[16] = JD_COMPENSATING_HOLIDAY;
			}
		}
		return $res;
	}
	
	/**
	 * 祝日判定ロジック二月
	 *
	 * @param {int} year 西暦
	 * @return {array}
	 */
	public function getFebruaryHoliday(int $year): array
	{
		$res[11] = JD_NATIONAL_FOUNDATION_DAY;
		//振替休日確認
		if ($this->getWeekDay(mktime(0, 0, 0, 2, 11, $year)) == JD_SUNDAY) {
			$res[12] = JD_COMPENSATING_HOLIDAY;
		}
		if ($year == 1989) {
			$res[24] = JD_THE_SHOWA_EMPEROR_DIED;
		}
		if ($year >= 2020) {
			$res[23] = JD_THE_EMPEROR_S_BIRTHDAY;
		}
		return $res;
	}
	
	/**
	 * 祝日判定ロジック三月
	 *
	 * @param {int} year 西暦
	 * @return {array}
	 */
	public function getMarchHoliday(int $year): array
	{
		$VrenalEquinoxDay = $this->getVrenalEquinoxDay($year);
		if ($VrenalEquinoxDay==0) return array();
		
		$res[$this->getDay($VrenalEquinoxDay)] = JD_VERNAL_EQUINOX_DAY;
		//振替休日確認
		if ($this->getWeekDay($VrenalEquinoxDay) == JD_SUNDAY) {
			$res[$this->getDay($VrenalEquinoxDay)+1] = JD_COMPENSATING_HOLIDAY;
		}
		return $res;
	}
	
	/**
	 * 祝日判定ロジック四月
	 *
	 * @param {int} year 西暦
	 * @return {array}
	 */
	public function getAprilHoliday(int $year): array
	{
		$res = array();
		if ($year == 1959) {
			$res[10] = JD_CROWN_PRINCE_HIROHITO_WEDDING;
		}
		if ($year >= 2007) {
			$res[29] = JD_DAY_OF_SHOWA;
		} else if ($year >= 1989) {
			$res[29] = JD_GREENERY_DAY;
		} else {
			$res[29] = JD_THE_EMPEROR_S_BIRTHDAY;
		}
		//振替休日確認
		if ($this->getWeekDay(mktime(0, 0, 0, 4, 29, $year)) == JD_SUNDAY) {
			$res[30] = JD_COMPENSATING_HOLIDAY;
		}
		return $res;
	}
	
	/**
	 * 祝日判定ロジック五月
	 *
	 * @param {int} year 西暦
	 * @return {array}
	 */
	public function getMayHoliday(int $year): array
	{
		$res[3] = JD_CONSTITUTION_DAY;
		if ($year >= 2007) {
			$res[4] = JD_GREENERY_DAY;
		} else if ($year >= 1986) {
			// 5/4が日曜日の場合はそのまま､月曜日の場合はは『憲法記念日の振替休日』(2006年迄)
			if ($this->getWeekday(mktime(0, 0, 0, 5, 4, $year)) > JD_MONDAY) {
				$res[4] = JD_NATIONAL_HOLIDAY;
			} elseif ($this->getWeekday(mktime(0, 0, 0, 5, 4, $year)) == JD_MONDAY)  {
				$res[4] = JD_COMPENSATING_HOLIDAY;
			}
		}
		$res[5] = JD_CHILDREN_S_DAY;
		if ($this->getWeekDay(mktime(0, 0, 0, 5, 5, $year)) == JD_SUNDAY) {
			$res[6] = JD_COMPENSATING_HOLIDAY;
		}
		if ($year >= 2007) {
			// [5/3,5/4が日曜]なら、振替休日
			if (($this->getWeekday(mktime(0, 0, 0, 5, 4, $year)) == JD_SUNDAY) || ($this->getWeekday(mktime(0, 0, 0, 5, 3, $year)) == JD_SUNDAY)) {
				$res[6] = JD_COMPENSATING_HOLIDAY;
			}
		}
		if ($year == 2019) {
			// 天皇即位
			$res[1] = JD_EMPEROR_ENTHRONEMENT_DAY;
		}
		return $res;
	}

	/**
	 * 祝日判定ロジック六月
	 *
	 * @param {int} year 西暦
	 * @return {array}
	 */
	public function getJuneHoliday(int $year): array
	{
		$res = array();
		if ($year == "1993") {
			$res[9] = JD_CROWN_PRINCE_NARUHITO_WEDDING;
		}
		return $res;
	}
	
	/**
	 * 祝日判定ロジック七月
	 *
	 * @param {int} year 西暦
	 * @return {array}
	 */
	public function getJulyHoliday(int $year): array
	{
		$res = array();
		if ($year >= 2003) {
			$third_monday = $this->getDayByWeekly($year, 7, JD_MONDAY, 3);
			$res[$third_monday] = JD_MARINE_DAY;
		} else if ($year >= 1996) {
			$res[20] = JD_MARINE_DAY;
			//振替休日確認
			if ($this->getWeekDay(mktime(0, 0, 0, 7, 20, $year)) == JD_SUNDAY) {
				$res[21] = JD_COMPENSATING_HOLIDAY;
			}
		}
		return $res;
	}
	
	/**
	 * 祝日判定ロジック八月
	 *
	 * @param {int} year 西暦
	 * @return {array}
	 */
	public function getAugustHoliday(int $year): array
	{
		$res = array();
		if ($year >= 2016) {
			$res[11] = JD_MOUNTAIN_DAY;
			//振替休日確認
			if ($this->getWeekDay(mktime(0, 0, 0, 8, 11, $year)) == JD_SUNDAY) {
				$res[12] = JD_COMPENSATING_HOLIDAY;
			}
		}
		return $res;
	}

	/**
	 * 祝日判定ロジック九月
	 *
	 * @param {int} year 西暦
	 * @return {array}
	 */
	public function getSeptemberHoliday(int $year): array
	{
		$autumnEquinoxDay = $this->getAutumnEquinoxDay($year);
		if ($autumnEquinoxDay==0) return array();
		
		$res[$this->getDay($autumnEquinoxDay)] = JD_AUTUMNAL_EQUINOX_DAY;
		//振替休日確認
		if ($this->getWeekDay($autumnEquinoxDay) == 0) {
			$res[$this->getDay($autumnEquinoxDay)+1] = JD_COMPENSATING_HOLIDAY;
		}
		
		if ($year >= 2003) {
			$third_monday = $this->getDayByWeekly($year, 9, JD_MONDAY, 3);
			$res[$third_monday] = JD_RESPECT_FOR_SENIOR_CITIZENS_DAY;
			
			//敬老の日と、秋分の日の間の日は休みになる
			if (($this->getDay($autumnEquinoxDay) - 1) == ($third_monday + 1)) {
				$res[($this->getDay($autumnEquinoxDay) - 1)] = JD_NATIONAL_HOLIDAY;
			}
			
		} else if ($year >= 1966) {
			$res[15] = JD_RESPECT_FOR_SENIOR_CITIZENS_DAY;
		}
		return $res;
	}
	
	/**
	 * 祝日判定ロジック十月
	 *
	 * @param {int} year 西暦
	 * @return {array}
	 */
	public function getOctoberHoliday(int $year): array
	{
		$res = array();
		if ($year >= 2000) {
			//2000年以降は第二月曜日に変更
			$second_monday = $this->getDayByWeekly($year, 10, JD_MONDAY, 2);
			$res[$second_monday] = JD_SPORTS_DAY;
		} else if ($year >= 1966) {
			$res[10] = JD_SPORTS_DAY;
			//振替休日確認
			if ($this->getWeekDay(mktime(0, 0, 0, 10, 10, $year)) == JD_SUNDAY) {
				$res[11] = JD_COMPENSATING_HOLIDAY;
			}
		}
		
		if ($year == 2019) {
			// 即位礼正殿の儀
			$res[22] = JD_REGNAL_DAY;
		}
		
		return $res;
	}
	
	/**
	 * 祝日判定ロジック十一月
	 *
	 * @param {int} year 西暦
	 * @return {array}
	 */
	public function getNovemberHoliday(int $year): array
	{
		$res[3] = JD_CULTURE_DAY;
		//振替休日確認
		if ($this->getWeekDay(mktime(0, 0, 0, 11, 3, $year)) == JD_SUNDAY) {
			$res[4] = JD_COMPENSATING_HOLIDAY;
		}
		
		if ($year == 1990) {
			$res[12] = JD_REGNAL_DAY;
		}
		
		$res[23] = JD_LABOR_THANKSGIVING_DAY;
		//振替休日確認
		if ($this->getWeekDay(mktime(0, 0, 0, 11, 23, $year)) == JD_SUNDAY) {
			$res[24] = JD_COMPENSATING_HOLIDAY;
		}
		return $res;
	}
	
	/**
	 * 祝日判定ロジック十二月
	 *
	 * @param {int} year 西暦
	 * @return {array}
	 */
	public function getDecemberHoliday(int $year): array
	{
		$res = array();
		if ($year >= 1989 && $year < 2019) {
			$res[23] = JD_THE_EMPEROR_S_BIRTHDAY;
		}
		if ($this->getWeekDay(mktime(0, 0, 0, 12, 23, $year)) == JD_SUNDAY) {
			$res[24] = JD_COMPENSATING_HOLIDAY;
		}
		return $res;
	}
	
	/**
	 * 第○ ■曜日の日付を取得します。
	 *
	 * @param {int} year 年
	 * @param {int} month 月
	 * @param {int} weekly 曜日
	 * @param {int} renb 何週目か
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
	 * 指定月のカレンダー配列を取得します
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
	 * 指定範囲のカレンダー配列を取得します
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
			$res[] = $this->purseTime($time_stamp);
			$lim--;
		}
		return $res;
	}
	
	/**
	 * タイムスタンプを展開して、日付情報を返します
	 *
	 * @param {int} time_stamp タイムスタンプ
	 * @return {array}
	 */
	public function purseTime(int $time_stamp): array
	{
		$holiday = $this->getHolidayList($time_stamp);

		$day = date("j", $time_stamp);
		$res = array(
			"time_stamp" => $time_stamp, 
			"day"        => $day, 
			"strday"     => date("d", $time_stamp), 
			"holiday"    => isset($holiday[$day]) ? $holiday[$day] : JD_NO_HOLIDAY, 
			"week"       => $this->getWeekday($time_stamp),
			"month"      => date("m", $time_stamp), 
			"year"       => date("Y", $time_stamp), 
		);
		return $res;
	}

	/**
	 * 営業日を取得します
	 *
	 * @param {int} time_stamp 取得開始日
	 * @param {int} lim_day 取得日数（マイナスも可）
	 * @param {bool} is_bypass_holiday 祝日を無視するかどうか (optional)
	 * @param {array} bypass_week_arr 無視する曜日 (optional)
	 * @param {array} is_bypass_date 無視する日 (optional)
	 * @return {array}
	 */
	public function getWorkingDay(int $time_stamp, int $lim_day, bool $is_bypass_holiday = true, array $bypass_week_arr = array(), array $is_bypass_date = array() ): array
	{
		if (!empty($bypass_week_arr)) {
			$bypass_week_arr   = array_flip($bypass_week_arr);
		}
		if (!empty($is_bypass_date)) {
			$gc = array();
			foreach ($is_bypass_date as $value) {
				if (preg_match("/^[1-9][0-9]*$/", $value)!==1) {
					$value = strtotime($value);
				}
				$gc[mktime(0, 0, 0, (int)date("n", $value), (int)date("j", $value), (int)date("Y", $value))] = 1;
			}
			$is_bypass_date = $gc;
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
			$gc = $this->purseTime($time_stamp);
			if (
				(array_key_exists($gc["week"], $bypass_week_arr) == false) && 
				(array_key_exists($gc["time_stamp"], $is_bypass_date) == false) && 
				($is_bypass_holiday ? $gc["holiday"] == JD_NO_HOLIDAY : true)
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