<?php
require_once('Mail/mimeDecode.php');


/**
 * 受信メールのヘッダの値やマルチパートの本文、ファイルを取得するクラス
 *  
 * @author	k5959k+yamada@gmail.com
 * @create	2008-03-11
 * @package	ya--mada
 *
 *	プロパティ
	array $attachments = array()
	var: array[] = array('mime_type'=>{mime_type}, 'file_name'=>{file_name},'binary'=>{binary} )
	array $body = array( 'text'=> null , 'html'=> null )
	var: array('text'=>{text}, 'html'=>{html} )
	メソッド
	ReceiptMailDecoder ReceiptMailDecoder ( &$raw_mail, string $raw_mail)
	string extractionEmails (string $raw_string)
	string getDecodedHeader (string $header_name)
	string getFromAddr ()
	string getHeaderAddresses (string $header_name)
	int getNumOfAttach ()
	string getRawHeader (string $header_name)
	string getToAddr ()
	bool isMultiPart ()
	bool saveAttachFile (int $index, string $str_path)
 */
class ReceiptMailDecoder {
    
    
    /**
     * 本文
     * 
     * @var array array('text'=>{text}, 'html'=>{html} )
     */
    var $body = array( 'text'=> null , 'html'=> null );
    
    /**
     * 添付ファイル
     * 
     * @var array array[] = array('mime_type'=>{mime_type},
     *                            'file_name'=>{file_name},
     *                            'binary'=>{binary} )
     */
    var $attachments = array();
    
    
    /**
     * Mail_mimeDecode オブジェクト
     * 
     * @var object
     */
    var $_decoder;
    
    
    /**
     * constractor ReceiptMailDecoder class.
     * 
     * @param string $raw_mail 受信したそのままメール文字列
     */
    function ReceiptMailDecoder ( &$raw_mail )
    {
	if ( !is_null( $raw_mail ) ) {
	    $this->_decode( $raw_mail );
	}
    }
    
    /**
     * このクラスを使用する際の初期化
     * 
     * @access public
     * @param  array
     */
    function init() {
	
    }
    
    /**
     * 生メールをデコードしてプロパティに代入する
     * 
     * @access public
     * @param  string $raw_mail 受信したそのままメール文字列
     */
    function _decode ( &$raw_mail ) {
	if ( is_null($raw_mail) ) {
	    return false;
	}
	
	$params = array();
	$params['include_bodies'] = true;
	$params['decode_bodies']  = true;
	$params['decode_headers']  = true;
	// $params['input'] = $raw_mail."\n";
	
	/*
	 * Mail_mime::Decode をつかって分解解析する
	 * マルチパートの場合は、本文と添付に分けます。
	 */
	$this->_decoder = new Mail_mimeDecode( $raw_mail."\n" );
	$this->_decoder = $this->_decoder->decode($params);
	
	$this->_decodeMultiPart($this->_decoder);
    }
    
    /**
     * 指定ヘッダの取得
     * 
     * @access public
     * @param  string  $header_name
     * @return string
     */
    function getRawHeader ( $header_name ) {
	
	return isset($this->_decoder->headers["$header_name"])
	    ? $this->_decoder->headers["$header_name"]
	    : null;
    }
    
    /**
     * ヘッダがmimeエンコードされている場合はデコードして取得する。
     * 
     * @todo   携帯絵文字には対応していない。
     * @access public
     * @param  string $header_name
     * @return string
     */
    function getDecodedHeader( $header_name ) {
	return mb_decode_mimeheader($this->getRawHeader( $header_name ));
    }
    
    
    /**
     * 指定ヘッダ内のE-mailアドレスだけを抜き出して返す
     * 
     * @access public
     * @param  string $header_name
     * @return string
     * @see extractionEmails()
     */
    function getHeaderAddresses ( $header_name ) {
	return $this->extractionEmails($this->getRawHeader( $header_name ));
    }
    
    /**
     * STATIC
     * 文字列の中からemailアドレスっぽいものだけを抽出して返します。
     * emailアドレスっぽいものの正規表現をあらためた
     * see. http://red.ribbon.to/~php/memo_003.php
     * 
     * @access public
     * @param  string $raw_string
     * @return string $mail_addresses メールアドレスっぽいものを複数あれば,(カンマ)区切りで
     */
    function extractionEmails( $raw_string ) {
	
	/*
	 * 旧emailアドレスっぽい正規表現
	 * see. http://www.tt.rim.or.jp/~canada/comp/cgi/tech/mailaddrmatch/
	 *
	$email_regex_pattern = "/[\x01-\x7F]+@(([-a-z0-9]+\.)*[a-z]+|\[\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\])/";
	*/
	
	/*
	 * 新emailアドレスっぽい正規表現
	 * see. http://red.ribbon.to/~php/memo_003.php
	 */
	$email_regex_pattern = '/(?:[^(\040)<>@,;:".\\\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\\\[\]\000-\037\x80-\xff])|"[^\\\\\x80-\xff\n\015"]*(?:\\\\[^\x80-\xff][^\\\\\x80-\xff\n\015"]*)*")(?:\.(?:[^(\040)<>@,;:".\\\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\\\[\]\000-\037\x80-\xff])|"[^\\\\\x80-\xff\n\015"]*(?:\\\\[^\x80-\xff][^\\\\\x80-\xff\n\015"]*)*"))*@(?:[^(\040)<>@,;:".\\\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\\\x80-\xff\n\015\[\]]|\\\\[^\x80-\xff])*\])(?:\.(?:[^(\040)<>@,;:".\\\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\\\x80-\xff\n\015\[\]]|\\\\[^\x80-\xff])*\]))*/';
	
	
	if ( preg_match_all( $email_regex_pattern, $raw_string, $matches, PREG_PATTERN_ORDER ) ) {
	    if ( isset($matches[0]) ) {
		return implode( ",", $matches[0] );
	    }
	}
	
	return null;
    }
    
    /**
     * デコードした本文の取得
     * 
     * $this->body['text']; // テキスト形式の本文
     * $this->body['html']; // html形式の本文
     */
    
    /**
     * 添付ファイルの取得
     * 
     * $this->attachments[$i]['mime_type']; // MimeType
     * $this->attachments[$i]['file_name']; // ファイル名
     * $this->attachments[$i]['binary'];    // ファイル本体
     */
    
    /**
     * メール本文部分の処理
     * マルチパートの場合は、その処理もしますよ。
     * 
     * @access private
     */
    function _decodeMultiPart(&$decoder) {
	
	// マルチパートの場合 それぞれがparts配列内に再配置されているので
	// 再帰的に処理をする。
	if ( !empty($decoder->parts) ) {
	    foreach ( $decoder->parts as $part ) {
		$this->_decodeMultiPart($part);
	    }
	}
	else {
	    
	    if ( !empty($decoder->body) ) {
		
		// 本文 (text or html )
		if ( 'text' === strToLower($decoder->ctype_primary) ) {
		    if ( 'plain' === strToLower($decoder->ctype_secondary) ) {
			$this->body['text'] =& $decoder->body;
		    }
		    elseif ( 'html' === strToLower($decoder->ctype_secondary) ) {
			$this->body['html'] =& $decoder->body;
		    }
		    // その他のtext系マルチパート
		    else {
			$this->attachments[] = array( 'mime_type'=>$decoder->ctype_primary.'/'.$decoder->ctype_secondary,
						      'file_name'=>$decoder->ctype_parameters['name'],
						      'binary'=>&$decoder->body
						      );
		    }
		}
		// その他
		else {
		    $this->attachments[] = array( 'mime_type'=>$decoder->ctype_primary.'/'.$decoder->ctype_secondary,
						  'file_name'=>$decoder->ctype_parameters['name'],
						  'binary'=>&$decoder->body
						  );
		}
	    }
	}
    }
    
    /**
     * メールが添付ファイルつきか調べる
     * 
     * @access private
     * @return bool      添付付きなら true 無ければ false を返す
     */
    function isMultiPart() {
	
	return (count($this->attachments) > 0) ? true : false;
    }
    
    /**
     * 添付ファイルの数を数える
     * 
     * @access private
     * @return int
     */
    function getNumOfAttach() {
	return count($this->attachments);
    }
    
    /**
     * Toヘッダからアドレスのみを取得する
     * 
     * @access public
     * @return string toアドレス 複数あればカンマ区切りで返す
     * @see getHeaderAddresses(), extractionEmails()
     */
    function getToAddr() {
	return $this->getHeaderAddresses( 'to' );
    }
    
    /**
     * Fromヘッダからアドレスのみを取得する
     * 
     * @access public
     * @return string Fromアドレス 複数あればカンマ区切りで返す
     * @see getHeaderAddresses(), extractionEmails()
     */
    function getFromAddr () {
	return $this->getHeaderAddresses( 'from' );
    }
    
    /**
     * 添付ファイルを保存する
     * 
     * @todo   Mail_mimeDecode はマルチパートのbase64decodeの面倒はみない？
     * @access public
     * @param  int
     * @param  string $str_path
     * @return bool  成功なら true 失敗なら false を返す
     */
    function saveAttachFile ( $index, $str_path ) {
	
	if ( !file_exists($str_path) ) {
	    if ( !is_writable(dirname($str_path)) ) {
		return false;
	    }
	}
	else {
	    if ( !is_writable($str_path) ) {
		return false;
	    }
	}
	
	if ( !isset($this->attachments[$index]) ) {
	    return false;
	}
	
	if ( $fp=fopen($str_path, "wb") ) {
	    fwrite($fp, $this->attachments[$index]['binary'] );
	    fclose($fp);
	    return true;
	}
	
	return false;
    }
}
