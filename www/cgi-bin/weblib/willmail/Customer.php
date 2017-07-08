<?php
/*
 * REST API 顧客レコード登録更新
 * @package willmail
 * @author (c) 2014 ks.desk@gmail.com
 */
ini_set('memory_limit', '1024M');
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/weblib/willmail/SqlStatement.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/weblib/Http.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/weblib/JSON.php';

abstract class Customer extends SqlStatement
{
	protected $primaryKey;	// レコードを一意に識別するフィールド名
	protected $json;		// JSONクラスのインスタンス（PHP 5 < 5.2.0）
	protected $upsertCount = array('update' => 0, 'insert' => 0);
	
	
	/**
	 * constructor
	 * @param {string} key レコード比較で使用する一意に識別できるフィールドコード
	 * @param {string}
	 */
	public function __construct($key = '')
	{
		parent::__construct();
		$this->primaryKey = $key;
		$this->json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
	}
	
	
	/**
	 * 顧客リストの登録更新処理
	 * @param {string} args 検索クエリで使用する最終更新日付YYY-MM-DD
	 * @param {string} method HTTPメソッド{@code GET|POST|PUT|DELETE}
	 * @return {array|string} 成功した場合{@code ['update':更新したレコード数, 'insert':登録したレコード数]}
	 * 						  失敗した場合はエラーメッセージ
	 */
	public function upsert($args, $method)
	{
		try {
			$args = $this->validDate($args, '');
			if ($args === '') {
				throw new Exception('更新日付が不正です。');
			}
			$data = $this->getRecord($args);
			if (is_string($data)) {
				throw new Exception($data);
			}
			if (!empty($data)) {
				switch ($method) {
					case 'POST': 
						$resp = $this->upsertRecord($data);
						break;
					case 'CSV':
						$resp = $this->upsertCSV($data);
						break;
				}
				
				if (TRUE !== $resp) {
					throw new Exception($resp);
				}
//				if ($this->upsertCount['update']!=count($data)) {
//					throw new Exception('未更新レコードがあります。');
//				}
			}
			$result = $this->upsertCount;
		} catch (Exception $e) {
			$result = $e->getMessage();
			if (empty($result)) {
				$result = 'Error: Customer::upsert';
			}
		}
		return $result;
	}
	
	
	/**
	 * 顧客データ抽出
	 * @param {string} args 検索クエリで使用する最終更新日付、YYY-MM-DDやISO8601などのフォーマットにも対応
	 * @return {array} 顧客レコード
	 * @throws 差分データ取得で失敗した場合はエラーメッセージ
	 */
	abstract protected function getRecord($args);


	/**
	 * 顧客リストの新規登録と修正更新
	 * @param {array} list データの配列
	 * @return {boolean|array} 成功した場合はTRUE
	 * 						   失敗した場合はエラーメッセージ
	 */
	abstract protected function upsertRecord($list);
	
	
	/**
	 * 顧客リストをCSVファイルでUpsert
	 * @param {array} list データの配列
	 * @return {boolean|array} 成功した場合はTRUE
	 * 						   失敗した場合はエラーメッセージ
	 */
	abstract protected function upsertCSV($list);
	
	
	/**
	 * 日付の妥当性を検証
	 * ISO8601などのフォーマットにも対応
	 * @param {string} args 日付文字列
	 * @param {string} defDate 日付が不正値の場合に返す日付文字列、省略可
	 * @return {string} YYYY-MM-DDにフォーマットした日付字列を返す
	 */
	protected function validDate($args, $defDate='2011-06-05')
	{
		if (empty($args)) {
			return $defDate;
		} else {
			$args = str_replace("/", "-", $args);
			$timestamp = strtotime($args);
			if ($timestamp) {
				return date('Y-m-d', $timestamp);
			} else {
				return $defDate;
			}
		}
	}
}

?>