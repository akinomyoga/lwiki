<?php // -*- mode:js -*-
//-----------------------------------------------------------------------------
// ldiff (Light Diff) 簡易差分プロシージャ
//   Copyright 2014 Koichi Murase
//
// 関数
// - ldiff_bytes_diff($old,$new) for 文字列/バイト単位
// - ldiff_lines_diff($old,$new) for 文字列/行単位
//-----------------------------------------------------------------------------

function _ldiff_swap(&$l,&$r){
  $t=$l;$l=$r;$r=$t;
}

function _ldiff_mbstr2array($str){
  $ret=array();
  for($i=0,$len=mb_strlen($str);$i<$len;$i++)
    array_push($ret,mb_substr($str,$i,1));
  return $ret;
}

function ldiff_split_lines($text){
  return preg_split('/\r?\n|\r/u',$text);
}

//-----------------------------------------------------------------------------

function _ldiff_getpath_wu($arr1,$M,$arr2,$N){
  // Wu のアルゴリズム

  if(($swapped=$M<$N)){
    _ldiff_swap($arr1,$arr2);
    _ldiff_swap($M,$N);
  }

  // $p: $arr2 のskip回数 (これの数でループを回す)
  // $k: 対角線番号
  // $x = $y+$k-$N        ($arr1 内の index, 0<=$x<$M)
  // $y = $y              ($arr2 内の index, 0<=$y<$N)
  //
  // assert(0<=$k&&$k<=$N+$M);
  // assert($y>=$p);

  $ys=array();
  $ls=array();
  // $ys[k] k in 0~M+N
  //   各対角線について skip 回数 $p 回で何処の $y まで到達できるか?

  for($p=0;$p<=$N;$p++){
    for($k=$N-$p;$k<=$M+$p+1;$k++){
      $y=-1;
      if($p>0&&$k<$M+$p){
        // 上からの寄与
        $y=$ys[$k+1]+1;
        $l=$ls[$k+1]; // 後で上書きされるので流用可
      }
      if($k>$N-$p&&$y<$ys[$k-1]){
        // 左からの寄与
        $y=$ys[$k-1];
        $l=array_slice($ls[$k-1],0); // 後で使われるのでクローン
      }
      if($y<0){
        // 上・左どちらの寄与もないのは原点 (x,y)=(0,0) にいる時のみ。
        $y=0;
        $l=array();
      }

      // 進めるだけ進む (進めたら開始点を記録)
      $isfirst=true;
      $x=$y+$k-$N;
      while($y<$N&&$x<$M&&$arr1[$x]==$arr2[$y]){
        if($isfirst){
          $isfirst=false;
          array_push($l,$swapped?array($y,$x):array($x,$y));
        }
        $y++;$x++;
      }

      if($y==$N)return $l;

      $ys[$k]=$y;
      $ls[$k]=$l;
    }
  }
}

function _ldiff_getdiff_unified($arr1,$M,$arr2,$N,$path){
  $len=count($path);
  $x=0;
  $y=0;
  $ret='';
  for($i=0;$i<$len;$i++){
    $x0=$path[$i][0];
    $y0=$path[$i][1];
    for(;$x<$x0;$x++)
      $ret.='-'.$arr1[$x].PHP_EOL;
    for(;$y<$y0;$y++)
      $ret.='+'.$arr2[$y].PHP_EOL;
    for(;$x<$M&&$y<$N&&$arr1[$x]==$arr2[$y];$x++,$y++)
      $ret.=' '.$arr1[$x].PHP_EOL;
  }
  for(;$x<$M;$x++)
    $ret.='-'.$arr1[$x].PHP_EOL;
  for(;$y<$N;$y++)
    $ret.='+'.$arr2[$y].PHP_EOL;
  return $ret;
}

//-----------------------------------------------------------------------------

function ldiff_bytes_diff($text1,$text2){
  $M=strlen($text1);
  $N=strlen($text2);
  $path=_ldiff_getpath_wu($text1,$M,$text2,$N);
  return _ldiff_getdiff_unified($text1,$M,$text2,$N,$path);
}

function ldiff_chars_diff($text1,$text2){
  $arr1=_ldiff_mbstr2array($text1);
  $arr2=_ldiff_mbstr2array($text2);
  $M=count($arr1);
  $N=count($arr2);
  $path=_ldiff_getpath_wu($arr1,$M,$arr2,$N);
  return _ldiff_getdiff_unified($arr1,$M,$arr2,$N,$path);
}

function ldiff_lines_diff($text1,$text2){
  $arr1=ldiff_split_lines($text1);
  $arr2=ldiff_split_lines($text2);
  $M=$text1==''?0: count($arr1);
  $N=$text2==''?0: count($arr2);
  $path=_ldiff_getpath_wu($arr1,$M,$arr2,$N);
  return _ldiff_getdiff_unified($arr1,$M,$arr2,$N,$path);
}

//-----------------------------------------------------------------------------
// ldiff_lines_sed($str1,$str2)
//
function ldiff_lines_sed($text1,$text2){
  $arr1=ldiff_split_lines($text1);
  $arr2=ldiff_split_lines($text2);
  $M=$text1==''?0: count($arr1);
  $N=$text2==''?0: count($arr2);

  $path=_ldiff_getpath_wu($arr1,$M,$arr2,$N);
  array_push($path,array($M,$N)); // 終端

  $x=0;$y=0;
  $ret='';
  foreach($path as $p){
    // 削除
    $x0=$p[0];
    if($x<$x0){
      $a1=$x+1;
      $a2=$x0;
      if($a1==$a2)
        $ret.=$a1.'d'.PHP_EOL;
      else
        $ret.=$a1.','.$a2.'d'.PHP_EOL;
      $x=$x0;
    }

    // 挿入
    $y0=$p[1];
    if($y<$y0){
      $a1=$x0+1;
      for(;$y<$y0;$y++)
        $ret.=$a1.'i '.$arr2[$y].PHP_EOL;
    }

    // 共通部分は省略
    for(;$x<$M&&$y<$N&&$arr1[$x]==$arr2[$y];$x++,$y++);
  }

  return $ret;
}

//-----------------------------------------------------------------------------
// ldiff_lines_lwiki($str1,$str2)
//   lwiki の pre 内に表示する為の html を返します。

function _ldiff_lines_lwiki_tag($arr1,&$x,$x0,$hchar,$tag1,$tag2){
  $ret='';
  $istag=false;
  $str='';
  for(;$x<$x0;$x++){
    if($x==0||$arr1[$x-1]==="\n")$ret.=$hchar;

    if($arr1[$x]==="\n"){
      if($istag){
        $ret.=htmlspecialchars($str).$tag2;
        $istag=false;
        $str='';
      }
      $ret.=$arr1[$x];
    }else{
      if(!$istag){
        $ret.=$tag1;
        $istag.=true;
      }
      $str.=$arr1[$x];
    }
  }

  if($istag)
    $ret.=htmlspecialchars($str).$tag2;

  return $ret;
}

function _ldiff_lines_lwiki_addrem($rem,$add){
  // 詳細差分
  $arr1=_ldiff_mbstr2array($rem);
  $arr2=_ldiff_mbstr2array($add);
  $M=count($arr1);
  $N=count($arr2);
  if($M==0&&$N==0)return '';
  $path=_ldiff_getpath_wu($arr1,$M,$arr2,$N);

  // HTML構築 (pre 用)
  $len=count($path);
  $x=0;
  $y=0;
  $rem='';
  $add='';
  for($i=0;$i<$len;$i++){
    // 差分部分
    $x0=$path[$i][0];
    $y0=$path[$i][1];
    $rem.=_ldiff_lines_lwiki_tag($arr1,$x,$x0,'-','<span class="lwiki-diff-removed">','</span>');
    $add.=_ldiff_lines_lwiki_tag($arr2,$y,$y0,'+','<span class="lwiki-diff-added">'  ,'</span>');

    // 共通部分
    for(;$x0<$M&&$y0<$N&&$arr1[$x0]==$arr2[$y0];$x0++,$y0++);
    $rem.=_ldiff_lines_lwiki_tag($arr1,$x,$x0,'-','','');
    $add.=_ldiff_lines_lwiki_tag($arr2,$y,$y0,'+','','');
  }
  $rem.=_ldiff_lines_lwiki_tag($arr1,$x,$M,'-','<span class="lwiki-diff-removed">','</span>');
  $add.=_ldiff_lines_lwiki_tag($arr2,$y,$N,'+','<span class="lwiki-diff-added">'  ,'</span>');

  // 何れも最後の文字は改行と仮定
  if($M>0)$rem='<span class="lwiki-diff-removed-lines">'.mb_substr($rem,0,-1).'</span>'.PHP_EOL;
  if($N>0)$add='<span class="lwiki-diff-added-lines">'.mb_substr($add,0,-1).'</span>'.PHP_EOL;
  return $rem.$add;
}

function ldiff_lines_lwiki($text1,$text2){
  // 行毎の差分
  $arr1=preg_split('/\r?\n|\r/',$text1);
  $arr2=preg_split('/\r?\n|\r/',$text2);
  $M=$text1==''?0: count($arr1);
  $N=$text2==''?0: count($arr2);
  $path=_ldiff_getpath_wu($arr1,$M,$arr2,$N);
  $len=count($path);

  // pre 用 html 構築
  $x=0;
  $y=0;
  $ret='';
  for($i=0;$i<$len;$i++){
    $x0=$path[$i][0];
    $y0=$path[$i][1];

    // 詳細 diff
    $rem='';$add='';
    for($x=$x;$x<$x0;$x++)$rem.=$arr1[$x].PHP_EOL;
    for($y=$y;$y<$y0;$y++)$add.=$arr2[$y].PHP_EOL;
    $ret.=_ldiff_lines_lwiki_addrem($rem,$add);

    for(;$x<$M&&$y<$N&&$arr1[$x]==$arr2[$y];$x++,$y++)
      $ret.=' '.htmlspecialchars($arr1[$x]).PHP_EOL;
  }

  $rem='';$add='';
  for($x=$x;$x<$M;$x++)$rem.=$arr1[$x].PHP_EOL;
  for($y=$y;$y<$N;$y++)$add.=$arr2[$y].PHP_EOL;
  $ret.=_ldiff_lines_lwiki_addrem($rem,$add);

  return $ret;
}


?>
