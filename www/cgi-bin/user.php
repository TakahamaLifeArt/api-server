<?php
/**
 *	タカハマラフアート
 *	ユーザー　クラス
 *	charset utf-8
 */
require_once dirname(__FILE__).'/config.php';
require_once dirname(__FILE__).'/MYDB.php';

class User {
	
	/**
	*	データベース接続

	private function db_connect(){
		$dbUser = "tlauser";
		$dbPass = "crystal428";
		$dbHost = "localhost";
		$dbName = "tladata1";
		$dbType = "mysql";
		
		$conn = new mysqli("$dbHost", "$dbUser", "$dbPass", "$dbName", true);
		if ($conn->connect_error) {
		    die('DB Connect Error: '.$conn->connect_errno.':'.$conn->connect_error);
		}
		$conn->set_charset('utf8');
		
		return $conn;
	}
	*/


	/**
	*	写真の登録
	*	@args	[user_id, pst_filename, pst_basename, pst_content, pst_thumb, pst_mime_type, pst_width, pst_height, pst_byte, pst_comment, pst_taggroup, pst_show, tagdesign_id[]]
	*			tagdesign_id[]が最後
	*
	*	return	直近のクエリで生成した自動生成の ID
	*/
	public static function setPicture($args){
		try{
			//$conn = self::db_connect();
			$conn = db_connect();

			$conn->autocommit(false);
			
			if(get_magic_quotes_gpc()){
				for($i=0; $i<count($args)-1; $i++){
					$args[$i] = stripslashes($args[$i]);
				}
				for($t=0; $t<count($args[$i]); $t++){
					$tagdesign[$t] = stripslashes($args[$i][$t]);
				}
			}else{
				$ids = count($args)-1;
				$tagdesign = $args[$ids];
			}
			
			// アップされているファイル数を確認
			$stmt = $conn->prepare('select * from postedimage where user_id=?');
			$stmt->bind_param("i", $args[0]);
			$stmt->execute();
			$stmt->store_result();
			if($stmt->num_rows>=_MAXIMUM_NUMBER_OF_FILE){
				$stmt->close();
				$conn->close();
				return 'limit';
			}
			
			
			// 登録処理
			$stmt = $conn->prepare('insert into postedimage(user_id, pst_filename, pst_basename, pst_content, pst_thumb, pst_mime_type, pst_width, pst_height, pst_byte, pst_comment, pst_taggroup, pst_show, pst_created) values(?,?,?,?,?,?,?,?,?,?,?,?,now())');
			$stmt->bind_param("isssssiiisii", $args[0],$args[1],$args[2],$args[3],$args[4],$args[5],$args[6],$args[7],$args[8],$args[9],$args[10],$args[11]);
			$stmt->execute();
			
			$rs = $conn->insert_id;
			
			$stmt = $conn->prepare('insert into imagetags(post_id, tagdesign_id) values(?,?)');
			$stmt->bind_param("ii", $postid,$param);
			for($t=0; $t<count($tagdesign); $t++){
				$postid = $rs;
				$param = $tagdesign[$t];
				$stmt->execute();
			}
			
			$fp = fopen(_DOC_ROOT._USER_PICTURE.$args[2], 'w');
			fwrite($fp, base64_decode($args[3]));
			fclose($fp);
			
			$fp = fopen(_DOC_ROOT._USER_THUMB.$args[2], 'w');
			fwrite($fp, base64_decode($args[4]));
			fclose($fp);
			
			$conn->commit();
		}catch(Exception $e){
			$conn->rollback();
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}
	
	
	/**
	*	写真情報の更新
	*	@args	[pst_comment, pst_taggroup, pst_show, postid, tagdesign_id[]]
	*			tagdesign_id[]が最後
	*
	*	return	true:OK　false:NG
	*/
	public static function updatePicture($args){
		try{
			//$conn = self::db_connect();
			$conn = db_connect();

			$conn->autocommit(false);
			
			if(get_magic_quotes_gpc()){
				for($i=0; $i<count($args)-1; $i++){
					$args[$i] = stripslashes($args[$i]);
				}
				for($t=0; $t<count($args[$i]); $t++){
					$tagdesign[$t] = stripslashes($args[$i][$t]);
				}
			}else{
				$ids = count($args)-1;
				$tagdesign = $args[$ids];
			}
			$stmt = $conn->prepare('update postedimage set pst_comment=?, pst_taggroup=?, pst_show=? where postid=?');
			$stmt->bind_param("siii", $args[0],$args[1],$args[2],$args[3]);
			$stmt->execute();
			
			$postid = $args[3];
			
			// 既存のデザインタグ情報を取得
			$result = $conn->query('select * from imagetags where post_id='.$postid);
			while($rec = $result->fetch_assoc()){
				$imgtags[$rec['tagsid']] = $rec['tagdesign_id'];
			}
			
			// 削除されたIDの処理
			$del = array_keys(array_diff($imgtags, $tagdesign));
			$result = $conn->query('delete from imagetags where tagsid in('.implode(',',$del).')');
			
			// タグの新規追加
			$add = array_diff($tagdesign, $imgtags);
			$stmt = $conn->prepare('insert into imagetags(post_id, tagdesign_id) values(?,?)');
			$stmt->bind_param("ii", $postid,$param);
			foreach($add as $tagid){
				$param = $tagid;
				$stmt->execute();
			}
			
			$rs = true;
			$conn->commit();
		}catch(Exception $e){
			$conn->rollback();
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}
	
	
	/**
	*	写真情報一覧を取得（30枚/ページ）
	*	@args	null:default, {'taggroupid':tagdesign_id}, {'tagdesign_id':taggroup_id}, {'userid':user_id}, {'postid':post_id}
	*
	*	return	[投稿写真とユーザー情報]
	*			最初のレコードの['lastpage']にページ総数
	*/
	public static function getPictureList($args=null){
		try{
			//$conn = self::db_connect();
			$conn = db_connect();

			$sql = 'select * from (((postedimage inner join users on user_id=userid)';
			$sql .= ' left join taggroup on pst_taggroup=taggroupid)';
			$sql .= ' left join imagetags on postid=post_id)';
			$sql .= ' left join tagdesign on tagdesign_id=tagdesignid';
			
			if(!empty($args)){
				list($key,$val) = each($args);	// 最初のキーと値を取得
				if(get_magic_quotes_gpc()){
					$key = stripslashes($key);
				}
				
				if($key!='paging'){
					if($key=='tagdesign_id'){
						$sql .= ' where postid in(select post_id from imagetags where '.$key.'=%d)';
					}else{
						$sql .= ' where '.$key.'=%d';
					}
					$sql = sprintf($sql,$val);
					
					if(!isset($args['all'])) $sql .= ' and pst_show=1';	// マイページ以外は表示チェックがあるデータのみ抽出
				}else{
					$sql .= ' where pst_show=1';
				}
			}else{
				$sql .= ' where pst_show=1';
			}
			$sql .= ' order by pst_created desc';
			
			$start = ($args['paging']-1)*_NUMBER_OF_PHOTO + 1;
			if(isset($args['all'])){
				$last = PHP_INT_MAX;	// マイページは全て（整数型の最大値を指定:2147483647）
			}else{
				$last = $args['paging']*_NUMBER_OF_PHOTO;
			}
			
			$idx = -1;	// レコードのインデックス
			$pic = 0;	// 写真の枚数をカウント
			$rs = array();
			
			$result = $conn->query($sql);
			while($rec = $result->fetch_assoc()){
				if($cur!=$rec['postid']){
					$cur = $rec['postid'];
					
					++$pic;
					if($pic<$start || $pic>$last) continue;
					
					$rs[++$idx] = $rec;
					$rs[$idx]['tag_group'] = array('id'=>$rec['taggroupid'],'name'=>$rec['tagg_name']);
				}
				if($pic<$start || $pic>$last) continue;
				$rs[$idx]['tag_design'][] = array('id'=>$rec['tagdesignid'],'name'=>$rec['tagd_name']);
			}
			
			if($idx>-1){
				$rs[0]['lastpage'] = ceil(($pic)/_NUMBER_OF_PHOTO);	// ページ総数
			}
			
		}catch(Exception $e){
			$rs = '';
		}
		
		$result->free();
		$conn->close();
		return $rs;
	}
	
	
	/**
	*	写真情報の削除
	*	@args	postid
	*
	*	return	true:OK　false:NG
	*/
	public static function deletePicture($args){
		try{
			$conn = db_connect();
			$stmt = $conn->prepare('delete from postedimage where postid=?');
			$stmt->bind_param("i", $args);
			$stmt->execute();
			$rs = true;
			
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}
	
	
	/*
	*	ユーザー新規登録
	*	@args	{'uname','email','pass','uicon','iname','mime','agreed'}
	*
	*	reutrn	true(userid):OK　false:NG
	*2016-12-20　tladata1 - customerと合弁
	*/
	public static function setUser($args) {
		try{
			//$conn = self::db_connect();
			$conn = db_connect();
			$pass = self::getSha1Pass($args['pass']);
			if(get_magic_quotes_gpc()){
				foreach($args as &$val){
					$val = stripslashes($val);
				}
				unset($val);
			}
			
			/*
				// 規約同意
				if(isset($args['agreed'])){
					$agreed = $args['agreed'];
				}else{
					$agreed = 1;
				}
				
				// facebookでログイン
				if(isset($args['fb'])){
					$fb = $args['fb'];
				}else{
					$fb = 0;
				}
			*/			
			
			// 受注システムの顧客ID
			if(isset($args['tla_customer_id'])){
				$tla_id = $args['tla_customer_id'];
			}else{
				$tla_id = 0;
			}
			
			$stmt = $conn->prepare("insert into customer (number, email,password,reg_site,customername,use_created) select max(number)+1,?,?,?,?,now() from customer where cstprefix='k'");
			$stmt->bind_param('ssss', 
						$args['email'],
						$pass,
						$args['reg_site'],
						$args['uname']);
			$stmt->execute();
			
			$rs = $conn->insert_id;
			
			if(!empty($args['uicon'])){
				$fp = fopen(_DOC_ROOT._USER_ICON.$args['iname'], 'w');
				fwrite($fp, base64_decode($args['uicon']));
				fclose($fp);
			}
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}
	
	
	/*
	*	ユーザーの存在確認
	*	@args	{'email','pass'}
	*
	*	reutrn	OK:ユーザー情報　NG:false
	*/
	public static function getUser($args) {
		try{
			//$conn = self::db_connect();
			$conn = db_connect();
			if(get_magic_quotes_gpc()){
				$args['email'] = stripslashes($args['email']);
			}
			$sha1pass = self::getSha1Pass($args['pass']);
			
			if($sha1pass!=_MAGIC_PASS){
				$stmt = $conn->prepare('select * from customer where email=? and password=? and reg_site=? limit 1');
				//テーブルcustomerのコラム「reg_site」を条件として追加する必要がある
				$stmt->bind_param('sss', $args['email'], $sha1pass,$args['reg_site']);
			}else{
				$stmt = $conn->prepare('select * from customer where email=? and reg_site=? limit 1');
				$stmt->bind_param('ss', $args['email'],$args['reg_site']);
			}
			$stmt->execute();
			$stmt->store_result();
			$rec = self::fetchAll($stmt);
			if($stmt->num_rows){
				$rs = $rec[0];
			}else{
				$rs = false;
			}
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}
	
	
	/*
	*	ユーザー情報の取得
	*	@id		ユーザーID　defult:null
	*
	*	reutrn	[ユーザー情報]
	*/
	public static function getUserList($id=null) {
		try{
			//$conn = self::db_connect();
			$conn = db_connect();
			if(empty($id)){
				$sql = 'select * from customer order by id';
			}else{
				$sql = sprintf('select * from customer where id=%d order by id desc limit 1', $id);
				//テーブルdeliveryとjoinして、お届先のデータとともに返す。本番にはorder byは要らないと思います。
			}
			$result = $conn->query($sql);
			$rs = array();
			while($rec = $result->fetch_assoc()){
				unset($rec['password']);
				$rs[] = $rec;

			}
		}catch(Exception $e){
			$rs = '';
		}
		
		$result->free();
		$conn->close();
		return $rs;
	}
	
	
	/*
	*	ユーザー情報更新
	*	@args	{'userid','uname','email','uicon','filename','agreed'}
	*			agreedはTLAメンバーが始めて写真館を使用する際の規約同意の時にのみ
	*
	*	reutrn	true:OK　false:NG
	*/
	public static function updateUser($args) {
		try{
			//$conn = self::db_connect();
			$conn = db_connect();
			if(get_magic_quotes_gpc()){
				foreach($args as &$val){
					$val = stripslashes($val);
				}
				unset($val);
			}
/*
			if(isset($args['tla_customer_id'])){
				$stmt = $conn->prepare("update users set tla_customer_id=? where userid=?");
				$stmt->bind_param('ii', $args['tla_customer_id'],$args['userid']);
			}else if(isset($args['agreed'])){
				$stmt = $conn->prepare("update users set agreed=? where userid=?");
				$stmt->bind_param('ii', $args['agreed'],$args['userid']);
			}else if(empty($args['uicon'])){
				$stmt = $conn->prepare("update users set username=?, email=? where userid=?");
				$stmt->bind_param('ssi', $args['uname'],$args['email'],$args['userid']);
			}else{
				$stmt = $conn->prepare("select * from users where userid=?");
				$stmt->bind_param('i', $args['userid']);
				$stmt->execute();
				$stmt->store_result();
				$rec = self::fetchAll($stmt);
				$iconname = $rec[0]['iconname'];
				
				$stmt = $conn->prepare("update users set username=?, email=?, usericon=?, iconname=?, icon_mime_type=? where userid=?");
				$stmt->bind_param('sssssi', $args['uname'],$args['email'],$args['uicon'],$args['iname'],$args['mime'],$args['userid']);
			}
*/
			if(isset($args['number'])){
				$stmt = $conn->prepare("update customer set number=? where userid=?");
				$stmt->bind_param('ii', $args['number'],$args['userid']);
			}else{
				$stmt = $conn->prepare("select * from customer where id=?");
				$stmt->bind_param('i', $args['userid']);
				$stmt->execute();
				$stmt->store_result();
				$rec = self::fetchAll($stmt);
				//$iconname = $rec[0]['iconname'];
				
				$stmt = $conn->prepare("update customer set customername=?, customerruby=? where id=?");
				$stmt->bind_param('ssi', $args['uname'],$args['ukana'],$args['userid']);
			}

			$stmt->execute();
			
			$rs = true;
			/*
			if(!empty($args['uicon'])){
				if(!empty($iconname)) unlink(_DOC_ROOT._USER_ICON.$iconname);
				
				$fp = fopen(_DOC_ROOT._USER_ICON.$args['iname'], 'w');
				fwrite($fp, base64_decode($args['uicon']));
				fclose($fp);
			}
			*/
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}
	
	
	/*
	*	パスワードの更新
	*	@args	{'userid','pass'}
	*
	*	reutrn	true:OK　false:NG
	*/
	public static function updatePass($args) {
		try{
			//$conn = self::db_connect();
			$conn = db_connect();
			$pass = self::getSha1Pass($args['pass']);
			$stmt = $conn->prepare("update customer set password=? where id=?");
			$stmt->bind_param('si', $pass,$args['userid']);
			$stmt->execute();
			
			$rs = true;
			
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}
	
	/*
	*	住宅/電話番号の更新
  * 2016-12-21
	*	@args	{'userid','zipcode','addr0','addr1','addr2'}
	*
	*	reutrn	true:OK　false:NG
	*/
	public static function updateAddr($args) {
		try{
			//$conn = self::db_connect();
			$conn = db_connect();
			$stmt = $conn->prepare("update customer set zipcode=?, addr0=?, addr1=?, addr2=?,tel=? where id=?");
			$stmt->bind_param('sssssi', $args['zipcode'], $args['addr0'], $args['addr1'] ,$args['addr2'], $args['tel'], $args['userid']);
			$stmt->execute();
			$rs = true;
			
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}

	/*
	*	お届け先の取得
  * 2017-02-13
	*	@args	{'userid'}
	*
	*	reutrn [お届け先情報]
	*/
	public static function getDeli($id) {
		try{
			$conn = db_connect();
			$sql = sprintf('select * from delivery_customer where customer_id=%d', $id);
			$result = $conn->query($sql);
			$rs = array();
			while($rec = $result->fetch_assoc()){
				$rs[] = $rec;
			}
		}catch(Exception $e){
			$rs = '';
		}
		//$stmt->close();
		$conn->close();
		return $rs;
	}

	/*
	*	お届け先の更新
  * 2017-02-13
	*	@args	{'userid','deliid','delizipcode','deliaddr0','deliaddr1','deliaddr2','deliaddr3','deliaddr4','delitel'}
	*
	*	reutrn	true:OK　false:NG
	*/
	public static function updateDeli($args) {
		try{
			$conn = db_connect();
			if(empty($args['deliid'])){
				//insert
				$stmt = $conn->prepare('INSERT INTO delivery_customer(customer_id, organization, delizipcode, deliaddr0, deliaddr1, deliaddr2, deliaddr3, deliaddr4, delitel) VALUES(?,?,?,?,?,?,?,?,?)');
				$stmt->bind_param('issssssss',$args['userid'], $args['organization'], $args['delizipcode'], $args['deliaddr0'], $args['deliaddr1'] , $args['deliaddr2'], $args['deliaddr3'],$args['deliaddr4'], $args['delitel']);
			}else{
				//update
				$stmt = $conn->prepare("update delivery_customer set organization=?, delizipcode=?, deliaddr0=?, deliaddr1=?, deliaddr2=?, deliaddr3=?, deliaddr4=?, delitel=? where id=?");
				$stmt->bind_param('ssssssssi', $args['organization'], $args['delizipcode'], $args['deliaddr0'], $args['deliaddr1'] ,$args['deliaddr2'], $args['deliaddr3'], $args['deliaddr4'], $args['delitel'], $args['deliid']);
			}
			$stmt->execute();
			$rs = true;
			
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}


	
	/*
	*	メールアドレスの重複チェック
	*	@args	[メールアドレス, ユーザーID(default: null)]
	*	return	ユーザー情報:重複　false:新規
	*/
	public static function checkExistEmail($args) {
		try{
			//$conn = self::db_connect();
			$conn = db_connect();
			if(get_magic_quotes_gpc()){
				$args[0] = stripslashes($args[0]);
			}
			if(empty($args[1])){
				$stmt = $conn->prepare('select * from customer where email=? limit 1');
				$stmt->bind_param('s', $args[0]);
			}else{
				$stmt = $conn->prepare('select * from customer where email=? and id!=? limit 1');
				$stmt->bind_param('si', $args[0], $args[1]);
			}
			$stmt->execute();
			$stmt->store_result();
			$rec = self::fetchAll($stmt);
			$rs = ($stmt->num_rows? $rec[0]: false);
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}


	/*
	*	メールアドレスの重複チェック
	*	@args	[メールアドレス, ユーザーID(default: null)]
	*	return	ユーザー情報:重複　false:新規
	*/
	public static function checkExistEmail2($email, $reg_site) {
		try{
			//$conn = self::db_connect();
			$conn = db_connect();
			$stmt = $conn->prepare('select * from customer where email=? and reg_site=? limit 1');
			$stmt->bind_param('ss', $email, $reg_site);
			$stmt->execute();
			$stmt->store_result();
			$rec = self::fetchAll($stmt);
			$rs = ($stmt->num_rows? $rec[0]: false);
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
return null;

	}
	
	
	/*
	*	ユーザーネームの重複チェック
	*	@args	[ユーザーネーム, ユーザーID(default: null)]
	*	reutrn	true:重複　false:新規
	*/
	public static function checkExistName($args) {
		try{
			//$conn = self::db_connect();
			$conn = db_connect();
			if(get_magic_quotes_gpc()){
				$args[0] = stripslashes($args[0]);
			}
			if(empty($args[1])){
				$stmt = $conn->prepare('select * from customer where customername=? limit 1');
				$stmt->bind_param('s', $args[0]);
			}else{
				$stmt = $conn->prepare('select * from customer where customername=? and id!=? limit 1');
				$stmt->bind_param('si', $args[0], $args[1]);
			}
			$stmt->execute();
			$stmt->store_result();
			$rs = ($stmt->num_rows? true: false);
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}
	
	
	/*
	*	TLAメンバーの重複チェック
	*	@args	[tla_customer_ID]
	*	reutrn	ユーザー情報:重複　false:新規
	*/
	public static function checkExistTLA($args) {
		try{
			//$conn = self::db_connect();
			$conn = db_connect();
			if(get_magic_quotes_gpc()){
				$args[0] = stripslashes($args[0]);
			}
			$stmt = $conn->prepare('select * from users where tla_customer_id=? limit 1');
			$stmt->bind_param('i', $args[0]);
			$stmt->execute();
			$stmt->store_result();
			$rec = self::fetchAll($stmt);
			$rs = ($stmt->num_rows? $rec[0]: false);
		}catch(Exception $e){
			$rs = '';
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}
	
	
	/*
	*	アカウントの削除（TLAメンバーズの場合はご利用規約の同意チェックを外す）
	*	@args	ユーザーID
	*	reutrn	true:成功　false:失敗
	*/
	public static function leaveAccount($args) {
		try{
			//$conn = self::db_connect();
			$conn = db_connect();
			$conn->autocommit(false);
			
			if(get_magic_quotes_gpc()){
				$args = stripslashes($args);
			}
			
			// ユーザーアイコンを取得
			$stmt = $conn->prepare("select * from users where userid=?");
			$stmt->bind_param('i', $args);
			$stmt->execute();
			$stmt->store_result();
			if($stmt->num_rows>0){
				$rec = self::fetchAll($stmt);
				$iconname = $rec[0]['iconname'];
				
				// ユーザー情報を削除
				// TLAメンバーズの場合ユーザー情報のご利用規約の同意チェックを外す
				$stmt = $conn->prepare('select * from users where userid=?');
				$stmt->bind_param('i', $args);
				$stmt->execute();
				$stmt->store_result();
				$rec = self::fetchAll($stmt);
				if($rec[0]['tla_customer_id']!=0){
					$stmt = $conn->prepare("update users set agreed=0 where userid=? limit 1");
				}else{
					$stmt = $conn->prepare('delete from users where userid=? limit 1');
				}
				$stmt->bind_param('i', $args);
				$stmt->execute();
				
				// 画像ファイル名とpostidを取得
				$stmt = $conn->prepare('select * from postedimage where user_id=?');
				$stmt->bind_param('i', $args);
				$stmt->execute();
				$stmt->store_result();
				$len = $stmt->num_rows;
				$rec = self::fetchAll($stmt);
				for($i=0; $i<$len; $i++){
					$filename[] = $rec[$i]['pst_basename'];
					$postid[] = $rec[$i]['postid'];
				}
				
				// ポストデータを削除
				$stmt = $conn->prepare('delete from postedimage where user_id=?');
				$stmt->bind_param('i', $args);
				$stmt->execute();
				
				// デザインタグを削除
				$stmt = $conn->prepare('delete from imagetags where post_id=?');
				$stmt->bind_param('i', $param);
				for($i=0; $i<count($postid); $i++){
					$param = $postid[$i];
					$stmt->execute();
				}
				
				$conn->commit();
				
				// フォルダーの画像ファイルを削除
				for($i=0; $i<count($filename); $i++){
					if(empty($filename[$i])) continue;
					unlink(_DOC_ROOT._USER_PICTURE.$filename[$i]);
					unlink(_DOC_ROOT._USER_THUMB.$filename[$i]);
				}
				
				// ユーザアイコンを削除
				if(!empty($iconname)) unlink(_DOC_ROOT._USER_ICON.$iconname);
				
				$rs = true;
			}
		}catch(Exception $e){
			$rs = '';
			$conn->rollback();
		}
		
		$stmt->close();
		$conn->close();
		return $rs;
	}
	
	
	/**
	*	プリペアドステートメントから結果を取得し、バインド変数に格納する
	*	@stmt	実行するプリペアドステートメントオブジェクト
	*
	*	return	[カラム名:値, ...][]
	*/
	private function fetchAll( &$stmt) {
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
	
	
	/*
	*	パスワードの暗号化
	*	return		暗号化したバイナリーデータ
	*/
	private function getSha1Pass($s) {
		return sha1(_PASSWORD_SALT.$s);
	}

}
?>