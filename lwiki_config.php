<?php // -*- mode:php -*-

// 認証(コメント用)
$comment_authcode=lwiki_hash($_SERVER['REMOTE_ADDR'].':'.$_SERVER['HTTP_USER_AGENT'],'%authhash%');
$comment_authcode_cookie=@$_COOKIE['comment-authcode'];

function lwiki_auth_check(&$errorMessage){
  global $comment_authcode,$comment_authcode_cookie;
  if($comment_authcode_cookie!=$comment_authcode){
    $errno=lwiki_auth_securimage_check($errorMessage);
    if($errno!=0)return $errno;

    setcookie('comment-authcode',$comment_authcode,time()+24*60*60);
    $comment_authcode_cookie=$comment_authcode;
  }

  return 0;
}

function lwiki_auth_generate(){
  global $comment_authcode,$comment_authcode_cookie;
  if($comment_authcode_cookie!=$comment_authcode){
    return lwiki_auth_securimage_generate();
  }

  return '';
}

$LWIKI_URL_AGH='/~murase/agh';
$LWIKI_URL_PDF_ICON=$LWIKI_URL_AGH.'/icons/file-pdf.png';
$lwiki_config_default_pageid='Main Page';

# $lwiki_config_timezone=new DateTimeZone('Asia/Tokyo');

?>
