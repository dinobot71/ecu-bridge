<?php 

/**
 * 
 * web \ http \ WebRequest - when we receive a request from
 * a client browser, perhaps for an AJAX/REST interaction,
 * submitting a form, or browsing a page, we want to be
 * able to consistently and reliably fetch the data and
 * meta-data they are are passing to us (the server).  This
 * class handles that.
 * 
 * Note that this class is different from web\http\Request -
 * which is used for making *outgoing* requests from this
 * server to some remote server. WebRequest handles 
 * *incomming* requersts to this server.
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

/**
 * 
 * class WebRequest - handles the marching of 
 * data from a web client/browser to the server,
 * that is an *incoming* request.  These requests
 * can then be passed and used in AJAX/REST 
 * interactions, routed to pages etc.
 *
 */

class littlemdesign_web_http_WebRequest
  extends littlemdesign_util_Object {

  /**
   * 
   * The incoming request method.
   * 
   * @var string
   * 
   */
    
  private $method  = "GET";
  
  /**
   * 
   * The original request URL, we store
   * as a URL object.
   * 
   * @var URL
   * 
   */
  
  private $url     = "/";
  
  /**
   * 
   * The GET parameters if there are any, normally
   * these will be inferred from the URL.
   * 
   * @var array
   * 
   */
  
  private $get     = array();
  
  /**
   * 
   * The POST parameters, normally these come
   * rom PHP's input post data, but if it looks
   * like we are dealing with a web form, we will
   * parse the body of the content to infer these
   * parameters.
   * 
   * @var array
   * 
   */
  
  private $post    = array();
  
  /**
   * 
   * Any cookies we know about.
   * 
   * @var array
   * 
   */
  
  private $cookies = array();
  
  /**
   * 
   * The incoming request headers.
   * 
   * @var array
   * 
   */
  
  private $headers = array();
  
  /**
   * 
   * The incoming file objects.
   * 
   * @var array
   * 
   */
  
  private $files = array();
  
  /**
   * 
   * The content body; we can have
   * content on any request, but normally
   * we expect only POST and PUT to have
   * content.
   * 
   * @var string
   * 
   */
  
  private $content = "";
    
  /**
   * 
   * Try to track the client's IP address, although
   * they may be behind a proxy or spoofing.
   * 
   * var string
   * 
   */
  
  private $clientIP = "127.0.0.1";
  
  /**
   * 
   * The presumed file format of the client request.
   * 
   * @var string
   * 
   */
  
  private $format = "plain";
  
  /**
   * 
   * The HTTP protocol level in play
   * 
   * @var string
   * 
   */
  
  private $protocol = "HTTP/1.1";
  
  /**
   * 
   * standard constructor, you can call directly to
   * simulate a web request, but normally you would
   * use the factory create() method to automatically
   * create a web request from the existing scripting
   * environment.
   * 
   */
    
  public function __construct(
    $method  = "GET",
    $url     = "/",
    $get     = array(),
    $post    = array(),
    $cookies = array(),
    $headers = array(),
    $files   = array(),
    $content = "") {

    parent::__construct('WebRequest', 'littlemdesign_web_http', true);

    $this->info("WebRequest is constructing...");
    $this->unReady();

    $method = strtoupper(trim($method));
    
    switch($method) {
      case "GET":
      case "POST":
      case "PUT":
      case "DELETE":
      case "TRACE":
      case "HEAD":
      case "CONNECT":
      case "PATCH":  
        {
          $this->method = $method;
        } 
        break;
        
      default:
        {
          $this->error("Can not construct, bad method: $method");
          return ;
        }
        break;
    }
    
    $this->url = littlemdesign_web_http_URL::create($url);
    
    if($url === false) {
      $this->error("Can not construct, bad URL: $url");
      return ;
    }
    
    if(!is_array($get)) {
      $this->error("Can not construct, get array is not an array.");
      return ;
    } else {
      $this->get = $get;
    }
    
    /* 
     * If we are simulating a request, we have promote
     * the paramters from the URL to $_GET
     * 
     */
    
    $query =$this->url->getQuery();
    
    foreach($query as $k => $v) {

      if(!isset($this->get[$k])) {
        $this->get[$k] = $v;
      }
    }
    
    if(!is_array($post)) {
      $this->error("Can not construct, post array is not an array.");
      return ;
    } else {
      $this->post = $post;
    }
    
    if(!is_array($cookies)) {
      $this->error("Can not construct, cookies array is not an array.");
      return ;
    } else {
      $this->cookies = $cookies;
    }
    
    if(!is_array($headers)) {
      $this->error("Can not construct, headers array is not an array.");
      return ;
    } else {
      $this->headers = $headers;
    }
    
    if(!is_array($files)) {
      $this->error("Can not construct, files array is not an array.");
      return ;
    } else {
      $this->files = $files;
    }
    
    if(($content === false)||($content === null)) {
      $content = "";
    }
    
    $this->content = $content;
    
    /* all set */
    
    $this->makeReady();
    
    $this->info("WebRequest is ready.");
  }
  
  /**
   * 
   * getFormat() - get the file format of the client
   * request.
   * 
   */
  
  public function getFormat() {
    return $this->format;
  }
  
  /**
   * 
   * setProtocol() - set the protocol to follow.
   * 
   */
  
  public function setProtocol($proto="HTTP/1.1") {
    $this->protocol = $proto;
  }
  
  /**
   * 
   * getProtocol() - fetch the HTTP protocol level for this
   * request.
   * 
   * @return string protocol level.
   * 
   */
  
  public function getProtocol() {
    return $this->protocol;
  }
  
  /**
   * 
   * setFormat() - set the file format of this client
   * requiest.
   * 
   * @param string $format - typically plain, json, form
   * etc.
   * 
   */
  
  public function setFormat($format="plain") {
    $this->format = $format;  
  }
  
  /**
   * 
   * setClientIP() - set what we think is the client's 
   * IP address.
   * 
   * @param unknown_type $ip
   * 
   */
  
  public function setClientIP($ip) {
    $this->clientIP = trim($ip);
  }  
  
  /**
   * getClientIP() - fetch what we think was the client's
   * IP address.
   *
   * @return string the presumed IP address.
   * 
   */
  
  public function getClientIP() {
    return $this->clientIP;
  }
  
  /**
   * 
   * get() - try to look up a parameter with fallback.
   * if the given $name is an array of names then we 
   * look each (in order) until we find one we can 
   * return. If after looking for all possible names
   * we still find nothing, then we return $default.
   * 
   * The search loop is:
   * 
   *   foreach $name {
   * 
   *     is in $_GET ?
   *     
   *     is in $_POST ?
   *     
   *   }
   *   
   * We stop searching once we fine a match.  If no
   * match can be found then we return $default.
   *  
   * @param mixed $name - you can provide a parameter
   * name or an array of parameters names to look for
   * in order. We stop looking as soon as we find one.
   * 
   * @param unknown_type $default - whatever value you
   * prefer to be passed when the parameter can not be
   * found.
   * 
   * @return mixed - if we find the parameter, return 
   * its value.  If we don't, return $default.
   * 
   */
  
  public function get($name, $default=false) {
    
    if(!$this->isReady()) {
      $this->error("Can not do get($name) - object not ready.");
      return false;
    }
    
    if(!is_array($name)) {
      $name = array($name);
    }
    
    foreach($name as $key) {
      
      $key   = trim($key);
      
      $value = $this->getParameter($key);
      
      if($value === false) {
        
        $value = $this->postParameter($key);
        
        if($value === false) {
          continue;
        }
      }
      
      /* pass back the parameter's value. */
      
      return $value;
    }
    
    /* does not appear to exist */
    
    return $default;
  }
  
  /**
   * 
   * parameters() - get the request parameters.  You
   * can select the merged parameters, $_GET or $_POST
   * as needed.
   * 
   * @param string $mode - if you supply 'get' you 
   * will get the raw $_GET parameters, similarly you
   * can supply 'post' to get $_POST for this request.
   * Otherwise you get the merged result of $_GET with
   * $_POST added in (without overwriting).
   * 
   * @return array - the parameters of this request.
   * 
   */
  
  public function parameters($mode="") {
    
    if(!$this->isReady()) {
      $this->error("Can not do parameters($name) - object not ready.");
      return false;
    }
    
    $mode = strtolower(trim($mode));
    
    switch($mode) {
      
      case 'get':
        {
          return $this->get;
        }
        break;

      case 'post':
        {
          return $this->post;  
        }
        break;
        
      default: 
        {
          /*
           * merged parameters, return whatever is
           * in $_GET, and then fill in without 
           * overwriting, from $_POST
           * 
           */
          
          $parms = array();
          
          foreach($this->get as $k => $v) {
            $parms[$k] = $v;
          }
          
          foreach($this->get as $k => $v) {
            
            if(isset($parms[$k])) {
              continue;
            }
            
            $parms[$k] = $v;
          }
          
          return $parms;
          
        }
        break;
    }
    
    /* should never get here */
    
    return null;
  }
  
  /**
   * 
   * getParameter() - look up the value of the given
   * $_GET parameter, if it doesn't exist then we
   * return exactly false.
   * 
   * @param string $name - parameter name
   * 
   * @return boolean return exactly false if not found.
   * 
   */
  
  public function getParameter($name) {
    
    if(!$this->isReady()) {
      $this->error("Can not do getParameter($name) - object not ready.");
      return false;
    }
    
    $name = trim($name);
    if(!isset($this->get[$name])) {
      return false;
    }
    return $this->get[$name];
  }
  
  /**
   * 
   * postParameter() - look up the value of the given
   * $_POST parameter, if it doesn't exist then we
   * return exactly false.
   * 
   * @param string $name - parameter name
   * 
   * @return boolean return exactly false if not found.
   * 
   */
  
  public function postParameter($name) {
    
    if(!$this->isReady()) {
      $this->error("Can not do postParameter($name) - object not ready.");
      return false;
    }
    
    $name = trim($name);
    if(!isset($this->post[$name])) {
      return false;
    }
    return $this->post[$name];
  }
  
  /**
   * 
   * getMethod() - fetch the request method,  will
   * be GET, POST, etc.
   * 
   * @return string.
   * 
   */
  
  public function getMethod() {
    return $this->method;
  }
  
  /**
   * 
   * getURL() - fetch the original request URL, 
   * but as a proper URL object.
   * 
   * @return URL - the URL object for this request.
   * 
   */
  
  public function getURL() {
    return $this->url; 
  }
  
  /**
   * 
   * getCookies() get raw request cookies.
   * 
   * @return array - the cookies.
   * 
   */
  
  public function getCookies() {
    return $this->cookies;
  }
  
  /**
   * 
   * getHeaders() get raw request headers.
   * 
   * @return array - the headers.
   * 
   */
  
  public function getHeaders() {
    return $this->headers;
  }
  
  /**
   * 
   * getFiles() - fetch the details of the uploaded
   * files (if there are any).
   * 
   * @return array - the uploaded file descriptors.
   * 
   */
  
  public function getFiles() {
    return $this->files;
  }
  
  /**
   * 
   * getContent() - fetch the raw content of this
   * request.
   * 
   * @return string
   * 
   */
  
  public function getContent() {
    return $this->content;  
  }
 
  /**
   * 
   * create() - generate WebRequest object based on the
   * current scripting environment.  Essentially capture
   * whatever might be useful to know about the incomming
   * web request.
   * 
   * @return WebRequest - a new WebRequest object.
   * 
   */
  
  static public function create() {
    
    /* figure out the basic attributes */
    
    $scheme  = "http://";
    if(isset($_SERVER["HTTPS"])) {
      $scheme = "https://";
    }
    
    $host    = "localhost";
    if(isset($_SERVER["SERVER_NAME"])) {
      $host = $_SERVER["SERVER_NAME"];
    }
    
    $port    = "";
    if(isset($_SERVER["SERVER_PORT"])) {
      $port = ":".$_SERVER["SERVER_PORT"];
    }
   
    $method  = strtolower(trim($_SERVER['REQUEST_METHOD']));
    $url     = $scheme.$host.$port.$_SERVER['REQUEST_URI'];
    $headers = getallheaders();
    $get     = $_GET;
    $post    = $_POST;
    $cookies = $_COOKIE;  
    $files   = $_FILES;

    /* 
     * fetching the body content depends on the method, 
     * but actually *any* method can take a content 
     * body.
     *  
     */
    
    $content = file_get_contents('php://input');
    
    if(!empty($content) && isset($_SERVER['CONTENT_TYPE'])) {
  
      switch($_SERVER['CONTENT_TYPE']) {
      
        case "application/json":
          {
            $content = json_decode($content);
          }
          break;
        
        case "application/x-www-form-urlencoded":
          {
            parse_str($content, $tmp);
            $content = $tmp;  

            /* 
             * if we apear to have a web-form in hand, then 
             * we merge any valus we found in the content
             * into $_POST
             * 
             */
            
            foreach($content as $k => $v) {
              if(!isset($post[$k])) {
                $post[$k] = $v;
              }
            }
          }
          break;
        
        default:
          break;
      }
    }
    
    /* we now have everything we need to create a request */
    
    $request = new littlemdesign_web_http_WebRequest(
      $method,
      $url,
      $get,
      $post,
      $cookies,
      $headers,
      $files,
      $content
    );
    
    if(!$request->isReady()) {
      
      /* some kind of internal error? */
      
      return false;
    }
    
    $request->setClientIP($_SERVER["REMOTE_ADDR"]);
    
    $type   = "";
    $format = "plain";
    
    if(isset($_SERVER['CONTENT_TYPE'])) {
      $type = $_SERVER['CONTENT_TYPE'];
    } else if(isset($headers['Content-Type'])) {
      $type = $headers['Content-Type'];
    }
    
    if(!empty($type)) {
    
      $format = littlemdesign_web_http_MIME::typeToExt($type);
      if(($format === false)||($format === null)) {
        $format = "plain";
      }
    }
    
    $request->setFormat($format);
    
    /* pass back the new request */
    
    return $request;    
  }
  
  public function toString() {
    
    $method  = $this->getMethod();
    $uri     = $this->url->getURI();
    $proto   = $this->getProtocol();
    
    $header  = "";
    foreach($this->headers as $k => $v) {
      $header .= "$k: $v\r\n";  
    }
    $content = $this->getContent();
    
    $text  = $method." ".$uri." ".$proto."\r\n";
    $text .= $header;
    $text .= $content;
    
    return $text;         
  }
}
 
?>