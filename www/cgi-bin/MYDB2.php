<?php
class MYDB2 {
	public function __construct(){}
	
	
	/*
	*	SQLの接続
	*/
	protected function db_connect(){
		$dbUser = "tlauser";
		$dbPass = "crystal428";
		$dbHost = "localhost";
		$dbName = "tladata1";
		$dbType = "mysql";
		
		$conn = new mysqli("$dbHost", "$dbUser", "$dbPass", "$dbName");
		if (mysqli_connect_error()) {
		    die('DB Connect Error: '.mysqli_connect_error());
		}
		$conn->set_charset('utf8');
		
		return $conn;
	}
	
	
	/*
	*	SQLの接続
	*/
	public static function getConnection(){
		return self::db_connect();
	}
	
	
	/**
	*	プリペアドステートメントから結果を取得し、バインド変数に格納する
	*	@stmt	実行するプリペアドステートメントオブジェクト
	*
	*	return	[カラム名:値, ...][]
	*/
	public static function fetchAll( &$stmt) {
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
