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

autorequire('littlemdesign_web_http_CURL');

echo "Making CURL...\n";

$curl = new littlemdesign_web_http_CURL("http://slashdot.com");

echo "Doing operation...\n";

/*
$h = fopen(__FILE__,'r');
fseek($h, 0);
$result = $curl->write(
    $method      = "PUT", 
    $url         = "http://localhost/rest/mike?expand=true", 
    $content     = $h,
    $contentType = '', 
    $headers     = array("Content-Length" => filesize(__FILE__)));
if($result === false) {
  echo "Can't do curl: ".$curl->getError()."\n";
  exit(1);
}
fclose($h);

echo "Curl Result: ".print_r($result,true)."\n";

*/

$result = $curl->write(
    $method      = "options", 
    $url         = "http://localhost/rest/mike?expand=true", 
    $content     = 'to-delete',
    $contentType = '',
    $headers     = array());
if($result === false) {
  echo "Can't do curl: ".$curl->getError()."\n";
  exit(1);
}

echo "Curl Result: ".print_r($result,true)."\n";

echo "OK.\n";



?>