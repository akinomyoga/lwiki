<?php // -*- mode:php -*-

require_once 'lib.flock.php';
require_once 'lib.ldiff.php';

class lwiki_hist{
  private $fhist;
  private $fhist_name;
  private $m_lines=null;
  private $m_data=null;
  private $m_sources=null;
  public function __construct(){
    global $pageid;
    $this->fhist=".lwiki/data/page.$pageid.hist";
    $this->fhist_name="page.$pageid.hist";
    $this->m_data=array();
    $this->m_sources=array();
  }

  public function lines(){
    return $this->m_lines;
  }

  public $error_message='';
  private function perr($message){
    $this->error_message.="<p><b>lwiki/lib/lib.hist.php(class lwiki_hist)</b>: $message</p>".PHP_EOL;
  }

//-----------------------------------------------------------------------------
// 読み取り

  public function load(){
    return false!==($this->m_lines=@file($this->fhist));
  }

  public function get_fields($index){
    if(($ret=$this->m_data[$index])!==null)return $ret;
    return $this->m_data[$index]=explode('/',$this->m_lines[$index]);
  }

  private function _apply_edits($file,$edits){
    $file2=array();
    $iline=0;
    foreach($edits as $edit){
      if(!preg_match('/^(\d+)(?:,(\d+))?([di])(?: (.*))?$/m',$edit,$m)){
        if($edit=='')continue;
        $this->perr("{$this->fhist_name}: invalid diff data ($edit)!");
        return false;
      }

      // skip
      $a1=$m[1]-1;
      while($iline<$a1)
        array_push($file2,$file[$iline++]);

      if($m[3]==='d'){
        // $m[1],$m[2]d
        $a2=$m[2]-1;
        $a2=$a1;
        if($m[2]!=='')$a2=$m[2]-1;
        $iline=$a2+1;
      }else{
        // $m[1]i $m[4]
        array_push($file2,$m[4]);
      }
    }

    $a1=count($file);
    while($iline<$a1)
      array_push($file2,$file[$iline++]);
    return $file2;
  }

  public function get_source(&$wiki,$index=-1){
    $lines=$this->m_lines;
    if($index==-1)$index=count($lines)-1;

    if(!(0<=$index&&$index<count($lines)))return false;

    $diffs=array();
    $wiki=null;
    for($i=$index;$i>=0;$i--){
      if($this->m_sources[$i]!==null){
        $wiki=$this->m_sources[$i];
        break;
      }

      $f=$this->get_fields($i);
      if(substr($f[2],0,1)!=="!"){
        $wiki=urldecode($f[2]);
        $this->m_sources[$i]=$wiki;
        break;
      }

      array_push($diffs,urldecode(substr($f[2],1)));
    }

    $ndiff=$index-$i;
    if($i<0){
      $this->perr("{$this->fname_hist}: there is no base entry for the diff!");
      return false;
    }else if($ndiff>0){
      // 差分適用
      $ndiff=$index-$i;
      $wiki=ldiff_split_lines($wiki);

      while(++$i<=$index){
        $edits=preg_split('/\n/u',$diffs[$index-$i]);
        $wiki=$this->_apply_edits($wiki,$edits);
        if($wiki===false)return false;
        $this->m_sources[$i]=implode("\n",$wiki);
      }

      $wiki=$this->m_sources[$index];
    }

    return $ndiff;
  }

//-----------------------------------------------------------------------------
// 書き換え

  public function lock(){
    global $flock;
    return $flock->lock($this->fhist);
  }
  public function unlock(){
    global $flock;
    $flock->unlock($this->fhist);
  }
  public function append_line($newline){
    global $flock;
    return $flock->file_atomic_append($this->fhist,$newline);
  }
}

?>
