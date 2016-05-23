<?php // -*- mode:php -*-
  require_once "lib.page-edit.php";
  require_once "lib.page.php";

  $frag_title='Wiki:ページ一覧';
  lwiki\page\begin_document($frag_title,'<meta name="robots" content="none" />'.PHP_EOL);

?>
<p class="lwiki-linkbar-main">
  [ <a href="index.php">表紙</a> | <b>一覧</b> ]
</p>
<p class="lwiki-linkbar-modified"><?php echo page_modified_date();?></p>
<div class="lwiki-page-content">
<?php
  function mwg_file_list($dirname,$preg){
    $ret=array();
    $dir=opendir($dirname);
    if($dir!==false){
      while(false!==($file=readdir($dir))){
        if($preg&&preg_match($preg,$file))
          array_push($ret,$file);
      }
      closedir($dir);
    }
    //sort($ret);
    usort($ret,'strcasecmp');
    return $ret;
  }

  $pages=mwg_file_list('./.lwiki/data','/page..*\.htm$/');
  echo '<table class="lwiki-single lwiki-center">'.PHP_EOL;
  echo '<tr><th>ページ名</th><th>編集者</th><th>最終更新日時</th></tr>';
  foreach($pages as $page){
    if(!preg_match('/page.(.*)\.htm$/',$page,$m))
      continue;
    $page_id=$m[1];
    $page_name=urldecode($m[1]);
    $page_date=@lwiki_datetime(@filemtime('./.lwiki/data/'.$page));

    $htmlPageName=htmlspecialchars($page_name);
    $htmlPageDate=htmlspecialchars($page_date).' [<a href="index.php?id='.htmlspecialchars($page_id).'&hist=last">差分</a>]';
    echo '<tr><td><a href="index.php?id='.htmlspecialchars($page_id).'">'.$htmlPageName.'</a></td><td></td><td>'.$htmlPageDate.'</td></tr>'.PHP_EOL;
  }
  echo '</table>'.PHP_EOL;

  $editlogs=@file('./.lwiki/data/log.edit.txt');
  if($editlogs!=null&&count($editlogs)){
    echo '<h2>最近のページ編集</h2>';
    echo '<ul>';
    $htmlContent='';
    foreach($editlogs as $editlog){
      $fields=explode('/',$editlog);
      $htmlAuth=htmlspecialchars(lwiki_hash(urldecode($fields[0])));
      $htmlDate=htmlspecialchars(urldecode($fields[1]));
      $htmlPage=htmlspecialchars(urldecode($fields[2]));
      $htmlHist=htmlspecialchars(urldecode(trim($fields[3])));
      $edit_pageid=htmlspecialchars($fields[2]);

      $htmlDiffLink='[<a href="index.php?id='.$edit_pageid.'&hist='.$htmlHist.'">差分</a>]';
      $htmlPageLink='<a href="index.php?id='.$edit_pageid.'">'.$htmlPage.'</a>';
      $htmlLine='<li>'.$htmlDate.' '.$htmlDiffLink.' '.$htmlPageLink.' by '.$htmlAuth.'</li>';
      $htmlContent=$htmlLine.PHP_EOL.$htmlContent;
    }
    echo $htmlContent;
    echo '</ul>';
  }

?>
</div>
<?php
  lwiki\page\end_document();
?>
