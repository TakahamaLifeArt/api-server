<?php
/** 
 * Mysqli connection
 * @package calendar
 * @author (c) 2014 ks.desk@gmail.com
 */
declare(strict_types=1);
namespace package\db;
use Mysqli;
class DbConnection 
{
	private $dbHost = _DB_HOST;
	private $dbUser = _DB_USER;
	private $dbPass = _DB_PASS;
	private $dbName = _DB_NAME;
	private $dbType = _DB_TYPE;
	protected $conn = NULL;
	
	
	public function __construct() {}
	
	
	public function __destruct() {
		if ($this->conn instanceof mysqli) {
			$this->conn->close();
		}
	}
	
	
	/**
	 * SQLの接続
	 * MySQLサーバーへの接続をオープンしてオブジェクトをプロパティ{@code $conn}に代入
	 */
	protected function dbConnect() {
		if (! ($this->conn instanceof mysqli)) {
			$this->conn = new mysqli($this->dbHost, $this->dbUser, $this->dbPass, $this->dbName);
		}
		if (mysqli_connect_error()) {
		    die('DB Connect Error: '.mysqli_connect_error());
		}
		$this->conn->set_charset('utf8');
	}
	
	
	/**
	 * MySQL接続パラメータの設定
	 * @param {array} args {@code "host"=>"","user"=>"","pass"=>"","name"=>""}
	 */ 
	public function setParameter($args) {
		foreach ($args as $key => $value) {
			switch ($key) {
				case 'host':
					$this->dbHost = $value;
					break;
				case 'user':
					$this->dbUser = $value;
					break;
				case 'pass':
					$this->dbPass = $value;
					break;
				case 'name':
					$this->dbName = $value;
					break;
				default:
					break;
			}
		}
	}
	
	
	/**
	 * SQLの接続を表すオブジェクトを返す
	 * @return {object} MySQLサーバーへの接続を表すオブジェクトを返します
	 */
	public function getConnection() {
		if(! ($this->conn instanceof mysqli)){
			$this->db_connect();
		}
		return $this->conn;
	}
	
	
	/**
	 * SQLの接続を閉じる
	 */
	public function close() {
		if ($this->conn instanceof mysqli) {
			$this->conn->close();
		}
		$this->conn = NULL;
	}
	
	
	/**
	 * トランザクションを開始
	 */
	public function beginTransaction() {
		/*
		 * PHP ver5.5 and above
		 * MySql ver5.6 and above
		 * $this->conn->beginTransaction();
		 */ 
		$this->conn->autocommit(false);
	}
	 
	 
	/**
	 * コミット
	 */
	public function commit() {
		$this->conn->commit();
		$this->conn->autocommit(true);
	}
	 
	 
	/**
	 * ロールバック
	 */
	public function rollback() {
		$this->conn->rollBack();
		$this->conn->autocommit(true);
	}
}
?>