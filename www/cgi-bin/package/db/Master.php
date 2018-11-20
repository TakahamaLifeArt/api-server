<?php
/**
 * マスターデータのインターフェース for API3
 * charset utf-8
 *--------------------
 * log
 * 2018-11-20 created
 *
 *--------------------
 *
 * update 更新
 * insert 新規登録
 * delete 削除
 */
declare(strict_types=1);
namespace package\db;
interface Master {
	
	/**
	 * 更新
	 * @param {array} args 更新データの可変長引数リスト
	 * @return {boolean} Returns FALSE on failure. For successful will return TRUE.
	 */
	public function update(...$args): bool;
	
	/**
	 * 新規登録
	 * @param {array} args 登録データの可変長引数リスト
	 * @return {boolean} Returns FALSE on failure. For successful will return TRUE.
	 */
	public function insert(...$args): bool;
	
	/**
	 * 削除
	 * @param key 削除データの primary ID、または unique ID
	 * @return {boolean} Returns FALSE on failure. For successful will return TRUE.
	 */
	public function delete($key): bool;

}
?>