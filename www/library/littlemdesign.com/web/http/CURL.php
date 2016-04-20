<?php 

/**
 * 
 * web \ http \ CURL - adapter to send a request to a 
 * remote web server via the built in cURL library in
 * PHP. 
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

autorequire('littlemdesign_web_http_WebWriter');
autorequire('littlemdesign_web_http_URL');
autorequire('littlemdesign_util_Object');
autorequire('littlemdesign_util_Error');

/**
 * 
 * class CURL - send web server messages using
 * the built in cURL library. 
 *
 */

class littlemdesign_web_http_CURL 
  extends littlemdesign_web_http_WebWriter  {
  	
  /**
   * 
   * The URL we are writing to.
   * 
   * @var URL
   * 
   */
  	
  private $url = null;
  
  /*
   * track the stats of transfer times etc.
   * 
   */
  
  private $stats = array();
  
  /* cURL parameters */
  
  private $bufferSize  = 65536;
  private $verbose     = false;
  private $cookieFile  = null;
  private $timeout     = 60;     /* use 0 to wait forever */
  private $connTimeout = 10;     /* total execution time allowed */
  private $user        = '';
  private $password    = '';
  
  /* curl option names */
  
  static public $optionNames = array(
    CURLOPT_BINARYTRANSFER => 'CURLOPT_BINARYTRANSFER',
    CURLOPT_BUFFERSIZE     => 'CURLOPT_BUFFERSIZE',
    CURLOPT_CONNECTTIMEOUT => 'CURLOPT_CONNECTTIMEOUT',
    CURLOPT_COOKIEFILE     => 'CURLOPT_COOKIEFILE',
    CURLOPT_COOKIEJAR      => 'CURLOPT_COOKIEJAR',
    CURLOPT_CUSTOMREQUEST  => 'CURLOPT_CUSTOMREQUEST',
    CURLOPT_ENCODING       => 'CURLOPT_ENCODING',
    CURLOPT_FOLLOWLOCATION => 'CURLOPT_FOLLOWLOCATION',
    CURLOPT_HEADER         => 'CURLOPT_HEADER',
    CURLOPT_HTTPAUTH       => 'CURLOPT_HTTPAUTH',
    CURLOPT_HTTPGET        => 'CURLOPT_HTTPGET',
    CURLOPT_HTTPHEADER     => 'CURLOPT_HTTPHEADER',
    CURLOPT_INFILE         => 'CURLOPT_INFILE',
    CURLOPT_INFILESIZE     => 'CURLOPT_INFILESIZE',
    CURLOPT_POST           => 'CURLOPT_POST',
    CURLOPT_POSTFIELDS     => 'CURLOPT_POSTFIELDS',
    CURLOPT_PUT            => 'CURLOPT_PUT',
    CURLOPT_RETURNTRANSFER => 'CURLOPT_RETURNTRANSFER',
    CURLOPT_SSL_VERIFYHOST => 'CURLOPT_SSL_VERIFYHOST',
    CURLOPT_SSL_VERIFYPEER => 'CURLOPT_SSL_VERIFYPEER',
    CURLOPT_TIMEOUT        => 'CURLOPT_TIMEOUT',
    CURLOPT_UPLOAD         => 'CURLOPT_UPLOAD',
    CURLOPT_URL            => 'CURLOPT_URL',
    CURLOPT_USERPWD        => 'CURLOPT_USERPWD',
    CURLOPT_VERBOSE        => 'CURLOPT_VERBOSE'
  ); 
  
  /**
   * 
   * Standard constructor, initialize but don't actually do 
   * anything.
   * 
   * @param URL $url - the URL object that specifyes the remote
   * server to connect to.  If you provide a normal string based
   * URL, then it will be converted to a URL object.
   * 
   * @param LogWriter $logger - optionally re-direct logging.
   * 
   */
  
  public function __construct($url = null, $logger = null) {

  	parent::__construct('CURL', 'littlemdesign_web_http', true);
  	
  	$this->unReady();
  	
  	if(!function_exists("curl_init")) {
      $this->error("Can not construct a CURL object, that extension is not installed.");
      return ;
  	}
  	
  	/* set the connection URL */
  	
  	if($url !== null) {
  	  if(!is_object($url)) {
  	    $url = littlemdesign_web_http_URL::create($url);
      }
  	  $this->url = $url;
  	}
  	
  	/* make sure the curl extention is installed */
  	
  	$this->makeReady();
  	
  	$this->info("Constructed.");
  }	
  
  /**
   * 
   * getOptionName() fetch the string name for the given
   * curl option code.
   * 
   * @param integer $code the option code.
   * 
   * @return string the curl option name or exactly false
   * if not found.
   * 
   */
  
  static public function getOptionName($code) {

  	if(!isset(self::$optionNames[$code])) {
      return false;
  	}
  	
  	return self::$optionNames[$code];
  }
  
  /**
   * 
   * setTimeout() - set the timeout for total execution time.  To 
   * set the timeout for initially connecting to the remote server
   * use setConnTimeout().  If you want to wait forever, use 0. 
   * 
   * @param integer $seconds the number of seconds to allow cURL 
   * to run.
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
   * getTimeout() - fetch the timeout for cURL operations. THis timeout
   * is for the start to finish execution of curl, not the time to initially
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
   * setConnTimeout() - set the timeout for initially connecting to 
   * the remote server.  To set the timeout for execution time, use 
   * setTimeout().  If you want to wait forever, use 0. 
   * 
   * @param integer $seconds the number of seconds to wait when 
   * connecting.
   * 
   * @return self
   * 
   */
  
  public function setConnTimeout($seconds=10) {
    $this->connTimeout = intval($seconds);
    return $this;
  }
  
  /**
   * 
   * getConnTimeout() - fetch the timeout for initially
   * connecting to the remote server.
   * 
   * @return integer - reutrn the connection timeout value.
   * 
   */
  
  public function getConnTimeout() {
    return $this->connTimeout;
  }
  
  /**
   * 
   * setVerbsoe() - enable or disable verbose mode in the cURL library.
   * 
   * @param boolean $flag use false, null or 'f' to disable verbose mode.
   * 
   * @return self
   * 
   */
  
  public function setVerbose($flag) {
  	if(($flag === false)||($flag === null)||(substr(strtolower($flag),0,1)=='f')) {
  	  $this->verbose = false;
  	}
  	$this->verbose = true;
  	
  	return $this;
  }
  
  /**
   * 
   * isVerbose() test to see if cURL is in verbose mode.
   * 
   * @return boolean will return exactly true if in verbose mode.
   * 
   */
  
  public function isVerbose() {
    return $this->verbose;
  }
  
  /**
   * 
   * setUser() - set the user name to use for Basic Auth.
   * 
   * @param string $user the Basic Auth user name.
   * 
   * @return self;
   * 
   */
  
  public function setUser($user) {
    $this->user = $user;
    
    return $this;
  }
  
  /**
   * 
   * getUser() - fetch the user name that is being used for
   * Basic Auth.
   * 
   * @return string the Basic Auth user name.
   * 
   */
  
  public function getUser() {
  	return $this->user;
  }
  
  /**
   * 
   * setPassword() - set the password to use for Basic Auth.
   * 
   * @param string $pass the Basic Auth user password.
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
   * getPassword() - fetch the password that is being used for
   * Basic Auth.
   * 
   * @return string the Basic Auth user password.
   * 
   */
  
  public function getPassword() {
  	return $this->password;
  }
      
  /**
   * 
   * write() - send a request to the remote web server.  You can 
   * specify the method (per HTTP protocol), the URL, apply headers,
   * and the post/put content if there is one. If you don't provide a
   * URL, the existing URL will be used. 
   * 
   * All state and response data is held in this object, so you should
   * only use one CURL object per one request.
   * 
   * @param string $method you may specify any standard HTTP method; 
   * GET, POST, PUT, DELETE, HEAD, OPTIONS, TRACE, CONNECT, PATCH.  If
   * you do not provide one, GET will be assumed.
   *
   * @param mixed $url the URL to write to, may be a normal URL string,
   * or URL object. If its not already a URL object, it will be convered
   * to one.  If you do not provide one, what is current set in this 
   * object will be used.
   * 
   * @param $content mixed, if you are passing data to POST, PUT or PATCH
   * and it should be passed directly (not a file upload), the just set 
   * $content to the string to pass. 
   * 
   * If you wish to pass form data, provide the form already encoded as a 
   * string, or pass in an array that maps form variable names to their 
   * values.  
   * 
   * If you wish upload a file with PUT, use $method=PUT and pass a file 
   * descriptor/handle as $content.  When you upload a file in this way,
   * you must provide the header Content-Length.  
   * 
   * @param string $contentType override default content type (text/html).
   * 
   * @param array $headers an associative array of HTTP header name and value.
   * You must be careful to provide proper headers.  If you provide no headers,
   * some may still be created and sent, either by cURL or as needed to do
   * the given operation.
   * 
   * @return boolean return exactly false if there is a problem, true
   * otherwise. On success we return an object that has several fields
   * which in turn expand to useful data:
   * 
   *   meta - an array of detail related to the request just made,
   *   inclues stuff like the HTTP response code etc.
   *   
   *   stats - a record of download speed etc.
   *   
   *   headers - the response headers (as an associative array)
   *   
   *   data - the actual response data
   * 
   * If there is any problem with the over-the-wire transaction, then
   * exactly false will be returned.
   * 
   */
  
  public function write(
    $method      = "GET", 
    $url         = null, 
    $content     = '', 
    $contentType = '', 
    $headers     = array(),
    $encoding    = null) {
  	
  	if(!$this->isReady()) {
      $this->error("Can note write, not ready.");
      return false;
  	}
  	
    /* check the URL */
		
    if($url === null) {
      $url = $this->url;
    }
    
    if(!is_object($url)) {
      $url = littlemdesign_web_http_URL::create($url);
    }
    
    $urlStr = $url->toString($encoding);
    if(($url === null) || empty($urlStr)) {
      $this->error("can not write no 'url'.");
      return false;
    }
    
  	/*
     * figure out what the various curl options should be,
     * this will be a merging of default options, what we 
     * need for this request, and what the security settings
     * are etc.
     * 
     * We may eventaully want to also allow the caller to 
     * pass in curl options that can override our settings  
     * so the caller has more direct control over curl.
     * 
     */  
      
    $curlOpts = array(
      CURLOPT_URL =>            $url->toString($encoding),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST =>  $method,
      CURLOPT_CONNECTTIMEOUT => $this->connTimeout,
      CURLOPT_TIMEOUT =>        $this->timeout,
      CURLOPT_SSL_VERIFYHOST => false,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HEADER =>         true,
      CURLOPT_VERBOSE =>        $this->verbose,
      CURLOPT_ENCODING =>       "",
      CURLOPT_BUFFERSIZE =>     $this->bufferSize
    );
    
    /* 
     * this cURL object might be being reused as part of
     * a session; if it is, then we will have a cookie 
     * file to use to re-enter the session.
     * 
     */
    
    if(!empty($this->cookieFile)) {
      $curlOpts[CURLOPT_COOKIEJAR]  = $this->cookieFile;
      $curlOpts[CURLOPT_COOKIEFILE] = $this->cookieFile;
    } 
    
    /* check the method */
		
    $method = strtoupper(trim($method));
    if(empty($method)) {
      $this->error("can not write, no 'method'.");
      return false;
    }
    switch($method) {
    	
      case 'GET':
      	$curlOpts[CURLOPT_HTTPGET]        = true;
      	break;
      	
      case 'POST':
      	$curlOpts[CURLOPT_BINARYTRANSFER] = true;
      	$curlOpts[CURLOPT_POST]           = true;
      	$curlOpts[CURLOPT_POSTFIELDS]     = $content;
      	break;
      	
      case 'PUT':
      	{
          /*
           * PUT requires special handling, we may be doing an 
           * upload of a stream, or they may be giving us a 
           * chunk of data (like a string) to send directly.
           * 
           * If they are giving us a stream, they must have 
           * specified the Context-Length header for us so we 
           * know the size to transfer.  
           * 
           */
      		
          $curlOpts[CURLOPT_BINARYTRANSFER] = true;
          
          /* 
           * $curlOpts[CURLOPT_PUT] = true;
           * 
           * we can't enable this, for some reason it causes cURL 
           * to send 0 bytes, maybe its an alias for UPLOAD.
           * 
           */
          
          $curlOpts[CURLOPT_CUSTOMREQUEST] = $method;
            
      	  if(is_resource($content)) {
      	  	
      	    /* its a stream */
      	  	
            $curlOpts[CURLOPT_INFILE] = $content;
            
            if(isset($headers["Content-Length"])) {
              $curlOpts[CURLOPT_INFILESIZE] = $headers["Content-Length"];
            }
            if(!isset($curlOpts[CURLOPT_INFILESIZE]) || empty($curlOpts[CURLOPT_INFILESIZE])) {
              $this->error("can not write, unknown PUT stream size.");
              return false;
            }
            
            $curlOpts[CURLOPT_UPLOAD] = true;
            
            /* 
             * make sure Content-Length isn't set, to keep
             * cURL from getting confused.  cURL needs to set
             * it, but if its already set, instead of using 
             * it...cURL fails to do the upload. So, make sure 
             * its clear.
             * 
             */
            
            unset($headers["Content-Length"]);
            
      	  } else {

            /* 
             * we're passing 'content' as is, not doing a stream
             * upload.
             * 
             * we don't alter 'content', if they give us a string,
             * we pass it as is.  If its an array, then we presume
             * they intended to pass as form data, and we use
             * http_build_query() to convert to form data.
             * 
             */

      	  	if(is_array($content)) {
      	  		
      	      $form = http_build_query($content);
      	      $curlOpts[CURLOPT_POSTFIELDS] = $form;
      	      $headers["Content-Length"] = strlen($form);
      	      
      	  	} else {
      	  		
      	  	  $curlOpts[CURLOPT_POSTFIELDS] = $content;
     
      	  	  /* 
      	  	   * if they didn't fill in Content-Lenth in the headers,
      	  	   * then fill it in for them.
      	  	   * 
      	  	   */
      	  	
      	      if(!isset($headers["Content-Length"])) {
      	  	    $headers["Content-Length"] = strlen($content);
              }
            }
      	  }
      	}
      	break;
      	
      case 'DELETE':
      	$curlOpts[CURLOPT_CUSTOMREQUEST] = $method;
        break;
        
      case 'HEAD':
      	$curlOpts[CURLOPT_CUSTOMREQUEST] = $method;
        break;
        
      case 'OPTIONS':
      	$curlOpts[CURLOPT_CUSTOMREQUEST] = $method;
        break;
        
      case 'TRACE':
      	$curlOpts[CURLOPT_CUSTOMREQUEST] = $method;
        break;
        
      case 'CONNECT':
      	$curlOpts[CURLOPT_CUSTOMREQUEST] = $method;
        break;
        
      case 'PATCH':
      	$curlOpts[CURLOPT_CUSTOMREQUEST] = $method;
      	$curlOpts[CURLOPT_POSTFIELDS] = $content;
        break;
        
      default: 
        {
          $this->error("can not write, unknown method: $method");
          return false;
        }
    }

    /*
     * If we need to modify the headers being passe din,
     * do that and *then* pass the final set of headers
     * to cURL.
     * 
     */
    
    /* make sure Content-Type is set */
    
    if(!empty($contentType)) {
      $headers["Content-Type"] = $contentType;
    }
    if(!isset($headers["Content-Type"]) || empty($headers["Content-Type"])) {
      $headers["Content-Type"] = "text/html";
    }
      
    /* make sure Accept is set */
    
    if(!isset($headers['Accept'])) {
      $headers['Accept'] = '*/*; q=0.5, text/plain; q=0.8, text/html;level=3;';
    }
    
    /* set method override if we need to */
    
    if($method=="PUT") {
      $headers["X-HTTP-Method-Override"] = $method;		  
    } 
      
    /* 
     * Authoriation; we only handle Basic Auth at this point,
     * if this object is configured with a user and password
     * then we use that.  At some point we should add 
     * an auhtoriation adapotor so that the caller can 
     * provide their own authorization method.
     * 
     * we are doing htis after the header processing in case 
     * we need to influence it with headers like Authorization.
     * 
     */
    
    if(isset($this->user) && !empty($this->user)) {
      $curlOpts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
      $curlOpts[CURLOPT_USERPWD]  = $this->user.":".$this->password;
    }
    
    $this->info(". initializing cURL...");
    
	$ch = curl_init();

	$this->info(". configuring...");
	
	foreach($curlOpts as $k =>  $v) {
	
      $this->info(". . option: ($k) ".self::getOptionName($k)." => |$v|");
      curl_setopt($ch, $k, $v);
	}
	
    /* pass the final headers to cURL */
    
    if(count($headers) != 0) {
    	
      $hdrs = array();
      
      foreach ($headers as $k => $v) {
        $hdrs[] = $k.': '.$v;
      }
            
      curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);
    }

    /* ok, all configured, lets rock! */
    
    $this->info(". executing...");
  	
    $status   = true;
    $response = curl_exec($ch);
		
	  if($response === false) {

      /* some kind of critical problem */
			
      $errorString = curl_error($ch);
      $errorNumber = curl_errno($ch);

      $status = false;
      $msg    = "Problem with cURL request ($errorNumber): $errorString";
                  
      $this->error("can not write, $msg");
			
      return false;
    }
	
    /* get any HTTP info we need before we drop the connection */
		
    $info = curl_getinfo($ch);
		
    curl_close($ch);

    /* empty (but valid empty) response? */

    if(empty($response)) {
      if((!isset($info['http_code'])) || ($info['http_code'] != "204")) {
        $this->error("can not write, unexpected empty response.");
        return false;
      }
    }

    /* update stats */
		
    $stats = (object)array(
      "url"      => $url,
      "method"   => $method,
      "time"     => $info['total_time'],
      "dnstime"  => $info['namelookup_time'],	
      "download" => $info['size_download'],  
      "speed"    => $info['speed_download']
    );
    $this->stats[] = $stats;

    /* decode and passback data */
		
  	$details = array();
    $data    = $response;
    
    /* pull the headers out of the repsonse */
	
    $rHdrs      = array();
    
    $headerSize = $info['header_size'];
    $headerStr  = substr($response, 0, $headerSize);
    $data       = substr($response, $headerSize);

    foreach(preg_split('/\r\n/', $headerStr) as $hdr) {

      if(preg_match('/^([^:]+):\s+(.*)$/', $hdr, $matches)) {
      	
      	$k = $matches[1];
      	$v = $matches[2];
      	
      	$rHdrs[$k] = $v;
      }
    }
    
    /* pass back the results */
    
    $details = (object)array(
      "meta"    => $info,
      "stats"   => $stats,
      "data"    => $data,
      "headers" => $rHdrs
    );
 
    $this->info("curl write is done.");
    
    return $details;
  }
  
}

?>