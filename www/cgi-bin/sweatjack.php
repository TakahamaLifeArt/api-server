<?php
/**
*	タカハマラフアート
*	商品マスター　クラス Sweatjack用
*	charset utf-8
*/

require_once dirname(__FILE__).'/config.php';
require_once dirname(__FILE__).'/MYDB.php';

class Sweatjack{
/**
*	getTablelist		データベースのテーブルリストを返す
*	getItemPrice		当該商品の単価を返す（サイズIDが0の時は最低価格を返す）
*	getSize				サイズ一覧
*	getItemInfo			当該商品の情報を返す（価格は最低価格帯のみ）
*	getItemID			アイテムコードからアイテムIDを返す
*	salestax			商品単価に使用する消費税率を返す（static）
*	validdate			日付の妥当性を確認し不正値は今日の日付を返す（static）
*/	
	
	/**
	*	データベースのテーブルリストを返す
	*	@mode			データベースのテーブル名
	*	@item_id		アイテムID
	*	@itemcolor_code	アイテムのカラーコード
	*	@part			1:パーカー  2:パンツ  null(default):全て
	*
	*	@return			テーブルのレコード
	*/
	public function getTablelist($mode, $item_id=NULL, $itemcolor_code=NULL, $part=NULL){
		try{

			$isExe = true;
			$curdate = date('Y-m-d');
			
			switch($mode){
				case 'items':	// only sweat for the Sweatjack
					$conn = db_connect();
					if($part==1){
						$sql = sprintf("select * from catalog inner join item on item.id=catalog.item_id where lineup=1 and color_lineup=1 and category_id=%d
						 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s'
						 and item_id in(119,120,122,123,124,125,126,127,172,173) group by item.id", 2,$curdate,$curdate,$curdate,$curdate);
					}else if($part==2){
						$sql = sprintf("select * from catalog inner join item on item.id=catalog.item_id where lineup=1 and color_lineup=1 and category_id=%d
						 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s'
						 and item_id in(115,117,174) group by item.id", 2,$curdate,$curdate,$curdate,$curdate);
					}else{
						$sql = sprintf("select * from catalog inner join item on item.id=catalog.item_id where lineup=1 and color_lineup=1 and category_id=%d
						 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s'
						 and item_id in(119,120,122,123,124,125,126,127,172,173,115,117,174) group by item.id", 2,$curdate,$curdate,$curdate,$curdate);
					}

					break;

				case 'item':	// 指定アイテムIDのリスト
					$conn = db_connect();
					$sql = sprintf("select * from ((catalog inner join item on item.id=catalog.item_id)
					 inner join itemcolor on catalog.color_id=itemcolor.id)
					 left join itemdetail on item.item_code=itemdetail.item_code
					 where lineup=1 and color_lineup=1 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s'
					 and item_id=%d and catalog.color_code!='000'", $curdate,$curdate,$curdate,$curdate,$item_id);
					
					break;

				case 'itemsize':	// アイテムのサイズリスト
					$conn = db_connect();
					$isExe = false;
					
					// 消費税
					$tax = self::salestax($conn, $curdate);
					
					// TAKAHAMA価格
					if(strtotime($curdate)>=strtotime(_APPLY_TAX_CLASS)){
						$tax_tla = 0;			// 外税
					}else{
						$tax_tla = $tax/100;	// 内税
					}
					
					// メーカー価格
					$tax_maker = $tax/100;
					
					$result = exe_sql($conn, "SELECT * FROM size");
					while($rec = mysqli_fetch_array($result)){
						$size_list[$rec['id']] = $rec['size_name'];
					}

					if(is_null($item_id)){
						$rs = $size_list;
						break;
					}
					$item_id = quote_smart($conn, $item_id);
					$itemcolor_code = quote_smart($conn, $itemcolor_code);
					
					$sql = sprintf("SELECT * FROM (itemsize INNER JOIN catalog ON itemsize.series=catalog.size_series)
					 LEFT JOIN itemcolor ON catalog.color_id=itemcolor.id
					 WHERE size_lineup=1 and color_lineup=1 and itemsizeapply<='%s' and itemsizedate>'%s' and catalogapply<='%s' and catalogdate>'%s' and catalog.item_id=%d and catalog.color_code='%s'
					 order by size_from",
					 $curdate,$curdate,$curdate,$curdate,$item_id,$itemcolor_code);


					$result = exe_sql($conn, $sql);
					while($rec = mysqli_fetch_array($result)){
						$data[] = $rec;
					}

					// 価格
					$sql = sprintf("select * from itemprice where size_lineup=1 and itempriceapply<='%s' and itempricedate>'%s' and item_id=%d order by id", $curdate,$curdate,$item_id);
					$result = exe_sql($conn, $sql);
					while($rec = mysqli_fetch_array($result)){
						$tmp[] = $rec;
					}
					for($i=0; $i<count($tmp); $i++){
						for($t=$tmp[$i]['size_from']; $t<=$tmp[$i]['size_to']; $t++){
							$price[$t]['price_color'] = round( ($tmp[$i]['price_0']*$tmp[$i]['margin_pvt']*(1+$tax_tla))+4, -1 );
							if($tmp[$i]['price_1']==0){
								$price[$t]['price_white'] = $price[$t]['price_color'];
							}else{
								$price[$t]['price_white'] = round( ($tmp[$i]['price_1']*$tmp[$i]['margin_pvt']*(1+$tax_tla))+4, -1 );
							}
							$price[$t]['price_color_maker'] = round( $tmp[$i]['price_maker_0']*(1+$tax_maker) );
							$price[$t]['price_white_maker'] = round( $tmp[$i]['price_maker_1']*(1+$tax_maker) );
						}
					}

					$rs = array();
					$i=0;
					$series = $data[0]['series'];
					foreach($data as $val){
						if($val['series']!=$series) continue;
						for($t=$val['size_from']; $t<=$val['size_to']; $t++){
							$rs[$i]['id'] = $t;
							$rs[$i]['size_name'] = $size_list[$t];
							$rs[$i]['color_name'] = $val['color_name'];
							$rs[$i]['price_color'] = $price[$t]['price_color'];
							$rs[$i]['price_white'] = $price[$t]['price_white'];
							$rs[$i]['price_color_maker'] = $price[$t]['price_color_maker'];
							$rs[$i]['price_white_maker'] = $price[$t]['price_white_maker'];
							$i++;
						}
					}

					break;
			}

			if($isExe){
				$result = exe_sql($conn, $sql);
				$rs = array();
				while($rec = mysqli_fetch_array($result)){
					$rs[] = $rec;
				}
			}
		}catch(Exception $e){
			$rs = null;
		}

		mysqli_close($conn);

		return $rs;
	}


	/**
	*		当該商品の単価を返す（サイズIDが0の時は最低価格を返す）
	*		@item_id		アイテムのID
	*		@size_id		サイズのID
	*		@points			プリントの有無（1..あり or 0..なし）
	*		@isWhite		白Ｔ..1 or それ以外..0(default)
	*		@mode			タカハマ価格のみ..0(default) or メーカー価格も返す..1
	*/
	public function getItemPrice($item_id, $size_id, $points, $isWhite=0, $mode=0){
		try{
			$conn = db_connect();
			$curdate = date('Y-m-d');
			
			// 消費税
			$tax = self::salestax($conn, $curdate);
			
			// TAKAHAMA価格
			if(strtotime($curdate)>=strtotime(_APPLY_TAX_CLASS)){
				$tax_tla = 0;			// 外税
			}else{
				$tax_tla = $tax/100;	// 内税
			}
			
			// メーカー価格
			$tax_maker = $tax/100;
			
			if($size_id==0){
				$sql = sprintf("SELECT * FROM itemprice WHERE size_lineup=1 and item_id=%d and itempriceapply<='%s' and itempricedate>'%s' ORDER BY size_to ASC LIMIT 1", 
				$item_id,$curdate,$curdate);
			}else{
				$sql = sprintf("SELECT * FROM itemprice WHERE size_lineup=1 and item_id=%d and size_from<=%d and size_to>=%d and itempriceapply<='%s' and itempricedate>'%s'",
				$item_id,$size_id,$size_id,$curdate,$curdate);
			}
			
			$result = exe_sql($conn, $sql);
			$rec = mysqli_fetch_array($result);
			if($rec){
				if($isWhite==1 && $rec['price_1']>0){
					$tla = $rec['price_1'] * $rec['margin_pvt'] * (1+$tax_tla);
					$maker = round($rec['price_maker_1']*(1+$tax_maker));
				}else{
					$tla = $rec['price_0'] * $rec['margin_pvt'] * (1+$tax_tla);
					$maker = round($rec['price_maker_0']*(1+$tax_maker));
				}

				$tla = round($tla+4, -1);

				if($points==0){
					$tla *= 1.1;
					$tla = round($tla+4, -1);
				}

				if(empty($mode)){
					$rs = $tla;
				}else if($mode==1){
					$rs[] = $tla;
					$rs[] = $maker;
				}
			}
		}catch(Exception $e){
			$rs = '0';
		}

		mysqli_close($conn);

		return $rs;
	}


	/**
	*	サイズを返す
	*	@id				アイテムID
	*	@color			カラーコード
	*	@mode			id:アイテムID(default), code:アイテムコード
	*
	*	@return			[id:size_from, name:size_name]　colorがNULLのときは全サイズ
	*/
	public function getSize($id, $color=NULL, $mode='id'){
		try{
			if($mode=='code') $id=$this->getItemID($id);
			if(empty($id)) return null;
			$conn = db_connect();
			$curdate = date('Y-m-d');
			//$curdate = $this->validdate($curdate);
			if(empty($color)){
				$sql = sprintf("select distinct(size_from) as id, size_name as name from itemsize inner join size on size_from=size.id 
				where size_lineup=1 and itemsizeapply<='%s' and itemsizedate>'%s' and item_id=%d order by series, size_from",
				$curdate, $curdate, $id);
			}else{
				$color = quote_smart($conn, $color);
				$sql = sprintf("select size_from as id, size_name as name from (itemsize inner join size on size_from=size.id) 
				inner join catalog on itemsize.series=catalog.size_series
				where size_lineup=1 and color_lineup=1 and itemsizeapply<='%s' and itemsizedate>'%s' and catalogapply<='%s' and catalogdate>'%s' and itemsize.item_id=%d and color_code='%s' 
				order by size_from",
				$curdate, $curdate, $curdate, $curdate, $id, $color);
			}
			
			$result = exe_sql($conn, $sql);
			while ($rec = mysqli_fetch_assoc($result)) {
				$res[] = $rec;
			}
		}catch(Exception $e){
			$res = null;
		}

		mysqli_close($conn);

		return $res;
	}
	
	
	/**
	*		取扱商品の情報を返す（価格は最低価格帯のみ）
	*		価格は販売価格に計算済み
	*		@part			1:Tシャツ  6:ブルゾン  10:パーカー  11:トレーナー  12:パンツ  null(default):全て
	*		@keyname		返り値の配列のキーを指定、default ['item_id']
	*/
	public function getItemInfo($part=null, $keyname="item_id"){
		try{
			$conn = db_connect();
			$curdate = date('Y-m-d');
			
			// 消費税
			$tax = self::salestax($conn, $curdate);
			
			// TAKAHAMA価格
			if(strtotime($curdate)>=strtotime(_APPLY_TAX_CLASS)){
				$tax_tla = 0;			// 外税
			}else{
				$tax_tla = $tax/100;	// 内税
			}
			
			// メーカー価格
			$tax_maker = $tax/100;
			
			if($part==1){	// t-shirts
				$sql = sprintf("select * from ((catalog inner join item on item.id=catalog.item_id)
				 inner join itemprice on item.id=itemprice.item_id)
				 left join itemdetail on item.item_code=itemdetail.item_code
				 where item.show_site like "."'%%".$_REQUEST['show_site']."%%'"." and lineup=1 and color_lineup=1 and size_lineup=1 and category_id=%d
				 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s'
				 group by item.id,itemprice.id order by itemprice.size_from", 
				 1,$curdate,$curdate,$curdate,$curdate,$curdate,$curdate);
			}else if($part==6){	// outer(blouson)
				$sql = sprintf("select * from ((catalog inner join item on item.id=catalog.item_id)
				 inner join itemprice on item.id=itemprice.item_id)
				 left join itemdetail on item.item_code=itemdetail.item_code
				 where item.show_site like "."'%%".$_REQUEST['show_site']."%%'"." and lineup=1 and color_lineup=1 and size_lineup=1 and category_id=%d
				 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s'
				 group by item.id,itemprice.id order by itemprice.size_from", 
				 6,$curdate,$curdate,$curdate,$curdate,$curdate,$curdate);
			}else if($part==10){	// parker
				$sql = sprintf("select * from (((catalog inner join item on item.id=catalog.item_id)
				 inner join itemprice on item.id=itemprice.item_id)
				 inner join itemtag on item.id=tag_itemid)
				 left join itemdetail on item.item_code=itemdetail.item_code
				 where item.show_site like "."'%%".$_REQUEST['show_site']."%%'"." and lineup=1 and color_lineup=1 and size_lineup=1 and (tag_id=%d or tag_id=%d) and category_id=%d
				 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s'
				 group by item.id,itemprice.id order by itemprice.size_from",
				 13,14,2,$curdate,$curdate,$curdate,$curdate,$curdate,$curdate);
			}else if($part==11){	// trainer
				$sql = sprintf("select * from (((catalog inner join item on item.id=catalog.item_id)
				 inner join itemprice on item.id=itemprice.item_id)
				 inner join itemtag on item.id=tag_itemid)
				 left join itemdetail on item.item_code=itemdetail.item_code
				 where item.show_site like "."'%%".$_REQUEST['show_site']."%%'"." and lineup=1 and color_lineup=1 and size_lineup=1 and tag_id=%d and category_id=%d
				 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s'
				 group by item.id,itemprice.id order by itemprice.size_from",
				 15,2,$curdate,$curdate,$curdate,$curdate,$curdate,$curdate);
			}else if($part==12 || $part==2){	// pants
				$sql = sprintf("select * from (((catalog inner join item on item.id=catalog.item_id)
				 inner join itemprice on item.id=itemprice.item_id)
				 inner join itemtag on item.id=tag_itemid)
				 left join itemdetail on item.item_code=itemdetail.item_code
				 where item.show_site like "."'%%".$_REQUEST['show_site']."%%'"." and lineup=1 and color_lineup=1 and size_lineup=1 and tag_id=%d and category_id=%d
				 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s'
				 group by item.id,itemprice.id order by itemprice.size_from",
				 16,2,$curdate,$curdate,$curdate,$curdate,$curdate,$curdate);
			}else if($part==13){	// jacket
				$sql = sprintf("select * from (((catalog inner join item on item.id=catalog.item_id)
				 inner join itemprice on item.id=itemprice.item_id)
				 inner join itemtag on item.id=tag_itemid)
				 left join itemdetail on item.item_code=itemdetail.item_code
				 where item.show_site like "."'%%".$_REQUEST['show_site']."%%'"." and lineup=1 and color_lineup=1 and size_lineup=1 and tag_id=%d and category_id=%d
				 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s'
				 group by item.id,itemprice.id order by itemprice.size_from",
				 44,2,$curdate,$curdate,$curdate,$curdate,$curdate,$curdate);
			}else if($part==14){	// champion
				$sql = sprintf("select * from (((catalog inner join item on item.id=catalog.item_id)
				 inner join itemprice on item.id=itemprice.item_id)
				 inner join itemtag on item.id=tag_itemid)
				 left join itemdetail on item.item_code=itemdetail.item_code
				 where item.show_site like "."'%%".$_REQUEST['show_site']."%%'"." and lineup=1 and color_lineup=1 and size_lineup=1 and tag_id=%d and category_id=%d
				 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s'
				 group by item.id,itemprice.id order by itemprice.size_from",
				 43,2,$curdate,$curdate,$curdate,$curdate,$curdate,$curdate);
			}else if($part==15){	// zipparker
				$sql = sprintf("select * from (((catalog inner join item on item.id=catalog.item_id)
				 inner join itemprice on item.id=itemprice.item_id)
				 inner join itemtag on item.id=tag_itemid)
				 left join itemdetail on item.item_code=itemdetail.item_code
				 where item.show_site like "."'%%".$_REQUEST['show_site']."%%'"." and lineup=1 and color_lineup=1 and size_lineup=1 and tag_id=%d and category_id=%d
				 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s'
				 group by item.id,itemprice.id order by itemprice.size_from",
				 14,2,$curdate,$curdate,$curdate,$curdate,$curdate,$curdate);
			}else if($part==90){	// sweat（pantsを除く）
				$sql = sprintf("select * from (((catalog inner join item on item.id=catalog.item_id)
				 inner join itemprice on item.id=itemprice.item_id)
				 inner join itemtag on item.id=tag_itemid)
				 left join itemdetail on item.item_code=itemdetail.item_code
				 where item.show_site like "."'%%".$_REQUEST['show_site']."%%'"." and lineup=1 and color_lineup=1 and size_lineup=1 and tag_id in(13,14,15,44) and category_id=2
				 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s'
				 group by item.id,itemprice.id order by itemprice.size_from",
				 $curdate,$curdate,$curdate,$curdate,$curdate,$curdate);
			}else if($part==99){	// all items(sweat)
				$sql = sprintf("select * from ((catalog inner join item on item.id=catalog.item_id)
				 inner join itemprice on item.id=itemprice.item_id)
				 left join itemdetail on item.item_code=itemdetail.item_code
				 where item.show_site like "."'%%".$_REQUEST['show_site']."%%'"." and lineup=1 and color_lineup=1 and size_lineup=1 and category_id=%d
				 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s'
				 group by item.id,itemprice.id order by itemprice.size_from", 
				 2,$curdate,$curdate,$curdate,$curdate,$curdate,$curdate);
			}else{
				$sql = sprintf("select * from ((((catalog
				 inner join category on catalog.category_id = category.id)
				 inner join item on item.id=catalog.item_id)
				 inner join itemprice on item.id=itemprice.item_id)
				 inner join itemtag on item.id=tag_itemid)
				 left join itemdetail on item.item_code=itemdetail.item_code 
				 where item.show_site like "."'%%".$_REQUEST['show_site']."%%'"." and lineup=1 and color_lineup=1 and size_lineup=1 and category_id=%d and tag_id in(13,14,15,16,44)
				 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s'
				 group by item.id, tag_id, itemprice.id order by item_row, itemprice.size_from", 
				 2,$curdate,$curdate,$curdate,$curdate,$curdate,$curdate);
			}
			
			$result = exe_sql($conn, $sql);
			while($rec = mysqli_fetch_array($result)){
				if(isset($rs[$rec['item_id']])) continue;
				$rec['price_0'] = round( ($rec['price_0'] * $rec['margin_pvt'] * (1+$tax_tla))+4, -1);
				$rec['price_1'] = round( ($rec['price_1'] * $rec['margin_pvt'] * (1+$tax_tla))+4, -1);
				$rec['price_maker_0'] = round( $rec['price_maker_0']*(1+$tax_maker) );
				$rec['price_maker_1'] = round( $rec['price_maker_1']*(1+$tax_maker) );
				
				$sizes = $this->getSize($rec['item_id']);
				$rec['size_count'] = count($sizes);
				$rec['size_list'] = $sizes;
				
				$colors = $this->getTablelist('item', $rec['item_id']);
				$rec['color_count'] = count($colors);
				
				$rs[$rec[$keyname]] = $rec;
			}
		}catch(Exception $e){
			$rs = null;
		}

		mysqli_close($conn);

		return $rs;
	}



	/**
	*	アイテムコードからアイテムIDを返す
	*	@itemcode		アイテムコード
	*
	*	@return			アイテムID
	*/
	public function getItemID($itemcode){
		try{
			if(empty($itemcode)) return null;
			$conn = db_connect();
			$curdate = date('Y-m-d');
			$itemcode = quote_smart($conn, $itemcode);
			$sql= sprintf("select * from item where lineup=1 and itemapply<='%s' and itemdate>'%s' and item_code='%s'", $curdate, $curdate, $itemcode);
			$result = exe_sql($conn, $sql);
			$rec = mysqli_fetch_assoc($result);
			$rs = $rec['id'];
		}catch(Exception $e){
			$rs = null;
		}
		
		mysqli_close($conn);
		
		return $rs;
	}
	
	
	/**
	*	商品単価に使用する消費税率を返す（Static）
	*	@curdate		日付(0000-00-00)
	*
	*	return			消費税
	*/
	private static function salestax($conn, $curdate){
		$sql = sprintf('select taxratio from salestax where taxapply=(select max(taxapply) from salestax where taxapply<="%s")', $curdate);
		$result = exe_sql($conn, $sql);
		$rec = mysqli_fetch_array($result);
		
		return $rec['taxratio'];
	}
	
	
	/**
	*	日付の妥当性を確認し不正値は今日の日付を返す
	*	@curdate		日付(0000-00-00)
	*	
	*	@return			0000-00-00
	*/
	private function validdate($curdate){
		if(empty($curdate)){
			$curdate = date('Y-m-d');
		}else{
			$d = explode('-', $curdate);
			if(checkdate($d[1], $d[2], $d[0])==false){
				$curdate = date('Y-m-d');
			}
		}
		return $curdate;
	}
	
}
?>
