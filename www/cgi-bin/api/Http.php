<?php
/**
 * HTTP Request
 * @author <ks.desk@gmail.com>
 *
 * Copyright © 2014 Kyoda Yasushi
 *
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/MIT
 */
declare(strict_types=1);
class Http
{

	private $url;
	
	public function __construct($args)
	{
		$this->url = $args;
	}
	
	
	/**
	 * cURLセッションを実行
	 * REST API 用
	 * request headerでContent-Type:application/jsonを指定する
	 *
	 * @param {string} method HTTPメソッド{@code GET | POST | PUT | DELET}
	 * @param {string} param 送信情報、JSON形式の文字列
	 * @param {array} header HTTPヘッダーフィールドの配列
	 * @return {boolean|string} 成功した場合はTRUE、メソッドが{@code GET}の場合は文字列（JSON形式など）
	 * 							失敗した場合はエラーメッセージ
	 */
	public function requestRest(string $method, string $param, array $header = array())
	{
		$url = $this->url;
		$method = strtoupper($method);
		if ('GET' == $method) {
			$data = http_build_query(json_decode($param));
			$url = ($data != '')? $url.'?'.$data: $url;
		}
		
		$ch = curl_init($url);
		curl_setopt_array(
			$ch, 
			array(
				CURLOPT_RETURNTRANSFER => TRUE,
				CURLOPT_TIMEOUT => 60,
				CURLOPT_CUSTOMREQUEST => $method,
				CURLOPT_POSTFIELDS => $param,
				CURLOPT_HTTPHEADER => $header
			)
		);

		$response = curl_exec($ch);
		$err = curl_error($ch);

		if ($err) {
			$res = "Error: " . $err;
		} else {
			switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
				case 200:
					if ($method != 'GET') {
						$res = TRUE;
					} else {
						$res = $response;
					}
					break;
				default:
					$res = 'Unexpected HTTP code: ' . $http_code . '. Messeage: '.$response;
			}
		}
		
		curl_close($ch);
		return $res;
	}
	
	
	/**
	 * GETまたはPOSTのcURLセッションを実行
	 * @param {string} method HTTPメソッド{@code GET | POST}
	 * @param {array} param 送信情報の連想配列
	 * @return {boolean} 成功した場合はTRUE、失敗した場合はFALSE
	 */
	public function request(string $method, array $param = array()): boolean
	{
		$url = $this->url;
		$data = http_build_query($param);
		if ('GET' == $method) {
			$url = ($data != '')?$url.'?'.$data:$url;
		}

		$ch = curl_init($url);

		if ('POST' == $method) {
			curl_setopt($ch,CURLOPT_POST,1);
			curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
		}

		//curl_setopt($ch, CURLOPT_HEADER,true); //header情報も一緒に欲しい場合
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		//curl_setopt($ch, CURLOPT_TIMEOUT, 2);
		$res = curl_exec($ch);

		//ステータスをチェック
		$respons = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if (preg_match("/^(404|403|500)$/",$respons)) {
			return false;
		}

		return $res;
	}
}
?>
