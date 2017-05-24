<?php
	/**
	*		SQLの接続
	*/
	function db_connect(){
		$dbUser = "tlauser";
		$dbPass = "crystal428";
		$dbHost = "localhost";
		$dbName = "tladata1";
		$dbType = "mysql";
		
		$conn = mysqli_connect("$dbHost", "$dbUser", "$dbPass", "$dbName", true) 
			or die("MESSAGE : cannot connect!". mysqli_error());
		
		$conn->set_charset('utf8');
		return $conn;
	}

	/**
	*		エスケープ処理
	*/
	function quote_smart($conn, $value){
		if (!is_numeric($value)) {
			if(get_magic_quotes_gpc()) stripslashes($value);
			$value = mysqli_real_escape_string($conn, $value);
		}
		return $value;
	}
		
	/**
	*		SQLの発行
	*/
	function exe_sql($conn, $sql){
		$result = mysqli_query($conn, $sql);
			//or die ('Invalid query: ' . mysqli_error($dbName));
		return $result;
	}
?>
