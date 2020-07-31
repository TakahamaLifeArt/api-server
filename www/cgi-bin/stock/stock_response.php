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
                        $sizeName = mb_convert_kana($fld[6], 'a', 'sjis');	// 英数全角を半角に変換
                        $sizeName = str_replace('cm', '', $sizeName);
                        $sizeName = mb_convert_encoding($sizeName, 'utf-8', 'sjis');

                        $itemName = mb_convert_kana($fld[2], 'aKV', 'sjis');	// 英数全角を半角、半角カタカナを全角に変換
                        $itemName = mb_convert_encoding($itemName, 'utf-8', 'sjis');

                        $colorName = mb_convert_kana($fld[4], 'aKV', 'sjis');// 英数全角を半角、半角カタカナを全角に変換
                        $colorName = mb_convert_encoding($colorName, 'utf-8', 'sjis');

                        // tomsmaster更新用データ
                        $tmpMaster = array();
                        $tmpMaster['item_code'] = $fld[1];
                        $tmpMaster['item_name'] = $itemName;
                        $tmpMaster['color_code'] = $fld[3];
                        $tmpMaster['color_name'] = $colorName;
                        $tmpMaster['size_code'] = $fld[5];
                        $tmpMaster['size_name'] = $sizeName;
                        $tmpMaster['jan_code'] = $fld[9];

                        $tomsMaster[] = $tmpMaster;

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
                        switch ($sizeName) {
                            case 'フリー':
                            case 'F':
                                $sizeName = 'Free';
                                break;
                            case 'LL':
                                $sizeName = 'XL';
                                break;
                            case 'XXL':
                                // 2XLとXXLが混在している場合は、2XLの在庫を使用する
                                if (isset($tmp[$curItem]['3L'])) {
                                    $sizeName = '';
                                } else {
                                    $sizeName = '3L';
                                }
                                break;
                            case '2XL':
                                $sizeName = '3L';
                                break;
                            case 'XXXL':
                                $sizeName = '4L';
                                break;
                            case 'XXXXL':
                                $sizeName = '5L';
                                break;
                            case 'XXXXXL':
                                $sizeName = '6L';
                                break;
                            case 'XXXXXXL':
                                $sizeName = '7L';
                                break;
                            case 'SS':$sizeName = 'XS';
                                break;
                        }

                        if ($sizeName === '') {
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
                        $tmp[$curItem][$sizeName] = array(
                            'item_code'=>strtolower($item_code),
                            'color_code'=>$fld[3],
                            'size_name'=>$sizeName,
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
