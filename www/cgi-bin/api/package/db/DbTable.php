<?php
/**
 * データベース操作クラス
 * @package calendar
 * @author <ks.desk@gmail.com>
 *
 * Copyright © 2014 Kyoda Yasushi
 *
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/MIT
 */
declare(strict_types=1);
namespace package\db;
require_once dirname(__FILE__).'/DbConnection.php';
class DbTable extends DbConnection {
	protected $fieldName = array();
	private $paramMarker = array();
	private $tableName = null;
	private $fieldType = array(
							'1'=>'i', '2'=>'i', '3'=>'i', '8'=>'i', '9'=>'i', '16'=>'i', 
							'4'=>'d', '5'=>'d', '246'=>'d', 
							'252'=>'b', 
							'7'=>'s', '10'=>'s', '11'=>'s', '12'=>'s', '13'=>'s', '253'=>'s', '254'=>'s'
							);
	private $resultType = array('both'=>MYSQLI_BOTH, 'assoc'=>MYSQLI_ASSOC, 'num'=>MYSQLI_NUM);
	private $defaultStopDate = "9999-12-31";
	
	public function __construct($tablename){
		if(empty($tablename)) return;
		$this->setField($tablename);
	}
	
	
	public function __destruct() {
		parent::__destruct();
	}
	
	
	/**
	 * テーブルのフィールド構成を設定
	 * @param tableName
	 */
	public function setField($tableName){
		try{
			$this->fieldName = array();
			$this->paramMarker = array();
			$this->tableName = null;
			parent::dbConnect();
			$sql = sprintf("SELECT * FROM %s LIMIT 1", $tableName);
			$result = $this->conn->query($sql);
			while($finfo = $result->fetch_field()){
				$this->fieldName[] = $finfo->name;
				$this->paramMarker[] = $this->fieldType[$finfo->type];
			}
			$this->tableName = $tableName;
		}catch(Exception $e){
			$this->fieldName = array();
			$this->paramMarker = array();
			$this->tableName = null;
		}
		$result->close();
	}
	
	
	/**
	 * フィールド構成を取得
	 * @return カラム名の配列を返す
	 */ 
	public function getField(){
		return $this->fieldName;
	}
	
	
	/**
	 * レコードを取得
	 * @param whereIs {object} index of the column=>[operator, value]
	 * @param groupBy {array} index of the column
	 * @param orderBy {array} [{index of the column=>asc|desc}, ...]
	 * @param limitIs {int} number of row count
	 * @param curdate {string} yyyy-mm-dd
	 * @param resutltype {string} both(default), assoc, num, 
	 * @return [record, ...]
	 */ 
	public function getRecord($whereIs=array(), $groupBy=NULL, $orderBy=array(), $limitIs=NULL, $curdate=NULL, $resulttype='both'){
		try{
			parent::dbConnect();
			$r = array();
			$where = "";
			$columns = count($this->fieldName) - 1;
			if(preg_match('/^stop$/', $this->fieldName[$columns])){
				$curdate = $this->validdate($curdate);
				$where = " where ".$this->fieldName[$columns].">'".$curdate."' and ".$this->fieldName[--$columns]."<='".$curdate."'";
			}
			if(!empty($whereIs)){
				foreach ($whereIs as $key => $val) {
					$pieces[] = $this->fieldName[$key].$val[0]."'".$val[1]."'";
				}
				if($where==""){
					$where = " where ";
				}else{
					$where .= " and ";
				}
				$where .= implode(" and ", $pieces);
			}
			if(empty($groupBy)){
				$group = "";
			}else{
				$tmp = array();
				for($i=0; $i<count($groupBy); $i++){
					$tmp[] = $this->fieldName[$groupBy[$i]];
				}
				$group = " group by ".implode(",",$tmp);
			}
			if(empty($orderBy)){
				$order = $this->fieldName[0];
			}else{
				$tmp = array();
				for ($i=0; $i < count($orderBy); $i++){ 
					foreach($orderBy[$i] as $key=>$val){
						$tmp[] = $this->fieldName[$key]." ".$val;
					}
				}
				$order = implode(",",$tmp);
			}
			if(empty($limitIs)){
				$limit = "";
			}else{
				$limit = " limit ".intval($limitIs);
			}
			$sql = sprintf("select * from %s%s%s order by %s%s", $this->tableName, $where, $group, $order, $limit);
			$result = $this->conn->query($sql);
			while($rec = $result->fetch_array($this->resultType[$resulttype])){
				$r[] = $rec;
			}
		}catch(Exception $e){
			$r = array();
		}
		$result->close();
		return $r;
	}
	
	
	/**
	 * テーブル連結してレコードを取得
	 * @param table		{array} テーブル名 [table, ...]
	 * @param join		{array} inner or left
	 * @param assoc		{array} onで関連づけるカラム名 [[colum,colum], ..]
	 * @param where		{Object} column name => [operator, value]
	 * @param orderBy	{array} [{column name => asc|desc}, ...]
	 * @return [record, ...]
	 */ 
	public function getJoinRecord($table, $join, $assoc, $whereIs=array(), $orderBy=array()){
		try{
			parent::dbConnect();
			$r = array();
			$len = count($join);
			if($len<1) throw new Exception();
			$sql = 'select * from ';
			for($i=0; $i<$len; $i++){
				$sql .= '(';
			}
			$sql .= $table[0];
			for($i=0; $i<$len; $i++){
				$next = $i+1; 
				$sql .= ' '.$join[$i].' join '.$table[$next].' on '.$assoc[$i][0].'='.$assoc[$i][1].')';
			}
			if(!empty($whereIs)){
				foreach ($whereIs as $key => $val) {
					$pieces[] = $key.$val[0]."'".$val[1]."'";
				}
				$sql .= " where ".implode(' and ', $pieces);
			}
			$tmp = array();
			if( ! empty($orderBy)){
				for ($i=0; $i < count($orderBy); $i++){ 
					foreach($orderBy[$i] as $column=>$val){
						$tmp[] = $column." ".$val;
					}
				}
				$order = implode(",",$tmp);
				$sql .= ' order by '.$order;
			}
			$result = $this->conn->query($sql);
			while($rec = $result->fetch_array(MYSQLI_BOTH)){
				$r[] = $rec;
			}
		}catch(Exception $e){
			$r = array();
		}
		$result->close();
		return $r;
	}
	
	
	/**
	 * レコード新規登録
	 * @param value		{array} [[0:0, fieldのindex:データ, ],[]]
	 * @param curdate	{string} 適用開始日付、YYYY-MM-DD
	 * @return insert ID
	 */ 
	public function insertRecord($value, $curdate=NULL){
		try{
			if(empty($value)) return;
			$dateType = 0;	// 0:追加カラムなし,  1:取扱期間を設定,  2:登録日時を設定
			parent::dbConnect();
			$columns = count($this->fieldName) - 1;
			if(preg_match('/.+_stop$/', $this->fieldName[$columns])){
				$len = count($this->fieldName);
				$dateType = 1;
			}else{
				$len = count($value[0]);
			}
			$fld = array();
			$marker = '';
			for($i=0; $i<$len; $i++){
				$fld[] = $this->fieldName[$i];
				$marker .= $this->paramMarker[$i];
			}
			if(empty($dateType)){
				$columns = count($this->fieldName) - 2;
				if(preg_match('/.+_created$/', $this->fieldName[$columns])){
					$createdColumn = count($this->fieldName)-2;
					$fld[] = $this->fieldName[$columns];
					$marker .= $this->paramMarker[$columns];
					$dateType = 2;
					$len++;
				}
			}
			$sql = "insert into ".$this->tableName." (".implode( ' , ', $fld).") values(".implode( ' , ', array_fill(0, $len, '?') ).")"; 
			$stmt = $this->conn->prepare($sql);
			$count = count($value);
			for($i=0; $i<$count; $i++){
				if($dateType==1){
					$value[$i][] = $this->validdate($curdate);
					$value[$i][] = $this->defaultStopDate;
				}else if($dateType==2){
					$value[$i][] = date('Y-m-d H:i:s');
				}
				array_unshift($value[$i], $marker);
				parent::prepared($stmt, $value[$i]);
			}
			$r = $stmt->insert_id;
		}catch(Exception $e){
			$r = 0;
		}
		$stmt->close();
		return $r;
	}
	
	
	/**
	 * レコード更新
	 * @param value	{Object} {primaryID:[fieldのindex:データ, ...], ...}
	 * @return 更新した行数
	 */ 
	public function updateRecord($value){
		try{
			if(empty($value)) return;
			parent::dbConnect();
			$query = " set ";
			$when = array();
			$val = array();
			foreach($value as $id=>$rec){
				$when[] = "when ".$this->fieldName[0]."=".$id." then ? ";
				foreach($value[$id] as $index=>$data) {
					$val[$index][] = $data;
				}
			}
			$param = array();
			$marker = array();
			foreach($value[$id] as $index=>$data) {
				$query .= $this->fieldName[$index]." = case ";
				for($u=0; $u<count($when); $u++){
					$query .= $when[$u];
				}
				$query .= "else ".$this->fieldName[$index]." end,";
				
				for($o=0; $o<count($val[$index]); $o++){
					$param[] = $val[$index][$o];
					$marker[] = $this->paramMarker[$index];
				}
			}
			$sql = "update ".$this->tableName.substr($query, 0, -1);
			$stmt = $this->conn->prepare($sql);
			array_unshift($param, implode('', $marker));
			parent::prepared($stmt, $param);
			$r = $stmt->affected_rows;
		}catch(Exception $e){
			$r = -1;
		}
		$stmt->close();
		return $r;
	}
	
	/**
	 * レコード削除
	 * @param value		{array} Primary ID または検索データ(integer)
	 * @param index		{Integer} 検索対象Fieldのインデックス、defaultは０（Primary ID）
	 * 
	 * @return 削除した行数を返す
	 */ 
	public function deleteRecord($value, $index=0){
		try{
			$count = count($value);
			if($count==0) return 0;
			
			parent::dbConnect();
			$sql = "delete from ".$this->tableName." where ".$this->fieldName[$index]." in(".implode( ' , ', array_fill(0, $count, '?') ).")";
			$stmt = $this->conn->prepare($sql);
			$marker = implode( '', array_fill(0, $count, 'i') );
			array_unshift($value, $marker);
			parent::prepared($stmt, $value);
			$r = $stmt->affected_rows;
		}catch(Exception $e){
			$r = -1;
		}
		$stmt->close();
		return $r;
	}
	
	/**
	 *	日付の妥当性を確認し不正値は今日の日付を返す
	 *	@param curdate 日付(0000-00-00)
	 *	@return 0000-00-00
	 */
	private function validdate($curdate){
		if(empty($curdate)){
			$curdate = date('Y-m-d');
		}else{
			$curdate = str_replace("/", "-", $curdate);
			$d = explode('-', $curdate);
			if(checkdate($d[1], $d[2], $d[0])===false){
				$curdate = date('Y-m-d');
			}
		}
		return $curdate;
	}
}
