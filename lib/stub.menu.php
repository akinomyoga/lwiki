<?php // -*- mode:php -*-

global $pageid,$pageinfo;

function sidebar_begin_holder($title,$haslinkbar=false){
  $title=htmlspecialchars($title);
  echo '<div class="lwiki-menu-holder">'.PHP_EOL;
  echo "<h1>$title</h1>".PHP_EOL;
  if($haslinkbar){
    $url_edit=htmlspecialchars("index.php?id=Menu&mode=edit");
    echo '<p class="lwiki-page-wikilinks">'.PHP_EOL;
    echo '  [ <a href="index.php">表紙</a> | <a href="?mode=list">一覧</a> ]'.PHP_EOL;
    echo '  [ <a href="'.$url_edit.'">目次編集</a> ]'.PHP_EOL;
    echo '</p>'.PHP_EOL;
  }
  echo '<div class="lwiki-page-content">'.PHP_EOL;
}

function sidebar_end_holder(){
  echo '</div><!-- end of .lwiki-page-content -->'.PHP_EOL;
  echo '</div><!-- end of .lwiki-menu-holder -->'.PHP_EOL;
}

echo <<<EOF
<div class="lwiki-sidebar">
<style type="text/css">
div.lwiki-menu-holder a[href$="?id=$pageid"]{
  pointer-events:none;
  font-weight:bold;text-decoration:none;color:black;
  /*background-color:#eee;
  border:1px solid #bbb;padding:1px 3px;margin:2px;*/
}
</style>

EOF;

sidebar_begin_holder('目次',true);
function load_menu_content(){
  $fname_menu='.lwiki/data/page.Menu.htm';
  $content_exists=file_exists($fname_menu);
  if($content_exists){
    include $fname_menu;
  }else{
    echo '<p>Error: Menu ページが存在しません</p>';
  }
}
load_menu_content();
sidebar_end_holder();

if($pageinfo!==false){
  $pageinfo[1]=trim($pageinfo[1]);
  $pageinfo[2]=trim($pageinfo[2]);
  $pageinfo[3]=trim($pageinfo[3]);
  if($pageinfo[1]||$pageinfo[2]||$pageinfo[3]){
    sidebar_begin_holder('頁');
    if($pageinfo[1]!=''){
      echo '<h2>頁内目次</h2>';
      echo urldecode($pageinfo[1]);
      echo PHP_EOL;
    }
    if($pageinfo[2]!=''){
      echo '<h2>附票</h2>';
      $tags=lwiki_split_lines(urldecode($pageinfo[2]));
      echo '<ul class="lwiki-list">';
      foreach($tags as $tag){
        if($tag=='')continue;
        echo '<li class="lwiki-list-item">'.htmlspecialchars($tag).'</li>';
      }
      echo '</ul>'.PHP_EOL;
    }
    if($pageinfo[3]!=''){
      echo '<h2>単語</h2>';
      $kwds=lwiki_split_lines(urldecode($pageinfo[3]));
      echo '<ul class="lwiki-list">';
      foreach($kwds as $kwd){
        if($kwd=='')continue;
        echo '<li class="lwiki-list-item"><span class="lwiki-keyword">'.htmlspecialchars($kwd).'</span></li>';
      }
      echo '</ul>'.PHP_EOL;
    }
    sidebar_end_holder();
  }
}

echo '</div><!-- end of .lwiki-sidebar -->'.PHP_EOL;

?>
