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

autorequire('littlemdesign_web_geocode_Google');

/*
echo "initializing...\n";
$g = new littlemdesign_web_geocode_Google();
if(!$g->isReady()) {
  echo "Can't make geocoder: ".$g->getError()."\n";
  exit(1);
}

echo "geocoding...\n";
$result = $g->geocode('35 holland, ottawa, on, canada');
echo "result: ".print_r($result,true)."\n";
*/

/*
echo "initializing...\n";
$g = new littlemdesign_web_geocode_Google();
if(!$g->isReady()) {
  echo "Can't make geocoder: ".$g->getError()."\n";
  exit(1);
}

echo "geocoding...\n";
$result = $g->geocode(array('address' => '35 holland, ottawa', 'country' => 'canada'));
echo "result: ".print_r($result,true)."\n";
*/

/*
echo "initializing...\n";
$g = new littlemdesign_web_geocode_Google();
if(!$g->isReady()) {
  echo "Can't make geocoder: ".$g->getError()."\n";
  exit(1);
}

echo "geocoding...\n";
$result = $g->reverse(45.4021731, -75.7333709);
echo "result: ".print_r($result,true)."\n";
*/

echo "initializing...\n";
$g = new littlemdesign_web_geocode_Google();
if(!$g->isReady()) {
  echo "Can't make geocoder: ".$g->getError()."\n";
  exit(1);
}

echo "geocoding...\n";
$result = $g->geocode('DE,Berlin,10117');
echo "result: ".print_r($result,true)."\n";
                          
echo "Ok.\n";



?>