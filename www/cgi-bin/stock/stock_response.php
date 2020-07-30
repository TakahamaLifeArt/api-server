<?php
/**
 * トムス在庫確認メールの添付ファイルを読込みデータ抽出
 * - postfixで受信したメールを受取り解析する
 * log
 * 2014-09-08 created
 * 2016-03-16 在庫メールの商品名変更に伴う更新
 * 2019-01-21 2XLとXXLのどちらかを3Lとする。混在している場合は2XLの在庫を使用する。
 * 2020-07-30 tomsmasterテーブルの更新処理
 */

require_once dirname(__FILE__).'/ReceiptMailDecoder.php';

// 添付ファイルの作業用フォルダ
define('_TEMP_ATTACH_FILE_DIR_', '/opt/lampp/htdocs/takahama/dev_takahamalifeart.com/www/html/toms/stock');

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
setlocale(LC_ALL, 'ja_JP.euc-jp');	// fgetcsv で全角文字を使用するため
$curItem = null;
$tmp = array();
$data = array();
$tomsMaster = array();

if ($decoder->isMultipart()) {
    $tempFiles = array();
    $num_of_attaches = $decoder->getNumOfAttach();
    for ($i=0 ; $i < $num_of_attaches ; ++$i) {
        
        // 一時ファイルにCSVを保存
        $fpath = _TEMP_ATTACH_FILE_DIR_.'/'.$decoder->attachments[$i]['file_name'];
        
        if ($decoder->saveAttachFile($i, $fpath)) {
            //$tempFiles["$fpath"] = $decoder->attachments[$i]['mime_type'];
            chmod($fpath, 0604);
            if (strpos(basename($fpath, '.csv'), 'wearzaiko') !== false) {
                if ($fp = fopen($fpath, 'r')) {
                    $orders_id = 0;
                    $error_code = '';
                    $short = array();
                    fgetcsv($fp, 4096, _DELIMITER, _ENCLOSURE);	// 1行目を除外
                    while ($fld = fgetcsv($fp, 4096, _DELIMITER, _ENCLOSURE)) {
                        // 空行
                        if (is_null($fld[0])) {
                            continue;
                        }

                        // Normalization
                        $sizename = mb_convert_kana($fld[6], 'a', 'sjis');	// 英数全角を半角に変換
                        $sizename = str_replace('cm', '', $sizename);
                        $sizename = mb_convert_encoding($sizename, 'utf-8', 'sjis');

                        // tomsmaster更新用データ
                        $tmp = array();
                        $tmp['item_code'] = $fld[1];
                        $tmp['item_name'] = $fld[2];
                        $tmp['color_code'] = $fld[3];
                        $tmp['color_name'] = $fld[4];
                        $tmp['size_code'] = $fld[5];
                        $tmp['size_name'] = $sizename;
                        $tmp['jan_code'] = $fld[9];

                        $tomsMaster[] = $tmp;

                        // アイテムのカラー毎に集計
                        if ($curItem !== $fld[1].'_'.$fld[3]) {
                            if (isset($tmp[$curItem])) {
                                foreach ($tmp[$curItem] as $val) {
                                    $data[] = $val;
                                }
                            }
                            $curItem = $fld[1].'_'.$fld[3];
                            $tmp = array();
                            $tmp[$curItem] = array();
                        }

                        // サイズの呼称を統一する
                        switch ($sizename) {
                            case 'フリー':
                            case 'F':
                                $sizename = 'Free';
                                break;
                            case 'LL':
                                $sizename = 'XL';
                                break;
                            case 'XXL':
                                // 2XLとXXLが混在している場合は、2XLの在庫を使用する
                                if (isset($tmp[$curItem]['3L'])) {
                                    $sizename = '';
                                } else {
                                    $sizename = '3L';
                                }
                                break;
                            case '2XL':
                                $sizename = '3L';
                                break;
                            case 'XXXL':
                                $sizename = '4L';
                                break;
                            case 'XXXXL':
                                $sizename = '5L';
                                break;
                            case 'XXXXXL':
                                $sizename = '6L';
                                break;
                            case 'XXXXXXL':
                                $sizename = '7L';
                                break;
                            case 'SS':$sizename = 'XS';
                                break;
                        }

                        if ($sizename === '') {
                            continue;
                        }

                        if ($fld[1]==0) {
                            $item_code = preg_replace('/^([a-z]{2})(\d{1,3})$/', '$1-$2', strtolower($fld[1]));		// du-001
                        } elseif (strpos($fld[1], '0')===0) {
                            /*
                             * 2016-03-16 在庫メールの商品名変更に伴い更新
                            $a = mb_convert_kana($fld[2], 'a', 'sjis');
                            preg_match("/^[a-zA-Z]+/", $a, $matches);
                            $item_code = sprintf("%03d", $fld[1])."-".substr($matches[0],0,3);	// 085-cvt
                             */
                            $item_code = sprintf("%03d", $fld[1])."-%";	// 085-cvtは085-で前方一致
                        } else {
                            $item_code = sprintf("%03d", $fld[1]);		// 30016
                        }

                        // サイズ毎に一時配列で集計、サイズ名が重複している場合は上書き
                        $tmp[$curItem][$sizename] = array(
                            'item_code'=>strtolower($item_code),
                            'color_code'=>$fld[3],
                            'size_name'=>$sizename,
                            'amount'=>$fld[8],
                            'jancode'=>$fld[9],
                        );
                    }

                    if (isset($tmp[$curItem])) {
                        foreach ($tmp[$curItem] as $val) {
                            $data[] = $val;
                        }
                    }
                }
            }
        }
    }
}
