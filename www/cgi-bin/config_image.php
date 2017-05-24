<?
/**
 * 定数定義ファイル
 * アイテム画像のディレクトリ及びファイルAPI用
 * @author (c) 2014 ks.desk@gmail.com
 */

define('_WEB_PATH', 'http://takahamalifeart.com/weblib/img/items');
define('_ROOT_PATH', $_SERVER['DOCUMENT_ROOT'].'/weblib/img/items');
define('_TMP_PATH', $_SERVER['DOCUMENT_ROOT'].'/v2/tmpArchive');

// 除外ディレクトリ
define('_EXCLUDE_DIR', 'printposition');

// パーミッション
define('_MKDIR_MODE', 02775);

// アップロードできるファイルサイズの上限2MB、base64に対応するため×1.38
define('_MAX_UPLOAD_SIZE', 2097152*1.38);


// API
define('_API', 'http://takahamalifeart.com/v2/api2.php');
?>