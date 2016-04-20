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

/* make sure we auto-load required stuff */

autorequire('littlemdesign_web_geocode_Geocode');
autorequire('littlemdesign_web_geocode_Request');

echo "geocoding...\n";

$r = littlemdesign_web_geocode_Geocode::code('DE,Berlin,10117');
if(is_object($r)) {
  if(!$r->isReady()) {
    echo "Could not get response: ".$r->getError();
    exit(1);
  }
}

echo "Response: ".print_r($r,true)."\n";

echo "Ok.\n";


?>