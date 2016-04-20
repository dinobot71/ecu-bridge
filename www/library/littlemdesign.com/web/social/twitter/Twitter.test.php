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

autorequire('littlemdesign\web\social\twitter\Twitter'); 

$key    = ''; 
$secret = '';

$tw     = new Twitter($key,$secret);

if(!$tw->isReady()) {
  echo "Could not make TW: ".$fb->getError()."\n";
  exit(0);
}

$result = $tw->simplePublicFeed('');

if($result === false) {
  echo "X: fail: ".$fb->getError()."\n";
  exit(1);
}

echo 'X: result: '.print_r($result,true)."\n";


?>
