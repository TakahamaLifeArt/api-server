<?php
/**
 * Takahama Life Art 
 * API controller
 * @author <ks.desk@gmail.com>
 *
 */
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/calc.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/review.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/User.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/Delivery.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/Product.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/tag/CategoryTag.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/tag/ItemTag.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/Item.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/Itemdetail.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/Category.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/Maker.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/Measure.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/PrintType.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/Size.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/Tag.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/TagType.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/Printposition.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/Printpattern.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/PrintGroup.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/ItemGroup1.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/ItemGroup2.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/package/db/Website.php';
use package\User;
use package\Delivery;
use package\Product;
use package\tag\CategoryTag;
use package\tag\ItemTag;
use package\db\Item;
use package\db\Itemdetail;
use package\db\Category;
use package\db\Maker;
use package\db\Measure;
use package\db\PrintType;
use package\db\Size;
use package\db\Tag;
use package\db\TagType;
use package\db\Printposition;
use package\db\Printpattern;
use package\db\PrintGroup;
use package\db\ItemGroup1;
use package\db\ItemGroup2;
use package\db\Website;

try {

	$method = $_SERVER['REQUEST_METHOD'];
	if ('GET' === $method || 'PUT' === $method || 'POST' === $method || 'DELETE' === $method) {
		$headers = getallheaders();
		if (isset($headers[_HTTP_HEADER_KEY]) || isset($headers['X-Tla-Access-Token'])) {
			if (_ACCESS_TOKEN !== $headers[_HTTP_HEADER_KEY] && _ACCESS_TOKEN !== $headers['X-Tla-Access-Token']) {
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

		// 二重にURLデコードされているため、上書き
		$var = explode('&', $_SERVER['QUERY_STRING']);
		for ($i=0; $i<count($var); $i++) {
			$q = explode('=', $var[$i]);
			if ($q[0] !== 'm') continue;
			$param[$q[0]] = substr($var[$i], strpos($var[$i], '=')+1);
		}

		if (empty($param['r'])) {
			throw new Exception('418');
		}
		if (!empty($param['m'])) {
			$m = explode('/', $param['m']);
		}

		$domain = explode('.', $host);
		$hostName = $domain[0];
		if ($host == 'www.alesteq.com') {
			$db = _DB_NAME_DEV;
		} else if ($hostName !== 'test') {
			$db = _DB_NAME;
		} else {
			$db = _DB_NAME_DEV;
		}
		
		$pro = new Product($db);
		switch ($param['r']) {
			case 'masters':
				if (empty($m[0])) {
					throw new Exception('400');
				}
				
				// Class名を指定
				$className = 'package\db\\' . $m[0];
				if (class_exists($className) === false) {
					throw new Exception('404');
				}
				
				$master = new $className($db);
				
				if ($method==='GET') {
					$args = $param['args'] ?? [];
					
					if (empty($m[1])) {
						$res = $master->update(...$args);
					} else {
						$res = $master->{$m[1]}($args);
					}
				} else if ($method==='DELETE') {
					if (empty($m[1])) {
						throw new Exception('400');
					}
					$res = $master->delete($m[1]);
				} else {
					$args = $_REQUEST['args'] ?? [];
					if ($_REQUEST['_method'] == 'update') {
						$res = $master->update(...$args);
					} else {
						$res = $master->insert(...$args);
					}
				}
				break;
			case 'categories':
				if ($method==='GET') {
				/**
				 * /categories/商品カテゴリID/ソート指定{@code low|high|desc|asc|heavy|light}/取得レコード数制限{@code 'offset,length'}
				 */
					if (empty($m[0])) {
						if (empty($param['args'])) {
							$res = $pro->getCategory();		// マスターリスト
						} else {
							// タグによる絞り込み、カテゴリに依存しない
							if (empty($m[1])) $m[1] = '';
							if (empty($m[2])) $m[2] = '';
							$res = $pro->itemOfTag($param['args'], $m[1], $m[2]);
						}
					} else {
						// アイテム情報、タグによる絞り込みを含む
						$ary = $param['args'] ?? [];	// NULL合体演算子
						if (empty($m[1])) $m[1] = '';
						if (empty($m[2])) $m[2] = '';
						$res = $pro->itemOfCategory($m[0], $ary, $m[1], $m[2]);
					}
				}
				break;
			case 'itemtags':
				if ($method==='GET') {
				/**
				 * /itemtags/カテゴリーID/?args[]=タグID
				 */
					if (empty($m[0])) {
						if (empty($param['args'])) throw new Exception('400');
						$ids = $param['args'];
						$tag = new ItemTag($db);
					} else {
						if (empty($param['args'])) {
							$ids[] = $m[0];
						} else {
							array_unshift($param['args'], $m[0]);
							$ids = $param['args'];
						}
						$tag = new CategoryTag($db);
					}
					$res = $pro->getItemTag($tag, ...$ids);		// 可変長引数
				}
				break;
			case 'items':
				if ($method==='GET') {
				/**
				 * /items/アイテムID/{@code colors|sizes|costs|printpatterns|details}/商品カラーコード
				 */
					if (empty($m[0])) {
						throw new Exception('400');
					} else if (empty($m[1])) {
						// アイテムの基本情報
						$res = $pro->getItem($m[0]);
					} else {
						switch ($m[1]) {
							case 'colors':	// カラー展開
								$res = $pro->getItemColor($m[0]);
								break;
							case 'sizes':	// サイズ毎の単価
								$res = $pro->getSizePrice($m[0], $m[2]);
								break;
							case 'costs':	// サイズ毎の単価、量販単価に対応
								$amount = $param['args'] ?? 0;	// NULL合体演算子
								$res = $pro->getItemPrice($m[0], $m[2], $amount);
								break;
							case 'printpatterns':	// 絵型
								$res = $pro->getPrintPosition($m[0]);
								break;
							case 'details':	// 詳細
								$res = $pro->getItemDetail($m[0]);
								break;
							default:
								throw new Exception('404');
						}
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
				 * /printpatterns/アイテムID
				 */
				$res = $mst->getPrintposition($m[0]);
				break;
			case 'printcharges':
				$calc = new Calc();
				if ($method==='GET') {
				/**
				 * /printcharges/プリント方法
				 */
					if (empty($param['args'])) throw new Exception('400');
					$a = json_decode($param['args'], true);
					
					// インクジェット以外はアイテムID毎に枚数を合算
					foreach ($a['items'] as $itemId=>$ary) {
						$item[$itemId] = 0;
						if (is_array($ary)) {
							foreach ($ary as $amount) {
								$item[$itemId] += $amount;
							}
						} else {
							$item[$itemId] += intval($ary);
						}
					}
					
					switch ($m[0]) {
						case 'silk':
							$res = $calc->calcSilkPrintFee($a['amount'], $a['ink'], $item, $a['size'], $a['repeat']['silk']);
							break;
						case 'inkjet':
							$res = $calc->calcInkjetFee($a['option'], $a['amount'], $a['size'], $a['items']);
							break;
						case 'trans':
						case 'digit':
							$res = $calc->calcDigitFee($a['amount'], $a['size'], $item, $a['repeat']['digit']);
							break;
						case 'cutting':
							$res = $calc->calcCuttingFee($a['amount'], $a['size'], $item);
							break;
						case 'embroidery':
						case 'emb':
							$res = $calc->calcEmbroideryFee($a['option'], $a['amount'], $a['size'], $item, $a['repeat']['emb']);
							break;
						case 'recommend':	// おまかせプリント
							for ($i=0; $i<count($a['printable']); $i++) {
								switch ($a['printable'][$i]) {
									case 'silk':
										$tmp = $calc->calcSilkPrintFee($a['amount'], $a['ink'], $item, $a['size'], $a['repeat']['silk']);
										break;
									case 'inkjet':
										$tmp = $calc->calcInkjetFee($a['option'], $a['amount'], $a['size'], $a['items']);
										break;
									case 'trans':
									case 'digit':
										$tmp = $calc->calcDigitFee($a['amount'], $a['size'], $item, $a['repeat']['digit']);
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
				}
				break;
			case 'users':
				$user = new User($db);
				if ($method==='GET') {
				/**
				 * /users/メールアドレス
				 * /users/メールアドレス/パスワード
				 * /users/ユーザーID/sales
				 * /users/ユーザーID/pass
				 */
					if (empty($m[0])) throw new Exception('400');
					if (!ctype_digit(strval($m[0]))) {
						if (empty($m[1])) {
							$res = $user->isExistEmail($m[0]);
						} else {
							$res = $user->signIn($m[0], $m[1]);
						}
					} else {
						if (empty($m[1])) {
							if (empty($param['args'])) {
								$res = $user->getUser($m[0]);
							} else {
								$a = json_decode($param['args'], true);
								$a['id'] = $m[0];
								$res = $user->updateUser($a);		// TODO PUT
							}
						} else {
							if ($m[1]==='sales') {
								$res = $user->salesVolume($m[0]);
							} else if ($m[1]==='pass' && !empty($param['args'])) {
								$a = json_decode($param['args'], true);
								$a['id'] = $m[0];
								$res = $user->setPassword($a);		// TODO PUT
							} else {
								throw new Exception('400');
							}
						}
					}
				} else if ($method==='POST') {
				/**
				 * /users/pass/メールアドレス
				 */
					if ($m[0]==='pass' && !empty($m[1])) {
						$res = $user->resetPassword($m[1]);
					} else {
						throw new Exception('400');
					}
				}
				break;
			case 'taxes':
				if ($method==='GET') {
				/**
				 * /taxes
				 */
					$res = $pro->salesTax();	// 消費税率（int）
				}
				break;
			case 'delivery':
			case 'deliveries':
				if ($method==='GET') {
				/**
				 * /delivery/納期のtimestamp(sec)
				 * /delivery/
				 */
					$deli = new Delivery();
					if (empty($param['args'])) {
						$res = $deli->getWorkDay($m[0]);
					} else {
						for ($i=0, $len=count($param['args']['workday']); $i<$len; $i++) {
							$res[$i] = $deli->getDelidate((int)$param['args']['basesec'], (int)$param['args']['workday'][$i], (int)$param['args']['transport'], (int)$param['args']['extraday']);
						}
					}
				}
				break;
			case 'holidays':	// pending
				if ($method==='GET') {
				/**
				 * /holiday/timestamp(sec)
				 */
					$deli = new Delivery();
					$res = $deli->makeDateArray($m[0]);
				}
				break;
			case 'calendars':
				if ($method==='GET') {
				/**
				 * /calendar/year/month
				 */
					$deli = new Delivery();
					$res = $deli->getCalendar((int)$m[0], (int)$m[1]);
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
		header('Access-Control-Allow-Methods: POST, PUT, GET, OPTIONS');
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
