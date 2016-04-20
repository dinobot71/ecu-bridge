<?php 

/**
 * 
 * db \ SQLFactory - this is the factory for SQL style databases, 
 * we start with support for MySQL and PostgreSQL, but intend
 * to add adaptors for other SQL style databases.
 * 
 * In general our goal is provide a library that can easily be 
 * used withe SQL database of the user's choice.  We try to be 
 * as consistent and compatible accross databases as possible, 
 * but ultimate we don't epxect seemless migration (migrating
 * between databases is a huge effort no matter what).  So, 
 * we focus on providing consistent access to the database
 * they happen to use in a given project.
 * 
 * More importantly we want database access to be easy enough
 * that it is never a concern, we should never have to go hunting
 * for addin libraries, have to bend over backwards to work 
 * with a 3rd party model of how to use databases etc.  It 
 * should provide quick, easy, and reasonable access with low
 * learning curve.
 * 
 * For details on the actual SQL Database interface, take a look
 * at SQLDatabase
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

/* 
 * we need to parse pseudo database connection URLs
 * so that we can figure out which kind of database
 * to instantiate.
 * 
 */

autorequire('littlemdesign_db_DBConnectArgs');

/**
 * 
 * class SQLFactory - this is a factory for obtaining SQL style 
 * database adaptors to work with.
 *
 */

class littlemdesign_db_SQLFactory {
	
  /**
   * 
   * When creating managed instances of database adaptors we 
   * store them here, and reuse them when we get a 
   * request for a kind that we've already seen.
   * 
   * This is a "class" variable, not specific to any
   * instance of SQLFactory.  The other approach to doing 
   * class variable would be to use $GLOBALS[], but 
   * if we can keep things encapsulated within the
   * class, then its cleaner.
   * 
   * @var array SQLDatabase[]
   * 
   */
	
  private static $pool = array();
  
  /**
   * 
   * standard constructor is disallowed, usage should
   * only be via static methods (its a factory)
   * 
   */
  
  private function __construct() {}
  
  /**
   * 
   * connect() - In PHP scripts where you want to connect once
   * and then just re-use that connection anytime you need 
   * database related features, or objects, you should use this
   * method.  Calling this connect() once at the top of your 
   * PHP script will establish a single database connection
   * in the connection pool.  
   * 
   * Now, where you would normally pass in a DBConnectArgs 
   * object or a pseduo database connection URL, you can leave
   * it null, and most databse related functions or classes 
   * will try to use the one connection you already created.
   * This "default connection" only applies when there is one
   * and only one connection in the databse connectino pool.
   * Otherwise database related functions and classes would not
   * know which one is the intended default.  
   * 
   * This approach to managing your database connections can 
   * greatly simply your scripts, because you don't have 
   * sprikle database connection properties everywhere, and 
   * you don't have manage any database connections.  
   * 
   * But if you need to connect to more than one database, 
   * this approach doesn't apply.
   * 
   * If you call connect() and there is already at least 
   * one connection in the database connection pool, it will
   * do nothing and return true.
   * 
   * NOTE: eventually the "default" will be able to self 
   * start, we will read in connection paramters from the
   * ".ini" preferences for the application, and then the 
   * user doesn't even have to call connect(), we can auto
   * load the default connection from preferences, if it 
   * hasn't been loaded yet.
   * 
   * @param object parms a DBConnectArgs object.  If you 
   * provide a string version of a database pseudo URL,
   * then it will be automatically converted to a DBConnectArgs
   * object.
   * 
   * @return boolean return exactly false if there is a problem
   * connecting.
   * 
   */
  
  static public function connect($parms) {
  	
    if(count(self::$pool) > 0) {
      return true;
    }
    
    /*
     * $parms can't be null yet...that would imply we can 
     * autoload $parms from ".ini" application preferences,
     * that part is on the TODO list.
     *  
     */
    
    if(($parms === null)||(is_string($parms)&&empty($parms))) {
      error_log("[littlemdesign \ db \ SQLFactory] ERROR can not connect, no parms.");
      return false;
    }
    
    /*
     * ok, create a managed database adaptor as we normally would.
     * 
     */
    
    $db = self::createManaged($parms);
    if($db === false) {
      error_log("[littlemdesign \ db \ SQLFactory] ERROR can not create db.");
      return false;
    }
    
    if(!$db->isReady()) {
      error_log("[littlemdesign \ db \ SQLFactory] ERROR created db is not valid: ".$db->getError());
      return false;
    }
    
    /* if we get this far, everything is ok. */
    
    return $db;
  }
  
  /**
   * 
   * createManaged() - fetch the existing shared connection for 
   * the given pseudo database connection URL. (A cached connection)
   * 
   * If you do not provide a DBConnectArgs object or a pseudo 
   * database connection URL, then the default behavior is to 
   * try to reuse the existing connection, if there is one and
   * only one connection in the database connection pool.
   * 
   * Otherwise, if you don't provide a database to connect to,
   * and there is more than one existing connection...this 
   * method will fail (as it doesn't know which onnection to 
   * use as the default).
   * 
   * This default connection behavior is intended to be used 
   * through out the library and in the application to simplify
   * scripts.  Instead of specifying the database connection 
   * each time you want to create a database object, or use a 
   * database function, you can indicate a "null" connection,
   * and the default will be used.  But you still have the 
   * option explicitly indicating the connection...in applications
   * where multiple connections are necessary.
   * 
   * See the connect() method for more details on default 
   * connections.
   * 
   * @param object parms a DBConnectArgs object.  If you 
   * provide a string version of a database pseudo URL,
   * then it will be automatically converted to a DBConnectArgs
   * object.  You can obtain the default connection by 
   * leaving this argument null.
   *  
   * @return boolean returns an SQLDatabase on success, and 
   * exactly false on failure.
   * 
   */
	
  static public function createManaged($parms=null) {
  	
  	/* 
  	 * if they don't give us an idea of where to connect to,
  	 * but we have exactly one known connection...then use that.
  	 * 
  	 */
  	
  	if(($parms === null) && (count(self::$pool) == 1)) {
      return reset(self::$pool);
  	}
  	
  	if($parms === null) {
  	  error_log("[littlemdesign \ db \ SQLFactory] ERROR bad DSN: (null)");
  	  return false;
  	}
  	
    /* make sure we have a database pseudo URL */
  	
    if(is_string($parms)) {
    	
      /* convert to DBConnectArgs */
    	
      $obj = new littlemdesign_db_DBConnectArgs($parms);
      $parms = $obj;
    }
    
    if(!$parms->isValid()) {
      error_log("[littlemdesign \ db \ SQLFactory] ERROR bad DSN: ".$parms->toString());  	
      return false;
    }
  	
  	$signature = $parms->toString();
    
    /* do we already have one of these? */
    
    if(isset(self::$pool[$signature])) {
    
      /* yes, return an existing logger */
    	
      return self::$pool[$signature];  
    }
    
    /* we have to create a logger */
    
    $db = self::create($parms);
    if($db === false) {
      error_log("[littlemdesign \ db \ SQLFactory] ERROR createManaged(".$parms->toString().") could not instantiate an SQLDatabase.");
      return false;
    }
    
    /* ok, save it */
    
    self::$pool[$signature] = $db;
    
    /* pass it back */
    
    return self::$pool[$signature];
  }
  
  /**
   * 
   * create() - create an entirely new connection, even if there 
   * is an existing connection with this same pseudo database 
   * URL. Most users should use createManaged(), you would only
   * use this method to ensure a completely clean transation.
   * 
   * @param object parms a DBConnectArgs object.  If you 
   * provide a string version of a database pseudo URL,
   * then it will be automatically converted to a DBConnectArgs
   * object.
   *  
   * @return boolean returns an SQLDatabase on success, and 
   * exactly false on failure.
   * 
   */
  
  static public function create($parms) {
  	
  	/* make sure we have a usable database pseudo URL */
  	
  	if(($parms === false)||($parms === null)||(empty($parms))) {
      error_log("[littlemdesign \ db \ SQLFactory] ERROR create() no DSN given.");
      return false;
  	}
  	
    if(is_string($parms)) {
    	
      /* convert to DBConnectArgs */
    	
      $obj = new littlemdesign_db_DBConnectArgs($parms);
      $parms = $obj;
    }
    
    if(!$parms->isValid()) {
      error_log("[littlemdesign \ db \ SQLFactory] ERROR bad DSN: ".$parms->toString());  	
      return false;
    }
    
    $type = $parms->type;
    
    /* include the database class we need */
    
    if($type == LMD_MYSQL) {
      autorequire('littlemdesign_db_MySQL');
    } else if($type == LMD_PGSQL) {
      autorequire('littlemdesign_db_PgSQL');
    } else {
      error_log("[littlemdesign \ db \ SQLFactory] ERROR unknown databse type in DSN (".$parms.toSTring().")");
      return false;
    }
      
  	/* create the database */
  	
    $adaptor = false;
    
    if($type == LMD_MYSQL) {
    	
      $adaptor = new littlemdesign_db_MySQL($parms); 
      if(!$adaptor->isReady()) {
      	error_log("[littlemdesign \ db \ SQLFactory] ERROR problem creating db connection: ".$adaptor->getError());
      	return false;
      }
      
    } else if($type == LMD_PGSQL) {
    	
      $adaptor = new littlemdesign_db_PgSQL($parms); 
      if(!$adaptor->isReady()) {
      	error_log("[littlemdesign \ db \ SQLFactory] ERROR problem creating db connection: ".$adaptor->getError());
      	return false;
      }
      
    }
    
  	/* return it */
  	
  	return $adaptor;
  }
  
}

/* test */

/*
echo "Connecting to database...\n";

$dsn = "pgsql://rolleradmin:roller@localhost/rollermarathon";
$db  = littlemdesign_db_SQLFactory::createManaged($dsn); 
if(!$db->isReady()) {
  echo "Can not connect to database: ".$db->getError()."\n";
  exit(1);
}

$result = $db->query("SELECT * FROM books;");
if($result === false) {
  echo "can not do query: ".$db->getError()."\n";
  exit(1);
}
echo "result: ".print_r($result,true)."\n";
*/

/*
$dsn = "mysql://rolleradmin:roller@localhost/rollermarathon";
$db  = littlemdesign_db_SQLFactory::createManaged($dsn); 
if(!$db->isReady()) {
  echo "Can not connect to database: ".$db->getError()."\n";
  exit(1);
}

echo "Connected.\n";

echo "doing query...\n";

$result = $db->query("SELECT * FROM departments;");

if($result === false) {
  echo "can not do query: ".$db->getError()."\n";
  exit(1);
}
echo "result: ".print_r($result,true)."\n";

*/

/*
echo "Connecting to database...\n";

$dsn = "pgsql://rolleradmin:roller@localhost/rollermarathon";
$db1  = littlemdesign_db_SQLFactory::createManaged($dsn); 
if(!$db1->isReady()) {
  echo "Can not connect to database: ".$db1->getError()."\n";
  exit(1);
}

echo "db1: ".print_r($db1,true)."\n";

$dsn = "pgsql://rolleradmin:roller@localhost/rollermarathon";
$db2 = littlemdesign_db_SQLFactory::createManaged($dsn); 
if(!$db2->isReady()) {
  echo "Can not connect to database: ".$db2->getError()."\n";
  exit(1);
}

echo "db2: ".print_r($db2,true)."\n";
*/

?>