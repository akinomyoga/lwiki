<?php // -*- mode:js -*-

namespace lwiki\edit;

require_once 'lib.flock.php';
require_once 'lib.lwiki.php';
require_once 'lib.hist.php';
use lwiki_hist;

$lwiki_edit_error="";
function message($title,$html){
  global $lwiki_edit_error;
  $lwiki_edit_error=$lwiki_edit_error.'<p><b>'.$title.'</b>: '.$html.'</p>';
}
function error($html){
  message('Wiki更新エラー',$html);
}

function find_section_range($line,$content,&$i0,&$iN){
  $level=preg_match('/^\*+/u',$line,$m)?strlen($m[0]):0;

  $i0=-1;
  for($i=0;preg_match('/^\*+.*$/mu',$content,$m,PREG_OFFSET_CAPTURE,$i);$i=$iend){
    $hit=$m[0][0];
    $ibeg=$m[0][1];
    $iend=$ibeg+strlen($hit);
    if($hit===$line){
      if($i0>=0){
        message('部分編集不可','※同一の見出し行「<code style="background-color:#ef8;">'.htmlspecialchars($line).'</code>」が複数あるので部分編集はできません。');
        return false;
      }
      $i0=$ibeg;
      $i1=$iend;
    }
  }

  if($i0<0){
    message('部分編集不可','独立した見出し行「<code style="background-color:#ef8;">'.htmlspecialchars($line).'</code>」が存在しないので部分編集はできません。');
    return false;
  }

  $depth=0;
  $i=$iN=$i1;
  for(;preg_match('/^(\*+)|\z|[{}]/mu',$content,$m,PREG_OFFSET_CAPTURE,$i);$i=$iend){
    $hit=$m[0][0];
    $ibeg=$m[0][1];
    $iend=$ibeg+strlen($hit);

    $iN=$ibeg;
    if($hit==='{'){
      $depth++;
    }else if($hit==='}'){
      $depth--;
      if($depth<0)break;
    }else if($ibeg==$iend||$depth==0&&$level>=strlen($m[1][0])){
      break;
    }
  }

  if($iN>0&&substr($content,$iN-1,1)==="\n")$iN--;
  return $i0<$iN;
}

class edit_session_data{
  private $pageid;
  private $partmark;

  private $content;
  private $edithash;
  private $partlength;
  private $initialized=false;

  public function __construct($pageid,$partmark){
    $this->pageid=$pageid;
    $this->partmark=$partmark;
  }

  public function exists(){
    $fwiki=".data/page.".$this->pageid.".wiki";
    return file_exists($fwiki);
  }

  private function create_new(){
    $this->initialized=true;

    $fwiki=".data/page.".$this->pageid.".wiki";
    $this->content=lwiki_canonicalize_linebreaks(@file_get_contents($fwiki));
    if($this->content===false)
      $this->content='';
    else if($this->partmark&&find_section_range($this->partmark,$this->content,$i0,$iN)){
      $this->partlength=$iN-$i0;
      $this->content=substr($this->content,$i0,$this->partlength);
    }
    $this->edithash=lwiki_hash($this->content,'17e3ae721e64dbba');
  }

  private function initialize(){
    if($this->initialized)return;
    $this->initialized=true;
    $this->content=lwiki_canonicalize_linebreaks($_POST['content']);
    $this->edithash=$_POST['edithash'];
    $this->partlength=$_POST['partlength'];

    if(!$this->edithash)
      $this->create_new();
  }

  public function content(){
    $this->initialize();
    return $this->content;
  }
  public function edithash(){
    $this->initialize();
    return $this->edithash;
  }
  public function partlength(){
    $this->initialize();
    return $this->partlength;
  }
  public function is_part(){
    $this->initialize();
    return !!$this->partlength;
  }
}

$edit_session=new edit_session_data($pageid,$_GET['part']);

// 単に wiki→htm 変換を実行する
function page_convert(){
  global $flock,$pageid;

  $fname_wiki=".data/page.$pageid.wiki";
  // ロック
  if(!$flock->lock($fname_wiki)){
    error("sorry, failed to lock the file, 'page.$pageid.wiki'.");
    return false;
  }

  $wiki=lwiki_canonicalize_linebreaks(@file_get_contents($fname_wiki));

  $conv=\lwiki\convert\create_converter();
  $conv->setCustomGenerateEditLink('default');
  $phtml=$conv->convert($wiki);
  if(!$flock->file_atomic_save_locked(".data/page.$pageid.htm",$phtml)){
    error('(page_update): sorry, failed to save html to page.htm.');
    $flock->unlock($fname_wiki);
    return false;
  }

  $flock->unlock($fname_wiki);
  return true;
}

class page_update_proc{
  public function __construct(){}

  private function _page_hist_append($ipaddr,$date,$content,$remarks){
    global $flock,$pageid;

    $ndiff_max=20;

    $encoded_content=urlencode($content);

    $hist=new lwiki_hist;
    if(!$hist->lock()){
      error('page_update (_page_hist_append): failed to lock the page.hist file.');
      return false;
    }

    $newline=null;
    $hindex=-1;
    if($hist->load()){
      $hindex=count($hist->lines());
      if(false!==($ndiff=$hist->get_source($oldwiki))&&$ndiff<$ndiff_max){
        $sed=ldiff_lines_sed($oldwiki,$content);
        $encoded_sed=urlencode(trim($sed));
        if(strlen($encoded_sed)+1<strlen($encoded_content))
          $newline=$ipaddr.'/'.urlencode($date).'/!'.$encoded_sed.'/'.urlencode($remarks).PHP_EOL;
      }
    }
    if($newline===null)
      $newline=$ipaddr.'/'.urlencode($date).'/'.$encoded_content.'/'.urlencode($remarks).PHP_EOL;

    if(!$hist->append_line($newline)){
      error("(page_update): sorry, failed to append an entry to page.$pageid.hist nl=($newline).");
      $hist->unlock();
      return false;
    }
    
    $hist->unlock();

    $editlog=$ipaddr.'/'.urlencode($date).'/'.$pageid.'/'.$hindex.PHP_EOL;
    if(!$flock->file_atomic_append_locked(".data/log.edit.txt",$editlog)){
      error('(page_update): sorry, failed to add log to recent edits.');
    }

    if($hist->error_message){
      global $lwiki_edit_error;
      $lwiki_edit_error.=$hist->error_message;
      return false;
    }
    return true;
  }

  public function execute(){
    global $flock,$pageid;

    $ipaddr=$_SERVER["REMOTE_ADDR"];
    $date=date('Y-m-d H:i:s T');

    global $edit_session;
    $content=$edit_session->content();
    $edithash=$edit_session->edithash();

    if(lwiki_auth_check($msg)!=0){
      error($msg);
      return false;
    }

    {
      $fname_wiki=".data/page.$pageid.wiki";
      // ロック
      if(!$flock->lock($fname_wiki)){
        error("sorry, failed to lock the file, 'page.$pageid.wiki'.");
        return false;
      }

      $original_wiki_source=lwiki_canonicalize_linebreaks(@file_get_contents($fname_wiki));

      $param_part=$_GET['part'];
      if($param_part&&find_section_range($param_part,$original_wiki_source,$i0,$iN)){
        // 部分編集
        $original_wiki_head=substr($original_wiki_source,0,$i0);
        $original_wiki_body=substr($original_wiki_source,$i0,$iN-$i0);
        $original_wiki_tail=substr($original_wiki_source,$iN);
      }else{
        // 全体編集
        $original_wiki_head='';
        $original_wiki_body=$original_wiki_source;
        $original_wiki_tail='';
      }

      // check 編集の衝突
      if($edithash!=lwiki_hash($original_wiki_body,'17e3ae721e64dbba')){
        error('編集の衝突が発生しました (別のユーザによる更新が編集中に行われました)。差分を参照して下さい。{'.$i0.','.$iN.'}');
        $flock->unlock($fname_wiki);
        return false;
      }

      // check 変更無し
      if($content==$original_wiki_body){
        error('変更点がありません。');
        $flock->unlock($fname_wiki);
        return false;
      }

      //if(mb_substr($content,-1)!=="\n")$content.="\n";
      $content=$original_wiki_head.$content.$original_wiki_tail;

      // 保存実行
      if(!@file_put_contents($fname_wiki,$content)){
        error('編集データ保存に失敗しました。');
        $flock->unlock($fname_wiki);
        return false;
      }
      $flock->unlock($fname_wiki);
    }

    if(!$this->_page_hist_append($ipaddr,$date,$content,$_POST['remarks']))
      return false;

    $conv=\lwiki\convert\create_converter();
    $conv->setCustomGenerateEditLink('default');
    $content=$conv->convert($content);
    if(!$flock->file_atomic_save_locked(".data/page.$pageid.htm",$content)){
      error('(page_update): sorry, failed to save html to page.htm.');
      return false;
    }

    $info=$ipaddr.'/'.$date.PHP_EOL;
    $info.=urlencode($conv->header_close()).PHP_EOL; // 頁内目次
    $info.=urlencode($conv->tags).PHP_EOL; // タグ
    $info.=urlencode($conv->keywords).PHP_EOL; // 索引

    if(!$flock->file_atomic_save_locked(".data/page.$pageid.info",$info)){
      error('(page_update): sorry, failed to update the page information in page.info.');
    }
    
    return true;
  }
}

// ページ内容を更新する
function page_update(){
  $updater=new page_update_proc();
  return $updater->execute();
}

?>
