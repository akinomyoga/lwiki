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

//---------------------------------------------------------------------------
// 認証

require_once 'lwiki_config.php';

//---------------------------------------------------------------------------

// $lwiki_base_baseDirectoryUrl=preg_replace('/\/index\.php$/','',$_SERVER['PHP_SELF']);
// $lwiki_base_resourceDirectoryUrl=$lwiki_base_wikiDirectoryUrl.'/res';
$lwiki_base_resourceDirectoryUrl=preg_replace('/index\.php$/','res',$_SERVER['PHP_SELF']);

$lwiki_page_commonHead=<<<EOS
EOS;

//---------------------------------------------------------------------------

$page_title=@$_GET['id'];
if($page_title==''){
  $page_title=@file_get_contents(".lwiki/data/main");
  if($page_title===false||$page_title=='')
    $page_title=$lwiki_config_default_pageid;
}
$ht_page_title=htmlspecialchars($page_title);
$pageid=urlencode($page_title);
$pageinfo=@file('.lwiki/data/page.'.$pageid.'.info');
function page_modified_date(){
  global $pageid,$pageinfo;
  if($pageinfo!==false){
    $f=explode('/',$pageinfo[0]);
    $ipaddr=$f[0];
    $date=$f[1];
  }else{
    $ipaddr='';
    $date=@date('Y-m-d H:i:s T',@filemtime('.lwiki/data/page.'.$pageid.'.htm'));
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
    header("Location: index.php?id=$pageid"); // flush post data
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
      header("Location: index.php?id=$pageid"); // flush post data
      exit;
    }else{
      $comment_body=$_POST['body'];
    }
  }

  include ".lwiki/lib/page.read.php";
  break;
}

?>
