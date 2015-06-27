<?php // -*- mode:js -*-

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
    $html_captcha='<div class="securimage-captcha">'.Securimage::getCaptchaHtml($opts).'</div>';
  }

  if($comment_action=='')
    $comment_action="index.php?id=$pageid";
?>
<div id="comment-form" style="width:400px;text-ailgn:center;border:1px solid gray;padding:10px 20px;">
  <?php if($comment_error!='')echo $comment_error;?>
  <form method="post" action="<?php echo $comment_action;?>">
    <input type="hidden" name="type" value="comment_add" />
    <p style="margin:0.5em 0;">名前: <input type="text" name="name" size="20" value="<?php echo $comment_name;?>"/></p>
    <textarea name="body" rows="5" style="width:390px;padding:5px;"><?php echo $comment_body;?></textarea>
    <div style="text-align:center;margin:0.5em;">
      <?php echo $html_captcha;?><input type="submit" name="comment_post" value="投稿" />
    </div>
  </form>
</div>
