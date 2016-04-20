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

autorequire('littlemdesign\web\social\pinterest\Pinterest'); 

$userid = ''; 

$pn     = new Pinterest($userid);

if(!$pn->isReady()) {
  echo "Could not make IG: ".$pn->getError()."\n";
  exit(0);
}

$result = $pn->simplePublicFeed();

if($result === false) {
  echo "X: fail: ".$pn->getError()."\n";
  exit(1);
}

echo 'X: result: '.print_r($result,true)."\n";

?>
