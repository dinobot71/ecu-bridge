<?php 

/**
 * 
 * web \ geocode \ Geocode - this factory can be used to do 
 * geocoding (and reverse geocoding), by using a default 
 * geocoding service, or by selecting a specific one to use.
 * Initially we just have the Google provider, but will 
 * eventually add alternative providers.
 * 
 * For providers that need API keys or other custom configuration;
 * all custom configuration will be handled through the ".ini" 
 * preference system.  If really needced users can invoke the 
 * providers directly. 
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

/* make sure we auto-load required stuff */

autorequire('littlemdesign_web_geocode_Provider');
autorequire('littlemdesign_web_geocode_Google');

/**
 * 
 * class Geocode - this is geocoding factor. Use the
 * static methods as needed:
 * 
 *   find($name, $attrs=null)
 *    
 *     - create (if needed) a geocoding provider by name,
 *       currently supported providers are 'google'.  $attrs 
 *       is passed to the constructor of the provider to
 *       allow users to pass in API keys etc.
 *       
 *   code($address, $name='google')
 *   
 *     - perform geocoding on $address using $name 
 *       provider.  Some providers may take a more
 *       complex object thatn string for $address.
 *       See the providers class documentation for
 *       details.
 *       
 *   reverse($latitude, $longitude, $name='google')
 *   
 *    - do the reverse of code()   
 *
 */

class littlemdesign_web_geocode_Geocode {

  /**
   * 
   * $pool is the registry of providers we have for 
   * geocoding services.  Its lazy loaded, we cache them
   * as we create them.
   * 
   * @var Provider (web\geocode\Provider)
   * 
   */
	
  private static $pool = array();
  
  /**
   * 
   * find() - create (if needed) a new provider (of the given kind). 
   * If we want, register/cache it at the same time so that next
   * time we can just reuse the one we have.  If we already 
   * have this one cached, then just return that one.
   * 
   * @param string $name the provider to create and register.
   * 
   * @param array $attrs parameters to pass into constructor of 
   * 
   * @param boolean $autoRegister true if we want to also register
   * (cache) this provider.
   * 
   * @return Provider (web\geocode\Provider), or exactly false
   * if there is problem.
   * 
   */
	
  static public function find($name, $attrs=null, $autoRegister=true) {
      
    /* check parameters */
  	
    $name = strtolower(trim($name));
    if(empty($name)) {
      error_log("[littlemdesign \ web \ geocode \ Geocode] ERROR can not find provider, no name.");
      return false;
  	}
  	
  	$provider = null;
  	
  	/* already have one? */
  	
  	if(isset(self::$pool[$name])) {
  	  return self::$pool[$name];	
  	}
  	
  	/* look up a provider */
  	
  	switch($name) {
      case 'google':
      	$provider = new littlemdesign_web_geocode_Google($attrs);
        break;
        
      default: 
      	break;
  	}
  	
  	if($provider === null) {
  	  error_log("[littlemdesign \ web \ geocode \ Geocode] ERROR can not find provider ($name).");
      return false;	
  	}
  	
  	/* cache it? */
  	
  	if($autoRegister) {
      self::$pool[$name] = $provider;
  	}
  	
  	/* all done */
  	
  	return $provider;
  }
  
  /**
   * 
   * code() - geocode an address.  The address data can be
   * whatever the given provider accepts.  See its class 
   * documentation for details.
   * 
   * If the provider has not yet been cached/registered, it 
   * will be cached for you (lazy loading).
   * 
   * @param $address the address to geocode.
   * 
   * @param $name the provider ot use
   * 
   * @param $attr construction parameters (array) to pass to
   * the provider (the first time we cache the provider).
   * 
   * @return Response (web\geocode\Response)
   * 
   */
  
  static public function code($address, $name='google', $attrs=null) {

  	$provider = self::find($name,$attrs,true);
  	
  	if($provider === false) {
      error_log("[littlemdesign \ web \ geocode \ Geocode] ERROR can not code, no provider.");
  	  return true;
  	}
  	
  	return $provider->geocode($address);
  }
  
  /**
   * 
   * reverse() - reverse geocode co-ords.  
   * 
   * If the provider has not yet been cached/registered, it 
   * will be cached for you (lazy loading).
   * 
   * @param $latitude the co-ord(s) to reverse.
   * @param $longitude the co-ord(s) to reverse.
   * 
   * @param $name the provider ot use
   * 
   * @param $attr construction parameters (array) to pass to
   * the provider (the first time we cache the provider).
   * 
   * @return Response (web\geocode\Response)
   * 
   */
  
  static public function reverse($latitude, $longitude, $name='google', $attrs=null) {
  	
  	$provider = self::find($name,$attrs,true);
  	
  	if($provider === false) {
      error_log("[littlemdesign \ web \ geocode \ Geocode] ERROR can not reverse, no provider.");
  	  return true;
  	}
  	
  	return $provider->reverse($latitude, $longitude);
  	
  }
}

?>
