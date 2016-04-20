<?php 

/**
 * 
 * web \ oauth \ OAuthYahoo - this is the OAuth consumer for
 * Yahoo, our adaptor for doing authentication via OAuth
 * with Yahoo (remotely).  For more details see OAuthConsumer.php
 * 
 * Yahoo only supports OAuth 1.x so we will reuse the signing
 * machinery of OAuthConsumer.
 * 
 * You can learn more about Yahoo's  OAuth support on YDN:
 * 
 *   http://developer.yahoo.com/oauth/
 *   
 * The quick start guide is over here:
 * 
 *   http://developer.yahoo.com/oauth/guide/
 *   
 * The developer/app console is over here:
 * 
 *   https://developer.apps.yahoo.com
 *   
 * NOTE: yahoo doesn't fully comply with OAuth; they don't allow
 * you to test/develop on "localhost", they are the only provider
 * to disallow this.  Its unlikely that it avoides any actual 
 * problems and it just creates hassle for developers.  Once again
 * Yahoo, falls short of the mark.
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
 * class OAuthYahoo - Yahoo authentication consumer.
 *
 */

class littlemdesign_web_oauth_OAuthYahoo
  extends littlemdesign_web_oauth_OAuthConsumer {

  /* where to go at Google+ to fetch a request token */
    
  private $requestURL  = "";
  
  /* where to redirect browser (and user) to for authentication */
  
  private $authURL     = "";
  
  /* general REST API access */
  
  private $apiURL      = "http://social.yahooapis.com/v1/";
  
  /* where to go to convert an authenticated code to an access token */
  
  private $accessURL   = "";
  
  /* the base URL for things like starting login process */
  
  private $baseURL     = "";
    
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
      
  public function __construct($key, $secret, $callback) {
    
    parent::__construct('Yahoo', $key, $secret, $callback);
    
    $this->requestURL = 'https://api.login.yahoo.com/oauth/v2/get_request_token';
    $this->authURL    = 'https://api.login.yahoo.com/oauth/v2/request_auth';
    $this->accessURL  = 'https://api.login.yahoo.com/oauth/v2/get_token';
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
   */
  
  public function get($uri) {
    
    if(empty($uri)) {
      return false;
    }
    
    $uri = trim($uri, '/');
    $url = $this->apiURL.$uri;
    
    /* 
     * do the actual get by way of a signed request to the 
     * provider.
     * 
     */
    
    $result = parent::get($url);
    
    if($result === false) {
      $this->error("Could not GET ($url): ".$this->getError());
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
      $this->error("Yahoo login denied or canceled by user.");
      return false;
    }
    
    $user = new littlemdesign_web_oauth_OAuthUser;
    
    /*
     * before we can fetch the current user's Yahoo profile,
     * we have to know who the current user is...
     * 
     */
    
    $result = $this->get("me/guid?format=json");
    
    if($result === false) {
      $this->error("could not get current user id: ".$this->getError());
      return false;
    }
    
    $data = json_decode($result);
    
    if(!isset($data->guid->value)) {
      $this->error("could not get current user id, got: ".$result);
      return false;
    }
    
    $user->id = $data->guid->value;
    
    /* now get the profile for this user */
    
    $result = $this->get("user/".$user->id."/profile?format=json");
    
    if($result === false) {
      $this->error("could not fetch profile for ".$user->Id.": ".$this->getError());
      return false;
    }
    
    $data = json_decode($result);
    
    if(isset($data->profile->birthdate)) {
      $user->birth = $data->profile->birthdate; // month/day
    }
    
    if(isset($data->profile->emails)) {
      
      foreach($data->profile->emails as $item) {
        
        if(!$item->primary) {
          continue;
        }
        
        $user->email = $item->handle;
        break;
      }
    }
    
    if(isset($data->profile->image->imageUrl)) {
      $user->photoURL = $data->profile->image->imageUrl;
    }
    
    if(isset($data->profile->nickname)) {
      $user->displayName = $data->profile->nickname;
    }
    
    if(isset($data->profile->profileUrl)) {
      $user->profileURL = $data->profile->profileUrl;
    }
    
    $user->accessToken = $this->getToken();
    $user->provider    = 'yahoo';
    
    return $user;
  }
}
  
?>