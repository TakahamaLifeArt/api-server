<?php
/**
 * 絵型クラス for API3
 * charset utf-8
 *--------------------
 * log
 * 2018-11-20 created
 *
 *--------------------
 *
 * update 更新
 * insert 新規登録
 * delete 削除
 * validDate 日付の妥当性を確認
 */
declare(strict_types=1);
namespace package\db;
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/config.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/SqlManager.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/Master.php';
use \Exception;
use package\db\SqlManager;
class Printposition implements Master {
	
	private $_sql;		// データベースサーバーへの接続を表すオブジェクト
	private $_curDate;	// 抽出条件に使用する日付(0000-00-00)。NULL(default)は今日
	
	
	/**
	 * param {string} db データベース名
	 * param {string} curDate 抽出条件に使用する日付(0000-00-00)。NULL(default)は今日
	 */
	public function __construct(string $db, string $curDate=''){
		$this->_curDate = $this->validDate($curDate);
		$this->_sql = new SqlManager($db);
	}
	
	
	public function __destruct(){
		$this->_sql->close();
	}
	
	
	/**
	 * 更新
	 * @param {array} args 更新データの可変長引数リスト
	 * @return {boolean} Returns FALSE on failure. For successful will return TRUE.
	 */
	public function update(...$args): bool
	{
		try {
			$res = true;
			if (empty($args)) throw new Exception();
			$sql = "update printposition set category_type=?, item_type=?, position_type=?, pos_pattern=?, frontface=?, backface=?, sideface=? where id=?";
			$ary = $this->_sql->prepared($sql, "sssiiiii", array($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7]));
			$res = empty($ary)? false: true;
		} catch (Exception $e) {
			$res = false;
		}
		return $res;
	}

	/**
	 * 新規登録
	 * @param {array} args 登録データの可変長引数リスト
	 * @return {boolean} Returns FALSE on failure. For successful will return TRUE.
	 */
	public function insert(...$args): bool
	{
		try {
			$res = true;
			if (empty($args)) throw new Exception();
			$sql = "insert into printposition (category_type, item_type, position_type, pos_pattern, frontface, backface, sideface) values (?, ?, ?, ?, ?, ?, ?)";
			for ($i=0, $len=count($args); $i<$len; $i++) {
				$this->_sql->prepared($sql, "sssiiii", array(
					$args[$i]['category_type'], $args[$i]['item_type'], $args[$i]['area_type'], $args[$i]['pos_pattern'], 
					$args[$i]['frontface_id'], $args[$i]['backface_id'], $args[$i]['sideface_id']));
			}
		} catch (Exception $e) {
			$res = false;
		}
		return $res;
	}

	/**
	 * 削除
	 * @param key 削除データの primary ID、または unique ID
	 * @return {boolean} Returns FALSE on failure. For successful will return TRUE.
	 */
	public function delete($key): bool
	{
		try {
			$res = true;
			if (empty($key)) throw new Exception();
			$sql = "delete from printposition where id=? limit 1";
			$this->_sql->prepared($sql, "i", array($key));
		} catch (Exception $e) {
			$res = false;
		}
		return $res;
	}
	
	
	/**
	* 日付の妥当性
	* @param {string} args 日付(0000-00-00)
	* @return {string} 日付(0000-00-00)。不正値の場合は今日の日付
	*/
	private function validDate(string $args): string {
		if (empty($args)) {
			$res = date('Y-m-d');
		} else {
			$res = str_replace("/", "-", $args);
			$d = explode('-', $res);
			if (checkdate((int)$d[1], (int)$d[2], (int)$d[0])===false) {
				$res = date('Y-m-d');
			}
		}
		return $res;
	}
}
?>