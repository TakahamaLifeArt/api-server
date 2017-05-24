<?php
/*
*	トムスEDI発注の回答メールの添付ファイルを読込みデータ抽出
*	- postfixで受信したメールを受取り解析する
*	log		: 2013-11-22 created
*			: 2014-01-10 マルチパートのデータを取得し解析
*			: 2014-05-02 エラーコードを取得
*/

require_once dirname(__FILE__).'/ReceiptMailDecoder.php';

// 添付ファイルの作業用フォルダ
define('_TEMP_ATTACH_FILE_DIR_', '/opt/lampp/htdocs/takahama/dev_takahamalifeart.com/www/html/toms/response');

//CSVファイル定義
define('_DELIMITER', ',');	//データ区切り(カンマ)
define('_ENCLOSURE', '"');	//データ囲み文字(ダブルクォーテーション)


$decoder = new ReceiptMailDecoder($raw_mail);

/*
// To:アドレスのみを取得する
$toAddr = $decoder->getToAddr();

// To:ヘッダの値を取得する
$toString = $decoder->getDecodedHeader( 'to' );

// Subject:ヘッダの値を取得する
$subject = $decoder->getDecodedHeader( 'subject' );

// text/planなメール本文を取得する
$body = mb_convert_encoding($decoder->body['text'],"utf-8","jis");

// text/htmlなメール本文を取得する
$body = mb_convert_encoding($decoder->body['html'],"utf-8","jis");
*/

// マルチパートのデータを取得する
if ( $decoder->isMultipart() ) {
	$tempFiles = array();
	$num_of_attaches = $decoder->getNumOfAttach();
	for ( $i=0 ; $i < $num_of_attaches ; ++$i ) {
		/*
		* ファイルを一時ディレクトリ _TEMP_ATTACH_FILE_DIR_ に保存する
		* 一時ファイルには tempnam()を使用する（パーミッション「0600」）
		*/
		//$fpath = tempnam( _TEMP_ATTACH_FILE_DIR_, "todoattach_" );
		
		$fpath = _TEMP_ATTACH_FILE_DIR_.'/'.$decoder->attachments[$i]['file_name'];
		if ( $decoder->saveAttachFile( $i, $fpath ) ) {
			//$tempFiles["$fpath"] = $decoder->attachments[$i]['mime_type'];
			chmod($fpath, 0604);
			$file_number = preg_split('/_/', basename($fpath, '.csv'));
			
			//ファイルを開く
			if($fp = fopen($fpath, 'r')){
				$orders_id = 0;
				$error_code = '';
				$short = array();
				while ($fld = fgetcsv($fp, 4096, _DELIMITER, _ENCLOSURE)) {
					if(is_null($fld[0])) continue;	// 空行
					if(empty($orders_id)) $orders_id = $fld[2];	// 受注No.
					if(!empty($fld[48])) $error_code = $fld[48];// エラーコード
					$short[] = $fld[31];						// 欠品数の配列
				}
			}
		}
	}
}

?>
