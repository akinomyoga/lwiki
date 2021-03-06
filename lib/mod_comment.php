<?php // -*- mode:php -*-

namespace lwiki\comment;

include_once "lib.flock.php";
include_once "lib.lwiki.php";

// require variable $comment_id

// コメント用のフォーム署名
function _calculate_xsrfhash(){
  global $lwiki_config_fingerPrint;
  global $lwiki_global_userIdentifier;
  return lwiki_hash($lwiki_global_userIdentifier,$lwiki_config_fingerPrint.':XvJiDXWkQXzoQ');
}

$comment_error="";
function comment_echoe($html){
  global $comment_error;
  $comment_error.='<p><b>コメント送信エラー</b>: '.$html.'</p>';
}

// 何れのデータ(.dat/.htm/.last)の書き換え中も $fdat をロックする事にする。
$fdat="./.lwiki/data/$comment_id.dat";
$fhtm="./.lwiki/data/$comment_id.htm";

function output_comment_form(){
  global $pageid;
  global $comment_name;
  global $comment_body;
  global $comment_action;
  global $comment_error;

  $html_captcha=lwiki_auth_generate();
  $action=$comment_action;
  if($action=='')$action=lwiki_link_page($pageid);

  echo '<div id="comment-form" style="width:400px;text-ailgn:center;border:1px solid gray;padding:10px 20px;">';
  if($comment_error!='')echo '  '.$comment_error;
  echo '  <form method="post" action="'.htmlspecialchars($action).'">';
  echo '    <input type="hidden" name="type" value="comment_add" />';
  echo '    <input type="hidden" name="sigma" value="'.htmlspecialchars(_calculate_xsrfhash()).'" />';
  echo '    <p style="margin:0.5em 0;">名前: <input type="text" name="name" size="20" value="'.htmlspecialchars($comment_name).'"/></p>';
  echo '    <textarea name="body" rows="5" style="width:390px;padding:5px;">'.htmlspecialchars($comment_body).'</textarea>';
  echo '    <div style="text-align:center;margin:0.5em;">';
  echo '      '.$html_captcha.'<input type="submit" name="comment_post" value="投稿" />';
  echo '    </div>';
  echo '  </form>';
  echo '</div>';
}

function comment_generate_html($ipaddr,$date,$name,$body,$count){
  // ID
  $hash=lwiki_hash($ipaddr);

  // トリップ
  $p=mb_strpos($name,'#');
  if($p!==false){
    $hash.='◆'.lwiki_hash(mb_substr($name,$p+1),'6cf73f4c8c4d');
    $name=mb_substr($name,0,$p);
  }

  // head
  $html_name='<span class="comment-name">'.$name.'</span>';
  $html_date='<span class="comment-date">'.$date.'</span>';
  $html_hash='<span class="comment-hash">'.$hash.'</span>';
  $html_head='<p class="comment-head" data-comment-number="'.$count.'">'.$html_name.' '.$html_date.' '.$html_hash.'</p>';
  // body
  $html_body=\lwiki\convert\convert($body);
  $html_body=preg_replace('/(^|<br\/><br\/>)(<br\/>)+/','$1',$html_body);
  $html_body='<div class="comment-body">'.$html_body.'</div>';
  $html_holder='<div class="comment-holder" id="lwiki-comment-'.$count.'" data-comment-source="'.htmlspecialchars($body).'">'.$html_head.PHP_EOL.$html_body.'</div>';
  return $html_holder.PHP_EOL;
}

function comment_add(){
  global $flock,$comment_id;

  global $comment_error;
  if(lwiki_auth_check($comment_error)!=0)return false;

  $name=$_POST['name'];
  $body=$_POST['body'];
  $xsrf=$_POST['sigma'];
  if($name==''){
    comment_echoe('名前が空です。');
    return false;
  }else if(mb_substr($name,0,1)=='#'){
    comment_echoe('名前が空です。# の前に少なくとも1文字以上入力して下さい。');
    return false;
  }else if(mb_strlen($name)>100){
    comment_echoe('名前が長すぎです。100文字以下にして下さい。');
    return false;
  }else if($body==''){
    comment_echoe('コメント内容が空です。');
    return false;
  }else if(mb_strlen($body)>5000){
    comment_echoe('コメント内容が長すぎです。分割して投稿して下さい。');
    return false;
  }else if($xsrf!==_calculate_xsrfhash()){
    comment_echoe('フォーム署名が不正です。意図した投稿か確認し再度投稿を試みて下さい。');
    return false;
  }

  global $fdat,$fhtm;
  if(!$flock->lock($fdat)){
    comment_echoe("(comment_add): sorry, failed to lock the file `$fdat'.");
    return false;
  }

  $name=htmlspecialchars($name);
  $ipaddr=$_SERVER["REMOTE_ADDR"];
  $date=@lwiki_datetime();
  $count=$flock->file_increment("./.lwiki/data/$comment_id.count");

  $chklast=$ipaddr.'/'.urlencode($name).'/'.urlencode($body);
  $flast="./.lwiki/data/$comment_id.last";
  if($chklast==@file_get_contents($flast)){
    comment_echoe('二重投稿です');
    $flock->unlock($fdat);
    return false;
  }

  $dat=$ipaddr.'/'.$date.'/'.urlencode($name).'/'.urlencode($body).'/'.$count."\n";
  if(false===@file_put_contents($fdat,$dat,FILE_APPEND)){
    comment_echoe("(comment_add): sorry, failed to append an entry to the file `$fdat'.");
    $flock->unlock($fdat);
    return false;
  }

  // write
  if(!$flock->file_atomic_append($fhtm,comment_generate_html($ipaddr,$date,$name,$body,$count))){
    comment_echoe('(comment_add): sorry, failed to append your comment to the comment.htm file.');
    $flock->unlock($fdat);
    return false;
  }

  file_put_contents($flast,$chklast);
  $flock->unlock($fdat);
  return true;
}

function comment_regenerate(){
  global $fdat,$fhtm,$flock;
  if(!$flock->lock($fdat)){
    comment_echoe('failed to lock the .dat file.');
    return false;
  }

  if(false===($lines=file($fdat))){
    comment_echoe('failed to open the .dat file.');
    return false;
  }

  $html='';
  for($i=0,$iN=count($lines);$i<$iN;$i++){
    $f=explode('/',$lines[$i]);

    $ipaddr=$f[0];
    $date=$f[1];
    $name=urldecode($f[2]);
    $body=urldecode($f[3]);
    $count=$f[4];if(!$count)$count=$i;

    $html.=comment_generate_html($ipaddr,$date,$name,$body,$count);
  }

  if(!$flock->file_atomic_save($fhtm,$html)){
    comment_echoe('failed to write html to the .htm file.');
    $flock->unlock($fdat);
    return false;
  }

  $flock->unlock($fdat);
  return true;
}

?>
