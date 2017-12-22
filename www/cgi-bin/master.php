<?php
/**
 *	タカハマラフアート
 *	商品マスター　クラス
 *	charset utf-8
 */

require_once dirname(__FILE__).'/config.php';
require_once dirname(__FILE__).'/MYDB.php';



class Master{
/**
 *	getCategory			商品カテゴリー一覧
 *	getItem				アイテム一覧
 *	getSize				サイズ一覧
 *	getItemcolor		アイテムカラー一覧
 *	getItemprice		価格一覧
 *	getPrintposition	プリント位置画像（絵型）の相対パスとなるキーとタイプ
 *	matchPattern		新絵型と旧絵型のプリント箇所の対応データ
 *	getItemAttr			商品の属性データとカラーごとの商品コードを返す
 *	getSizePrice		商品のカラーを指定してサイズごとの価格を返す
 *	getCategories		商品カテゴリーを指定して商品情報を返す
 *	itemOf				指定したタグ及びカテゴリのアイテム一覧ページ情報を返す
 *	itemIdOf			指定したタグ及びカテゴリのアイテムIDを返す（private, itemOfから呼出す）
 *	getTagInfo			アイテムタグ情報を返す
 *	getItemTag			タグ一覧を返す
 *	getItemPageInfo		商品一覧で使用する基本情報を返す（サイズ数、カラー数、最安価格）
 *	getItemdetail		商品詳細ページ情報
 *	getItemMeasure		寸法情報
 *	getPrintMethod		プリント方法情報
 *	getItemStock		在庫数を返す
 *	getSizename			サイズIDからサイズ名を返す
 *	getItemID			アイテムコードからアイテムIDを返す
 *	getUserList			顧客情報の取得
 *	getDeliveryList		お届け先情報の取得
 *	checkExistEmail		メールアドレスの存在確認、存在する場合に顧客情報を返す
 *	setRequestMail		資料請求メールの情報を登録する
 *	setEnquete			商品到着後のアンケート結果を登録する
 *	setAcceptingOrder	受注システムに注文データを登録する
 *	unsubscribe			お知らせメールの配信停止設定
 *	getSalesTax			外税方式の消費税率を返す		一般で_APPLY_TAX_CLASSより前は外税方式適用前のため、0%を返す
 *  validdate			日付の妥当性を確認し不正値は今日の日付を返す、ISO8601などのフォーマットにも対応
 *
 * 	(private)
 *	salestax			商品単価に使用する消費税率を返す
 *	rename_size			サイズ名を変換する
 */
	
	
	/**
	*	商品カテゴリーを返す
	*	@curdate		抽出条件に使用する日付(0000-00-00)。NULL:今日
	*
	*	@return			[id:category_id, code:category_key, name:category_name]
	*/
	public function getCategory($curdate=NULL){
		try{
			$conn = db_connect();
			$curdate = $this->validdate($curdate);
			$sql = sprintf("select category_id as id, category_key as code, category_name as name 
			from category inner join catalog on category.id=catalog.category_id 
			where catalogapply<='%s' and catalogdate>'%s' 
			group by category.id", 
			$curdate, $curdate);
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
	 * 商品情報を返す
	 * @id				カテゴリーID
	 * @curdate			抽出条件に使用する日付(0000-00-00)。NULL:今日
	 * @mode			@idの種類を指定、NULL:カテゴリーID(default), item:アイテムID, tag:タグ
	 * 					または、{array}で複数のタグIDを指定
	 *
	 * @return			[id:item_id, code:item_code, name:item_name, posid:printposition_id, 'item_row':item_row, 'cost':min_price
	 * 					i_color_code, i_caption, i_description, i_material, i_silk, i_digit, i_inkjet, i_cutting, i_note, i_note_label]
	 */
	public function getItem($id, $curdate=NULL, $mode=NULL){
		try{
			if(empty($id)) return null;
			$conn = db_connect();
			$curdate = $this->validdate($curdate);
			if(empty($mode)){
				$sql = sprintf("select item_id as id, item.item_code as code, item_name as name, printposition_id as posid, item_row, category_id,
				i_color_code, i_caption, i_description, i_material, i_silk, i_digit, i_inkjet, i_cutting, i_note, i_note_label, count(catalog.id) as colors, 
				category_key, category_name 
				from ((item 
				inner join catalog on item.id=catalog.item_id) 
				inner join category on catalog.category_id=category.id) 
				left join itemdetail on item.item_code=itemdetail.item_code 
				where catalog.color_code!='000' 
				and show_site like "."'%%".$_REQUEST['show_site']."%%'"." 
				and lineup=1 and color_lineup=1 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' 
				and category_id=%d group by item.id order by item_row", 
				$curdate, $curdate, $curdate, $curdate, $id);
			}else if($mode=='tag'){
				$sql = sprintf("select item_id as id, item.item_code as code, item_name as name, printposition_id as posid, item_row, category_id,
				i_color_code, i_caption, i_description, i_material, i_silk, i_digit, i_inkjet, i_cutting, i_note, i_note_label, count(catalog.id) as colors, 
				category_key, category_name 
				from (((item 
				inner join catalog on item.id=catalog.item_id) 
				inner join category on catalog.category_id=category.id) 
				left join itemtag on item.id=itemtag.tag_itemid) 
				left join itemdetail on item.item_code=itemdetail.item_code 
				where catalog.color_code!='000' 
				and lineup=1 and color_lineup=1 
				and show_site like "."'%%".$_REQUEST['show_site']."%%'"." 
				and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' 
				and itemtag.tag_id=%d group by item.id order by item_row",
				$curdate, $curdate, $curdate, $curdate, $id);

			}else if(is_array($mode)){
			/**
			 * 指定カテゴリーで且つ指定タグのいずれかに合致するアイテム
			 */
				$sql = "select item_id as id, item.item_code as code, item_name as name, printposition_id as posid, item_row, category_id,
				i_color_code, i_caption, i_description, i_material, i_silk, i_digit, i_inkjet, i_cutting, i_note, i_note_label, count(catalog.id) as colors, 
				tag_id, category_key, category_name from (((item 
				inner join catalog on item.id=catalog.item_id) 
				inner join category on catalog.category_id=category.id) 
				left join itemtag on item.id=itemtag.tag_itemid) 
				left join itemdetail on item.item_code=itemdetail.item_code 
				where catalog.color_code!='000' 
				and show_site like "."'%%".$_REQUEST['show_site']."%%'"." 
				and lineup=1 and color_lineup=1 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' 
				and category_id=%d ";

				if(! empty($mode)){
					$tmp = array();
					for ($i=0; $i < count($mode); $i++) { 
						$tmp[] = 'tag_id='.intval($mode[$i]);
					}
					$sql .= "and ( ". implode(' or ', $tmp) .") ";
				} 
				$sql .= "group by item.id order by item_row"; 
				
				$sql = sprintf($sql, $curdate, $curdate, $curdate, $curdate, $id);

			}else{
				/* modeがitemの場合は当該アイテムのみのデータを返す(2015-08-15)
				$sql = sprintf("select category_id from item inner join catalog on item.id=catalog.item_id 
				where lineup=1 and color_lineup=1 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' 
				and item.id=%d group by item.id order by item_row", 
				$curdate, $curdate, $curdate, $curdate, $id);
				$result = exe_sql($conn, $sql);
				$data = mysqli_fetch_assoc($result);
				*/
				$sql = sprintf("select item_id as id, item.item_code as code, item_name as name, printposition_id as posid, item_row, category_id,
				i_color_code, i_caption, i_description, i_material, i_silk, i_digit, i_inkjet, i_cutting, i_note, i_note_label, count(catalog.id) as colors, 
				category_key, category_name 
				from ((item 
				inner join catalog on item.id=catalog.item_id) 
				inner join category on catalog.category_id=category.id) 
				left join itemdetail on item.item_code=itemdetail.item_code 
				where catalog.color_code!='000' 
				and show_site like "."'%%".$_REQUEST['show_site']."%%'"." 
				and lineup=1 and color_lineup=1 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' 
				and item.id=%d group by item.id", 
				$curdate, $curdate, $curdate, $curdate, $id);

			}
			
			$result = exe_sql($conn, $sql);
			while ($rec = mysqli_fetch_assoc($result)) {
				$cost = $this->getItemprice($rec['id']);
				$rec['cost'] = $cost[0]['price_white'];
				$rec['cost_color'] = $cost[0]['price_color'];
				$rec['cost_maker'] = $cost[0]['maker_white'];
				$rec['cost_maker_color'] = $cost[0]['maker_color'];
				$res[] = $rec;
			}
		}catch(Exception $e){
			$res = null;
		}

		mysqli_close($conn);

		return $res;
	}
	
	
	
	/**
	*	サイズを返す
	*	@id				アイテムID
	*	@color			カラーコード
	*	@curdate		抽出条件に使用する日付(0000-00-00)。NULL:今日
	*	@mode			id:アイテムID(default), code:アイテムコード
	*
	*	@return			[id:size_from, name:size_name]　colorがNULLのときは全サイズ
	*/
	public function getSize($id, $color=NULL, $curdate=NULL, $mode='id'){
		try{
			if($mode=='code') $id=$this->getItemID($id);
			if(empty($id)) return null;
			$conn = db_connect();
			$curdate = $this->validdate($curdate);
			if(empty($color)){
				$sql = sprintf("select distinct(size_from) as id, size_name as name from itemsize inner join size on size_from=size.id 
				where size_lineup=1 and itemsizeapply<='%s' and itemsizedate>'%s' and item_id=%d order by series, size_row",
				$curdate, $curdate, $id);
			}else{
				$color = quote_smart($conn, $color);
				$sql = sprintf("select size_from as id, size_name as name from (itemsize inner join size on size_from=size.id) 
				inner join catalog on itemsize.series=catalog.size_series
				where size_lineup=1 and color_lineup=1 and itemsizeapply<='%s' and itemsizedate>'%s' and catalogapply<='%s' and catalogdate>'%s' and itemsize.item_id=%d and color_code='%s' 
				order by size_row",
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
	*	商品カラーを返す
	*	@id				アイテムID
	*	@curdate		抽出条件に使用する日付(0000-00-00)。NULL:今日
	*	@mode			id:アイテムID(default), code:アイテムコード
	*
	*	@return			[id:color_id, code:color_code, name:color_name]
	*/
	public function getItemcolor($id, $curdate=NULL, $mode='id'){
		try{
			if($mode=='code') $id=$this->getItemID($id);
			if(empty($id)) return null;
			$conn = db_connect();
			$curdate = $this->validdate($curdate);
			$sql = sprintf("select color_id as id, color_code as code, color_name as name from catalog inner join itemcolor on color_id=itemcolor.id 
			where color_lineup=1 and catalogapply<='%s' and catalogdate>'%s' and item_id=%d order by color_code",
			$curdate, $curdate, $id);
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
	*	商品価格を返す
	*	@id				アイテムID
	*	@curdate		抽出条件に使用する日付(0000-00-00)。NULL:今日
	*	@mode			id:アイテムID(default), code:アイテムコード
	*	@amount			量販単価の判別 0-149枚、150-299枚、300枚以上
	*
	*	@return			[sizeid:size_from, price_color:price_0, price_white:price_1, maker_color:price_0, maker_white:price_1]
	*/
	public function getItemprice($id, $curdate=NULL, $mode='id', $amount=NULL){
		try{
			if($mode=='code') $id=$this->getItemID($id);
			if(empty($id)) return null;
			$conn = db_connect();
			$curdate = $this->validdate($curdate);
			
			// 消費税
			$tax = self::salestax($conn, $curdate);
			$tax /= 100;
			
			// メーカー「ザナックス」には量販価格を適用しない
			$sql = sprintf("select maker_id from item where itemapply<='%s' and itemdate>'%s' and id=%d", $curdate, $curdate, $id);
			$result = exe_sql($conn, $sql);
			$rec = mysqli_fetch_assoc($result);
			if(!is_null($amount) && $rec['maker_id']!=10 && $amount>149){
				if($amount<300){
					$margin = _MARGIN_1;
				}else{
					$margin = _MARGIN_2;
				}
				$sql = sprintf("select size_from as sizeid, truncate(price_0 * %F * (1+".$tax.")+9,-1) as price_color, 
				truncate(price_1 * %F * (1+".$tax.")+9,-1) as price_white, price_maker_0 as maker_color, price_maker_1 as maker_white 
				from itemprice  where itempriceapply<='%s' and itempricedate>'%s' and item_id=%d order by size_from",
				$margin, $margin, $curdate, $curdate, $id);
			}else{
				$sql = sprintf("select size_from as sizeid, truncate(price_0*margin_pvt*(1+".$tax.")+9,-1) as price_color, 
				truncate(price_1*margin_pvt*(1+".$tax.")+9,-1) as price_white, price_maker_0 as maker_color, price_maker_1 as maker_white 
				from itemprice  where itempriceapply<='%s' and itempricedate>'%s' and item_id=%d order by size_from",
				$curdate, $curdate, $id);
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
	*	プリント位置画像の相対パスのフォルダー名をを返す
	*	@id				ID
	*	@curdate		抽出条件に使用する日付(0000-00-00)。NULL:今日
	*	@mode			id:アイテムID(default), code:アイテムコード, pos:プリントポジションID
	*
	*	@return			[id: position_id, category:category_type, item:item_type, pos:position_type]　idがNULLのときは全て
	*/
	public function getPrintposition($id=NULL, $curdate=NULL, $mode='id'){
		try{
			if($mode=='code') $id=$this->getItemID($id);
			$conn = db_connect();
			$curdate = $this->validdate($curdate);
			if(empty($id)){
				$sql = "select id, category_type as category, item_type as item, position_type as pos from printposition";
			}else if($mode=='pos'){
				$sql= sprintf("select id, category_type as category, item_type as item, position_type as pos 
				from printposition where printposition.id=%d", $id);
			}else{
				$sql= sprintf("select printposition_id as id, category_type as category, item_type as item, position_type as pos 
				from item inner join printposition on item.printposition_id=printposition.id 
			 	where lineup=1 and itemapply<='%s' and itemdate>'%s' and item.id=%d", 
			 	$curdate, $curdate, $id);
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
	 * 新絵型と旧絵型のプリント箇所の対応データ
	 * @param {int} posid 絵型ID
	 * @param {string} face 絵型面{@code front|back|side}
	 * @param {string} name プリント箇所の名称
	 * @return {array} [area:旧プリント箇所名 code:旧プリント箇所コード]
	 */
	public function matchPattern($posid, $face, $name) {
		try{
			if (empty($posid)) throw new Exception();
			
			$conn = db_connect();
			$sql = sprintf("select * from patternmatch where posid=%d and face='%s' and name='%s';", $posid, $face, $name);
			$result = exe_sql($conn, $sql);
			$res = mysqli_fetch_assoc($result);
		}catch(Exception $e){
			$res = array();
		}
		mysqli_close($conn);

		return $res;
	}
		
		
	// テスト用
//	public function getPrintPattern($id) {
//		try{
//			$conn = db_connect();
//
//			$sql = sprintf("select printposition.id as id, category_type as category, item_type as item, position_type as pos, 
//			front.name_list as front, back.name_list as back, side.name_list as side from (printposition 
//			left join printpattern as front on frontface=front.id) 
//			left join printpattern as back on backface=back.id 
//			left join printpattern as side on sideface=side.id where printposition.id=%d group by printposition.id", $id);
//			
//			$result = exe_sql($conn, $sql);
//			while ($rec = mysqli_fetch_assoc($result)) {
//				$res[] = $rec;
//			}
//			
//		}catch(Exception $e){
//			$res = array();
//		}
//		mysqli_close($conn);
//
//		return $res;
//	}
	
	
	
	/**
	*	商品の属性データとカラーごとの商品コードを返す
	*	@id				アイテムID
	*	@curdate		抽出条件に使用する日付(0000-00-00)。NULL:今日
	*
	*	@return			['name':[item_code:item_name], 'category':[category_key:category_name] code:[code:color_name, ...], 'size':[code:[size_id:size_name, ...], ...], 'ppid':printposition_id, 'maker':maker_id]
	*					codeのフォーマットは、「アイテムコード＿カラーコード」　ex) 085-cvt_001
	*/
	public function getItemAttr($id, $curdate=NULL){
		try{
			if(empty($id)) return null;
			$conn = db_connect();
			$curdate = $this->validdate($curdate);
			$sql = sprintf("select category_id,category_key,category_name,item_code,item_name,concat(item_code,'_',color_code) as code,color_name,size_from,size_name,printposition_id,maker_id from 
			((((item inner join catalog on item.id=catalog.item_id) 
			inner join itemcolor on catalog.color_id=itemcolor.id) 
			inner join category on catalog.category_id=category.id) 
			inner join itemsize on catalog.size_series=itemsize.series) 
			inner join size on itemsize.size_from=size.id 
			where show_site like "."'%%".$_REQUEST['show_site']."%%'"." 
			and lineup=1 and color_lineup=1 and size_lineup=1 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' and itemsizeapply<='%s' and itemsizedate>'%s' and item.id=%d order by color_code, size_row", 
			$curdate, $curdate, $curdate, $curdate, $curdate, $curdate, $id);
			
			$result = exe_sql($conn, $sql);
			while ($rec = mysqli_fetch_assoc($result)) {
				$tmp1[$rec['code']] = $rec['color_name'];
				$tmp2[$rec['code']][$rec['size_from']] = $rec['size_name'];
			}
			mysqli_data_seek($result, 0);
			$row = mysqli_fetch_assoc($result);
			$res = array('id'=>$row['category_id'], 'name'=>array($row['item_code']=>$row['item_name']), 'category'=>array($row['category_key']=>$row['category_name']), 'code'=>$tmp1, 'size'=>$tmp2, 'ppid'=>$row['printposition_id'], 'maker'=>$row['maker_id']);
		}catch(Exception $e){
			$res = null;
		}

		mysqli_close($conn);

		return $res;
	}
	
	
	/**
	*	商品のカラーを指定してサイズごとの価格を返す
	*	@id				アイテムID
	*	@color			カラーコード　''の場合はカラーコードの昇順で最初のカラーを使用する
	*	@curdate		抽出条件に使用する日付(0000-00-00)。NULL:今日
	*
	*	@return			[master_id, id:size_id, name:size_name, cost:cost, series:size_series, stock:stock_volume,
	* 					printarea_1,printarea_2,printarea_3,printarea_4,printarea_5,printarea_6,printarea_7]
	*					itemid 112トートバッグ、212スクエアトートはナチュラルが最安価格
	*/
	public function getSizePrice($id, $color, $curdate=NULL){
		try{
			if(empty($id)) return null;
			$conn = db_connect();
			$curdate = $this->validdate($curdate);
			
			if(empty($color)){
				$sql = sprintf("select color_id, color_name, color_code from itemcolor inner join catalog on itemcolor.id=catalog.color_id 
				where color_lineup=1 and catalogapply<='%s' and catalogdate>'%s' and item_id=%d order by color_code limit 1", $curdate, $curdate, $id);
			}else{
				$color = quote_smart($conn, $color);
				$sql = sprintf("select color_id, color_name, color_code from itemcolor inner join catalog on itemcolor.id=catalog.color_id 
				where color_lineup=1 and catalogapply<='%s' and catalogdate>'%s' and item_id=%d and color_code='%s'", $curdate, $curdate, $id, $color);
			}
			$result = exe_sql($conn, $sql);
			$rec = mysqli_fetch_assoc($result);
			if($rec['color_id']==59){
				$fldPrice='price_1';
//			}else if($rec['color_id']==42 && ($id==112 || $id==212)){
//				$fldPrice='price_1';
			}else{
				$fldPrice='price_0';
				
			}
			$colorcode = $rec['color_code'];
			
			// 消費税
			$tax = self::salestax($conn, $curdate);
			$tax /= 100;
			
			$sql = sprintf("select catalog.id as master_id, size.id as id, size_name as name, truncate(%s*margin_pvt*(1+".$tax.")+9,-1) as cost, series, stock_volume as stock, 
			printarea_1,printarea_2,printarea_3,printarea_4,printarea_5,printarea_6,printarea_7 from 
			(((catalog inner join itemsize on catalog.size_series=itemsize.series) 
			inner join itemprice on itemprice.size_from=itemsize.size_from) 
			inner join size on itemsize.size_from=size.id) 
			left join itemstock on itemsize.item_id=stock_item_id and itemsize.size_from=stock_size_id and catalog.id=stock_master_id 
			where color_lineup=1 and itemsize.size_lineup=1 and catalogapply<='%s' and catalogdate>'%s' and itemsizeapply<='%s' and itemsizedate>'%s' and itempriceapply<='%s' and itempricedate>'%s' and 
			catalog.item_id=%d and catalog.color_code='%s' and itemprice.item_id=catalog.item_id group by itemsize.size_from order by series, size_row", 
			$fldPrice, $curdate, $curdate, $curdate, $curdate, $curdate, $curdate, $id, $colorcode);
			$result = exe_sql($conn, $sql);
			while ($rec = mysqli_fetch_assoc($result)) {
				$res[] = $rec;
			}
			
			//usort($res, array('Master', 'sort_size'));
			
		}catch(Exception $e){
			$res = null;
		}

		mysqli_close($conn);

		return $res;
	}
	
	
	
	/**
	*	商品カテゴリーまたはタグを指定して商品情報を返す
	*	@id				カテゴリID, カテゴリーキー, アイテムIDの配列
	*	@curdate		抽出条件に使用する日付(0000-00-00)。NULL:今日
	*	@mode			id:カテゴリID(default), code:カテゴリーキー, tag:全商品からタグで抽出
	*					当該カテゴリでタグによる抽出は、@id:カテゴリID, @mode:タグID
	*
	*	@return			[category_key,item_id,item_name,item_code,size_id,size_from,size_to,colors,cost,printposition_id,item_row,maker_id][...]
	*					costは最安価格
	*/
	public function getCategories($id, $curdate=NULL, $mode='id',$show_site){
			$show_site = $_REQUEST['show_site'];
		try{
			if(empty($id)) return null;
			$conn = db_connect();
			$curdate = $this->validdate($curdate);
			
			// 消費税
			$tax = self::salestax($conn, $curdate);
			$tax /= 100;
			
			if($mode=='code'){
				$sql = sprintf("select category_key, item.id as item_id, item_name, item_code, size.id as size_id, size_name as size_from, count(distinct(catalog.id))-1 as colors, 
				truncate(price_1*margin_pvt*(1+".$tax.")+9,-1) as cost, item.printposition_id as pos_id, item_row, maker_id from 
				 (((item inner join catalog on item.id=catalog.item_id) 
				 inner join itemprice on item.id=itemprice.item_id) 
				 inner join category on catalog.category_id=category.id)
				 inner join size on itemprice.size_from=size.id 
				 where lineup=1 and color_lineup=1 and size_lineup=1 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s' and category_key='%s' 
				 group by catalog.item_id, itemprice.size_from order by item_row, item.id, size.id", 
				 $curdate, $curdate, $curdate, $curdate, $curdate, $curdate, $id);
			}else if($mode=='tag'){
				$sql = sprintf("select category_key, item.id as item_id, item_name, item_code, size.id as size_id, size_name as size_from, count(distinct(catalog.id))-1 as colors, 
				truncate(price_1*margin_pvt*(1+".$tax.")+9,-1) as cost, item.printposition_id as pos_id, item_row, maker_id from 
				 ((((item inner join catalog on item.id=catalog.item_id) 
				 inner join itemprice on item.id=itemprice.item_id) 
				 inner join category on catalog.category_id=category.id)
				 inner join size on itemprice.size_from=size.id)
				 left join itemtag on item.id=itemtag.tag_itemid
				 where lineup=1 and color_lineup=1 and size_lineup=1 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s' and itemtag.tag_id=%d 
				 group by item.id, itemprice.size_from order by item_row, item.id, size.id", 
				 $curdate, $curdate, $curdate, $curdate, $curdate, $curdate, $id);
			}else if(is_array($id)){
			// itemOfで使用
				$sql = sprintf("select item.show_site, category_key, category_name, item.id as item_id, item_name, item.item_code as item_code, size_from, size_name, 
				truncate(price_1*margin_pvt*(1+".$tax.")+9,-1) as cost, truncate(price_maker_1*(1+".$tax.")+9,-1) as makercost, item.printposition_id as pos_id, 
				item_row, maker_id, oz, count(distinct(catalog.id)) as colors,
				i_color_code, i_caption from 
				 (((((item inner join catalog on item.id=catalog.item_id) 
				 inner join itemprice on item.id=itemprice.item_id) 
				 inner join category on catalog.category_id=category.id)
				 inner join size on size_from=size.id)
				 left join itemdetail on item.item_code=itemdetail.item_code)
				 where show_site like "."'%%".$_REQUEST['show_site']."%%'"." 
				 and lineup=1 and color_lineup=1 and size_lineup=1 and catalog.color_code!='000' and catalogapply<='%s' and catalogdate>'%s' and 
				 itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s'",
				 $curdate, $curdate, $curdate, $curdate, $curdate, $curdate);
				$sql .= " and item.id in (".implode(',', $id).")";
				$sql .= " group by item.id, size_from order by item_row, item.id, size_from";
			} else if($mode=='id') {
				// itemOfでカテゴリID指定の場合
				$sql = sprintf("select item.show_site, category_key, category_name, item.id as item_id, item_name, item.item_code as item_code, size_from, size_name, 
				truncate(price_1*margin_pvt*(1+".$tax.")+9,-1) as cost, truncate(price_maker_1*(1+".$tax.")+9,-1) as makercost, item.printposition_id as pos_id, 
				item_row, maker_id, oz, count(distinct(catalog.id)) as colors,
				i_color_code, i_caption from 
				 (((((item inner join catalog on item.id=catalog.item_id) 
				 inner join itemprice on item.id=itemprice.item_id) 
				 inner join category on catalog.category_id=category.id)
				 inner join size on size_from=size.id)
				 left join itemdetail on item.item_code=itemdetail.item_code)
				 where show_site like "."'%%".$_REQUEST['show_site']."%%'"." 
				 and lineup=1 and color_lineup=1 and size_lineup=1 and catalog.color_code!='000' and catalogapply<='%s' and catalogdate>'%s' and 
				 itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s' and category_id=%d",
				 $curdate, $curdate, $curdate, $curdate, $curdate, $curdate, $id);
				$sql .= " group by item.id, size_from order by item_row, item.id, size_from";
			}else if(is_array($mode)){
				if( !empty($mode[2]) && $mode[2]==4 && !empty($mode[1]) ){
				/*
				*	スポーツウェアのタグ指定
				*	ドライで且つ指定タグ、またはスポーツウェアで指定タグのあるアイテムを抽出
				*/
					$sql = sprintf('select item.id as itemid from (item
					 inner join itemtag on item.id=tag_itemid)
					 inner join catalog on item.id=catalog.item_id
					 where lineup=1 and color_lineup=1 and ( (tag_id=%d and item.id=any(select tag_itemid from itemtag where tag_itemid=item.id and tag_id=%d))
					 or (category_id=%d and itemtag.tag_id=%d) )
					 group by item.id', $mode[0], $mode[1], $id, $mode[1]);
					$result = exe_sql($conn, $sql);
					while($r = mysqli_fetch_assoc($result)){
						$tmp[] = $r['itemid'];
					}
					
					$sql = sprintf("select category_key, item.id as item_id, item_name, item_code, size.id as size_id, size_name as size_from, 
					truncate(price_1*margin_pvt*(1+".$tax.")+9,-1) as cost, item.printposition_id as pos_id, item_row, maker_id from 
					 ((((item inner join catalog on item.id=catalog.item_id) 
					 inner join itemprice on item.id=itemprice.item_id) 
					 inner join category on catalog.category_id=category.id)
					 inner join size on itemprice.size_from=size.id)
					 left join itemtag on item.id=itemtag.tag_itemid
					 where lineup=1 and color_lineup=1 and size_lineup=1 and catalog.color_code!='000' and catalogapply<='%s' and catalogdate>'%s' and 
					 itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s'",
					 $curdate, $curdate, $curdate, $curdate, $curdate, $curdate);
					$sql .= ' and item.id in ('.implode(',', $tmp).')';
					$sql .= ' group by item.id, itemprice.size_from, catalog.color_code order by item_row, item.id, size.id';
				}else{
					$sql = sprintf("select category_key, item.id as item_id, item_name, item.item_code as item_code, size.id as size_id, size_name as size_from, 
					truncate(price_1*margin_pvt*(1+".$tax.")+9,-1) as cost, item.printposition_id as pos_id, item_row, maker_id, 
					 i_color_code, i_caption from 
					 (((((item inner join catalog on item.id=catalog.item_id) 
					 inner join itemprice on item.id=itemprice.item_id) 
					 inner join category on catalog.category_id=category.id)
					 inner join size on itemprice.size_from=size.id)
					 left join itemtag on item.id=itemtag.tag_itemid)
					 left join itemdetail on item.item_code=itemdetail.item_code
					 where lineup=1 and color_lineup=1 and size_lineup=1 and catalog.color_code!='000' and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s'",
					 $curdate, $curdate, $curdate, $curdate, $curdate, $curdate);
					 $sql .= ' and (itemtag.tag_id='.$mode[0];
					 if(isset($mode[1])){
					 	if(empty($mode[2])){
					 	// 2種類のタグのどちらかに合致
					 		$sql .= ' or itemtag.tag_id='.$mode[1].')';
					 	}else if($mode[2]=='and'){
					 	// 2種類のタグ双方に合致
					 		$sql .= ' and item.id=any(select tag_itemid from itemtag where tag_itemid=item.id and tag_id='.$mode[1].'))';
					 	}else{
					 		if($mode[1]==0 && $mode[2]==4){
					 		// ドライとスポーツウェア「全て」
					 			$sql .= ' or category.id='.$id.')';
					 		}else if($mode[1]>0){
					 		// 指定カテゴリで且つ2種類のタグのどちらかに合致
					 			$sql .= ' or  itemtag.tag_id='.$mode[1].') and category.id='.$mode[2];
					 		}else{
					 		// ドライのみ
					 			$sql .= ')';
					 		}
					 	}
					 }else{
					 	$sql .= ')';
					 }
					 $sql .= ' group by item.id, itemprice.size_from, catalog.color_code order by item_row, item.id, size.id';
				}
			}else if(preg_match('/^[1-9][0-9]*$/', $mode)){
				$sql = sprintf("select category_key, item.id as item_id, item_name, item.item_code as item_code, size.id as size_id, size_name as size_from, count(distinct(catalog.id))-1 as colors, 
				truncate(price_1*margin_pvt*(1+".$tax.")+9,-1) as cost, item.printposition_id as pos_id, item_row, maker_id,
				 i_color_code, i_caption from 
				 (((((item inner join catalog on item.id=catalog.item_id) 
				 inner join itemprice on item.id=itemprice.item_id) 
				 inner join category on catalog.category_id=category.id)
				 inner join size on itemprice.size_from=size.id)
				 left join itemtag on item.id=itemtag.tag_itemid)
				 left join itemdetail on item.item_code=itemdetail.item_code
				 where show_site like "."'%%".$show_site."%%'"." 
				 and lineup=1 and color_lineup=1 and size_lineup=1 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s' and category_id=%d and itemtag.tag_id=%d 
				 group by item.id, itemprice.size_from order by item_row, item.id, size.id", 
				 $curdate, $curdate, $curdate, $curdate, $curdate, $curdate, $id, $mode);
			}else{
				$sql = sprintf("select category_key, item.id as item_id, item_name, item.item_code as item_code, size.id as size_id, size_name as size_from, count(distinct(catalog.id))-1 as colors, 
				truncate(price_1*margin_pvt*(1+".$tax.")+9,-1) as cost, item.printposition_id as pos_id, item_row, maker_id, i_color_code, i_caption from 
				 ((((item inner join catalog on item.id=catalog.item_id) 
				 inner join (
				 select item_id,size_from,size_to,price_1,margin_pvt,
				 size_lineup,itempriceapply,itempricedate from itemprice GROUP by item_id,size_from,size_to,price_1,margin_pvt,size_lineup,itempriceapply,itempricedate ) as itemprice_temp  on item.id=itemprice_temp.item_id) 
				 inner join category on catalog.category_id=category.id)
				 inner join size on itemprice_temp.size_from=size.id) 
				 left join itemdetail on item.item_code=itemdetail.item_code 
				 where show_site like "."'%%".$show_site."%%'"." 
				 and lineup=1 and color_lineup=1 and size_lineup=1 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s' and itempriceapply<='%s' and itempricedate>'%s' and category_id=%d 
				 group by item.id, itemprice_temp.size_from order by item_row, item.id, size_row", 
				 $curdate, $curdate, $curdate, $curdate, $curdate, $curdate, $id);
			}
			
			$result = exe_sql($conn, $sql);
			if(is_array($id) || $mode=='id'){	// itemOf で使用
//				$brandTagId = array(43,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,70);
				$i = -1;
				while($r = mysqli_fetch_assoc($result)){
					if(empty($res) || $res[$i]['item_name']!=$r['item_name']){
						if(!empty($sizeHash)){
							// サイズのソート
							usort($sizeHash, array('Master', 'sort_size'));
							$lastIdx = count($sizeHash)-1;
							$res[$i]['sizename_from'] = $sizeHash[0]['name'];
							$res[$i]['sizename_to'] = $sizeHash[$lastIdx]['name'];
							$sizeHash = array();
						}
						$tmp = array($r['size_from']=>true);	// サイズ数のカウント用
						$sizeHash[] = array('name'=>$r['size_name']);
						$r['sizename_from'] = $r['size_name'];
						$r['sizename_to'] = $r['size_name'];
						$r['sizecount'] = 1;
						$r['brandtag_id'] = "";
						
						// itemreview
						$sql = sprintf("select count(*) as reviews from itemreview where item_id=%d", $r['item_id']);
						$rev = exe_sql($conn, $sql);
						$review = mysqli_fetch_assoc($rev);
						$r['reviews'] = $review['reviews'];
						
						$res[++$i] = $r;
					}else{
						if(empty($tmp[$r['size_from']])){
							$res[$i]['sizecount'] += 1;
							$tmp[$r['size_from']] = true;
							$sizeHash[] = array('name'=>$r['size_name']);
						}
					}
					
//					if(in_array($r['tag_id'], $brandTagId)){
//						$res[$i]['brandtag_id'] = $r['tag_id'];
//					}
//
//					if($r['tag_type']>0){
//						$res[$i]['tag'][$r['tag_id']] = array("tagtype"=>$r['tag_type'], 
//															  "tagtype_key"=>$r['tagtype_key'],
//															  "tagname"=>$r['tag_name'],
//															  "tagorder"=>$r['tag_order'],
//															  "tagid"=>$r['tag_id'],
//															  );
//					}
				}
				if(!empty($sizeHash)){
					usort($sizeHash, array('Master', 'sort_size'));
					$lastIdx = count($sizeHash)-1;
					$res[$i]['sizename_from'] = $sizeHash[0]['name'];
					$res[$i]['sizename_to'] = $sizeHash[$lastIdx]['name'];
				}
			}else if(is_array($mode)){
				$i = -1;
				while($r = mysqli_fetch_assoc($result)){
					if($res[$i]['item_name']!=$r['item_name']){
						$i++;
						$cursize = $r['size_from'];
						$r['size_to'] = $r['size_from'];
						$r['colors'] = 1;
						$r['sizecount'] = 1;
						$tmp = array($cursize=>true);
						$res[$i] = $r;
					}else{
						if($cursize==$r['size_from']){
							$res[$i]['colors'] += 1;
						}else{
							$res[$i]['size_to'] = $r['size_from'];
							if(empty($tmp[$r['size_from']])){
								$res[$i]['sizecount'] += 1;
								$tmp[$r['size_from']] = true;
							}
						}
					}
				}
			}else if($mode!='id' && $mode!='code'){
				while ($rec = mysqli_fetch_assoc($result)) {
					$res[] = $rec;
				}
			}else{
				$i = -1;
				while($r = mysqli_fetch_assoc($result)){
					if($res[$i]['item_name']!=$r['item_name']){
						$i++;
						$r['size_to'] = $r['size_from'];
						
						// itemreview
						$sql = sprintf("select count(*) as reviews from itemreview where item_id=%d", $r['item_id']);
						$rev = exe_sql($conn, $sql);
						$review = mysqli_fetch_assoc($rev);
						$r['reviews'] = $review['reviews'];
						
						$res[$i] = $r;
					}else{
						$res[$i]['size_to'] = $r['size_from'];
					}
				}
			}
			
		}catch(Exception $e){
			$res = null;
		}

		mysqli_close($conn);

		return $res;
	}
	
	
	
	/**
	*	指定したタグ及びカテゴリのアイテム一覧ページの情報を返す
	*	@id			一覧ページの基底ID - カテゴリID, タグID。　若しくは、アイテムIDまたはカテゴリIDの配列
	*	@tag		タグの配列
	*	@mode		idの種類 - category(default), tag, item
	*	@limit		検索レコード数
	*
	*	@return		[アイテム一覧ページ情報]
	*/
	public function itemOf($id, $tag=null, $mode='category', $limit=0){
		try{
			if(empty($id)) return null;
			$res = array();
			if(is_array($id)){
				// 指定IDまたはタグの全アイテムの集合
				$len = count($id);
				if(is_array($mode)){
					for($t=0; $t<$len; $t++){
						$ids = $this->itemIdOf($id[$t], $tag, $mode[$t], null, $limit);
						$tmp = $this->getCategories($ids);
						$res = array_merge($res, $tmp);
					}
				}else{
					for($t=0; $t<$len; $t++){
						$ids = $this->itemIdOf($id[$t], $tag, $mode, null, $limit);
						$tmp = $this->getCategories($ids);
						$res = array_merge($res, $tmp);
					}
				}
			}else{
				if(empty($tag)){
					if ($mode!="category") {
						$res = $this->itemIdOf($id, $tag, $mode);
					}
				}else{
					// 指定タグ全てが共通部分となるアイテムの集合
					$len = count($tag);
					if($mode!="category"){
						$tmp = $this->itemIdOf($id, null, $mode, $tmp);
						for($t=0; $t<$len; $t++){
							$tmp = $this->itemIdOf($tag[$t], null, $mode, $tmp);
						}
					}else{
						for($t=0; $t<$len; $t++){
							$tmp = $this->itemIdOf($id, $tag[$t], $mode, $tmp);
						}
					}
					$res = $tmp;
				}
				
				if (empty($tag) && $mode=="category") {
					$res = $this->getCategories($id);
				} else if (!empty($res) && is_array($res)) {
					$res = $this->getCategories($res);
				}
			}
		}catch(Exception $e){
			$res = null;
		}
		
		return $res;
	}
	
	
	
	/**
	*	指定したタグ及びカテゴリのアイテムIDを返す
	*	@id			一覧ページの基底ID - カテゴリID, タグID
	*	@tag		タグID
	*	@mode		idの種類 - category(default), tag
	*	@target		検索対象のアイテムIDの配列
	*	@limit		検索レコード数
	*	@curdate	抽出条件に使用する日付(0000-00-00)。NULL:今日(default)
	*
	*	@return		[アイテムID, ...]
	*/
	public function itemIdOf($id, $tag=null, $mode='category', $target=null, $limit=0, $curdate=null){
		try{
			if(empty($id)) return null;
			
			$conn = db_connect();
			
			$curdate = $this->validdate($curdate);
			$sql = "select item_id from ((item ";
			$sql .= " inner join catalog on item.id=catalog.item_id) ";
			$sql .= " left join itemtag on item.id=itemtag.tag_itemid) ";
			$sql .= " left join tags on itemtag.tag_id=tags.tagid ";
			$sql .= " where show_site like "."'%%".$_REQUEST['show_site']."%%'"." 
					and lineup=1 and catalogapply<='%s' and catalogdate>'%s' and itemapply<='%s' and itemdate>'%s'";
			if($mode=='category'){
				$sql .= " and category_id=".quote_smart($conn, $id);
			}else{
				$sql .= " and tag_id=".quote_smart($conn, $id);
			}
			if(!empty($tag)){
				$sql .= " and tag_id=".quote_smart($conn, $tag);
			}
			if(!empty($target)){
				$sql .= " and item_id in(".implode(",", $target).") ";
			}
			$sql .= " group by item.id order by item_row";
			if(!empty($limit)){
				$sql .= " limit ".$limit;
			}
			$sql = sprintf($sql, $curdate, $curdate, $curdate, $curdate);
			$result = exe_sql($conn, $sql);
			if(mysqli_num_rows($result)==0){
				$res = array();
			}else{
				while ($rec = mysqli_fetch_assoc($result)) {
					$res[] = $rec['item_id'];
				}
			}
			
		}catch(Exception $e){
			$res = null;
		}
		mysqli_close($conn);
		
		return $res;
	}
	
	
	
	/*
	*	アイテムタグのマスター情報を返す
	*	@id		タグID
	*
	*	return	{tagid, tag_name, tag_type, tagtype_name, tagtype_key}
	*/
	public function getTagInfo($id){
		try{
			$conn = db_connect();
			$sql = sprintf('select * from tags inner join tagtype on tags.tag_type=tagtype.tagtypeid where tagid=%d', $id);
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
	 * タグの一覧を返す
	 * @id		カテゴリーID | アイテムID | タグID
	 * @mode	category:カテゴリーID(default), item:アイテムID, tag:タグID
	 * @tag		アイテムタグIDの配列
	 * @return タグ情報の配列
	 */
	public function getItemTag($id, $mode='category', $tag=array()){
		try {
			$conn = db_connect();
			$sql = "select tag_itemid, tag_id, tag_name, tag_type, tag_order, tagtype_key, tagtype_name from ";
			
			$l = count($tag);
			if ($l>0) {
				$cnt = 0;
				for ($i=0; $i<$l; $i++) {
					if (!ctype_digit(strval($tag[$i]))) {
						unset($tag[$i]);	// １０進数の数値以外を削除
						$cnt++;
					}
				}
				if ($cnt>0) {
					$l -= $cnt;
					if ($l>0) {
						array_values($tag);
						$sql .= implode('', array_fill(0, $l, '(') );
					}
				} else {
					$sql .= implode('', array_fill(0, $l, '(') );
				}
			}
			
			if ($mode=='category') {
				$sql .= "(((itemtag
					 inner join item on tag_itemid=item.id)
					 inner join catalog on item.id=catalog.item_id)";
			} else if ($mode=='tag') {
				$sql .= "((itemtag
					 inner join (select tag_itemid as tmpid, itemdate from itemtag inner join item on tag_itemid=item.id where tag_id = %d) as tmp on itemtag.tag_itemid=tmpid)";
			} else {
				$sql .= "((itemtag inner join item on tag_itemid=item.id)";
			}
			
			for ($i=0; $i<$l; $i++) {
				$sql .= " inner join (select tag_itemid as tmp{$i}id from itemtag where tag_id = %d) as tmp{$i} on itemtag.tag_itemid=tmp{$i}id)";
			}
			
			$sql .= " inner join tags on itemtag.tag_id=tags.tagid)
				 inner join tagtype on tags.tag_type=tagtype.tagtypeid
				 where";
			
			if ($mode=='category') {
				$sql .= " category_id = %d and itemdate='3000-01-01'";
			} else if ($mode=='tag') {
				$sql .= " tmp.itemdate='3000-01-01'";
			} else {
				$sql .= " tag_itemid = %d and itemdate='3000-01-01'";
			}
			
			$sql .= " group by tag_id order by tag_order";
			
			if ($l>0) {
				if ($mode!='tag') {
//					array_push($tag, $id);
					$tag[] = $id;
				} else {
					array_unshift($tag, $id);
				}
				$sql = vsprintf($sql, $tag);
			} else {
				$sql = sprintf($sql, $id);;
			}
			
			$result = exe_sql($conn, $sql);
			while ($rec = mysqli_fetch_assoc($result)) {
				$res[] = $rec;
			}
		} catch(Exception $e) {
			$res = null;
		}
		mysqli_close($conn);

		return $res;
	}
	
	
	
	/**
	*	商品一覧で使用する基本情報を返す（サイズ数、カラー数、最安価格）
	*	@id				アイテムIDまたはアイテムコード
	*	@curdate		抽出条件に使用する日付(0000-00-00)。NULL:今日
	*	@mode			id:アイテムID(default), code:アイテムコード
	*
	*	@return			{'item_name':item_name, 'sizes':size_count, 'colors':color_count, 'mincost':mincost, 'initcolor':initColor, 'caption':caption}
	*/
	public function getItemPageInfo($id, $curdate=NULL, $mode='id'){
		try{
			if(empty($id)) return null;
			
			$price = $this->getItemprice($id, $curdate, $mode);
			$sizes = $this->getSize($id, NULL, $curdate, $mode);
			$colors = $this->getItemcolor($id, $curdate, $mode);
			
			if($mode!='id') $id = $this->getItemID($id);
			$items = $this->getItem($id, $curdate, 'item');
			for($a=0; $a<count($items); $a++){
				if($items[$a]['id']==$id){
					$itemname = $items[$a]['name'];
					$r = $this->getItemDetail($items[$a]['code']);
					$initColor = $r['i_color_code'];
					$caption = $r['i_caption'];
					break;
				}
			}
			
			$res = array('item_name'=>$itemname, 'sizes'=>count($sizes), 'colors'=>count($colors), 'mincost'=>$price[0]['price_white'], 'initcolor'=>$initColor, 'caption'=>$caption);
			
		}catch(Exception $e){
			$res = null;
		}

		return $res;
	}
	
	
	
	/**
	*	商品詳細ページ情報
	*	@args			アイテムコード
	* 	@mode			'list':color_code and caption, 
	*
	*	@return			{'color_code', 'caption', 'description', 'material', 'silk', 'digit', 'inkjet', 'cutting', 'note_title', 'note'}
	*/
	public function getItemDetail($args, $mode=NULL){
		try{
			$conn = db_connect();
			if($mode=='list'){
				$select = 'select item_code, i_color_code, i_caption from itemdetail';
			}else{
				$select = 'select * from itemdetail';
			}
			if(empty($args)){
				$result = exe_sql($conn, $select);
				while ($rec = mysqli_fetch_assoc($result)) {
					$res[$rec['item_code']] = $rec;
				}
			}else{
				$sql = sprintf($select.' where item_code="%s"', $args);
				$result = exe_sql($conn, $sql);
				$res = mysqli_fetch_assoc($result);
			}
		}catch(Exception $e){
			$res = null;
		}
		mysqli_close($conn);
		
		return $res;
	}
	
	
	
	/**
	*	寸法情報
	*	@args			アイテムコード
	*
	*	@return			{'size_id', 'measure_id', 'dimension', 'size_name'}
	*/
	public function getItemMeasure($args){
		try{
			$conn = db_connect();
			$sql = sprintf('select * from (itemmeasure inner join size on size_id=size.id) inner join measure on measure_id=measureid where item_code="%s" order by measure_row, size_row', $args);
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
	 * プリント方法
	 *
	 * @return {array} プリント方法のコードをキーにしたプリント方法名のハッシュ
	 */
	public function getPrintMethod(){
		try{
			$conn = db_connect();
			$result = exe_sql($conn, 'select printtypeid as id, print_key as code, print_name as name from printtype');
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
	*		在庫数を返す
	*		@master		マスターID
	*		@size		サイズID
	*
	*		return		在庫数、該当するアイテムがない場合はnull
	*/
	public function getItemStock($master, $size){
		try{
			$conn = db_connect();
			$sql = sprintf("select * from itemstock where stock_master_id=%d and stock_size_id=%d", $master, $size);
			$result = exe_sql($conn, $sql);
			if(mysqli_num_rows($result)==0){
				$rs = null;
			}else{
				$rec = mysqli_fetch_assoc($result);
				$rs = $rec['stock_volume'];
			}
		}catch(Exception $e){
			$rs='';
		}
		mysqli_close($conn);
		
		return $rs;
	}
	
	
	
	/**
	*	size id からサイズ名を返す
	*	@id				サイズID
	*
	*	@return			サイズ名
	*/
	public function getSizename($id){
		try{
			if(empty($id)) return null;
			$conn = db_connect();
			$sql = sprintf("select * from size where id=%d", $id);
			$result = exe_sql($conn, $sql);
			$rec = mysqli_fetch_assoc($result);
			$rs = $rec['size_name'];
		}catch(Exception $e){
			$res = null;
		}

		mysqli_close($conn);

		return $res;
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
			$sql= sprintf("select id from item where lineup=1 and itemapply<='%s' and itemdate>'%s' and item_code='%s'", $curdate, $curdate, $itemcode);
			$result = exe_sql($conn, $sql);
			$rec = mysqli_fetch_assoc($result);
			$rs = $rec['id'];
		}catch(Exception $e){
			$rs = null;
		}
		
		mysqli_close($conn);
		
		return $rs;
	}
	
	
	
	/*
	*	ユーザー情報の取得
	*	@id		ユーザーID　defult:null
	*
	*	reutrn	[ユーザー情報]
	*/
	public static function getUserList($id=null) {
		try{
			$conn = db_connect();
			if(empty($id)){
				$sql = 'select * from customer';
			}else{
				$sql = sprintf('select * from customer where id=%d limit 1', $id);
			}
			$rs = array();
			$result = exe_sql($conn, $sql);
			while($res = mysqli_fetch_assoc($result)){
				$rs[] = $res;
			}
		}catch(Exception $e){
			$rs = '';
		}
		
		mysqli_close($conn);
		return $rs;
	}
	
	
	
	/*
	*	お届け先情報の取得
	*	@id		ユーザーID　defult:null
	*
	*	reutrn	[お届け先情報情報]
	*/
	public static function getDeliveryList($id=null) {
		try{
			$conn = db_connect();
			if(empty($id)){
				$sql = 'select * from delivery';
			}else{
				$sql = 'SELECT * FROM orders INNER JOIN delivery ON orders.delivery_id=delivery.id';
				$sql .= ' where customer_id = %d';
				$sql .= ' GROUP BY organization, deliaddr0, deliaddr1, deliaddr2, deliaddr3, deliaddr4';
				
				$sql = sprintf($sql, $id);
			}
			$rs = array();
			$result = exe_sql($conn, $sql);
			while($res = mysqli_fetch_assoc($result)){
				$rs[] = $res;
			}
		}catch(Exception $e){
			$rs = '';
		}
		
		mysqli_close($conn);
		return $rs;
	}
	
	
	
	/*
	*	メールアドレスの存在チェック
	*	@args	[メールアドレス, ユーザーID(default: null)]
	*	return	[ユーザー情報]
	*/
	public static function checkExistEmail($args) {
		try{
			if(empty($args)) return null;
			$conn = db_connect();
			if(empty($args[1])){
				$sql = sprintf('select * from customer where email="%s" limit 1', $args[0]);
			}else{
				$sql = sprintf('select * from customer where email="%s" and id=%d limit 1', $args[0], $args[1]);
			}
			$rs = array();
			$result = exe_sql($conn, $sql);
			while($res = mysqli_fetch_assoc($result)){
				$rs[] = $res;
			}
		}catch(Exception $e){
			$rs = '';
		}
		
		mysqli_close($conn);
		return $rs;
	}
	
	/*
	*	メールアドレスの存在チェック
	*	@args	[メールアドレス, サイトID]
	*	return	[ユーザー情報]
	*/
	public static function checkExistEmail2($email, $reg_site) {
		try{
			if(empty($email) || empty($reg_site)) return null;
			$conn = db_connect();
			$sql = sprintf('select * from customer where email="%s" and reg_site="%s" limit 1', $email, $reg_site);
			$rs = array();
			$result = exe_sql($conn, $sql);
			while($res = mysqli_fetch_assoc($result)){
				$rs[] = $res;
			}
		}catch(Exception $e){
			$rs = '';
		}
		
		mysqli_close($conn);
		return $rs;
	}
		
	
	/**
	 *	資料請求メールの情報を登録する
	 *	@data		登録情報のハッシュ
	 * 
	 *	@return	成功：ID　失敗：null
	 */
	public function setRequestMail($data){
		try{
			if(empty($data)) return null;
			$conn = db_connect();
			mysqli_query($conn, 'BEGIN');
			foreach($data as $key=>$val){
				$info[$key]	= quote_smart($conn, $val);
			}
			$info["requestdate"] = date('Y-m-d H:i:s');
			
			// メールデータの登録
			$sql = sprintf("INSERT INTO requestmail(requester,subject,message,reqmail,reqzip,reqaddr,requestdate,phase,site_id) 
							VALUES('%s','%s','%s','%s','%s','%s','%s',%d,%d)",
							$info["requester"],
							$info["subject"],
							$info["message"],
							$info["reqmail"],
							$info["reqzip"],
							$info["reqaddr"],
							$info["requestdate"],
							1,
							$info["site_id"]
						);
			if(exe_sql($conn, $sql)){
				$rs = mysqli_insert_id();
			}else{
				mysqli_query($conn ,'ROLLBACK');
				return null;
			}
			
		}catch(Exception $e){
			mysqli_query($conn ,'ROLLBACK');
			$rs = null;
		}
		
		mysqli_query($conn, 'COMMIT');
		mysqli_close($conn);
		
		return $rs;
	}
	
	
	
	/**
	 *	商品到着後のアンケート結果を登録する
	 *	@data		登録情報のハッシュ
	 * 
	 *	@return	成功：ID　失敗：null
	 */
	public function setEnquete($data){
		try{
			if(empty($data)) return null;
			$conn = db_connect();
			mysqli_query($conn, 'BEGIN');
			foreach($data as $key=>$val){
				$info[$key]	= quote_smart($conn, $val);
			}
			$info["enq1date"] = date('Y-m-d H:i:s');
			
			// アンケート結果の登録
			$sql = sprintf("INSERT INTO enquete1(ans1,ans2,ans3,ans4,ans5,ans6,ans7,ans8,ans9,ans10,ans11,ans12,ans13,ans14,
			enq1date, customer_number, enq1name, enq1zip, enq1addr) 
							VALUES(%d,'%s',%d,%d,%d,%d,%d,'%s','%s','%s','%s','%s','%s','%d','%s',%d,'%s','%s','%s')",
							$info["a1"],
							$info["a2"],
							$info["a3"],
							$info["a4"],
							$info["a5"],
							$info["a6"],
							$info["a7"],
							$info["a8"],
							$info["a9"],
							$info["a10"],
							$info["a11"],
							$info["a12"],
							$info["a13"],
							$info["a14"],
							$info["enq1date"],
							$info["number"],
							$info["customername"],
							$info["zipcode"],
							$info["addr"]
						);
			
			if(exe_sql($conn, $sql)){
				$rs = mysqli_insert_id();
			}else{
				mysqli_query($conn, 'ROLLBACK');
				return null;
			}
			
		}catch(Exception $e){
			mysqli_query($conn, 'ROLLBACK');
			$rs = null;
		}
		
		mysqli_query($conn, 'COMMIT');
		mysqli_close($conn);
		
		return $rs;
	}
	
	
	
	/**
	 *	受注システムに注文データを登録
	 *	@data		{customer, order, item, print}
	 * 
	 *	@return	成功：true　失敗：null
	 */
	public function setAcceptingOrder($data){
		try{
			if(empty($data)) return null;
			$conn = db_connect();
			mysqli_query($conn, 'BEGIN');
			
			//$info["enq1date"] = date('Y-m-d H:i:s');
			
			// 顧客情報
			if(empty($data["customer"]["customer_id"])){
				// 新規登録ユーザー
				foreach($data["customer"] as $key=>$val){
					$info[$key]	= quote_smart($conn, $val);
				}
				$sql = sprintf("select number from customer where cstprefix='%s' order by number desc limit 1 for update", $info['cstprefix']);
				$result = exe_sql($conn, $sql);
				if(!mysqli_num_rows($result)){
					$number = 1;
				}else{
					$res = mysqli_fetch_assoc($result);
					$number = $res['number']+1;
				}
				
				$sql = sprintf("INSERT INTO customer(number,cstprefix,customername,customerruby,zipcode,addr0,addr1,addr2,addr3,addr4,tel,fax,email,mobmail,
					company,companyruby,mobile,job,customernote)
					VALUES(%d,'%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",
					$number,
					'k',
					$info["customername"],
					$info["customerruby"],
					$info["zipcode"],
					$info["addr0"],
					$info["addr1"],
					$info["addr2"],
					'',
					'',
					$info["tel"],
					'',
					$info["email"],
					'',
					'',
					'',
					'',
					'',
					''
				);
				
				if(exe_sql($sql)){
					$customer_id = mysqli_insert_id();
				}else{
					mysqli_query($conn, 'ROLLBACK');
					return null;
				}
			}else{
				$customer_id = quote_smart($conn, $data["customer"]["customer_id"]);
			}
			
			// 受注情報
			
			// 商品情報
			
			// プリント情報
			
			
			// アンケート結果の登録
			$sql = sprintf("INSERT INTO enquete1(ans1,ans2,ans3,ans4,ans5,ans6,ans7,ans8,ans9,ans10,ans11,ans12,enq1date,customer_number,enq1name,enq1zip,enq1addr) 
							VALUES(%d,'%s',%d,%d,%d,%d,%d,'%s','%s','%s','%s','%s','%s',%d,'%s','%s','%s')",
							$info["a1"],
							$info["a2"],
							$info["a3"],
							$info["a4"],
							$info["a5"],
							$info["a6"],
							$info["a7"],
							$info["a8"],
							$info["a9"],
							$info["a10"],
							$info["a11"],
							$info["a12"],
							$info["enq1date"],
							$info["number"],
							$info["customername"],
							$info["zipcode"],
							$info["addr"]
						);
			
			if(exe_sql($conn,$sql)){
				$rs = mysqli_insert_id();
			}else{
				mysqli_query($conn,'ROLLBACK');
				return null;
			}
			
		}catch(Exception $e){
			mysqli_query($conn,'ROLLBACK');
			$rs = null;
		}
		
		mysqli_query($conn,'COMMIT');
		mysqli_close($conn);
		
		return $rs;
	}
	
	
	
	/**
	 *	お知らせメールの配信停止
	 *	@data		{'customer_id', 'cancel'}
	 *				cancel - 停止:1,　送信:0
	 * 
	 *	@return	成功：true　失敗：null
	 */
	public function unsubscribe($data){
		try{
			if(empty($data)) return null;
			$conn = db_connect();
			mysqli_query($conn, 'BEGIN');
			
			$sql = sprintf('update customer set cancelfollowmail=%d where id=%d limit 1', $data['cancel'], $data['customer_id']);
			$rs = exe_sql($conn, $sql);
			if(!$rs){
				mysqli_query($conn,'ROLLBACK');
				return null;
			}
			
		}catch(Exception $e){
			mysqli_query($conn,'ROLLBACK');
			$rs = null;
		}
		
		mysqli_query($conn.'COMMIT');
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
		if(strtotime($curdate)>=strtotime(_APPLY_TAX_CLASS)) return 0;	// 外税方式
		$sql = sprintf('select taxratio from salestax where taxapply=(select max(taxapply) from salestax where taxapply<="%s")', $curdate);
		$result = exe_sql($conn, $sql);
		$rec = mysqli_fetch_array($result);
		
		return $rec['taxratio'];
	}
	
	
	/**
	*	外税方式の消費税率を返す		一般で_APPLY_TAX_CLASSより前は外税方式適用前のため、0%を返す
	*	見積・納品・請求書の出力ファイルで呼出
	*	@curdate		日付(0000-00-00)
	*	@ordertype		general, industry
	*
	*	return			消費税
	*/
	public function getSalesTax($curdate, $ordertype='general'){
		try{
			$conn = db_connect();
			$curdate = $this->validdate($curdate);
			if(strtotime($curdate)<strtotime(_APPLY_TAX_CLASS) && $ordertype=='general') return 0;	// 外税方式適用前
			$sql = sprintf('select taxratio from salestax where taxapply=(select max(taxapply) from salestax where taxapply<="%s")', $curdate);
			$result = exe_sql($conn, $sql);
			$rec = mysqli_fetch_array($result);
			$rs = $rec['taxratio'];
		}catch(Exception $e){
			$rs="0";
		}
		mysqli_close($conn);
		return $rs;
	}
	
	
	/**
	*	日付の妥当性を確認し不正値は今日の日付を返す
	*	@curdate		日付(0000-00-00)
	*	
	*	@return			0000-00-00
	*/
	public function validdate($curdate){
		if(empty($curdate)){
			$curdate = date('Y-m-d');
		}else{
			$curdate = str_replace("/", "-", $curdate);
			$d = explode('-', $curdate);
			if(checkdate($d[1], $d[2], $d[0])===false){
				$curdate = date('Y-m-d');
			}
		}
		return $curdate;
	}
	
	
	
	/**
	*	サイズ名を変換する
	*	@txt			省略したサイズ名
	*	
	*	@return			サイズ名
	*/
	private function rename_size($txt){
		$size_text = $txt;
		switch($txt){
			case 'GS':	$size_text = "Girl's-S";
						break;
			case 'GM':	$size_text = "Girl's-M";
						break;
			case 'GL':	$size_text = "Girl's-L";
						break;
			case 'JS':	$size_text = 'Jr.S';
						break;
			case 'JM':	$size_text = 'Jr.M';
						break;
			case 'JL':	$size_text = 'Jr.L';
						break;
		}
		return $size_text;
	}
	
	
	
	/**
	*	サイズ名でソート
	*	usortのユーザー定義関数
	*
	*	getSizePrice, getCategories で使用
	*/
	private function sort_size($a, $b){
		$tmp=array(
	    	'70'=>1,'80'=>2,'90'=>3,'100'=>4,'110'=>5,'120'=>6,'130'=>7,'140'=>8,'150'=>9,'160'=>10,
	    	'JS'=>21,'JM'=>22,'JL'=>23,'JF'=>24,
	    	'WS'=>31,'WM'=>32,'WL'=>33,'GS'=>34,'GM'=>35,'GL'=>36,
	    	'SSS'=>41,'SS'=>42,'XS'=>43,
	    	'S'=>44,'M'=>45,'L'=>46,'XL'=>47,
	    	'XXL'=>48,
	    	'O'=>51,'XO'=>52,'2XO'=>53,'YO'=>54,
	    	'3L'=>61,'4L'=>62,'5L'=>63,'6L'=>64,'7L'=>65,'8L'=>66,
			'Free'=>91);
		return ($tmp[$a["name"]] == $tmp[$b["name"]]) ? 0 : ($tmp[$a["name"]] < $tmp[$b["name"]]) ? -1 : 1;
	}
}
?>
