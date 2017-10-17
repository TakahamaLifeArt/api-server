<?php
/**
 * API for the Component
 * charset "UTF-8"
 * @author (c) 2014 ks.desk@gmail.com
 */
declare(strict_types=1);
require_once dirname(__FILE__).'/api_conf.php';
//define('_END_POINT', 'http://takahamalifeart.com/v1/api');
//require_once dirname(__FILE__).'/Http.php';
//require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/weblib/JSON.php';
//require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/weblib/User.php';
//require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/weblib/Master.php';
//require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/weblib/fb/facebook.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/api/package/calendar/MyCalendar.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/api/package/db/DbTable.php';
use package\calendar\MyCalendar;
use package\db\DbTable;
class Composer extends DbTable {
	
	public function __construct() {}
	
	public function __destruct() {
		parent::__destruct();
	}
	
	/**
	 * カレンダーのタグを返す
	 * @param {int}		y 西暦
	 * @param {int}		m 月
	 * @param {array}	me ユーザー情報
	 * @return {array} [year:西暦、month:月、today:今日の日付、schedule:カレンダー要素のタグ]
	 */
	public static function showCalendar(int $year=0, int $month=0, array $me=null): array {
		if ($year == FALSE || $month == FALSE) {
			$timestamp = time();
		} else {
			$timestamp = mktime(0, 0, 0, $month, 1, $year);
		}
		$year = date('Y', $timestamp);
		$month = date('n', $timestamp);
		$param = array(
			"user_id"=>"0",
			"locale"=>"ja",
			"pick"=>0,
//			"workday"=>4,
//			"deliveryday"=>1,
			'onsundays'=>1,
			'onmondays'=>0,
			'ontuesdays'=>0,
			'onwednesdays'=>0,
			'onthursdays'=>0,
			'onfridays'=>0,
			'onsaturdays'=>1,
			'onholidays'=>1,
			'holiday'=>array(),
//			'holidaysetting'=>0,
		);
		
		/*
		if (!is_null($me)) {
			$_REQUEST['me']['holiday'] = $json->decode($_REQUEST['me']['holiday']);
			$me = $_REQUEST['me'];
		} else {
			if (empty($_SESSION['me'])) {
				$me = $param;
				$_SESSION['me'] = $me;
			} else {
				$u = new User();
				$me = $u->setSession();
			}
		}
		if (empty($me)) {
			$me = $param;
		}
		*/
		
		if (empty($me)) {
			$param['holiday'] = self::getHoliday();
			$me = $param;
		}
		$mode = 0;
		$today = explode('-', date('Y-n-j'));
		$calendar = new MyCalendar((int)$year, (int)$month, $me['locale']);
		return array(
			'year'=>$year, 'month'=>$month, 'today'=>array('y'=>$today[0], 'm'=>$today[1], 'd'=>$today[2]), 
			'schedule'=>$calendar->render($me, $mode),
			'me'=>$me
		);
	}
	
	
	/**
	 * 休業期間の日付情報を取得
	 * @return {array} {yyyy-mm-dd=>0|1, ...} 0:営業  1:休み
	 */
	private static function getHoliday(): array {
		try {
			$fp = fopen($_SERVER['DOCUMENT_ROOT'].'/const/config_holiday.php', 'r+b');
			if ($fp===false) throw new Exception();
			
			// フィルタの登録
			stream_filter_register('crlf', 'crlf_filter');
			
			// 出力ファイルへフィルタをアタァッチ
			stream_filter_append($fp, 'crlf');
			while (!feof($fp)) {
				$contents .= fread($fp, 8192);
			}
			fclose($fp);
			$info = json_decode($contents, true);
			if (empty($info['start']) || empty($info['end'])) throw new Exception();
			
			// 期間内の日付を取得
			$one_day = 86400;
			$dt = new DateTime();
			$_from_holiday = strtotime($info['start']);		// お休み開始日
			$_to_holiday	= strtotime($info['end']);		// お休み最終日
			$baseSec = $_from_holiday;
			while ($baseSec <= $_to_holiday) {
				$currentDate = $dt->setTimestamp($baseSec)->format('Y-m-d');
				$res[$currentDate] = 1;
				$baseSec += $one_day;
			}
		} catch (Exception $e) {
			$res = array();
		}
		return $res;
	}
	
	
	/**
	 * 商品カテゴリー情報を返す
	 * @param {int} id	カテゴリID
	 *					0の場合カテゴリ一覧
	 *					それ以外は指定カテゴリのアイテム情報
	 * @return {string}	JSON形式で配列データを返す
	 * 					失敗した場合はエラーメッセージ
	 */
	public function getCategory(int $id=0): string {
		try{
			if (empty($id)) {
				parent::setField('category');
				$rec = parent::getRecord();
				if(empty($rec)) throw new Exception("No such category data exists");
				$r = json_encode($rec);
			} else {
				parent::setField('item');
				$whereIs = array();
				$len = count($this->fieldName);
				for ($i=0; $i < $len; $i++) {
					//				if(array_key_exists($this->fieldName[$i], $args)){
					if ($this->fieldName[$i] == 'category_id') {
						$whereIs[$i] = array('=', $id);
					} 
				}
				$rec = parent::getRecord($whereIs);
				if(empty($rec)) throw new Exception("No such item data exists");
				$r = json_encode($rec);
			}
			
		}catch(Exception $e){
			$r = $e->getMessage();
			if ('' === $r) {
				$r = 'Error: getCategory';
			}
		}
		return $r;
	}
	
	/**
	 * 商品カテゴリー一覧を返す
	 * @return {string}	JSON形式のアイテムカテゴリ情報
	 * 					失敗した場合はエラーメッセージ
	 *
	public static function getCategory(): string {
		$data = array();
		$param = json_encode($data);
		$method = 'GET';
		$header = array(
			'Content-Type: application/json',
		);
		$http = new Http(_END_POINT.'?act=category&output=json');
		$result = $http->requestRest($method, $param, $header);
		return $result;
	}
	*/
	
	
	/**
	 * 商品カテゴリー一覧を返す
	 * @param {int}		id アイテムID、カテゴリIDの何れか
	 * @param {string}	mode {code 'id' | 'category'}
	 * @return {string}	JSON形式のアイテム情報
	 * 					失敗した場合はエラーメッセージ
	 */
	public static function getItem(int $id, string $mode): string {
		$data = array(
			'act' => 'categories',
			'output' => 'json',
			'mode' => $mode,
			'id' => $id,
		);
		$param = json_encode($data);
		$method = 'GET';
		$header = array(
			'Content-Type: application/json',
		);
		$http = new Http(_END_POINT);
		$result = $http->requestRest($method, $param, $header);
//		$r = json_decode($result);
//		$resp = is_string($result) && is_array($r) ? $r : $result;
		return $result;
	}
}