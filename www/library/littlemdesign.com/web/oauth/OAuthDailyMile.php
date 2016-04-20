<?php 

/**
 * 
 * web \ oauth \ OAuthDailyMile - this is the OAuth consumer for
 * DailyMile, our adaptor for doing authentication via OAuth
 * with DailyMile (remotely).  For more details see OAuthConsumer.php
 * 
 * DailyMile uses OAuth 2.x so its much simpler, but we can still
 * use the same framework of begin/finish login.
 * 
 * You an learn more here:
 * 
 *  http://www.dailymile.com/api
 * 
 * Authentication:
 * 
 *   http://www.dailymile.com/api/documentation/oauth
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
 * class OAuthDailyMile - DailyMile authentication consumer.
 * Note that OAuthDailyMile uses OAuth 2.x protocal...which is 
 * vastly simplified.  So we don't need all the signing 
 * machinery of OAuthConsumer, but we follow the same
 * model overall process to be consistent.
 * 
 */

class littlemdesign_web_oauth_OAuthDailyMile
  extends littlemdesign_web_oauth_OAuthConsumer {

  /* where to go at DailyMile to fetch a request token */
    
  private $requestURL  = "";
  
  /* where to redirect browser (and user) to for authentication */
  
  private $authURL     = "";
  
  /* general REST API access */
  
  private $apiURL      = "https://api.dailymile.com/";
  
  /* the base URL for things like starting login process */
  
  private $baseURL     = "https://api.dailymile.com/";
  
  /* the user account in context */
  
  private $userId      = "";
  
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
    
    parent::__construct('DailyMile', $key, $secret, $callback);
    
    $this->authURL   = $this->baseURL."oauth/authorize?response_type=code&client_id=$key&redirect_uri=$callback";

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
     * because DailyMile is OAuth 2.x we simply redirect 
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
     * proper.  Build up a DailyMile style access token URL, 
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
    
    /* daily mile can return 200 but give us an error object */
    
    if(isset($data->error)) {
      $this->error("finishLogin() - API error: ".$data->error);
      return false;
    }
    
    if(!$data) {
      $this->error("finishLogin() - can't decode JSON data: ".$result->data);
      return false;
    }

    /*
     * Similar to Faacebook, DailyMile will give us an "access_token" 
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
    
    $user = null;
    $uri  = "people/me.json";
    $data = $this->get($uri);
    
    if($data == false) {
      $this->error("finishLogin() - can not fetch user profile: ".$this->getError());
      return false; 
    }
    
    $key      = $this->getKey();
    $secret   = $this->getConsumerSecret();
    $callback = $this->getCallback();
    $token    = $this->getToken();

    $this->info(" . id       : {$data->username}");
    $this->info(" . key      : $key");
    $this->info(" . secret   : $secret");
    $this->info(" . callback : $callback");
    $this->info(" . token    : $token");
    
    $user = new littlemdesign_web_oauth_OAuthUser;
    
    $user->id           = $data->username;
    $user->accessToken  = $token;
    $user->accessSecret = $secret;
    $user->provider     = 'dailymile';
    $user->displayName  = $data->display_name;
    $user->address      = $data->location;
    $user->photoURL     = $data->photo_url;

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
    
    $url = $this->baseURL.$uri;
    
    if(!is_object($url)) {
      $url   = littlemdesign_web_http_URL::create($url);
    }
    
    /* add the access token */
    
    $auth = array(
      "oauth_token" => $this->getToken()
    );
    
    $url->mergeQuery($auth);
    
    $textURL = $url->toString();
    
    /* build up a cURL request... */
    
    $curl = new littlemdesign_web_http_CURL($textURL);
    if(!$curl->isReady()) {
      $this->error("get() - no cURL: ".$curl->getError());
      return false;
    }
    
    /* GET our request... */
    
    $result  = $curl->write($verb, $textURL); 
     
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
   * making signed requests.
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
    
    if(!empty($startDate)) {
      if(!is_numeric($startDate)) {
        $start = strtotime($startDate);
      }
    }
    
    if(!empty($start)) {
      $startClause = "?since=$start";
    }
       
    /*
     * workouts are entries in their stream of workouts, 
     * notes, photos etc.  So we have to walk through 
     * their stream page by page and pull out the 
     * entries that are workouts, and then expand them to 
     * get the details.
     * 
     * Results are paged 20 at a time, so we have to do a 
     * loop until the end.
     * 
     */
    
    $pageURI = "people/{$this->getUserId()}/entries.json$startClause";
    
    while(true) {
      
      /* get the next page of results from their stream. */
      
      $uri = $pageURI;
      $page++;
      
      $pageClause = "?page=$page";
      if(!empty($startClause)) {
        $pageClause = "&page=$page";
      }
      $uri = $pageURI.$pageClause;
      
      $pageData = $this->get($uri);
      
      if($pageData == false) {
        $this->error("workouts() - problem fetching results: ".$this->getError());
        return false;
      }
      
      if(!isset($pageData->entries)||empty($pageData->entries)) {
        
        /* we ran out of results */
        
        break;
      }
      
      foreach($pageData->entries as $idx => $entry) {
        
        /* convert entry to a workout if appropriate */
     
        if(!isset($entry->workout)) {
          continue;
        }
                        
        $id        = $entry->id;
        $routeId   = "";
        $stamp     = strtotime($entry->at);
        $notes     = "";
        $address   = "";
        $latitude  = 0.0;
        $longitude = 0.0;
        $kind      = "";
        $felt      = "";
        $distance  = 0.0;
        $duration  = 0.0;
        $calories  = 0.0;
        $title     = "";
        
        if(empty($asof)) {
          $asof = $stamp;
        }
        
        if(isset($entry->message)) {
          $notes = $entry->message;
        }
        
        if(isset($entry->location->name)) {
          $address = $entry->location->name;
        }
        
        if(isset($entry->geo->type)) {
          if($entry->geo->type == "Point") {
            $latitude  = $entry->geo->coordinates[0];
            $longitude = $entry->geo->coordinates[0]; 
          }
        }
        
        /* one of "running", "cycling", "swimming", "walking", or "fitness" */
        
        $kind = strtolower($entry->workout->activity_type);
        
        if(($kind == "inline")||($kind == "inline skating")) {
          $kind = "inlineskating";
        }
        
        /* one of "great", "good", "alright", "blah", "tired" or "injured" */
        
        if(isset($entry->workout->felt)) {
          $felt = strtolower($entry->workout->felt);
        }
        
        if(isset($entry->workout->title)) {
          $title = strtolower($entry->workout->title);
        }
        
        if(isset($entry->workout->completed_at)) {
          $stamp = strtotime($entry->workout->completed_at);
        }
        
        if(isset($entry->workout->distance->units)) {
          
          $units    = strtolower($entry->workout->distance->units);
          $distance = $entry->workout->distance->value;
        
          if(($units != "kilometers") && ($units != "km")) {
            
            if($units == "meters") {
              $distance = $entry->workout->distance->value / 1000.0;
            } else if($units == "yards") {
              $distance = $entry->workout->distance->value / 1093.61;
            } else if($units == "miles") {
              $distance = $entry->workout->distance->value / 0.621371;
            }
          }
          
        } else if(isset($entry->workout->distance->value)) {
          $distance = $entry->workout->distance->value;
        } 
       
        if(isset($entry->workout->duration)) {
          $duration = $entry->workout->duration;
        }
        
        if(isset($entry->workout->calories)) {
          $calories = $entry->workout->calories;
        }
        
        if(isset($entry->workout->route_id)) {
          $routeId = $entry->workout->route_id;
        }

        /* ok make our workout object */

        $workout = (object)array(
          'id'        => $id,
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
        
        $results[] = $workout;
        
      } /* end of parsing a workout */
      
    } /* end of parsing a page of the stream */
    
    /* pass back */
    
    $obj = (object)array(
      "asof" => date('Y-m-d H:i:s', $asof),
      "data" => $results
    );
    return $obj;
  }
}
  
?>