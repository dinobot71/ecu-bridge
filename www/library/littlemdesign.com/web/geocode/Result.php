<?php 

/**
 * 
 * web \ geocode \ Result - lightweight container for geocoding
 * results.
 * 
 * @package littlemdesign.com
 * 
 * @author Little m Design (Michael Garvin)
 * @copyright Copyright (c) 2013-, Littl m Design
 * 
 */

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

/**
 * 
 * class Result - container for geocoding results. 
 * We don't expect all providers to fill in all 
 * attributes.  We also use this same object for 
 * both geocoding results and reverse geocoding 
 * results.
 *
 */

class littlemdesign_web_geocode_Result {

  public $latitude     = 0;   
  public $longitude    = 0;
  public $streetNumber = null;
  public $streetName   = null;
  public $locality     = null;
  public $city         = null;
  public $postalCode   = null;
  public $county       = null;
  public $countyCode   = null;
  public $region       = null;
  public $regionCode   = null;
  public $country      = null;
  public $countryCode  = null;
  public $timezone     = null;
  public $formatted    = null;
  
}
  
?>