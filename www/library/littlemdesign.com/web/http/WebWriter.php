<?php 

/**
 * 
 * web \ http \ WebWriter - this is the super-class for the various
 * ways we can write out requests to a remote web server  and get
 * a response.  There are mainly two ways for us in PHP, eithe r
 * use sockets and write byte-by-byte, or use curl.  
 * 
 * WebWriter objects can be used on their own, but they are intended
 * to be used by higher level convenience classes that wrap the 
 * writer with logic and features for specific kinds of requests,
 * like ushing a REST API, or SOAP, etc.
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

/**
 * 
 * class WebWriter - these are methods common to all
 * ways that we want to send (write) a request to a 
 * remote web server. 
 *
 */

abstract class littlemdesign_web_http_WebWriter
  extends littlemdesign_util_Object {

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
  
  abstract public function setTimeout($seconds=60);
  
  /**
   * 
   * getTimeout() - fetch the timeout for operations. THis timeout
   * is for the start to finish execution, not the time to initially
   * connect to the remote server.
   * 
   * @return integer - reutrn the execution timeout value.
   * 
   */
  
  abstract public function getTimeout();
  
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
  
  abstract public function setConnTimeout($seconds=10);
  
  /**
   * 
   * getConnTimeout() - fetch the timeout for initially
   * connecting to the remote server.
   * 
   * @return integer - reutrn the connection timeout value.
   * 
   */
  
  abstract public function getConnTimeout();
  
  /**
   * 
   * setVerbsoe() - enable or disable verbose mode.
   * 
   * @param boolean $flag use false, null or 'f' to disable verbose mode.
   * 
   * @return self
   * 
   */
  
  abstract public function setVerbose($flag);
  
  /**
   * 
   * isVerbose() test to see if in verbose mode.
   * 
   * @return boolean will return exactly true if in verbose mode.
   * 
   */
  
  abstract public function isVerbose();
  
  /**
   * 
   * setUser() - set the user name to use for Basic Auth.
   * 
   * @param string $user the Basic Auth user name.
   * 
   * @return self;
   * 
   */
  
  abstract public function setUser($user);
  
  /**
   * 
   * getUser() - fetch the user name that is being used for
   * Basic Auth.
   * 
   * @return string the Basic Auth user name.
   * 
   */
  
  abstract public function getUser();
  
  /**
   * 
   * setPassword() - set the password to use for Basic Auth.
   * 
   * @param string $pass the Basic Auth user password.
   * 
   * @return self;
   * 
   */
  
  abstract public function setPassword($pass);
  
  /**
   * 
   * getPassword() - fetch the password that is being used for
   * Basic Auth.
   * 
   * @return string the Basic Auth user password.
   * 
   */
  
  abstract public function getPassword();
  
  /**
   * 
   * write() - send a request to the remote web server.  You can 
   * specify the method (per HTTP protocol), the URL, apply headers,
   * and the post/put content if there is one. If you don't provide a
   * URL, the existing URL will be used. 
   * 
   * All state and response data is held in this object, so you should
   * only use one object per one request.
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
   * some may still be created and sent, as needed to do the given operation.
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
  
  abstract public function write(
    $method      = "GET", 
    $url         = null, 
    $content     = '', 
    $contentType = '', 
    $headers     = array());
}

?>