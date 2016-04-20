<?php

/**
 * 
 * Status - provides a quick summary of the ECU Bridge
 * status (for display in the GUI).
 * 
 * @package chumpcar
 * 
 * @author Little m Design (Michael Garvin)
 * @copyright Copyright (c) 2016-, Little m Design
 * 
 */

{ /* make sure we can auto-load */
  
  if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') 
  { $DS = "\\"; } else { $DS = "/";}

  $path = dirname(__FILE__); while(!empty($path)) {
    if(is_readable($path.$DS."configure.php")) {
      require_once($path.$DS."configure.php"); break;
    }
    $path = dirname($path);
  }
}

autorequire('littlemdesign\web\http\Resource');
autorequire('chumpcar\ecubridge\ECUBridge');

/**
 * 
 * Status - let the GUI know how the ECU Bridge is doing.
 * 
 */

class Status extends Resource {

  public function __construct() {
    parent::__construct("Status");
  }
  
  public function get() {
    
    /* fetch the data */
    
    $result  = "";
   
    $jsonObj = (object)array(
      "data"   => "",
      "status" => "OK",
      "error"  => ""
    );

    /* build the status message */
    
    $cmdr = new ECUBridge;

    if(!$cmdr->isReady()) {
      $jsonObj->status = "ERROR";
      $jsonObj->error  = "Can't make ECUBridge: ".$cmdr->getError();
      return json_encode($jsonObj);
    }
    
    $jsonObj->data = $cmdr->status();
    
    if(!$jsonObj->data) {
      $jsonObj->status = "ERROR";
      $jsonObj->error  = "Can't get status: ".$cmdr->getError();
      return json_encode($jsonObj);
    }
    
    /* all done, pass it back */
    
    return json_encode($jsonObj);
    
  }

}

?>
