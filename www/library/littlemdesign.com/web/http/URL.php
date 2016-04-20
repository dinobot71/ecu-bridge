<?php

/**
 * 
 * web \ http \ URL - provide a wrapper for URL strings that
 * can do any convenience functions associated with a URL.
 * 
 * @package littlemdesign.com
 * 
 * @author Little m Design (Michael Garvin)
 * @copyright Copyright (c) 2013-, Littl m Design
 * 
 */

/**
 * 
 * URL - a wrapper for URL strings.
 * 
 */

class littlemdesign_web_http_URL {

  /* the encoding styles we can use */
	
  const NONE       = '';
  const RFC_3986   = 'RFC 3986';
  const FORM       = 'application/x-www-form-urlencoded';
  
  /**
   * 
   * The encoding style for the URL.
   * 
   * @var string 
   * 
   */
	
  private $encoding = self::NONE;
  
  /**
   * 
   * the separator between the URL/URI and the arguments
   * 
   */
  
  const argsSep  = '?';
  
  /**
   * 
   * The separator between whole arguments
   * 
   */
  
  const fieldSep = '&';

  /**
   * 
   * the separator between arg name and value
   * 
   */
  
  const valueSep = '=';
  
  /**
   * 
   * The pieces of a URL
   * 
   */
  
  public $scheme   = 'http';
  public $host     = null;
  public $port     = '80';
  public $username = null;
  public $password = null;
  public $path     = '';
  public $fragment = '';
  public $query    = array();

  /**
   * 
   * standard constructor, you must provide the various pieces of a URL.
   * The $query is an array that maps argument name to argument value 
   * (unencoded).  Normally users will not call this method directly; 
   * they should be using the factory method create() to obtain a new
   * URL object.
   * 
   */
  
  public function __construct(
    $host, 
    $scheme='http', 
    $username = null, 
    $password = null, 
    $port     = null, 
    $path     = null, 
    $query    = array(), 
    $fragment = '') {
      
    /* initialize */
    	
    $this->scheme   = $scheme;
    $this->host     = $host;
    $this->port     = $port;
    $this->path     = $path;
    $this->username = $username;
    $this->password = $password;
    $this->fragment = $fragment;
    
    if(is_array($query)) {
      $this->query = $query;
      ksort($this->query);
    }
    
  }
  
  /**
   * 
   * getPath() - fetch the URI path if there is
   * one.
   * 
   * @return string the URI path.
   * 
   */
  
  public function getPath() {
    return $this->path;  
  }
  
  /**
   * 
   * getFragment() - fetch the anchior (#) fragment 
   * if there is one.
   * 
   * @return string the anchor (#) fragment.
   * 
   */
  
  public function getFragment() {
    return $this->fragment;  
  }
  
  /**
   * 
   * getQuery() - fetch the query name/value pairs.
   * 
   * @return array - the query name/value pairs, if
   * there are any.  
   * 
   */
  
  public function getQuery() {
    return $this->query;  
  }
  
  /**
   * 
   * setQuery() - set or clear the query parameters 
   * 
   * @param array $query the key/value query parameters.
   * 
   */
  
  public function setQuery($query=array()) {
    $this->query = $query;
    ksort($this->query);
    return $this;
  }
  
  /**
   * 
   * mergeQuery() - add parameters of $query to the 
   * existing parameters in this URL object. 
   * 
   * @param array $query the key/value query parameters.
   * 
   */
  
  public function mergeQuery($query) {
    $this->query = array_merge($this->query, $query);
    ksort($this->query);
    return $this;
  }
  
  /**
   * 
   * setEncoding() - set the default encoding for URLs when 
   * converted to strings.  If your URL has query parameters
   * you will most likely need to encode as either web form 
   * data or RFC_3986 or FORM.  Use NONE to do no encoding at
   * all.
   * 
   * @param string $encoding the encoding to use for the 
   * actual URL to send to the remote server.  Must be NONE,
   * FORM or RFC_3986.
   * 
   * @return self
   * 
   */
  
  public function setEncoding($encoding=self::NONE) {
  	
  	switch($encoding) {
  	  case self::NONE:
  	  case self::RFC_3986:
  	  case self::FORM:
  	  	$this->encoding = $encoding;
  	  	break;
  	  				
  	  default:
  	    $this->encoding = self::NONE;
  	    break;
  	}
  	
  	/* all done */
  	
  	return $this;
  }
  
  /**
   * 
   * create() - convert a string based URL into an actual URL
   * object.
   * 
   * @param string $url the URL to convert.
   * 
   * @return mixed if the URL is not usable, return exactly false. 
   * Otherwise return a URL object.
   * 
   */
  
  static public function create($url) {
  	
  	/* 
  	 * get parts of URL, its its not parsable we can error 
  	 * out now.
  	 * 
  	 */
  	
    $parts = parse_url($url);
    if($parts === false) {
      return false;
  	}
  	
  	/* the complex part is the query arguments, pull that out */
  	
  	$query = array();
  	
  	if(isset($parts['query']) && !empty($parts['query'])) {
  	
      /* split into argument name/value pairs  */
  		
  	  $parts['query'] = ltrim($parts['query']);
  	  
      $args = explode(self::fieldSep, $parts['query']);

      /* walk the args */
     
      foreach($args as $arg) {
   
      	/* 
      	 * break the name=value argument into its parts, if its a PHP 
      	 * style array, then we build up an array for that argument's 
      	 * value.
      	 * 
      	 * We assume that the given URL is already raw encoded (special
      	 * characters are escaped with %)
      	 * 
      	 */
      	
        $pieces = explode(self::valueSep, $arg, 2);
        
        $k = rawurldecode($pieces[0]);
        
        if(count($pieces) > 1) {
        	
          if(substr($k, -2) == '[]') {
        	
          	$k = substr($k, 0, -2);
            if(!isset($query[$k])) {
              $query[$k] = array();
            }
            $query[$k][] = rawurldecode($pieces[1]);
          
          } else {
            $query[$k] = rawurldecode($pieces[1]);
          }
          
        } else {
          $query[$k] = '';
        }
        
        /* next */
      }

  	}
  	
  	/* we have everythgin we need to create a URL object */
  	
  	if(!isset($parts['host'])) {
  	  $parts['host'] = null;
  	}
  	if(!isset($parts['scheme'])) {
  	  $parts['scheme'] = null;
  	}
  	if(!isset($parts['user'])) {
  	  $parts['user'] = null;
  	}
    if(!isset($parts['pass'])) {
  	  $parts['pass'] = null;
  	}
    if(!isset($parts['port'])) {
  	  $parts['port'] = null;
  	}
    if(!isset($parts['path'])) {
      $parts['path'] = null;
  	}
    if(!isset($parts['fragment'])) {
      $parts['fragment'] = null;
  	}
  		
    $obj = new littlemdesign_web_http_URL(
      $parts['host'], 
      $parts['scheme'], 
      $parts['user'],
      $parts['pass'], 
      $parts['port'], 
      $parts['path'], 
      $query,
      $parts['fragment']);
      
    /* pass it back */
      
    return $obj;
  }
  
  /**
   * 
   * isAbsolute() - check to see if this is an absolute path or 
   * relative to the document root.
   * 
   * @return boolean return true if this is an absolute URL 
   * which includes a server name.
   * 
   */
  
  public function isAbsolute() {
  	
  	if(!empty($this->scheme) && !empty($this->host)) {
      return true;
  	}
  	
  	return false;
  }
  
  /**
   * 
   * percentEncode() - helper function to encode given 
   * text per RFC 3986.  In PHP this means calling rawurlencode().
   * 
   * @param string $text text to encode
   * 
   * @return string encoded text.
   * 
   */
  
  public static function percentEncode($text) {
    return rawurlencode($text);
  }
  
  /**
   * 
   * encodedQuery() - fetch a copy of the query parameters encoded 
   * for use in a URL.  You can specify what kind of encoding to 
   * use; NONE, RFC_3986 or FORM.  Normally users won't call this
   * directly, they would instead use toString() on the URL as a 
   * whole.
   * 
   * @param string $encoding the encoding style to use, must be 
   * one of NONE, RFC_3986 or FORM.
   * 
   * @return string return a string version of the query 
   * parameters, encoded as requested.
   * 
   */
  
  public function encodedQuery($encoding=self::NONE) {
  	
    /* encode the query aregs */

  	$args = array();
  	
  	foreach($this->query as $k => $v) {

      if($encoding == self::RFC_3986) {
  		$args[rawurlencode($k)] = rawurlencode($v);	
      } else if($encoding == self::FORM) {
      	$args[urlencode($k)] = urlencode($v);
      } else {
      	$args[$k] = $v;
      }
  	}
  
  	/* flatten into a string */
  	
  	$result = "";
  	
  	foreach($args as $k => $v) {
  		
  	  /* 
  	   * array values have to be flattened into occurring
  	   * multiple times in the query.
  	   * 
  	   */
  		
  	  if($v === null) {
  	  	$v = array("");
  	  } else if(!is_array($v)) {
  	  	$v = array($v);
  	  }
  	  
      foreach($v as $value) {
      	
        $result .= self::fieldSep;
        
        $result .= $k;
        $result .= self::valueSep.$value;
      }
  	}
  	$result = ltrim($result, self::fieldSep);
  	
    /* all done */
  	
  	return $result;
  }
  
  /**
   * 
   * getURI() - similar to toString() convert back to
   * a normal path, but only the URI part.
   * 
   * @return string - the request URI.
   * 
   */
  
  public function getURI($encoding=null) {
    
    $uri = "";
    
    /* path */
    
    if(!empty($this->path)) {
      if($this->path[0] != '/') {
        $uri .= "/";
      }
      $uri .= $this->path;
    }

    /* query */
    
    if(count($this->query) != 0) {

      $uri .= self::argsSep;
      $uri .= $this->encodedQuery($encoding);

    }
    
    /* fragment */

    if(!empty($this->fragment)) {
      $uri .= "#".$this->fragment;
    }
    
    return $uri;
  }
  
  /**
   * toString() - convert back to a string.
   * 
   * @param constant $encoding - one of NONE, RFC_3986,
   * FORM or null (to use the default).  Encoding
   * is used to determine how to render the query 
   * parameters (if there are any).
   * 
   * @return string the string form of the URL.
   * 
   */
  
  public function toString($encoding=null) {
  	
  	if($encoding === null) {
  	  $encoding = $this->encoding;	
  	}
  	
    /*
     * Build up the URL based on what we have, not all pieces
     * are required to form a valid URL.
     * 
     * Note that scheme and host are optional, because it might 
     * be a URL relative to the document root.
     * 
     */

    $url = "";
    if(!empty($this->scheme)) {
      $url .= $this->scheme.":";
    }

    /* host spec */
    
    if(!empty($this->host)) {
    	
      $url .= "//";
      
      if(!empty($this->username)) {
      	
        $url .= $this->username;

        if(!empty($this->password)) {
          $url .= ":".$this->password;
        }
        
        $url .= "@";
      }
      
      $url .= $this->host;
    }
        
    /* port */
    
    if(!empty($this->port)) {
     
      $isDefault = false;
      
      if(($this->scheme == "http")&&($this->port == 80))  {
      	$isDefault = true;
      }
      if(($this->scheme == "https")&&($this->port == 443))  {
      	$isDefault = true;
      }
      
      if(!$isDefault) {
      	$url .= ":".$this->port;
      }
    }  

    /* path */
    
    if(!empty($this->path)) {
      if($this->path[0] != '/') {
        $url .= "/";
      }
      $url .= $this->path;
    }

    /* query */
    
    if(count($this->query) != 0) {

      $url .= self::argsSep;
      $url .= $this->encodedQuery($encoding);

    }
    
    /* fragment */

    if(!empty($this->fragment)) {
      $url .= "#".$this->fragment;
    }

    /* all done */

    return $url;
  }
  
  /**
   * normalize() do any clean up of this URL and return a new/separate
   * URL object that is cleaned up.  We do not modify the original.
   * 
   * @return object return the cleaned up URL object.
   * 
   */
  
  public function normalize() {

  	$url = clone $this;
  	
  	/* scheme */
  	
  	$url->scheme = strtolower(trim($url->scheme));
  	
  	/* 
  	 * host, make lowecase, and if the www. version is the same IP 
  	 * as the non-www version, then use the non-www version.
  	 * 
  	 */
  	
  	$url->host = strtolower(trim($url->host));
  	
  	if(substr($url->host, 0, 4) == "www.") {
  		
  	  $h1 = $url->host;
  	  $h2 = substr($url->host, 4);
  	  
  	  if(gethostbyname($h1) == gethostbyname($h2)) {
  	  	$url->host = $h2;
  	  }
  	}

  	/* port */
  	
  	/* path */
  	
  	if(!empty($url->path)) {
  		
      /* make sure raw encoding are in upper case */
  		
  	  /* will need to change this to preg_replace_callback() in PHP 5.6 */
  		
      $url->path = preg_replace(
        '/%([0-9abcdef][0-9abcdef])/e', 
        "'%'.strtoupper('\\1')", 
        $url->path);
        
      /* reduce double / characters */
        
      while(preg_match("/\/\/|\/\.\//", $url->path)) {
        $url->path = preg_replace("/\/\/|\/\.\//", "/", $url->path);
      }
      
      /* 
       * remove dot segments
       *
       * http://www.apps.ietf.org/rfc/rfc3986.html#sec-5.2.4
       * 
       */
      
      /* 1 The input buffer is initialized with the now-appended path */
       
      $path = '';
       
      /* 2 While the input buffer is not empty, loop as follows */
       
      while(!empty($url->path)) {
       	
        if(preg_match('/^(\.\.\/|\.\/)/', $url->path)) {

       	  /* 
       	   * A If the input buffer begins with a prefix of "../" or "./",
       	   * then remove that prefix from the input buffer; otherwise, 
       	   * 
       	   */
       	
       	  $url->path = preg_replace('/^(\.\.\/|\.\/)/', '', $url->path);
       	 
        } else if(preg_match('/^(\/\.\/)/', $url->path, $matches) || preg_match('/^(\/\.)$/', $url->path, $matches)) {
       
          /* 
           * B if the input buffer begins with a prefix of "/./" or "/.",
           * where "." is a complete path segment, then replace that prefix 
           * with "/" in the input buffer; otherwise, 
           * 
           */
       	    
          $url->path = preg_replace("/^".preg_quote($matches[1],'/').'/', '/', $url->path);
         
        } else if(preg_match('/^(\/\.\.\/|\/\.\.)/', $url->path, $matches)) {
       	
          /*
       	   * if the input buffer begins with a prefix of "/../" or "/..",
           * where ".." is a complete path segment, then replace that prefix with "/" 
           * in the input buffer and remove the last segment and its preceding "/" (if any)
           * from the output buffer; otherwise, 
           *
       	   */
       	
          $url->path = preg_replace('/^'.preg_quote($matches[1], '/').'/', '/', $url->path);
          $path = preg_replace('/\/([^\/]+)$/', '', $path);
         
        } else if(preg_match('/^(\.|\.\.)$/', $url->path)) {
       	
       	  /* 
       	   * D if the input buffer consists only of "." or "..", then remove
           * that from the input buffer; otherwise,
           * 
           */
       	
          $url->path = preg_replace('/^(\.|\.\.)$/', '', $url->path);
         
  	    } else {

  	   	  /*
  	   	   * E move the first path segment in the input buffer to the end of
           * the output buffer, including the initial "/" character (if any) 
           * and any subsequent characters up to, but not including, the next 
           * "/" character or the end of the input buffer.
           * 
  	   	   */
  	   	
  	      if(preg_match('/(\/*[^\/]*)/', $url->path, $matches)) {
  	   		
            $seg = $matches[1];
            $url->path = preg_replace('/^'.preg_quote($seg, '/').'/', '', $url->path, 1);
            $path .= $seg;
          }
        }		
      
        /* next */
       
  	  } 
  	  
  	  /* replace */
  	  
  	  $url->path = $path;
  	}
  	
  	/* query */
  	
  	/* fragment */
  	
  	/* pass it back */
  	
  	return $url;
  }
  
  /**
   * 
   * getPort() - fetch port of the URL.  Fill in an appropriate 
   * default if its no explicitly set.
   * 
   * @return integer the server port.
   * 
   */
  
  public function getPort() {
  	
    if($this->port) {
      return $this->port;
    } else if ($this->scheme == 'http') {
      return 80;
    } else if ($this->scheme == 'https') {
      return 443;
    }

    return $this->port;
  }
}

?>