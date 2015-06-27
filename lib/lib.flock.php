<?php // -*- mode:js -*-

// http://www.programming-magic.com/20080211020413/ 改
class file_lock{
  private $lockdir;       //ロックファイル用ディレクトリ(最後に/はなし)
  private $timeout;       //タイムアウト時間(秒、float)
  private $sleeptime;     //スリープ時間(秒、float)

  //コンストラクタ
  public function __construct($lockdir = '.', $timeout = 10.0, $sleeptime = 0.1){
    if(substr($lockdir, -1) === '/')
      $lockdir = substr($lockdir, 0, strlen($lockdir)-1);//末尾の/を削る
    $this->lockdir = $lockdir;
    $this->timeout = $timeout;
    $this->sleeptime = $sleeptime;
  }

  //-----------------------------------
  // lock using filesystem
  private function generate_lockfile_name($filename){
    // return $this->lockdir.DIRECTORY_SEPARATOR.basename($filename).'.lock';
    return $this->lockdir.DIRECTORY_SEPARATOR.'!lock.'.urlencode($filename);
  }
  private function fsys_lock($filename){
    $lockfile = $this->generate_lockfile_name($filename);

    //ロックファイルがタイムアウト時間を過ぎて存在し続けていたら削除
    if(file_exists($lockfile)){
      if (microtime(true) - filemtime($lockfile) > $this->timeout)
        $this->unlock($filename);
    }

    //ロックをかける
    $start = microtime(true);
    while(!@mkdir($lockfile, 0755)){
      if(microtime(true) - $start > $this->timeout){
        //タイムアウト時間を過ぎたのでロック失敗
        return false;
      }
      usleep($this->sleeptime * 1000 * 1000);
    }

    return true;
  }
  private function fsys_unlock($filename){
    $lockfile = $this->generate_lockfile_name($filename);
    @rmdir($lockfile);
  }

  private $fpdict=array();
  private function flock_lock($filename){
    $lockfile=$this->generate_lockfile_name($filename).'.flock';
    if(!($fp=fopen($lockfile,'a+')))return false;
    $fpdict[$filename]=$fp;
    flock($fp,LOCK_EX);
  }  
  private function flock_unlock($filename){
    if($fpdict[$filename]){
      fclose($fpdict[$filename]);
      $fpdict[$filename]=null;
    }
  }  
  //-----------------------------------

  //ロック用関数
  public function lock($filename){
    global $lwiki_option_use_flock;
    if($lwiki_option_use_flock)
      return $this->flock_lock($filename);
    else
      return $this->fsys_lock($filename);
  }
  //ロック解除用
  public function unlock($filename){
    global $lwiki_option_use_flock;
    if($lwiki_option_use_flock)
      return $this->flock_unlock($filename);
    else
      return $this->fsys_unlock($filename);
  }

  //---------------------------------------------------------------------------
  // atomic write oprations
  //   他の所から読み出す時に中途半端な内容が読み取られない様に atomic に書き換え。
  //   (ただ読み出す時にはできるだけ lock したくないので。)

  public function file_atomic_save($fname,$content){
    $tmpfile=$fname.'.part';
    return @file_put_contents($tmpfile,$content)!==false
      &&@rename($tmpfile,$fname);
  }

  public function file_atomic_append($fname,$content){
    $tmpfile=$fname.'.part';
    return (!file_exists($fname)||@copy($fname,$tmpfile))
      &&@file_put_contents($tmpfile,$content,FILE_APPEND)!==false
      &&@rename($tmpfile,$fname);
  }

  //---------------------------------------------------------------------------
  
  public function file_atomic_append_locked($fname,$content){
    $ret=false;
    if($this->lock($fname)){
      $ret=$this->file_atomic_append($fname,$content);
      $this->unlock($fname);
    }
    return $ret;
  }

  public function file_atomic_save_locked($fname,$content){
    $ret=false;
    if($this->lock($fname)){
      $ret=$this->file_atomic_save($fname,$content);
      $this->unlock($fname);
    }
    return $ret;
  }

  public function file_increment($fname){
    $value=false;
    if($this->lock($fname)){
      $value=@file_get_contents($fname)+1;
      $tmpfile=$fname.'.part';
      @file_put_contents($tmpfile,$value);
      @rename($tmpfile,$fname);
      $this->unlock($fname);
    }
    return $value;
  }
}

$flock=new file_lock('.lwiki/lock');

?>
