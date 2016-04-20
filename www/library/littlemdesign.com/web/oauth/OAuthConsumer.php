<?php 

/**
 * 
 * web \ oauth \ OAuthConsumer - this base class provides the
 * methods that are common to most OAuth based authentication 
 * consumers, that is when we want to be a client of Twitter, 
 * Facebbook, LinkedIn etc. for doing authentication.  
 * 
 * We are generally aiming for OAuth 1.0a support, but will try 
 * to handle whatever we have to in order to work with the major 
 * social networks.  Note that many providers (such as Facebook
 * or Linked) in now support OAuth 2.0 and *much* simpler 
 * protocol.  Where possible we will use OAuth 2.0 in those
 * providers.
 * 
 * The sole purpose of OAuth consumers is to authenticate a
 * user...without actually knowing their login details, and 
 * gaining the trusted identity (for a limited time) from
 * the remote authenication provider.  In our case we expect
 * most authentication providers to be social networks.
 * 
 * Note that authentication is different from authorization,
 * we could also use LDAP or our own database to authenticate
 * a user id and password...this is different from the roles
 * and permissions a user might have, like say being an 
 * administrator versus a normal user.
 * 
 * You can read more about OAuth here:
 * 
 *   http://hueniverse.com/oauth/guide/
 *   http://oauth.net/core/1.0a/
 *   
 * Details for the actual social networks:
 * 
 *   https://developers.facebook.com/docs/facebook-login/login-flow-for-web-no-jssdk/
 *   https://dev.twitter.com/docs/auth/implementing-sign-twitter
 *   http://developer.linkedin.com/documents/authentication
 *   
 * Test server/client over here:
 * 
 *   http://term.ie/oauth/example/
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

autorequire('littlemdesign_web_http_URL');
autorequire('littlemdesign_web_http_CURL');

/**
 * 
 * class OAuthConsumer - base class (common code) for 
 * all consumers that use a remote OAuth service to 
 * authenticate a user.
 *
 * NOTE: we currently only support HMAC-SHA1 signing.
 * 
 */

class littlemdesign_web_oauth_OAuthConsumer
  extends littlemdesign_util_Object {

  /* the supported signature methods */
    
  const SIG_HMAC_SHA1 = 1;
  const SIG_PLAINTEXT = 2;
  const SIG_RSA_SHA1  = 3;

  /* 
   * the kinds of OAuth remote authentication providers
   * we can work with.
   * 
   */
  
  const TWITTER     = 1;
  const FACEBOOK    = 2;
  const LINKEDIN    = 3;
  const GOOGLE      = 4;
  const YAHOO       = 5;
  const WITHINGS    = 6;
  const DAILYMILE   = 7;
  const UNDERARMOUR = 8;
  const STRAVA      = 9;
  
  /**
   * 
   * The name of this resource.
   * 
   * @var string
   * 
   */
    
  private $name      = "";
  
  private $key       = false;
  
  private $secret    = false;
  
  private $callback  = false;
  
  private $sigMethod = self::SIG_HMAC_SHA1;
  
  /**
   * 
   * After authentication is complete, we have the
   * access token/secete that we can use to access
   * the remote provider on behalf of the user.
   * 
   * @var unknown_type
   * 
   */
  
  private $accessToken  = false;
  private $accessSecret = false;
  
  /**
   * 
   * At the moment all service providers are supposed to
   * assume protocal version 1.0, so we force 1.0 and do 
   * not allow it to be set by the caller.
   *  
   * @var string
   * 
   */
  
  private $version  = "1.0";
  
  
  /**
   * 
   * If the user has denied permission or otherwise
   * canceled the login, we set this to true, so we 
   * know this condition separately from a general
   * connection error.
   * 
   * @var boolean.
   * 
   */
  
  private $denied   = false;
  
  /**
   * 
   * Standard constructor, your OAuthConsumer must be named,
   * it can be an arbitrary name, but it must be named. The
   * name is used to refine the log messages.
   * 
   * @param string $name - the name of your sub-class, used to refine log
   * messages.
   * 
   * @param string $key - the OAuth key (sometimes called App ID or Application key)
   * 
   * @param string $secret - the consumer secret, should never be made publicly
   * visible on yoru website in any way.
   * 
   * @param string $callback - the URL to return to once the remote authentication
   * service is done.
   * 
   */
    
  public function __construct($name, $key, $secret, $callback) {
    
    $name = trim($name);
    if(empty($name)) {
      return ;
    }
    
    $this->name      = $name;
    
    $this->sigMethod = self::SIG_HMAC_SHA1;
    
    parent::__construct("OAuthConsumer[$name]", 'littlemdesign_web_oauth', true);

    $this->info("Constructing...");
    
    /* 
     * in order to authenticate the consumer must provide the key, 
     * secret and callback URL.  The callback URL establishes how
     * we will finish the authentication sequence.
     * 
     */
    
    if(empty($key)) {
      $this->error("Can not construct, no key.");
      return ;
    }
    if(empty($secret)) {
      $this->error("Can not construct, no secret.");
      return ;
    }
    
    /* 
     * call back can be empty because some providers store
     * the callback at the provider (not publicly visible).
     * 
     */
    
    $this->key      = $key;
    $this->secret   = $secret;
    $this->callback = $callback;
    
    /* good to  go */
    
    $this->makeReady();
    
    $this->info("ready.");  
  }
  
  /**
   * 
   * getCallback() - fetch the redirection URL.
   * 
   * @return string the redirection URL.
   * 
   */
  
  public function getCallback() {
    return $this->callback;
  }
  
  /**
   * 
   * getKey() - fetch the application/consumer key.
   * 
   * @return string - the application/consumer key.
   * 
   */
  
  public function getKey() {
    return $this->key;
  }
  
  /**
   * 
   * isDenied() - check to see if the user denied us
   * access.
   * 
   * @return boolean return true if we are denied access.
   * 
   */
  
  public function isDenied() {
    return $this->denied;
  }
  
  public function getVersion() {
    return $this->version;
  }
  
  /**
   * 
   * setDenied() - set or lcear the access denied 
   * flag (a user intentionally cancels authentication).
   * 
   * @param boolean $flag
   * 
   */
  
  public function setDenied($flag=false) {
    $this->denied=$falg;
    return $this;
  }
  
  /**
   * 
   * getToken() - fetch the acces token, will only be non-false
   * after authentication has completed.
   * 
   */
  
  public function getToken() {
    return $this->accessToken;
  }
  
  /**
   * 
   * setToken() - set the access token.
   * 
   * @param string $token
   * 
   */
  
  public function setToken($token) {
    $this->accessToken = $token;
    return $this;
  }

  /**
   * 
   * getSecret() - fetch the access secret, will only be non-false
   * after authentication has completed.
   * 
   * @return string the access secret.
   * 
   */
  
  public function getSecret() {
    return $this->accessSecret;
  }
  
  public function setSecret($secret) {
    $this->accessSecret = $secret;
    return $this;
  }
  
  /**
   * 
   * getConsumerSecret() - fetch the consumer secret, the
   * secret for the application, not the particular user.
   * 
   * @return string the consumer secret.
   * 
   */
  
  public function getConsumerSecret() {
    return $this->secret;  
  }
  
  /**
   * 
   * nonce() - "number used once" used to help make requests 
   * unqiue by including a random value in each request that is 
   * guarenteed to be unique among the requests you are senting to 
   * the remote provider.  Its intended to avoid replay attacks on 
   * the protocol.
   * 
   * ref: 
   * 
   *   http://stackoverflow.com/questions/4145531/how-to-create-and-use-nonces
   * 
   * There is actually a library for this:
   * 
   *   http://fullthrottledevelopment.com/php-nonce-library
   *   
   * But we shouldn't need to go so far on this.
   * 
   * @return string the randome text string to use for nonce
   * 
   */
  
  public static function nonce($bits=256,$method='sha512') {
    
    $bytes = ceil($bits / 8);
    $text  = '';
    
    for ($i = 0; $i < $bytes; $i++) {
      $text .= chr(mt_rand(0, 255));
    }
    
    $text = hash($method, $text);
    
    /* pass it back */
    
    return $text;
  }
  
  /**
   * 
   * timestamp() - the protocol defines timestamps as Julian
   * seconds since 1971, i.e. unix time, so we just fall 
   * through to time();
   * 
   * @return integer timestamp
   * 
   */
  
  public static function timestamp() {
    return time();  
  }
  
  /**
   * 
   * baseString() - helper method to build the base string to use 
   * for signing a request.  You most provide the HTTP method, 
   * the URL and all parameters, including the OAuth parameters.
   * 
   * @param string $method - the HTTP method being used GET or POST
   * 
   * @param URL $url - the URL object that includes the endpoint 
   * URL and any parameters being passed, including the OAuth 
   * parameters.
   * 
   */
  
  public static function baseString($method, $url) {
    
    $method = strtoupper(trim($method));
    if(($method != "GET")&&($method != "POST")) {
      $method = "GET";
    }
    
    if(!is_object($url)) {
      return false;
    }
    
    $base    = "";
    $noQuery = array_shift(explode('?',$url->toString()));
    $query   = $url->getQuery();
    
    /* percent encode the method and URL */
    
    $args = array_map(
      'littlemdesign_web_http_URL::percentEncode',
      array(
        $method,
        $noQuery
      )
    );
    
    $base = implode('&', $args);
        
    /*
     * The spec says to sort on key and then on value for keys, but
     * in most cases we should not have duplicate keys in requests,
     * so we just sort based on the key. 
     * 
     */

    uksort($query, 'strcmp');

    $paramString = "";
    foreach($query as $k => $v) {
      
      /* percent encode the paramters (including OAuth parameters) */
   
      $k = littlemdesign_web_http_URL::percentEncode($k);
      $v = littlemdesign_web_http_URL::percentEncode($v);
      
      $paramString .= "$k=$v&";
    }
    
    $paramString = trim($paramString, "&");
    
    /* percent encode the entire parameters string */
    
    $paramString = littlemdesign_web_http_URL::percentEncode($paramString);
    
    /* 
     * ok, we now have everything for a complete base string
     * suitable for signing.
     * 
     */
    
    $base .= "&$paramString";
    
    /* all done */
    
    return $base;
  }
  
  /**
   * 
   * signatureKey() - generate a signing key.  This object must already
   * be valid, meaning it was constructed with a valid consumer
   * key and secret.  The returned key is ready for use, it is
   * constructed, and encoded as appropriate.
   * 
   * @param string $tokenSecret - the token secret (which identifies
   * the user account we are working on behalf of).  It may not be 
   * know yet, so its ok to give an empty string.
   * 
   * @return string returnt the percent encooded signing key, ready
   * to use for signing.
   * 
   */
  
  public function signaturekey($tokenSecret='') {
  
    $consumerSecret = littlemdesign_web_http_URL::percentEncode($this->secret);
    if(!empty($tokenSecret)) {
      $tokenSecret = littlemdesign_web_http_URL::percentEncode($tokenSecret);
    }
    
    $signingKey = "$consumerSecret&$tokenSecret";
    
    /* all done */
    
    return $signingKey;
  }
  
  /**
   * 
   * buildOAuthHeader() - internal helper to build the OAuth 
   * header for a given request.
   * 
   * @param URL $endpoint - the request we are making, which
   * already includes oauth_* parameters.  Must be  web\http\URL
   * object.
   * 
   * @return string - the OAuth header.
   * 
   */
  
  public static function buildOAuthHeader($endpoint, $realm='') {

    $text    = "";
    if(!empty($realm)) {
      $text  = 'Authorization: OAUTH realm="';
      $text .= littlemdesign_web_http_URL::percentEncode($realm);
      $text .= '", ';
    } else {
      $text = 'Authorization: OAuth ';
    }
    
    $params  = $endpoint->getQuery();

    uksort($params, 'strcmp');
    
    foreach ($params as $k => $v) {
      
      if (substr($k, 0, 5) != "oauth") {
        continue;
      }
      
      /* note that consumer key is already encoded. */
      
      $text .= littlemdesign_web_http_URL::percentEncode($k);
      $text .= '="';
      $text .= littlemdesign_web_http_URL::percentEncode($v);
      $text .= '", ';
    }
    
    $text = trim($text, ', ');
    
    /* all done */
    
    return $text;
  }
  
  /**
   * 
   * generateRequest() - internal helper to generate a an OAuth 
   * request.  The URL object should already include any necessary
   * OAuth parameters.
   * 
   * @param string $method - the HTTP method, should be GET or 
   * POST.
   * 
   * @param URL $endpoint - the remote resoruce we are accessing,
   * must be a web\http\URL object.  The URL must include in its
   * parameters the following OAuth parameters:
   * 
   *   oauth_version
   *   oauth_nonce
   *   oauth_timestamp
   *   oauth_consumer_key
   *   oauth_signature_method
   *   
   * Some requests should optionally add oauth_token.
   * 
   * @param string tokenSecret - the secret which identifies the
   * account of the user (at the remote provider) we are authenticating
   * on behalf of.
   * 
   * @return object return an anonymous object that has the generated
   * headers and request URL. (everything needed to actually send
   * a request.
   * 
   */
  
  protected function generateSignedRequest($method, $endpoint, $tokenSecret='', $realm='') {
    
    if(!$this->isReady()) {
      $this->error("generateRequest() - not ready.");
      return false;
    }
    
    if(!is_object($endpoint)) {
      $endpoint = littlemdesign_web_http_URL::create($endpoint);
    }
    
    /* NOTE: we only currently support HMAC-SHA1 signing */
    
    $baseString = self::baseString($method, $endpoint);
    $key        = $this->signatureKey($tokenSecret);
  
    $signature  = base64_encode(hash_hmac('sha1', $baseString, $key, true));
     
    /* prepare the request headers */
    
    $endpoint->mergeQuery(array(
      "oauth_signature" => $signature,
    ));
    
    $oauthHeader = self::buildOAuthHeader($endpoint, $realm);
    
    /* 
     * ok, we have everything we need to pasa back a 
     * result object.
     * 
     */
    
    $result = (object)array(
      "method"        => $method,
      "endpoint"      => $endpoint,
      "signature"     => $signature,
      "authorization" => $oauthHeader
    );
    
    /* all done */
    
    return $result;
  }
  
  /**
   * 
   * beginLogin() - this is the method sub-classes should
   * implement to to start the login sequence.
   * 
   */
  
  public function beginLogin() {
    return false;
  }
  
  /**
   * 
   * finishLogin() - this is methodd sub-classes should 
   * implement to finish the login sequence.
   * 
   * @param unknown_type $token
   * @param unknown_type $verifier
   * 
   */
  
  public function finishLogin($token, $verifier) {
    return false;  
  }
  
  /**
   * 
   * get() - helper function to do a signed GET from the
   * provider.  We assume that the access token and secret
   * have been set, becasue we will use those to sign the
   * request.
   * 
   * @param URL $request - the URL we want to do a signed GET 
   * from.
   * 
   * @param string realm - some providers may need you to 
   * provide a realm parameter for added scoping/protection.
   * I haven't seen it used yet by any provider, but its 
   * possible per the protocol.
   * 
   * @return object - an object that includes the full results 
   * of the webcall, a member 'meta' for stats and status, 
   * 'data' for the actual result from the provider.
   * 
   */
  
  public function get($request, $realm="", $verb="GET") {
    
    $this->info("get() starts...");
    
    /* ready to go? */
    
    if(!$this->isReady()) {
      $this->error("get() - not ready.");
      return false;
    }
    
    if(($this->getToken() === false)||($this->getSecret() === false)) {
      $this->error("get() - access token or secret not set yet.");
      return false;
    }
    
    if(!is_object($request)) {
      $request = littlemdesign_web_http_URL::create($request);
    }
    
    $textURL = $request->toString(littlemdesign_web_http_URL::RFC_3986);
    
    /* add in basic OAuth parameters */
    
    $oauthParams = array(
      "oauth_version"          => $this->version,
      "oauth_nonce"            => self::nonce(),
      "oauth_timestamp"        => self::timestamp(),
      "oauth_consumer_key"     => $this->key,
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
      $headers); 
     
    /* havest */
      
    if($result === false) {
      $this->error("get() - problem with cURL request: ".$curl->getError());
      return false;
    }
    
    /* 
     * pass back everything we got, let the caller do what they will
     * with the results.
     * 
     */
    
    return $result;
  }
 
  /**
   * 
   * requestAuthentication() start the authentication process. 
   * We need the user to give their credentials to the remote
   * provider, so we must redirect the browser to the 
   * remote provider and then after authentication, 
   * our login callback URL is called by the remote provider
   * to hand control back to us (via the web browser).
   * 
   * This means a call this this method should be the last
   * thing that happens in your script, because the last 
   * part of this method is to redirect the browser.
   * 
   * @param string $method - the HTTP method, GET or POST 
   * normally.
   * 
   * @param URL $request - the URL to use for requesting 
   * temporary credentials (request token)  Typically something 
   * like 
   * 
   *   https:://provider.com/oauth/request_token
   * 
   * The URL object must be a valid web\http\URL object. We 
   * use URL objects because they understand percent encoding
   * etc.  If you  pass in a full URL as a string, it will
   * be auto-converted to a URL object.
   * 
   * @param URL $authenticate - similar to $request, but this
   * is the URL to actually redirect the user's browser to,
   * once we have a request token.
   * 
   * This method redirects the browser but will still return 
   * control to your script before control is passed to the
   * browser. So there is a return value, but this method 
   * should be the last call in your script.
   * 
   * @return boolean - return exactly false if there is any
   * kind of problem sending the request for a request token.
   *  
   */
  
  public function requestAuthentication($request, $authenticate) {
    
    $this->info("requestAuthentication() starts...");
    
    /* ready to go? */
    
    if(!$this->isReady()) {
      $this->error("requestAuthentication($request, $authenticate) - not ready.");
      return false;
    }
    
    /* 
     * step 1 - get a temporary request token, but do it 
     * securely. We do this "server to server" so the browser
     * doens't see this step, and neither can a man in the
     * middle...at least not one watching the user's browser,
     * hopefully the endpoint is https://, so that it can't
     * be monitoried server to server either.
     * 
     */
    
    /* have headers already been sent? */
    
    if(headers_sent()) {
      $this->error("requestAuthentication($request, $authenticate) - headers already sent.");
      return false;
    }
    
    if(!is_object($request)) {
      $request = littlemdesign_web_http_URL::create($request);
    }
    if(!is_object($authenticate)) {
      $authenticate = littlemdesign_web_http_URL::create($authenticate);
    }
    
    $textURL = $request->toString();
    if(!preg_match('/^https:/', $textURL)) {
      $this->warning("requestAuthentication($request, $authenticate) - non secured request endpoint in use.");
    }
    
    $this->info(". fetching request token via ($textURL)...");
    
    /* add in basic OAuth parameters */
    
    $oauthParams = array(
      "oauth_version"          => $this->version,
      "oauth_nonce"            => self::nonce(),
      "oauth_timestamp"        => self::timestamp(),
      "oauth_consumer_key"     => $this->key,
      "oauth_signature_method" => "HMAC-SHA1",
      "oauth_callback"         => $this->callback
    );
    $request->mergeQuery($oauthParams);
    
    /* 
     * generate a signed request, per protocol must 
     * providers should be using POST for this step.
     * 
     */ 
    
    $requestObj = $this->generateSignedRequest("POST", $request);
    if($requestObj === false) {
      $this->error("requestAuthentication($request, $authenticate) - can not generate request token object.");
      return false;
    }
    
    /* request a temp token ... */
    
    /* 
     * step 2 - autehticate.  Now that we have a request token, 
     * we need to convert it into an access token, so we have to
     * redirect to the authenication URL.
     * 
     */
    
    /* get ready to do a cURL request */
    
    $curl = new littlemdesign_web_http_CURL($textURL);
    if(!$curl->isReady()) {
      $this->error("requestAuthentication($request, $authenticate) - no cURL: ".$curl->getError());
      return false;
    }
    
    /* format the headers */
    
    $auth    = trim(substr($requestObj->authorization, 14));
    $headers = array( 
      "Content-Type"  => "application/x-www-form-urlencoded",
      "Authorization" => $auth,
      "Expect"        => ''
    );
    
    /* POST our request... */
    
    $result  = $curl->write(
      "POST", 
      $textURL, 
      "", 
      $headers['Content-Type'], 
      $headers); 
    
    /* havest */
      
    if($result === false) {
      $this->error("requestAuthentication($request, $authenticate) - problem with cURL request: ".$curl->getError());
      return false;
    }
    
    $statusCode = $result->meta['http_code'];
    if($statusCode != 200) {
      $this->error("requestAuthentication($request, $authenticate) - provider error ($statusCode): ".$result->data); 
      return false;
    }
    
    /*
     * ok, we should have gotten back a token, secret, and callback confirmed.
     * 
     */
    
    $data  = array();
    $pairs = explode('&', $result->data);
     
    foreach($pairs as $item) {
      
      list($k,$v) = explode('=', $item);
      
      $k = urldecode($k);
      $v = urldecode($v);
      
      $data[$k] = $v;
    }
   
    $authenticate->setQuery(array(
      "oauth_token" => $data['oauth_token']
    ));
    
    $secret = "";
    if(isset($data['oauth_token_secret'])) {
      $secret = $data['oauth_token_secret'];
    }
    
    /* 
     * some providers,i.e. Yahoo, generate a token secret as part
     * of the request token, and then require you to sign your
     * access token request with that secret.  So if there is a
     * request token secret...we have to remember it accross 
     * the authenticaction re-direct.  We use the PHP session 
     * for this.
     * 
     */
  
    if(!empty($secret)) {
      
      /* make sure we have a session */
      
      $id = session_id();
      if(empty($id)) {
        session_start();
      }
      
      /* save */
      
      $this->info(". saving request token secret: $secret");
      
      $_SESSION['OAuthConsumer:ReqeuestSecret'] = $secret;
    }  
  
    $textURL = $authenticate->toString();
    
    $this->info(". request token: ".$data['oauth_token']);
    $this->info(". authenicating with request token via $textURL");
     
    /* do the actual redirect */    
    
    header("Location: $textURL");
    
    /* all done */
    
    return true;
  }
  
  /**
   * 
   * receiveAuthentication() - we've previously launched 
   * an authentication request to a remote authentication
   * provider, like Twitter, Facebook etc.  The user
   * has entered their credentials and now the remote
   * authentication service is handing control back 
   * to us.
   * 
   * In general this method will take the steps of
   * converting an OAuth request token in an access
   * token that we can actually use to access the 
   * remote application, and do any other chores, 
   * like log the user into our own site etc.
   * 
   * If you need to do more than just receive the 
   * authentication info, you should override
   * this method, define your code but first call
   * parent::receiveAuthentication()
   * 
   * @param URL access - the URL to use for converting
   * request tokens to access tokens.  If you do not 
   * provide a web\http\URL object, it will be auto-
   * constructed for you.
   * 
   * @param array $params - any parameters received
   * in the login callback that are relevant to 
   * completing authentication.
   * 
   * Specifically we expect to see:
   * 
   *   array(
   *    "oauth_token"    => $token,
   *    "oauth_verifier" => $verifier
   *   )
   * 
   * Where $token and $verifier are from the incoming GET 
   * callback from the remote provider.
   * 
   * @return array - on success we hand back whatever 
   * parameters we were given, separate from the OAuth
   * parameters.  You can use these to build a user profile
   * object (who it was that authenticated)
   * 
   * If there is problem of some kind, we return exactly 
   * false.
   * 
   */
  
  public function receiveAuthentication($access, $params=array()) {
    
    $this->info("receiveAuthentication() starts...");
    
    /* ready to go? */
    
    if(!$this->isReady()) {
      $this->error("receiveAuthentication() - not ready.");
      return false;
    }
    
    if(!is_object($access)) {
      $access = littlemdesign_web_http_URL::create($access);
    }
    
    $textURL = array_shift(explode('?',$access->toString()));
    
    if(!preg_match('/^https:/', $textURL)) {
      $this->warning("receiveAuthentication() - non secured access endpoint in use.");
    }
    
    if(!isset($params['oauth_token'])) {
      $this->errro("receiveAuthentication() - missing token.");
      return false;  
    }
    if(!isset($params['oauth_verifier'])) {
      $this->errro("receiveAuthentication() - missing verifier.");
      return false;  
    }
    
    $token    = $params['oauth_token'];
    $verifier = $params['oauth_verifier'];
    
    /* 
     * some OAuth 1.x providers (i.e. Yahoo) use a token secret 
     * that is generated before we redirect ot the autehtnication
     * page at the provider, we have to remember that (if
     * its present) and use it to sign the access token
     * request.
     * 
     * Twitter doens't use a secret until after you have an access
     * token.
     * 
     * To recover the secret (if there is one), we look in the 
     * default PHP session (where we saved it).
     * 
     */
    
    $secret   = "";
    
    /* make sure we have a session */
      
    $id = session_id();
    if(empty($id)) {
      session_start();
    }

    if(isset($_SESSION['OAuthConsumer:ReqeuestSecret'])) {
      $secret = $_SESSION['OAuthConsumer:ReqeuestSecret'];
      unset($_SESSION['OAuthConsumer:ReqeuestSecret']);
    }
     
    $this->info(". fetching access token via ($textURL,$token,$verifier)...");
    $this->info(". . using secret: $secret");
    
    /* add in basic OAuth parameters */
    
    $oauthParams = array(
      "oauth_version"          => $this->version,
      "oauth_nonce"            => self::nonce(),
      "oauth_timestamp"        => self::timestamp(),
      "oauth_consumer_key"     => $this->key,
      "oauth_signature_method" => "HMAC-SHA1",
      "oauth_token"            => $token,
      "oauth_verifier"         => $verifier
    );
    $access->mergeQuery($oauthParams);
    
    /* the verifier goes in the POST body, to be more secure */
    
    /* 
     * generate a signed request, per protocol must 
     * providers should be using POST for this step.
     * 
     */ 
    
    $accessObj = $this->generateSignedRequest("POST", $access, $secret);
    if($accessObj === false) {
      $this->error("receiveAuthentication() - can not generate access token object.");
      return false;
    }
     
    /*
     * ok, we need to make a POST request to the access token endpoint...
     * 
     */
    
    /* get ready to do a cURL request */
    
    $curl = new littlemdesign_web_http_CURL($textURL);
    if(!$curl->isReady()) {
      $this->error("receiveAuthentication() - no cURL: ".$curl->getError());
      return false;
    }
    
    /* format the headers */
    
    $auth    = trim(substr($accessObj->authorization, 14));
    $headers = array( 
      "Content-Type"  => "application/x-www-form-urlencoded",
      "Authorization" => $auth,
      "Expect"        => ''
    );
    
    /* the form data must be allthe oauth paramters */
    
    
    $form  = "";
    $form .= "oauth_version=".littlemdesign_web_http_URL::percentEncode($this->version);
    $form .= "&oauth_nonce=".littlemdesign_web_http_URL::percentEncode(self::nonce());
    $form .= "&oauth_timestamp=".littlemdesign_web_http_URL::percentEncode(self::timestamp());
    $form .= "&oauth_consumer_key=".littlemdesign_web_http_URL::percentEncode($this->key);
    $form .= "&oauth_signature=".littlemdesign_web_http_URL::percentEncode($accessObj->signature);
    $form .= "&oauth_signature_method=".littlemdesign_web_http_URL::percentEncode('HMAC-SHA1');
    $form .= "&oauth_token=".littlemdesign_web_http_URL::percentEncode($token);
    $form .= "&oauth_verifier=".littlemdesign_web_http_URL::percentEncode($verifier);
    
    /* POST our request... */
    
    $result  = $curl->write(
      "POST", 
      $textURL, 
      $form, 
      $headers['Content-Type'], 
      $headers); 
    
    /* havest */
      
    if($result === false) {
      $this->error("receiveAuthentication() - problem with cURL request: ".$curl->getError());
      return false;
    }
    
    $statusCode = $result->meta['http_code'];
    if($statusCode != 200) {
      $this->error("receiiveAuthentication() - provider error ($statusCode): ".$result->data); 
      return false;
    }
    
    /*
     * we expect to get the access token and secret, but the provider 
     * may hand back othe details to us (which we can use to build the
     * user profile.
     * 
     */
    
    $data  = array();
    $pairs = explode('&', $result->data);
     
    foreach($pairs as $item) {
      
      list($k,$v) = explode('=', $item);
      
      $k = urldecode($k);
      $v = urldecode($v);
      
      if($k == "oauth_token") {
        $this->setToken($v);
      }
      
      if($k == "oauth_token_secret") {
        $this->setSecret($v);
      }
      
      if(preg_match('/^oauth_/',$k)) {
        continue;
      }
      
      $data[$k] = $v;
    }
    
    /* pass back the non-OAuth attributes */
  
    return $data;
  }
}

?>
  