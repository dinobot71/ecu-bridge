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

autorequire('littlemdesign_web_http_Request');

/* test */

echo "Doing request...\n";

$response = 
  littlemdesign_web_http_Request::post("http://localhost/rest/mike?expand=true", array("a"=>"b"), 'json')->send();

if(!$response->isReady()) {
  echo "Could not do request: ".$response->getError()."\n";
  exit(1);
}

echo "Request: [".$response->status."]: ".$response->toString()."\n";

echo "Ok.\n";

?>