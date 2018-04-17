<?php
/**
 * カテゴリー内のアイテムタグの一覧を取得
 */
declare(strict_types=1);
namespace package\tag;
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/tag/TagList.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/SqlManager.php';
use \Exception;
use package\db\SqlManager;
class CategoryTag implements TagList {
	
	private $_sql;		// データベースサーバーへの接続を表すオブジェクト
	
	
	/**
	 * param {string} db データベース名
	 */
	public function __construct(string $db) {
		$this->_sql = new SqlManager($db);
	}


	/**
	 * アイテムタグの一覧を取得
	 * @param {int} ids 可変長引数[カテゴリーID, タグID, ...] 配列の先頭にカテゴリーID, インデックス１以降はタグID
	 * @return {array} 該当するアイテムタグ情報
	 */
	public function getTagList(int ...$ids): array {
		try {
			if (empty($ids)) throw new Exception();
			
			$query = "select tag_itemid, tag_id, tag_name, tag_type, tag_order, tagtype_key, tagtype_name from ";
			$l = count($ids)-1;
			$query .= implode('', array_fill(0, $l, '(') );
			$query .= "(((itemtag
					 inner join item on tag_itemid=item.id and itemdate='3000-01-01')
					 inner join catalog on item.id=catalog.item_id and category_id = ?)";

			$marker = 'i';
			for ($i=0; $i<$l; $i++) {
				$query .= " inner join (select tag_itemid as tmp{$i}id from itemtag where tag_id = ?) as tmp{$i} on itemtag.tag_itemid=tmp{$i}id)";
				$marker .= 'i';
			}

			$query .= " inner join tags on itemtag.tag_id=tags.tagid)
				 inner join tagtype on tags.tag_type=tagtype.tagtypeid
				 group by tag_id order by tag_order";

			$res = $this->_sql->prepared($query, $marker, $ids);
		} catch (Exception $e) {
			$res = array();
		}

		return $res;
	}
}
?>