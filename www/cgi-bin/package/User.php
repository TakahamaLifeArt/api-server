<?php
/**
 * ユーザークラス for API3
 * charset utf-8
 *--------------------
 *
 * getUser		ユーザー情報
 */
declare(strict_types=1);
namespace package;
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/config.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/SqlManager.php';
use \Exception;
use package\db\SqlManager;
class User {

	private $_sql;		// データベースサーバーへの接続を表すオブジェクト
	
	public function __construct(string $curDate=''){
		$this->_sql = new SqlManager();
	}
	
	public function __destruct() {}


	/**
	 * ユーザー情報
	 * @param {int} id ユーザーID
	 * @param {string} start 注文確定日による検索開始日（yyyy-mm-dd）
	 * @param {string} end 注文確定日による検索終了日（yyyy-mm-dd）
	 * @reutrn [ユーザー情報]
	 */
	public function getUser(int $id=0, string $start='', string $end=''): array {
		try{
			$query = "select (case when customer.cstprefix='k' then concat('K', lpad(customer.number,6,'0')) else concat('G', lpad(customer.number,4,'0')) end) as customer_num,";
			$query .= " customername, customerruby, company as dept, companyruby as deptruby,";
			$query .= " insert(zipcode, 4, 0, '-') as zipcode, addr0, addr1, addr2, addr3, addr4,";
			$query .= " tel as tel1, mobile as tel2, email as email1, mobmail as email2, fax,";
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

			if($start){
				$start = str_replace("/", "-", $start);
				$d = explode('-', $start);
				if(checkdate($d[1], $d[2], $d[0])==false){
					$start = "2011-06-05";
				}
			}else{
				$start = "2011-06-05";
			}

			if($end){
				$end = str_replace("/", "-", $end);
				$d = explode('-', $end);
				if(checkdate($d[1], $d[2], $d[0])==false){
					$end = date('Y-m-d');
				}
			}else{
				$end = date('Y-m-d');
			}

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

}
?>