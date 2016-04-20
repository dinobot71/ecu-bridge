<?php

/**
 * 
 * api.php - This is the REST/Ajax dispatcher for chumpcar.
 * 
 * NOTE: you must have added a web server rewrite rule:
 * 
 *   RewriteRule ^/rest/ /rest/api.php
 *   
 * @package chumpcar
 * 
 * @author Little m Design (Michael Garvin)
 * @copyright Copyright (c) 2016-, Little m Design
 * 
 */

/* 
 * make sure we can auto-load from any libraries we 
 * need.
 * 
 */

{ /* make sure we can auto-load */
  
  if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') 
  { $DS = "\\"; } else { $DS = "/";}

  $path = dirname(__FILE__); while(!empty($path)) {
    if(is_readable($path.$DS."autoloader.php")) {
      require_once($path.$DS."autoloader.php"); break;
    }
    $path = dirname($path);
  }
}

/* 
 * manually bring in RESTDispatcher since we are using 
 * static methods only.
 * 
 */

autorequire('littlemdesign\web\http\RESTDispatcher');

/* 
 * register our actions, if we don't register an 
 * action it will be auto-mapped by RESTDispatcher.
 *  
 */

$actions = array(

);

foreach($actions as $route => $classSpec) {
  RESTDIspatcher::register($route, $classSpec);
}

/* dispatch the current request */

RESTDIspatcher::dispatch();

?>