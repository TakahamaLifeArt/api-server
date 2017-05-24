<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/MYDB2.php';

/**
 *	���s�R�[�h��CRLF�ɂ���t�B���^�N���X�̒�`
 * 	log		: 2016-06-06 created
 */ 
class crlf_filter extends php_user_filter {
    function filter($in, $out, &$consumed, $closing) {
		while ($bucket = stream_bucket_make_writeable($in)) {
		// make sure the line endings aren't already CRLF
			$bucket->data = preg_replace("/(?<!\r)\n/", "\r\n", $bucket->data);
			$consumed += $bucket->datalen;
			stream_bucket_append($out, $bucket);
		}
		return PSFS_PASS_ON;
	}
}

/**
 *	�g���X�ւ�EDI�����p��CSV�t�@�C���̐���(Shift-JIS)
 *	log		: 2014-05-02 toms_size_name��GS,GM,GL���������ߕϊ���JAN�R�[�h���擾�ł��Ȃ����̗�O����
 *			: 2014-06-11 �^���Ǝ�, �y�j�z��, ���j�j���z��, PP�ܗL��, �̎w���ǉ�
 *			: 2014-06-11 ���t��̎w���ǉ�
 *			: 2014-11-15 DB�N���X���X�V
 * 			: 2016-06-06 Linux�ŉ��s�R�[�h��CRLF�ɂ��邽�߃t�B���^�[���g�p
 *			: 2017-03-17 �ڋq�����t���K�i�ɕύX
 */
class Toms Extends MYDB2 {
	
	public function __construct(){
	}

	/*
	*	���t��Z��
	*/
	private $addresslist = array('',
		array(
			'code'=>'14240',
			'destination'=>'�^�J�n�}���C�t�A�[�g',
			'zipcode'=>'124-0025',
			'addr1'=>'�����s',
			'addr2'=>'�����搼�V����R�|�P�S�|�Q�U',
		),
		array(
			'code'=>'242753',
			'destination'=>'�^�J�n�}���C�t�A�[�g��Q�H��',
			'zipcode'=>'124-0025',
			'addr1'=>'�����s',
			'addr2'=>'�����搼�V����R�|�Q�X�|�V',
		),
	);
	
	/*
	*	�����t�@�C���iCSV�j�̐���
	*	@args			{'orders_id':��No., 'deliver':�^���Ǝ�, 'saturday':�y�j�z��, 'holiday':���j�j���z��, 'pack':PP�ܗL��, 'destination':���t��}
	*
	*	return			1:����, 2:�󒍃f�[�^�Ȃ�, 3:JAN�R�[�h�Ȃ�, 4:���̑��G���[
	*/
	public function orderform($args){
		try{
			$reply = 0;
			$shipp_number = 1;
			$row_id = 0;
			$order_date = date('Ymd');
			$record = array();
			$order_id = htmlspecialchars($args['orders_id'], ENT_QUOTES);
			$deliver = sprintf("%02d", $args['deliver']);
			$saturday = empty($args['saturday'])? '': 1;
			$holiday = empty($args['holiday'])? '': 1;
			$addr = $this->addresslist[$args['destination']];
			if(empty($addr)) $addr = $this->addresslist[1];
			
			$conn = parent::db_connect();
			
			// JAN CODE
			$sql = "select jan_code, amount, customername, customerruby, staffname, package_no from (((((orders
					 inner join customer on customer_id=customer.id)
					 inner join orderitem on orders.id=orderitem.orders_id)
					 inner join staff on orders.reception=staff.id)
					 inner join acceptstatus on orders.id=acceptstatus.orders_id)
					 inner join progressstatus on orders.id=progressstatus.orders_id)
					 left join itemstock on master_id=stock_master_id
					 where created>'2011-06-05' and progress_id=4 and shipped=1 and stock_maker=1 and orders.id=?
					 and orderitem.size_id=stock_size_id
					 group by master_id, jan_code";
			$stmt_order = $conn->prepare($sql);
			$stmt_order->bind_param("i", $order_id);
			$stmt_order->execute();
			$stmt_order->store_result();
			$rec = parent::fetchAll($stmt_order);
			
			// OPP�܂̖����m�F
			$pack = empty($rec[0]['package_no'])? 1: 0;
			
			for($i=0; $i<count($rec); $i++){
				if(empty($rec[$i]['jan_code'])){
					throw new Exception('empty code');
				}
				
				$row_id++;
				$customername = mb_convert_kana($rec[$i]['customerruby'], 'ASKV', 'utf-8');	// �S�p�ɕϊ�
				$customername1 = mb_substr($customername, 0, 16, 'utf-8');					// �}���`�o�C�g�̐؂�o��
				$customername1 = mb_convert_encoding($customername1, 'sjis', 'utf-8');		// shift_jis�ɕϊ�
				$customername2 = mb_substr($customername, 0, 12, 'utf-8');					// �}���`�o�C�g�̐؂�o��
				$customername2 = mb_convert_encoding($customername2, 'sjis', 'utf-8');		// shift_jis�ɕϊ�
				$staffname = mb_convert_kana($rec[$i]['staffname'], 'ASKV', 'utf-8');		// �S�p�ɕϊ�
				$staffname = mb_substr($staffname, 0, 16, 'utf-8');							// �}���`�o�C�g�̐؂�o��
				$staffname = mb_convert_encoding($staffname, 'sjis', 'utf-8');				// shift_jis�ɕϊ�
				
				$rs = array();
				$rs[] = "6372";					//  1.�����R�[�h,�Œ�
				$rs[] = "A001";					//  2.���b�Z�[�W���ʎq,�Œ�
				$rs[] = $order_id;				//  3.���q�l�����ԍ�,
				$rs[] = $row_id;				//  4.���q�l�����sNo.,
				$rs[] = $shipp_number;			//  5.�o�גP�ʔԍ�(4��),
				$rs[] = $order_date;			//  6.�����f�[�^���M��,
				$rs[] = "";						//  7.�����񓚃f�[�^���M��,�L�ڕs�v
				$rs[] = "01";					//  8.�����敪2,�Œ�
				$rs[] = "01";					//  9.���t��敪,�������l
				$rs[] = $addr['code'];			// 10.���t��R�[�h,���ߑł��T�[�r�X���g�p
				$rs[] = $addr['destination'];	// 11.���t�戶��,
				$rs[] = $addr['zipcode'];		// 12.���t��X�֔ԍ�,
				$rs[] = $addr['addr1'];			// 13.���t��s���{��,
				$rs[] = $addr['addr2'];			// 14.���t��Z��,
				$rs[] = "";						// 15.���t��S������,
				$rs[] = "";						// 16.���t��S���Җ�,
				$rs[] = "03-5670-0787";			// 17.���t��d�b�ԍ�,
				$rs[] = $staffname;				// 18.�����S���Җ�,
				$rs[] = "";						// 19.�o�׎�敪,
				$rs[] = "";						// 20.�o�׎喼,
				$rs[] = "";						// 21.�o�׎�Z��,
				$rs[] = "";						// 22.�o�׎�d�b�ԍ�,
				$rs[] = $pack;					// 23.OPP��,0:�s�v, 1:�K�v
				$rs[] = "01";					// 24.���i���f�敪,01:�i�`�m�R�[�h, 02:TOMS�R�[�h
				$rs[] = $rec[$i]['jan_code'];	// 25.JAN�R�[�h,
				$rs[] = "";						// 26.TOMS���i�R�[�h,
				$rs[] = "";						// 27.TOMS�J���[�R�[�h,
				$rs[] = "";						// 28.TOMS�T�C�Y�R�[�h,
				$rs[] = $rec[$i]['amount'];		// 29.������,
				$rs[] = $customername1."�@�l";	// 30.���ה��l,�ڋq��
				$rs[] = "";						// 31.�݌Ɉ�����,�L�ڕs�v
				$rs[] = "";						// 32.���i��,�L�ڕs�v
				$rs[] = "";						// 33.���ח\���1,�L�ڕs�v
				$rs[] = "";						// 34.���ח\�萔1,�L�ڕs�v
				$rs[] = "";						// 35.���ח\���2,�L�ڕs�v
				$rs[] = "";						// 36.���ח\�萔2,�L�ڕs�v
				$rs[] = "";						// 37.��tNo.,�L�ڕs�v
				$rs[] = "02";					// 38.���i���f�P��,�����P��
				$rs[] = "02";					// 39.���i������,�S���s�v
				$rs[] = "2";					// 40.�����o�׋敪,���Ȃ�
				$rs[] = "1";					// 41.�^����Ўw��,����
				$rs[] = $deliver;				// 42.�^����ЃR�[�h,01:����, 02:���R, 03:���}�g
				$rs[] = "01";					// 43.�^���֎�R�[�h,������
				$rs[] = "";						// 44.�������,
				$rs[] = $saturday;				// 45.�y�j���z���t���O,�\
				$rs[] = $holiday;				// 46.���j���z���t���O,
				$rs[] = $holiday;				// 47.�j���z���t���O,
				$rs[] = "�ߑO���@".$customername2."�l";	// 48.�������l,�ߑO���̎w��ƌڋq��
				$rs[] = "";						// 49.�G���[�R�[�h,�L�ڕs�v
				$rs[] = "";						// 50.�G���[���e,�L�ڕs�v
				$rs[] = "";						// 51.�\���P,
				$rs[] = "";						// 52.�\���Q,
				$rs[] = "";						// 53.�o�ח\���,�L�ڕs�v
				
				$record[] = $rs;
			}
			
			if(!empty($record)){
				$dir_path = "./order".$order_date;
				if(!file_exists($dir_path)){
					mkdir($dir_path, 0707);
				}
				chmod($dir_path, 0707);		// umask���w�肳��Ă���ꍇ�ɑΉ�
				$filename = $dir_path."/order".$order_date."_".$order_id.".csv";
				$fp = fopen($filename, 'w');
				
				// �t�B���^�̓o�^
				stream_filter_register('crlf', 'crlf_filter');
	
				// �o�̓t�@�C���փt�B���^���A�^�@�b�`
				stream_filter_append($fp, 'crlf');
	
				if($fp==false) return 4;
				for($i=0; $i<count($record); $i++){
					fputcsv($fp, $record[$i]);
				}
				fclose($fp);
				$reply = 1;
			}else{
				$reply = 2;
			}
		
		}catch(Exception $e){
			$reply = 3;
		}
		
		$stmt_order->close();
		$conn->close();
		
		return $reply;
	}
}

?>
