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

/*
 * test code 
 * 
 */

$p = new littlemdesign_db_DBConnectArgs;
echo "X: initial valid: ".($p->isValid()?"YES":"NO")."\n";
$p->parseParms("mysql://myhost/db");
echo "X: ".print_r($p,true)."\n";
echo "X: final valid: ".($p->isValid()?"YES":"NO")."\n";
?>
