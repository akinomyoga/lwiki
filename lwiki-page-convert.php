<?php // -*- mode:php -*-

const LWIKI_PHP_BEGIN_TAG="<\x3Fphp ";
const LWIKI_PHP_END_TAG="\x3F>";

function lwiki_hash($key,$salt='4fa52acaf207'){
  return substr(base64_encode(md5('salt+'.$salt.'+'.$key,true)),0,12);
}

function lwiki_canonicalize_linebreaks($text){
  return preg_replace('/[ \t　]*(?:\r?\n|\r)/u',"\n",$text);
}

require_once dirname(__FILE__).'/lib/lib.lwiki.php';
require_once dirname(__FILE__).'/lib/lib.page-edit.php';

$pageid=@$argv[1];
if(!$pageid)exit;
\lwiki\edit\page_convert();

// if($argv[1]==NULL)
//   $fname='php://stdin';
// else
//   $fname=$argv[1];

// $content=file_get_contents($fname);
// $content=lwiki\convert\convert($content);
// echo $content.PHP_EOL;

?>
