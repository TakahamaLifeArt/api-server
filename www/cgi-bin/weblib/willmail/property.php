<?php
/**
 * 定数定義ファイル
 * @package willmail
 * @author (c) 2014 ks.desk@gmail.com
 */

// Parameter for put
define('_DB_ID', '5');
define('_API_TOKEN', '18F694AFB2B24A50B63FB1C1F197A159');
define('_URL', 'https://willap.jp/api/rest/1.0.0/customers/'._API_TOKEN.'/'._DB_ID.'/');

// 受注システム
define('_ACCESS_TOKEN', 'dR7cr3cHasucetaYA8Re82xUtHuB3A7a');
define('_HTTP_HEADER_KEY', 'X-TLA-Access-Token');

// Database at 受注システム
define('_DB_HOST', 'localhost');
define('_DB_USER', 'tlauser');
define('_DB_PASS', 'crystal428');
define('_DB_NAME', 'tladata1');
define('_DB_TYPE', 'mysql');

// SQL
$_sql =<<< SQL
select (case when customer.cstprefix='k' then concat('K', lpad(customer.number,6,'0'))
 else concat('G', lpad(customer.number,4,'0')) end) as customer_num,
 (case when customer.cstprefix='k' then '一般' else '業者' end) as order_type,
 customername, company as dept, customernote,
 insert(zipcode, 4, 0, '-') as zipcode, addr0, addr1, addr2,
 tel, email, estimated as total_price, 
 expressfee, (case when expressfee>0 then 1 else 0 end) as express,
 order_amount, schedule1, schedule2, schedule3, schedule4, 
 (case when progress_id=4 then '注文確定' else '問合せ中' end) as order_status,
 purpose, orders.job as job, deliverytime, manuscript, orders.additionalfee as addfee,
 packfee, carriagefee, payment, printfee, designfee, creditfee, exchinkfee, 
 (case when count(orders.id)>1 then 1 else 0 end) as repeater,
 DATE_FORMAT(schedule3, '%Y') as yy
 from ((orders
 inner join acceptstatus on orders.id=acceptstatus.orders_id)
 inner join customer on orders.customer_id=customer.id)
 inner join estimatedetails on orders.id=estimatedetails.orders_id
 where created>'2011-06-05' and (progress_id=1 || progress_id=4) 
 and email!='' and email like '%@%' and email not like '% %'
 and updated_at >= ? and lastmodified < ?
 group by orders.id
 order by cstprefix desc, email, number, schedule3
SQL;
define('_SQL_LAST_MODIFY', $_sql);

$_sql =<<< SQL
select orders_id, customer_id from (orders
 inner join acceptstatus on orders.id=acceptstatus.orders_id)
 inner join customer on customer_id=customer.id
 where created>'2011-06-05' and (progress_id=1 || progress_id=4)
 and email!='' and email like '%@%' and lastmodified >= ?
 group by customer_id
SQL;
define('_SQL_RECENT_ORDER', $_sql);

$_sql =<<< SQL
select orders.id as orderid, (case when customer.cstprefix='k' then concat('K', lpad(customer.number,6,'0'))
 else concat('G', lpad(customer.number,4,'0')) end) as customer_num,
 (case when customer.cstprefix='k' then '一般' else '業者' end) as order_type,
 customername, company as dept, customernote,
 insert(zipcode, 4, 0, '-') as zipcode, addr0, addr1, addr2,
 tel, email, estimated as total_price, 
 expressfee, (case when expressfee>0 then 1 else 0 end) as express,
 order_amount, schedule1, schedule2, schedule3, schedule4, 
 (case when progress_id=4 then '注文確定' else '問合せ中' end) as order_status,
 purpose, orders.job as job, deliverytime, manuscript, orders.additionalfee as addfee,
 packfee, carriagefee, payment, printfee, designfee, creditfee, exchinkfee, 
 print_type, noprint,
 coalesce(case orderitemext.item_id  
 when 100000 then '持込' 
 when 99999 then '転写シート' 
 when 0 then 'その他' 
 else null end, category_name) as item_category, 
 (case when count(orders.id)>1 then 1 else 0 end) as repeater,
 DATE_FORMAT(schedule3, '%Y') as yy
 from ((((((((orders
 inner join acceptstatus on orders.id=acceptstatus.orders_id)
 inner join customer on orders.customer_id=customer.id)
 inner join estimatedetails on orders.id=estimatedetails.orders_id)
 inner join orderprint on orders.id=orderprint.orders_id)
 inner join orderarea on orderprint.id=orderprint_id)
 inner join orderitem on orders.id=orderitem.orders_id)
 left join orderitemext on orderitem.id=orderitem_id)
 left join catalog on catalog.id=master_id)
 left join category on catalog.category_id=category.id
 where created>'2011-06-05' and (progress_id=1 || progress_id=4)
 and email!='' and email like '%@%' and email not like '% %'
SQL;
define('_SQL_REPEAT_ORDER_1', $_sql);

$_sql =<<< SQL
 group by orders.id, print_type, item_category
 order by cstprefix desc, email, number, schedule3
SQL;
define('_SQL_REPEAT_ORDER_2', $_sql);
?>