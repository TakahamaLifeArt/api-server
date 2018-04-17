<?php
/**
 * MySQLの接続とクエリ及びプリペアードステートメントの実行
 *--------------------
 * log
 * 2017-12-12 created
 * 2018-02-20 execQueryを追加
 */
declare(strict_types=1);
namespace package\db;
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/config.php';
use \mysqli;
use \Exception;
class SqlManager {
	private $_cn;	// データベースサーバーへの接続を表すmysqliオブジェクト
	private $_db;	// データベース名
	
	/**
	 * param {string} db データベース名
	 */
	public function __construct($db) {
		$this->_db = $db;
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
		$conn = new mysqli(_DB_HOST, _DB_USER, _DB_PASS, $this->_db);
		if (mysqli_connect_error()) {
			die('DB Connect Error: '.mysqli_connect_error());
		}
		$conn->set_charset('utf8');
		$this->_cn = $conn;
	}
	
	/**
	 * クエリーを実行する
	 * @param {string} query
	 * @return {array} selectクエリの結果セット、またはupdate, insert, deleteクエリで適用された行数
	 */
	public function execQuery(string $query): array {
		try {
			$result = $this->_cn->query($query);
			$r = array();
			
			if ($result === FALSE) {
//				$r[] = $this->_cn->errno.": ".$this->_conn->error;
				throw new Exception();	// Error
			} else if($result === TRUE) {
				$r[] = $this->_cn->affected_rows;	// SELECT文以外
			} else {
				// select文
//				$this->count = $result->num_rows;
				while ($rec = $result->fetch_array(MYSQLI_BOTH)) {
					$r[] = $rec;
				}
				$result->close();
			}
		} catch (Exception $e) {
			$r = array();
		}
		return $r;
	}
	
	/**
	 * プリペアードステートメント
	 * @param {string} query
	 * @param {string} marker
	 * @param {array} param
	 * @return {array} selectクエリの結果セット、またはupdate, insert, deleteクエリで適用された行数
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
			if (!$stmt->execute()) throw new Exception();
			$rows = $this->_cn->affected_rows;
			if ($rows > 0) {
				$r = array($rows);
			} else {
				$stmt->store_result();
				$r = $this->fetchAll($stmt);
			}
			$stmt->close();
			if (empty($r)) throw new Exception();
		} catch (Exception $e) {
			$r = array();
		}
		return $r;
	}
	
	/**
	 * プリペアドステートメントから結果を取得し、バインド変数に格納する
	 * @param stmt 実行するプリペアドステートメントオブジェクト
	 * @return {array} [カラム名:値, ...][]
	 * @throw 結果セットがない場合、空配列を返す
	 */
	protected function fetchAll( &$stmt): array {
		try {
			$hits = array();
			$params = array();
			$meta = $stmt->result_metadata();
			if (!is_object($meta)) throw new Exception();
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
		} catch (Exception $e) {
			$hits = array();
		}
		return $hits;
	}
}
?>
