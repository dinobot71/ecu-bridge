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

autorequire('littlemdesign\web\oauth\OAuthWithings'); 

/* make the basic API */

$key    = 'cde0e93010cc524250821b8b7ff0661996d8f2e440fc6704e74b71065521c1'; 
$secret = '84dcb1656b6662b067640c07808e558879e333f5f655124db9f09bdf813f';
$w      = new OAuthWithings($key,$secret);

if(!$w->isReady()) {
  echo "Could not make Witings: ".$w->getError()."\n";
  exit(0);
}

/* "login" by setting the access token (what we saved in bodymeasuresource) */

$token  = "e4271f9d0d77776379bebd69eccc50cf785b67194d609ed7fc0f6e3804355";
$secret = "1322ee9c8c7eb07f275728f0d88735ebc6f041d6e06e623eed6bebdc943d5";
$userId = "4142537";

$w->setToken($token);
$w->setSecret($secret);
$w->setUserId($userId);

/* get body measures */

$result = $w->bodyMeasures();

echo 'X: result: '.print_r($result,true)."\n";

?>