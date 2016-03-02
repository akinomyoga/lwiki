<?php // -*- mode:php -*-

// lib.lwiki.php
// version 3.0
//

# 使用する外部変数
#
# @var $pageid
#
# @var $lwiki_base_resourceDirectoryUrl
#   現在は @... 拡張で使っているだけである。
#

namespace lwiki\convert;

//-----------------------------------------------------------------------------
// utility functions

function lwc_html_gettext($html){
  $html=str_replace('<br/>',"\n",$html);
  $html=str_replace('&nbsp;',' ',$html);
  return html_entity_decode(strip_tags($html));
}

function lwc_char_after($content,$i){
  return mb_substr(substr($content,$i),0,1);
}
function lwc_char_before($content,$i){
  return mb_substr(substr($content,0,$i),-1);
}

/* 読み取り用関数
 *
 * - function lwc_skip_space($content,&$i);
 * - function lwc_skip_single_nl($content,&$i);
 * - function lwc_read_args($content,&$i);
 * - function lwc_read_brace($content,&$pos);
 * - function lwc_read_until($pat,$content,&$pos,$iMax=-1);
 * - function lwc_read_until_brace($needle,$content,&$pos);
 * - function lwc_read_braced_line($content,&$pos);
 *
 */
function lwc_skip_space($content,&$i){
  if(preg_match('/[^\s]/u',$content,$m,PREG_OFFSET_CAPTURE,$i))
    $i=$m[0][1];
  else
    $i=strlen($content);
}
function lwc_skip_single_nl($content,&$i){
  if(substr($content,$i,1)==="\n")$i++;
}

function lwc_read_args($content,&$i,$cbeg='(',$cend=')'){
  lwc_skip_space($content,$i);

  $c=lwc_char_after($content,$i);
  if($c!=$cbeg)return false;
  $i+=strlen($c);

  $args=array();
  $iarg=0;
  $a='';
  for($iMax=strlen($content);$i<$iMax;){
    $c=lwc_char_after($content,$i);
    $i+=strlen($c);

    if($c==','||$c==$cend){
      $args[$iarg++]=$a;
      $a='';
      if($c==$cend)return $args;
    }else
      $a.=$c;
  }

  return false;
}

// function lwc_read_brace_v1($content,&$pos){
//   $i=$pos;
//   lwc_skip_space($content,$i);

//   $c=lwc_char_after($content,$i);
//   if($c!='{')return false;
//   $i+=strlen($c);

//   $cont='';
//   $depth=0;
//   for($iMax=strlen($content);$i<$iMax;){
//     $c=lwc_char_after($content,$i);
//     $i+=strlen($c);

//     if($c=='{')
//       $depth++;
//     else if($c=='}'){
//       $depth--;
//       if($depth<0){
//         $pos=$i;
//         return $cont;
//       }
//     }
//     $cont.=$c;
//   }

//   return false;
// }

// 2014-09-30 lwc_read_brace v2
function lwc_read_brace($content,&$pos){
  $i=$pos;
  if(!preg_match('/\G\s*\{/u',$content,$m,0,$i))return false;
  $i+=strlen($m[0]);

  $ibeg=$i;
  $depth=0;
  while(preg_match('/[{}]/u',$content,$m,PREG_OFFSET_CAPTURE,$i)){
    $c=$m[0][0];
    $iend=$m[0][1];
    $i=$iend+strlen($c);
    if($c=='{'){
      $depth++;
    }else{
      if(--$depth<0){
        $pos=$i;
        return substr($content,$ibeg,$iend-$ibeg);
      }
    }
  }
  return false;
}

function lwc_read_block($content,&$pos){
  $i=$pos;

  // 2014-09-30 Here Document <<<EOF
  if(preg_match('/\G\s*\<{3}\s*([_\w]+)\s*$/mu',$content,$m,0,$i)){
    // <<<EOF
    $eof=$m[1];
    $i+=strlen($m[0]);
    $ibeg=$i;
    if(preg_match('/^\s*'.$eof.'\s*$/mu',$content,$m,PREG_OFFSET_CAPTURE,$i)){
      $iend=$m[0][1];

      $i=$iend+strlen($m[0][0]);
      if(preg_match('/(?:\r\n?|\n)/u',$content,$m,0,$i))$i+=strlen($m[0]);

      $pos=$i;
      return substr($content,$ibeg,$iend-$ibeg);
    }
  }

  // // 2014-09-30 multibraced {{{ ... }}} not used
  // if(preg_match('/\G\s*(\{{2,})/u',$content,$m,0,$i)){
  //   $mult=strlen($m[1]);
  //   $i+=strlen($m[0]);
  //   if(preg_match('/\}{'.$mult.'}/u',$content,$m,PREG_OFFSET_CAPTURE,$i)){
  //     $iend=$m[0][1];
  //     $pos=$iend+$mult;
  //     return substr($content,$i,$iend-$i);
  //   }
  // }

  return lwc_read_brace($content,$pos);
}

function lwc_read_until($pat,$content,&$pos,$iMax=-1){
  if($iMax==-1)$iMax=strlen($content);
  $depth=0;
  $i=$pos;
  $ret='';
  while($i<=$iMax&&preg_match("/$pat|[{}]/u",$content,$m,PREG_OFFSET_CAPTURE,$i)){
    //※↑$pat が零幅で一致する事もあるので $i<$iMax ではなく $i<=$iMax。

    $needle=$m[0][0];
    $ibegin=$m[0][1];
    $iend=$ibegin+strlen($needle);
    if($iMax<$iend)return false; // overrun

    //$ret.='(('.$i.','.$ibegin.','.$iend.'|'.substr($content,$i,$ibegin-$i).'))';
    $ret.=substr($content,$i,$ibegin-$i);

    if($needle=='{'){
      $depth++;
    }else if($needle=='}'){
      $depth--;
      if($depth<0)return false; // region ended
    }else{
      if($depth>0){
        $c=lwc_char_after($content,$ibegin);
        $iend=$ibegin+max(1,strlen($c)); // 一文字だけ進める。末端に来た時など strlen($c)==0 の時があるのに注意。
      }else{
        $pos=$iend;
        return $ret;
      }
    }

    $ret.=substr($content,$ibegin,$iend-$ibegin);
    $i=$iend;
  }
  return false;
}

// {content}TERM → "content" を返す。
// {cont1}cont2 TERM → "{cont1}cont2" を返す。
function lwc_read_until_brace($needle,$content,&$pos){
  if(false!==($cont=lwc_read_until($needle,$content,$pos))){
    $cont=trim($cont);
    if(mb_substr($cont,0,1)=='{'&&mb_substr($cont,-1)=='}')
      return mb_substr($cont,1,-1);
    else
      return $cont;
  }

  return false;
}

// @fn lwc_read_braced_line($content,&$pos)
// 行末までを読み取る。
// 行末に \ を置く事で行を繋げる事ができる。
function lwc_read_braced_line($content,&$pos){
  // option: 行末のパターン
  $needle='\n|$';

  // option: 行末の \ による行接続を有効にするかどうか。
  $hasEscape=true;

  // option: 全体を囲む {} を削除するかしないか?
  $removesBrace=true;

  // ToDo:
  // option: escape された終端文字を含めるか含めないか?
  // option: escape \\ 自体を含めるか含めないか?

  $ret='';
  $len=strlen($content);
  while($pos<$len){
    $r=lwc_read_until($needle,$content,$pos,$len);
    if($r===false)return false; // (括弧が閉じていないなどの理由で失敗)
    $ret.=$r;

    if($hasEscape&&mb_substr($ret,-1)=="\\"){
      $ret=mb_substr($ret,0,-1);
      continue;
    }

    break;
  }

  if($removesBrace&&preg_match('/^\s*\{\s*([\s\S]*?)\s*\}\s*$/u',$ret,$m))$ret=$m[1];
  return $ret;
}

class lwc_util{
  public static $rex_cssColor='(?:[\w#-]+|\w+\s*\([-\d\s,.%]+\))';
  public static $rex_cssFontSize='(?:medium|xx?-(?:small|large)|(?:small|large)(?:er)?|initial|inherit|[\-.\d]+(?:e[mx]|in|p[tcx]|[cm]m|%)?)';
  public static $rex_cssLength='(?:auto|initial|inherit|[\-.\d]+(?:e[mx]|in|p[tcx]|[cm]m|%)?)';

  // http://webnote.motq.net/html/sum/attrsval.htm
  // HTML4 では id は ID であり /[a-zA-Z][-_.:a-zA-Z0-9]*/ を使用する事ができる。
  const rex_ID='(?:[a-zA-Z][-_.:a-zA-Z0-9])';
  const rex_NAME=lwc_util::rex_ID;

  // class 属性に使用する事の出来る識別子?
  //
  // 1 https://groups.google.com/forum/#!topic/html5-developers-jp/7ABMfig0n4M
  //   CSS 2.1 では class/id 名には
  //     /(?!-[-\d]|\d)([-_a-zA-Z0-9\xA0-\x{FFFF}]|\\)+/
  //     (※注意: PCRE で hex sequence (\x...) に 2 文字以上直接指定した時にどう解釈されるかは未確認)
  //   を使用する事ができる。特に、どの様な文字であっても \\ でエスケープできる事に注意する。
  //
  // 2 CSS3 http://www.w3.org/TR/css3-syntax/#non-ascii-code-point を見ると、
  //   class は ident-token だろうか。これは
  //     <ident-token> = /(?=<name start code point>)(<name code point>|<escaped>)+*/
  //     <escaped> = <\\ で始まる文字を指定するエスケープシーケンス>
  //     <name code point> = [-\d<name start code point>]
  //     <name start code point> = [a-zA-Z_\x{80}-\x{10FFFF}]
  //   となっている様に読める。
  //
  // 3 一方で HTML では class 属性は CDATA となっているので好きな物を指定できる。
  //
  // 4 ブラウザの実装では \ によるエスケープにうまく対応できない場合が存在する様である。
  //   例えば経験上、クラス名に : が含まれている場合 \: とエスケープすれば CSS 仕様的には OK の筈だが、
  //   ブラウザによってこれを解釈してくれない様である。
  //   また、Unicode 文字を使うとちゃんと解釈されないという話もある様である。
  //
  const rex_CLASS='(?(?!\d|-[-\d])[-_\w]*)';

  public static function add_unit_to_csslength($len){
    if(preg_match('/^[\-.\d]+$/',$len))$len.='px';
    return $len;
  }
}

//
//-----------------------------------------------------------------------------
// language definition

class lwiki_language{
  private $patternGroupCount=1;
  public $patterns=array(); // [ int groupIndex, string(*proc)(converter conv,string name,string content,int& pos) ][]
  public $rex_main='';

  //-----------------------------------
  // patterns
  /**
   * @fn register_pattern($rex,$proc)
   * @param[in] $rex
   * @param[in] $proc function($conv,$name,$content,&$i){}
   *   returns html source.
   */
  public function register_pattern($rex,$proc){
    array_push(
      $this->patterns,
      array($this->patternGroupCount,$proc));

    if($this->rex_main)
      $this->rex_main.='|'.$rex;
    else
      $this->rex_main=$rex;

    $this->patternGroupCount
      +=preg_match_all('/(?:^|[^\\\\])(?:\\\\\\\\)*\((?!\?)/',$rex);
  }

  //-----------------------------------
  // entity handlers
  private $entityHandlers=array();
  /**
   * @fn register_entity($name,$hasa,$hasb,$proc)
   * @param[in] $name
   * @param[in] $hasa true or false
   * @param[in] $hasb true, 'block', or false
   * @param[in] $proc function($name,$args,$cont,$conv,$content,&$i){}
   *   $name is the name of the entity.
   *   $args is the '(...)' arguments in the form of an array.
   *   If $hasa is false, or '(...)' arguments are missing, $args will be false.
   *   $cont is the content of the '{...}' argument.
   *   If $hasb is false, or '{...}' argument is missing, $cont will be false.
   *   $conv is the lwiki_converter instance.
   *   $content is the processed wiki source.
   *   $i is the position in $content specifying the starting point of the next wiki translation.
   *   This originally points the end of the entity and its arguments, and it can be changed by $proc.
   *
   *   $proc returns the resulting HTML source if the entity reference is correctly processed.
   *   Otherwise, $proc returns false. When $proc returns false, the change to $i is discarded.
   */
  public function register_entity($name,$hasa,$hasb,$proc){
    $this->entityHandlers[$name]=function($conv,$content,&$i) use($name,$hasa,$hasb,$proc){
      $args=$cont=false;

      if($hasa){
        $args=lwc_read_args($content,$i);
        if($args)
          for($j=0,$jN=count($args);$j<$jN;$j++)
            $args[$j]=trim($args[$j]);
      }

      if($hasb){
        if($hasb==='block')
          $cont=lwc_read_block($content,$i);
        else
          $cont=lwc_read_brace($content,$i);
        if($cont)$cont=trim($cont);
      }

      return $proc($name,$args,$cont,$conv,$content,$i);
    };
  }
  public function entity_handler($name){
    return @$this->entityHandlers[$name];
  }

  //-----------------------------------
  // create converter
  public function create_converter(){
    return new lwiki_converter($this);
  }

  //-----------------------------------
  public static $defaultInstance;
}

lwiki_language::$defaultInstance=new lwiki_language();


class lwiki_source{
  private $content;
  private $index;
  public function __construct($content,$index){
  }
}
class lwiki_converter{
  private $lang=null;
  private $rex;
  private $pat;
  private $npat;

  public $flagInline=false;
 
  public $option_prog_enabled=false;
  public $pageid;
  public function __construct($lang){
    $this->lang=$lang;
    $this->rex='/'.$lang->rex_main.'/mu';
    $this->pat=&$lang->patterns;
    $this->npat=count($lang->patterns);

    global $pageid;
    $this->pageid=$pageid;
    $this->option_prog_enabled=htmlspecialchars(getenv("LWIKI_ENABLE_PROG"));
  }

  private function _convertImpl($content){
    // 行末正規化
    $content=preg_replace('/[ 　\t]*(?:\r?\n|\r)/u',"\n",$content);

    // (preg_match 使い方)
    // preg_match(needlePattern,haystackString,matches,flags,byteOffset);
    // flags = PREG_OFFSET_CAPTURE の時、
    //   m[0][0] 一致文字列
    //   m[0][1] 一致文字列開始位置 (byteOffset)
    //   m[1][0] 第一キャプチャ
    //   m[1][1] 第一キャプチャ開始位置 (byteOffset)

    $ret='';
    $i=0;$iMax=strlen($content);
    while($i<$iMax&&preg_match($this->rex,$content,$m,PREG_OFFSET_CAPTURE,$i)){
      $ibegin=$m[0][1];
      $iend=$ibegin+strlen($m[0][0]);
      $ret.=substr($content,$i,$ibegin-$i);

      // apply the corresponding pattern
      for($ipat=0;$ipat<$this->npat;$ipat++){
        $p=$this->pat[$ipat];
        $g=$m[$p[0]];
        if($g[1]!==null&&$g[1]>=0){
          $ret.=$p[1]($this,$g[0],$content,$iend);
          break;
        }
      }
      if($ipat==$this->npat)
        echo '<p>lwiki3(unknown pattern): i='.$i.', needle='.htmlspecialchars($m[0][0]).'</p>';

      if($iend<=$i)
        $i++;
      else
        $i=$iend;
    }

    $ret.=substr($content,$i);
    return $ret;
  }

  public function convert($content,$isInline=false){
    $originalFlagInline=$this->flagInline;
    $this->flagInline=$isInline;
    $ret=$this->_convertImpl($content);
    $this->flagInline=$originalFlagInline;
    return $ret;
  }

  public function iconvert($content){
    if(preg_match('/^\s*\{\s*([\s\S]*?)\s*\}\s*$/u',$content,$m)){
      return $this->convert($m[1],false);
    }else{
      return $this->convert($content,true);
    }
  }

  //-----------------------------------
  // headers, keywords, tags

  private $header='';
  private $header_level=0;
  public function header_close($level=0){
    for(;$this->header_level>$level;$this->header_level--)
      $this->header.='</li></ul>';
    return $this->header;
  }
  public function header_append($level,$html){
    if($this->header_level<$level){
      for(;$this->header_level<$level;$this->header_level++)
        $this->header.='<ul class="lwiki-list"><li class="lwiki-list-'.($this->header_level+1==$level?'item':'nomarker').'">';
    }else{
      $this->header_close($level);
      $this->header.='</li><li class="lwiki-list-item">';
    }
    $this->header.=$html;
  }

  private $m_keywords='';
  private $m_keywords_dict=array();
  public function keywords_append($kwd){
    $kwd=trim(preg_replace('/\s+/u',' ',$kwd));
    if($kwd&&!@$this->m_keywords_dict[$kwd]){
      $this->m_keywords.=$kwd."\n";
      $this->m_keywords_dict[$kwd]=true;
    }
  }
  public function keywords(){
    return $m_keywords;
  }

  private $m_tags='';
  private $m_tags_dict=array();
  public function tags_append($kwd){
    $kwd=trim(preg_replace('/\s+/u',' ',$kwd));
    if($kwd&&!$this->m_tags_dict[$kwd]){
      $this->m_tags.=$kwd."\n";
      $this->m_tags_dict[$kwd]=true;
    }
  }
  public function tags(){
    return $m_tags;
  }

  //---------------------------------------------------------------------------
  // functions to be called from construct definitions

  public function entity_handler($name){
    return $this->lang->entity_handler($name);
  }

  private $m_procGenerateEditLink=NULL;

  private static function generate_editlink_default($_pageid,$mark){
    $mark=urlencode($mark);
    $href='?id='.$_pageid.'&mode=edit&part='.$mark;
    return '<a class="lwiki-edit-partial" href="'.$href.'">[編集]</a>';
  }
  public function setCustomGenerateEditLink($proc){
    if($proc==='none')
      $this->m_procGenerateEditLink=NULL;
    else if($proc==='default')
      $this->m_procGenerateEditLink=true;
    else
      $this->m_procGenerateEditLink=$proc;
  }
  public function generate_editlink($_pageid,$mark){
    if($this->m_procGenerateEditLink===true)
      return self::generate_editlink_default($_pageid,$mark);
    else if($this->m_procGenerateEditLink!==NULL)
      return $this->m_procGenerateEditLink($_pageid,$mark);
    else
      return "";
  }

  public function &language(){
    return $this->lang;
  }
}

//$lwiki_conv_instance=$lwiki_lang_instance->create_converter();

function create_converter(){
  return lwiki_language::$defaultInstance->create_converter();
}
function convert($content){
  return create_converter()->convert($content);
}

//-----------------------------------------------------------------------------
// definition of the default lwiki_language

//
//-----------------------------------------------------------------------------
// block components

class lwc_list{
  private $ret='';
  private $cont='';
  private $stack=array();
  private $depth=0;

  public static function listtag($hchar){
    if($hchar==='+')
      return 'ol';
    return 'ul';
  }

  private $conv;
  public function __construct($conv){
    $this->conv=$conv;
  }

  public function html(){
    $this->bubble(0);
    return $this->ret;
  }

  private function bubble($d){
    if($d<$this->depth){
      $this->ret.=$this->conv->convert($this->cont);
      $this->cont='';
      do{
        $this->ret.='</li></'.$this->stack[--$this->depth].'>';
      }while($d<$this->depth);
    }
  }

  public function process1($head,$content,&$i){
    // 中身の読取
    $r=lwc_read_braced_line($content,$i);
    if($r===false){
      $this->bubble(0);
      //$this->ret.='error(unmatching closing brace at '.substr($content,$i,10).'...)';
      return false;
    }

    if(trim($r)===''){
      $this->bubble(0);
      if($head=='----')
        $head='<hr/>';
      else
        $head=htmlspecialchars($head).'<br/>';

      $this->ret.=$head.PHP_EOL;
      return true;
    }

    $d=mb_strlen($head);

    // 中で新しい list が始まる場合
    if($d>$this->depth&&preg_match('/\G[\-\+]+/u',$head,$m,0,$this->depth)){
      $this->ret.=$this->conv->convert($this->cont);
      $d=$this->depth+mb_strlen($m[0]);
      while($this->depth+1<$d){
        $this->ret.='<ul class="lwiki-list"><li class="lwiki-list-nomarker">';
        $this->stack[$this->depth++]='ul';
      }
      $tag=lwc_list::listtag(mb_substr($head,-1));
      $this->ret.='<'.$tag.' class="lwiki-list"><li class="lwiki-list-item">';
      $this->stack[$this->depth++]=$tag;
      $this->cont=$r;
      return null;
    }

    if($this->depth<$d)
      $r=mb_substr($head,$this->depth).$r;
    else
      $this->bubble($d);

    $c=mb_substr($head,$this->depth-1,1);
    if($c=='+'||$c=='-'){
      // 新規リスト項目
      $this->ret.=$this->conv->convert($this->cont);
      $tag=lwc_list::listtag($c);
      if($this->stack[$this->depth-1]===$tag){
        $this->ret.='</li><li class="lwiki-list-item">';
      }else{
        $this->ret.='</li></'.$this->stack[$this->depth-1].'>'.
          '<'.$tag.' class="lwiki-list"><li class="lwiki-list-item">';
        $this->stack[$this->depth-1]=$tag;
      }
      $this->cont=$r;
    }else{
      // 同じリスト項目
      if($this->cont==='')
        $this->cont=$r;
      else
        $this->cont.=PHP_EOL.$r;
    }
    return null;
  }
}

class lwc_table{
  private $conv;
  private $data;
  private $rows;
  private $icol;
  private $cols;
  private $is_sortable;
  public function __construct($conv){
    $this->conv=$conv;
    $this->data=array();
    $this->rows=0;
    $this->cols=0;
    $this->is_sortable=false;

    $this->initialize_cell_style();
  }

  public function add_row(){
    $this->data[$this->rows]=array();
    $this->rows++;
    $this->icol=0;
  }
  public function remove_row(){
    $this->rows--;
    $this->cols=0;
    for($r=0,$rN=count($this->rows);$r<$rN;$r++){
      $cols1=count($this->data[$r]);
      if($this->cols<$cols1)
        $this->cols=$cols1;
    }
  }
  public function add_cell($cont){
    $this->data[$this->rows-1][$this->icol++]=$cont;
    if($this->cols<$this->icol)
      $this->cols=$this->icol;
  }

  // visible rows
  //   書式指定行などの分別を行う。
  //   実際に出力される行を整理する。
  private $vdata;
  private $vrows;
  private function _construct_vrows(){
    $this->vdata=array();
    $this->vrows=0;
    for($r=0;$r<$this->rows;$r++){
      $row=&$this->data[$r];
      if(preg_match('/^c:/',$row[0])){
        // 書式指定行
        $row['columns']=true;
        $row[0]=mb_substr($row[0],2);
        continue;
      }

      if(preg_match('/^~/',$row[0])){
        $row['celltag']='th';
        $row[0]=mb_substr($row[0],1);
      }

      $row['ivcol']=$this->vrows;
      $this->vdata[$this->vrows]=&$row;
      $this->vrows++;
    }
  }
  private function check_span($vr,$c,&$vrN,&$cN,&$cont){
    // colspan
    $cN=$c+1;
    while(trim($cont)==='>'&&$cN<$this->cols){
      $cont1=$this->vdata[$vr][$cN];
      if($cont1===null||$cont1===false)break;
      $cont=$cont1;
      $cN++;
    }
    if(trim($cont)==='>'||trim($cont)==='^')$cont='';

    // rowspan
    for($vr_=$vr+1;$vr_<$this->vrows;$vr_++){
      $flag=true;
      for($c_=$c;$c_<$cN;$c_++){
        $cont1=trim($this->vdata[$vr_][$c_]);
        if($cont1==='^')continue;
        if($c_+1!==$cN&&$cont1==='>')continue;
        $flag=false;
        break;
      }
      if(!$flag)break;
    }
    $vrN=$vr_;
  }
  private function clear_span($vr0,$c0,$vrN,$cN,$cont=false){
    for($vr=$vr0;$vr<$vrN;$vr++)
      for($c=$c0;$c<$cN;$c++)
        $this->vdata[$vr][$c]=$cont;
  }

  private $rex_cell_style;
  private $cell_style_props;
  private function initialize_cell_style(){
    $style_color='^((?:bg)?color)\(\s*('.lwc_util::$rex_cssColor.')\s*\):'; // $2 $3
    $style_size='^size\(\s*('.lwc_util::$rex_cssFontSize.')\s*\):';         // $4
    $style_width='^(width|height|padding)\(\s*('.lwc_util::$rex_cssLength.')\s*\):'; // $5 $6
    $this->rex_cell_style='/^!|^(left|right|center|top|bottom|middle):|'.$style_color.'|'.$style_size.'|'.$style_width.'/iu';

    $this->cell_style_props=array(
      'align' => false,
      'valign' => 'vertical-align',
      'color' => 'color',
      'bgcolor' => 'background-color',
      'size' => 'font-size',
      'width' => 'width',
      'height' => 'height',
      'padding' => 'padding');
  }
  private function read_cell_style(&$tag,&$style,&$cont){
    while(preg_match($this->rex_cell_style,$cont,$m)){
      if($m[1]){
        if(preg_match('/^(?:left|right|center)$/i',$m[1]))
          $style['align']=strtolower($m[1]);
        else
          $style['valign']=strtolower($m[1]);
      }else if($m[2]){
        if(strtolower($m[2])=='color')
          $style['color']=$m[3];
        else
          $style['bgcolor']=$m[3];
      }else if($m[4]){
        $size=$m[4];
        if(preg_match('/^[\-.\d]+$/',$size))$size.='px';
        $style['size']=$size;
      }else if($m[5]){
        // width height padding
        $attr=strtolower($m[5]);
        $len=lwc_util::add_unit_to_csslength($m[6]);
        $style[$attr]=$len;
      }else{
        // !
        $tag='th';
      }

      $cont=substr($cont,strlen($m[0]));
    }
  }

  private function read_style_row(&$colstyles,$row){
    for($c=0;$c<$this->cols;){
      // span
      $cN=$c+1;
      while($cN<$this->cols&&trim($row[$cN-1])==='>')$cN++;

      $cont=$row[$cN-1];
      if($cont){
        $celltag=null;
        $style=array();
        $this->read_cell_style($celltag,$style,$cont);

        // replaceの場合
        $style['celltag']=$celltag;
        if($cont)$style['content']=$cont;
        for(;$c<$cN;$c++)
          $colstyles[$c]=$style;

        // overrideの場合
        // for(;$c<$cN;$c++){
        //   if($celltag!==null)$colstyles[$c]['celltag']=$celltag;
        //   foreach($this->cell_style_props as $key => $value){
        //     if($style[$key]!==null)$colstyles[$c][$key]=$style[$key];
        //   }
        //   if($cont)$colstyles[$c]['content']=$cont;
        // }
      }else{
        $c=$cN;
      }
    }
  }

  public function html(){
    if($this->rows==0)return '';
    $this->_construct_vrows();
    $colstyles=array();

    $ret='<table class="lwiki-table">';
    for($r=0;$r<$this->rows;$r++){
      $row=&$this->data[$r];
      if($row['columns']){
        $this->read_style_row($colstyles,$row);
        continue;
      }

      $vr=$row['ivcol'];

      $ret.='<tr class="lwiki-table-row">';
      for($c=0;$c<$this->cols;$c++){
        $cont=$row[$c];
        if($cont===null)break;
        if($cont===false)continue; // span

        $attr=' class="lwiki-table-cell"';

        // span
        $this->check_span($vr,$c,$vrN,$cN,$cont);
        if($this->is_sortable){
          $this->clear_span($vr,$c,$vrN,$cN,$cont);
        }else{
          $this->clear_span($vr,$c,$vrN,$cN,false);
          $rspan=$vrN-$vr;
          $cspan=$cN-$c;
          if($rspan>1)$attr.=' rowspan="'.$rspan.'"';
          if($cspan>1)$attr.=' colspan="'.$cspan.'"';
        }

        // 属性
        if($colstyles[$c]===null)$colstyles[$c]=array();
        $style=$colstyles[$c];
        $tag=$row['celltag']?$row['celltag']: ($style['celltag']?$style['celltag']: 'td');
        $this->read_cell_style($tag,$style,$cont);

        $css='';
        foreach($this->cell_style_props as $key => $cssProperty){
          if($cssProperty&&$style[$key])
            $css.=$cssProperty.':'.$style[$key].';';
        }
        if($css!=='')
          $attr.=' style="'.$css.'"';
        if($style['align']!==null)
          $attr.=' align="'.$style['align'].'"';

        // 中身
        if($cont===''&&$colstyles[$c]['content'])
          $cont=$colstyles[$c]['content'];

        $ret.='<'.$tag.$attr.'>'.$this->conv->iconvert(trim($cont)).'</'.$tag.'>';
      }
      $ret.='</tr>';
    }
    $ret.='</table>';
    return $ret;
  }
};

// block constructs
lwiki_language::$defaultInstance->register_pattern(
  '^([\*]+|[\-\+]+|[:\|]|\/\/|>+(?: |$))',
  function($conv,$head,$content,&$pos){
    // インラインモードではそのまま解釈
    if($conv->flagInline)
      return htmlspecialchars($head);

    $i=$pos;

    $c=mb_substr($head,0,1);
    switch($c){
    case '*':
      $config_max_level=5;
      $config_max_level_edit=3;

      $level=mb_strlen($head);

      // 部分編集用
      $partial_edit='';
      if($conv->pageid!=''){
        if($level<=$config_max_level_edit&&preg_match('/^\G.+$/m',$content,$m,0,$pos-strlen($head))){
          $partial_edit=$conv->generate_editlink($conv->pageid,$m[0]);
        }
      }

      $cont=lwc_read_braced_line($content,$i);
      if($cont===false||$cont==='')break;
      $pos=$i;

      if($level>$config_max_level){
        $level=$config_max_level;
        $cont=mb_substr($head,$config_max_level).$cont;
      }

      $attrs=' class="lwiki-header"';

      // id は " #" で指定する (前に空白を少なくとも1つ置く)。
      $id=false;
      if(preg_match('/\s+\#([\-\w._]+)$/u',$cont,$m,PREG_OFFSET_CAPTURE)){
        $cont=substr($cont,0,$m[0][1]);
        $id=$m[1][0];
      }

      $tag='h'.($level+1);
      $cont=$conv->iconvert($cont);
      $text=lwc_html_gettext($cont);
      if($id===false)
        $id='anchor-'.lwiki_hash($text.mt_rand(),'0f01437fac2b5f2f');
      $attrs.=' id="'.$id.'"';

      $conv->header_append($level,'<a class="lwiki-internal-link" href="#'.$id.'">'.htmlspecialchars($text).'</a>');
      return '<'.$tag.$attrs.'>'.$cont.$partial_edit.'</'.$tag.'>'.PHP_EOL;
    case '+':
    case '-':
      $list=new lwc_list($conv);
      for(;;){
        $result=$list->process1($head,$content,$i);
        if($result===false)
          return $list->html().htmlspecialchars($head);

        $pos=$i;

        if($result===true)
          return $list->html();

        if(preg_match('/\G[\-\+ ]+/u',$content,$m,0,$i)){
          $head=$m[0];
          $i+=strlen($head);
          $pos=$i; //※$pos は、この関数が呼び出された時と同様に - の後に置く。
        }else
          return $list->html();
      }
      break;
    case ':':
      $ret='<dl>';
      $dd='';
      for(;;){
        if($head===':'){
          if($dd){
            $ret.='<dd>'.$conv->convert($dd).'</dd>'.PHP_EOL;
            $dd='';
          }

          $dt=lwc_read_braced_line($content,$i);

          $attrs='';
          {
            $className='';
            while(preg_match('/^class\(('.lwc_util::rex_CLASS.')\):/u',$dt,$m)){
              $className=$className?$className.' '.$m[1]:$m[1];
              $dt=substr($dt,strlen($m[0]));
            }

            if($className)
              $attrs.=' class="'.$className.'"';
          }
          $ret.='<dt'.$attrs.'>'.$conv->iconvert($dt).'</dt>'.PHP_EOL;
        }else{
          if($dd)$dd.=PHP_EOL;
          $dd.=lwc_read_braced_line($content,$i);
        }

        // check next line
        if(!preg_match('/\G[ :]/u',$content,$m,0,$i))break;
        $head=$m[0];
        $i+=strlen($m[0]);
      }

      if($dd){
        $ret.='<dd>'.$conv->convert($dd).'</dd>'.PHP_EOL;
        $dd='';
      }

      $pos=$i;
      return $ret.'</dl>';
    case '>':
      $cont=lwc_read_braced_line($content,$i);
      if($cont===false)break;
      if($head!=='> ')
        $cont=mb_substr($head,1).$cont;
      $pos=$i;

      while(preg_match('/\G>+(?: |$)/mu',$content,$m,0,$i)){
        $i+=strlen($m[0]==='> '?$m[0]:mb_substr($m[0],0,1));
        $r=lwc_read_braced_line($content,$i);
        if($r===false)break;
        $pos=$i;
        $cont.=PHP_EOL.$r;
      }

      return '<div class="lwiki-quote">'.$conv->convert($cont).'</div>';
    case '|':
      $table=new lwc_table($conv);
      $table->add_row();

      $iMax=strlen($content);
      while($i<$iMax){
        $r=lwc_read_until('(?=[\|\n]|$)',$content,$i);
        if($r===false)break;
        $c=lwc_char_after($content,$i);

        if($c!=="|")break;

        $table->add_cell($r);

        if(preg_match('/\G\|(?:\n([ |])?|$)/u',$content,$m,0,$i)){
          $i+=strlen($m[0]);
          if($m[1]==='|'){ // \n|
            $pos=$i;
            $table->add_row();
          }else if($m[1]!==' '){
            $pos=$i;
            return $table->html();
          }
        }else{
          $i+=strlen($c);
        }
      }

      // 失敗時
      $table->remove_row();
      return $table->html().'|';
    case '/':
      // comment
      if(preg_match('/\G(?:\\\\\n|.)*$\n?/um',$content,$m,0,$i))
        $i+=strlen($m[0]);
      $pos=$i;
      return '';
    }
    return htmlspecialchars($head);
  }
);

// entity constructs
lwiki_language::$defaultInstance->register_pattern(
  '&(;|\$;?|#\d+;|#[\da-fA-F]+;|\w+;?|(?=\s*\{))',
  function($conv,$name,$content,&$pos){
    if(preg_match('/^(#\d+|#x[\da-fA-F]+|nbsp|amp|[lg]t|quot);$/u',$name))
      return '&'.$name;
    $i=$pos;
    switch($name){
    case ';':return '';
    case 'lbrace;':return '{';
    case 'rbrace;':return '}';
    case 'br;':return '<br/>';
    case '$':case '$;':return '$';
    case 'color':
      if(($args=lwc_read_args($content,$i))){
        $style='';
        if(preg_match('/^\s*'.lwc_util::$rex_cssColor.'\s*$/u',@$args[0]))
          $style.='color:'.trim($args[0]).';';
        if(preg_match('/^\s*'.lwc_util::$rex_cssColor.'\s*$/u',@$args[1]))
          $style.='background-color:'.trim($args[1]).';';
        if(preg_match('/^\s*'.lwc_util::$rex_cssColor.'\s*$/u',@$args[2]))
          $style.='border:1px solid '.trim($args[2]).';padding:1px;margin:0 1px;';
        if($style&&($wiki=lwc_read_brace($content,$i))!==false){
          $pos=$i;
          return '<span style="'.$style.'">'.$conv->iconvert($wiki).'</span>';
        }
      }
      break;
    case 'size':
      if(($args=lwc_read_args($content,$i))){
        $style='';
        if(preg_match('/^\s*\d+\s*$/u',$args[0])){
          $style.='font-size:'.trim($args[0]).'px;';
        }else if(preg_match('/^\s*[\-\w%]+\s*$/u',$args[0]))
          $style.='font-size:'.trim($args[0]).';';
        if($style&&($wiki=lwc_read_brace($content,$i))!==false){
          $pos=$i;
          return '<span style="'.$style.'">'.$wiki=$conv->iconvert($wiki).'</span>';
        }
      }
      break;
    case 'verb': // 中身をそのまま出力
      if(false!==($cont=lwc_read_brace($content,$i))){
        $pos=$i;
        $cont=htmlspecialchars($cont);
        $cont=str_replace(' ','&nbsp;',$cont);
        $cont=str_replace("\n",'<br/>',$cont);
        return $cont;
      }
      break;
      //-----------------------------------
    case 'kbd':
    case 'code':
    case 'pre':
      // * → $toconvert: 中身を変換するかどうか
      $toconvert=false;
      $c=lwc_char_after($content,$i);
      if($c==='*'){
        $toconvert=true;
        $i+=strlen($c);
      }

      if($name==='pre')$isblock=true;
      goto htmltag;
    case 'div':
      $isblock=true;
      goto htmltag;
    case 'span':
      goto htmltag;
    htmltag:
      // PARAMETERS
      //   $name              ! タグ名
      //   $toconvert = true  ! 中身を変換するか?
      //   $isblock   = false ! 末尾の改行を削除するか?

      // (...) → $className: class 属性を読み取り
      $isTitled=false;
      $language='';
      $contentHead='';
      $attributes='';
      $className='';
      $styles='';
      if(($args=lwc_read_args($content,$i))){
        for($j=0,$jN=count($args);$j<$jN;$j++){
          $a=trim($args[$j]);

          // attribute=value
          if(preg_match('/^([\w_-]+)=(.*)$/u',$a,$m)){
            $prop=$m[1];
            $value=$m[2];
            if($name=='pre'){
              if($prop==='title'){
                if($value){
                  $contentHead.='<div class="lwiki-explicit-title">'.htmlspecialchars($value).'</div>';
                  $className.=' lwiki-explicit-title';
                }
                $isTitled=true;
              }
            }else if($name==='div'){
              if($prop==='color'){
                $colors=explode(':',$value);
                if(preg_match('/^'.lwc_util::$rex_cssColor.'$/u',$colors[0]))
                  $styles.='color:'.$colors[0].';';
                if(preg_match('/^'.lwc_util::$rex_cssColor.'$/u',$colors[1]))
                  $styles.='background-color:'.$colors[1].';';
                if(preg_match('/^'.lwc_util::$rex_cssColor.'$/u',$colors[2]))
                  $styles.='border:1px solid '.$colors[2].';';
              }else if($prop==='padding'){
                if(preg_match('/^\s*'.lwc_util::$rex_cssLength.'(?:\s+'.lwc_util::$rex_cssLength.')*\s*$/u',$value))
                  $styles.='padding:'.$value.';';
              }
            }
            continue;
          }

          // !language if &pre,&code
          if(preg_match('/^![-_\w\/]+$/u',$a)){
            if($name==='pre'||$name==='code'){
              $lang=substr($a,1);
              $language=$language?$language.'/'.$lang:$lang;
              continue;
            }
          }

          if(preg_match('/^[-_\w\s]+$/u',$a))
            $className.=' '.$a;
        }
      }

      if($language){
        $cls='lwiki-language-'.$language;
        $className.=' '.$cls;

        // &pre: determine title
        if($name==='pre'&&!$isTitled){
          $desc=$conv->language()->codeTitles[preg_replace('/.*\//u','',$language)];
          if(!$desc)$desc=$language;
          $className.=' lwiki-implicit-title';
          $attributes.=' data-lwiki-title="'.htmlspecialchars($desc).'"';
        }
      }

      if($className)$attributes.=' class="'.htmlspecialchars(substr($className,1)).'"';
      if($styles)$attributes.=' style="'.$styles.'"';

      $cont=$isblock?lwc_read_block($content,$i):lwc_read_brace($content,$i);
      if($cont!==false){
        if($isblock)lwc_skip_single_nl($content,$i);
        $cont=trim($cont,"\r\n");
        if($toconvert!==false){
          if($isblock)
            $cont=$conv->convert($cont);
          else
            $cont=$conv->iconvert($cont);
        }else
          $cont=htmlspecialchars($cont);
        $pos=$i;
        return $contentHead.'<'.$name.$attributes.'>'.$cont.'</'.$name.'>';
      }
      break;
      //-----------------------------------
    case 'tag':
      if(false!==($cont=lwc_read_brace($content,$i))){
        $pos=$i;
        $conv->tags_append($cont);
        return '';
      }
      break;
    case '': // &{ ... }
      if(($wiki=lwc_read_brace($content,$i))!==false){
        $pos=$i;
        return $conv->convert(trim($wiki,"\r\n"));
      }
      break;
    default:
      if(($fun=$conv->entity_handler($name))&&($ret=$fun($conv,$content,$i))!==false){
        $pos=$i;
        return $ret;
      }
      break;
    }

    return htmlspecialchars('&'.$name);
  }
);

lwiki_language::$defaultInstance->codeTitles=array(
  # from prog.std.css
  'c'    => 'C Language',
  'cpp'  => 'C++',
  'cs'   => 'C#',
  'x86'  => 'x86 Assembly',
  'il'   => 'Common Inermediate Language',
  'vb'   => 'Visual Basic',
  'vbs'  => 'VBScript',
  'bash' => 'Bash Script', # Bourne-Again Shell
  'el'   => 'Emacs Lisp',
  'html' => 'HTML',
  'xml'  => 'XML',
  'css'  => 'CSS',
  'js'   => 'JavaScript (ECMAScript)',
  'php'  => 'PHP (Hypertext Preprocessor)',
  'txt'  => 'text/plain',

  # added
  'tex' => 'TeX',
  'latex' => 'LaTeX',
  'bash-interactive' => 'Bash Interactive'
);

lwiki_language::$defaultInstance->register_entity('img',true,true,function($name,$args,$cont,$conv,$content,&$i){
  // entity img;
  // 2014-06-30 KM: Created

  if($cont===false)return false;

  $style='';
  $alt='';
  if($args){
    $narg=0;
    for($iarg=0;$iarg<count($args);$iarg++){
      if(preg_match('/^\s*(?:([a-zA-Z0-9_]+)\s*=\s*)?(.*?)\s*$/u',$args[$iarg],$m)){
        switch($m[1]){
        case 'alt':
          $alt.=$m[2];
          break;
        case 'width':
          goto style_width;
        case 'height':
          goto style_height;
        case '':
          $narg++;
          if($narg==1)
            goto style_width;
          else if($narg==2)
            goto style_height;
          break;
        style_width:
          if(preg_match('/^'.lwc_util::$rex_cssLength.'$/',$m[2]))
            $style.='width:'.lwc_util::add_unit_to_csslength($m[2]).';';
          break;
        style_height:
          if(preg_match('/^'.lwc_util::$rex_cssLength.'$/',$m[2]))
            $style.='height:'.lwc_util::add_unit_to_csslength($m[2]).';';
          break;
        }
      }
    }
  }
  if($style)$style=' style="'.$style.'"';
  if($alt)$alt=' alt="'.htmlspecialchars($alt).'"';

  return '<img'.$style.$alt.' src="'.htmlspecialchars(trim($cont)).'" />';
});

// inline constructs (two character inline sequences)
// - >>\d+ comment reference
// - ~~    line break
// - [[    link
// - ''    bold
// - '''   italic
// - ##    mark
// - %%    strike
// - __    u
// - ,,    sub
// - ^^    sub
// - ==    dfn
// - $     inline math-mode latex
// - <?\w+ code
lwiki_language::$defaultInstance->register_pattern(
  '(>>\d+|~~|\[\[|'."'''?".'|##|%%|__|,,|\^\^|==)',
  function($conv,$spec,$content,&$pos){
    $spec2=mb_substr($spec,0,2);
    switch($spec2){
    case '~~':
      return '<br/>';
    case '>>':
      // anchor (if comment)
      $num=mb_substr($spec,2);
      return '&gt;&gt;<a class="lwiki-comment-anchor" href="#lwiki-comment-'.$num.'">'.$num.'</a>';
    case "''":case '%%':case '==':case '##':
    case '__':case ',,':case '^^':case '$':case '<?':
      $tag='span';
      $isverb=false;
      switch($spec2){
      case "''":
        $tag=$spec==="'''"?'i': 'b';
        break;
      case '%%':$tag='del';break;
      case '__':$tag='u';break;
      case ',,':$tag='sub';break;
      case '^^':
        $term='\^\^';
        $tag='sup';
        break;
      case '==':$tag='dfn';break;
      case '##':$tag='em class="lwiki-marker"';$etag='em';break;
      }
      if(!@$term)$term=$spec;
      if(!@$etag)$etag=$tag;

      if(false!==($cont=lwc_read_until($term,$content,$pos))){
        if($isverb)
          $cont=htmlspecialchars($cont);
        else
          $cont=$conv->iconvert($cont);

        if($spec=='==')
          $conv->keywords_append(lwc_html_gettext($cont));

        return '<'.$tag.'>'.$cont.'</'.$etag.'>';
      }
      break;
    case '[[':
      if(false!==($cont=lwc_read_until('\]\]',$content,$pos))){
        $i=0;

        $text=lwc_read_until_brace('>',$cont,$i);

        // $link: html, $hash: false or safestring
        if(false!==($link=lwc_read_until_brace('#',$cont,$i))){
          $hash=htmlspecialchars(substr($cont,$i));
        }else{
          $link=substr($cont,$i);
          if(preg_match('/^{.+}$/u',$link))
            $link=substr($link,1,strlen($link)-2);
          $hash=false;
        }

        // $text: html, $link: string, $hash: false or safestring
        if($text!==false){
          // [[text>link]] の時 (link に対しては変換を実行せず)
          $text=$conv->convert($text,true);
          $link=preg_replace('/\s+/u',' ',$link);
        }else{
          // [[link]] の時 (link に対して変換を実行)
          $text=$conv->convert($link,true);
          $link=preg_replace('/\s+/u',' ',lwc_html_gettext($text));
        }

        if(preg_match('/^(https?|ftp|file|mailto):(.*)$/u',$link,$m)){
          $className='lwiki-external-link';
          if($m[1]=='mailto')
            $className='lwiki-external-mail';
          else if($m[1]=='ftp')
            $className='lwiki-external-ftp';

          // 相対パス。現在の頁と同じ scheme に強制的に書き換えられる。(relative スキームなども作るべきか?)
          if(preg_match('/^(?:https?|ftp|file):(?!\/\/)/u',$link))
            $link=$m[2];

          $link=htmlspecialchars($link);
          if($hash!==false)
            $link.='#'.$hash;

          return '<a class="'.$className.'" href="'.$link.'" target="_blank">'.$text.'</a>';
        }

        if(preg_match('/^arxiv:([0-9]{4}\.[0-9]{4,5})$/u',$link,$m)){
          $className='lwiki-external-link';
          $link1='http://arxiv.org/abs/'.$m[1];
          $link2='http://arxiv.org/pdf/'.$m[1].'.pdf';
          return '<a class="lwiki-external-link" href="'.$link1.'" target="_blank">'.$text.'</a>'.
            '<a class="lwiki-external-link" href="'.$link2.'" target="_blank"></a>';
        }

        if(preg_match('/^news:([^\/]+)$/u',$link,$m)){
          $className='lwiki-external-link';
          $link='https://groups.google.com/forum/#!forum/'.$m[1];
          return '<a class="'.$className.'" href="'.$link.'" target="_blank">'.$text.'</a>';
        }

        if($hash!==false&&!$link)
          return '<a class="lwiki-internal-link" href="#'.htmlspecialchars($hash).'">'.$text.'</a>';

        return \LWIKI_PHP_BEGIN_TAG.'echo \lwiki\page\generate_dynamic_link('
          .var_export($text,true).','
          .var_export($link,true).','
          .var_export($hash,true).');'
          .\LWIKI_PHP_END_TAG;
      }
      break;
    }

    return htmlspecialchars($spec);
  }
);

//-----------------------------------------------------------------------------
// ここから: Ageha JavaScript Library 依存

lwiki_language::$defaultInstance->register_entity('math',false,true,function($name,$args,$cont,$conv,$content,&$i){
  if($cont===false)return false;
  return '<span class="aghfly-tex-math">'.htmlspecialchars($cont).'</span>';
});
lwiki_language::$defaultInstance->register_entity('begin',true,true,function($name,$args,$cont,$conv,$content,&$i){
  if($args!==false&&preg_match('/^[\w*@]+$/u',$args[0])){
    if($cont!==false){
      lwc_skip_single_nl($content,$i);
      return '<div class="aghfly-begin-'.$args[0].'">'.htmlspecialchars($cont).'</div>';
    }
  }
  return false;
});
lwiki_language::$defaultInstance->register_pattern(
  '((?<![^-\/\(\[\{\<\s「『【〈《。、．，])\$(?=[^\s$])|\<\?(?:[_a-zA-Z][-_\w]*\*?\s?))',
  function($conv,$spec,$content,&$pos){
    $spec2=mb_substr($spec,0,2);
    switch($spec2){
    case '$':case '<?':
      $tag='span';
      $isverb=false;
      switch($spec2){
      case '$':
        $term='(?<=\S)\$(?!\w)';
        $tag='span class="aghfly-tex-math"';
        $etag='span';
        $isverb=true;
        break;
      case '<?':
        $lang=trim(substr($spec,2));
        if(substr($lang,-1)==='*')
          $lang=mb_substr($lang,0,-1);
        else
          $isverb=true;

        $term='\?\>';
        $tag='code class="lwiki-language-'.$lang.'"';
        $etag='code';
        break;
      }
      if(!$term)$term=$spec;
      if(!$etag)$etag=$tag;

      if(false!==($cont=lwc_read_until($term,$content,$pos))){
        if($isverb)
          $cont=htmlspecialchars($cont);
        else
          $cont=$conv->iconvert($cont);

        if($spec=='==')
          $conv->keywords_append(lwc_html_gettext($cont));

        return '<'.$tag.'>'.$cont.'</'.$etag.'>';
      }
      break;
    }

    return htmlspecialchars($spec);
  }
);

// ここまで: Ageha JavaScript Library 依存
//-----------------------------------------------------------------------------

lwiki_language::$defaultInstance->register_pattern(
  '(\`)',
  function($conv,$spec,$content,&$pos){
    if($conv->option_prog_enabled){
      if(false!==($cont=lwc_read_until('\`',$content,$pos))){
        $cont=htmlspecialchars($cont);
        return '<code class="lwiki-language-'.$conv->option_prog_enabled.'">'.$cont.'</code>';
      }
    }
    return htmlspecialchars($spec);
  }
);
lwiki_language::$defaultInstance->register_pattern(
  '^@([a-z]+)\b',
  function($conv,$spec,$content,&$pos){
    if($conv->option_prog_enabled){
      global $lwiki_base_resourceDirectoryUrl;

      $icon='';
      switch($spec){
      case 'fn':
        $icon='<img class="lwiki-prog-item" alt="@fn" src="'.$lwiki_base_resourceDirectoryUrl.'/icons/prog-meth.png" /> ';
        break;
      case 'op':
        $icon='<img class="lwiki-prog-item" alt="@fn" src="'.$lwiki_base_resourceDirectoryUrl.'/icons/prog-oper.png" /> ';break;
      case 'namespace':
        $icon='<img class="lwiki-prog-item" alt="@fn" src="'.$lwiki_base_resourceDirectoryUrl.'/icons/prog-ns.png" /> ';
        break;
      case 'param':
        $icon='<img class="lwiki-prog-item" alt="@param" src="'.$lwiki_base_resourceDirectoryUrl.'/icons/prog-param.png" />';
        if(($args=lwc_read_args($content,$pos,'[',']'))){
          for($j=0,$jN=count($args);$j<$jN;$j++){
            $icon.='<span class="lwiki-prog-param-attribute">'.htmlspecialchars($args[$j]).'</span>';
          }
        }
        $icon.=' ';
        break;
      case 'class':
        $icon='<img class="lwiki-prog-item" alt="@class" src="'.$lwiki_base_resourceDirectoryUrl.'/icons/prog-class.png" /> ';
        break;
      }

      if($icon){
        if(false!==($cont=lwc_read_until('(?=[\r\n])|$',$content,$pos))){
          $cont=trim($cont);
          if($cont!=''){
            $cont=$conv->iconvert($cont);
            return $icon.'<code class="lwiki-language-'.$conv->option_prog_enabled.'">'.$cont.'</code>';
          }
        }
      }
    }

    return '@'.htmlspecialchars($spec);
  }
);


lwiki_language::$defaultInstance->register_pattern(
  '(  +|[&<>\n\t]|\\\\\n)',
  function($conv,$letter,$content,&$pos){
    switch($letter){
    case "\\\n":return '';
    case "\n":return '<br/>';
    case ' ':return '&#x20;';
    case "\t":return '&nbsp;&nbsp;&nbsp;&nbsp;';
    default:
      if(substr($letter,0,1)==' ')
        return str_repeat('&nbsp;',strlen($letter)-1).' ';
      else
        return htmlspecialchars($letter);
    }
  }
);

//-----------------------------------------------------------------------------

?>
