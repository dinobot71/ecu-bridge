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

autorequire('littlemdesign\web\oauth\OAuthMapMyRun'); 

/* make the basic API */

$key    = '6k5cjwdckmxjyc5kqppevbvryvhsvc6j'; 
$secret = 'rvGmNVNRcAFWgkVdZzvEFhPec85x6bfYBVsHv98junX';
$mmr     = new OAuthMapMyRun($key,$secret);

if(!$mmr->isReady()) {
  echo "Could not make Map My Run: ".$mmr->getError()."\n";
  exit(0);
}

/* "login" by setting the access token (what we saved in bodymeasuresource) */

$token  = "9bb92d7aac8ba39593df17be40c1d1488cb00aef";
$secret = "";
$userId = "185357";

$mmr->setToken($token);
$mmr->setUserId($userId);

echo "Fetching activity name for '372'...\n";

$result = $mmr->activityName(372);

echo 'Activity name: '.print_r($result,true)."\n";

/* get body measures */

echo "Fetching workouts...\n";

$result = $mmr->workouts();

echo 'X: result: '.print_r($result,true)."\n";

?>