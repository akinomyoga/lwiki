<?php // -*- mode:php -*-
  require_once "lib.page-edit.php";
  require_once "lib.page.php";

  if(lwiki_auth_check($emsg)==1)lwiki\edit\error($emsg);
  $frag_captcha=lwiki_auth_generate();

  if(!$edit_session->exists()){
    $frag_action=htmlspecialchars(lwiki_link_page($pageid,'mode=edit'));
    $frag_title='新規作成:'.$ht_page_title;
  }else if($edit_session->is_part()){
    $frag_action=htmlspecialchars(lwiki_link_page($pageid,'mode=edit&part='.urlencode($_GET['part'])));
    $frag_title='部分編集:'.$ht_page_title;
  }else{
    $frag_action=htmlspecialchars(lwiki_link_page($pageid,'mode=edit'));
    $frag_title='編集:'.$ht_page_title;
  }

  $frag_content =htmlspecialchars($edit_session->content());
  $frag_edithash=htmlspecialchars($edit_session->edithash());
  $frag_remarks =htmlspecialchars($_POST['remarks']);
  $frag_partlength=$edit_session->is_part()?'<input type="hidden" name="partlength" value="'.$edit_session->partlength().'" />'.PHP_EOL:'';

  lwiki\page\begin_document($frag_title,'<meta name="robots" content="none" />'.PHP_EOL);
?>
<p class="lwiki-linkbar-main">
<?php
  $url_main=htmlspecialchars(lwiki_link_page());
  $url_list=htmlspecialchars(lwiki_link_page(null,'mode=list'));
  echo
    '[ <a href="'.$url_main.'">表紙</a>'.
    ' | <a href="'.$url_list.'">一覧</a> ]';

  $url_read=htmlspecialchars(lwiki_link_page($pageid));
  $url_hist=htmlspecialchars(lwiki_link_page($pageid,'mode=hist'));
  $url_diff=htmlspecialchars(lwiki_link_page($pageid,'hist=last'));
  echo
    ' [ <a href="'.$url_read.'">'.$ht_page_title.'</a>'.
    ' | <b>編集</b>'.
    ' | <a href="'.$url_hist.'">履歴</a>'.
    ' - <a href="'.$url_diff.'">差分</a>]'.PHP_EOL;
?>
</p>
<p class="lwiki-linkbar-modified"><?php echo page_modified_date();?></p>
<div class="lwiki-page-content">
<?php
  if($lwiki_edit_error!='')echo "$lwiki_edit_error";
?>
<form method="post" action="<?php echo $frag_action?>" style="width:600px;" id="lwiki_form_edit">
  <?php echo $frag_partlength;?>
  <input type="hidden" name="edithash" value="<?php echo $frag_edithash;?>" />
  <textarea name="content" rows="20" style="width:590px;padding:5px;"><?php echo $frag_content;?></textarea>
  <div><label>備考: <input type="text" style="width:500px;" name="remarks" value="<?php echo $frag_remarks;?>" /></label></div>
  <div style="text-align:center;margin:0.5em;">
    <?php echo $frag_captcha;?>
    <input type="submit" name="page_preview" value="プレビュー" />
    <input type="submit" name="page_update" value="保存" /></div>
</form>
<?php
  //if($_POST['page_preview']||$_POST['page_update']){
    echo '<hr/>'.PHP_EOL;
    echo '<div class="lwiki-page-preview" id="lwiki_page_preview">';
    echo '<h1 id="lwiki_page_preview_head">プレビュー:'.$ht_page_title.($frag_partlength?' (部分)':'').'</h1>';
    require_once '.lwiki/lib/lib.lwiki.php';
    lwiki_include_string(\lwiki\convert\convert($edit_session->content()));
    echo '</div>';
  //}
?>
</div><!-- end of .lwiki-page-content -->
<?php
  lwiki\page\end_document();
?>
