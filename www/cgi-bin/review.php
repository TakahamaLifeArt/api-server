<?php
/*
*	review class
*	charset UTF-8
*	log:	2014-06-07 created
*/
require_once dirname(__FILE__).'/MYDB2.php';

class Review extends MYDB2{
/**
*	getUserReview		お客様レビュー情報
*	getItemReview		アイテムレビュー情報
*/

	
	public function __construct(){
		parent::__construct();
	}
	
	
	/**
	 *		お客様レビュー情報
	 *		@args		ソート項目（新着順：post　評価の高い順：high　評価の低い順：low）
	 *
	 *		return		[]
	 */
	public function getUserReview($args){
		try{
		/*
			$rec = array();
			$sql = 'select *, coalesce(item.item_name, userreview.item_name) as item_name, ';
			$sql .= 'coalesce(item.item_code, "") as itemcode, coalesce(category_id, 99) as category_id,';
			$sql .= 'truncate(avg((vote_1+vote_2+vote_3+vote_4)/4),1) as avg';
			$sql .= ' from ((userreview';
			$sql .= ' left join item on userreview.item_id=item.id)';
			$sql .= ' left join catalog on item.id=catalog.item_id)';
			$sql .= ' left join category on catalog.category_id=category.id';
			$sql .= ' group by urid';
			switch($args){
				case 'post':	$sql .= ' order by posted desc, urid';
								break;
				case 'high':	$sql .= ' order by avg desc, urid';
								break;
				case 'low':		$sql .= ' order by avg, urid';
								break;
				default:		$sql .= ' order by posted desc, urid';
								break;
			}
			$conn = $this->db_connect();
			if($result = $conn->query($sql)){
				while ($row = $result->fetch_assoc()) {
					$rec[] = $row;
    			}
			}
			*/
			$sql = 'select *, truncate(avg((vote_1+vote_2+vote_3+vote_4)/4),1) as avg from userreview';
			$sql .= ' group by urid';
			switch($args){
				case 'post':	$sql .= ' order by posted desc, urid';
								break;
				case 'high':	$sql .= ' order by avg desc, urid';
								break;
				case 'low':		$sql .= ' order by avg, urid';
								break;
				default:		$sql .= ' order by posted desc, urid';
								break;
			}
			$conn = parent::db_connect();
			if($result = $conn->query($sql)){
				// アイテム情報検索のprepare
				$today = date('Y-m-d');
				$sql2 = 'select * from (catalog inner join item on item_id=item.id)';
				$sql2 .= ' inner join category on category_id=category.id';
				$sql2 .= ' where catalogapply<=? and catalogdate>? and itemapply<=? and itemdate>? and item_id=?';
				$sql2 .= ' group by item_id';
				$stmt = $conn->prepare($sql2);
				
				while ($row = $result->fetch_assoc()) {
					$ids = explode('|', $row['item_id']);	// 複数アイテムの場合
					$names = explode('|', $row['item_name']);
					for($i=0; $i<count($ids); $i++){
						if($ids[$i]==0){
							$row['category_id'][] = 0;		// 持込等
							$row['category_key'][] = '';
							$row['itemid'][] = 0;
							$row['item_code'][] = '';
							$row['itemname'][] = $names[$i];
						}else{
							$stmt->bind_param("ssssi", $today,$today,$today,$today,$ids[$i]);
							$stmt->execute();
							$stmt->store_result();
							$r = parent::fetchAll($stmt);
							if(count($r)>0){
								$row['category_id'][] = $r[0]['category_id'];
								$row['category_key'][] = $r[0]['category_key'];
								$row['itemid'][] = $ids[$i];
								$row['item_code'][] = $r[0]['item_code'];
								$row['itemname'][] = $r[0]['item_name'];
							}else{
								$row['category_id'][] = 0;	// 取扱中止
								$row['category_key'][] = '';
								$row['itemid'][] = 0;
								$row['item_code'][] = '';
								$row['itemname'][] = $names[$i];
							}
						}
					}
					$rec[] = $row;
    			}
    			$stmt->close();
			}
		}catch(Exception $e){
			$rec = array();
		}
		
		$result->free();
		$result->close();
		$conn->close();
		return $rec;
	}
	
	
	/**
	 *		アイテムレビュー情報
	 *		@args		{sort: ソート項目（新着順：post　評価の高い順：high　評価の低い順：low）,
	 *					itemid:	アイテムID}
	 *
	 *		return		[]
	 */
	public function getItemReview($args){
		try{
			$rec = array();
			$sql = "";
			if(empty($args['nodata'])){
				$sql .= 'select *, coalesce(item.item_name, itemreview.item_name) as item_name, ';
				$sql .= 'coalesce(item.item_code, "") as itemcode, coalesce(category_id, 0) as category_id, i_color_code';
			} else {
				$sql .= 'select coalesce(item.item_name, itemreview.item_name) as item_name ';
			}
			$sql .= ' from (((itemreview';
			$sql .= ' left join item on itemreview.item_id=item.id)';
			$sql .= ' left join catalog on item.id=catalog.item_id)';
			$sql .= ' left join category on catalog.category_id=category.id)';
			$sql .= ' left join itemdetail on item.item_code=itemdetail.item_code';
			$sql .= ' where itemreview.item_id=?';
			$sql .= ' group by irid';
			switch($args['sort']){
				case 'post':	$sql .= ' order by posted desc, irid';
								break;
				case 'high':	$sql .= ' order by vote desc, irid';
								break;
				case 'low':		$sql .= ' order by vote, irid';
								break;
				default:		$sql .= ' order by posted desc, irid';
								break;
			}
			$conn = parent::db_connect();
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("i", $args['itemid']);
			$stmt->execute();
			$stmt->store_result();
			$rec = parent::fetchAll($stmt);
			
		}catch(Exception $e){
			$rec = array();
		}
		
		$stmt->close();
		$conn->close();
		return $rec;
	}
	
}
?>