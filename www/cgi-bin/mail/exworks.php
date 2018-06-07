<?php
/*
*	受渡し確認メール
*	工場渡しの場合に全てのプリント完了、又はプリントなしで入荷済みになっている注文の発送日の10:00に自動送信
*/

require_once dirname(__FILE__).'/exwmail.php';
use package\holiday\DateJa;

// 休業日の場合は何もしない
$ja = new DateJa();
$_from_holiday = strtotime(_FROM_HOLIDAY);
$_to_holiday	= strtotime(_TO_HOLIDAY);
$baseSec = time();
$fin = $ja->makeDateArray($baseSec);
if( (($fin['Weekday']==0 || $fin['Weekday']==6) || $fin['Holiday']!=0) || ($baseSec>=$_from_holiday && $_to_holiday>=$baseSec) ){
	exit;
}


$inst = new Exwmail();

// 注文データを取得
$result = $inst->getExwOrder();
if(empty($result)) exit;

// 送信
$inst->send($result);

?>
