<?php 

/**
 * 
 * web \ oauth \ OAuthMapMyRun - this is the OAuth consumer for
 * MapMyRun (actually its UnderArmour), our adaptor for doing authentication 
 * via OAuth with MapMyRun (remotely).  For more details see OAuthConsumer.php
 * 
 * MapMyRun uses OAuth 2.x so its much simpler, but we can still
 * use the same framework of begin/finish login.
 * 
 * You an learn more here:
 * 
 *  https://developer.underarmour.com/apps/mykeys
 * 
 * Authentication:
 * 
 *   https://developer.underarmour.com/docs/v71_OAuth_2_Demo
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
 * class OAuthMapMyRun - MapMyRun authentication consumer.
 * Note that OAuthMapMyRun uses OAuth 2.x protocal...which is 
 * vastly simplified.  So we don't need all the signing 
 * machinery of OAuthConsumer, but we follow the same
 * model overall process to be consistent.
 * 
 */

class littlemdesign_web_oauth_OAuthMapMyRun
  extends littlemdesign_web_oauth_OAuthConsumer {

  /* where to go at Map My Run to fetch a request token */
    
  private $requestURL      = "";
  
  /* where to redirect browser (and user) to for authentication */
  
  private $authURL         = "";
  
  /* general REST API access */
   
  private $apiURL          = "https://oauth2-api.mapmyapi.com/v7.1/";
   
  /* the base URL for things like starting login process */
  
  private $baseURL         = "https://api.mapmyfitness.com/v7.1/";
  
  /* the user account in context */
  
  private $userId          = "";
  
  /* flag for having done one time mappings */
  
  private $initDone        = false;
   
  /* the mappings of activities from id to human text */
  
  private $activityMap     = array();
  private $activityParents = array();
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
    
    parent::__construct('MapMyRun', $key, $secret, $callback);
    
    $this->authURL   = $this->baseURL."oauth2/authorize/?client_id=$key&response_type=code&redirect_uri=$callback";

    $this->accessURL = $this->baseURL."oauth2/access_token/";
                 
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
     * because MapMyRun is OAuth 2.x we simply redirect 
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
     * proper.  Build up a MapMyRun style access token URL, 
     * and make it a proper URL object so we can just pump 
     * it through our usual machinery.
     * 
     */    
    
    $url  = $this->accessURL."?grant_type=authorization_code&";
    $url .= "client_id={$this->getKey()}&";
    $url .= "client_secret={$this->getConsumerSecret()}&";
    $url .= "redirect_uri={$this->getCallback()}&";
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
     * Similar to Faacebook, MapMyRun will give us an "access_token" 
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
     * now fetch the user profile, this will both test that the
     * API is working and given us details on who the user is 
     * that allow us to co-ordinate future API calls on behalf
     * of the user.
     * 
     */
     
    $userId   = $data->user_id;
    $expires  = $data->expires_in + time();
    $userURI  = "user/self/";
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
    
    $uData = $this->get($userURI);
    
    if($uData == false) {
      $this->error("finishLogin() - can not fetch user profile: ".$this->getError());
      return false; 
    }
    
    /* do we have an address? */
    
    $address = "";
    if(isset($uData->location)) {
      $address  = $uData->location->locality.", ";
      $address .= $uData->location->region." ";
      $address .= $uData->location->country;
    }
    
    /* do we have a profile photo? */
    
    $photo = "";
   
    /* build up the user object */
     
    $user = new littlemdesign_web_oauth_OAuthUser;
    
    $user->id           = $userId;
    $user->accessToken  = $token;
    $user->accessSecret = $secret;
    $user->provider     = 'mapmyrun';
    $user->firstName    = $uData->first_name;
    $user->lastName     = $uData->last_name;
    $user->gender       = $uData->gender;
    $user->displayName  = $uData->display_name;
    $user->address      = $address;
    $user->photoURL     = $photo;
    $user->profileURL   = "http://www.mapmyfitness.com/profile/$userId/";
    $user->birth        = $uData->birthdate;
    $user->email        = $uData->email; 
    $user->expiry       = $expires;

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
  
  private function init() {
    
    if($this->initDone !== false) {
      
      /* already done */
      
      return true;
    }
    
    /*
     * do one time init like map activity codes to names 
     * 
     */
    
    
    $uri = "activity_type/";
    
    $data = $this->get($uri);
    
    if(!isset($data->_embedded->activity_types) || !is_array($data->_embedded->activity_types)) {
      $this->error("init() - can't find activity types.");
      return false;
    }
    
    $data    = $data->_embedded->activity_types;
    
    foreach($data as $idx => $item) {
      
      /* try to map name to id */
      
      $name = $item->name;
      
      if(!isset($item->_links->self)||!is_array($item->_links->self)) {
        continue;
      }
      
      $link = $item->_links->self[0];
      $id   = $link->id;
      
      $this->activityMap[$id] = strtolower($name);
      
      /* 
       * mapmy run has *hundreds* of activity types, so we
       * try to gather the parent groupings, so we can aggregrate
       * up to something more reasonable to work with.
       * 
       */
      
      if(!isset($item->_links->parent)||!is_array($item->_links->parent)) {
        continue;
      }
      
      $link         = $item->_links->parent[0];
      $pid          = $link->id;
      
      $this->activityParents[$id] = $pid;
    }

    /*
     * debug 
     * 
     * 
    echo "dumping activity map...\n";
    foreach($this->activityMap as $id => $name) {

      $parent = "";
      
      if(isset($this->activityParents[$id])) {
        $pid    = $this->activityParents[$id];
        $parent = $this->activityMap[$pid];
      }
      
      echo "[$parent] $id => $name\n";
      
    }
    echo ".\n";
    
    *
    */
      
    /* all done */
    
    $this->initDone = true;
    
    return $this->initDone;
  }
  
  /**
   * 
   * activityName() - convert an activity code into a human 
   * readable name.  MapMyRun uses a hierarchy of activities, 
   * so the name is a "path" from the root to this activity.
   * 
   * @param integer $code the activity code to convert to a 
   * name.
   * 
   * @return mxied the path from the activity group root to 
   * this specific activity (separated by '\')
   * 
   */
  
  public function activityName($code) {
    
    if(!$this->apiReady()) {
      $this->error("activityName() - not ready.");
      return false;
    }
    
    if(!is_numeric($code)) {
      $this->error("activityName() - $code must be integer");
      return false;
    }
    
    $path = "";
    
    if(!isset($this->activityMap[$code])) {
      $this->error("activityName() - No such activity ($code)");
      return false;
    }
    
    $parents   = array();
    $parents[] = $this->activityMap[$code];
    
    while(true) {
      
      if(!isset($this->activityParents[$code])) {
        break;
      }
        
      $pid    = $this->activityParents[$code];
      $parent = $this->activityMap[$pid];
      
      $parents[] = $parent;
      
      /* walk up to next parent */
      
      $code    = $pid;
    }
    
    /* reverse the array */
    
    $parents = array_reverse($parents);
    
    /* join to path */
    
    $path = implode(' \\ ', $parents);
    
    return $path;
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
    
    /* 
     * basically ready, but...MapMyRun encodes things
     * like activities as numbers, so make sure we do
     * some init to do one time mappings where needed.
     * 
     */
    
    if($this->init() == false) {
      $this->error("apiReady() - init failed: ".$this->getError());
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
        $start = date('Y-m-d\TH:i:s', $start);
      }
    }
    
    if(!empty($start)) {
      $startClause = "&started_after=$start";
    }
    
    $uri = "workout/?user={$this->getUserId()}$startClause";
    
    /* 
     * results are paged, so we have to loop until we don't have 
     * any 'next' links.
     * 
     */
    
    while(true) {
      
      /* get the next page of data */
      
      $pageData = $this->get($uri);

      if($pageData == false) {
        $this->error("workouts() - can not fetch page data: ".$this->getError());
        return false;
      }
      
      /* harvest ... */
      
      if(!isset($pageData->_embedded->workouts)) {
        $this->error("workouts() - can not find workouts in page data.");
        return false;
      }
      
      $workouts = $pageData->_embedded->workouts;
      
      foreach($workouts as $idx => $workout) {
        
        $id        = "";
        $routeId   = "";
        $address   = "";
        $latitude  = 0.0;
        $longitude = 0.0;
        $stamp     = strtotime($workout->start_datetime);
        $notes     = $workout->notes; 
        $kind      = "";
        $felt      = "";
        $source    = "";
        $distance  = 0.0;
        $duration  = 0.0;
        $calories  = 0.0;
        $title     = $workout->name;
        
        /* figure out the id */
        
        if(!isset($workout->_links->self)||!is_array($workout->_links->self)) {
          $this->error("workouts() - workout has no id.");
          continue;
        }
        $id = $workout->_links->self[0]->id;
        
        /* where did the data come from, manual, mobile app... */
        
        if(isset($workout->source)&&!empty($workout->source)&&($workout->source != "null")) {
          $source = $workout->source;
        }
        
        /* try to figure out route/location */
        
        if(isset($workout->_links->route)&&is_array($workout->_links->route)) {
          $routeId = $workout->_links->route[0]->id;
        }
        
        /* figure out the activity kind */
        
        if(isset($workout->_links->activity_type)&&is_array($workout->_links->activity_type)) {
          $code = $workout->_links->activity_type[0]->id;
          $kind = $this->activityName($code);
        }
        
        /* 
         * figure out the 'felt' if there is one, unfortunately
         * for mapmyrun there doesn't appear to be any way to 
         * grab this information.  The REST API doesn't have any
         * endpoints/links from workouts leading to the "quality"
         * data for a workout.
         * 
         * For now we leave it blank.
         * 
         */
        
        
        /* distance and time */
        
        if(isset($workout->aggregates)) {
          
          if(isset($workout->aggregates->metabolic_energy_total)) {
            $calories = $workout->aggregates->metabolic_energy_total;
          }
          if(!isset($workout->aggregates->elapsed_time_total)||($workout->aggregates->elapsed_time_total == 0)) {
            $this->error("workouts() - workout ($id) has no total time.");
            continue;
          }
          
          if(!isset($workout->aggregates->distance_total)||($workout->aggregates->distance_total == 0)) {
            $this->error("workouts() - workout ($id) has no total distance.");
            continue;
          }
          
          $duration = $workout->aggregates->elapsed_time_total;
          $distance = $workout->aggregates->distance_total;
          
          /* mapmyrun distance is in meters, we are in km */
          
          $distance = $distance / 1000.00;
        }
        
        /* 
         * if we have a $routeId...we can expand it to get the
         * associated location data.
         * 
         */
        
        if(is_numeric($routeId)) {
          
          $routeURI = "route/$routeId/?format=json";
          
          $routeData = $this->get($routeURI);

          if($routeData == false) {
            
            $this->error("workouts() - can not fetch route data: ".$this->getError());
            
          } else {
            
            /* we've got a route! */
            
            $city    = $routeData->city;
            $country = $routeData->country;
            $state   = $routeData->state;
            
            /* 
             * NOTE: unfortunately, underarmour uses two digit codes for 
             * states and the REST API gives no method of decoding them (as 
             * far as I can find).
             * 
             */
            
            $address = "$city, $state $country"; 
            
            
            if(isset($routeData->starting_location->coordinates)) {
              $latitude  = $routeData->starting_location->coordinates[0];
              $longitude = $routeData->starting_location->coordinates[0];
            }
           
          }
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
      
      
      
      /* get the next page of results from their stream. */
      
      if(!isset($pageData->_links->next)||!is_array($pageData->_links->next)) {
        
        /* no more pages */
        
        break;
      }
      
      $next    = $pageData->_links->next[0];
      $uri     = $next->href;
      $matches = array();
      
      if(preg_match('/^(\/v\d+\.\d+\/)(.*)$/', $uri, $matches)) {
        $uri = $matches[2];
      }
          
      $page++;
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