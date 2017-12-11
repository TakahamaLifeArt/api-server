<?php
/**
 * 商品クラス for API3
 * charset utf-8
 *--------------------
 *
 * getCategory		商品カテゴリー一覧
 * getItemLiset		商品一覧情報
 * itemOfCategory	カテゴリー内のアイテムを対象に商品タグによる絞り込み
 * itemOfTag		タグ名の一覧
 * getItemTag		タグ名の一覧
 * getItemColor		商品カラー
 * getSizePrice		サイズ展開と単価
 * getPrintposition	プリント位置画像のパス情報とプリント個所名
 * getItemDetail	商品詳細情報
 * salesTax			商品単価に使用する消費税率を返す
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
	
	public function __construct(string $curDate=''){
		$this->curDate = $this->validDate($curDate);
		$this->_sql = new SqlManager();
	}
	
	
	public function __destruct(){
		$this->_sql->close();
	}
	
	
	/**
	 * 商品カテゴリー一覧
	 * @return {array} [id:category_id, code:category_key, name:category_name]
	 */
	public function getCategory(): array {
		try{
			$query = "select category_id as id, category_key as code, category_name as name 
			from category inner join catalog on category.id=catalog.category_id 
			where catalogapply<=? and catalogdate>? group by category.id";
			$res = $this->_sql->prepared($query, "ss", array($this->curDate, $this->curDate));
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
	 * @return {array} 各アイテムのデータ 
	 * [category_key, category_name, item_id, item_name, item_code, cost, pos_id, maker_id, 
	 *  oz, colors, i_color_code, i_caption, reviews, sizename_from, sizename_to, range_id, screen_id]
	 */
	private function getItemList(int $id=0, array $ary=array()): array {
		try{
			if(empty($id) && count($ary)==0) throw new Exception();

			$tax = $this->salesTax();
			$tax /= 100;

			$query = "select category_key, category_name, item.id as item_id, item_name, item.item_code as item_code, 
			min(truncate(price_1*margin_pvt*(1+".$tax.")+9,-1)) as cost, item.printposition_id as pos_id, 
			item_row, maker_id, oz, count(distinct(catalog.id)) as colors, i_color_code, i_caption, 
			item_group1_id as range_id, item_group2_id as screen_id from (((item
			 inner join catalog on item.id=catalog.item_id)
			 inner join category on catalog.category_id=category.id)
			 inner join itemprice on item.id=itemprice.item_id)
			 inner join itemdetail on item.item_code=itemdetail.item_code
			 where lineup=1 and color_lineup=1 and catalog.color_code!='000' and catalogapply<=? and catalogdate>? and 
			 itemapply<=? and itemdate>? and itempriceapply<=? and itempricedate>?";
			$marker = 'ssssss';
			$param = array_fill(0, 6, $this->curDate);
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
			$query .= " group by item.id order by item_row";

			$res = $this->_sql->prepared($query, $marker, $param);
			$l = count($res);
			if ($l==0) throw new Exception();
			
			// itemreview count
			$queryReview = "select count(*) as review_count from itemreview where item_id=?";
			
			// item size min,max
			$querySize = "select size_name from size inner join (
				select max(size_row) as maxsize, min(size_row) as minsize from 
				itemsize inner join size on size_from=size.id where item_id=? and itemsizeapply<=? and itemsizedate>?) as tmp 
				on size.size_row=tmp.maxsize or size.size_row=minsize order by size_row";
			
			for ($i=0; $i<$l; $i++) {
				// review count
				$review = $this->_sql->prepared($queryReview, 'i', array($res[$i]['item_id']) );
				$res[$i]['reviews'] = $review[0]['review_count'];
				
				// size
				$size = $this->_sql->prepared($querySize, 'iss', array($res[$i]['item_id'], $this->curDate, $this->curDate) );
				$size = array_column($size, 'size_name');
				$res[$i]['sizename_from'] = $size[0];
				$res[$i]['sizename_to'] = $size[1] ?? $size[0];	// NULL合体演算子
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
	 * @return {array} 各アイテム情報, getItemListを呼び出す
	 */
	public function itemOfCategory(int $id, array $tag=array()): array {
		try {
			if(empty($id)) throw new Exception();
			$l = count($tag);
			if ($l==0) {
				$res = $this->getItemList($id);
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
			$param = array_merge($tag, array($this->curDate, $this->curDate));
			$rec = $this->_sql->prepared($query, $marker, $param);
			$ids = array_column($rec, 'itemid');
			$res = $this->getItemList(0, $ids);
		} catch (Exception $e) {
			$res = array();
		}
		return $res;
	}
	
	
	/**
	 * アイテムを商品タグで絞り込み
	 * @param {array} tag タグID
	 * @return {array} 各アイテム情報, getItemListを呼び出す
	 */
	public function itemOfTag(array $tag): array {
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
			$param = array_merge($tag, array($this->curDate, $this->curDate));
			$rec = $this->_sql->prepared($query, $marker, $param);
			$ids = array_column($rec, 'itemid');
			$res = $this->getItemList(0, $ids);
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
	 * @return {array} [id:color_id, code:color_code, name:color_name]
	 */
	public function getItemColor(int $id): array {
		try{
			if(empty($id)) throw new Exception();
			$query = "select color_id as id, color_code as code, color_name as name from catalog inner join itemcolor on color_id=itemcolor.id 
			where item_id=? and catalogapply<=? and catalogdate>? and color_lineup=1 order by color_code";
			$res = $this->_sql->prepared($query, "iss", array($id, $this->curDate, $this->curDate));
		}catch(Exception $e){
			$res = array();
		}

		return $res;
	}
	
	
	
	/**
	 * サイズ展開と単価
	 * @param {int} id アイテムID
	 * @param {string} color カラーコード
	 * @return {array} [master_id, id:size_id, name:size_na me, cost:cost, series:size_series, stock:stock_volume,
	 * 					printarea_1,printarea_2,printarea_3,printarea_4,printarea_5,printarea_6,printarea_7]
	 *					costは最安単価
	 */
	public function getSizePrice(int $id, string $colorCode): array {
		try{
			if(empty($id)) throw new Exception();

			$tax = $this->salesTax();
			$tax /= 100;

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
			$ary2 = array_fill(0, 6, $this->curDate);
			$param = array_merge($ary1, $ary2);
			$res = $this->_sql->prepared($query, "isssssss", $param);
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
			$res = $this->_sql->prepared($query, "iss", array($id, $this->curDate, $this->curDate));
		}catch(Exception $e){
			$res = array();
		}

		return $res;
	}
	
	
	/**
	 * 商品単価に使用する消費税率
	 * @return {int} 消費税
	 */
	private function salesTax(): int {
		try {
			if (_TAX_CLASS < 2) return 0;	// 非課税と外税の場合は消費税 0%
			$query = "select taxratio from salestax where taxapply=(select max(taxapply) from salestax where taxapply<=?)";
			$r = $this->_sql->prepared($query, "s", array($this->curDate));
			if (empty($r)) throw new Exception();
			$res = $r[0]['taxratio'];
		} catch (Exception $e) {
			$res = 0;
		}
		return $res;
	}
	
	
	/**
	* 日付の妥当性
	* @param {string} curdate 日付(0000-00-00)
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