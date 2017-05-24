<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/MYDB2.php';

class Cab Extends MYDB2 {
/**
*	�L���u�ւ�EDI���������N���X
*	charset Shift-JIS
*	log				: 2014-11-14 created
*/
	
	public function __construct(){
	}
	
	
	/*
	*	���t��Z��
	*/
	private $addresslist = array('',
		array(
			'destination'=>'�^�J�n�}���C�t�A�[�g',
			'zipcode'=>'124-0025',
			'addr1'=>'�����s',
			'addr2'=>'�����搼�V����R�|�P�S�|�Q�U',
			'tel'=>'03-5670-0787',
		),
		array(
			'destination'=>'�^�J�n�}���C�t�A�[�g��Q�H��',
			'zipcode'=>'124-0025',
			'addr1'=>'�����s',
			'addr2'=>'�����搼�V����R�|�Q�X�|�V',
			'tel'=>'03-5875-7019',
		),
	);
	
	
	/*
	*	�����t�@�C���̐���
	*	@args {��No., ���t��Z��(1 or 2)}
	*
	*	return	����:�t�@�C���p�X�A�@���s:�G���[�R�[�h
	*/
	public function orderform($args){
		try{
			$record_len = 190;	// ���R�[�h�̃o�C�g���Œ蒷
			$order_id = htmlspecialchars($args['orders_id'], ENT_QUOTES);
			$note = mb_convert_encoding(htmlspecialchars($args['cab_note'], ENT_QUOTES), 'sjis', 'utf-8');
			$addr = $this->addresslist[$args['destination']];
			if(empty($addr)) $addr = $this->addresslist[1];
			$user_number = str_pad($order_id, 18, " ", STR_PAD_RIGHT);
			
			// header
			$header = array(
				"header_class" => "H",
				"user_number" => $user_number,
				"note" => str_pad($note, 40, " ", STR_PAD_RIGHT),
				"payment" => "0000",	// ���ݒ�
				"destination" => str_pad($addr['destination'], 30, " ", STR_PAD_RIGHT),
				"zipcode" => str_pad($addr['zipcode'], 8, " ", STR_PAD_RIGHT),
				"addr0" => str_pad($addr['addr1'], 8, " ", STR_PAD_RIGHT),
				"addr1" => str_pad($addr['addr2'], 30, " ", STR_PAD_RIGHT),
				"addr2" => str_pad("", 30, " ", STR_PAD_RIGHT),
				"tel" => str_pad($addr['tel'], 14, " ", STR_PAD_RIGHT),
				"detail_number" => 1,
				"shortage" => "0",	// �S���ׂ̔����𒆎~
				"EOF" => "\r\n",
			);
			
			// details
			$details = array(
				"detail_class" => "S",
				"user_number" => $user_number,
				"detail_number" => 0,
				"jancode" => 0,
				"item_code" => str_pad("", 7, " ", STR_PAD_RIGHT),
				"color_code" => str_pad("", 4, " ", STR_PAD_RIGHT),
				"size_code" => str_pad("", 2, " ", STR_PAD_RIGHT),
				"amount" => 0,
				"filler" => str_pad("", 135, " ", STR_PAD_RIGHT),
				"EOF" => "\r\n",
			);
			
			$conn = parent::db_connect();
			
			// JAN CODE
			$sql ="select jan_code, amount, customername, opp, package_no from ((((((orders
				 inner join customer on customer_id=customer.id)
				 inner join orderitem on orders.id=orderitem.orders_id)
				 inner join acceptstatus on orders.id=acceptstatus.orders_id)
				 inner join progressstatus on orders.id=progressstatus.orders_id)
				 left join itemstock on master_id=stock_master_id)
				 left join catalog on catalog.id=master_id)
				 left join item on catalog.item_id=item.id
				 where created>'2011-06-05' and progress_id=4 and shipped=1 and stock_maker=2 and orders.id=?
				 and orderitem.size_id=stock_size_id
				 group by master_id, jan_code";
			$stmt_order = $conn->prepare($sql);
			$stmt_order->bind_param("i", $order_id);
			$stmt_order->execute();
			$stmt_order->store_result();
			$rec = parent::fetchAll($stmt_order);
			
			// ���׃f�[�^����
			$data = array();
			$opp_amount = array(
				"small"=>array("jan_code"=>"4527078285956", "amount"=>0),
				"big"=>array("jan_code"=>"4527078285970", "amount"=>0),
			);
			for($i=0; $i<count($rec); $i++){
				if(empty($rec[$i]['jan_code'])){
					throw new Exception('empty code');
				}
				
				foreach($details as $key=>&$val){
					switch($key){
						case "detail_number":
							$val++;
							$data[] = str_pad($val, 4, " ", STR_PAD_RIGHT);
							break;
						case "jancode":
							$data[] = $rec[$i]['jan_code'];
							break;
						case "amount":
							$data[] = str_pad($rec[$i]['amount'], 4, " ", STR_PAD_RIGHT);
							break;
						default:
							$data[] = $val;
					}
				}
				
				// OPP�܂̖����m�F
				if($rec[$i]['package_no']==0){
					if($rec[$i]['opp']==1){
						$opp_amount['small'][amount] += $rec[$i]['amount'];
					}else if($rec[$i]['opp']==2){
						$opp_amount['big'][amount] += $rec[$i]['amount'];
					}
				}
			}
			unset($val);
			
			// OPP�܂̔����f�[�^
			foreach($opp_amount as $key=>$opp){
				if($opp['amount']==0) continue;
				foreach($details as $key=>&$val){
					switch($key){
						case "detail_number":
							$val++;
							$data[] = str_pad($val, 4, " ", STR_PAD_RIGHT);
							break;
						case "jancode":
							$data[] = $opp['jan_code'];
							break;
						case "amount":
							$data[] = str_pad($opp['amount'], 4, " ", STR_PAD_RIGHT);
							break;
						default:
							$data[] = $val;
					}
				}
			}
			unset($val);
			$record_detail = implode("", $data);
			
			// �w�b�_�[�f�[�^����
			$record_header = "";
			$customername = mb_convert_kana($rec[0]['customername'], 'ASKV', 'utf-8');	// �S�p�ɕϊ�
			$customername = mb_substr($customername, 0, 18, 'utf-8');					// �}���`�o�C�g�̐؂�o��
			$customername = mb_convert_encoding($customername, 'sjis', 'utf-8');		// shift_jis�ɕϊ�
			$data = array();
			foreach($header as $key=>$val){
				switch($key){
					/*
					case "note":
						$data[] = str_pad($customername." �l", 40, " ", STR_PAD_RIGHT);
						break;
					*/
					case "detail_number":
						$data[] = str_pad($details['detail_number'], 4, " ", STR_PAD_RIGHT);
						break;
					default:
						$data[] = $val;
				}
			}
			$record_header = implode("", $data);
			
			if(!empty($record_detail)){
				$check = $details['detail_number']*$record_len + $record_len;
				$dir_path = "order";
				/*
				if(!file_exists($dir_path)){
					mkdir($dir_path, 0775);
				}
				chmod($dir_path, 0775);		// umask���w�肳��Ă���ꍇ�ɑΉ�
				*/
				$filename = $dir_path."/M".date('YmdHis');
				$len = file_put_contents($filename, $record_header.$record_detail);
				if($len===false){
					$reply = 4;		// �t�@�C�������Ɏ��s
				}else if($len!=$check){
					$reply = $len;	// �t�@�C����������Ȃ�
				}else{
					$reply = $filename;		// ����
				}
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
	
	
	/*
	*	�w��f�B���N�g�����̃t�@�C�����ċA�I�Ɏ擾
	*	@dir		�f�B���N�g���p�X
	*	@pattern	��������t�@�C���̃p�^�[����\��������idefault is *�j
	*
	*	return	[�t�@�C���p�X]
	*/
	public function getFileList($dir, $pattern="*") {
		$files = glob(rtrim($dir, '/').'/'.$pattern);
		$list = array();
		foreach ($files as $file) {
			if (is_file($file)) {
				$list[] = $file;
			}
			if (is_dir($file)) {
				$list = array_merge($list, getFileList($file.'/'.$pattern));
			}
		}
		
		return $list;
	}
	
	
	
	/*
	*	�����񓚂̓��e��o�^
	*
	*	@orders_id		��No.
	*	@error			�G���[�R�[�h
	*
	*	return 			1:success
	*/
	public function update_response($orders_id, $error){
		$res = 1;
		try{
			if(empty($orders_id)) return;
			if($error!=0){
				$response = 2;	// �G���[�܂��́A���i����
			}else {
				$response = 1;	// ��������
			}
			$conn = parent::db_connect();
			$sql = "update progressstatus set cab_response=? where orders_id=?";
			$stmt = $conn->prepare($sql);
			$stmt->bind_param("ii", $response, $orders_id);
			$stmt->execute();
			
			// ���o�[�W�����̔����t���O
			if($response==1){
				// �����S����ID���擾
				$sql = "SELECT * from progressstatus where orders_id=?";
				$stmt = $conn->prepare($sql);
				$stmt->bind_param("i", $orders_id);
				$stmt->execute();
				$stmt->store_result();
				$rec = parent::fetchAll($stmt);
				
				// �v�����g�i���e�[�u�����X�V
				$sql = "update printstatus set state_0=? where orders_id=?";
				$stmt = $conn->prepare($sql);
				$stmt->bind_param("ii", $rec[0]['ordering'], $orders_id);
				$stmt->execute();
			}
		}catch (Exception $e) {
			$res = "Exception Error; ".$e->getMessage();
		}
		$stmt->close();
		$conn->close();
		
		return $res;
	}
}

?>
