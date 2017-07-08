<?php
/*
*	HTTP Request
*	@author (c) 2014 ks.desk@gmail.com
*/

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
	 * @param {string} method HTTPメソッド{@code GET | POST | PUT | DELET} 
	 * @param {string} param 送信情報、JSON形式の文字列
	 * @param {array} header HTTPヘッダーフィールドの配列
	 * @return {boolean|string} 成功した場合はTRUE、メソッドが{@code GET}の場合はJSON形式の文字列
	 * 							失敗した場合はエラーメッセージ
	 */
	public function requestRest($method, $param, $header = array())
	{
		$method = strtoupper($method);
		$ch = curl_init($this->url);
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
	 */
    public function request($method, $param = array())
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
