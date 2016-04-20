<?php 

/**
 * 
 * web \ geocode \ Google - do geocoding with Google Geocode API
 * 
 * @package littlemdesign.com
 * 
 * @author Little m Design (Michael Garvin)
 * @copyright Copyright (c) 2013-, Littl m Design
 * 
 * Google API reference:
 * 
 *   https://developers.google.com/maps/documentation/geocoding/
 *   
 * Notes:
 * 
 *   - we don't need an API key to do geocoding
 *   
 *   - free usage is limited to 2500 requests a day
 *   
 *   - must be used only in conjunction with Google maps.
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
autorequire('littlemdesign_web_geocode_Provider');

/**
 * 
 * class Google - do geocoding with Google Geocode API
 *
 */

class littlemdesign_web_geocode_Google
  extends littlemdesign_web_geocode_Provider {
  	
  
  private $baseURL = "https://maps.googleapis.com/maps/api/geocode/json?";
  
  /**
   *
   * standard constructor, $attrs can be used to  pass in API
   * key etc.
   * 
   */
  	
  public function __construct($attrs=null) {

  	parent::__construct('Google', 'littlemdesign_web_geocode', true);

  	$this->makeReady();
  }
  
  /**
   * 
   * geocode() - get the latitude and longitude for a given
   * address.  If you provide a string, it will be taken to
   * be the address to use.  If you provide an array, it can
   * have various attributes that allow you to refine the 
   * search.  Supported attributes:
   * 
   *    address     - the address to convert
   *    locality    - county or city area (refines the search)
   *    country     - country name or code code (ccTLD style) (refines the search)
   *    postal_code - postal/zip code (refines the search)
   *    
   * 
   * @param mixed $address
   * 
   * @return Result[] return an array of Result objects.
   * All providers return the same kind of result 
   * objects.  If there is a problem, return exactly 
   * false.
   * 
   */
  
  public function geocode($address) {
  	
  	$url = $this->baseURL;
  	
  	if(!$this->isReady()) {
      $this->error("geocoding, not ready.");
  	  return false;
  	}
  	
  	if(is_array($address)) {

  	  /* get the basic address */
  		
  	  $addr = null;
  	  
  	  if(isset($address['address'])) {
  	  	$addr = $address['address'];
  	  }
  	  if(($addr===false)||($addr===null)||empty($addr)) {
        $this->error("geocoding, no address given.");
  	    return false;
  	  }
  	  
  	  $url .= "address=$addr&sensor=false";
  	  
  	  $components = array();
  	  
  	  /*
  	   * look for filtering attributes...
  	   * 
  	   */
  	  
  	  if(isset($address['locality']) && !empty($address['locality'])) {
  	  	$components['locality'] = $address['locality'];
  	  }
  	  if(isset($address['country']) && !empty($address['country'])) {
  	  	$components['country'] = $address['country'];
  	  }
  	  if(isset($address['postal_code']) && !empty($address['postal_code'])) {
  	  	$components['postal_code'] = $address['postal_code'];
  	  }
  	  
  	  if(count($components) != 0) {
  	  	
  	  	$url .= "&components=";
  	  	
  	  	$data = "";
  	  	foreach($components as $k => $v) {
  	  	  $data .= "$k:$v".'|';
  	  	}
  	  	$data = trim($data, '|');
  	  	
  	  	$url .= $data;
  	  }
  	    	  
  	} else {
  		
  	  /* we only have the basic address */
  		
  	  if(($address===false)||($address===null)||empty($address)) {
        $this->error("geocoding, no address given.");
  	    return false;
  	  }
  	
  	  $url .= "address=$address&sensor=false";
  	}
  
  	/* 
  	 * make a proper URL object, so we can choose
  	 * the encoding to use etc.
  	 * 
  	 */
  	
  	$requestURL = littlemdesign_web_http_URL::create($url);
  	$requestURL->setEncoding(littlemdesign_web_http_URL::RFC_3986);
  	
  	$response = $this->doRequest($requestURL);
  	if(!is_object($response)) {
  	  $this->error("geocoding, problem doing request: $resposne");
  	  return false;
  	}

  	/* process the response */
  	
  	$raw    = $response->body->results;
  	$status = $response->body->status;
  	
  	if(strtolower(trim($status)) != "ok") {
  	  $this->error("geocoding, google returned an error code: $status (URL was ".$requestedURL->toString().")");
  	  return false;
  	}

  	$hits = array();
  	
  	/*
  	 * walk through the matches and try to fill in result
  	 * objects.
  	 * 
  	 */
  	
  	$fieldMap = array(
  	  "street_number" => "streetNumber",
  	  "route"         => "streetName",
  	  "neighborhood"  => "locality",
  	  "locality"      => "city",
  	  "administrative_area_level_2" => "county",
  	  "administrative_area_level_1" => "region",
  	  "country"       => "country",
  	  "postal_code"   => "postalCode"
  	);
  	
  	$shortFieldMap = array(
  	  "country" => "countryCode",
  	  "administrative_area_level_1" => "regionCode"
  	);
  	  	  	
  	foreach($raw as $obj) {

  	  $hit = new littlemdesign_web_geocode_Result();
  	  
  	  if(isset($obj->address_components)) {
  	  	
  	  	/* the components of the address */
  	  	
  	  	$components = $obj->address_components;
  	  	 	
  	  	foreach($components as $comp) {
  	  		
  	  	  $value = $comp->long_name;
  	  	  $code  = $comp->short_name;

  	  	  /* figure out what result attribute we have */
  	  	  
  	  	  foreach($comp->types as $type) {
  	  	  	
  	  	  	if(isset($fieldMap[$type])) {
  	  	  		
  	  	  	  $fieldName = $fieldMap[$type];  	    	  		  	
  	  	  	  $hit->{$fieldName} = $value;
  	  	  	  
  	  	  	  break;
  	  
  	  	  	}
  	  	  }

  	  	  foreach($comp->types as $type) {
  	  	  	if(isset($shortFieldMap[$type])) {	
  	  	  	  $fieldName = $shortFieldMap[$type];  	    	  		  	
  	  	  	  $hit->{$fieldName} = $comp->short_name;
  	  	  	}
  	  	  }
  	  	}
  	  } 
  	  
  	  if(isset($obj->formatted_address)) {
  	  	
  	  	/* human readable address */
  	  	
  	  	$hit->formatted = $obj->formatted_address;
  	  }
  	  
  	  if(isset($obj->geometry)) {
  	  	
  	  	/* get the bounding box */
  	  	
  	  	if(isset($obj->geometry->location)) {
  	  		
  	  	  $loc = $obj->geometry->location;
  	  	  $hit->latitude  = $loc->lat;   
          $hit->longitude = $loc->lng;
  	  	}	
  	  }
  	  
  	  $hits[] = $hit;
  	}

  	/* all done, pass back the results */
  	
  	return $hits;
  }
  
  public function reverse($latitude, $longitude) {
  	return $this->geocode(sprintf("%F, %F", $latitude, $longitude));
  }
  
}

?>
  
  