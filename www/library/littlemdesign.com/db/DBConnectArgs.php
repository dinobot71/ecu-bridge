<?php

/**
 * 
 * db \ DBConnectArgs - helper class for working with connection
 * parameters for databases.
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

/**
 * 
 * class DBConnectArgs - helper for connection parameters.
 * 
 */

define('LMD_MYSQL', 'mysql');
define('LMD_PGSQL', 'pgsql');

class littlemdesign_db_DBConnectArgs {
	
  /**
   * 
   * type - what kind of database it is
   * 
   * @var string
   * 
   */
	
  public $type      = "";
  
  /**
   * 
   * user - the user id to use in the database.
   * 
   * @var string
   * 
   */
  
  public $user      = "";
  
  /**
   * 
   * password - the password to use in the database.
   * 
   * @var string
   * 
   */
  
  public $password  = "";
  
  /**
   * 
   * server - the database server to connect to
   * 
   * @var string
   * 
   */
  
  public $server    = "localhost";
  
  /**
   * 
   * database - the database to actually use
   * 
   * @var string
   * 
   */
  
  public $database  = "";
  
  /**
   * 
   * port - (optional) use a non-default connection port
   * 
   * @var string
   * 
   */
  
  public $port      = -1;
  
  /**
   * 
   * socket - for local connections, allow for unix socket connections.
   * 
   * @var string
   * 
   */
  
  public $socket    = "";
  
  /**
   * 
   * default constructor, we don't require args 
   * for construction, but if they give us an 
   * MDB2 style DSN, then try to auto-fill.
   * 
   */
  
  public function __construct($dsn="") {
  	
    /* if they are trying to use a DSN auto-fill */
  	
    if(preg_match('/^([^:]+):\/\//', $dsn)) {
      $this->parseParms($dsn);
    }
  }
  
  /**
   * 
   * default destructor
   * 
   */
  
  public function __destruct() {
  	
  }
  
  /**
   * 
   * toString() - return a human readable strring representing
   * the connection parameters.
   * 
   * @return string - the connection as a pseudo URL.
   * 
   */
  
  public function toString() {
  	
  	$hostspec = "localhost";
    if(!empty($this->server)) {
      $hostspec = $this->server;
      if(!empty($this->database)) {
        $hostspec .= "/".$this->database;
      }
  	}
  	
  	$user = "";
  	if(!empty($this->user)) {
      $user = $this->user;
      if(!empty($this->password)) {
        $user .= ":".$this->password;
      }
  	}
    if(!empty($user)) {
      $user .= "@";
    }
  	
    return $this->type."://$user$hostspec";
  }
  
  /**
   * 
   * setParms() - explicitly set the parameters 
   * 
   * @param string s - the server name
   * @param string d - the database
   * @param string u - the user name
   * @param string pw - the passwod
   * @param integer p - the port
   * @param string sock - the local unix socket
   * 
   * @return boolean - true on success.
   * 
   */
  
  public function setParms($s,$d,$u,$pw,$p="-1",$sock="") {
  	
    /* if they are trying to use a DSN auto-fill */
  	
    if(preg_match('/^([^:]+):\/\//', $s)) {
      return $this->parseParms($s);	
    }

    /* set parameters normally */
  	
    $this->server   = $s;
    $this->database = $d;
    $this->user     = $u;
    $this->password = $pw;
    $this->port     = $p;
    $this->socket   = $sock;
  	
    return true;
  }
  
  /**
   * 
   * setType() - set the database type we are connecting to.
   * Must be one of:
   * 
   *   LMD_MYSQL
   *   LMD_PGSQL
   * 
   * @param string $t
   * 
   * @return boolean - true on success.
   * 
   */
  
  public function setType($t) {

    switch($t) {
      case LMD_MYSQL:
        $this->type = $t;
        break; 
      case LMD_PGSQL:
        $this->type = $t;
        break;
  	    
      default:
        return false;
    }

    return true;
  }

  /**
   * 
   * parseParms() - set the parameters from an MDB2 style
   * "DSN" URL. MDB style URLs look like:
   * 
   *   type://username:password@hostspec/database_name
   *   type://username:password@hostspec
   *   type://username@hostspec
   *   type://hostspec/database
   *   type://hostspec
   *
   * @param string locator the URL to parse
   * 
   * @return boolean - true on success.
   * 
   */
  
  public function parseParms($locator) {

    $locator = trim($locator);
  	
    $pm = array();
    
    /* find the database type */
    
    if(preg_match('/^([^:]+):\/\//', $locator, $pm)) {
      $this->type = strtolower($pm[1]);   		
    }
    
    /* do we have a user/password? */
    
    if(preg_match('/^([^:]+):\/\/([^@]+)@/', $locator, $pm)) {
      $this->type = strtolower($pm[1]);
      list($u,$p) = explode(":", $pm[2]);
      $this->user = $u;
      if(!empty($p)) {
      	$this->password = $p;
      }
    }
    
    /* do we have user, password and host? */
    
    if(preg_match('/^([^:]+):\/\/([^@]+)@([^\/]+)/', $locator, $pm)) {
      $this->type = strtolower($pm[1]);
      list($u,$p) = explode(":", $pm[2]);
      $this->user = $u;
      if(!empty($p)) {
      	$this->password = $p;
      }
      $this->server = $pm[3];
    }
    
    /* do we have user, password, host and database? */
    
    if(preg_match('/^([^:]+):\/\/([^@]+)@([^\/]+)\/(.+)\/?/', $locator, $pm)) {
      $this->type = strtolower($pm[1]);
      list($u,$p) = explode(":", $pm[2]);
      $this->user = $u;
      if(!empty($p)) {
      	$this->password = $p;
      }
      $this->server = $pm[3];
      $this->database = $pm[4];
      
    }

    /* do we have hostspec? */
    
    if(preg_match('/^([^:]+):\/\/([^\/@]+)\/?$/', $locator, $pm)) {
      $this->type = strtolower($pm[1]);
      $this->server = $pm[2];
    }
    
    /* do we have hostspec and database? */
    
    if(preg_match('/^([^:]+):\/\/([^\/@]+)\/([^@]+)\/?$/', $locator, $pm)) {
      $this->type = strtolower($pm[1]);
      $this->server = $pm[2];
      $this->database = $pm[3];
    }
    
    return $this->isValid();
  }
  
  /**
   * 
   * isValid() check to see if we have usable 
   * connection parameters.
   * 
   * Basic validity means  we have a database 
   * type and a host.  We do not require user and
   * password to be present, because we might be
   * connecting with a "local" user that has been
   * added to the local security configuraiton 
   * for the database.
   * 
   * return boolean - true on success.
   * 
   */
  
  public function isValid() {

    /* we need to be connecting to a supported database kind */
  	
    if(empty($this->type)) {
      return false;
    }

    switch($this->type) {
      case LMD_MYSQL:
        break;
      case LMD_PGSQL:
        break;
  	  	
      default:
        return false;
    }
  	
    /* they have to provide at least server */
  	
    if(empty($this->server)) {
      return false;
    }
  	  	
    return true;
  }
  
}

?>
