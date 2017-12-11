<?php
/**
 * アイテムタグの一覧を取得するインターフェース
 */
declare(strict_types=1);
namespace package\tag;
interface TagList {
	
	/**
	 * アイテムタグの一覧を取得
	 * @param {int} ids 検索するIDの可変長引数リスト
	 * @return {array} 該当するアイテムタグ情報
	 */
	public function getTagList(int ...$ids): array;
}
?>