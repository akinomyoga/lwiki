<?php // -*- mode:php -*-

#
# $lwiki_config_fingerPrint
#
#   この変数の値は各 wiki サイト毎に乱数によって決定します。
#   この値は他人に知られてはなりません。この値を用いると、、
#   任意に認証をスキップしたり XSRF 攻撃を行ったりすることが可能になります。
#
#   この値を変更すると一旦全ての session が切れます。
#   また、IP に紐付けられた ID が変化します。
#   それ以外に特に問題は生じません。
#
$lwiki_config_fingerPrint='%authhash%';

// 認証(コメント用)

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

# $lwiki_config_rewrite=false;
# $lwiki_config_timezone=new DateTimeZone('Asia/Tokyo');
# $lwiki_config_commonHeadContent='';

?>
