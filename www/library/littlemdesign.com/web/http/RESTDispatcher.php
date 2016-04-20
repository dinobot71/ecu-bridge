<?php 

/**
 * 
 * web \ http \ RESTDispatcher - to make it easy to respond 
 * to Ajax/REST calls, or quickly implement a REST API, this
 * class can be used to handle incoming requests and dispatch
 * the appropriate controller and send the response 
 * automatically.
 * 
 * General Usage:
 * 
 * To use the controller you will normally place it in a file
 * such as "rest/rest.php" or "rest/api.php" etc. and then add
 * a Rewrite rule to the web server, such as:
 * 
 *    RewriteRule ^/rest/ /rest/api.php
 * 
 * So the web clients/browsers can use a URIs like:
 * 
 *    /rest/my/resource/path/resource
 *    
 * to access resources.  To hint to the resource what
 * format of output to send back, you can add to your
 * resource path a trailing ".xml" or ".json" to force
 * the output style.
 * 
 * For example:
 * 
 *   /rest/my/resource/path/resource.json
 *   
 * Would tell 'resource' to use JSON style output.  It
 * is then up to your Resource sub-class to honor the
 * style request.
 * 
 * Its then a simple matter of registerinig your resource
 * classes for the appropriate REST URI.  See the register()
 * method below.  
 * 
 * In most cases you do not even need to register your 
 * Resource classes, their file location will be automaticalliy
 * inferred from the resource path if you don't explicity
 * register a mapping.
 * 
 * Essentially your resources should inherit the Resource
 * class; which expects yoru sub-class to  define methods 
 * like get(), post() etc. Each method should return the 
 * output to send back. This dispatcher will handle sending 
 * the output (as is) back to the calling web browser/client.  
 * 
 * To actually process the incoming request, use the 
 * dispatch() method.
 * 
 * Some reading:
 * 
 *   http://www.sitepoint.com/rest-can-you-do-more-than-spell-it-1/
 * 
 * @package littlemdesign.com
 * 
 * @author Little m Design (Michael Garvin)
 * @copyright Copyright (c) 2013-, Littl m Design
 * 
 */

/* make sure we can auto-load */

{
  $DS     = "\\";  
  $INCSEP = ";";
  
  if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $DS     = "\\";
    $INCSEP = ";";
  } else {
    $DS     = "/";
    $INCSEP = ":";
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
autorequire('littlemdesign_web_http_Resource');
autorequire('littlemdesign_web_http_WebResponse');
autorequire('littlemdesign_web_http_WebRequest');

/**
 * 
 * RESTDispatcher - the controller that mapps incomming
 * REST URI requests to actual resources (classes), 
 * invokes the resource, and returns the result.
 * 
 *
 */

class littlemdesign_web_http_RESTDispatcher {
    
  /**
   * 
   * The map of routes to class names.
   * 
   * @var array
   * 
   */
    
  static $registry = array();
    
  /**
   * 
   * standard constructor is disallowed, usage should
   * only be via static methods (its a factory)
   * 
   */
  
  private function __construct() {}
  
  /**
   * 
   * loadClass() - helper function to automatically
   * bring in a Resource class if its not already 
   * loaded.
   * 
   * @param string $classSpec the class, maybe
   * prefixed with a folder path.
   * 
   * @boolean return false if we can't load.
   * 
   */
  
  static private function loadClass($classSpec) {
    
    $DS     = "\\";  
    $INCSEP = ";";
  
    if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
      $DS     = "\\";
      $INCSEP = ";";
    } else {
      $DS     = "/";
      $INCSEP = ":";
    }
   
    /*
     * ok,  we have a class name, now we need to 
     * try and load the file and instantiate the 
     * class.
     * 
     */
    
    $spec      = explode('\\', $classSpec);
    $className = array_pop($spec);
    $folder    = implode($DS, $spec);
    
    if(class_exists($className, false)) {
      return true;
    }

    /*
     * to search for the class file we look for a file 
     * with a name like:
     * 
     *   <className>.php
     *   <className>.class.php
     *   <className>.inc
     * 
     * We look in the folders:
     *
     *   <basedir>/$folder
     *  
     */
    
    $searchPaths = array(
      dirname($_SERVER["SCRIPT_FILENAME"]),
      dirname(dirname($_SERVER["SCRIPT_FILENAME"])),
      dirname(dirname(dirname($_SERVER["SCRIPT_FILENAME"])))
    );
    $searchPaths = array_merge($searchPaths, explode($INCSEP, get_include_path()));
    
    $suffixes = array(
      ".php",
      ".class.php",
      ".inc"
    );
    
    $toRequire = "";
    foreach($searchPaths as $prefix) {
      
      /* next path */
      
      foreach($suffixes as $suffix) {
        
        /* next naming style */
        
        $classFile = $prefix.$DS.$className.$suffix;
                  
        if(is_readable($classFile)) {
          
          $toRequire = $classFile; 
          break;      
        } 
        
        if(!empty($folder)) {
          
          $classFile = $prefix.$DS.$folder.$DS.$className.$suffix;
          
          if(is_readable($classFile)) {
          
            $toRequire = $classFile; 
            break;      
          }  
        }
      }
      
      if(!empty($toRequire)) {
        break;
      }
    }
    
    if(empty($toRequire)) {
    
      /* we failed to find the class */

      error_log("[littlemdesign \ web \ http \ RESTDistpatcher] ERROR - can not find class file for '$classSpec ($className)'.");
      error_log("[littlemdesign \ web \ http \ RESTDistpatcher] ERROR - search path was: ".implode($INCSEP, $searchPaths));  

      return false;
    }

    /* require it */
    
    require_once($toRequire);
    
    /* still no class? */
    
    if(!class_exists($className, false)) {
      error_log("[littlemdesign \ web \ http \ RESTDistpatcher] ERROR loaded $toRequire, but still no $className.");
      return false;
    }
    
    /* gotcha */
    
    return $className;
  }
  
  /**
   * 
   * dispatch() - for the current scripting environment build a request
   * (WebRequest) to capture the incoming web client/browser reqquest,
   * and then route it the appropriate  Resource implementing class. 
   * Return the result of executing the requested method back to the
   * web client/browser.
   * 
   * @return boolean return exactly false if something goes wrong.
   * 
   */
  
  static public function dispatch() {
 
    error_log("[littlemdesign \ web \ http \ RESTDistpatcher] starts...");
    
    /* build a new requst */
    
    $request = littlemdesign_web_http_WebRequest::create();
    
    /* try to find the matching resource */
    
    $path    = trim($request->getURL()->getPath(), '/');
    $format  = Resource::TEXT;
    $matches = array();
    
    if(preg_match('/(.*)((?:\.json)|(?:\.xml))$/i',$path, $matches)) {
      
      $type = $matches[2];
      $path = $matches[1];
      
      if(strtolower($type)==".json") {
        $format = Resource::JSON;
      } else if(strtolower($type) == ".xml") {
        $format = Resource::XML;
      }
    }
    
    /* 
     * if we are about to find out that we don't actually 
     * have anything mapped to this route, then try to
     * auto-route by converting the resoruce path into
     * the sub-folder and the resource name into a class
     * name.
     * 
     */
    
    if(!isset(self::$registry[$path])) {
      
      $p = explode('/', $path);
      $c = array_pop($p);
      $f = implode('\\', $p);
      
      $s = $f.'\\'.$c;
      
      error_log("[littlemdesign \ web \ http \ RESTDistpatcher] . auto mapping $path to $s\n");
      
      self::$registry[$path] = $s;
    }
    
    error_log("[littlemdesign \ web \ http \ RESTDistpatcher] . route: $path");
    
    if(!isset(self::$registry[$path])) {
      
      /* 404 - no found */
      
      error_log("[littlemdesign \ web \ http \ RESTDistpatcher] ERROR No such REST resource ($path)");
      
      $response = littlemdesign_web_http_WebResponse::create(
        404, 
        "No such REST resource ($path)", 
        $request
      );
      
      $response->send();
      return false;
    }
    
    $classSpec = self::$registry[$path];
 
    $className = self::loadClass($classSpec);
    
    if($className === false) {
      
      /* internal error, can't find the class we need */
      
      error_log("[littlemdesign \ web \ http \ RESTDistpatcher] ERROR Can not find registered Resource class $classSpec for $path");
      
      $response = littlemdesign_web_http_WebResponse::create(
        500, 
        "Can not find registered Resource class $classSpec for $path", 
        $request
      );
      
      $response->send();
      return false;
    }
    
    /*
     * ok, at this point we should have everything we need.
     * Try to instantiate the resource.
     * 
     */
    
    $resource = null;
    
    try {
      
      $resource = new $className;
      
    } catch(Exception $e) {
      
      /* internal error, can't find the class we need */
      
      error_log("[littlemdesign \ web \ http \ RESTDistpatcher] ERROR Can not instantiate $className of $classSpec for $path");
      
      $response = littlemdesign_web_http_WebResponse::create(
        500, 
        "Can not instantiate $className of $classSpec for $path", 
        $request
      );
      
      $response->send();
      return false;
    }
    
    /* make sure this horse has a name... */
    
    $name = $resource->getName();
    
    if(empty($name)) {
      
      /* internal error, can't find the class we need */
      
      error_log("[littlemdesign \ web \ http \ RESTDistpatcher] ERROR Resource $className is missing its name.");
       
      $response = littlemdesign_web_http_WebResponse::create(
        500, 
        "Resource $className is missing its name.", 
        $request
      );
      
      $response->send();
      return false;
    }
    
    /* does this Resource support the requested method? */
    
    if(!$resource->supports($request->getMethod())) {

      /* not supported */
      
      error_log("[littlemdesign \ web \ http \ RESTDistpatcher] Resource $className does not support method ".$request->getMethod());
       
      $response = littlemdesign_web_http_WebResponse::create(
        405, 
        "Resource $className does not support method ".$request->getMethod(), 
        $request
      );
      
      $response->send();
      return false;
    }
    
    /* ok, we're good to go! */
    
    error_log("[littlemdesign \ web \ http \ RESTDistpatcher] . configuring resource...");
    
    $resource->setRequest($request);
    $resource->setFormat($format);
    
    error_log("[littlemdesign \ web \ http \ RESTDistpatcher] . executing (".$request->getMethod().")...");
  
    $result = "";
    
    switch($request->getMethod()) {
      
      case "GET":
        {
          $result = $resource->get();
        }
        break;
        
      case "POST":
        {
          $result = $resource->post();
        }
        break;
        
      case "HEAD":
        {
          $result = $resource->head();
        }
        break;
        
      case "PUT": 
        {
          $result = $resource->put();
        }
        break;
        
      case "OPTIONS": 
        {
          $result = $resource->options();
        }
        break;
        
      case "DELETE":
        {
          $result = $resource->delete();
        }
        break;
        
      case "TRACE": 
        {
          $result = $resource->trace();
        }
        break;
        
      case "CONNECT":
        {
          $result = $resource->connect();
        }
        break;
    }
    
    error_log("[littlemdesign \ web \ http \ RESTDistpatcher] . done.");
    
    if($result === false) {
      
      /* internal error */
      
      $err = $resource->getError();
      
      error_log("[littlemdesign \ web \ http \ RESTDistpatcher] $err");
       
      $response = littlemdesign_web_http_WebResponse::create(
        500, 
        $err, 
        $request
      );
      
      $response->send();
      return false;
    }
    
    /* 
     * send back the result, all REST results are non-cachable.
     * 
     */
    
    $response = littlemdesign_web_http_WebResponse::create(200, $result, $request);
    $response->makeUnCachable();
    $response->send();
    
    /* done */
    
    return true;
  }
  
  /**
   * 
   * register() - register a class to use as the Resource
   * to service requests at the given rest resource 
   * path ($route). The $classSpec can be just a class
   * name or a sub-folder path and a class name.  When
   * the class is neded it will be auto-loaded if 
   * required. See $classSpec below for auto-loading
   * search path.
   * 
   * For details on implementing your Resource,
   * see the Resource class.
   * 
   * @param string $route - the resource path to match
   * against (the request URI). For example:
   * 
   *   rest/com/mycompany/form/edit
   * 
   * @param string $classSpec - the name or path + name
   * of the class to use.  The 'name' of your class can
   * be as you like, be the file name that will be auto-
   * loaded, to bring in the class, will be expected 
   * to have a file name like:
   * 
   *   <name>.php
   *   <name>.class.php
   *   <name>.inc
   *   
   * If your $classSpec includes a path, then that path
   * will also be used to help locate the file needed to
   * auto-load your class. For example if you $classSpec
   * is:
   * 
   *   rest\com\mycompany\form\EditImp
   *   
   * Then the class name will be taken to be 'EditImp' and
   * the sub-folder path will be 'rest\com\mycompany\form'.
   * So to locate the class file to auto-load we will look
   * for:
   * 
   *   <basedir>\EditImp.php
   *   <basedir>\EditImp.class.php
   *   <basedir>\EditImp.inc
   *   <basedir>\rest\com\mycompany\form\EditImp.php
   *   <basedir>\rest\com\mycompany\form\EditImp.class.php
   *   <basedir>\rest\com\mycompany\form\EditImp.inc
   * 
   * Stopping when we find a matching file to load. Here 
   * <basedir> is the top level directory, assumed to be
   * the current folder of the running script, a level up
   * from that, or two levels up fromn that (all will be
   * searched in turn until a match is found).
   * 
   * If you don't  provide a sub-folder as part of the class
   * spec, then  the search order for the class file  would
   * be:
   * 
   *   <basedir>\EditImp.php
   *   <basedir>\EditImp.class.php
   *   <basedir>\EditImp.inc
   * 
   * The matched file is expected to have a valid class
   * declaration for the givne class name.  
   * 
   * Auto-mapping: if you don't actually register a class
   * with a resource path, it may still be found automatically,
   * the REST resource path (minus  the resource name) will
   * be assumed to be teh sub-folder path, and the resource
   * name will be assumed to be exactly the class name.
   * 
   * In effet, you only need to register a class to a 
   * resource path, if you want the sub-folder to be different
   * from the resource path.
   * 
   * @return  boolean return exactly false if there is
   * a problem.
   * 
   */
  
  static public function register($route, $classSpec) {
    
    if(empty($route)) {
      error_log("[littlemdesign \ web \ http \ RESTDistpatcher] ERROR can not register empty route.");
      return false;
    }
    if(empty($classSpec)) {
      error_log("[littlemdesign \ web \ http \ RESTDistpatcher] ERROR can not register empty class spec.");
      return false;
    }
    
    /* clean up values */
    
    $route     = trim($route, '/');
    $classSpec = trim($classSpec, '/');
    $classSpec = trim($classSpec, '\\');
    
    error_log("[littlemdesign \ web \ http \ RESTDistpatcher] registering $route to $classSpec");
    
    /* all done */
    
    self::$registry[$route] = $classSpec;
    
    return true;
  }
  
  
  
}

?>