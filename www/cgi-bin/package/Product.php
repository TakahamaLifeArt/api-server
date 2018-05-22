<?php
/**
 * 商品クラス for API3
 * charset utf-8
 *--------------------
 * log
 * 2017-12-12 created
 * 2018-02-20 ソート指定に、生地の厚い順、生地の薄い順、レビューの少ない順を追加
 *			  取得するレコード数の制限（limit句）の指定を追加
 *--------------------
 *
 * getCategory		商品カテゴリー一覧
 * getItem			商品の基本情報
 * getItemLiset		商品一覧情報
 * itemOfCategory	カテゴリー内のアイテムを対象に商品タグによる絞り込み
 * itemOfTag		タグ名の一覧
 * getItemTag		タグ名の一覧
 * getItemColor		商品カラー
 * getSizePrice		サイズ展開と単価
 * getItemPrice		サイズ毎の単価、量販単価に対応
 * getPrintposition	プリント位置画像のパス情報とプリント個所名
 * getItemDetail	商品詳細情報
 * salesTax			消費税率を返す
 * validDate		日付の妥当性を確認
 */
declare(strict_types=1);
namespace package;
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/config.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/SqlManager.php';
use \Exception;
use package\db\SqlManager;
use package\tag\TagList;
class Product {
	
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
	
	
	public function __destruct(){
		$this->_sql->close();
	}
	
	
	/**
	 * 商品カテゴリー一覧
	 * @param {int} id アイテムID
	 * @return {array} [id:category_id, code:category_key, name:category_name]
	 */
	public function getCategory(): array {
		try{
			$query = "select category_id as id, category_key as code, category_name as name 
			from category inner join catalog on category.id=catalog.category_id 
			where catalogapply<=? and catalogdate>? group by category.id";
			$res = $this->_sql->prepared($query, "ss", array($this->_curDate, $this->_curDate));
		}catch(Exception $e){
			$res = array();
		}
		return $res;
	}
	
	
	/**
	 * 商品の基本情報
	 * @return {array} [id, code, name, position_id, maker_id, oz, surcharge_id, volumerange_id, silkscreen_id]
	 */
	public function getItem(int $id): array {
		try{
			$query = "select id, item_code as code, item_name as name, printposition_id as position_id,
			maker_id, oz, print_group_id as surcharge_id, item_group1_id as volumerange_id, item_group2_id as silkscreen_id 
			from item
			where id=? and itemapply<=? and itemdate>?";
			$res = $this->_sql->prepared($query, "iss", array($id, $this->_curDate, $this->_curDate));
		}catch(Exception $e){
			$res = array();
		}
		return $res;
	}
	
	
	/**
	 * 商品一覧情報
	 * カテゴリー{@code $id}又はアイテムID{@code $ary}のどちらかを指定する
	 * @param {int} id カテゴリID(default:0)
	 * @param {array} ary アイテムID
	 * @param {string} sort 表示順を指定、popular|''(人気), low(廉価), high(高価), desc(レビュー数降順), asc(レビュー数昇順), heavy(生地が厚い), light(生地が薄い)
	 * @param {string} limit 取得するレコード数を制限、{@code 'offset-length'}
	 * @return {array} 各アイテムのデータ
	 * [category_key, category_name, item_id, item_name, item_code, cost, pos_id, maker_id, brand_id, brand_name, 
	 *  oz, colors, i_color_code, i_caption, reviews, avg_votes, sizename_from, sizename_to, range_id, screen_id]
	 */
	private function getItemList(int $id=0, array $ary=array(), string $sort='popular', string $limit=''): array {
		try{
			if(empty($id) && count($ary)==0) throw new Exception();

			// 消費税率
			$tax = 0;
			
			// 内税の場合
			if (_TAX_CLASS == 2) {
				$tax = $this->salesTax();
				$tax /= 100;
			}
			
			$query = "select category_key, category_name, item.id as item_id, item_name, item.item_code as item_code, 
			min(truncate(price_1*margin_pvt*(1+".$tax.")+9,-1)) as cost, item.printposition_id as pos_id, 
			item_row, maker_id, oz, count(distinct(catalog.id)) as colors, i_color_code, i_caption, 
			tagid as brand_id, tag_name as brand_name,
			item_group1_id as range_id, item_group2_id as screen_id from (((((item
			 inner join catalog on item.id=catalog.item_id)
			 inner join category on catalog.category_id=category.id)
			 inner join itemprice on item.id=itemprice.item_id)
			 inner join itemdetail on item.item_code=itemdetail.item_code)
			 inner join itemtag on tag_itemid=item.id)
			 inner join tags on tag_id=tagid
			 where lineup=1 and color_lineup=1 and tag_type=7 and catalog.color_code!='000' and catalogapply<=? and catalogdate>? and 
			 itemapply<=? and itemdate>? and itempriceapply<=? and itempricedate>?";
			$marker = 'ssssss';
			$param = array_fill(0, 6, $this->_curDate);
			$l = count($ary);
			if ($l>0) {
				$query .= " and item.id in (".implode( ' , ', array_fill(0, $l, '?') ).")";
				$marker .= implode( '', array_fill(0, $l, 'i') );
				$param = array_merge($param, $ary);
			} else {
				$query .= " and category.id=?";
				$marker .= 'i';
				array_push($param, $id);
			}
			$query .= " group by item.id";
			
			if (!empty($limit)) {
				$args = explode('-', $limit);
				if (count($args) === 1) {
					$offset = 0;
					$length = $args[0];
				} else {
					$offset = $args[0];
					$length = $args[1];
				}
				$limit = " limit ".$offset.",".$length;
			}
			
			if ($sort == 'low') {
				$query .= " order by cost asc".$limit;
			} else if ($sort == 'high') {
				$query .= " order by cost desc".$limit;
			} else if ($sort == 'heavy') {
				$query .= " order by oz desc".$limit;
			} else if ($sort == 'light') {
				$query .= " order by oz asc".$limit;
			} else if (empty($sort) || $sort == 'popular') {
				$query .= " order by item_row".$limit;
			} else {
				$sortBy = $sort;
			}

			$res = $this->_sql->prepared($query, $marker, $param);
			$l = count($res);
			if ($l==0) throw new Exception();
			
			// itemreview count
			$queryReview = "select count(*) as review_count, avg(vote) as avg_votes from itemreview where item_id=?";
			
			// item size min,max,count
			$querySize = "select count(*) as sizes, max(size_row) as maxsize, min(size_row) as minsize from 
				size inner join itemsize on size_from=size.id where item_id=? and itemsizeapply<=? and itemsizedate>?";
			
//			$querySize = "select size_name from size inner join (
//				select max(size_row) as maxsize, min(size_row) as minsize from 
//				itemsize inner join size on size_from=size.id where item_id=? and itemsizeapply<=? and itemsizedate>?) as tmp 
//				on size.size_row=tmp.maxsize or size.size_row=minsize order by size_row";
			
			// size master
			$sizeMaster = $this->_sql->execQuery("select * from size");
			$sizeCount = count($sizeMaster);
			for ($i=0; $i<$sizeCount; $i++) {
				$sizeName[$sizeMaster[$i]['size_row']] = $sizeMaster[$i]['size_name'];
			}
			
			for ($i=0; $i<$l; $i++) {
				// review count
				$review = $this->_sql->prepared($queryReview, 'i', array($res[$i]['item_id']) );
				$res[$i]['reviews'] = $review[0]['review_count'];
				$res[$i]['avg_votes'] = $review[0]['avg_votes'];
				
				// size
				$size = $this->_sql->prepared($querySize, 'iss', array($res[$i]['item_id'], $this->_curDate, $this->_curDate) );
				$res[$i]['sizename_from'] = $sizeName[$size[0]['minsize']];
				$res[$i]['sizename_to'] = $sizeName[$size[0]['maxsize']];
				$res[$i]['sizes'] = $size[0]['sizes'];
				
//				$size = array_column($size, 'size_name');
//				$res[$i]['sizename_from'] = $size[0];
//				$res[$i]['sizename_to'] = $size[1] ?? $size[0];	// NULL合体演算子
			}
			
			if (!empty($sortBy)) {
				for($i=0; $i<count($res); $i++){
					$a[$i] = $res[$i]['reviews'];
				}
				if ($sortBy=='desc') {
					array_multisort($a, SORT_DESC, $res);
				} else {
					array_multisort($a, SORT_ASC, $res);
				}
				
				if (!empty($limit)) {
					$res = array_slice($res, (int)$offset, (int)$length);
				}
			}
		}catch(Exception $e){
			$res = array();
		}
		return $res;
	}


	/**
	 * カテゴリー内のアイテムを対象に商品タグによる絞り込み
	 * @param {int} id カテゴリーID
	 * @param {array} tag タグID
	 * @param {string} sort 表示順を指定、popular|null(人気), low(廉価), high(高価), desc(レビュー数降順), asc(レビュー数昇順), heavy(生地が厚い), light(生地が薄い)
	 * @param {string} limit 取得するレコード数を制限、{@code 'offset-length'}
	 * @return {array} 各アイテム情報, getItemListを呼び出す
	 */
	public function itemOfCategory(int $id, array $tag=array(), string $sort='', string $limit=''): array {
		try {
			if(empty($id)) throw new Exception();
			$l = count($tag);
			if ($l==0) {
				$res = $this->getItemList($id, [], $sort, $limit);
				return $res;
			}
			$query = "select item.id as itemid from ".implode('', array_fill(0, $l, '(') );
			$query .= "(item inner join catalog on item.id=catalog.item_id and category_id = ?)";
			$marker = 'i';
			array_unshift($tag, $id);
			for ($i=0; $i<$l; $i++) {
				$query .= " inner join (select tag_itemid as tmp{$i}id from itemtag where tag_id = ?) as tmp{$i} on item.id=tmp{$i}id)";
				$marker .= 'i';
			}
			$query .= " where itemapply<=? and itemdate>? group by item.id;";
			$marker .= 'ss';
			$param = array_merge($tag, array($this->_curDate, $this->_curDate));
			$rec = $this->_sql->prepared($query, $marker, $param);
			$ids = array_column($rec, 'itemid');
			$res = $this->getItemList(0, $ids, $sort, $limit);
		} catch (Exception $e) {
			$res = array();
		}
		return $res;
	}
	
	
	/**
	 * アイテムを商品タグで絞り込み
	 * @param {array} tag タグID
	 * @param {string} sort 表示順を指定、popular|null(人気), low(廉価), high(高価), desc(レビュー数降順), asc(レビュー数昇順), heavy(生地が厚い), light(生地が薄い)
	 * @param {string} limit 取得するレコード数を制限、{@code 'offset-length'}
	 * @return {array} 各アイテム情報, getItemListを呼び出す
	 */
	public function itemOfTag(array $tag, string $sort='', string $limit=''): array {
		try {
			if(empty($tag)) throw new Exception();
			$l = count($tag);
			$query = "select item.id as itemid from ".implode('', array_fill(0, $l, '(') );
			$query .= "item inner join itemtag on item.id=tag_itemid and tag_id = ?)";
			$marker = 'i';
			for ($i=1; $i<$l; $i++) {
				$query .= " inner join (select tag_itemid as tmp{$i}id from itemtag where tag_id = ?) as tmp{$i} on item.id=tmp{$i}id)";
				$marker .= 'i';
			}
			$query .= " where itemapply<=? and itemdate>? group by item.id;";
			$marker .= 'ss';
			$param = array_merge($tag, array($this->_curDate, $this->_curDate));
			$rec = $this->_sql->prepared($query, $marker, $param);
			$ids = array_column($rec, 'itemid');
			$res = $this->getItemList(0, $ids, $sort, $limit);
		} catch (Exception $e) {
			$res = array($e->getMessage());
		}
		return $res;
	}
	
	
	/**
	 * タグ名の一覧
	 * @param {Tag} tag Tagインターフェースを実装したクラスのインスタンス
	 * @param {int} ids 可変長引数リスト [カテゴリーID, タグID, ...]
	 * @return タグ情報の配列
	 */
	public function getItemTag(TagList $tag, int ...$ids): array {
		try {
			if (empty($ids)) throw new Exception();
			$l = count($ids);
			$cnt = 0;
			for ($i=0; $i<$l; $i++) {
				if (!ctype_digit(strval($ids[$i]))) {
					unset($ids[$i]);	// １０進数の数値以外を削除
					$cnt++;
				}
			}
			if ($cnt>0) {
				$l -= $cnt;
				if ($l==0) throw new Exception();
				array_values($ids);
			}
			$res = $tag->getTagList(...$ids);
		} catch (Exception $e) {
			$res = array();
		}

		return $res;
	}
	
	
	/**
	 * 商品カラー
	 * @param {int} id アイテムID
	 * @return {array} [id:color_id, code:color_code, name:colo_name, category:category_key]
	 */
	public function getItemColor(int $id): array {
		try{
			if(empty($id)) throw new Exception();
			$query = "select color_id as id, color_code as code, color_name as name, category_key as category from (catalog 
			inner join category on catalog.category_id=category.id)
			inner join itemcolor on color_id=itemcolor.id 
			where item_id=? and catalogapply<=? and catalogdate>? and color_lineup=1 order by color_code";
			$res = $this->_sql->prepared($query, "iss", array($id, $this->_curDate, $this->_curDate));
		}catch(Exception $e){
			$res = array();
		}

		return $res;
	}
	
	
	/**
	 * サイズ展開と単価
	 * @param {int} id アイテムID
	 * @param {string} color カラーコード
	 * @return {array} [master_id, id:size_id, name:size_name, cost:cost, series:size_series, stock:stock_volume,
	 * 					printarea_1,printarea_2,printarea_3,printarea_4,printarea_5,printarea_6,printarea_7]
	 *					costは最安単価
	 */
	public function getSizePrice(int $id, string $colorCode): array {
		try{
			if(empty($id)) throw new Exception();

			// 消費税率
			$tax = 0;

			// 内税の場合
			if (_TAX_CLASS == 2) {
				$tax = $this->salesTax();
				$tax /= 100;
			}

			$query = "select catalog.id as master_id, size.id as id, size_name as name, series, stock_volume as stock, 
			(case when color_id=59 then truncate(price_1*margin_pvt*(1+".$tax.")+9,-1) else truncate(price_0*margin_pvt*(1+".$tax.")+9,-1) end) as cost, 
			printarea_1,printarea_2,printarea_3,printarea_4,printarea_5,printarea_6,printarea_7 from (((catalog 
			inner join itemsize on catalog.size_series=itemsize.series) 
			inner join itemprice on itemprice.size_from=itemsize.size_from and itemprice.item_id=catalog.item_id) 
			inner join size on itemsize.size_from=size.id) 
			left join itemstock on catalog.id=stock_master_id and itemsize.item_id=stock_item_id and itemsize.size_from=stock_size_id 
			where catalog.item_id=? and catalog.color_code=? and catalogapply<=? and catalogdate>? and 
			itemsizeapply<=? and itemsizedate>? and itempriceapply<=? and itempricedate>? and 
			color_lineup=1 and itemsize.size_lineup=1 group by size.id order by size_row";
			$ary1 = array($id, $colorCode);
			$ary2 = array_fill(0, 6, $this->_curDate);
			$param = array_merge($ary1, $ary2);
			$res = $this->_sql->prepared($query, "isssssss", $param);
		}catch(Exception $e){
			$res = array();
		}

		return $res;
	}
	
	
	/**
	 * サイズ毎の商品価格
	 * @param {int} id アイテムID
	 * @param {string} colorCode アイテムカラーコード
	 * @param {int} amount 量販単価の判別 0-149枚、150-299枚、300枚以上
	 * @return {array} [id:size_id, name:size_name, cost:cost]
	 */
	public function getItemPrice(int $id, string $colorCode, int $amount=0): array {
		try{
			if(empty($id) || empty($colorCode)) throw new Exception();

			// 消費税率
			$tax = 0;

			// 内税の場合
			if (_TAX_CLASS == 2) {
				$tax = $this->salesTax();
				$tax /= 100;
			}
			
			if($amount>149){
				if($amount<300){
					$margin = _MARGIN_1;
				}else{
					$margin = _MARGIN_2;
				}
				$query = "select size.id as sizeid, size_name as name, 
				(case when color_id=59 then truncate(price_1 * ? * (1+".$tax.")+9,-1) else truncate(price_0 * ? * (1+".$tax.")+9,-1) end) as cost 
				from ((catalog 
				inner join itemsize on catalog.size_series=itemsize.series) 
				inner join itemprice on itemprice.size_from=itemsize.size_from and itemprice.item_id=catalog.item_id) 
				inner join size on itemsize.size_from=size.id 
				where catalog.item_id=? and catalog.color_code=? and catalogapply<=? and catalogdate>? and 
				itemsizeapply<=? and itemsizedate>? and itempriceapply<=? and itempricedate>? and 
				color_lineup=1 and itemsize.size_lineup=1 group by size.id order by null";
				$ary1 = array($margin, $margin, $id, $colorCode);
				$ary2 = array_fill(0, 6, $this->_curDate);
				$param = array_merge($ary1, $ary2);
				$res = $this->_sql->prepared($query, "ddisssssss", $param);
			}else{
				$query = "select size.id as sizeid, size_name as name, 
				(case when color_id=59 then truncate(price_1 * margin_pvt * (1+".$tax.")+9,-1) else truncate(price_0 * margin_pvt * (1+".$tax.")+9,-1) end) as cost 
				from ((catalog 
				inner join itemsize on catalog.size_series=itemsize.series) 
				inner join itemprice on itemprice.size_from=itemsize.size_from and itemprice.item_id=catalog.item_id) 
				inner join size on itemsize.size_from=size.id 
				where catalog.item_id=? and catalog.color_code=? and catalogapply<=? and catalogdate>? and 
				itemsizeapply<=? and itemsizedate>? and itempriceapply<=? and itempricedate>? and 
				color_lineup=1 and itemsize.size_lineup=1 group by size.id order by null";
				$ary1 = array($id, $colorCode);
				$ary2 = array_fill(0, 6, $this->_curDate);
				$param = array_merge($ary1, $ary2);
				$res = $this->_sql->prepared($query, "isssssss", $param);
			}
		}catch(Exception $e){
			$res = array();
		}

		return $res;
	}
	
	
	/**
	 * プリント位置画像のパス情報とプリント個所名
	 * @param {int} id アイテムID
	 * @return {array} [id: position_id, category:category_type, item:item_type, pos:position_type, front:個所名, back:個所名, side:個所名]
	 *					個所名はカンマ区切りのテキスト
	 */
	public function getPrintPosition(int $id): array {
		try{
			if(empty($id)) throw new Exception();
			
			$query = "select printposition.id as id, category_type as category, item_type as item, position_type as pos, 
			front.name_list as front, back.name_list as back, side.name_list as side from (((printposition 
			inner join item on item.printposition_id=printposition.id) 
			left join printpattern as front on frontface=front.id) 
			left join printpattern as back on backface=back.id) 
			left join printpattern as side on sideface=side.id where item.id=?";
			$res = $this->_sql->prepared($query, "i", array($id));
		}catch(Exception $e){
			$res = array();
		}

		return $res;
	}
	
	
	/**
	 * 商品詳細情報
	 * @param {int} id アイテムID
	 * @return {array} ['caption', 'description', 'material', 'silk', 'digit', 'inkjet', 'cutting', 'emb', 'note_title', 'note']
	 *					silk, digit, inkjet, cutting, emb は対応するプリント方法、１:対応する、０:対応しない
	 */
	public function getItemDetail(int $id): array {
		try{
			if (empty($id)) throw new Exception();
			
			$query = "select i_caption as caption, i_description as description, i_material as material, 
			i_silk as silk, i_digit as digit, i_inkjet as inkjet, i_cutting as cutting, i_embroidery as emb, 
			i_note_label as label, i_note as note from item 
			inner join itemdetail on item.item_code=itemdetail.item_code 
			where item.id=? and itemapply<=? and itemdate>?";
			$res = $this->_sql->prepared($query, "iss", array($id, $this->_curDate, $this->_curDate));
		}catch(Exception $e){
			$res = array();
		}

		return $res;
	}
	
	
	/**
	 * 消費税
	 * @return {int} 消費税率
	 */
	public function salesTax(): int {
		try {
			$query = "select taxratio from salestax where taxapply=(select max(taxapply) from salestax where taxapply<=?)";
			$r = $this->_sql->prepared($query, "s", array($this->_curDate));
			if (empty($r)) throw new Exception();
			$res = $r[0]['taxratio'];
		} catch (Exception $e) {
			$res = 0;
		}
		return $res;
	}
	
	
	/**
	* 日付の妥当性
	* @param {string} args 日付(0000-00-00)
	* @return {string} 日付(0000-00-00)。不正値の場合は今日の日付
	*/
	private function validDate(string $args): string {
		if (empty($args)) {
			$res = date('Y-m-d');
		} else {
			$res = str_replace("/", "-", $args);
			$d = explode('-', $res);
			if (checkdate($d[1], $d[2], $d[0])===false) {
				$res = date('Y-m-d');
			}
		}
		return $res;
	}
}
?>