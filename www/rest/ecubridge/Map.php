<?php

/**
 * 
 * Map - Show how the channels are currently configured.
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
 * Map - let the GUI know the channel configuration
 * 
 */

class Map extends Resource {

  public function __construct() {
    parent::__construct("Map");
  }
  
  public function get() {
    
    /* fetch the data */
    
    $result  = "";
   
    $jsonObj = (object)array(
      "data"   => "",
      "status" => "OK",
      "error"  => ""
    );

    /* build the map display */
    
    $cmdr = new ECUBridge;

    if(!$cmdr->isReady()) {
      $jsonObj->status = "ERROR";
      $jsonObj->error  = "Can't make ECUBridge: ".$cmdr->getError();
      return json_encode($jsonObj);
    }
    
    $rows = $cmdr->map();
    
    if(!$rows) {
      $jsonObj->status = "ERROR";
      $jsonObj->error  = "Can't get channel map: ".$cmdr->getError();
      return json_encode($jsonObj);
    }
    
    $results = array();
    
    $i = 1;
    foreach($rows as $idx => $row) {
      
      $it  = $row[0];
      $if  = $row[1];
      $src = $row[2];
      $dst = $row[3];
      $of  = $row[4];
      $ot  = $row[5];
      
      $obj = (object)array(
        "channel"         => $i,
        "inputtransform"  => $it,
        "inputfilter"     => $if,
        "src"             => $src,
        "dst"             => $dst,
        "outputfilter"    => $of,
        "outputtransform" => $ot
      );
      
      $results[] = $obj;
      
      $i++;
    }
    
    $jsonObj->data = $results;
     
    /* all done, pass it back */
    
    return json_encode($jsonObj);
    
  }

}

?>
