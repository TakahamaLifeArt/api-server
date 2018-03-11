<?php
/*
 * API controller
 * @author <ks.desk@gmail.com>
 */
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/api/Composer.php';

try {
	$headers = getallheaders();
	
//	if (isset($headers["Authorization"])) {
//		if (_ACCESS_TOKEN !== $headers["Authorization"]) {
//			throw new Exception('トークンが不正です。');
//		}
//	} else {
//		throw new Exception('アクセスキーを指定してください。');
//	}
	
//	$headers = array();
//	foreach($_SERVER as $h=>$v){
//		if(preg_match('/^(X_HTTP|HTTP)_(.+)/',$h,$hp)) {
//			$headers[$hp[1]]=$v;
//			$hoge_key = $hp[1];
//			$hoge_val = $v;
//		}
//	}
	
	$method = $_SERVER['REQUEST_METHOD'];
	if ('GET' === $method) {
		if (isset($headers[_HTTP_HEADER_KEY])) {
			if (_ACCESS_TOKEN !== $headers[_HTTP_HEADER_KEY]) {
				throw new Exception('401');
			}
		} else {
			throw new Exception('403');
		}
		
		parse_str($_SERVER['QUERY_STRING'], $param);
		if ($param['ver'] != 'v1') {
			throw new Exception('バージョンが不正です。');
		}
		$resource = explode('/', $param['resource']);
		switch ($resource[0]) {
			case 'calendar':
				if (isset($param['y'], $param['m'])) {
					$y = $param['y'];
					$m = $param['m'];
				} else if (empty($resource[1])) {
					$y = date('Y');
					$m = date('n');
				} else {
					$y = $resource[1];
					$m = $resource[2];
				}
				if (checkdate($m, 1, $y)===false) {
					throw new Exception('年月の指定が不正です。');
				}
				if (!isset($param['me'])) {
					$me = array();
				} else {
					$me = $param['me'];
					$me['dayOff'] = json_decode($me['dayOff']);
					$me['holiday'] = json_decode($me['holiday'], true);
				}
				$res = Composer::showCalendar($y, $m, $me);
				if (is_string($res)) {
					throw new Exception($res);
				}
				$res = json_encode($res);
				break;
			case 'categories':
				if (empty($resource[1])) {
					$compo = new Composer();
					$res = $compo->getCategory();
				} else {
					if (preg_match('/^[1-9][0-9]?$/', $resource[1])) {
						$compo = new Composer();
						$res = $compo->getCategory($resource[1]);
					} else {
						throw new Exception('カテゴリIDの指定が不正です。');
					}
				}
				break;
			case 'items':
				if (empty($resource[1])) {
					throw new Exception('リソースの指定が不正です。');
				}
				switch ($resource[1]) {
					case 'id':
						
						break;
					case 'category':
						if (empty($resource[2])) {
							throw new Exception('カテゴリIDの指定が不正です。');
						}
						$res = Composer::getItem($resource[2], $resource[1]);
						break;
					default:
						throw new Exception('リソースの指定が不正です。');
				}
				break;
			default:
				throw new Exception('リソースの指定が不正です。');
		}
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
		header('Access-Control-Max-Age: 86400');	// preflight request のレスポンスをキャッシュ（１日）
		header("Content-Length: 0");
		header('HTTP/1.1 200 OK');
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
	} else {
		header('HTTP/1.1 400 Bad Request');
	}
}

//if (isset($param['callback'])) {
//	$res = $param['callback'].'('.$res.')';
//}
//header("Access-Control-Allow-Origin: *");
header("Content-Type: text/javascript; charset=utf-8");

echo $res;
