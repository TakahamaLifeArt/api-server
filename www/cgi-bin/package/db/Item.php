<?php
/**
 * アイテムクラス for API3
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
 * validDate 日付の妥当性を確認
 */
declare(strict_types=1);
namespace package\db;
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/config.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/SqlManager.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/Master.php';
use \Exception;
use package\db\SqlManager;
class Item implements Master {
	
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
	 * 更新
	 * @param {array} args 更新データの可変長引数リスト
	 *  item
	 *  itemsize
	 *  itemprice
	 *  catalog
	 * @return {boolean} Returns FALSE on failure. For successful will return TRUE.
	 */
	public function update(...$args): bool
	{
		try {
			$res = true;
			if (empty($args)) throw new Exception();
			
			$apply = $args[7] ?: $this->_curDate;
			
			$sql = "select item.id as itemId, itemdate from item inner join catalog on item.id=item_id";
			$sql .= " inner join category on category_id=category.id";
			$sql .= " where item_code=? and category_key=? and itemapply<=? and itemdate>? group by item.id";
			$rec = $this->_sql->prepared($sql, "ssss", array($args[2], $args[0], $apply, $apply));
			if (empty($rec)) throw new Exception();
			
			$itemId = $rec[0]['itemId'];
			$itemDate = $args[6] ?: $rec[0]['itemdate'];
			
			// item
			$sql = "update item set item_name=?, item_code=?, maker_id=?, lineup=?, item_row=?, itemdate=?, ";
			$sql .= "opp=?, oz=?, show_site=?, printposition_id=?, print_group_id=?, item_group1_id=?, item_group2_id=? where id=?";
			$debug = $this->_sql->prepared($sql, "ssiiisissiiiii", 
										 array(
											 $args[1], $args[2], $args[3], $args[4], $args[5], $itemDate, 
											 $args[8], $args[9], $args[10], $args[11], $args[12], $args[13], $args[14], 
											 $itemId,
										 )
										);


			// itemsize
			// $args[15]
			$addSeries = $args[15];
			$sizeSeries = [];
			
			// 既存のitemsizeデータを取得
			$sql = "select * from itemsize ";
			$sql .= "where item_id=? and itemsizeapply<=? and itemsizedate>? order by series, size_from";
			$tmp = $this->_sql->prepared($sql, "iss", array($itemId, $apply, $apply));
			if (!empty($tmp)) {
				// サイズシリーズをキーにしたハッシュを生成
				for ($i=0, $len=count($tmp); $i<$len; $i++) {
					$itemSize[ $tmp[$i]['series'] ][] = $tmp[$i];
				}

				$idx = -1;
				foreach ($itemSize as $series => $v) {
					$idx++;
					$sizeSeries[] = $series;
					
					if (isset($args[15][$idx]) === false) {
						// 既存のサイズシリーズに対応するデータが無い場合、当該シリーズを全て取扱中止
						$sql = "update itemsize set itemsizedate=? where item_id=? and series=?";
						$this->_sql->prepared($sql, "sii", array($apply, $itemId, $series));
						continue;
					}

					// 更新ずみデータ
					unset($addSeries[$idx]);

					// 既存シリーズのサイズIDをキーにしたハッシュを生成
					$existSize = array_column($v, null, 'size_from');

					for ($n=0, $len=count($args[15][$idx]['size_id']); $n<$len; $n++) {
						// サイズ毎に処理
						$sizeId = $args[15][$idx]['size_id'][$n];
						$stop = $args[15][$idx]['stop'][$n] ? date("Y-m-d", strtotime($args[15][$idx]['stop'][$n]." +1 day")): '3000-01-01';

						if (isset($existSize[$sizeId])) {
							// 既存サイズあり

							if ($existSize[$sizeId]['itemsizedate'] == $stop) continue;

							// 取扱中止日に変更あり
							$sql = "update itemsize set itemsizedate=? where id=?";
							$this->_sql->prepared($sql, "si", array($stop, $existSize[$sizeId]['id']));

						} else if ($i === 0) {
							// 既存サイズなし

							$sql = "insert into itemsize (id, item_id, series, size_from, size_to, itemsizeapply, itemsizedate) ";
							$sql .= "values(null,?,?,?,?,?,?)";
							$this->_sql->prepared($sql, "iiiiss", array($itemId, $series, $sizeId, $sizeId, $apply, $stop));
						}
					}
				}
			}
			
			// 追加のシリーズあり
			if (!empty($addSeries)) {
				$tmp = $this->_sql->execQuery('select max(series) as max_series from itemsize');
				if (empty($tmp)) throw new Exception();
				$series = $tmp[0]['max_series'];
				
				$sql = "insert into itemsize (id, item_id, series, size_from, size_to, itemsizeapply, itemsizedate) values(null,?,?,?,?,?,?)";
				foreach ($addSeries as $v) {
					$series++;
					$sizeSeries[] = $series;
					for ($i=0, $len=count($v['size_id']); $i<$len; $i++) {
						$this->_sql->prepared($sql, "iiiiss", 
											  array(
												  $itemId, $series, $v['size_id'][$i], $v['size_id'][$i], $apply,
												  $v['stop'][$i] ? date("Y-m-d", strtotime($v['stop'][$i]." +1 day")): '3000-01-01',
											  )
											 );
					}
				}
			}


			// itemprice
			//	price_0:白以外の単価
			//	price_1:白色単価
			//	$args[16][ size_id=>[ price=>[price_0, price_1], stop=>] ... ]
			
			// 既存のitempriceデータ取得
			$sql = "select * from itemprice ";
			$sql .= "where item_id=? and itempriceapply<=? and itempricedate>? order by size_from";
			$tmp = $this->_sql->prepared($sql, "iss", array($itemId, $apply, $apply));
			if (empty($tmp)) {
				$itemPrice = [];
			} else {
				// サイズIDをキーにしたハッシュを生成
				$itemPrice = array_column($tmp, null, 'size_from');
			}
			
			foreach ($args[16] as $sizeId => $v) {
				// 単価
				$price_0 = max($v['price']);
				$price_1 = min($v['price']);
				
				// 取扱中止日
				$idx = array_search('', $v['stop']);
				if ($idx !== false) {
					$stop = '3000-01-01';
				} else {
					$stop = max($v['stop']);
					$stop = date("Y-m-d", strtotime($stop." +1 day"));
				}
				
				if (isset($itemPrice[$sizeId])) {
					// 既存サイズあり
					if ($itemPrice[$sizeId]['price_0'] != $price_0 || $itemPrice[$sizeId]['price_1'] != $price_1) {
						// 金額の変更は新規レコードで書き換え
						$sql = "update itemprice set itempricedate=? where id=?";
						$this->_sql->prepared($sql, "si", array($apply, $itemPrice[$sizeId]['id']));
						
						$sql = "insert into itemprice (id, item_id, size_from, size_to, price_0, price_1, itempriceapply, itempricedate) ";
						$sql .= "values(null,?,?,?,?,?,?,?)";
						$this->_sql->prepared($sql, "iiiiiss", 
											  array($itemPrice[$sizeId]['item_id'], $sizeId, $sizeId, $price_0, $price_1, $apply, $stop)
											 );
					} else {
						// 取扱中止日を更新
						$sql = "update itemprice set itempricedate=? where id=?";
						$this->_sql->prepared($sql, "si", array($stop, $itemPrice[$sizeId]['id']));
					}
				} else {
					// 追加サイズあり
					$sql = "insert into itemprice (id, item_id, size_from, size_to, price_0, price_1, itempriceapply, itempricedate) ";
					$sql .= "values(null,?,?,?,?,?,?,?)";
					$this->_sql->prepared($sql, "iiiiiss", 
										  array($itemPrice[$sizeId]['item_id'], $sizeId, $sizeId, $price_0, $price_1, $apply, $stop)
										 );
				}
			}
			
			// サイズを取扱中止
			$remove = array_diff_key($itemPrice, $args[16]);
			if (!empty($remove)) {
				foreach ($remove as $sizeId => $v) {
					$sql = "update itemprice set itempricedate=? where id=?";
					$this->_sql->prepared($sql, "si", array($apply, $v['id']));
				}
			}


			// カラー名をキーにしたitemcolorのハッシュ
			$colors = $this->_sql->execQuery('select * from itemcolor');
			if (empty($colors)) throw new Exception();
			$colorNames = array_column($colors, null, 'color_name');	// ['color_name' => [id, color_name, inkjet_option], ...]

			// カテゴリーIDを取得
			$sql2 = "select id from category where category_key=?";
			$category = $this->_sql->prepared($sql2, "s", array($args[0]));
			if (empty($category)) throw new Exception();
			$categoryId = $category[0]['id'];

			// catalog
			// $args[17][ [カラーコード, カラー名, 当該カラーに対応するパターンのインデックス, 取扱最終日], ... ]
			$sql = "select * from catalog where item_id=? and catalogapply<=? and catalogdate>?";
			$tmp = $this->_sql->prepared($sql, "iss", array($itemId, $apply, $apply));
			
			if (empty($tmp)) {
				$catalog = [];
			} else {
				// カラーコードをキーにしたハッシュを生成
				$catalog = array_column($tmp, null, 'color_code');
			}
			
			for ($i=0, $len=count($args[17]); $i<$len; $i++) {
				$v = $args[17][$i];

				// 取扱中止日
				if (empty($v['stop'])) {
					$stop = '3000-01-01';
				} else {
					$stop = date("Y-m-d", strtotime($v['stop']." +1 day"));
				}

				// 既存のアイテムカラー名の有無を検証し、無い場合は新規登録
				if (isset($colorNames[ $v['name'] ])) {
					$colorId = $colorNames[ $v['name'] ]['id'];
				} else {
					$sql2 = "insert into itemcolor (id, color_name, inkjet_option) values(null, ?, 0)";
					$insertColorId = $this->_sql->prepared($sql2, "s", array($v['name']));
					if (empty($insertColorId)) throw new Exception();
					$colorId = $insertColorId[0];
				}
				
				if (isset($catalog[ $v['code'] ])) {
					// 既存データあり
					
					if ($catalog[ $v['code'] ]['color_id'] != $colorId || $catalog[ $v['code'] ]['size_series'] != $sizeSeries[ $v['series'] ]) {
						// カラー名またはサイズシリーズの変更は新規レコードで書き換え
						$sql = "update catalog set catalogdate=? where id=?";
						$this->_sql->prepared($sql, "si", array($apply, $catalog[ $v['code'] ]['id']));

						$sql = "insert into catalog (id, category_id, item_id, color_code, color_id, size_series, catalogapply, catalogdate)";
						$sql .= " values(null,?,?,?,?,?,?,?)";
						$this->_sql->prepared($sql, "iisiiss", 
											  array($categoryId, $itemId, $v['code'], $colorId, $sizeSeries[ $v['series'] ], $apply, $stop)
											 );
					} else {
						// 取扱中止日を更新
						$sql = "update catalog set catalogdate=? where id=?";
						$this->_sql->prepared($sql, "si", array($stop, $catalog[ $v['code'] ]['id']));
					}
				} else {
					// 追加カラーあり
					$sql = "insert into catalog (id, category_id, item_id, color_code, color_id, size_series, catalogapply, catalogdate)";
					$sql .= " values(null,?,?,?,?,?,?,?)";
					$this->_sql->prepared($sql, "iisiiss", 
										  array($categoryId, $itemId, $v['code'], $colorId, $sizeSeries[ $v['series'] ], $apply, $stop)
										 );
				}
			}

			// カラーを取扱中止
			$tmp = array_column($args[17], null, 'code');
			$remove = array_diff_key($catalog, $tmp);
			if (!empty($remove)) {
				foreach ($remove as $colorCode => $v) {
					$sql = "update itemprice set itempricedate=? where id=?";
					$this->_sql->prepared($sql, "si", array($apply, $v['id']));
				}
			}

		} catch (Exception $e) {
			$res = false;
		}
		return $res;
	}

	/**
	 * 新規登録
	 * @param {array} args 登録データの可変長引数リスト
	 *  item
	 *  itemsize
	 *  itemprice
	 *  catalog
	 * @return {boolean} Returns FALSE on failure. For successful will return TRUE.
	 */
	public function insert(...$args): bool
	{
		try {
			$res = true;
			if (empty($args)) throw new Exception();
			
			// item
			$sql = "insert into item (id, item_name, item_code, maker_id, lineup, item_row, itemapply, opp, oz, show_site, ";
			$sql .= "printposition_id, print_group_id, item_group1_id, item_group2_id) ";
			$sql .= "values(null,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
			$insertItemId = $this->_sql->prepared($sql, "ssiiisissiiii", 
								  array(
									  $args[1], $args[2], $args[3], $args[4], $args[5],
									  $args[6], $args[7], $args[8], $args[9],
									  $args[10], $args[11], $args[12], $args[13],
								  )
								 );
			if (empty($insertItemId)) throw new Exception();
			$itemId = $insertItemId[0];
			
			// itemsize
			//	サイズ -> $args[14][ [サイズID, ...], ... ]
			$maxSeries = $this->_sql->execQuery('select max(series) as max_series from itemsize');
			if (empty($maxSeries)) throw new Exception();
			$series = $maxSeries[0] += 1;
			$pattern = [];
			$sql = "insert into itemsize (id, item_id, series, size_from, size_to, itemsizeapply) values(null,?,?,?,?,?)";
			// サイズパターン毎
			for ($i=0, $len=count($args[14]); $i<$len; $i++) {
				$pattern[] = $series;
				// サイズ毎
				for ($n=0, $seriesLen=count($args[14][$i]); $n<$seriesLen; $n++) {
					$this->_sql->prepared($sql, "iiiis", 
									  array(
										  $itemId,
										  $series,
										  $args[14][$i][$n],
										  $args[14][$i][$n],
										  $args[6],
									  )
									 );
				}
				$series++;
			}
			
			// itemprice
			//	price_0:白以外の単価
			//	price_1:白色単価
			//	$args[15][ size_id=>[price_0, price_1], ... ]
			$sql = "insert into itemprice (id, item_id, size_from, size_to, price_0, price_1, itempriceapply) values(null,?,?,?,?,?,?)";
			foreach ($args[15] as $sizeId => $price) {
				$price_0 = max($price);
				$price_1 = min($price);
				$this->_sql->prepared($sql, "iiiiis", array( $itemId, $sizeId, $sizeId, $price_0, $price_1, $args[6]) );
			}
			
			// カラー名をキーにしたitemcolorのハッシュ
			$colors = $this->_sql->execQuery('select * from itemcolor');
			if (empty($colors)) throw new Exception();
			$colorNames = array_column($colors, null, 'color_name');	// ['color_name' => [id, color_name, inkjet_option], ...]
			
			// カテゴリーIDを取得
			$sql2 = "select id from category where category_key=?";
			$category = $this->_sql->prepared($sql2, "s", array($args[0]));
			if (empty($category)) throw new Exception();
			$categoryId = $category[0]['id'];
			
			// catalog
			//	$args[16][ [カラーコード, カラー名, 当該カラーに対応するパターンのインデックス], ... ]
			$sql = "insert into catalog (id, category_id, item_id, color_code, color_id, size_series, catalogapply) ";
			$sql .= "values(null,?,?,?,?,?,?)";
			for ($i=0, $len=count($args[16]); $i<$len; $i++) {
				// 既存のカラー名の有無を検証し、無い場合は新規登録
				if (isset($colorNames[ $args[16][$i][1] ])) {
					$colorId = $colorNames[ $args[16][$i][1] ]['id'];
				} else {
					$sql2 = "insert into itemcolor (id, color_name, inkjet_option) values(null, ?, 0)";
					$insertColorId = $this->_sql->prepared($sql2, "s", array($args[16][$i][1]));
					if (empty($insertColorId)) throw new Exception();
					$colorId = $insertColorId[0];
				}
				
				$this->_sql->prepared($sql, "iisiis", 
									  array( $categoryId, $itemId, $args[16][$i][0], $colorId, $pattern[ $args[16][$i][2] ], $args[6])
									 );
			}
		} catch (Exception $e) {
			$res = false;
		}
		return $res;
	}

	/**
	 * 削除
	 * @param key 削除データの primary ID、または unique ID
	 * @return {boolean} Returns FALSE on failure. For successful will return TRUE.
	 */
	public function delete($key): bool
	{
		try {
			$res = true;
			if (empty($key)) throw new Exception();
			
		} catch (Exception $e) {
			$res = false;
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
			if (checkdate((int)$d[1], (int)$d[2], (int)$d[0])===false) {
				$res = date('Y-m-d');
			}
		}
		return $res;
	}
}
?>