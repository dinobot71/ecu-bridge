<?php 

/**
 * 
 * web \ http \ geocode \ Provider - super class for all providers
 * of geocoding services.  There will be one of these for Google, 
 * Bing, TomTom or whatever.
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

autorequire('littlemdesign_util_Object');
autorequire('littlemdesign_util_Error');
autorequire('littlemdesign_web_http_URL');
autorequire('littlemdesign_web_http_MIME');
autorequire('littlemdesign_web_http_Request');
autorequire('littlemdesign_web_http_Response');

/**
 * 
 * class Provider - base class for geocoding providers
 * such as Google, Bing, TomTom etc.  All such providers
 * must be able to map an address to latitude/longitude,
 * and do the same operation in reverse.
 *
 */

abstract class littlemdesign_web_geocode_Provider
  extends littlemdesign_util_Object {

  /**
   * 
   * Limit the number of responses we get back from
   * geocoding providers.
   * 
   * @var integer
   * 
   */
  	
  protected $maxResults = 10;
  	
  /**
   * 
   * geocode() - get the longitutde and latitude of 
   * the given address.  Differen providers have 
   * different support for  format and scoping, so 
   * you can provide either a string or an array 
   * of parameters. The given provider will interpret
   * the parameters as needed.
   *
   * For details on exactly what is supported by a 
   * provider, see the documentation for that provider.
   * In general you can at least expect that if you
   * give an address (as you would on google maps),
   * then you'll get something reasonable back.
   * 
   * @param mixed $address
   * 
   * @return Result[] return an array of Result objects.
   * All providers return the same kind of result 
   * objects.  If there is a problem, return exactly 
   * false.
   * 
   */
  
  abstract public function geocode($address);
  
  
  /**
   * 
   * reverse() - convert latitude and longitude back into
   * a normal address.  
   * 
   * @param float $latitude
   * @param float $longitude
   * 
   * @return Result all providers return a single result
   * object, if there was a problem, return exactly false.
   * 
   */
  
  abstract public function reverse($latitude, $longitude);
  	
  /**
   * 
   * doRequest() - helper method to do the actual web request 
   * to the goecoding web service.
   * 
   * @param URL $url - a proper URL object (the request)
   * 
   * @param string $mime - the content type we exchange with
   * the rmeote server.
   * 
   * @return Response (web\http\Response), or an error string
   * if there is a problem.
   * 
   */
  
  protected function doRequest($url, $mime='json') {
  	
  	if(!is_object($url) || !($url instanceof littlemdesign_web_http_URL)) {
  	  return "geocoding, expecting URL object in doRequest()";
  	}
  	
    /* make the request */
  	
  	$request = littlemdesign_web_http_Request::get($url,'',$mime);
  	if($request === false) {
  	  return "geocoding, can not make web request.";
  	}
  	if(!$request->isReady()) {
      return "geocoding, request not usable: ".$request->getError();
  	}
  	
  	/* do it! */
  	
  	$response = $request->send();
  	if(!$response->isReady()) {
  	  return "geocoding, response not usable: ".$response->getError();
  	}
  	
  	/* all done */
  	
  	return $response;
  }
}

?>