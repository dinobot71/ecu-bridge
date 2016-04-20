<?php 

/**
 * 
 * web \ oauth \ OAuthWithings - this is the OAuth consumer for
 * Withings, our adaptor for doing authentication via OAuth
 * with Withings (remotely).  For more details see OAuthConsumer.php
 * 
 * Withings only supports OAuth 1.x so we will reuse the signing
 * machinery of OAuthConsumer.
 * 
 * You can learn more about Withings  OAuth support on YDN:
 * 
 *   http://oauth.withings.com/api
 *    
 * The developer/app console is over here:
 * 
 *   http://oauth.withings.com/partner/dashboard
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
 * class OAuthWithings - Withings authentication consumer.
 *
 */

class littlemdesign_web_oauth_OAuthWithings
  extends littlemdesign_web_oauth_OAuthConsumer {

  /* where to go at withings to fetch a request token */
    
  private $requestURL  = "https://oauth.withings.com/account/request_token";
  
  /* where to redirect browser (and user) to for authentication */
  
  private $authURL     = "https://oauth.withings.com/account/authorize";
  
  /* general REST API access */
  
  private $apiURL      = "https://wbsapi.withings.net/";
  
  /* where to go to convert an authenticated code to an access token */
  
  private $accessURL   = "https://oauth.withings.com/account/access_token";
  
  /* the base URL for things like starting login process */
  
  private $baseURL     = "";

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
    
    parent::__construct('Withings', $key, $secret, $callback);
    
    
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
  
  /**
   * 
   * get() - once we are authenticated (have an access token
   * and secret, we can make signed requests, from the full
   * URL constructed from teh twitter base URL + the given 
   * URI. 
   * 
   * @param string $uri the URI (rest resource) we want to 
   * fetch on.
   * 
   * NOTE: to sign in with a saved access token/secret, call
   * setToken() and setSecret() with the access token and 
   * access secret.  This allows you to use this method 
   * without having to do the full round trip of authentication
   * via user re-direction in the browser.
   *  
   */
  
  public function get($uri, $realm="", $verb="GET") {
    
    if(empty($uri)) {
      return false;
    }
    
    $uri = trim($uri, '/');
    $url = $this->apiURL.$uri;
    
    /* 
     * do the actual get by way of a signed request to the 
     * provider.  Unfortunately, Withings doesn't pay 
     * attention to headers, and you have to pass the 
     * OAuth parameters directly in the URL.
     * 
     */
    
    $request = littlemdesign_web_http_URL::create($url);

    /* add in basic OAuth parameters */
    
    $oauthParams = array(
      "oauth_version"          => $this->getVersion(),
      "oauth_nonce"            => self::nonce(),
      "oauth_timestamp"        => self::timestamp(),
      "oauth_consumer_key"     => $this->getKey(),
      "oauth_signature_method" => "HMAC-SHA1",
      "oauth_token"            => $this->getToken()
    );
    $request->mergeQuery($oauthParams);
    
    /* 
     * generate a signed request, we sign with the access token
     * and the access secret.
     * 
     */ 
    
    $getObj = $this->generateSignedRequest($verb, $request, $this->getSecret(), $realm);
    if($getObj === false) {
      $this->error("get() - can not generate request token object.");
      return false;
    }
    
    /* now add the signature we created */
     
    $textURL = $request->toString(littlemdesign_web_http_URL::RFC_3986);
    
    $this->info(". fetching via: $textURL...");
    
    /* get ready to do a cURL request */
    
    $curl = new littlemdesign_web_http_CURL($textURL);
    if(!$curl->isReady()) {
      $this->error("get() - no cURL: ".$curl->getError());
      return false;
    }
    
    /* format the headers */
    
    $auth    = trim(substr($getObj->authorization, 14));
    $headers = array( 
      "Content-Type"  => "text/plain",
      "Authorization" => $auth,
      "Expect"        => ''
    );
       
    /* GET our request... */
    
    $result  = $curl->write(
      $verb, 
      $textURL, 
      "", 
      $headers['Content-Type'], 
      $headers,
      littlemdesign_web_http_URL::RFC_3986); 
     
    /* havest */
      
    if($result === false) {
      $this->error("get() - problem with cURL request: ".$curl->getError());
      return false;
    }
    
    /* 
     * ok, we've got the low level resutls, pull out 
     * the actual response from the provider, we expect 
     * to get JSON or XML usually.
     * 
     */
    
    $data = $result->data;
    
    /* pass it back */
    
    return $data;
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
    
     $status = $this->requestAuthentication($this->requestURL, $this->authURL);
    
    if(!$status) {
      $this->error("problem launching Yahoo authenication: ".$this->getError());
    }
    
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
   * Google there is no verifier.
   * 
   * @return object returns a standardized user profile
   * object (web\oauth\OAuthUser) that gives us details about the user who 
   * authenticated.  If there is a problem, then exactly
   * false is returned.
   *  
   */
   
  public function finishLogin($token, $verifier) {
    
    if(!$this->isReady()) {
      $this->error("finishLogin() - not ready.");
      return false;
    }
    
    $this->info("user has authenticated ($token, $verifier)...");
    
    /* convert to access token */
    
    $status = $this->receiveAuthentication($this->accessURL, array(
      "oauth_token"    => $token,
      "oauth_verifier" => $verifier
    ));
    
    if($status === false) {
      $this->error("problem getting access token.");
      return false;
    }
    
    if(isset($status['denied'])) {
      $this->setDenied(true);
      $this->error("withings login denied or canceled by user.");
      return false;
    }
    
    /*
     * at this point we have a valid access token, and we can 
     * create a proper source for Withings for this user.  That
     * data source can then be used at will in the future to 
     * sync data.
     * 
     */  
  
    $this->info("creating Withings data source...")/
    
    $key      = $this->getKey();
    $secret   = $this->getSecret();
    $callback = $this->getCallback();
    $token    = $this->getToken();

    $this->info(" . id       : {$this->userId}");
    $this->info(" . key      : $key");
    $this->info(" . secret   : $secret");
    $this->info(" . callback : $callback");
    $this->info(" . token    : $token");
    
    $user = new littlemdesign_web_oauth_OAuthUser;
    
    $user->id           = $this->userId;
    $user->accessToken  = $token;
    $user->accessSecret = $secret;
    $user->provider     = 'withings';
    
    /* 
     * fetch the user profile info... 
     * 
     * This isn't a published API :( 
     * 
     */
    
    $uri = "/user?action=getbyuserid&userid={$this->userId}";  
    
    /* fetch from provider */
    
    $result = $this->get($uri);
    
    if($result === false) {
      $this->error("Could not GET ($uri): ".$this->getError());
      return false;
    }

    /*
     * should get something like:
     * 
     *   {
     *     "status":0,
     *     "body":{
     *       "users":[
     *         {"id":4142537,
     *          "firstname":"Michael",
     *          "lastname":"garvin",
     *          "shortname":"MIC",
     *          "gender":0,
     *          "fatmethod":4,
     *          "birthdate":34197359,
     *          "ispublic":0
     *         }
     *       ]
     *     }
     *   }
     *
     */
    
    $data = json_decode($result);
    
    if(!isset($data->status)) {
      $this->error("Withings result has no status field.");
      return false;
    }
    if($data->status != 0) {
      $this->error("Withings returned error status: {$data->status}");
      return false;
    }
    
    if(!isset($data->body->users)) {
      $this->error("Withings returned no user list.");
      return false;
    }
    
    $wUser = $data->body->users[0];
    
    $user->id        = $wUser->id;
    
    if(is_numeric($wUser->birthdate)) {
      $user->birth   = date('Y-m-d H:i:s', $wUser->birthdate);
    } else {
      $user->birth   = $wUser->birthdate;
    }
    
    if($wUser->gender == 0) {
      $user->gender  = "Male";
    } else {
      $user->gender  = "Female";
    }
    
    $user->firstName   = $wUser->firstname;
    $user->lastName    = $wUser->lastname;
    $user->displayName = $wUser->firstname." ".$wUser->lastname;
    
    $user->profileURL  = "https://healthmate.withings.com/settings";
    
    return $user;
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
    
    if(empty($this->getSecret())) {
      $this->error("apiReady() - no access token secret.");
      return false;
    }
    
    /* ready! */
    
    return true;
  }
  
  /**
   * 
   * errorString() decode withings error codes and return the error 
   * string.
   * 
   * @param integer $code
   * 
   */
  
  public function errorString($code) {
    
    if(!is_numeric($code)) {
      return false;
    }

    $code = (int)$code;
    
    switch($code) {
      case 0:   
        return "Operation was successful";
      case 247:   
        return "The userid provided is absent, or incorrect";
      case 250:   
        return "The provided userid and/or Oauth credentials do not match";
      case 286:   
        return "No such subscription was found";
      case 293:   
        return "The callback URL is either absent or incorrect";
      case 294:   
        return "No such subscription could be deleted";
      case 304:   
        return "The comment is either absent or incorrect";
      case 305:   
        return "Too many notifications are already set";
      case 342:   
        return "The signature (using Oauth) is invalid";
      case 343:   
        return "Wrong Notification Callback Url don't exist";
      case 601:   
        return "Too Many Request";
      case 2554:   
        return "Wrong action or wrong webservice";
      case 2555:   
        return "An unknown error occurred";
      case 2556:   
        return "Service is not defined";
    }
    
    return false;
  }
  
  /**
   * 
   * bodyMeasures() - fetch the body measures from Withings
   * and place into an array of simplified objects that can 
   * be easily processed.
   * 
   * @param mixed $startDate - (optional) the unix time stsmp 
   * for whento start gathering measures. If not numeric it 
   * will be converted via strtotime().
   * 
   * @return mixed return exactly false on error, 
   * otherwise an array of measures.
   * 
   */
  
  public function bodyMeasures($startDate="") {
    
    if(!$this->apiReady()) {
      $this->error("bodyMeasures() - API not ready: ".$this->getError());
      return false;
    }
    
    $results = array();
    $start   = "";
    
    if(!empty($startDate)) {
      
      if(!is_numeric($startDate)) {
        $startDate = strtotime($startDate);
      }
      
      $start = "&startdate=$startDate";
    }
    
    $url     = "/measure?action=getmeas&userid={$this->userId}$start";
    
    $data = $this->get($url);
    
    if($data === false) {
      $this->error("bodyMeasures() - problem requesting data: ".$this->getError());
      return false;
    }
    
    $data = json_decode($data);
    
    if(!isset($data->status)) {
      $this->error("bodyMeasures() - result is missing status field.");
      return false;
    }
    
    if($data->status != 0) {
      $this->error("bodyMeasures() - withings error ({$data->status}) - ".$this->errorString($data->status));
      return false;
    }
    
    $samples = array();
    $asof    = date("Y-m-d H:i:s", $data->body->updatetime);
    $items   = $data->body->measuregrps;
    
    /* 
     * walk through the groups of measures, usually weight
     * and heart rate etc. are all taken at the same time.
     * 
     */
    
    foreach($items as $idx => $item) {
      
      $stamp = date('Y-m-d H:i:s', $item->date);
      $mode  = "manual";
      
      if(($item->attrib == 0)||($item->attrib == 1)) {
        $mode = "device";
      }
      
      /* pick off the actual measures */

      foreach($item->measures as $jdx => $datum) {
        
        $value = $datum->value;
        $unit  = $datum->unit;
        $type  = (int)$datum->type;
         
        /* $unit gives us decimal place adjustment */
        
        if($unit < 0) {
          $value /= (pow(10, abs($unit)));
        } else if($unit > 0) {
          $value *= (pow(10, $unit));
        }
        
        /* type can be converted into a measureent type */
        
        switch($type) {
          
          case 1: 
            $type = "weight";
            break;

          case 4: 
            $type = "height";
            break;

          case 5: 
            $type = "fat_free_mass";

          case 6: 
            $type = "fat_ratio";
            break;

          case 8: 
            $type = "fat_mass_weight";
            break;

          case 9: 
            $type = "diastolic_blood_pressure";
            break;

          case 10: 
            $type = "systolic_blood_pressure";
            break;

          case 11: 
            $type = "heart_pulse";
            break;

          case 54: 
            $type = "sp02";
            break;
        
        }
        
        /* ok we have everything we need to make a sample */
        
        $sample = (object)array(
          'stamp' => $stamp,
          'mode'  => $mode,
          'type'  => $type,
          'value' => $value
        );
        
        $samples[] = $sample;
      }
      
    }
    
    $results = (object)array(
      "asof" => $asof,
      "data" => $samples
    );
    
    /* pass back */
    
    return $results;
  }
}
  
?>