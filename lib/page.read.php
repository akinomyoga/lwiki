<?php // -*- mode:php -*-
  require_once 'lib.page.php';
  lwiki\page\begin_document($ht_page_title);
?>
<p class="lwiki-linkbar-main">
  [ <a href="index.php">表紙</a> | <a href="?mode=list">一覧</a> ]
  <?php
    $fname_content='.lwiki/data/page.'.$pageid.'.htm';
    $url_edit=htmlspecialchars("index.php?id=$pageid&mode=edit");
    $ht_nested=lwiki\page\generate_ancestor_links($page_title);
    echo
      ' [ '.$ht_nested.
      ' | <a href="'.$url_edit.'">編集</a>'.
      ' | <a href="?id='.$pageid.'&amp;mode=hist">履歴</a> - <a href="?id='.$pageid.'&amp;hist=last">差分</a>]'.PHP_EOL;
  ?>
</p>
<p class="lwiki-linkbar-modified"><?php echo page_modified_date();?></p>
<div class="lwiki-page-content">
<?php
  // content
  $content_exists=file_exists($fname_content);
  if($content_exists){
    include $fname_content;
  }else{
    echo '<p><b>Error</b>: 指定された名前「<b>'.$ht_page_title.'</b> ('.$pageid.')」のページは存在しません。</p>';
  }

  // comment
  if($content_exists){
    echo '<h2 class="comment">コメント</h2>';
    $comment_content=@file_get_contents(".lwiki/data/$comment_id.htm");
    if($comment_content==''){
      echo "<p>コメントは未だありません</p>";
    }else{
      lwiki_include_string($comment_content);
    }

    $comment_action="index.php?id=$pageid#comment-form";
    include ".lwiki/lib/stub.comment-form.php";
  }
?>
</div>
<?php
  lwiki\page\end_document();
?>
