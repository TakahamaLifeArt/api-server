<?php
/*
 * API controller
 * (c) 2014 ks.desk@gmail.com
 *
 */
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/weblib/willmail/CustomerTLA.php';

try {
	$headers = getallheaders();
	if (_ACCESS_TOKEN !== $headers[_HTTP_HEADER_KEY]) {
		throw new Exception('トークンが不正です。');
	}
	parse_str($_SERVER['QUERY_STRING'], $param);
	if ($param['ver'] != 'v1') {
		throw new Exception('バージョンが不正です。');
	}
	$method = $_SERVER['REQUEST_METHOD'];
	if('POST' === $method) {
		$resource = explode('/', $param['resource']);
		if ($resource[0] != 'customers') {
			throw new Exception('リソースの指定が不正です。');
		}
		if (empty($resource[1])) {
			$resource[1] = date('Y-m-d');
		}
		if (!empty($resource[2])) {
			$method = strtoupper($resource[2]);
		}
		$customer = new CustomerTLA('customer_num');
		$resp = $customer->upsert($resource[1], $method);
		if (is_string($resp)) {
			throw new Exception($resp);
		}
		header('HTTP/1.1 200 OK');
		$res = json_encode(array('更新' . $resp['update'] . '件'));
	} else {
		throw new Exception('メソッドが不正です。');
	}
} catch (Exception $e) {
	$res = $e->getMessage();
	if ('' === $res) {
		$res = '不正なリクエストです。';
		header('HTTP/1.1 400 Bad Request');
	} else {
		header('HTTP/1.1 500 Internal Server Error');
	}
}
echo $res;
?>