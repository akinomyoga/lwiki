<?php // -*- mode:php -*-

// 認証(コメント用)
$comment_authcode=lwiki_hash($_SERVER['REMOTE_ADDR'].':'.$_SERVER['HTTP_USER_AGENT'],'298b3fe53e13');
$comment_authcode_cookie=@$_COOKIE['comment-authcode'];

function lwiki_auth_check(&$errorMessage){
  global $comment_authcode,$comment_authcode_cookie;
  if($comment_authcode_cookie!=$comment_authcode){
    $scode=$_POST['lwiki_simi'];
    if(!$scode||$scode==''){
      $errorMessage='画像認証コードが入力されていません。';
      return 2;
    }

    require_once 'securimage.php';
    $simg=new Securimage();
    if($simg->check($scode)!==true){
      $errorMessage='画像認証に失敗';
      return 1;
    }
    setcookie('comment-authcode',$comment_authcode,time()+24*60*60);
    $comment_authcode_cookie=$comment_authcode;
  }

  return 0;
}

function lwiki_auth_generate(){
  global $comment_authcode,$comment_authcode_cookie;
  if($comment_authcode_cookie!=$comment_authcode){
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

  return '';
}

$LWIKI_URL_AGH='/~murase/agh';
$LWIKI_URL_PDF_ICON=$LWIKI_URL_AGH.'/icons/file-pdf.png';
$lwiki_config_default_pageid='Main Page';

?>
