<?php 

/**
 * 
 * web \ oauth \ OAuthTwitter - this is the OAuth consumer for
 * Twitter, our adaptor for doing authentication via OAuth
 * with Twitter (remotely).  For more details see OAuthConsumer.php
 * 
 * Because Twitter is a very strict OAuth 1.x implementation,
 * it is pretty much just using the base class (OAuthConsumer) 
 * methods...as that class implements OAuth 1.0a.  In general
 * any GET/POST REST call we make to Twitter will be a "signed"
 * request...via OAuth 1.0a signing specification.
 * 
 * Beyond that you can read more on the Twitter REST API here:
 * 
 *   https://dev.twitter.com/docs/api/1.1
 *   
 * Really good examples/tutorial here:
 * 
 *   https://dev.twitter.com/docs/auth/authorizing-request
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
 * class OAuthTwitter - Twitter authentication consumer.
 * 
 */

class littlemdesign_web_oauth_OAuthTwitter
  extends littlemdesign_web_oauth_OAuthConsumer {

  /* where to go at Twitter to fetch a request token */
    
  private $requestURL = "";
  
  /* where to redirect browser (and user) to for authentication */
  
  private $authURL    = "";
  
  /* where to go to convert request to access token */
  
  private $accessURL  = "";
  
  private $baseURL    = "https://api.twitter.com/";
  
  /**
   * 
   * Standard constructor, you have to inform the Twitter
   * consumer of the consumer key, consumer secret and
   * the callback to use when returning from authentication
   * at Twitter.
   * 
   * @param string $key
   * @param string $secret
   * @param string $callback
   * 
   */
      
  public function __construct($key, $secret, $callback) {
    
    parent::__construct('Twitter', $key, $secret, $callback);
    
    $this->requestURL = $this->baseURL.'oauth/request_token';
    $this->authURL    = $this->baseURL.'oauth/authenticate';
    $this->accessURL  = $this->baseURL.'oauth/access_token';
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
    $url = $this->baseURL.$uri;
    
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
      $this->error("problem launching Twitter authenication: ".$this->getError());
    }
    
    return false;
  }
  
  /**
   * 
   * finishLogn() - once user has authenticated at the remove provider
   * we need to finish the authentication sequence, and generate an
   * valid access token, and a basic user profile that we can use in
   * our application, to save the session details, log a user in, etc.
   * 
   * @param string $token - the verified token 
   * @param string $verifier - the verifier secret
   * 
   * @return object returns a standardized user profile
   * object (web\oauth\OAuthUser) that gives us details about the user who 
   * authenticated.  If there is a problem, then exactly
   * false is returned.
   *  
   */
   
  public function finishLogin($token, $verifier) {
    
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
      $this->error("Twitter login denied or canceled by user.");
      return false;
    }
    
    /* 
     * build user profile object, for Twitter we should have
     * at least gotten the user_id and screen_name
     *  
     */
    
    $user = new littlemdesign_web_oauth_OAuthUser;
    
    $user->id          = $status['user_id'];
    $user->displayName = $status['screen_name'];
    $user->accessToken = $this->getToken();
    $user->provider    = 'twitter';
    
    /*
     * to get more details than this we have to dial 
     * back to Twitter to get a full profile.
     */
    
    $result = $this->get("1.1/account/verify_credentials.json");
    
    if($result === false) {
      $this->error("could not verify credentials: ".$this->getError());
      return false;
    }
    
    $data = json_decode($result);
    
    /* 
     * Twitter is really terse, but fill in what can.  Also note that 
     * we don't try to figure out the expiry of the access token for
     * this consumer...Twitter (apparently) never expires tokens.
     * 
     */
    
    if(isset($data->profile_image_url) && !empty($data->profile_image_url)) {
      $user->photoURL = $data->profile_image_url;
    }
    
    if(isset($data->name) && !empty($data->name)) {
      
      /* 
       * we have no idea what they entered in the Twitter "name" 
       * field but we'll guess first and then last name.
       * 
       */
      
      $datum     = $data->name;
      $matches   = array();
      $firstName = "";
      $lastName  = "";
      
      if(preg_match('/^([^ ,:.]+)[ ,:.]+([^ ,:.]+)$/',$datum,$matches)) {
        $firstName = $matches[1];
        $lastName  = $matches[2];
      } else if(preg_match('/^([^ ,:.]+)[ ,:.]+[a-z][ ,:.]+([^ ,:.]+)$/',$datum,$matches)) {
        $firstName = $matches[1];
        $lastName  = $matches[2];
      } else if(preg_match('/^([^ ,:.]+)[ ,:.]+([^ ,:.]+[ ,:.]+[^ ,:.]+)$/',$datum,$matches)) {
        $firstName = $matches[1];
        $lastName  = $matches[2];
      } else if(preg_match('/^([^ ,:.]+)[ ,:.]+([^ ,:.]+[ ,:.]+[^ ,:.]+[ ,:.]+[^ ,:.]+)$/',$datum,$matches)) {
        $firstName = $matches[1];
        $lastName  = $matches[2];
      } 
      
      $user->firstName = $firstName;
      $user->lastName  = $lastName;
    }
    
    if(isset($data->location) && !empty($data->location)) {

      list($city,$state,$country) = explode(',',$data->location);
      
      $user->city    = trim($city);
      $user->state   = trim($state);
      $user->country = trim($country);
    }
    
    /* all done */
    
    return $user;
  }
  
  
    
}
  
?>