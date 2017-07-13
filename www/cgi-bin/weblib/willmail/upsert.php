<?php
/**
 * upsert record for Will Mail
 * @package willmail
 * @author (c) 2014 ks.desk@gmail.com
 */
require_once dirname(__FILE__).'/CustomerTLA.php';
try {
	$customer = new CustomerTLA('customer_num');
	$resp = $customer->upsert(date('Y-m-d'), 'POST');
	if (is_string($resp)) {
		throw new Exception($resp);
	}
} catch (Exception $e) {
	$res = $e->getMessage();
	if ('' === $res) {
		$res = '不正なリクエストです。';
//		header('HTTP/1.1 400 Bad Request');
	} else {
//		header('HTTP/1.1 500 Internal Server Error');
	}
}
?>