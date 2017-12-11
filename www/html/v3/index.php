<?php
/**
 * Takahama Life Art 
 * API controller
 * (c) 2017 ks.desk@gmail.com
 *
 */
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/calc.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/review.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/Product.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/tag/CategoryTag.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/tag/ItemTag.php';
use package\Product;
use package\tag\CategoryTag;
use package\tag\ItemTag;
try {

	$method = $_SERVER['REQUEST_METHOD'];
	if ('GET' === $method) {
		$headers = getallheaders();
		if (isset($headers[_HTTP_HEADER_KEY])) {
			if (_ACCESS_TOKEN !== $headers[_HTTP_HEADER_KEY]) {
				throw new Exception('401');
			}
		} else {
			throw new Exception('403');
		}

		if (($host = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST))===false) {
			throw new Exception('403');
		}
		if (in_array(strtolower($host), _MEMBER_HOSTS[_ACCESS_TOKEN])===false) {
			throw new Exception('401');
		}
		
		parse_str($_SERVER['QUERY_STRING'], $param);
		if (empty($param['r'])) {
			throw new Exception('418');
		}
		if (!empty($param['m'])) {
			$m = explode('/', $param['m']);
		}
		$mst = new Calc();
		$pro = new Product();
		switch ($param['r']) {
			case 'categories':
				/**
				 * 0: カテゴリーID
				 */
				if (empty($m[0])) {
					if (empty($param['args'])) {
						// マスターリスト
						$res = $pro->getCategory();
					} else {
						// タグによる絞り込み、カテゴリに依存しない
						$res = $pro->itemOfTag($param['args']);
					}
				} else {
					// アイテム情報、タグによる絞り込みを含む
					$ary = $param['args'] ?? [];
					$res = $pro->itemOfCategory($m[0], $ary);
				}
				break;
			case 'itemtags':
				/**
				 * 0: カテゴリーID
				 * tag[]: タグID
				 */
				if (empty($m[0])) {
					// カテゴリー指定なし
					if (empty($param['args'])) throw new Exception('400');
					$ids = $param['args'];
					$tag = new ItemTag();
				} else {
					// カテゴリー指定あり
					if (empty($param['args'])) {
						$ids[] = $m[0];
					} else {
						array_unshift($param['args'], $m[0]);
						$ids = $param['args'];
					}
					$tag = new CategoryTag();
				}
				$res = $pro->getItemTag($tag, ...$ids);
				break;
			case 'items':
				/**
				 * 0: アイテムID
				 * 1: リソース種類（カラー、単価、絵型、詳細）
				 * 2: カラーコード
				 */
				if (empty($m[0])) {
					// アイテムIDは必須
					throw new Exception('400');
				} else if (empty($m[1])) {
					// アイテムの基本情報
					$res = $mst->getItem($m[0], null, 'item');
				} else {
					switch ($m[1]) {
						case 'colors':
							// アイテムカラー
							$res = $pro->getItemColor($m[0]);
							break;
						case 'sizes':
							// サイズ毎の単価
							$res = $pro->getSizePrice($m[0], $m[2]);
							break;
						case 'printpatterns':
							// 絵型
							$res = $pro->getPrintPosition($m[0]);
							break;
						case 'details':
							// 詳細
							$res = $pro->getItemDetail($m[0]);
							break;
						default:
							throw new Exception('404');
					}
				}
				break;
			case 'itemreviews':	// pending
				/**
				 * 0: アイテムID
				 */
				$rev = new Review();
				$res = $rev->getItemReview(array('itemid'=>$m[0], 'sort'=>$m[1]));
				break;
			case 'printmethods':	// pending
				$res = $mst->getPrintMethod();
				break;
			case 'printpatterns':	// pending
				/**
				 * 0: アイテムID
				 */
				$res = $mst->getPrintposition($m[0]);
				break;
			case 'printcharges':
				/**
				 * 0: プリント方法
				 */
				if (empty($param['args'])) throw new Exception('400');
				$a = json_decode($param['args'], true);
				switch ($m[0]) {
					case 'silk':
						$res = $mst->calcSilkPrintFee($a['amount'], $a['ink'], $a['items'], $a['size'], $a['repeat']);
						break;
					case 'inkjet':
						$res = $mst->calcInkjetFee($a['option'], $a['amount'], $a['size'], $a['items']);
						break;
					case 'trans':
					case 'digit':
						$res = $mst->calcDigitFee($a['amount'], $a['size'], $a['items'], $a['repeat']);
						break;
					case 'cutting':
						$res = $mst->calcCuttingFee($a['amount'], $a['size'], $a['items']);
						break;
					case 'embroidery':
					case 'emb':
						$res = $mst->calcEmbroideryFee($a['option'], $a['amount'], $a['size'], $a['items'], $a['repeat']);
						break;
					case 'recommend':
						for ($i=0; $i<count($a['printable']); $i++) {
							switch ($a['printable'][$i]) {
								case 'silk':
									$tmp = $mst->calcSilkPrintFee($a['amount'], $a['ink'], $a['items'], $a['size'], $a['repeat']['silk']);
									break;
								case 'inkjet':
									$tmp = $mst->calcInkjetFee($a['option'], $a['amount'], $a['size'], $a['items']);
									break;
								case 'trans':
								case 'digit':
									$tmp = $mst->calcDigitFee($a['amount'], $a['size'], $a['items'], $a['repeat']['digit']);
									break;
							}
							
							// 最安値になるプリント方法を選択
							$tmp['method'] = $a['printable'][$i];
							if (empty($res)) {
								$res = $tmp;
							} else if ($res['tot'] > $tmp['tot']) {
								$res = $tmp;
							}
						}
						break;
				}
				break;
			default:
				throw new Exception('404');
		}
		$res = json_encode($res);
		header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
//		header('Access-Control-Allow-Credentials: true');
		header('Cache-Control: no-cache');
		header('Pragma: no-cache');
		header('HTTP/1.1 200 OK');
	} else if ('OPTIONS' === $method) {
		header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
		//		header('Access-Control-Allow-Credentials: true');
		header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
		header('Access-Control-Allow-Headers: '._HTTP_HEADER_KEY);
//		header('Access-Control-Max-Age: 86400');	// preflight request のレスポンスをキャッシュ（１日）
		header("Content-Length: 0");
		header('HTTP/1.1 200 OK '.$method);
//		header("Content-Type: text/plain");
	} else {
		throw new Exception('405');
	}
} catch (Exception $e) {
	$res = $e->getMessage();
	if ('' === $res) {
		header('HTTP/1.1 500 Internal Server Error');
	} else if ('405' === $res){
		header('HTTP/1.1 405 Method Not Allowed');
	} else if ('403' === $res) {
		header('HTTP/1.1 403 Forbidden');
	} else if ('401' === $res) {
		header('HTTP/1.1 401 Unauthorixed');
	} else if ('404' === $res) {
		header('HTTP/1.1 404 Not Found');
	} else if ('418' === $res) {
		header('HTTP/1.1 418 I\'m a teapot');
	} else {
		header('HTTP/1.1 400 Bad Request');
	}
}

//if (isset($param['callback'])) {
//	$res = $param['callback'].'('.$res.')';
//}

header("Content-Type: text/javascript; charset=utf-8");

echo $res;
