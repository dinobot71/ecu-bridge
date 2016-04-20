<?php 

/* make sure we can auto-load */

{  	
  $DS = "\\";   
  if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $DS = "\\";
  } else {
    $DS = "/";
  }
    
  $path = dirname(__FILE__);
  while(!empty($path)) {
    if(is_readable($path.$DS."autoloader.php")) {
      require_once($path.$DS."autoloader.php");
      break;
    }
    $path = dirname($path);
  }
}

autorequire('littlemdesign_web_http_URL');

/* test code */

$transform = array(
  array("http://example.com/",         "http://www.example.com/"),
  array("http://example.com/",         "http://example.com:80/"),
  array("https://example.com/",        "https://example.com:443/"),
  array("http://example.com/",         "http://example.com///"),
  array("http://example.com/a/b/c/",   "http://example.com/a/./b/../b/c/"),
  array("http://example.com/?c=d&a=b", "http://example.com/?c=d&a=b"),
);

/*
$url = littlemdesign_web_http_URL::create("/form/sub/?c=d&a=b&e[]=1");
echo "X: ".$url->toString()."\n";
*/

echo "normalizing...\n";

foreach($transform as $item) {

  echo " . ".$item[0]."...\n";
  
  $url1 = littlemdesign_web_http_URL::create($item[1]);
  $url2 = $url1->normalize();
  
  $result = $url2->toString();
  
  if($result != $item[0]) {
  	
  	echo "ERROR: Normalied string ($result) didn't match for: ".$item[0]."\n";
  	exit(1);
  }
}

echo "OK.\n";


?>