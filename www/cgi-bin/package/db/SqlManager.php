<?php
/**
 * MySQLの接続とプリペアードステートメントの実行
 *
 */
declare(strict_types=1);
namespace package\db;
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/config.php';
use \mysqli;
use \Exception;
class SqlManager {
	private $_cn;
	
	public function __construct() {
		$this->dbConnect();
	}
	
	public function __destruct() {
		$this->_cn->close();
	}
	
	public function close() {
		$this->_cn->close();
	}
	
	/**
	 * SQLの接続
	 */
	protected function dbConnect() {
		$conn = new mysqli(_DB_HOST, _DB_USER, _DB_PASS, _DB_NAME);
		if (mysqli_connect_error()) {
			die('DB Connect Error: '.mysqli_connect_error());
		}
		$conn->set_charset('utf8');
		$this->_cn = $conn;
	}
	
	/**
	 * プリペアードステートメント
	 * @param conn {mysqli} データベースサーバーへの接続を表すオブジェクト
	 * @param {string} sql
	 * @param {string} marker
	 * @param {array} param
	 * @return {array} selectクエリの結果
	 */
	public function prepared(string $query, string $marker, array $param): array {
		try {
			if (($stmt = $this->_cn->prepare($query))===false) throw new Exception();
			$stmtParams = array();
			array_unshift($param, $marker);
			foreach ($param as $key => $value) {
				$stmtParams[$key] =& $param[$key];	// bind_paramへの引数を参照渡しにする
			}
			call_user_func_array(array($stmt, 'bind_param'), $stmtParams);
			$stmt->execute();
			$stmt->store_result();
			$r = $this->fetchAll($stmt);
			$stmt->close();
			if (empty($r)) throw new Exception();
		} catch (Exception $e) {
			$r = array();
		}
		return $r;
	}
	
	/**
	 * プリペアドステートメントから結果を取得し、バインド変数に格納する
	 * @stmt	実行するプリペアドステートメントオブジェクト
	 * @return	[カラム名:値, ...][]
	 */
	protected function fetchAll( &$stmt) {
		$hits = array();
		$params = array();
		$meta = $stmt->result_metadata();
		while ($field = $meta->fetch_field()) {
			$params[] =& $row[$field->name];
		}
		call_user_func_array(array($stmt, 'bind_result'), $params);
		while ($stmt->fetch()) {
			$c = array();
			foreach($row as $key=>$val) {
				$c[$key] = $val;
			}
			$hits[] = $c;
		}
		return $hits;
	}
}
?>
