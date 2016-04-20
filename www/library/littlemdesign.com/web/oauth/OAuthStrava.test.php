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

autorequire('littlemdesign\web\oauth\OAuthStrava'); 

/* make the basic API */

$key    = '6932'; 
$secret = 'abf1742e8123c1fa8478431b0af2c584db81707a';
$strava = new OAuthStrava($key,$secret);

if(!$strava->isReady()) {
  echo "Could not make Map My Run: ".$strava->getError()."\n";
  exit(0);
}

/* "login" by setting the access token (what we saved in bodymeasuresource) */

$token  = "261f8dcbbdefd95c6ad13991349834669737ca76";
$secret = "";
$userId = "8179109";

$strava->setToken($token);
$strava->setUserId($userId);

/* get body workouts */

echo "Fetching workouts...\n";

$result = $strava->workouts('2015-06-23 22:45:04');

echo 'X: result: '.print_r($result,true)."\n";

?>