<?php // -*- mode:php -*-
  require_once "lib.page-edit.php";
  require_once "lib.page.php";

  $frag_title='履歴:'.$ht_page_title;
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
  $url_edit=htmlspecialchars(lwiki_link_page($pageid,'mode=edit'));
  $url_diff=htmlspecialchars(lwiki_link_page($pageid,'hist=last'));
  echo
    ' [ <a href="'.$url_read.'">'.$ht_page_title.'</a>'.
    ' | <a href="'.$url_edit.'">編集</a>'.
    ' | <b>履歴</b>'.
    ' - <a href="'.$url_diff.'">差分</a>]'.PHP_EOL;
?>
</p>
<p class="lwiki-linkbar-modified"><?php echo page_modified_date();?></p>
<div class="lwiki-page-content">
<?php
  if($_GET['mode']=='hist'){

    $fname_hist='.lwiki/data/page.'.$pageid.'.hist';
    if(file_exists($fname_hist)){
      echo '<table class="lwiki-single lwiki-center">'.PHP_EOL;
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
          '<td><a href="'.htmlspecialchars(lwiki_link_page($pageid,"hist=$iline")).'">'.$hist_date.'</a></td>'.
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
