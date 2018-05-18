<?php
/*
*	FTP Connection
*	log:	2014-10-28 created
*/
class FtpTransmission {
    private $conn;
     
    //接続処理
    function connect($host, $user, $pass, $port=21, $timeout=90){
        try {
            $this->conn = @ftp_connect($host, $port, $timeout);
            if(!$this->conn) throw new Exception('FTP CONNECTION FAILED');
            $result = @ftp_login($this->conn, $user, $pass);
            if(!$result) throw new Exception('AUTHENTICATION FAILED');
        } catch (Exception $e){
            echo $e->getMessage();
            return false;
        }
         
        return true;
    }
     
    //終了処理
    function close(){
        return ftp_close($this->conn);
    }
     
    //PASVモード
    function set_pasv($mode=true){
        return ftp_pasv($this->conn, $mode);
    }
     
    //アップロード
    function upload($file, $to, $mode='auto'){
        if($mode == 'auto') $mode = $this->detect_mode($file);
        $to = (preg_match('/(\\\\|\/)$/', $to)) ? $to . basename($file) : $to . '/' . basename($file);
        $result = ftp_put($this->conn, $to, $file, $mode);
        if(!$result) $result = $to;
        return $result;
    }
     
    //ダウンロード
    function download($file, $to, $mode='auto'){
        if($mode == 'auto') $mode = $this->detect_mode($file);
        $to = (preg_match('/(\\\\|\/)$/', $to)) ? $to . basename($file) : $to . '/' . basename($file);
        $result = ftp_get($this->conn, $to, $file, $mode, 0);
        return $result;
    }
     
    //ファイル一覧を表示
    function nlist($dir){
        return ftp_nlist($this->conn, $dir);
    }
     
    //詳細なファイル一欄を表示
    function rawlist($dir, $win=false){
        $list = array();
        $raw = ftp_rawlist($this->conn, $dir);
         
        $root = $dir;
         
        if($win){
            foreach($raw as $value){
                $parse = preg_split("/ +/", $value, -1, PREG_SPLIT_NO_EMPTY);
                 
                $date = DateTime::createFromFormat('m-d-y h:iA', $parse[0] . ' ' . $parse[1]);
                 
                $list[] = array(
                    'perms'  => null,
                    'number' => null,
                    'owner'  => null,
                    'group'  => null,
                    'size'   => ($parse[2] == '<DIR>') ? null : $parse[2],
                    'month'  => $date->format('M'),
                    'day'    => $date->format('j'),
                    'time'   => $date->format('H:i'),
                    'name'   => $parse[3],
                    'type'   => ($parse[2] == '<DIR>') ? 'directory' : 'file'
                );
            }
        } else {
            foreach($raw as $value){
                $parse = preg_split("/ +/", $value, -1, PREG_SPLIT_NO_EMPTY);
                 
                $list[] = array(
                    'perms'  => $parse[0],
                    'number' => $parse[1],
                    'owner'  => $parse[2],
                    'group'  => $parse[3],
                    'size'   => (preg_match('/^d/', $parse[0])) ? null : $parse[0],
                    'month'  => $parse[5],
                    'day'    => $parse[6],
                    'time'   => $parse[7],
                    'name'   => $parse[8],
                    'type'   => (preg_match('/^d/', $parse[0])) ? 'directory' : 'file'
                );
            }
        }
        return $list;
    }
     
    function current_directory(){
        return ftp_pwd($this->conn);
    }
     
    //ファイル削除
    function delete_file($file){
        return ftp_delete($this->conn, $file);
    }
     
    //フォルダ作成
    function make_directory($dir){
        return ftp_mkdir($this->conn, $dir);
    }
     
    //フォルダ削除
    function remove_directory($dir){
        return ftp_rmdir($this->conn, $dir);
    }
     
    //転送モード検出
    function detect_mode($file){
        $pathinfo = pathinfo($file);
        $extension = isset($pathinfo['extension']) ? strtolower($pathinfo['extension']) : '';
         
        //ASCIIとして扱うファイル
        $ascii = array(
            'asp', 'cgi', 'css', 'csv', 'html', 'htm', 'ini', 'js', 'jsp',
            'log', 'php', 'py', 'pl', 'svg', 'tpl', 'txt', 'xml', 'htaccess', ''
        );
         
        if( in_array($extension, $ascii)){
            return FTP_ASCII;
        } else {
            return FTP_BINARY;
        }
    }
}
