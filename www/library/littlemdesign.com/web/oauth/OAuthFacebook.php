<?php 

/**
 * 
 * web \ oauth \ OAuthFacebook - this is the OAuth consumer for
 * Facebook, our adaptor for doing authentication via OAuth
 * with Facebook (remotely).  For more details see OAuthConsumer.php
 * 
 * Facebook it turns out, allows OAuth 2.x protocol which is 
 * vastly simplified. So we use that method instead of jumping
 * through hoops with signing OAuth requests etc.  But, we 
 * use the same basic flow and method calls...to be consistent.
 * 
 * You an learn more here:
 * 
 *   https://developers.facebook.com/docs/facebook-login/login-flow-for-web-no-jssdk/
 * 
 * To fetch the user profile, 
 * 
 *   https://developers.facebook.com/docs/reference/api/user/
 *   
 * basically we call 
 * 
 *   /me?access_token=<access token>
 * 
 * Most of the API calls can be made by just supplying the
 * access token.
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
 * class OAuthFacebook - Facebook authentication consumer.
 * Note that Facebook used OAuth 2.x protocal...which is 
 * vastly simplified.  So we don't need all the signing 
 * machinery of OAuthConsumer, but we follow the same
 * model overall to be consistent.
 * 
 */

class littlemdesign_web_oauth_OAuthFacebook
  extends littlemdesign_web_oauth_OAuthConsumer {

  /* where to go at Facebook to fetch a request token */
    
  private $requestURL = "";
  
  /* where to redirect browser (and user) to for authentication */
  
  private $authURL    = "";
  
  /* where to go to convert request to access token */
  
  private $accessURL  = "";
  
  /* the base URL for things like starting login process */
  
  private $baseURL    = "https://www.facebook.com/";
  
  /* the main REST API */
  
  private $graphURL   = "https://graph.facebook.com/";
  
  /**
   * 
   * Standard constructor, you have to inform the Facebook
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
    
    parent::__construct('Facebook', $key, $secret, $callback);
    
    /* 
     * when authenticating make sure the user gives us enough
     * permissions.
     * 
     */
    
    $scope = "email,user_birthday";

    $this->requestURL  = $this->baseURL.'dialog/oauth?';
    $this->requestURL .= "client_id=$key&redirect_uri=$callback";
    $this->requestURL .= "&scope=$scope";
    
    $this->graphURL    = 'https://graph.facebook.com/';
    $this->accessURL   = $this->graphURL.'oauth/access_token';
   
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
     * because Facebook is OAuth 2.x we simply redirect 
     * the user's browser to the request URL, there is
     * no server-to-server interaction at this point.
     * 
     */
    
    $url     = $this->requestURL;
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
   * finishLogn() - once user has authenticated at the remove provider
   * we need to finish the authentication sequence, and generate an
   * valid access token, and a basic user profile that we can use in
   * our application, to save the session details, log a user in, etc.
   * 
   * @param string $code - the verified token 
   * 
   * @param string $verifier - the verifier secret, note that for 
   * Facebook we are using OAuth 2.x...so no verifier.
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
    
    $this->info("user has authenticated ($code)...");
    
    /* 
     * we need to exchange 'code' for an actual access 
     * token, this is a server to server call...
     * 
     */
    
    /*
     * I double checked this; Facebook wants you to send your
     * app secrete in the clear as part of the URL.  Not sure
     * how exactly this is secure...but that's what their 
     * docs say to do...
     * 
     */
    
    $url  = $this->accessURL."?client_id=".$this->getKey()."&";
    $url .= "redirect_uri=".$this->getCallback()."&";
    $url .= "client_secret=".littlemdesign_web_http_URL::percentEncode($this->getConsumerSecret())."&";
    $url .= "code=".littlemdesign_web_http_URL::percentEncode($code);
    
    if(!is_object($url)) {
      $url   = littlemdesign_web_http_URL::create($url);
    }
    
    $textURL = $url->toString();
    
    /* 
     * use cURL to fetch the access token...
     * 
     */
    
    $curl = new littlemdesign_web_http_CURL($textURL);
    if(!$curl->isReady()) {
      $this->error("finishLogin() - no cURL: ".$curl->getError());
      return false;
    }
    
    /* GET our request... */
    
    $result  = $curl->write(
      "GET", 
      $textURL); 
     
    /* havest */
      
    if($result === false) {
      $this->error("finishLogin() - problem with cURL request: ".$curl->getError());
      return false;
    }
    
    /* 
     * facebook grants access tokens for a month...which is practically infiniity
     * for our puposes. so we don't try to capture the 'expires' parameter, which
     * gives the # of seconds from now that the token expires.
     * 
     */
    
    $pairs = explode('&', $result->data);
    $token = false;
    
    foreach($pairs as $item) {
      
      list($k,$v) = explode('=', $item);
      
      if($k == "access_token") {
        $token = $v;
      }
    }
    
    if($token === false) {
      $this->error("finishLogin() - no access token returned.");
      return false;  
    }
    $this->setToken($token);
    
    /* 
     * fetch the user profile...
     * 
     */
    
    $url  = $this->graphURL."/me?access_token=".littlemdesign_web_http_URL::percentEncode($this->getToken());
    
    if(!is_object($url)) {
      $url   = littlemdesign_web_http_URL::create($url);
    }
    
    $textURL = $url->toString();
    
    /* 
     * use cURL to fetch the profile...
     * 
     */
    
    $curl = new littlemdesign_web_http_CURL($textURL);
    if(!$curl->isReady()) {
      $this->error("finishLogin() - no cURL: ".$curl->getError());
      return false;
    }
    
    /* GET our request... */
    
    $result  = $curl->write(
      "GET", 
      $textURL); 
     
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
     * build up the user object 
     * 
     */
    
    $user = new littlemdesign_web_oauth_OAuthUser;
    
    $user->id          = $data->id;
    $user->accessToken = $this->getToken();
    $user->provider    = 'facebook';
    
    if(isset($data->name)) {
      $user->displayName = $data->name;
    }
    if(isset($data->gender)) {
      $user->gender = $data->gender;
    }
    if(isset($data->first_name)) {
      $user->firstName = $data->first_name;
    }
    if(isset($data->last_name)) {
      $user->lastName = $data->last_name;
    }
    if(isset($data->birthday)) {
      $user->birth = $data->birthday;  /*  01/31/1971  */
    }
    if(isset($data->link)) {
      $user->profileURL = $data->link;
    }
    if(isset($data->email)) {
      $user->email = $data->email;
    }
    
    /* picture URL is generated on the fly */
    
    $user->photoURL = $this->graphURL.$user->id."/picture?width=150&height=150";
    
    /* all done */
    
    return $user;
  }
}
  
?>