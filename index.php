<?php // -*- mode:php -*-

//---------------------------------------------------------------------------
// 共通関数

const LWIKI_PHP_BEGIN_TAG="<\x3Fphp ";
const LWIKI_PHP_END_TAG="\x3F>";

function lwiki_include_string($html){
  // $html の中に含まれている php ディレクティブを処理したい
  echo eval(LWIKI_PHP_END_TAG.$html.LWIKI_PHP_BEGIN_TAG);
}

function lwiki_hash($key,$salt='4fa52acaf207'){
  return substr(base64_encode(md5('salt+'.$salt.'+'.$key,true)),0,12);
}

function lwiki_split_lines($text){
  return preg_split('/\r?\n|\r/u',$text);
}
function lwiki_canonicalize_linebreaks($text){
  return preg_replace('/[ \t　]*(?:\r?\n|\r)/u',"\n",$text);
}
function lwiki_util_getTail($haystack,$head){
  $h = strlen($head);
  if(substr($haystack,0,$h)===$head)
    return substr($haystack,$h);
  else
    return false;
}

//---------------------------------------------------------------------------
// 認証

function lwiki_auth_securimage_check(&$errorMessage){
  $scode=$_POST['lwiki_simi'];
  if(!$scode||$scode==''){
    $errorMessage='画像認証コードが入力されていません。';
    return 2;
  }

  require_once 'securimage.php';
  $simg=new Securimage();
  if($simg->check($scode)!==true){
    $errorMessage='画像認証に失敗。';
    return 1;
  }
}

function lwiki_auth_securimage_generate(){
  require_once 'securimage.php';
  $opts=array(
    'securimage_path' => '/~murase/php/',
    'image_id' => 'lwiki_simg',
    'image_alt_text' => 'letters',
    'input_id' => 'lwiki_simi',
    'show_audio_button' => false,
    'refresh_alt_text' => '別画像',
    'refresh_title_text' => '別画像',
    'input_text' => '上の文字:');
  return '<div class="securimage-captcha">'.Securimage::getCaptchaHtml($opts).'</div>';
}

//---------------------------------------------------------------------------
// config

$lwiki_config_rewrite=false;

$lwiki_config_timezone=new DateTimeZone('Asia/Tokyo');
function lwiki_datetime($utime){
  // http://qiita.com/Popphron/items/19c5bc6646db99bd3acb
  // - new DateTime() を使用しようとすると timezone が php.ini で指定されていない場合にエラーになる。
  // - '@' 形式で初期化すると $lwiki_config_timezone は使用されない。
  global $lwiki_config_timezone;
  $time=new DateTime(NULL,$lwiki_config_timezone);
  if($utime!==null){
    $time->setTimestamp($utime);
    $time->setTimezone($lwiki_config_timezone);
  }
  return $time->format('Y-m-d H:i:s T');
}

$lwiki_config_commonHeadContent=<<<EOS
EOS;

require_once 'lwiki_config.php';

//---------------------------------------------------------------------------

$lwiki_base_php=$_SERVER['SCRIPT_NAME'];
$lwiki_base_baseDirectoryUrl=preg_replace('/\/index\.php$/','',$lwiki_base_php);
$lwiki_base_resourceDirectoryUrl=$lwiki_base_baseDirectoryUrl.'/res';

function lwiki_link_page($pageid=null,$get=null){
  global $lwiki_config_rewrite;
  if($lwiki_config_rewrite){
    global $lwiki_base_baseDirectoryUrl;
    $url=$lwiki_base_baseDirectoryUrl.'/';
    if($pageid!==null){
      # 1. Apache - AllowEncodedSlashes Off 対策で %2F は / に変換する。
      $pageid=str_replace('%2F','/',$pageid);
      # 2. query string にする時は urlencode (' ' → '+') で良いが、
      #   URI の一部にするときは RFC 3986 (' ' → '%20') にしないと駄目。
      #   特に Apache の mod_rewrite で実行される "URI正規化" で '+' と ' ' が衝突する。
      $pageid=str_replace('+','%20',$pageid);
      $url.=$pageid;
    }
  }else{
    global $lwiki_base_php;
    $url=$lwiki_base_php;
    if($pageid!==null)
      $get='id='.$pageid.($get!==null?'&'.$get:'');
  }

  if($get!==null)$url=$url.'?'.$get;
  return $url;
}

//---------------------------------------------------------------------------

function lwiki_determine_pageid(){
  global $page_title,$pageid;

  # if accessed as http://example.com/wiki/index.php?id=title
  $page_title=@$_GET['id'];
  if($page_title!=''){
    $pageid=urlencode($page_title);
    return;
  }

  # if accessed as http://example.com/wiki/index.php/title
  $page_title=lwiki_util_getTail($_SERVER['PHP_SELF'],$_SERVER['SCRIPT_NAME'].'/');
  if($page_title!==false){
    $pageid=urlencode($page_title);
    return;
  }

  # if accessed as http://example.com/wiki/index.php
  global $lwiki_config_default_pageid;
  $page_title=$lwiki_config_default_pageid;
  $pageid=urlencode($page_title);
}

lwiki_determine_pageid();
$ht_page_title=htmlspecialchars($page_title);
$pageinfo=@file('.lwiki/data/page.'.$pageid.'.info');

function page_modified_date(){
  global $pageid,$pageinfo;
  if($pageinfo!==false){
    $f=explode('/',$pageinfo[0]);
    $ipaddr=$f[0];
    $date=$f[1];
  }else{
    $ipaddr='';
    $date=@lwiki_datetime(@filemtime('.lwiki/data/page.'.$pageid.'.htm'));
  }

  $line=$date;
  if($ipaddr!=null&&$ipaddr!='')
    $line.=' by '.lwiki_hash($ipaddr);
  return $line;
}

$comment_id='comment.'.$pageid;
$comment_error='';

switch(@$_GET['mode']){
case 'edit':
  $partlength=@$_POST['partlength'];

  $page_updated=false;
  if(@$_POST['page_update']){
    require_once ".lwiki/lib/lib.page-edit.php";
    $page_updated=lwiki\edit\page_update();
  }

  if(!$page_updated){
    include ".lwiki/lib/page.edit.php";
  }else{
    header('Location: '.lwiki_link_page($pageid)); // flush post data
  }
  exit;
case 'list':
  include '.lwiki/lib/page.list.php';
  exit;
case 'hist':
  include '.lwiki/lib/page.hist.php';
  exit;
case 'convert': // (preview 等の実装用に content を変換する)
  require_once '.lwiki/lib/lib.lwiki.php';
  require_once '.lwiki/lib/lib.page.php'; /* for \lwiki\page\generate_dynamic_link() */
  lwiki_include_string(lwiki\convert\convert($_POST['content']));
  exit;
default:
  if(@$_GET['hist']!=''){
    include ".lwiki/lib/page.diff.php";
    exit;
  }

  if(@$_GET['command']=='comment-regenerate'){
    require_once ".lwiki/lib/cmd.comment-add.php";
    comment_regenerate();
  }else if(@$_GET['command']=='page-convert'){
    require_once ".lwiki/lib/lib.page-edit.php";
    \lwiki\edit\page_convert();
  }

  // コメント追加
  $comment_name=@$_COOKIE['comment-name'];
  $comment_body="";
  if(@$_POST['type']=='comment_add'){
    require_once ".lwiki/lib/cmd.comment-add.php";
    $comment_added=comment_add();
    if($comment_added){
      $comment_name=$_POST['name'];
      setcookie('comment-name',$comment_name);
      header('Location: '.lwiki_link_page($pageid)); // flush post data
      exit;
    }else{
      $comment_body=$_POST['body'];
    }
  }

  include ".lwiki/lib/page.read.php";
  break;
}

?>
