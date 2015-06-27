<?php // -*- mode:js -*-
  require_once "lib.page-edit.php";
  require_once "lib.page.php";

  $frag_title='履歴:'.$ht_page_title;

  lwiki\page\begin_document($frag_title,'<meta name="robots" content="none" />');
  // 以下は余分に出力される物:
  // <meta http-equiv="Content-Script-Type" content="text/javascript" />
  // <base href="{$_SERVER['PHP_SELF']}?id=$pageid" />
  // <meta name="agh-fly-type" content="color,tex" />
  // <script type="application/x-tex" id="tex-preamble">\documentclass{article}\usepackage{amsmath,amssymb,bm,color}\edef\lbrace{\{}\edef\rbrace{\}}</script>
  // <script type="text/javascript" charset="utf-8" src="/~murase/agh/agh.fly.js"></script>

?>
<p class="lwiki-linkbar-main">
  [ <a href="index.php">表紙</a> | <a href="?mode=list">一覧</a> ]
  <?php
    $fname_content='.lwiki/data/page.'.$pageid.'.htm';
    $url_edit=htmlspecialchars("index.php?id=$pageid&mode=edit");
    echo
      ' [ <a href="?id='.$pageid.'">'.$ht_page_title.'</a>'.
      ' | <a href="'.$url_edit.'">編集</a>'.
      ' | <b>履歴</b> - <a href="?id='.$pageid.'&amp;hist=last">差分</a>]'.PHP_EOL;
  ?>
</p>
<p class="lwiki-linkbar-modified"><?php echo page_modified_date();?></p>
<div class="lwiki-page-content">
<?php
  if($_GET['mode']=='hist'){
    
    $fname_hist='.lwiki/data/page.'.$pageid.'.hist';
    if(file_exists($fname_hist)){
      echo '<table class="normal center">'.PHP_EOL;
      echo '<tr><th>版</th><th>更新日時</th><th>編集者</th><th>註</th><th>履歴容量</th></tr>';
      $html='';
      $lines=file($fname_hist);
      $iline=0;
      foreach($lines as $line){
        $fields=explode('/',$line);
        $hist_auth=htmlspecialchars(lwiki_hash($fields[0]));
        $hist_date=htmlspecialchars(urldecode($fields[1]));
        $hist_remk=htmlspecialchars(urldecode($fields[3]));
        $hist_size=(substr($fields[2],0,1)=='!'?'Δ':'').(strlen($line)+1).'B';
        $hist_line='<tr><td>v'.$iline.'</td>'.
          '<td><a href="index.php?id='.htmlspecialchars($pageid).'&hist='.$iline.'">'.$hist_date.'</a></td>'.
          '<td>'.$hist_auth.'</td><td>'.$hist_remk.'</td><td>'.$hist_size.'</td></tr>';
        $html=$hist_line.PHP_EOL.$html;
        $iline++;
      }
      echo $html;
      echo '</table>'.PHP_EOL;
    }else{
      echo '<p><b>Error</b>: 履歴はありません。</p>'.PHP_EOL;
    }
  }
?>
</div>
<?php
  lwiki\page\end_document();
?>
