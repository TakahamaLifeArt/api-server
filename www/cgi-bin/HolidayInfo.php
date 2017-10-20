<?php
/**
 * 休日の設定情報の登録と取得
 * 		start : 休日期間開始日{yyyy-mm-dd}
 * 		end : 休日期間終了日{yyyy-mm-dd}
 * 		notice : 告知文
 * 		notice-ext : 臨時告知文
 * 		state : 告知文の掲載可否、1:掲載する  0:掲載しない
 * 		state-ext : 臨時告知文の掲載可否、1:掲載する  0:掲載しない
 * 		site : [1:takahama428, 5:sweatjack.jp, 6:staff-tshirt.com]
 *
 * using
 * cgi-bin/config.php
 */

class HolidayInfo {
	
	private $_info = array();
	
	public function __construct() {
		$filename = $_SERVER['DOCUMENT_ROOT'].'/const/config_holiday.php';
		$fp = fopen($filename, 'r+b');
		if($fp===false) return;

		// フィルタの登録
		stream_filter_register('crlf', 'crlf_filter');

		// 出力ファイルへフィルタをアタァッチ
		stream_filter_append($fp, 'crlf');
		while (!feof($fp)) {
			$contents .= fread($fp, 8192);
		}
		fclose($fp);
		$this->_info = json_decode($contents, true);
	}
	
	
	/**
	 * 休日情報を登録
	 * @param {array} args 
	 * @return {int|bool} 書き込んだバイト数、またはエラー時に FALSE
	 */
	public function setData(array $args) {
		try {
			$tmp = array();
			foreach ($args as $key => $val) {
				switch($key){
					case 'start':
					case 'end':
						if (!empty($val)) $val = $mst->validdate($val);
						$this->_info[$key] = $val;
						break;
					case 'notice':
					case 'notice-ext':
						$this->_info[$key] = $val;
						break;
					case 'state':
					case 'state-ext':
						if($args['site']==1 || $args['site']==5 || $args['site']==6){
							if(empty($val)){
								$tmp[$key] = "0";
							}else{
								$tmp[$key] = "1";
							}
						}
						break;
				}
			}
			if(! empty($tmp)){
				$this->_info['site'][$args['site']] = $tmp;
			}
			$this->_info = $json->encode($this->_info);
			$fp = fopen($filename, 'wb');
			$dat = fwrite($fp, $this->_info);
			fclose($fp);
		} catch(Exception $ex) {
			$dat = false;
		} catch(Error $er) {
			$dat = false;
		}
		return $dat;
	}
	
	
	/**
	 * 休日情報を取得
	 * @param {array} args 
	 * @return {array} 休日情報のハッシュ
	 */
	public function getData(array $args=array()) {
		try {
			$tmp = array();
			$dat['start'] = $this->_info['start'];
			$dat['end'] = $this->_info['end'];
			if(isset($args['site'])){
				if(($this->_info['site'][$args['site']]['state']) == 0){
					$dat['notice'] = "";
				}else{
					$dat['notice'] = $this->_info['notice'];
				}
				if(($this->_info['site'][$args['site']]['state-ext'])== 0){
					$dat['notice-ext'] = "";
				}else{
					$dat['notice-ext'] = $this->_info['notice-ext'];
				}
			}else if(isset($args['notice'])){
				$dat['notice'] = $this->_info['notice'];
				$dat['notice-ext'] = $this->_info['notice-ext'];
			}else {
				// 受注システムがEUC-JPのため
				$this->_info['notice'] = mb_convert_encoding($this->_info['notice'],'euc-jp','utf-8');
				$this->_info['notice-ext'] = mb_convert_encoding($this->_info['notice-ext'],'euc-jp','utf-8');
				$dat = $this->_info;
			}
		} catch(Exception $ex) {
			$dat = array();
		} catch(Error $er) {
			$dat = array();
		}
		return $dat;
	}
}
