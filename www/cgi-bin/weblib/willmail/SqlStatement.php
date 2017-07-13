<?php
/**
 * MySqli statement abstract class
 * @package kintone
 * @author (c) 2014 ks.desk@gmail.com
 */
require_once dirname(__FILE__).'/../DbConnection.php';

class SqlStatement extends DbConnection
{
	private $resultType = array('both'=>MYSQLI_BOTH, 'assoc'=>MYSQLI_ASSOC, 'num'=>MYSQLI_NUM);
	
	
	public function __construct()
	{
		parent::__construct();
		parent::dbConnect();
	}
	
	
	/**
	 * クエリーを実行する
	 * @param {strig} sql SQL文
	 * @return {boolean|array} 成功の場合は{@code TRUE}を返す、失敗の場合は{@code FALSE}を返す
	 *						   SELECT, SHOW, DESCRIBE あるいは EXPLAINの場合は結果の配列
	 */
	public function queried($sql, $resulttype='both')
	{
		try {
			$result = $this->conn->query($sql);
			if (is_bool($result)) {
				$r = $result;
			} else {
				while($rec = $result->fetch_array($this->resultType[$resulttype])){
					$r[] = $rec;
				}
				$result->close();
			}
		} catch (Exception $e) {
			$r = FALSE;
		}
		return $r;
	}
	
	
	/**
	 * プリペアドステートメントの実行
	 * @param {string} sql SQL文
	 * @param {array} stmtParams bind_param関数に渡すパラメーターマーカーと対応する変数{@code array('ii', 0, 0)}
	 * @return {array|boolean} select statementの場合は結果の配列を返す
	 *						   それ以外は成功の場合に{@code TRUE}、失敗の場合は{@code FALSE}を返す
	 */
	public function prepared($sql, $stmtParams)
	{
		try {
			$stmt = $this->conn->prepare($sql);
			// PHP5.3から参照渡しになったため
			for ($i=0; $i<count($stmtParams); $i++){
				$params[] =& $stmtParams[$i];
			}
			call_user_func_array(array($stmt, 'bind_param'), $params);
			$r = $stmt->execute();
			if ($stmt->result_metadata()==NULL) return $r;	// insert, update, delete statement
			$stmt->store_result();
			$r = $this->fetchAll($stmt);
		} catch (Exception $e) {
			$r = FALSE;
		}
		$stmt->close();
		return $r;
	} 
	
	
	/**
	* プリペアドステートメントから結果を取得し、バインド変数に格納する
	* @param {object} stmt 実行するプリペアドステートメントオブジェクト
	* @return {array} カラム名をキーにした値の配列
	* @throws {array} 空の配列を返す
	*/
	private function fetchAll(&$stmt) {
		try {
			$hits = array();
			$params = array();
			$meta = $stmt->result_metadata();
			while ($field = $meta->fetch_field()) {
				$params[] =& $row[$field->name];
			}
			call_user_func_array(array($stmt, 'bind_result'), $params);
			while ($stmt->fetch()) {
				$c = array();
				foreach ($row as $key => $val) {
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