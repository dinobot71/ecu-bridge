<?php

/**
 * 
 * db \ PgSQL - this is the adaptor for PostgreSQL. We use the 
 * standard PHP API for connecting to PostgreSQL.  We do not 
 * use PDO because we do not want to move to that yet.
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

autorequire('littlemdesign_db_DBConnectArgs');
autorequire('littlemdesign_util_Object');
autorequire('littlemdesign_util_Error');

/**
 * 
 * class PgSQL - adaptor for a PostgreSQL style database.
 * 
 */

class littlemdesign_db_PgSQL 
  extends littlemdesign_util_Object 
  implements littlemdesign_db_SQLDatabase {
	
  private static $dbList    = array();
  private static $tableList = array();
  private static $currentDb = null;
  
  /**
   * @var integer after doing a 'query' if we did an INSERT
   * or UPDATE, this is the auto-increment id (usually the
   * primary key of the table).  Safe to use becuase its 
   * within the connection to the database, so we don't have
   * to lock tables to know what the any "new" id is.
   * 
   */
  	
  private $lastInsertId = -1;
  
  /**
   * 
   * track the number of rows affected by the query we just
   * did.
   * 
   */
  
  private $affectedRows = -1;
  
  /**
   * 
   * @var object store the low level connection to MySQL.
   * 
   */
	
  private $link = null;
  private $dsn  = null;
  
  /**
   * 
   * @var string the client side understanding of what character
   * set we are using.
   * 
   */
  
  private $encoding = "UTF8";

  /**
   * 
   * standard constructor 
   * 
   * @param mixed dsn the DBConnectArgs object or a DSN
   * style pseudo URL (string).
   *  
   * @param object logger the kind of Logger to use for general 
   * logging.  By default we will use PHPLogger (which falls 
   * through to the web log.
   * 
   */
  
  public function __construct($dsn = null, $logger = null, $encoding="UTF8") {

  	parent::__construct('PgSQL', 'littlemdesign_db', true);
  	
    $this->link     = null;
    $this->dsn      = null;
    $this->encoding = $encoding;
    
    $this->unReady();
    
    if($dsn != null) {
    
      if(is_string($dsn)) {
    	
        /* convert to DBConnectArgs */
    	
        $obj = new littlemdesign_db_DBConnectArgs($dsn);
        $dsn = $obj;
      }
    
      /* since we have a database pseudo URL, try to connect */
      
      $this->connect($dsn);
    }
    
    $this->info("Constructed.");
  } 
  
  /**
   * 
   * standard destructor.
   * 
   */
  
  function __destruct() {
  	$this->info("Destructing...");
  	$this->disconnect();
  	$this->info("Destructed.");
  }
  
  /**
   * 
   * getType() - reutrn the kind of database.  For example LMD_MYSQL, 
   * see DBConnectArgs for more types.
   * 
   */
	
  public function getType() {
    return LMD_PGSQL;
  }
  
  /**
   * 
   * escapeLiteral() - escape a string/literal, in whatever way is
   * appropriate for this database.
   * 
   * @param $text string the text to escape
   * 
   * @return mixed the escaped string or exactly false on error.
   *
   */
  
  public function escapeLiteral($text) {
  	
    if(!$this->isReady()) {
      $this->error("can not escape, object not ready.");
      return false;
  	}
  	if(empty($text)) {
  	  return "";
  	}
  	
  	$result = pg_escape_string($this->link,$text);
  	
  	if(!is_string($result)) {
  	  $this->error("can not escape, problem with db: ".pg_last_error($this->link));
      return false;
  	}
  	
  	return $result;
  }
  
  /** 
   * 
   * getDSN() - ask a database what its network connection path
   * is (i.e. the pseudo URL we used to connect to it.
   * 
   */
	
  public function getDSN() {
    return $this->dsn;
  }
  
  /**
   * 
   * connect() - open a connection to PostgreSQL
   * 
   * @param object parms a DBConnectArgs object.  If you 
   * provide a string version of a database pseudo URL,
   * then it will be automatically converted to a DBConnectArgs
   * object.
   *  
   * @return boolean returns true on success.
   * 
   */
  
  public function connect($parms) {
  	
  	$this->info("Connecting...");
  	
  	if(is_string($parms)) {
  		
      /* try to conver to DBConnectARgs */

      $obj   = new  littlemdesign_db_DBConnectArgs($parms);
      $parms = $obj;
  		
  	}
  	
    if(!is_object($parms)||(get_class($parms)!="littlemdesign_db_DBConnectArgs")) {
      $this->error("Can not construct a valid MySQL object, no connection arguments.");
      return false;
    }
    
    $this->dsn = $parms;
    
    $this->info(". db url: ".$parms->toString());
    
    /* valid conenction args? */
    
    if(!$parms->isValid()) {
      $this->error("Can not construct a valid MySQL object, connection arguments invalid.");
      return false;   	
    }
    
    /* is the extension available? */
    
    if(!function_exists('pg_connect')) {
      $this->critical("Can not construct a valid PgSQL object, no postgres extension installed.");
      return false;
    }
    
    /* 
     * PostgreSQL automatically re-uses connections unless we tell it 
     * not to, so we tell it not to...and at a higher level we will 
     * do singltons and connection pooling etc.
     * 
     */
    
    $host   = $parms->server;
  	$db     = $parms->database;
  	$user   = $parms->user;
  	$pass   = $parms->password;
  	$port   = $parms->port;
  	$socket = $parms->socket;
  	
  	if(($db === false)||(empty($db))) {
      $db = $user;		
  	}
  	
  	if(($port === false)||($port === null)||($port < 0)) {
      $port   = 5432;
  	}
  	
  	if(($socket === false)||empty($socket)) {
      $socket = null;
  	}
    
  	$args = "host=$host port=$port dbname=$db user=$user password=$pass";
  	
  	$this->info(" . connecting to postgresql: $args ...");

    $this->link = pg_connect($args, PGSQL_CONNECT_FORCE_NEW);

    /* check the connection */
    
    if($this->link === false) {
      $this->error("Can not construct a valid PgSQL object, error from postgresql: ".pg_last_error($this->link));
      return false;    	
    }  
    
    /* 
     * make sure we have UTF-8 as the default encoding, unless the
     * user has told us to use something else.
     * 
     */
    
    pg_set_client_encoding($this->link, $this->encoding);
    $enc = pg_client_encoding($this->link);
    
    if(strtolower($enc) != strtolower($this->encoding)) {
      $this->error("Can not construct a valid PgSQL object, can not set encoding $enc != ".$this->encoding);
      return false;
    }
   
    /* all done */
  	
    $this->info(" . connected ($enc).");
    
    $this->makeReady();
    return true;
  }
  
  /**
   * 
   * disconnect() - terminate our connection with PostgreSQL
   * 
   * Note: once you disconnection this object is not usable until you 
   * connect again.
   * 
   */
  
  public function disconnect() {
  	
  	$this->info("Closing connection...");
  	
  	if(!$this->isReady()) {
  	  return true;
  	}
  	
  	/* close the connection */
  	
  	pg_close($this->link);
  	unset($this->link);
  	$this->link = null;
  	
  	$this->info(". connection closed.");
  	
  	/* no connection, so no longer usable. */
  	
  	$this->unReady();
  	return true;
  }
  
  /**
   * 
   * transform() - given an SQL statement that we know will be 
   * executed in PostgreSQL, transform it from standard to work in
   * PostgreSQL.
   * 
   * @param string $sql the sql statement
   * 
   * @return string the transformed SQL statement.  If there
   * is any kind of error in transofrming, we note the error,
   * but return the original SQL statement.
   * 
   */
  
  public function transform($sql) {
  	
  	$this->info("transofmring: $sql");
  	
  	/* META: TODO */
  	
    return $sql;
  } 
  
  /**
   * 
   * query() - given a general SQL statement that is intended 
   * to yield rows of results, do teh query and return the array
   * of rows.  return exactly false on error.
   * 
   * @param string $sql tbe SQL statement to run. 
   * 
   * @return mixed returns exactly false on failure, and an array 
   * of results on success.  SQL that doens't generate row results
   * will return an empty array on success.
   * 
   * NOTE: getting a specific sub-range of a big set of results
   * is highly specific to the database being used.  Many databases
   * don't even follow the SQL standard and implement their own
   * alternative methods.  Commonly used databases like MySQL 
   * and Postgres do support the "LIMIT" clause...but this is 
   * not standard at all. See:
   * 
   *    http://troels.arvin.dk/db/rdbms/#select-limit
   * 
   * for a comparison of SQL implementations.  Because there is 
   * such wide variance in how paged/buffered results can be 
   * fetched at the user level.  We don't make an attempt to 
   * code it here with limit/offset parameters.  If they want
   * paged results, they'll have to encode it in SQL that is 
   * aware of the database being used.
   * 
   * If we do add support for limit/offset, we can add it 
   * latter in transform(), or in the controller class above.
   * 
   */
  
  public function query($sql) {
  	
  	$results = array();
  	
  	$this->info("Doing SQL query...");
  	
  	/* are we ready to query? */
  	
    if(!$this->isReady()) {
      $this->error("Can not run query, adaptor not ready.");
      return false;   	
    }
    
    if(empty($sql)) {
      $this->error("Can not run query, no query provided.");
      return false;
    }
    
    /* do any filtering/transforming of queries */
    
    $sql = $this->transform($sql);
    
    $this->info(". doing query: $sql");
    
    /*
     * If its an "execution" of something, like INSERT or 
     * DELETE, then we expect to get a 'true'. If its a 
     * SELECT, we expect to get a result object.  Either way,
     * if it fails we expect to get 'false'.
     * 
     */
    
    $result = pg_query($this->link, $sql);
    
    $this->lastInsertId = -1;
    
  	if($result === false) {
  	  $this->error("Could not do query: $sql, PostgreSQL ERROR: ".pg_last_error($this->link));
      return false;
  	} else if($result === true) {
      return array();
  	}
  	
  	/* we also need to track the number of rows we touched. */
    
    $this->affectedRows = pg_affected_rows($result); 
  	
  	$results =  pg_fetch_all($result);
  	pg_free_result($result);
  	unset($result);
  	
  	if(!is_array($results)) {
      $results = array();
  	}
  	
  	/* figure out the id of the row we just inserted/modified */
  	
    if(preg_match('/^\s*(INSERT|REPLACE|DELETE|UPDATE)\s+/i', $sql)) {
    	
      /* 
       * we might have touched the 'id' sequence, its safe to use
       * lastval() in multi-user environments, and we aren't using 
       * threading inside our PHP scripts, so this lastval() will
       * be exactly for that SQL statement we just ran.
       * 
       */
    	
      $sql = "SELECT lastval();";
      $result = @pg_query($this->link, $sql);
    
  	  if($result === false) {
  	    
  	  	/* 
  	  	 * in some cases lastval() isn't defined yet, we just ignore
  	  	 * because in the cases where it makes sense to use the lastval()
  	  	 * it will be there.
  	  	 * 
  	  	 */
  	  	
  	  	$this->lastInsertId = -1;
  	  	
  	  } else {
  	  
  	    $row = pg_fetch_row($result);
  	    $this->lastInsertId = $row[0];
  	  }
    }
  	
  	/* all done, pass back the user friendly results */
  	
  	return $results;
  }
  
  /**
   * 
   * getLastId() - return the id of the row we just inserted/modified.
   * This is multi-user safe (its tied to the last value used within
   * the current connection).
   * 
   * @return integer the id of the last row that was inserted/modified.
   * 
   */
  
  public function getLastId() {
  	return $this->lastInsertId;
  }
  
  /**
   * 
   * getAffectedRows - return the number of rows affected by the last
   * statement we executed.
   * 
   * @return integer - number of rows touched.
   * 
   */
  
  public function getAffectedRows() {
    return $this->affectedRows;
  }
  
  /**
   * 
   * dropTable() - drop a previously created table.  The table
   * is presumed to be in the current default database. Return
   * exactly false if there is problem.
   * 
   * @param string $tableName the name of the table to drop
   * 
   * @return boolean return exactly false if there is problem
   * dropping the table.
   * 
   */
  
  public function dropTable($tableName) {
   	
    $this->info("dropping table ($tableName)...");
  	
    /* are we ready to query? */
  	
    if(!$this->isReady()) {
      $this->error("Can not drop table, adaptor not ready.");
      return false;   	
    }
    
    /* make sure we have a table name */
    
    $tableName = strtolower(trim($tableName));
    if(empty($tableName)) {
      $this->error("Can not drop table, no table name.");
      return false;   	
    }
    
    if(!$this->validTableName($tableName)) {
      $this->error("Can not drop table, ($tableName) has invalid characters. Use: /^[a-z][a-z0-9_]*$/");
      return false;
    }
    
    /* already dropped? */
    
    $tableNames = $this->listTables();
    
    if(!in_array($tableName, $tableNames)) {
      $this->info(". table already dropped.");
      return true;
    }
    
    {
      while(count(self::$tableList) != 0) {
        array_shift(self::$tableList);
      } 
    }
    
    /* do it */
    
    $sql = "DROP TABLE IF EXISTS $tableName CASCADE;";
    
    $this->info(". dropping...");
    
    $result = $this->query($sql);
    if($result === false) {
      $this->error("Can not drop table, problem running SQL: ".$this->getError());
      return false;	
    }
    
    /* all done */
    
    $this->info("dropped table ($tableName).");
    
    return true;
  }
  
  /**
   * 
   * createTable() - create a new (empty) table.  It is assumed 
   * that the database in which to create the table, is already 
   * selected.  If there is problem creating the table, exactly
   * false will be returned.
   * 
   * @param string $tableName the name of the table
   * 
   * @param array $fields an associative array that maps
   * filed name to the field definition.  Each field definition
   * is itslef an associative array that specifys the various 
   * field attributes.
   * 
   *   array(
   *     'myfield' => array(
   *       'type' => 'integer',
   *       'default' => 0
   *     ),
   *     ...
   *   )
   *     
   * For more details on the field definitions, see MySQLField, PgSQLField etc.
   * Differnet databases have different support for field types.  We abstract
   * the type systems to provide reasonable basic types at the PHP level:
   * 
   *   text      - smallish strings
   *   clob      - large text chunks
   *   blob      - large binary data
   *   integer   - integers, some databases may support signed/unsigned
   *   decimal   - traditional decimal number
   *   float     - "double"
   *   boolean   - some databases emulate this with a small integer
   *   time      - time of day only
   *   date      - date only
   *   timestamp - full juian timestamp
   *   
   * When declaring a field you must provide the field name and type,
   * but other parameters are optional, reasonable defeaults/configuration
   * will be made if you are not explicit.
   * 
   * Primary Key - all tables will have as their first column an object
   * identifier (integer) key, which is defined to be unique within the
   * table.  Virtually all tables require a primary key so we will use
   * whatever mechanism is available for auto-increment...but allow the
   * auto-increment id to be explicitly set (if desired).  
   * 
   * When modeling objects in tables, some tables may intentionally have
   * OIDs that correlate with OIDs in other tables because the objects are
   * split accross tables, hence we may need to control (sometimes) what the 
   * OID is for a given row in a table.
   * 
   * The primary key of all tables created this way will be called 'id'. 
   * In cases where we want control over primary key definition we will
   * add a custom table creation method.  This method is intended for 
   * quick/easy use, that should work for most cases.
   * 
   * @return boolean if there is an error creating the table, then
   * exactly false will be returned.
   * 
   */
  
  public function createTable($tableName, $fields) {
  	
  	$this->info("creating table ($tableName)...");
  	
  	/* bring in the MySQLField object */
  	
    autorequire('littlemdesign_db_PgSQLField');
    
    /* are we ready to query? */
  	
    if(!$this->isReady()) {
      $this->error("Can not create table, adaptor not ready.");
      return false;   	
    }
    
    /* make sure we have a table name */
    
    $tableName = strtolower(trim($tableName));
    if(empty($tableName)) {
      $this->error("Can not create table, no table name.");
      return false;   	
    }
    
    if(!$this->validTableName($tableName)) {
      $this->error("Can not create table, ($tableName) has invalid characters. Use: /^[a-z][a-z0-9_]*$/");
      return false;
    }
    
    /* check the fields... */
    
    if(!is_array($fields)||(count($fields)==0)) {
      $this->error("Can not create table, no fields.");
      return false;   
    }
    
    $this->info(". walking fields...");
    
    /* walk the fields, and build up the table declarations */
    
    $fieldDecl = "";
    
    foreach($fields as $fieldName => $attr) {
    	
      $name     = strtolower(trim($fieldName));
      $type     = strtolower(trim(isset($attr['type']) ? $attr['type'] : null));
      $length   = (isset($attr['length'])   ? $attr['length']   : null);
      $scale    = (isset($attr['scale'])    ? $attr['scale']    : null);
      $fixed    = (isset($attr['fixed'])    ? $attr['fixed']    : null);
      $unsigned = (isset($attr['unsigned']) ? $attr['unsigned'] : null);
      $notnull  = (isset($attr['notnull'])  ? $attr['notnull']  : null);
      $default  = (isset($attr['default'])  ? $attr['default']  : null);	
    	
      /* have to at least have name and type */
      
      if(empty($name)) {
        $this->error("Can not create table, a field has no name!");
        return false;
      }
      if(empty($type)) {
        $this->error("Can not create table, a field ($name) has no type!");
        return false;
      }
      
      /* figure out the decl... */
      
      $field = new littlemdesign_db_PgSQLField(
        $name,
        $type, 
        $length, 
        $unsigned, 
        $default, 
        $notnull, 
        $scale, 
        $fixed
      );
      
      $fullDecl = $field->fullDecl;
      
      if(empty($fullDecl)) {
      	$this->error("Can not create table, can not determine db type for $name:$type.");
        return false;
      }

      $fieldDecl .= ", ".$fullDecl;
    }
    
    {
      while(count(self::$tableList) != 0) {
        array_shift(self::$tableList);
      } 
    }
    
    /* add in the primary key */
    
    $fieldDecl = "id SERIAL PRIMARY KEY".$fieldDecl;
    
    /* add in any table options */
    
    /* META: TODO */
    
    /* ok, create the table! */
    
    $sql = "CREATE TABLE $tableName ($fieldDecl);";
    
    $this->info(". creating...");
    
    $result = $this->query($sql);
    if($result === false) {
      $this->error("Can not create table, problem running SQL: ".$this->getError());
      return false;	
    }
    
    /* all done */
    
    $this->info("creating table ($tableName).");
    return true;
  }
  
  /**
   * 
   * listDatabases() - list the available databases.
   * 
   * @return mixed we return exactly false if there is an error
   * otherwise return an array of database names.
   * 
   */
  
  public function listDatabases() {
  
    /* are we ready to query? */
  	
    if(!$this->isReady()) {
      $this->error("Can not list databases, adaptor not ready.");
      return false;   	
    }
    
    if(count(self::$dbList) != 0) {
      return self::$dbList;  
    }
    
    $this->info("listing databases...");
    
    $results = array();
    
    $sql = "SELECT datname FROM pg_database;";
    
    $this->info(". listing...");
    
    $result = $this->query($sql);
    if($result === false) {
      $this->error("Can not list databases, problem running SQL: ".$this->getError());
      return false;	
    }
    
    foreach($result as $idx => $attr) {
       self::$dbList[] = array_pop(array_values($attr));
    }
    
    /* all done */
    
    return self::$dbList;
  }
  
  /**
   * validDatabaseName() - return true if the given name is
   * a valid format for a database name.
   * 
   * @return boolean return true if $name is a valid format.
   * 
   */
  
  public function validDatabaseName($name) {
    if(!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
      return false;
    }
    return true;
  }
  
  /**
   * validTableName() - return true if the given name is
   * a valid format for a table name.
   * 
   * @return boolean return true if $name is a valid format.
   * 
   */
  
  public function validTableName($name) {
    if(!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
      return false;
    }
    return true;
  }
  
  /**
   * 
   * createDatabase() - create a new databased with the given 
   * name.  If the database already exists, we return true but
   * do nothing.  If there is a problem then we return exactly
   * false.
   * 
   * @param string name the name of the new database
   * 
   * @return boolean return exactly false if there was a 
   * problem.
   * 
   */
  
  public function createDatabase($name) {

  	$this->info("creating database ($name)...");
  	
    /* are we ready to query? */
  	
    if(!$this->isReady()) {
      $this->error("Can not create database, adaptor not ready.");
      return false;   	
    }
    
    /* check params */
    
    $name = strtolower(trim($name));
    
    if(empty($name)) {
      $this->error("Can not create database, no name given.");
      return false;      	
    }
    
    if(!$this->validDatabaseName($name)) {
      $this->error("Can not create database, ($name) has invalid characters. Use: /^[a-z][a-z0-9_]*$/");
      return false;
    }
    
    /* is it already there? */
    
    $dbs = $this->listDatabases();
    if(in_array($name, $dbs)) {
      $this->info(". already exists.");
      return true;
    }
    
    /* do it */
    
    $this->info(". creating...");
    
    $sql = "CREATE DATABASE $name;";
    
    $result = $this->query($sql);
    if($result === false) {
      $this->error("Can not create database, problem running SQL: ".$this->getError());
      return false;	
    }
    
    $dbs = $this->listDatabases();
    if(!in_array($name, $dbs)) {
      $this->error("Can not create database, can not confirm database was created.");
      return false;
    }
    
    /* all done */
    
    return true;
  }
  
  /**
   * 
   * dropDatabase() - drop (delete) the database with the givne
   * name.  If the database doens't exist, we return true and
   * do nothing.  If there is a problem then we return exactly 
   * false.
   * 
   * @param string name the name of the new database
   * 
   * @return boolean return exactly false if there was a 
   * problem.
   * 
   */
  
  public function dropDatabase($name) {
  	  	
  	$this->info("dropping database ($name)...");
  	
    /* are we ready to query? */
  	
    if(!$this->isReady()) {
      $this->error("Can not drop database, adaptor not ready.");
      return false;   	
    }
    
    /* check params */
    
    $name = strtolower(trim($name));
    
    if(empty($name)) {
      $this->error("Can not drop database, no name given.");
      return false;      	
    }
    
    if(!$this->validDatabaseName($name)) {
      $this->error("Can not drop database, ($name) has invalid characters. Use: /^[a-z][a-z0-9_]*$/");
      return false;
    }
    
    /* is it already gone? */
    
    $dbs = $this->listDatabases();
    if(!in_array($name, $dbs)) {
      $this->info(". already dropped.");
      return true;
    }
    
    /* do it */
    
    $this->info(". dropping...");
    
    $sql = "DROP DATABASE $name;";
    
    $result = $this->query($sql);
    if($result === false) {
      $this->error("Can not drop database, problem running SQL: ".$this->getError());
      return false;	
    }
    
    $dbs = $this->listDatabases();
    if(in_array($name, $dbs)) {
      $this->error("Can not drop database, can not confirm database was dropped.");
      return false;
    }
    
    /* all done */
    
    return true;
  }
  
  /**
   * 
   * getCurrentDatabase() - fetch the name of the currently selected
   * default database.
   * 
   * @return mixed return exactly false on error, but otherwise the name
   * of the current default database.
   * 
   */
  
  public function getCurrentDatabase() {

    if(self::$currentDb !== null) {
      return self::$currentDb;  
    }
    
  	$this->info("getting default database name...");
  	
    /* are we ready to query? */
  	
    if(!$this->isReady()) {
      $this->error("Can get default database name, adaptor not ready.");
      return false;   	
    }
    
    /* ok, fetch it */
    
    $this->info(". querying...");
    
    $sql = "SELECT current_database();";
    
    $dbs = $this->listDatabases();

    $result = $this->query($sql);
    if($result === false) {
      $this->error("Can not get default database name, problem running SQL: ".$this->getError());
      return false;	
    }
    
    /* pass it back */
    
    self::$currentDb = array_pop(array_values(array_pop($result)));
    
    return self::$currentDb;
  }
  
  /**
   * 
   * selectDatabase() - select/connect to a database, if already
   * connected to a database, then switch.  Return exactly false
   * if there is a problem.
   * 
   * @param string $name the name of the database
   * 
   * @return boolean return exactly false if there is a problem.
   * 
   */
  
  public function selectDatabase($name) {
  	
    $this->info("selecting database ($name)...");
  	
    /*
     * In postgres we can't change databases on the fly, we would 
     * have to get a new connection.  We could potentailly get
     * a cached connection on entry to every one of our database
     * methods, base don whatever virtual database is selected...
     * but this adds a lot of complexity.  
     * 
     * In addition different databases may have different security
     * models in effect.  We could presume the host and credentials
     * of the existing connection, but it may or may not work. They
     * would really have to had opened (at least once) a connection
     * to the other database so we would have another connection to
     * use.
     * 
     * If the user wants to use a different database...they should
     * do it at the application level (for now).
     * 
     */
    
    $this->error("Can not select database, postgresql can not change the database of an active connection.");
    return false;  
  }
  
    /**
   * 
   * columnsOfTable() - fetch a list of the columns in the 
   * given table.
   * 
   * @param string $name the table to get information on
   * 
   */
  
  public function columnsOfTable($name) {
  	
  	/* check inputs */
  	
  	$this->info("columns of table: $name...");
  	
  	if(empty($name)) {
      $this->error("Can not list table columns, no table name.");
  	  return false;
  	}
  	
  	if(!$this->tableExists($name)) {
  	  $this->error("Can not list table columns, no such table.");
  	  return false;
  	}
  	
  	/* do the query */
  	
    $this->info(". listing...");
    
    $sql = "select column_name,data_type from INFORMATION_SCHEMA.COLUMNS where table_name = '$name';";
    
    $result = $this->query($sql);
    if($result === false) {
      $this->error("Can not list table columns, problem running SQL: ".$this->getError());
      return false;	
    }
    
    /* harvest */
    
    $columns = array();
    
    foreach($result as $row) {
    	
      $n = "";
      $t = "";
      
      if(isset($row['column_name'])) {
        $n = $row['column_name'];
      }
      if(isset($row['data_type'])) {
        $t = $row['data_type'];
      }
      
      if(!empty($n)) {
      	$columns[$n] = $t;
      }
    }

    /* all done */
    
  	$this->info("columns done.");
  	
  	return $columns;
  }
  
  /**
   * listTables() - fetch a list of the existing tables in the 
   * given database.  Return exactly false if there is problem.
   * 
   * @param string $name the database we want to list the tables
   * for.
   * 
   * @return boolena return exactly false if there is a problem.
   * 
   * NOTE: in PostgreSQL (unlike MySQL) you can not see other databasesm
   * you can only see the database you are connected to. So if they
   * provide the name of a database that isn't the current database,
   * that's a problem.
   * 
   */
  
  public function listTables($name="") {
  	
  	$results = array();
  	
    /* are we ready to query? */
  	
    if(!$this->isReady()) {
      $this->error("Can not list tables, adaptor not ready.");
      return false;   	
    }
    
    /* check params */
    
    $name = strtolower(trim($name));
    
    if(empty($name)) {
      $name = $this->getCurrentDatabase();	
    }
    
    if(empty($name)) {
      $this->error("Can not list tables, no databse name given.");
      return false;      	
    }
    
    if(!$this->validDatabaseName($name)) {
      $this->error("Can not list tables, databse $name has invalid characters. Use: /^[a-z][a-z0-9_]*$/");
      return false;
    }
    
    if($name != $this->getCurrentDatabase()) {
      $this->error("Can not list tables, databse $name is not currently visible.");
      return false;
    }
    
    if(isset(self::$tableList[$name])) {
      if(count(self::$tableList[$name]) != 0) {
        return self::$tableList[$name];
      }
    }
    
    $this->info("listing tables in db: $name...");
    
    /* do it */
    
    $this->info(". listing...");
    
    $sql  = "SELECT table_name FROM information_schema.tables ";
    $sql .= "WHERE table_type = 'BASE TABLE' AND table_schema = 'public' "; 
    $sql .= "ORDER BY table_type, table_name;";
    
    $result = $this->query($sql);
    if($result === false) {
      $this->error("Can not list tables, problem running SQL: ".$this->getError());
      return false;	
    }
    
    if(!isset(self::$tableList[$name])) {
      self::$tableList[$name] = array();
    }
    
    foreach($result as $idx => $attr) {
      self::$tableList[$name][] = strtolower(array_pop(array_values($attr)));
    }
    
    /* pass back the list */
    
    return self::$tableList[$name];
  }
  
  /**
   * 
   * tableExists() - return exactly true if the given 
   * table exists in the current default database.
   * 
   * @param string $name the name of the table to check.
   * 
   * @return boolean return true if the table exists in
   * the current default database.
   * 
   */
  
  public function tableExists($name) {
  	
    /* are we ready to query? */
  	
    if(!$this->isReady()) {
      $this->error("Can not check table, adaptor not ready.");
      return false;   	
    }
    
    $name = strtolower(trim($name));
    
    if(!$this->validTableName($name)) {
      $this->error("Can not check table, ($name) has invalid characters. Use: /^[a-z][a-z0-9_]*$/");
      return false;
    }
    
    $tables = $this->listTables();
    if($tables === false) {
      $this->error("Can not check table ($name): ".$this->getError());
      return false;
    }
    
    if(!in_array($name, $tables)) {
      return false;
    }
    
    return true;
  }
  
  /**
   * 
   * databaseExists() - return exactly true if the given 
   * database exists.
   * 
   * @param string $name the name of the database to check.
   * 
   * @return boolean return true if the database exists.
   * 
   */
  
  public function databaseExists($name) {
  	
  	$this->info("checking database ($name)...");
  	
    /* are we ready to query? */
  	
    if(!$this->isReady()) {
      $this->error("Can not check database, adaptor not ready.");
      return false;   	
    }
    
    /* check params */
    
    $name = strtolower(trim($name));
    
    if(empty($name)) {
      $this->error("Can not check database, no name given.");
      return false;      	
    }
    
    if(!$this->validDatabaseName($name)) {
      $this->error("Can not check database, ($name) has invalid characters. Use: /^[a-z][a-z0-9_]*$/");
      return false;
    }
    
    $dbNames = $this->listDatabases();
    
    if($dbNames === false) {
      $this->error("Can not check database: ".$this->getError());
      return false;
    }
    
    if(!in_array($name, $dbNames)) {
      return false;
    }
    
    return true;
  }
  
}

?>