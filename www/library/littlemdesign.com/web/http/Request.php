<?php 

/**
 * 
 * web \ http \ Request - a general Request helper, mainly 
 * intended as a base class for REST clients.  These 
 * requests are *outgoing* from this server to another
 * server.  For handing *incoming* requests from a web
 * client or browser you should use WebRequest.
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

/* make sure we auto-load required stuff */

autorequire('littlemdesign_util_Object');
autorequire('littlemdesign_util_Error');
autorequire('littlemdesign_web_http_ContentHandler');
autorequire('littlemdesign_web_http_WebWriter');
autorequire('littlemdesign_web_http_URL');
autorequire('littlemdesign_web_http_CURL');
autorequire('littlemdesign_web_http_Response');

/**
 * 
 * class Request - application level API for sending
 * and receiving data to/from a remote web server.  This
 * class ties together the way we read/write to the
 * remote web server (WebWriter)...for now just using
 * CURL (a variant of WebWriter), ContentHandler - which
 * gives us ContentParser appropriate for the MIME type 
 * of this Request, finally the user can work with a
 * Response object instead of raw data from the remote
 * we server.  
 * 
 * When sending and receiving data, an appropriate ContentParser
 * will be used to parse incoming data and serialize outgoing
 * data.
 * 
 * WebWriter comes in essentially two flavors in PHP; either 
 * socket based communication or cURL based communication with
 * the remote web server.  For now we are using only CURL.
 *
 * General usage is to use the static factory method create()
 * to generate a Request object, use the various set methods
 * to configure it, and finally use send() to actually send
 * off the request.
 * 
 */

class littlemdesign_web_http_Request
  extends littlemdesign_util_Object {

  /**
   * 
   * The modes of parsing and serialiation. If you 
   * use auto, then the MIME type will be used to 
   * try and find a content handler, if none can 
   * be found then plain (passthrough) will be used.
   *
   * If you specify a mode that doesn't exist, then 
   * plain (passthrough) will be used.
   * 
   */
  	
  const NEVER  = 'never';
  const ALWAYS = 'always';
  const AUTO   = 'auto';
  
  /**
   * 
   * The URL we are connecting to (a URL object)
   * 
   * @var URL
   * 
   */
  		
  private $url = null;
  
  /**
   * 
   * The REST 'verb' (HTTP method)
   * 
   * @var string
   * 
   */
  
  private $verb = "GET";
  
  /**
   * 
   * The MIME type for the request and the response,
   * normally they should be the same, and for most
   * REST API interactions it will be JSON.
   * 
   * @var string
   * 
   */
  
  private $requestType  = "plain";
  private $responseType = "plain";
  
  /**
   * 
   * The request headers, for now an array, but eventually
   * we might make this an object that can do convenience 
   * functions for headers as a whole.
   * 
   * @var mixed
   * 
   */
  
  private $headers = array();
  
  /**
   * 
   * The actual content (body) of the request.  Note that
   * you can have parameters as part of the URL and a body
   * at the same time.
   * 
   * @var mixed 
   * 
   */
  
  private $body = "";
  
  /**
   * 
   * How to interpret incoming data 
   * 
   * @var string
   * 
   */
  
  private $parseMode = 'auto';
  
  /**
   * 
   * How to interpret outgoing data
   * 
   * @var string
   * 
   */
  
  private $serialMode = 'auto';
  
  /**
   *
   * The executation timeout (not the connection timeout).  Use
   * 0 to wait forever.
   * 
   * @var integer
   * 
   */
  
  private $timeout = 60;
  
  /**
   * 
   * User credentials, when appropriate.
   * 
   * @var string
   * 
   */
  
  private $username = "";
  private $password = "";

  /**
   * 
   * The WebWriter we use to chat with a remote web server,
   * will normally be a CURL object.
   * 
   * @var WebWriter
   * 
   */
  
  private $writer = null;
  
  /**
   *
   * standard constructor
   * 
   */
  	
  public function __construct() {

  	parent::__construct('Request', 'littlemdesign_web_http', true);

  	$this->info("Request is constructing...");
  	
  	/* we are not ready for use until the WebWriter has been setup */
  	
  	$this->unReady();
  	
  	/* try to make a WebWriter */
  	
  	$this->writer = new littlemdesign_web_http_CURL();
  	if(!$this->writer->isReady()) {
  	  $this->error("Request can not constuct, problem making writer: ".$this->writer->getError());
  	  return ;
  	}
  	
  	/* all set */
  	
  	$this->makeReady();
  	
  	$this->info("Request constructed.");
  }
  
  /**
   * 
   * create() - this is the standard factory method for Request objects,
   * users should not attempt to call the constructor directly, they 
   * should always use the factory methods.
   * 
   * @param string $verb
   * @param URL $url the URL to remote resource
   * @param mixed $content
   * @param string $mime the standard MIME type to use on request and response
   * 
   * @return Request the new (ready) request, not yet sent.
   * 
   */
  
  static public function create($verb='GET', $url=null, $content='', $mime='plain') {
  	
  	$request = new littlemdesign_web_http_Request();
  	$request
  	  ->setVerb($verb)
  	  ->setURL($url)
  	  ->setBody($content)
  	  ->setAutoParse(self::AUTO)
  	  ->setAutoSerialize(self::AUTO)
  	  ->setContentType($mime)
  	  ->setResponseType($mime);
  	  
  	return $request;
  }
  
  /**
   * 
   * get() - create (but do not yet send) a GET request.
   * 
   * @param URL $url the URL to remote resource
   * @param mixed $content the body of the request
   * @param string $mime the standard MIME type to use on request and response
   * 
   * @return Request the new (ready) request, not yet sent.
   * 
   */
  
  static public function get($url, $content='', $mime='plain') {
    return self::create('GET',$url,$content,$mime);	
  }
  
  /**
   * 
   * post() - create (but do not yet send) a POST request.
   * 
   * @param URL $url the URL to remote resource
   * @param mixed $content the body of the request
   * @param string $mime the standard MIME type to use on request and response
   * 
   * @return Request the new (ready) request, not yet sent.
   * 
   */
  
  static public function post($url, $content, $mime='plain') {
    return self::create('POST',$url,$content,$mime);	
  }
  
  /**
   * 
   * put() - create (but do not yet send) a PUT request.
   * 
   * @param URL $url the URL to remote resource
   * @param mixed $content the body of the request
   * @param string $mime the standard MIME type to use on request and response
   * 
   * @return Request the new (ready) request, not yet sent.
   * 
   */
  
  static public function put($url, $content, $mime='plain') {
    return self::create('PUT',$url,$content,$mime);	
  }
  
  /**
   * 
   * delete() - create (but do not yet send) a DELETE request.
   * 
   * @param URL $url the URL to remote resource
   * @param mixed $content the body of the request
   * @param string $mime the standard MIME type to use on request and response
   * 
   * @return Request the new (ready) request, not yet sent.
   * 
   */
  
  static public function delete($url, $content='', $mime='plain') {
    return self::create('DELETE',$url,$content,$mime);	
  }
  
  /**
   * 
   * options() - create (but do not yet send) a OPTIONS request.
   * 
   * @param URL $url the URL to remote resource
   * @param mixed $content the body of the request
   * @param string $mime the standard MIME type to use on request and response
   * 
   * @return Request the new (ready) request, not yet sent.
   * 
   */
  
  static public function options($url, $content='', $mime='plain') {
    return self::create('OPTIONS',$url,$content,$mime);	
  }
  
  /**
   * 
   * head() - create (but do not yet send) a HEAD request.
   * 
   * @param URL $url the URL to remote resource
   * @param mixed $content the body of the request
   * @param string $mime the standard MIME type to use on request and response
   * 
   * @return Request the new (ready) request, not yet sent.
   * 
   */
  
  static public function head($url, $content='', $mime='plain') {
    return self::create('HEAD',$url,$content,$mime);	
  }
  
  public function parse($body) {
  	
  	/* pass through? */
  	
  	if(empty($body)||($this->parseMode == self::NEVER)) {
  	  return $body;
  	}
  	
  	/* 
  	 * for parsing response data...which always arrives as
  	 * a string, AUTO ans ALWAYS are the same thing. So if 
  	 * we get this far, we have to convert.
  	 * 
  	 */
  	
  	$contentParser = littlemdesign_web_http_ContentHandler::findParser($this->responseType);
  	if($contentParser === false) {
  	  $this->error("Can not serialize, can not find parser for MIME (".$this->responseType.")");
  	  return false;
  	}
  	
  	$this->info(". parsing with ".get_class($contentParser));
  	
  	$transformed = $contentParser->parse($body);
  	
  	/* pass back the result */
  	
  	return $transformed;
  }
  
  /**
   * 
   * serialilze() - format the content of this request for transmission
   * to the remote web server.
   * 
   * @param mixed $body
   * 
   * @return string data to send.  If there is problem serializing, 
   * then return exactly false.  
   * 
   */
  
  public function serialize($body) {
  	
  	/* nothing to do? */
  	
  	if(empty($body)||($this->serialMode == self::NEVER)) {
      return $body;
  	}
  	
  	/* if they are uploading a file, we leave it alone */
  	
  	if(is_resource($body)) {
  	  return $body;
  	}
  	
  	/* 
  	 * if we are in auto mode, then simple scalar types do 
  	 * not get transformed.
  	 * 
  	 */
  	
  	if(is_scalar($body) && $this->serialMode == self::AUTO) {
  	  return $body;
  	}
  	
  	/*
  	 * Its either a complex type, that AUTO mode will do, or 
  	 * we are always serializing.  So pick a formatter and go.
  	 * 
  	 */
  	
  	$contentParser = littlemdesign_web_http_ContentHandler::findParser($this->requestType);
  	if($contentParser === false) {
  	  $this->error("Can not serialize, can not find parser for MIME (".$this->requestType.")");
  	  return false;
  	}
  	
  	$this->info(". Serializing with ".get_class($contentParser));
  	
  	$transformed = $contentParser->serialize($body);
  	
  	/* pass back the result */
  	
  	return $transformed;
  }
  
  /**
   * 
   * send() - send the request to the remote web server, formatting
   * the data to/from the remote web server, and create and return
   * an appropriate Response object.
   * 
   * @return Response the response from the remote web server.
   * 
   */
  
  public function send() {
  	
  	$response = new littlemdesign_web_http_Response();
  	
  	/* do we have a WebWriter? */
  	
   if(!$this->isReady() || ($this->writer === null)) {
  	  $this->error("Request can not send, not ready (no WebWriter?).");
  	  $response->error("Request can not send, not ready (no WebWriter?).");
      return $response;  		
  	}
  	
  	/* do we have a URL? */
  	
  	$urlStr = $this->url->toString();
  	if(($this->url === false)||($this->url === null)||empty($urlStr)) {
  	  $this->error("Request can not send, no URL.");
  	  $response->error("Request can not send, no URL.");
  	  return $response;
  	}
  	
  	/* copy parameters to the WebWriter */
  	
  	$this->writer->setTimeout($this->timeout);
  	
  	if(!empty($this->username)) {
      $this->writer->setUser($this->username);
      $this->writer->setPasswrod($this->password);
  	}
  	
  	/* merge in any last minute headers */

  	$hdrs = $this->headers;
  	
  	{
      if(!isset($hdrs['Content-Type'])) {
        $hdrs['Content-Type'] = $this->requestType;	
      }
  	}
  	
  	/* serialize the data for sending */
  	
  	$body = $this->serialize($this->body);
  	
  	/* are they trying to upload, but not using PUT? */
  	
  	if(is_resource($body) && ($this->verb != 'PUT')) {
  	  $this->error("Request can not send, uploading file but verb is not PUT ($verb)");
  	  $response->error("Request can not send, uploading file but verb is not PUT ($verb)");
  	  return $response;
  	}
  	
    /* send it! */
  	
  	$result = $this->writer->write(
  	  $this->verb, 
  	  $this->url, 
  	  $body, 
  	  $this->requestType, 
  	  $hdrs);
  	  
  	if($result === false) {
  	  $this->error("Request can not send, problem writing to net: ".$this->writer->getError());
  	  $response->error("Request can not send, problem writing to net: ".$this->writer->getError());
  	  return $response;
  	}
  	
  	/* 
  	 * result has variable fields that we can pull out to setup the 
  	 * Response object properly.
  	 * 
  	 */
  	
  	$response->headers = $result->headers;
  	$response->status  = $result->meta['http_code'];
  	$response->request = $this;
  	
  	if(isset($result->headers['Content-Type'])) {
  	  $response->responseType = $result->headers['Content-Type'];
  	} else {
  	  $response->responseType = $this->responseType;
  	}
  	$this->responseType =  $response->responseType;
  	
  	/* decode the response content */
  	
    $body = $this->parse($result->data);
  	$response->body = $body;

  	/* all done */
  	
  	return $response;
  }
  
  /**
   * 
   * setAutoSerialize() - set the serialization (output) mode.
   * 
   * @param string $mode must be one of AUTO, ALWAYS or NEVER
   * 
   * @return self
   * 
   */
  
  public function setAutoSerialize($mode=self::AUTO) {
  	
    switch($mode) {
      case self::AUTO:
      case self::NEVER:
      case self::ALWAYS:
      	$this->serialMode = $mode;
      	break;
      default:
      	{
      	  $this->error("Request got bad serial mode ($mode) will use 'NEVER'.");
      	  $this->serialMode = self::NEVER;
      	}
      	break;
  	}
  	
  	return $this;
  }

  /**
   * 
   * getAutoSerialize() - fetch the serial (output) mode.
   * 
   * @return string - the parse mode (one of AUTO, ALWAYS or NEVER)
   * 
   */
  
  public function getAutoSerialize() {
  	return $this->serialMode;
  }
  
  /**
   * 
   * setAutoParse() - set the parse (input) mode.
   * 
   * @param string $mode must be one of AUTO, ALWAYS or NEVER
   * 
   * @return self
   * 
   */
  
  public function setAutoParse($mode=self::AUTO) {
  	
  	switch($mode) {
      case self::AUTO:
      case self::NEVER:
      case self::ALWAYS:
      	$this->parseMode = $mode;
      	break;
      default:
      	{
      	  $this->error("Request got bad parse mode ($mode) will use 'NEVER'.");
      	  $this->parseMode = self::NEVER;
      	}
      	break;
  	}
  	
  	return $this;
  }
  
  /**
   * 
   * getAutoParse() - fetch the parse (input) mode.
   * 
   * @return string - the parse mode (one of AUTO, ALWAYS or NEVER)
   * 
   */
  
  public function getAutoParse() {
  	return $this->parseMode;
  }
  
  /**
   * 
   * setHeader() override a specific HTTP header.  To clear
   * (remove) a header leave $value blank.
   * 
   * @param string $name
   * @param string $value
   * 
   * @return self
   * 
   */
  
  public function setHeader($name, $value="") {
  	
  	if(isset($this->headers[$name]) && empty($value)) {
      unset($this->headers[$name]);
  	  return $this;
  	}
  	
  	if(!empty($name) && !empty($value)) {
      $this->headers[$name] = $value;
  	}
  	
  	return $this;
  }
  
  /**
   * 
   * setHeaders() - set/clear the HTTP headers.
   * 
   * @param mixed $headers if you provide an array 
   * it will replace all existing headers. 
   * 
   * @return self
   * 
   */
  
  public function setHeaders($headers = array()) {
    $this->headers = $headers;
    return $this;
  }
  
  /**
   * 
   * getHeaders() - fetch the HTTP headers for this 
   * request.
   * 
   * @return array the array of HTTP headers.
   * 
   */
  
  public function getHeaders() {
  	return $this->headers;
  }
  
  /**
   * 
   * setVerb() - set the verb (method) of the request.
   * 
   * @param string $verb must be a valid HTTP method, one of
   * GET, PUT, POST, DELETE, HEAD, OPTIONS, TRACE, ConNECT, PATCH
   * 
   * @return self
   * 
   */
  
  public function setVerb($verb="GET") {
  	
  	$verb = strtoupper(trim($verb));
  	
  	switch($verb) {
      case 'GET':
      case 'PUT':
      case 'POST':
      case 'PATCH':
      case 'CONNECT':
      case 'HEAD':
      case 'OPTIONS':
      case 'DELETE':
      case 'TRACE':
      	$this->verb = $verb;
      	break;
      default:
      	{
          $this->error("Request setting bad verb: $verb, will use 'GET'.");
      	  $This->verb = "GET";
      	}
  	}
  	
  	return $this;
  }
  
  /**
   * 
   * getVerb() - fetch the verb (method) of this request.
   * 
   * @return string the request verb.
   * 
   */
  
  public function getVerb() {
    return $this->verb;
  }
  
  /**
   * 
   * setURL() - set/clear the URL for this request.  If you
   * provide a string URL it will be converted to a proper URL
   * object.  Otherwise you should provide an existing URL 
   * object.
   * 
   * @param mixed $url
   * 
   * @return self;
   * 
   */
  
  public function setURL($url=null) {
  	
    if($url !== null) {
    	
  	  if(!is_object($url)) {
  	    $url = littlemdesign_web_http_URL::create($url);
      }
  	  $this->url = $url;
  	  
  	} else {
  	  $this->url = null;
  	}
  	
  	return $this;
  }
  
  /**
   * 
   * getURL() - fetch the URL of this request.
   * 
   * @return URL the URL object of this request.
   * 
   */
  
  public function getURL() {
    return $this->url;	
  }
  
  /**
   * 
   * setBody() - change the content of the request.
   * 
   * @param mixed $data the PHP vaiable(s) that is the content
   * of this request.
   * 
   * @param string $mime - the content type of the request 
   * and response, unless you then call setResponeType() 
   * after this call.
   * 
   * @return self
   * 
   */
  
  public function setBody($data="", $mime=null) {
  	
  	if($mime===null) {
      if(!empty($this->contentType)) {
      	$mime = $this->contentType;
      }
  	}
  	
  	$this->setContentType($mime);
  	$this->setResponseType($mime);
  	
  	$this->body = $data;
  	
  	return $this;
  }
  
  /**
   * 
   * getBody() - fetch the content of this request.
   * 
   * @return mixed the PHP variables(s) that is the request
   * content.
   * 
   */
  
  public function getBody() {
  	return $this->body;
  }
  
  /**
   * 
   * setUser() - set the user name to use for authentication.
   * 
   * @param string $user the user name.
   * 
   * @return self;
   * 
   */
  
  public function setUser($user) {
    $this->username = $user;
    
    return $this;
  }
  
  /**
   * 
   * getUser() - fetch the user name
   * 
   * @return string the user name.
   * 
   */
  
  public function getUser() {
  	return $this->username;
  }
  
  /**
   * 
   * setPassword() - set the password to
   * 
   * @param string $pass the password.
   * 
   * @return self;
   * 
   */
  
  public function setPassword($pass) {
    $this->password = $pass;
    
    return $this;
  }
  
  /**
   * 
   * getPassword() - fetch the password that is being used.
   * 
   * @return string the user password.
   * 
   */
  
  public function getPassword() {
  	return $this->password;
  }
  
  /**
   * 
   * setTimeout() - set the timeout for total execution time.  To 
   * set the timeout for initially connecting to the remote server
   * use setConnTimeout().  If you want to wait forever, use 0. 
   * 
   * @param integer $seconds the number of seconds to allow to run.
   * 
   * @return self
   * 
   */
  
  public function setTimeout($seconds=60) {
    $this->timeout = intval($seconds);
    return $this;
  }
  
  /**
   * 
   * getTimeout() - fetch the timeout for remote operations. This timeout
   * is for the start to finish execution, not the time to initially
   * connect to the remote server.
   * 
   * @return integer - return the execution timeout value.
   * 
   */
  
  public function getTimeout() {
    return $this->timeout;
  }
  
  /**
   * 
   * getContentType() - fetch the MIME type of the request.
   * 
   * @return string standard MIME type.
   * 
   */
  
  public function getContentType() {
    return $this->requestType;	
  }
  
  /**
   * 
   * setContentType() - set the MIME type of the request.
   * 
   * @param string $mime standard MIME type.
   * 
   */
  
  public function setContentType($mime = null) {
  	
  	if(($mime === false)||($mime === null)||empty($mime)) {
      $this->requestType = "plain";
      return $this;
  	}
  	
    $type = littlemdesign_web_http_MIME::extToType($mime);
  	if($type === false) {
      if(littlemdesign_web_http_MIME::isTYpe($mime)) {
        $type = strtolower(trim($mime));
      }
  	}
  	
  	if($type === false) {
  	  $this->setError("Bad setContentType($mime)");
  	  $this->requestType = "plain";
      return $this;
  	}
  	
  	$this->requestType = $type;
  	
  	return $this;
  }
  
  /**
   * 
   * getResponseType() - fetch the MIME type of the request.
   * 
   * @return string standard MIME type.
   * 
   */
  
  public function getResponseType() {
    return $this->responseType;	
  }
  
  /**
   * 
   * setResponseType() - set the MIME type of the response.
   * 
   * @param string $mime standard MIME type.
   * 
   */
  
  public function setResponseType($mime = null) {
  	
  	if(($mime === false)||($mime === null)||empty($mime)) {
      $this->responseType = "plain";
      return $this;
  	}
  	
    $type = littlemdesign_web_http_MIME::extToType($mime);
  	if($type === false) {
      if(littlemdesign_web_http_MIME::isTYpe($mime)) {
        $type = strtolower(trim($mime));
      }
  	}
  	
  	if($type === false) {
  	  $this->setError("Bad setResponseType($mime)");
  	  $this->responseType = "plain";
      return $this;
  	}
  	
  	$this->respnoseType = $type;
  	
  	return $this;
  }
  
  	
  	
}


?>