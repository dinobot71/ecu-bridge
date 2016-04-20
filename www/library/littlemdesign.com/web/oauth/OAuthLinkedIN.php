<?php 

/**
 * 
 * web \ oauth \ OAuthLinkedIN - this is the OAuth consumer for
 * LinkedIN, our adaptor for doing authentication via OAuth
 * with LinkedIN (remotely).  For more details see OAuthConsumer.php
 * 
 * LinkedIN it turns out, allows OAuth 2.x protocol which is 
 * vastly simplified. So we use that method instead of jumping
 * through hoops with signing OAuth requests etc.  But, we 
 * use the same basic flow and method calls...to be consistent.
 * 
 * You an learn more here:
 * 
 *   https://developer.linkedin.com/
 * 
 * Authentication:
 * 
 *   https://developer.linkedin.com/documents/authentication
 *   
 * More API docs, getting profile etc.
 * 
 *   https://developer.linkedin.com/documents/profile-api
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
 * class OAuthLinkedIN - LinkedIN authentication consumer.
 * Note that LinkedIN uses OAuth 2.x protocal...which is 
 * vastly simplified.  So we don't need all the signing 
 * machinery of OAuthConsumer, but we follow the same
 * model overall to be consistent.
 * 
 */

class littlemdesign_web_oauth_OAuthLinkedIN
  extends littlemdesign_web_oauth_OAuthConsumer {

  /* where to go at LinkedIN to fetch a request token */
    
  private $requestURL  = "";
  
  /* where to redirect browser (and user) to for authentication */
  
  private $authURL     = "";
  
  /* general REST API access */
  
  private $apiURL   = "http://api.linkedin.com/";
  
  /* the base URL for things like starting login process */
  
  private $baseURL     = "https://www.linkedin.com/";
  
  
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
    
    parent::__construct('LinkedIN', $key, $secret, $callback);
    
    /* 
     * when authenticating make sure the user gives us enough
     * permissions.
     * 
     */
    
    $scope = "r_contactinfo,r_fullprofile,r_emailaddress";
    $state = $this->nonce(256,'md5');
    
    $this->authURL   = $this->baseURL."uas/oauth2/authorization?response_type=code";
    $this->authURL  .= "&client_id=$key&scope=$scope";
    $this->authURL  .= "&state=$state&redirect_uri=$callback";
    
    $this->accessURL = $this->baseURL."uas/oauth2/accessToken";
    
   
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
     * we should confirm $verifier is $state (from beginLogin() ),
     * but because it was all done in the clear as part fo the URL,
     * its not trustworthy anyways, so we don't bother checking it,
     * its going to make it any more secure.  If they wanted secure,
     * they would have used OAuth 1.x, but LinkedIN didn't.
     * 
     */    
    
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
     * Similar to Faacebook, LinkedIN will give us an "expires_in" token
     * which is long lived (on the order of a month), so for us its
     * infinite and we don't bother tracking it.  Our sessions will not
     * be anywhere near that long.
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
    
    $scope = "id,first-name,last-name,public-profile-url,picture-url,email-address,date-of-birth,phone-numbers,summary";

    $url   = $this->baseURL."v1/people/~:($scope)";
    $url  .= "?format=json&oauth2_access_token=".littlemdesign_web_http_URL::percentEncode($this->getToken());
    
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
    $user->provider    = 'linkedin';
   
    if(isset($data->firstName)) {
      $user->firstName = $data->firstName;
    }
    if(isset($data->lastName)) {
      $user->lastName = $data->lastName;
    }
    if(isset($data->birthday)) {
      $user->birth = $data->birthday;  /*  01/31/1971  */
    }
    if(isset($data->emailAddress)) {
      $user->email = $data->emailAddress;
    }
    if(isset($data->publicProfileUrl)) {
      $user->profileURL = $data->publicProfileUrl;
    }
    if(isset($data->pictureUrl)) {
      $user->photoURL = $data->pictureUrl;
    }

    /* all done */
    
    return $user;
  }
}
  
?>