<?php // -*- mode:js -*-
  require_once 'lib.lwiki.php';
  require_once 'lib.ldiff.php';
  require_once 'lib.hist.php';
  require_once 'lib.page.php';

  function lwiki_swap(&$l,&$r){
    $t=$l;$l=$r;$r=$t;
  }
  // function lwiki_file_getline($fname,$iline){
  //   $lines=file($fname);
  //   return $lines[$iline];
  // }

  $_hist=new lwiki_hist();
  $_hist->load();
  $lines=$_hist->lines();
  $nlines=count($lines);

  //-----------------------------------
  // determine versions
  function hist_index($text){
    global $nlines,$lines,$_hist;
    if($text==='last')
      return $nlines-1;
    else if(preg_match('/^date(\d{8})(?:\.(\d{6}))?$/u',$text,$m)){
      // ■二分法
      $dateString=substr($m[1],0,4).'-'.substr($m[1],4,2).'-'.substr($m[1],6,2);
      $timeString=$m[2]==NULL?'23:59:59':substr($m[2],0,2).':'.substr($m[2],2,2).':'.substr($m[2],4,2);
      $qtime=strtotime($dateString.' '.$timeString);
      for($i=$nlines-1;$i>=0;$i--){
        $fields=$_hist->get_fields($i);
        $htime=strtotime(urldecode($fields[1]));
        if($htime<$qtime)return $i;
      }
      return 0;
    }else{
      $h=(int)$text;
      if($h>=$nlines)
        $h=$nlines-1;
      else if($h<0)
        $h=0;
      return $h;
    }
  }

  if(preg_match('/^([^-]+)-([^-]+)$/',$_GET['hist'],$m)){
    $h2=hist_index($m[2]);
    $h1=hist_index($m[1]);
  }else{
    $h2=hist_index($_GET['hist']);
    $h1=$h2-1;
  }

  if($h1==$h2)$h1--;
  if($h1<0)$h1=false;

  //-----------------------------------
  // content, diff

  if($h1!==false){
    if($h1>$h2)lwiki_swap($h1,$h2);

    $fields1=$_hist->get_fields($h1);
    $hist1_auth=htmlspecialchars(lwiki_hash($fields1[0]));
    $hist1_date=htmlspecialchars(urldecode($fields1[1]));
    $_hist->get_source($hist1_wiki,$h1);
  }else{
    $hist1_auth='--';
    $hist1_date='--';
    $hist1_wiki='';
  }

  $fields2=$_hist->get_fields($h2);
  $hist2_auth=htmlspecialchars(lwiki_hash($fields2[0]));
  $hist2_date=htmlspecialchars(urldecode($fields2[1]));
  $_hist->get_source($hist2_wiki,$h2);
  $hist2_html=\lwiki\convert\convert($hist2_wiki);

  $hist_diff=ldiff_lines_lwiki($hist1_wiki,$hist2_wiki);

  //-----------------------------------
  // linkbars

  $h2text='v'.$h2;
  $h1text=($h1!==false?'v'.$h1:'無');
  $hist_title="差分:$ht_page_title ($h2text/$h1text)";

  $diff_links='';
  if($h2>=1)
    $diff_links.='<a href="?id='.$pageid.'&amp;hist='.($h2-1).'">前</a>';
  else
    $diff_links.=' <span style="color:gray;">前</span>';
  $diff_links.=" <b>($h2text/$h1text)</b> ";
  if($h2+1<count($lines))
    $diff_links.='<a href="?id='.$pageid.'&amp;hist='.($h2+1).'">次</a>';
  else
    $diff_links.='<span style="color:gray;">次</span>';

  //-----------------------------------
  lwiki\page\begin_document($hist_title,'<meta name="robots" content="none" />');
?>
<p class="lwiki-linkbar-main">
  [ <a href="index.php">表紙</a> | <a href="?mode=list">一覧</a> ]
  <?php
    $url_read=htmlspecialchars("?id=$pageid");
    $url_edit=htmlspecialchars("?id=$pageid&mode=edit");
    $url_hist=htmlspecialchars("?id=$pageid&mode=hist");
    echo
      ' [ <a href="'.$url_read.'">'.$ht_page_title.'</a>'.
      ' | <a href="'.$url_edit.'">編集</a>'.
      ' | <a href="'.$url_hist.'">履歴</a> - 差分 '.$diff_links.']'.PHP_EOL;
  ?>
</p>
<p class="lwiki-linkbar-modified"><?php echo "$hist2_date by $hist2_auth";?></p>
<div class="lwiki-page-content">
<?php if($_hist->error_message)echo $_hist->error_message;?>
<h2>Wikiソース差分</h2>
<p><?php echo "<b>$h2text</b> $hist2_date by $hist2_auth / <b>$h1text</b> $hist1_date by $hist1_auth";?></p>
<div class="lwiki-history-source">
<pre class="agh-prog-txt"><?php echo $hist_diff;?></pre>
</div><!-- end of lwiki-history-source -->
<?php
  $hist2_diff=$_hist->get_fields($h2)[2];
  if(substr($hist2_diff,0,1)==='!'){
    echo '<h2>履歴データ (差分)</h2>'.PHP_EOL;
    $hist2_diff=htmlspecialchars(urldecode(substr($hist2_diff,1)));
    echo '<pre class="agh-prog-sed agh-prog-titled" data-title="sed">'.$hist2_diff.'</pre>';
  }
?>
<h1><?php echo $ht_page_title;?></h1>
<?php lwiki_include_string($hist2_html);?>
</div><!-- end of lwiki-page-content -->
<?php
  lwiki\page\end_document();
?>
