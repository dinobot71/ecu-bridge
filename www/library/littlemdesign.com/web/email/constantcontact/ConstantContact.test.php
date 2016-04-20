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

autorequire('littlemdesign\web\email\constantcontact\ConstantContact');

$key   = ''; 
$token = '';
$cc    = new ConstantContact($key,$token);

if(!$cc->isReady()) {
  echo "Could not make CC: ".$cc->getError()."\n";
  exit(0);
}

$result = $cc->listMembers('');
echo 'X: result: '.print_r($result,true)."\n";


?>