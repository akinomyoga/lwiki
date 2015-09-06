<?php // -*- mode:php -*-

namespace lwiki\page;

function generate_dynamic_link($html,$name,$hash){
  if(!$name)
    return '<a class="lwiki-internal-link" href="#'.htmlspecialchars($hash).'">'.$html.'</a>';

  $targetPageid=urlencode($name);

  //$link='?id='.$targetPageid;
  $link=$_SERVER['PHP_SELF'].'?id='.$targetPageid;
  $classAttribute='';

  if(!file_exists('.lwiki/data/page.'.$targetPageid.'.htm')){
    $classAttribute=' class="lwiki-missing-link"';
    $link.='&mode=edit';
  }else if($hash!==false)
    $link.='#'.htmlspecialchars($hash);

  return '<a'.$classAttribute.' href="'.$link.'">'.$html.'</a>';
}

function begin_document($title,$headContent=""){
  global $lwiki_base_resourceDirectoryUrl;
  global $pageid;
  global $lwiki_page_commonHead;
  global $LWIKI_URL_AGH;
  if($headContent!=""){
    $headContent=preg_replace('/\s*\z/su',PHP_EOL,
      preg_replace('/^/mu','  ',$headContent),1);
  }

  echo <<<EOF
<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
  <meta http-equiv="Content-Style-Type" content="text/css" />
  <meta http-equiv="Content-Script-Type" content="text/javascript" />
  <title>$title</title>
$headContent  <link rel="stylesheet" type="text/css" charset="utf-8" href="{$LWIKI_URL_AGH}/prog.std.css" />
  <meta name="agh-fly-type" content="tex" />
  <script type="application/x-tex" id="tex-preamble">\documentclass{article}\usepackage{amsmath,amssymb,bm,color}\edef\lbrace{\{}\edef\rbrace{\}}</script>
  <script type="text/javascript" charset="utf-8" src="{$LWIKI_URL_AGH}/agh.fly.js"></script>
  <script type="text/javascript" charset="utf-8" src="{$lwiki_base_resourceDirectoryUrl}/lwiki.js"></script>
  <link rel="stylesheet" type="text/css" charset="utf-8" href="{$lwiki_base_resourceDirectoryUrl}/lwiki.css" />
$lwiki_page_commonHead</head>
<body class="lwiki-menued">

EOF;

echo <<<EOF
<div class="lwiki-page-holder">
<h1>$title</h1>

EOF;
}

function end_document(){
  echo <<<EOF
</div><!-- end of .lwiki-page-holder -->

EOF;

  include 'stub.menu.php';

  echo <<<EOF
</body>
</html>

EOF;
}

?>
