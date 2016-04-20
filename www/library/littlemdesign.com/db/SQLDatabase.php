<?php 

/**
 * 
 * db \ SQLDatabase - this is the interface for SQL style
 * database adaptors; MySQL PostgreSQL etc.
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
 * interface SQLDatabase - methods common for all SQL 
 * style databases.
 *
 */

interface littlemdesign_db_SQLDatabase {
	
  /**
   * 
   * getType() - reutrn the kind of database.  For example LMD_MYSQL, 
   * see DBConnectArgs for more types.
   * 
   */
	
  public function getType();
  
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
  
  public function escapeLiteral($text);
  
  /** 
   * 
   * getDSN() - ask a database what its network connection path
   * is (i.e. the pseudo URL we used to connect to it.
   * 
   */
	
  public function getDSN();
  
  /**
   * 
   * connect() 
   * 
   * @param object parms a DBConnectArgs object.  If you 
   * provide a string version of a database pseudo URL,
   * then it will be automatically converted to a DBConnectArgs
   * object.
   *  
   * @return boolean returns true on success.
   * 
   */
	
  public function connect($parms);
  
  /**
   * 
   * disconnect() - disconnect from the current database (if we
   * are connected).  This adaptor will not be usable until you
   * connect again.
   * 
   */
  
  public function disconnect();
  
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
  
  public function selectDatabase($name);
  
  /**
   * 
   * getCurrentDatabase() - fetch the name of the currently selected
   * default database.
   * 
   * @return mixed return exactly false on error, but otherwise the name
   * of the current default database.
   * 
   */
  
  public function getCurrentDatabase();
  
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
   * is high specific to the database being used.  Many databases
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
   * latter in transform().
   * 
   */
  
  public function query($sql);
  
  /**
   * 
   * getLastId() - return the id of the row we just inserted/modified.
   * This is multi-user safe (its tied to the last value used within
   * the current connection).
   * 
   * @return integer the id of the last row that was inserted/modified.
   * 
   */
  
  public function getLastId();
  
  /**
   * 
   * getAffectedRows - return the number of rows affected by the last
   * statement we executed.
   * 
   * @return integer - number of rows touched.
   * 
   */
  
  public function getAffectedRows();
  
  /**
   * 
   * columnsOfTable() - fetch a list of the columns in the 
   * given table.
   * 
   * @param string $name the table to get information on
   * 
   */
  
  public function columnsOfTable($name);
  
  /**
   * listTables() - fetch a list of the existing tables in the 
   * given database.  Return exactly false if there is problem.
   * 
   * @param string $name the database we want to list the tables
   * for.  If you omit the database name, the tables of the 
   * current default database are fetched.
   * 
   * @return boolena return exactly false if there is a problem.
   * 
   * Note: not all databases can be seen from the current 
   * connection for some implementations.  PostgreSQL for example
   * can no see the databases outside the one it is actively 
   * connected to.
   * 
   */
  
  public function listTables($name="");
  
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
  
  public function createTable($tableName, $fields);
  
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
  
  public function dropTable($tableName);
  
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
  
  public function tableExists($name);
 
  /**
   * 
   * listDatabases() - list the available databases.
   * 
   * @return mixed we return exactly false if there is an error
   * otherwise return an array of database names.
   * 
   */
  
  public function listDatabases();
   
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
  
  public function createDatabase($name);
  
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
  
  public function dropDatabase($name);
  
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
  
  public function databaseExists($name);
  
}

?>