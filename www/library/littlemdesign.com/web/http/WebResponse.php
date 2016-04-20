<?php 

/**
 * 
 * web \ http \ WebResponse - when we receive a request from
 * a client browser, perhaps for an AJAX/REST interaction,
 * submitting a form, or browsing a page, we want to be
 * able to consistently and reliably send back an answer.
 * This class handles outputing a complete response, from
 * headers to content back to the web client or browser.
 * 
 * Note that this class is different from web\http\Response -
 * which is used for parsing *incoming* requests from other
 * web servers.  WebResponse is used to format an *outgoing*
 * response to a web client or browser (normally we expect it
 * to be a browser).
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

autorequire('littlemdesign_util_Object');
autorequire('littlemdesign_util_Error');
autorequire('littlemdesign_web_http_ContentHandler');
autorequire('littlemdesign_web_http_URL');
autorequire('littlemdesign_web_http_MIME');
autorequire('littlemdesign_web_http_WebRequest');

/**
 * 
 * class WebResponse - handles the output format
 * and sending of a complete response back to a 
 * web client or web browser.
 *
 */

class littlemdesign_web_http_WebResponse
  extends littlemdesign_util_Object {

  /**
   * 
   * The HTTP status code
   * 
   * @var string
   * 
   */
    
  private $statusCode = "200";
  
  /**
   * 
   * The HTTP status text
   * 
   * @var string
   * 
   */
  
  private $statusText = "OK";
  
  /**
   * 
   * The HTTP protocol version to use
   * 
   * @var string
   * 
   */
  
  private $protocol   = "HTTP/1.1";
  
  /**
   * 
   * The content to send back.
   * 
   * @var string
   * 
   */
  
  private $content    = "";
  
  /**
   * 
   * The response headers
   * 
   * @var array
   * 
   */
  
  private $headers    = array();
  
  /**
   * 
   * The original request.
   * 
   * @var WebRequest
   * 
   */
  
  private $request = null;
  
  /**
   * 
   * standard constructor, you can call this to simulate creating a 
   * web response, but normally you will call the factory create() 
   * method to create request via an existing Response.
   * 
   * @param string $content - the content to sent
   * 
   * @param string $status - the response status code
   * 
   * @param string $proto - the HTTP protocol version to use
   * 
   * @param array $headers - the response headers
   * 
   */
  
  public function __construct(
    $content = "", 
    $status  = "200", 
    $proto   = "HTTP/1.1", 
    $headers = array()) {

    parent::__construct('WebResponse', 'littlemdesign_web_http', true);

    $this->info("WebResponse is constructing...");
    
    $this->unReady();
    
    $this->setContent($content);
   
    if(!$this->setStatus($status)) {
      return ;
    }
    
    if(($proto != "HTTP/1.1") && ($proto != "HTTP/1.0")) {
      $this->error("Can not construct, bad HTTP protocol version: $proto");
      return ;
    }
    
    $this->protocol = $proto;
    
    if(!is_array($headers)) {
      $this->error("Can not construct, headers array is not an array.");
      return ;
    }
    
    $this->setHeaders($headers);
    
    /* all set */
    
    $this->makeReady();
    
    $this->info("WebRepsonse is ready.");
  }
  
  static public function create($status="200", $content="", $request=null) {
    
    $response = new littlemdesign_web_http_WebResponse($content, $status);
    
    $response->setRequest($request);
    
    return $response;
  }
  
  /**
   * 
   * setRequest() - link this response to the original
   * request.
   * 
   * @param WebReequest $request - the original request.
   * 
   */
  
  public function setRequest($request) {
    $this->request = $request;
  }
  
  /**
   * 
   * getRequest() - fetch the request object we are 
   * responding to.
   * 
   * @return WebRequest - the original request.
   * 
   */
  
  public function getRequest() {
    return $this->request;
  }
  
  /**
   * 
   * getContent() - fetch the content we are sending back.
   * 
   * @return string - the content
   * 
   */
  
  public function getContent() {
    return $this->content;
  }
  
  /**
   * 
   * setContent() - set the content to send back.
   * 
   * @param string $content - the content
   * 
   */
  
  public function setContent($content="") {
    
    if(($content === false)||($content === null)) {
      $content = "";
    }
    $this->content = $content;
  }
  
  /**
   * 
   * getProtocol() - fetch the HTTP protocol version to use.
   * 
   * @return string
   * 
   */
  
  public function getProtocol() {
    return $this->protocol;
  }
  
  /**
   * 
   * setProtocol() - set the HTTP protocol version to use.
   * 
   * @param $proto string the HTTP protocol version
   * 
   */
  
  public function setProtocol($proto) {
    
    if(($proto != "HTTP/1.1")&&($proto != "HTTP/1.0")) {
      $this->error("setProtocol($proto) -  bad HTTP protocol version.");
      return ;
    }
    
    $this->protocol = $proto;
  }
  
  /**
   * 
   * getHeaders() - fetch the header array.
   * 
   * @return array - the headers.
   * 
   */
  
  public function getHeaders() {
    return $this->headers;
  }
  
  /**
   * 
   * setHeader() set the value of a given header.  Use
   * the value of exactly false to remove the header.
   * 
   * @param string $name - the header name.
   * 
   * @param mixed $value - the new value.
   * 
   * @return mixed return exactly false if there is a  problem
   * otherwise return $value.
   * 
   */
  
  public function setHeader($name, $value=false) {
    
    $name = trim($name);

    if(($value === false)||($value === null)) {
      if(isset($this->headers[$name])) {
        unset($this->headers[$name]);
      }
      return true; 
    }
    
    if(!is_string($value)) {
      $this->error("setHeader($name,$value) - expecting string value.");
      return false;  
    }
    
    $this->headers[$name] = $value;
    
    /* all done */
    
    return $value;
  }
  
  /**
   * 
   * getHeader() - fetch the value of a given header.
   * 
   * @param string $name - the header name
   * 
   * @return string the header value.
   * 
   */
  
  public function getHeader($name) {
    
    $name = trim($name);
    
    if(!isset($this->headers[$name])) {
      $this->warning("getHeader($name) - no such header.");
      return false;
    }
    
    return $this->headers[$name];
  }
  
  /**
   * 
   * setHeaders() - set/replace all headers at once.
   * 
   * @param array $headers
   * 
   * @return boolean return exactly true if ok.
   * 
   */
  
  public function setHeaders($headers) {
    
    if(!is_array($headers)) {
      $this->error("setHeaders() - expecting an array!");
      return false;
    }
    
    $this->headers = $headers;
    
    return true;
  }
  
  /**
   * 
   * hasHeader() - check to see if we have the given header.
   * 
   * @param string $name - the header name
   * 
   * @return boolean return exactly true if it exists.
   * 
   */
  
  public function hasHeader($name) {
    
    $name = trim($name);
    if(isset($this->headers[$name])) {
      return true;
    }
    
    return false;
  }
  
  /**
   * 
   * removeHeader() - remove the given header.
   * 
   * @param string $name - header to remove.
   * 
   */
  
  public function removeHeader($name) {
    $this->setHeader($name, false);
  }
  
  /**
   * 
   * getStatus() - fetch the HTTP status 
   * 
   */
  
  public function getStatus() {
    return $this->status;
  }
  
  /**
   * 
   * setStatus() - set the HTTP status, by default a status
   * status message will be used you can provide exactly 
   * false to make it blank or otherwise provide a custom 
   * message.  Note that not all client will pay attention
   * to or make visible status messages.
   * 
   * @param $status string the HTTP status
   * 
   * @param $text string status message (optional)
   *
   * @return boolean return exactly false if the
   * status code is bad.
   * 
   */
  
  public function setStatus($status, $text=null) {
    
    $statusText = self::codeToText($status);
    
    if($statusText === false) {
      $this->error("setStatus($status,$text) - bad status code ($status)");
      return false;  
    }
    
    if($text === false) {
      
      $statusText = '';
      
    } else if($text !== null) {
      
      $statusText = $text;
    }
    
    $this->status     = $status;
    $this->statusText = $statusText;
    
    return true;
  }
    
  /**
   * 
   * codeToText() - fetch the standard default  status message
   * for the given HTTP status code.  
   * 
   * @return mixed return the status text, or exactly false
   * if the status code is bad.
   * 
   */
  
  static public function codeToText($status) {
    
    static $codes = Array(  
      100 => 'Continue',  
      101 => 'Switching Protocols',  
      200 => 'OK',  
      201 => 'Created',  
      202 => 'Accepted',  
      203 => 'Non-Authoritative Information',  
      204 => 'No Content',  
      205 => 'Reset Content',  
      206 => 'Partial Content',  
      300 => 'Multiple Choices',  
      301 => 'Moved Permanently',  
      302 => 'Found',  
      303 => 'See Other',  
      304 => 'Not Modified',  
      305 => 'Use Proxy',  
      306 => '(Unused)',  
      307 => 'Temporary Redirect',  
      400 => 'Bad Request',  
      401 => 'Unauthorized',  
      402 => 'Payment Required',  
      403 => 'Forbidden',  
      404 => 'Not Found',  
      405 => 'Method Not Allowed',  
      406 => 'Not Acceptable',  
      407 => 'Proxy Authentication Required',  
      408 => 'Request Timeout',  
      409 => 'Conflict',  
      410 => 'Gone',  
      411 => 'Length Required',  
      412 => 'Precondition Failed',  
      413 => 'Request Entity Too Large',  
      414 => 'Request-URI Too Long',  
      415 => 'Unsupported Media Type',  
      416 => 'Requested Range Not Satisfiable',  
      417 => 'Expectation Failed',  
      500 => 'Internal Server Error',  
      501 => 'Not Implemented',  
      502 => 'Bad Gateway',  
      503 => 'Service Unavailable',  
      504 => 'Gateway Timeout',  
      505 => 'HTTP Version Not Supported'  
    );  
    
    return (isset($codes[$status])) ? $codes[$status] : false;
  }
  
  /**
   * 
   * makeUnCachable() - if you need this response to always be "live"
   * and not cached by the browser, you can use this method to ensure
   * no caching of this response.
   * 
   */
  
  public function makeUnCachable() {
    
    $this->setHeader("Last-Modified", gmdate("D, d M Y H:i:s")." GMT");
    $this->setHeader("Cache-Control", "no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
    $this->setHeader("Pragma","no-cache");
    
    return true;
  }
  
  /**
   * toString() format as a flat string.
   * 
   * @return string the response.
   * 
   */
  
  public function toString() {
    
    $headers = "";
    foreach($this->headers as $k => $v) {
      $headers .= "$k: $v\r\n";  
    }
    
    $text  = $this->protocol." ".$this->status." ".$this->statusText."\r\n";
    $text .= $headers."\r\n";
    $text .= $this->content;
   
    return $text; 
  }
  
  /**
   * 
   * send() - send response! 
   * 
   * @return boolean - return exactly true on success.
   * 
   */
  
  public function send() {
    
    $this->info("send() - sending response...");
    
    /* send the headers, if they aren't already sent. */
    
    $headers = array();
    $content = $this->content;
    
    if(headers_sent()) {
      
      $this->warning("send() - can't set headers, headers block already sent.");
      
    } else {
      
      /* do any header fixups before we send them ... */
      
      $headers = $this->headers;
      
      /* 
       * if there is no content type, presume it from the
       * reuqest.
       * 
       */
      
      if(!isset($headers["Content-Type"])) {
        
        if($this->request !== null) {
          
          $format = $this->request->getFormat();
          $type   = littlemdesign_web_http_MIME::extToType($format);
          
          $this->info("send() - assuming content type: $type");
          
          $headers["Content-Type"] = $type;
        }
      }
      
      /* make sure we have time stamp */
      
      if(!isset($headers["Date"])) {
        $headers["Date"] = date('D, d M Y H:i:s').' GMT';
      }
      
      /* send out the status */
    
      $statusLine = $this->protocol." ".$this->status." ".$this->statusText;
      header($statusLine);
    
      /* send out the headers */
    
      foreach($headers as $k => $v) {
        header("$k: $v");  
      }
    
    }
    
    /* TODO: send cookies */
    
    /* send out the content */
    
    echo $this->content;
    
    /* all done */
    
    $tag = "";
    if($this->request !== null) {
      $tag = $this->request->getURL()->getURI();
    }
    
    $this->info("send() - response sent ($tag).");
    
    return true;
  }
}

?>