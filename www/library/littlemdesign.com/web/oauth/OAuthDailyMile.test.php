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

autorequire('littlemdesign\web\oauth\OAuthDailyMile'); 

/* make the basic API */

$key    = 'VoTzkj7Tk0TM97F28afoi0lfHITqKBM3B75AkEm6'; 
$secret = 'vp0K7B0XVBaMeRLlUlvmBNlnXek7psnet5ngegJo';
$dm      = new OAuthDailyMile($key,$secret);

if(!$dm->isReady()) {
  echo "Could not make Witings: ".$dm->getError()."\n";
  exit(0);
}

/* "login" by setting the access token (what we saved in bodymeasuresource) */

$token  = "jIxzSoPHT5MZC1mpjGaDhP7xUStRkt2s1tUuTkx9";
$secret = "";
$userId = "MichaelG2726";
$userId = "mikesk8s";

$dm->setToken($token);
$dm->setUserId($userId);

/* get body measures */

$result = $dm->workouts("2015-01-01");

echo 'X: result: '.print_r($result,true)."\n";

?>