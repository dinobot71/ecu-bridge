<?php 

/**
 * 
 * web \ oauth \ OAuthGoogle - this is the OAuth consumer for
 * Google+/Google, our adaptor for doing authentication via OAuth
 * with Google+ (remotely).  For more details see OAuthConsumer.php
 * 
 * Google it turns out, allows OAuth 2.x protocol which is 
 * vastly simplified. So we use that method instead of jumping
 * through hoops with signing OAuth requests etc.  But, we 
 * use the same basic flow and method calls...to be consistent.
 * 
 * You can learn more here:
 * 
 *   https://developers.google.com/+/
 * 
 * Authentication:
 * 
 *   https://developers.google.com/+/api/oauth
 *   https://developers.google.com/accounts/docs/OAuth2WebServer
 *   https://developers.google.com/+/web/signin/server-side-flow
 *   
 * The second link provides the actual details.
 * 
 * To manage your application settings (app key/secret) use
 * the App Console:
 * 
 *   https://code.google.com/apis/console
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
 * class OAuthGoogle - Google+/Google authentication consumer.
 * Note that Google uses OAuth 2.x protocal...which is 
 * vastly simplified.  So we don't need all the signing 
 * machinery of OAuthConsumer, but we follow the same
 * model overall to be consistent.
 * 
 */

class littlemdesign_web_oauth_OAuthGoogle
  extends littlemdesign_web_oauth_OAuthConsumer {

  /* where to go at Google+ to fetch a request token */
    
  private $requestURL  = "";
  
  /* where to redirect browser (and user) to for authentication */
  
  private $authURL     = "https://accounts.google.com/o/oauth2/auth";
  
  /* general REST API access */
  
  private $apiURL      = "https://www.googleapis.com/";
  
  /* where to go to convert an authenticated code to an access token */
  
  private $accessURL   = "https://accounts.google.com/o/oauth2/token";
  
  /* the base URL for things like starting login process */
  
  private $baseURL     = "https://www.googleapis.com/";
    
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
    
    parent::__construct('Google+', $key, $secret, $callback);
    
    /* 
     * when authenticating make sure the user gives us enough
     * permissions.
     * 
     */
    
    $scope  = "{$this->apiURL}auth/userinfo.profile ";
    $scope .= "{$this->apiURL}auth/userinfo.email";

    $state = $this->nonce(256,'md5');
    
    $this->authURL  .= "?response_type=code";
    $this->authURL  .= "&client_id=".littlemdesign_web_http_URL::percentEncode($key);
    $this->authURL  .= "&scope=".littlemdesign_web_http_URL::percentEncode($scope);
    $this->authURL  .= "&state=".littlemdesign_web_http_URL::percentEncode($state);
    $this->authURL  .= "&redirect_uri=".littlemdesign_web_http_URL::percentEncode($callback);
    
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
     * because LinkedIN is OAuth 2.x we simply redirect 
     * the user's browser to the request URL, there is
     * no server-to-server interaction at this point.
     * 
     */
    
    $url     = $this->authURL;
    
    if(!preg_match('/^https:/', $url)) {
      $this->warning("beginLogin() - non secured authenitcation endpoint in use.");
    }
    
    /* redirect */
    
    $this->info("beginLogin() - authenticating user via: $url");
    
    header("Location: $url");
    
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
    
    $url  = $this->accessURL."?grant_type=authorization_code";
    $url .= "&code=$code&redirect_uri=".$this->getCallback();
    $url .= "&client_id=".$this->getKey();
    $url .= "&client_secret=".$this->getConsumerSecret();

    if(!is_object($url)) {
      $url   = littlemdesign_web_http_URL::create($url);
    }
    
    $textURL = $url->toString();
    $noQuery = array_shift(explode('?',$url->toString()));
    $query   = $url->getQuery();
    
    $form    = "";
    foreach($query as $k => $v) {
      $form .= littlemdesign_web_http_URL::percentEncode($k);
      $form .= "=";
      $form .= littlemdesign_web_http_URL::percentEncode($v);
      $form .= "&";
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
     * Similar to Faacebook, Google will give us an "expires_in" token
     * which is which lasts for an hour.  We don't plan to allow users to
     * remain logged in without activity for longer than that, so we don't 
     * bother tracking it.
     * 
     */
    
    if(!isset($data->access_token)) {
      $this->error("finishLogin() - no access token returned.");
      return false;  
    }
 
    $this->setToken($data->access_token);
    
    /* 
     * fetch the user profile...
     * 
     */
   
    $url   = $this->apiURL."oauth2/v2/userinfo";
    $url  .= "?access_token=".littlemdesign_web_http_URL::percentEncode($this->getToken());
    
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
    $user->provider    = 'Google+';
   
    if(isset($data->given_name)) {
      $user->firstName = $data->given_name;
    }
    if(isset($data->family_name)) {
      $user->lastName = $data->family_name;
    }
    if(isset($data->birthday)) {
      $user->birth = $data->birthday;  /*  01/31/1971  */
    }
    if(isset($data->email)) {
      $user->email = $data->email;
    }
    if(isset($data->link)) {
      $user->profileURL = $data->link;
    }
    if(isset($data->picture)) {
      $user->photoURL = $data->picture;
    }
    if(isset($data->gender)) {
      $user->gender = $data->gender;
    }
   
    /* all done */
    
    return $user;
  }
}
  
?>