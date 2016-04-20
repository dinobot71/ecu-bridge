<?php 

/**
 * 
 * web \ oauth \ OAuthStrava - this is the OAuth consumer for
 * Strava, our adaptor for doing authentication via OAuth with 
 * Strava (remotely).  For more details see OAuthConsumer.php
 * 
 * Strava uses OAuth 2.x so its much simpler, but we can still
 * use the same framework of begin/finish login.
 * 
 * You an learn more here:
 * 
 *  https://www.strava.com/settings/api
 * 
 * Authentication:
 * 
 *   http://strava.github.io/api/v3/oauth/
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

autorequire('littlemdesign_web_oauth_OAuthConsumer');
autorequire('littlemdesign_web_oauth_OAuthUser');

/**
 * 
 * class OAuthStrava - MapMyRun authentication consumer.
 * Note that OAuthMapMyRun uses OAuth 2.x protocal...which is 
 * vastly simplified.  So we don't need all the signing 
 * machinery of OAuthConsumer, but we follow the same
 * model overall process to be consistent.
 * 
 */

class littlemdesign_web_oauth_OAuthStrava
  extends littlemdesign_web_oauth_OAuthConsumer {

  /* where to go at Strava to fetch a request token */
    
  private $requestURL      = "";
  
  /* where to redirect browser (and user) to for authentication */
  
  private $authURL         = "";
  
  /* general REST API access */
   
  private $apiURL          = "https://www.strava.com/api/v3/";
   
  /* the base URL for things like starting login process */
  
  private $baseURL         = "https://www.strava.com/";
  
  /* the user account in context */
  
  private $userId          = "";
   
  /**
   * 
   * Standard constructor, you have to inform the LinkedIN
   * consumer of the consumer key, consumer secret and
   * the callback to use when returning from authentication
   * at Facebook.
   * 
   * @param string $key - application key (consumer key)
   * 
   * @param string $secret - application secret (consumer secret)
   * 
   * @param string $callback - the call back URI
   * on.
   * 
   */
      
  public function __construct($key, $secret, $callback="") {
    
    parent::__construct('Strava', $key, $secret, $callback);
    
    $this->authURL   = $this->baseURL."/oauth/authorize?client_id=$key&response_type=code&redirect_uri=$callback&scope=view_private";

    $this->accessURL = $this->baseURL."oauth/token";
                 
  }
  
  /**
   * 
   * setUserId() most requests will need to be done in 
   * the context of a user account, so you must first set
   * the userid before doing anything.
   * 
   * @param integer $userId - the user account id from Withings
   * 
   */
  
  public function setUserId($userId) {
    $this->userId = $userId;
  }
  
  public function getUserId() {
    return $this->userId;
  } 
  
  /**
   * 
   * beginLogin() start the authentication protocol, this will lead to 
   * a browser redirect, so this should be the last thing you call in
   * your script.
   * 
   */
  
  public function beginLogin() {
    
    $this->info("beginning login...");
    
    if(!$this->isReady()) {
      $this->error("beginLogin - not ready.");
      return false;
    }
    
    /* have headers already been sent? */
    
    if(headers_sent()) {
      $this->error("beginLogin) - headers already sent.");
      return false;
    }
    
    /*
     * because Strava is OAuth 2.x we simply redirect 
     * the user's browser to the request URL, there is
     * no server-to-server interaction at this point.
     * 
     */
    
    $url     = $this->authURL;
    if(!is_object($url)) {
      $url   = littlemdesign_web_http_URL::create($url);
    }
    
    $textURL = $url->toString();
    
    if(!preg_match('/^https:/', $textURL)) {
      $this->warning("beginLogin() - non secured authenitcation endpoint in use.");
    }
    
    /* redirect */
    
    $this->info("beginLogin() - authenticating user via: $textURL");
    
    header("Location: $textURL");
    
    return true;
  }
  
  /**
   * 
   * finishLogn() - once user has authenticated at the remote provider
   * we need to finish the authentication sequence, and generate a
   * valid access token, and a basic user profile that we can use in
   * our application, to save the session details, log a user in, etc.
   * 
   * @param string $code - the verified token 
   * 
   * @param string $verifier - the verifier secret, note that for 
   * LinkedIN the $verifier is the $state we sent to LinkedIN when
   * we requested authentication.
   * 
   * @return object returns a standardized user profile
   * object (web\oauth\OAuthUser) that gives us details about the user who 
   * authenticated.  If there is a problem, then exactly
   * false is returned.
   *  
   */
   
  public function finishLogin($code, $verifier='') {
    
    if(!$this->isReady()) {
      $this->error("finishLogin() - not ready.");
      return false;
    }
    
    $this->info("user has authenticated ($code, $verifier)...");
    
    /*
     * exchange our authentication code for an access token 
     * proper.  Build up a Strava style access token URL, 
     * and make it a proper URL object so we can just pump 
     * it through our usual machinery.
     * 
     */    
    
    $url  = $this->accessURL."?";
    $url .= "client_id={$this->getKey()}&";
    $url .= "client_secret={$this->getConsumerSecret()}&";
    $url .= "code=$code";
    
    if(!is_object($url)) {
      $url   = littlemdesign_web_http_URL::create($url);
    }
    
    /* covner the URL to the query part and non-query part, 
     * since we want to send the query part via POST for 
     * better security.
     * 
     */
    
    $textURL = $url->toString();
    $noQuery = array_shift(explode('?',$url->toString()));
    $query   = $url->getQuery();
    
    $form    = "";  
    foreach($query as $k => $v) {
      $form .= "$k=$v&";
    }
    $form    = trim($form, '&'); 
     
    /* 
     * use cURL to fetch the access token...
     * 
     */
    
    $curl = new littlemdesign_web_http_CURL($noQuery);
    if(!$curl->isReady()) {
      $this->error("finishLogin() - no cURL: ".$curl->getError());
      return false;
    }
    
    /* POST our request... */
    
    $result  = $curl->write(
      "POST", 
      $noQuery,
      $form,
      "application/x-www-form-urlencoded"); 
     
    /* havest */
      
    if($result === false) {
      $this->error("finishLogin() - problem with cURL request: ".$curl->getError());
      return false;
    }
    
    if($result->meta['http_code'] != 200) {
      $this->error("finishLogin() - API error: ".$result->data);
      return false;
    }
    
    $data = json_decode($result->data);
        
    if(!$data) {
      $this->error("finishLogin() - can't decode JSON data: ".$result->data);
      return false;
    }

    /*
     * Similar to Faacebook, Strava will give us an "access_token" 
     * field in a JSON object.
     * 
     */
    
    if(!isset($data->access_token)) {
      $this->error("finishLogin() - no access token returned.");
      return false;  
    }
    
    /* save the access token */
 
    $this->setToken($data->access_token);
    
    /* 
     * if things went correctly, the 'athlete' object will 
     * also be filled in so we don't have to make an 
     * additional call to fetch the profile.
     * 
     */
    
    if(!isset($data->athlete)) {
      $this->error("finishLogin() - no athlete profile.");
      return false;  
    }
    
    
    /* 
     * now fetch the user profile, this will both test that the
     * API is working and given us details on who the user is 
     * that allow us to co-ordinate future API calls on behalf
     * of the user.
     * 
     */
     
    $userId   = $data->athlete->id;
    $key      = $this->getKey();
    $secret   = $this->getConsumerSecret();
    $callback = $this->getCallback();
    $token    = $this->getToken();

    $this->setUserId($userId);
        
    $this->info(" . id       : $userId");
    $this->info(" . key      : $key");
    $this->info(" . secret   : $secret");
    $this->info(" . callback : $callback");
    $this->info(" . token    : $token");
    
    /* the actual fetch... */
    
    $uData = $data->athlete;
   
    /* build up our version of a user... */
       
    $user    = new littlemdesign_web_oauth_OAuthUser;
    $name    = "";
    $address = "";
    
    if(!empty($uData->firstname)) {
      $name = $uData->firstname." ".$uData->lastname;
    }
    
    if(!empty($uData->city)) {
      $address = $uData->city.", ".$uData->state." ".$uData->country;
    }
    
    $user->id           = $userId;
    $user->accessToken  = $token;
    $user->accessSecret = $secret;
    $user->provider     = 'strava';
    $user->firstName    = $uData->firstname;
    $user->lastName     = $uData->lastname;
    $user->gender       = $uData->sex;
    $user->displayName  = $name;
    $user->address      = $address;
    $user->profileURL   = "https://www.strava.com/athletes/$userId";
    $user->email        = $uData->email; 

    /* all done */
    
    return $user;
  }
  
  /**
   * 
   * get() - this is a simplified version of the  OAuthConsumer get()
   * method.  In OAuth 2.x we just need to pass along the access token,
   * we don't have to do a full out signed request.  
   * 
   * @param string $uri - the URI for the remote endpoint, the base URL
   * will be prepended, and the access token will be appended.
   * 
   * @param string $realm - (optional) some remote sites require a 
   * realm or scope.
   * 
   * @param string $verb - (optional) should almost always be GET.
   * 
   * @return mixed returns the object we got back or exactly false 
   * on any kind of error.
   * 
   */
  
  public function get($uri, $realm="", $verb="GET") {
    
    $url = $this->apiURL.$uri;
    
    if(!is_object($url)) {
      $url   = littlemdesign_web_http_URL::create($url);
    }
    
    /* add the access token */
    
    $headers = array(
      'Accept'           => '',
      'Api-Key'          => $this->getKey(),
      'Authorization'    => "Bearer {$this->getToken()}",
      'Content-Type'     => 'application/json'
    );
    
    $textURL = $url->toString();
    
    /* build up a cURL request... */
    
    $curl = new littlemdesign_web_http_CURL($textURL);
    if(!$curl->isReady()) {
      $this->error("get() - no cURL: ".$curl->getError());
      return false;
    }
    
    /* GET our request... */
    
    $result  = $curl->write(
      $verb, 
      $textURL,
      "", 
      $headers['Content-Type'], 
      $headers
    ); 
     
    /* havest */
      
    if($result === false) {
      $this->error("get() - problem with cURL request: ".$curl->getError());
      return false;
    }
    
    if(($result->meta['http_code'] != 200)&&($result->meta['http_code'] != 201)) {
      $this->error("get() - API error ({$result->meta['http_code']}): ".$result->data);
      return false;
    }
    
    $data = json_decode($result->data);
    
    if($data == false) {
      $this->error("get() - can not decode JSON result");
      return false;
    }
    
    /* pass it back */
    
    return $data;
  }
   
  /**
   * 
   * apiReady() - quick check for all things necessary to start
   * making requests.
   * 
   */
  
  public function apiReady() {
    
    if(!$this->isReady()) {
      $this->error("apiReady() - not ready.");
      return false;
    }
    
    if(empty($this->userId)) {
      $this->error("apiReady() - user id is not set.");
      return false;
    }
    
    if(empty($this->getToken())) {
      $this->error("apiReady() - no access token.");
      return false;
    }
    
    /* ready! */
    
    return true;
  }
  
  /**
   * 
   * workouts() fetch workouts from the provider 
   * and convert to a consistent format that we can
   * use regardless of what other fitness network
   * is providing the workout data.
   * 
   * @param mixed $startDate - (optional) the unix time stsmp 
   * for whento start gathering measures. If not numeric it 
   * will be converted via strtotime().
   * 
   * @return mixed return exactly false on error, 
   * otherwise an array of measures.
   * 
   */
  
  public function workouts($startDate="") {
    
    if(!$this->apiReady()) {
      $this->error("workouts() - API not ready: ".$this->getError());
      return false;
    }
    
    $results     = array();
    $start       = "";
    $startClause = "";
    $page        = 0;
    $asof        = time();
    
    /* figure out the request URI */
    
    if(!empty($startDate)) {
      if(!is_numeric($startDate)) {
        $start = strtotime($startDate);
      }
    }
    
    if(!empty($start)) {
      $startClause = "?after=$start";
    }
    
    $uri = "athlete/activities$startClause";
    
    /* 
     * Strava doesn't page reuslts for 'before' or
     * 'after' results...you get them all at once.
     * 
     */
    
    $pageData = $this->get($uri);

    if($pageData == false) {
      $this->error("workouts() - can not fetch page data: ".$this->getError());
      return false;
    }

    foreach($pageData as $idx => $workout) {
      
      /* harvest next workout ... */
        
      $id        = $workout->id;
      $routeId   = "";
      $address   = "";
      $latitude  = 0.0;
      $longitude = 0.0;
      $stamp     = strtotime($workout->start_date);
      $notes     = "";
      $kind      = "";
      $felt      = "";
      $source    = "";
      $distance  = 0.0;
      $duration  = 0.0;
      $calories  = 0.0;
      $title     = $workout->name;
        
      if(empty($workout->manual)||($workout->manual == false)) {
        $source = "Strava Mobile App";
      }
       
      /* try to figure out route/location */
        
      if(isset($workout->map->id)) {
        $routeId = $workout->map->id;
      }
        
      /* figure out the activity kind */

      $kind = strtolower($workout->type);
      
      if(preg_match('/^.*(ride)|(cycle)|(bike).*$/', $kind)) {
        $kind = "cycling";
      }
      if(preg_match('/^.*(run)|(job)|(walk).*$/', $kind)) {
        $kind = "running";
      }
      if(preg_match('/^.*(skate)|(roller)|(inline).*$/', $kind)) {
        $kind = "skating";
      }
      
      /* 
       * figure out the 'felt' if there is one, unfortunately
       * Strava does not seem to expose this in its REST API.
       * 
       * For now we leave it blank.
       * 
       */
              
      /* distance and time */
        
      if(isset($workout->distance)) {    
        $distance = $workout->distance;
      }
      if(isset($workout->moving_time)) {    
        $duration = $workout->moving_time;
      }
     
      if($duration == 0) {
        $this->error("workouts() - workout ($id) has no total time.");
        continue;
      }
      
      if($distance == 0) {
        $this->error("workouts() - workout ($id) has no total distance.");
        continue;
      }
          
      /* strava distance is in meters, we are in km */
          
      $distance = $distance / 1000.00;
          
      /* 
       * try to figure out the address...
       * 
       */
      
      $city    = $workout->location_city;
      $country = $workout->location_country;
      $state   = $workout->location_state;
            
      if(!empty($city)) {
        $address = "$city, $state $country";
      } else {
        $address = $country;
      }
      
      /* latitude and longitude ... */
      
      if(!empty($workout->start_latitude)) {
        $latitude  = $workout->start_latitude;
        $longitude = $workout->start_longitude;
      }
            
      /* ok make our workout object */

      $obj = (object)array(
        'id'        => $id,
        'source'    => $source,
        'routeid'   => $routeId,
        'when'      => date('Y-m-d H:i:s', $stamp),
        'notes'     => $notes,
        'address'   => $address,
        'latitude'  => $latitude,
        'longitude' => $longitude,
        'kind'      => $kind,
        'felt'      => $felt,
        'distance'  => $distance,
        'duration'  => $duration,
        'calories'  => $calories,
        'title'     => $title
      );
        
      $results[] = $obj;
        
    }
      
    /* pass back */
    
    $obj = (object)array(
      "asof" => date('Y-m-d H:i:s', $asof),
      "data" => $results
    );
    
    return $obj;
  }
}
  
?>