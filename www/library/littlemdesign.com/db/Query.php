<?php

/**
 * 
 * db \ Query - to hide the different ways that each database
 * follows the standard, handle some of the housework of 
 * marshelling objects in bulk etc.  we provide the Query 
 * object.  It works closely with ORMTsble to allow any 
 * model to run queries relative to itself, and leverage 
 * the schema knowledge built into ORMTable framework. 
 * 
 * Wrapping SQL with a layer of abstraction only provides a 
 * different, not better abstraction, so our focus here is not
 * on the language, instead we are trying to make sure that
 * any incompatibilities between databases are safely hidden
 * and encapsulated.  In addition we want to add convenencies
 * like handling objects in bulk and paging results etc.
 * 
 * Result hanlding is done through QueryResult, which can provide
 * results as lazy loaded objects (primary keys), full model 
 * objects, or just an associative array.
 * 
 * If you need to pull of related objects of a complex query, use
 * object output mode, the QueryResult iterator has a related()
 * method which lets you pull out related objects in the context
 * of that query, without going back to the database.
 * 
 * To control the output format you can use methods like asArray()
 * or asObjects().  Note that if you make any calls to  select() 
 * to cherry pick columns from the primary or extend()'d tables,
 * then asArray() mode is forced.
 * 
 * To obtain a Query object the factory method:
 * 
 *   Query::create('result');
 * 
 * and provide the "primary" table the query should be rooted in/focused 
 * on.  If you need a more complex query or output, you can use 
 * the extend() method to walk to other related tables (join), and
 * then include columns from other tables in where() clauses.
 * 
 * Generally when refering to columns you may refer to them 
 * by their name without a table prefix, Query will try to 
 * auto-resolve which table the column belongs to.
 * 
 * If you really need to do your own complex query, you the
 * passThrough() method to execute SQL directly.  You will 
 * get an associative array back, not a QueryResult iterator.
 * 
 * If you want to customize what columns are in the output,
 * you can use select() to cherry pick columns from the 
 * primary table or any extend()'d tables.  You can also
 * use includeTable() to tell Query to pull in a related 
 * object.  This will add time to you query so only use 
 * if you need to avoid making multiple trips back to the
 * database for related objects. 
 * 
 * Note that includeTable() objects are in the context of
 * this query only, the general relation from the primary 
 * table to that include'd table...may be completely 
 * different.
 * 
 * To create filtering, applicable to selecting, updating 
 * and deleting, you can use any of the usual methods:
 * 
 *   comment()
 *   offset()
 *   limit()
 *   where()
 *   rawWhere()
 *   distinct()
 *   groupBy()
 *   orderBy()
 *   _or()
 *   _xor()
 *   _and()
 *   _not()
 *   isFalse()
 *   isTrue()
 *   isNull()
 *   isNotNull()
 *   startGroup()
 *   endGroup()
 * 
 * These methods are all "non-terminal", they always return
 * $this (Query self), so that you can canin them together.
 * For example:
 * 
 *  $query->where('first_name', 'like', 'mike')
 *    ->where('last_name', 'regexp', '[G]arvin')
 *    ->_xor()
 *    ->where('age_class_high', '>', '30')
 *    ...
 * 
 * The terminlal query methods are:
 * 
 *   find()   - for selecting
 *   update() - for updating
 *   delete() - for deleting
 *   
 * So a complete query would look like:
 * 
 *   $query->where('first_name', 'like', 'mike')->find(1,10);
 *   
 * In this example the results are filtered to first_name 
 * matching 'mike' and the first page of 10 rows.
 * 
 * The comment filter doesn't actually filter, it can be
 * used to make log files easier to read by tagging your
 * logged SQL commands with a comment.
 * 
 * The startGroup() and endGroup() methods can be used
 * (and nested) to create explicit precedence.
 * 
 * Should you really need to provide a where expression
 * directly to the database, you can use the rawWhere()
 * method to do so.  
 * 
 * Unless you specify the relational operators _or(), 
 * _and(), _xor(), _not(), where clauses will be 
 * chained together with "AND" where appropriate.
 * 
 * Reading:
 * 
 *   Database comparisons:
 *   
 *     http://troels.arvin.dk/db/rdbms/
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
autorequire('littlemdesign_db_ORMTable');
autorequire('littlemdesign_db_DBConnectArgs');
autorequire('littlemdesign_db_SQLDatabase');
autorequire('littlemdesign_db_SQLFactory');
autorequire('littlemdesign_db_QueryResult');

/**
 * 
 * Query - our standard query wrapper to hid the details
 * of doing queries in specific databases.
 *
 */

class littlemdesign_db_Query
  extends littlemdesign_util_Object {

  const LAZY   = 1;
  const OBJECT = 2;
  const ASSOC  = 3;
  
  /**
   * 
   * Normally this is an SQL database refernece (i.e.
   * the active DB connection)
   * 
   * @var SQLDatabse
   * 
   */
  	
  protected $db;
  
  /**
   * 
   * the name of the table we are relative to.
   * 
   * @var string
   * 
   */
  
  protected $tableName;
  
  /**
   * 
   * The list of tables we are joining
   * 
   * @var array
   * 
   */
  
  protected $joins = array();
  
  /**
   * 
   * The list of columsn we need to check before
   * we can do a query.  The columns are used, but
   * we need to make sure they are from a table that
   * was included in the query.
   * 
   * @var array
   * 
   */
  
  protected $colsToCheck = array();
  
  /**
   * 
   * paging control
   * 
   */
  
  private $pageStart = -1;
  private $pageSize  = -1;
  
  /**
   * 
   * Order the output by these selected columns.
   * 
   */
  
  private $orderCols = array();
  
  /**
   * 
   * group the output by these selected columns.
   * 
   */
  
  private $groupCols = array();
  
  /**
   * 
   * The columns we are selecting, if you don't
   * select specific columsn then you get a list of
   * "this" objects (the model the query came from).
   * But if you  select specific columns from "this"
   * or other tables involved in the query, then you
   * get an associative array.
   * 
   * You can override default select behavior by 
   * calling outputFormat($kind), $kind is one of
   * LAZY (id only), OBJECT, or ASSOC (associative
   * array)
   * 
   * We use $pendingSelectCols to defer the column
   * check when we have to wait until we know all 
   * possible tables for implicit table references.
   * 
   * We use $includeTables to includeTable() related objects
   * in complex queries so we don't have to query 
   * again for related objects later.  Including 
   * tables is only appropriate when we are returning
   * objects, if you are returning an associative 
   * array, then just select() the other table columns
   * that you need.
   * 
   * @var unknown_type
   * 
   */
  
  private $selectCols        = array();
  private $pendingSelectCols = array();
  private $outputStyle       = self::OBJECT;
  private $includeTables     = array();
  
  /**
   * 
   * The query command 
   * 
   * @var string
   * 
   */
  
  private $command = "SELECT";
  
  
  /**
   * 
   * Should the results be unique?
   * 
   * @var boolean
   * 
   */
  
  private $isDistinct = false;
  
  /**
   * 
   * Option comment for query.
   * 
   * @var string
   * 
   */
  
  private $cmt = "";
    
  /**
   * 
   * a simple expression stack for the where clause
   * 
   * @var tree
   * 
   */
  
  private $whereExpr = null;
  
  /**
   * 
   * Standard constructor - you must create queries relative to at least
   * one table, so the constructor requires $tableName.  Normally you
   * won't construct directly, use the Query::create() factory method.
   * 
   * @param string $tableName - the name of the table this object 
   * relates to
   * 
   * @param string $className - name of the class to use for logging 
   * and logging filtering purposes.  This is not, and will not be used
   * for anything related to model class auto-loadeding.
   * 
   * @param mixed $db - the database connection, if you don't provide
   * either a connection pseudo URL or a an actual database (\db\SQLDatabase),
   * an attempt will be made to use the one already established if there is 
   * one and only one already in the connection pool.
   * 
   * @param LogWriter $logger - can optionally specify the logger to use.
   * 
   */
  
  public function __construct($tableName, $className='Query', $db=null, $logger=null) {

  	parent::__construct($className, 'littlemdesign_db', true);

  	$this->unReady();
  	
    $this->db        = null;
    $this->whereExpr = null;
    $this->joins     = null;
    
    /* make sure we have a database connection */
    
    if(!($db instanceof littlemdesign_db_SQLDatabase)) {
      
      $instance = littlemdesign_db_SQLFactory::createManaged($db);
      
      if($instance === false) {
      	$this->error("Query could not create a managed db instance.");
      	return ;
      }
      
      if(!$instance->isReady()) {
      	$this->error("Query created a db instance, but its not usable.");
      	return ;
      }
      
      $this->db = $instance;
      
    } else {
    	
      if($db === null) {
      	
      	/*
      	 * if we get nothing for database specification, then the intent
      	 * is to likely use whatever is the current connection.  So we
      	 * use the factory to get the default connection.
      	 * 
      	 */
      	
      	$db = SQLFactory::createManaged();
      	if($db === false) {
          $this->error("Query no db connection given, and no default connection exists.");
          return ;
      	}
      }
      
      $this->db = $db;
      
      if(!$this->db->isReady()) {
      	$this->error("Query db instance is not usable.");
      	return ;
      }
    }
    
    /* make sure the given table is a valid table */
     
    $tableName = littlemdesign_db_ORMTable::cleanTableName($tableName);
    
    if(!$this->db->tableExists($tableName)) {
      $this->error("Query bad construction, invalid table: ".$tableName);
      return ;
    }
    
    $this->tableName = $tableName;
    
    $this->joins[] = (object)array(
      "dest"   => $this->tableName,
      "tables" => array($this->tableName),
      "op"     => "",
      "conds"  => array()
    );
     
    /* we are ready for action! */
    
    $this->makeReady();
  }
  
  /**
   * 
   * escapeLiteral() - escape literal text before passing directly
   * to the datagbase.
   * 
   * @param $text
   */
  
  public function escapeLiteral($text) {
    
    if(!$this->isReady()) {
      $this->error("Can not escapeLiteral($text), object is not ready.");
      return false;
    }
    
    return $this->db->escapeLiteral($text);
  }
  
  /**
   * 
   * getOutputFormat() - fetch the current output format.
   * 
   * @return constant (one of LAZY, OBJECT or ASSOC)
   * 
   */
  
  public function getOutputFormat() {
    return $this->outputStyle; 	
  }
  
  /**
   * 
   * asArray() - force output to be in array mode, but
   * still handled by a QueryResult iterator.
   * 
   * @return this
   * 
   */
  
  public function asArray() {
  	$this->outputFormat(self::ASSOC);
    return $this;	
  }
  
  /**
   * 
   * asObjects() - force output to be in full object
   * mode, and handled by QueryResult iterator.
   * 
   * @return this
   *
   */
  
  public function asObjects() {
  	$this->outputFormat(self::OBJECT);
    return $this;	
  }
  
  /**
   * 
   * asIDs() - force output to be lazy loaded objects
   * handled by QueryResult iterator, on each iteration
   * call QueryResult::fetch() to actually load the 
   * object.
   * 
   * @return this
   * 
   */
  
  public function asIDs() {
  	$this->outputFormat(self::LAZY);
    return $this;	
  }
  
  /**
   * 
   * outputFormat() - call with LAZY, OBJECT or ASSOC
   * to set the output mode used by QueryResult iterator.
   * Normally you won't call this directly, use methods,
   * like asArray() or asObjects().
   * 
   * @param $kind constant - LAZY, OBJECT or ASSOC
   * 
   * @return boolean always returns true.
   * 
   */
  
  public function outputFormat($kind=self::OBJECT) {
  	
    switch($kind) {
      case self::LAZY:
      case self::OBJECT:
      case self::ASSOC:
        {
          $this->outputStyle = $kind;
        }	
        break;
        
      default:
        {
          $kind=self::OBJECT;	
        }	
        break;
    }

    return true;
  }
  
  /**
   * 
   * create() - factory method for creating instances of Query objects.
   * You must at least provide a model/table name to get a valid Query
   * you can work with.
   * 
   * @param string $tableName - the exact name of the model this query
   * should be relative to. All queries must be relative to at least
   * one model.
   * 
   * @param SQLDatabase $db - the database parameters (pseudo URL), or
   * actual database, or null (meaning use the default connection)
   * 
   */
  
  static public function create($tableName, $db=null) {
    
    /* clean up table name */
    
    $tableName = littlemdesign_db_ORMTable::cleanTableName($tableName);
    
    /* instantiate */
    
  	return new littlemdesign_db_Query($tableName,$className='Query',$db);
  } 
  
  /**
   * 
   * affectedRows() get the number rows changed by the query that just
   * ran. If not used direct after a method call like passthrough()
   * this likely won't have any sensible 
   * 
   */
  
  public function affectedRows() {
    
    if(!$this->isReady()) {
      $this->error("affectedRows() - object not ready.");
      return false;
    }
    
    return $this->db->getAffectedRows();
  }
  
  /**
   * 
   * passthrough() - skip the framework jazz, do a raw query in the
   * actual database adaptor.
   * 
   * @param string $sql the actual SQL query to pass to the SQL style
   * database.
   * 
   * @return mixed return's whatever SQLDatabase::query() returns.
   * its passthrough!
   *  
   */
  
  public function passthrough($sql) {
  	
  	if(!$this->isReady()) {
  	  $this->error("Can not do passthrough($sql) - object not ready.");
      return false;
  	}
  	
  	/* pass it through */
  	
  	$result = $this->db->query($sql);
  	
  	if($result === false) {
      $this->error("Can not do passthrough($sql) - problem  with database:".$this->db->getError());
      return false;
  	}
  	
  	/* all done */
  	
  	return $result;
  }
  
  /**
   * 
   * rawWhere() - provide a general where clause expression 
   * to use for filterinig.
   * 
   * @param string $value - the expression to use.
   * 
   * @param string $variable - for substitution (not used
   * currently)
   * 
   * @return this
   * 
   */
  
  public function rawWhere($value, $variable=null) {
  	
    if(!$this->isReady()) {
      $this->error("bad rawWhere($value,$variable), object not ready.");
      return $this;
  	}
  	
    /* 
  	 * make sure the variable(s) is properly escaped
  	 * before being substituted.
  	 * 
  	 */
  	
  	if($variable !== null) {
      if(is_array($variable)) {
        foreach($variable as $k => $v) {
          $variable[$k] = $this->db->escapeLiteral($v);
        }
      } else {
  	    $variable = $this->db->escapeLiteral($variable);
      }
  	}
  	
  	/* setup the custom where clause */
  	
    $expr = (object)array(
      'col'  => null,
      'var'  => $variable,
      'op'   => null,
      'expr' => $value
    );
      
    $this->addWhere($expr);
    
    return $this;
  }
  
  /**
   * 
   * refCol() - note a column we'll need to resolve before
   * doing the actual query.
   * 
   * @param string $col - the unresovled column name.
   * 
   */

  private function refCol($col) {
  
    if(empty($col) || ($col === null)) {
      return ;
    }
    
    /* 
     * queue up a column to be double checked at the end, before
     * we actually do the query, but after we know what tables 
     * are involved.
     * 
     */
  
  	if(!in_array($col, $this->colsToCheck)) {
      $this->colsToCheck[] = $col;
  	}
  	
  }
  
  /**
   * 
   * queryTables() - internal helper to fetch the list of
   * resolved tables that we know will be involved in 
   * the query due to extend() (joins).
   * 
   * return array - the list of join tables.
   * 
   */
  
  private function queryTables() {
  	
    $tables = array();
    
    foreach($this->joins as $joinObj) {
      foreach($joinObj->tables as $t) {
        $t = littlemdesign_db_ORMTable::cleanTableName($t);
        if(!empty($t) && !in_array($t, $tables))  {
          $tables[] = $t;
        }
      }
    }
    
    return $tables;
  }
  
  /**
   * 
   * resolveCol() - given a column name make sure its valid
   * within one (and only one) table that is involved in 
   * this query either as the primary table or an extend()'d
   * table.
   * 
   * This method can not be used until a terminal method 
   * is invoked (because we many not be done defining the
   * query yet).
   * 
   * @param string $col
   * 
   * @return string the resolved column name.
   * 
   */
  
  private function resolveCol($col) {
  	
    /* make sure that $col is referenced by its table/model */
  	
    $col       = trim($col);
    $tableName = "";
    $colName   = "";
  	
    /* figure out the column name and table name... */
  	
    $matches = array();
    if(preg_match('/^([^\.]+)$/', $col, $matches)) {
      $colName   = $matches[1];
  	} else if(preg_match('/^([^\.]+)\.([^\.]+)$/', $col, $matches)) {
      $tableName = littlemdesign_db_ORMTable::cleanTableName($matches[1]);
      $colName   = $matches[2];
    }
  	
    if(empty($colName)) {
      $this->error("resolveCol($col) - can't parse column name ($col)");
      return false;
    }
    
    /* 
     * convert the join list to a set of tables we can
     * look in for the column name.
     * 
     */
    
    $tables = $this->queryTables();

    /*
     * if they gave a table name, then it better be in the list.
     * 
     */
    
    if(!empty($tableName)) {
    	
      if(!in_array($tableName, $tables)) {
        $this->error("resolveCol($col) - table '$tableName' is not joined (yet).");
        return false;
      }
      
      return $tableName.".".$colName;
    }
    
    /*
     * now we have to walk through the tables and try to find
     * the column.
     * 
     */
    
    $matchingTables = array();
    
    foreach($tables as $table) {
    	
      $model = littlemdesign_db_ORMTable::create($table);

      if($model->hasColumn($colName)) {
        $matchingTables[] = $table;	
      }
      
      if($model->getPKName() == $colName) {
        $matchingTables[] = $table;
      }
      
      unset($model);
    }

    if(count($matchingTables) == 0) {
      $this->error("resolveCol($col) - no such column.");
      return false;
    } else if(count($matchingTables) > 1) {
      $this->error("resolveCol($col) - column reference is ambiguous.");
      return false;
    }
    
    /* all done */
    
    return $matchingTables[0].".".$colName;
  }
  
  /**
   * 
   * neededColumns() - internal helper to return the list 
   * of resolved columsn that are needed as part of the
   * select output.  We can't call this method until 
   * one of the terminal methods is invoked (so we know
   * we have all the columns we're going to have).
   * 
   * @return array the list of needed columns.
   * 
   */
  
  private function neededColumns() {

  	/* 
  	 * if we are in lazy mode, we only want the primary key of the
  	 * main objects.
  	 * 
  	 */
  	
  	if($this->outputStyle == self::LAZY) {

  	  $toSelect   = array();
  	  
  	  $toSelect[] = $this->tableName.":id";
  	  
      return $toSelect;
  	}
  	
    /* first make sure any pending columns are resolved now. */
  	
  	foreach($this->pendingSelectCols as $col) {
  		
  	  $colName = $this->resolveCol($col);
  	  
  	  if($colName === false) {
  	  	$this->warning("neededColumns() - ignoring request select column '$col' (can't resolve)");
  	  	continue;
  	  }
  	  
  	  $colName = str_replace('.', ':', $colName);
  	  
  	  if(!in_array($colName, $this->selectCols)) {
  	  	$this->selectCols[] = $colName;
  	  }
  	}
  	
    $noExplicitCols = false;
    if(count($this->selectCols) == 0) {
      $noExplicitCols = true;
    }
  	
    /*
     * if they have included additional realted tables
     * (that are part of the query) in the output, then
     * we have to add in the implied columsn for those 
     * tables...
     * 
     */
      
    foreach($this->includeTables as $toInclude) {
      $this->select($toInclude);
    }
      
  	/* 
  	 * if there were no explicit cols, then we have to
  	 * add at least the columns from this table.
  	 * 
  	 */
  	
  	if($noExplicitCols === true) {
      
  	  $this->select($this->tableName);
  	  
  	} else {
  	  
  	  /* 
  	   * we had an explicit list of columns, so enable
  	   * array mode.
  	   * 
  	   */
  	  
      $this->outputFormat(self::ASSOC);
    }
      
    /* 
     * now walk through $selectCols and echo them out to 
     * the caller.  If they chose lazy output, then we only
     * return the primary key of tables (id)
     * 
     */
  	
    $toSelect = array();
  	
  	foreach($this->selectCols as $colName) {

  	  if($this->outputStyle == self::LAZY) {

  	  	if(!preg_match('/\.id$/', $colName)) {
  	  	  continue ;
  	  	}
  	  }
  	  
  	  $toSelect[] = $colName;
  	}
  	
    /* all done */
  	
    return $toSelect;
  }
  
  /**
   * 
   * includeTable() - when doing a complex query that 
   * spans multiple tables, you might need not just
   * the matching object from the primary table, but  
   * one or more of the other matches, from the other
   * tables.  Instead of re-doing a separate query 
   * focused on the other table(s), you can just use
   * includeTable(<table name>), to have the objects
   * from that table returned as part of the result.
   * 
   * When you are then iterating through the results
   * with QueryResult, you can use QueryResult::related()
   * to fetch the objects from a given table that were 
   * returned at the same time as the object from the
   * primary table.
   * 
   * For example if your query involves Book, Author
   * and Review, you can pull the matching objects
   * all at once in the same query by calling 
   * includeTable('Author'), and includeTable('Review').  
   * Then while iterating use related('Author') and 
   * related('Review') to fetch those other objets...without 
   * doing more database queries.
   * 
   * This can speed-up over all access time and reduce
   * complexity of your code, but there is also overhead
   * to pulling multiple objects as part of the same 
   * query.
   * 
   * This method is only useful in asObjects() output mode,
   * if your output mode is lazy loading or associative 
   * array, then this method has no effect.  However, 
   * in associative array mode you can simply use the
   * select() method to cherry pick any columns you need 
   * from any of the primary or extend()'d tables.
   * 
   * @param string $tableName the name of the table to
   * pull related objects from during the query.
   * 
   * @return this
   *  
   */
  
  public function includeTable($tableName) {
  	
    $tableName = littlemdesign_db_ORMTable::cleanTableName($tableName);
    
    if(!in_array($tableName, $this->includeTables)) {
  	  $this->includeTables[] = $tableName;
    }
    
    return $this;
  }
  
  /**
   * 
   * select() - cherry pick columns to include in the output.
   * When you explicity pick columns, it will force the 
   * output mode to be associative array (implicitly calls
   * asArray())
   * 
   * @param string $tableOrColumn - specify table, a column
   * or a <table>.<column>.  if you provide a column then
   * just that column is picked.  If you provide a table
   * name,then all columns in the table will be output.
   * 
   * @param boolean $raw - if you want to explicitly provide
   * a select expression and not a column name, you can set
   * $raw to true to "pass through" the expression to the
   * database.
   * 
   * @return this
   * 
   */
  
  public function select($tableOrColumn, $raw=false) {

  	$colName   = "";
  	$tableName = "";
    $matches   = array();
    
    if($raw === true) {
      $this->selectCols[] = trim($tableOrColumn);
      return $this;
    }
    
    /* parse the table/column name */
    
    if(preg_match('/^([^\.]+)$/', $tableOrColumn, $matches)) {
      $colName   = $matches[1];
  	} else if(preg_match('/^([^\.]+)\.([^\.]+)$/', $tableOrColumn, $matches)) {
      $tableName = littlemdesign_db_ORMTable::cleanTableName($matches[1]);
      $colName   = $matches[2];
    }
    
    /* 
     * we need at least one table model to do things like check for
     * valid table names.
     * 
     */
    
    $model = littlemdesign_db_ORMTable::create($this->tableName);
    
    if(!empty($tableName) && !empty($colName)) {
    	
      if(!in_array("$tableName:$colName", $this->selectCols)) {
      	
        if(!$model->tableExists($tableName)) {
          $this->error("select($tableOrColumn) - no such table '$tableName'");
          return $this;
        }
      	
        $other = littlemdesign_db_ORMTable::create($tableName);
        if(!$other->hasColumn($colName)) {
          if(!$other->getPKName() == $colName) {
            $this->error("select($tableOrColumn) - no such column");
            return $this;
          }
        }
      	
        /* its legit! */
        
        $this->selectCols[] = "$tableName:$colName";
      }
      
      return $this;
    }
  	
  	/* 
  	 * if we have just column it might be a column name or a 
  	 * table name...
  	 * 
  	 */
    
    $maybeTable = $tableName;
    if(!empty($colName)) {
      $maybeTable = $colName;
    }
    
    $maybeTable = littlemdesign_db_ORMTable::cleanTableName($maybeTable);

    if(!$model->tableExists($maybeTable)) {
    	
      if(empty($colName)) {
        $this->error("select($tableOrColumn) - can't identify valid table.");
        return $this;
      }
      
      /* 
       * ok, its not a table, likely a column name, so 
       * search for matching table...we have to delay 
       * this check until the end when we know all 
       * the tables that are involved.
       * 
       */
      
      if(!in_array($colName,$this->pendingSelectCols)) {
        $this->pendingSelectCols[] = $colName;
      }
      
    } else {
    	
      /* 
       * ok, they asked to select a table, so we infer the
       * columns that are in that table.
       * 
       */
    
      $other  = littlemdesign_db_ORMTable::create($maybeTable);
      
      $fields = $other->getFieldTypes();
      
      /* include the primary key */
      
      $this->selectCols[]= "$maybeTable:id";
      
      /* include the table's general columns */
      
      foreach($fields as $name => $fieldType) {
        if(!in_array("$maybeTable:$name", $this->selectCols)) {
          $this->selectCols[] = "$maybeTable:$name";
        }
      }
      
    }
    
    unset($model);
    
  	return $this;
  }
  
  /**
   * 
   * extend() - extend the query to span the last 
   * table we extended to (the primary table to 
   * start with), to this next one ($otherTable).
   * The model built into your model classes tells
   * this method what the join condition(s) are, 
   * but you must extend from one table to the 
   * next so that the relationships can be determined.
   * 
   * This means that you are free to use extend() as 
   * often as you wish, but the order matters, each
   * call to extend() will be based on the previous
   * one.  The first call to extend walks from the
   * primary table to the additional table.
   * 
   * For example if the primary table (the table this
   * query was created relative to), is Book, and 
   * you then do extend("Author")->extend("Review"),
   * then the model relations will be walked:
   * 
   *   Book > Authoor > Review
   *   
   * And those relations (defined in your model classes),
   * as 'Has' Or 'Has One Through' etc, will determine
   * the table join conditions automatically.  You 
   * can further refine join conditions by adding 
   * additional where() clauses to lock to specific
   * rows of the table(s) etc.
   * 
   * Currnet the default join method is left join, but
   * in the future this method will be expanded to allow
   * other join styles.  
   * 
   * @param styring $otherTable the name of table to 
   * extend to (join with).
   * 
   * @return this
   * 
   */
  
  public function extend($otherTable) {

    if(!$this->isReady()) {
      $this->error("bad extend($otherTable) - object not ready.");
      return $this;
    }
  	
    if(count($this->joins)==0) {
      $this->joins[] = (object)array(
        "dest"   => $this->tableName,
        "tables" => array($this->tableName),
        "op"     => "",
        "conds"  => array()
      );
    }
    
    /* try to walk from the last table we extended to, to this one */
    
    $prev = $this->joins[count($this->joins) - 1]->dest;
    $next = $otherTable;
    
    $prevModel  = littlemdesign_db_ORMTable::create($prev);
    
    $tables     = array();
    $conditions = array();
    
    if(!$prevModel->canLinkTo($next, $tables, $conditions)) {
      $this->error("extend($otherTable) - can not extend, no relation from $prev");
      return $this;  
    }
    
    /* ok, extend */

    $this->joins[] = (object)array(
      "dest"   => $next,
      "tables" => $tables,
      "op"     => "",
      "conds"  => $conditions
    );

    /* all done */
    
    return $this;
  }
  
  /**
   * 
   * where() - add a where clause. By default where clauses are
   * chained together with "AND" but you can use methods like _or(),
   * _and(), _not() or _xor() to select out clauses are chained.
   * 
   * @param unknown_type $col - the unresolved column name that 
   * this where is for.
   * 
   * @param unknown_type $op - the kind of where, for example '='
   * or 'LIKE' etc.  Most basic operations are supported (including
   * Regexp).
   * 
   * @param unknown_type $value - the value expression for this
   * where clause.
   * 
   * @param unknown_type $variable - for substitution, no currently
   * used.
   * 
   * @return this
   * 
   */
  
  public function where($col, $op='', $value='', $variable=null) {

    if(!$this->isReady()) {
      $this->error("bad where($col,$op,$value,$variable) - object not ready.");
      return $this;
  	}

  	if(empty($col)) {
      $this->error("bad where($col,$op,$value,$variable) - no column name.");
  	  return $this;
  	}
  	
  	/* 
  	 * We do not try to do any escaping here because the value 
  	 * expressions might not be simple literals.  
  	 * 
  	 */
  	
  	$this->refCol($col);
    
  	/* setup the actual expression */
  	
  	$op  = strtoupper($op);
  	$xpr = null;
  	
  	switch($op) {
  		
      case '=':
      case '>=':
      case '>':
      case '<=':
      case '<':
      case '<>':
      case '!=':
        {
          $expr = (object)array(
            'col'  => $col,
            'var'  => $variable,
            'op'   => $op,
            'expr' => $value
          );
        }
      	break;
      	
      case 'IN':
      case 'NOT IN':
      	{
          if(!is_array($value)) {
            $value = array($value);
          }
          
          $v = array();
          foreach($value as $item) {
          	$v[] = $item;
          }
          
          $expr = (object)array(
            'col'  => $col,
            'var'  => $variable,
            'op'   => $op,
            'expr' => $v
          );
          
      	}
      	break;
      	
      case 'LIKE':
      case 'NOT LIKE':
      	{
          $expr = (object)array(
            'col'  => $col,
            'var'  => $variable,
            'op'   => $op,
            'expr' => $value
          );
      	}
      	break;
      	
      case 'REGEXP':
      case 'NOT REGEXP':
      	{
          if($this->db->getType() == LMD_PGSQL) {
          	
            if($op == 'REGEXP') {
              $op = "~";
          	} else {
              $op = "!~";
          	}
          }
          
          $expr = (object)array(
            'col'  => $col,
            'var'  => $variable,
            'op'   => $op,
            'expr' => $value
          );
      	}
      	break;
      	
      case 'IS NULL':
      case 'IS NOT NULL':
      	{
          $expr = (object)array(
            'col'   => $col,
            'var'   => null,
            'op'    => $op,
            'expr'  => null,
            'unary' => true
          );
      	}
      	break;
      	
      case 'IS TRUE':
      case 'IS NOT TRUE':
      	{
      	  $expr = (object)array(
            'col'   => $col,
            'var'   => null,
            'op'    => $op,
            'expr'  => null,
            'unary' => true
          );
      	}
      	break;
      	
      case 'IS FALSE':
      case 'IS NOT FALSE':
      	{
          $expr = (object)array(
            'col'   => $col,
            'var'   => null,
            'op'    => $op,
            'expr'  => null,
            'unary' => true
          );
      	}
      	break;
      	
      default:
        {
          $this->error("bad where($col,$op,$value,$variable) - unkonwn op.");
          return $this;
        }
      	break;

  	}
  	
  	/* add in this clause */
  	
  	if($expr) {
  	  $this->addWhere($expr);
  	}
  	
  	return $this;
  }
  
  /**
   * 
   * distinct() - enable unique results mode.
   * 
   * @param boolean $flag - enable/disable.
   * 
   * @return this
   * 
   */
  
  public function distinct($flag=true) {
    $this->isDistinct = $flag;
    return $this;
  }

  
  /**
   * 
   * comment() - tag the SQL command (in the log file) with a comment.
   * 
   * @param string $text
   * 
   * @return this
   * 
   */
  
  public function comment($text) {
  	
  	/*
  	 * NOTE: some databases don't support nexted comments
  	 * so we allow only onelevel of comment.
  	 * 
  	 */
  	
  	$text = str_replace('/*', '', $text);
    $text = str_replace('*/', '', $text);
  	
    $this->cmt = $text;	
    
    return $this;
  }
  
  /**
   * 
   * offset() - set what row of the results output to start 
   * showing results at.
   *  
   * @param integer $start - what row to start on (1 based)
   * 
   * @return this
   * 
   */
  
  public function offset($start=-1) {
    $this->pageStart = $start;
    return $this;	
  }
  
  /**
   * 
   * limit() - set how may results to show at once
   * in the result output.
   * 
   * @param integer $size
   * 
   * @return this
   * 
   */
  
  public function limit($size=-1) {
  	$this->pageSize=$size;
  	return $this;
  }
  
  /**
   * 
   * groupBy() - provide the list of columns to group the
   * output by. 
   * 
   * @param array $cols the columns to group by (in order)
   * 
   * @return this
   * 
   */
  
  public function groupBy($cols = array()) {
    
    /* 
     * NOTE: we currently only support grouping by a list 
     * of column names.
     * 
     */
    
    /* check the input */
    
    if(empty($cols)) {
      return $this;
    }
    
    if(is_string($cols)) {
      $cols = array($cols); 
    }
    
    if(count($cols)==0) {
      return $this;
    }
    
    /* 
     * for each column, resolve it and append it to the 
     * group by list.
     * 
     */
    
    foreach($cols as $col) {
      
      /*
       * column names can't be cleaned until the end when we know 
       * all the tables that are involved.
       * 
       * NOTE: we don't double check group by columns, becasue they 
       * many not refer to actual columns, they may be referring to
       * aliases we can't directly see.
       * 
       */
      
      /* $this->refCol($col); */
            
      $this->groupCols[] = $col;
    }
    
    /* all done */
    
    return $this;
  }
  
  /**
   * 
   * orderBy() - provide the list of columns to set the
   * soring order for the output.  If you are using  offset()
   * or limit(), you should also use orderBy(), otherwise
   * you are not guarenteed to have consistent results.
   * 
   * By default the sort order is ascending, you can 
   * specify the sort order by giving each column key
   * a value of 'asc' or 'desc'.
   * 
   * @param array $cols the sort ordering column names.
   * 
   * @return this
   * 
   */
  
  public function orderBy($cols = array()) {

    /* 
     * NOTE: we currently only support ordering by a list 
     * of column names. No 'USING' etc.
     * 
     */
  	
    /* check the input */
  	
    if(empty($cols)) {
      return $this;
    }
    
    if(!is_array($cols)) {
      $cols = array($cols => 'asc');	
    }
    
    $keepers = array();
    
    foreach($cols as $k => $v) {
        
      if(is_numeric($k)) {
      
        /* 
         * they probably provided a list of column 
         * names instead of an associative array.
         * 
         */
        
        $k = $v;
        $v = 'asc';
      }
      
      $v = strtolower($v);
      
      if(($v == 'desc')||($v == 'd')) {
        $v = 'desc';
      }
      
      $keepers[$k] = $v;
    }
    
    if(count($keepers) == 0) {
      return $this;
    }
    
    /* for each column, resolve it and append it to the 
     * order by list.
     * 
     */
    
    foreach($keepers as $k => $v) {
    	
      /*
       * column names can't be cleaned until the end when we know 
       * all the tables that are involved.
       * 
       */
    	
      $this->refCol($k);
            
      $this->orderCols[$k] = $v;
    }
    
    /* all done */
    
    return $this;
  }
  
  /**
   * 
   * _or() - link the previous where clause with the
   * next one via "OR".
   * 
   * @return this.
   * 
   */
  
  public function _or() {
  	return $this->_binOp('or');
  }
  
  /**
   * 
   * _and() - link the previous where clause with the
   * next one via "AND".
   * 
   * @return this.
   * 
   */
  
  public function _and() {
  	return $this->_binOp('and');
  }
  
  /**
   * 
   * _xor() - link the previous where clause with the
   * next one via "XOR".
   * 
   * @return this.
   * 
   */
  
  public function _xor() {
  	return $this->_binOp('xor');
  }
  
  /**
   * 
   * _not() - preceed the where clauses to come with a "NOT".
   * 
   * @return this.
   * 
   */
  
  public function _not() {
    
    if(!$this->isReady()) {
      $this->error("bad _not(), object not ready.");
      return $this;
    }

    if(!is_array($this->whereExpr)) {
      $this->whereExpr = array();
    }
    
    /* start a new expression that is negated... */
    
    array_unshift($this->whereExpr, (object)array(
      'type'  => 'not',
      'lhs'   => '',
      'op'    => strtoupper('not'),
      'rhs'   => null,
      'unary' => true
    ));
    
    /* all done */
    
    return $this;
    
  }
  
  /**
   * 
   * isFalse() - filter on the column being false.
   * 
   * @param string $col - the column name
   * 
   * @return this.
   * 
   */
  
  public function isFalse($col) {
    $this->where($col, 'is false');	
    return $this;
  }
  
  /**
   * 
   * isTrue() - filter on the column being true.
   * 
   * @param string $col - the column name
   * 
   * @return this.
   * 
   */
  
  public function isTrue($col) {
    $this->where($col, 'is true');
    return $this;	
  }
  
  /**
   * 
   * isNull() - filter on the column being null.
   * 
   * @param string $col - the column name
   * 
   * @return this.
   * 
   */
  
  public function isNull($col) {
    $this->where($col, 'is null');
    return $this;	
  }
  
  /**
   * 
   * isNotNull() - filter on the column not being null.
   * 
   * @param string $col - the column name
   * 
   * @return this.
   * 
   */
  
  public function isNotNull($col) {
    $this->where($col, 'is not null');
    return $this;
  }
  
  /**
   * 
   * isIn() - filter on the column value being
   * in the given set of values.
   * 
   * @param string $col - the column name
   * 
   * @return this.
   * 
   */
  
  public function isIn($col, $values) {
    $this->where($col, 'in', $values);
    return $this;
  }
  
  /**
   * 
   * isNotIn() - filter on the column value not
   * being in the given set of values.
   * 
   * @param string $col - the column name
   * 
   * @return this.
   * 
   */
  
  public function isNotIn($col, $values) {
    $this->where($col, 'in', $values);
    return $this;
  }
  
  /**
   * 
   * _binOp() - internal helper method that implements
   * _or(), _and() and _xor().
   * 
   * @param string $op
   * 
   * @return this
   * 
   */
  
  private function _binOp($op) {

    if(!$this->isReady()) {
      $this->error("bad _binOp($op), object not ready.");
      return $this;
  	}
  	
  	/* 
  	 * if we don't have any expression yet to use as the left
  	 * hand side, then we can ignore.
  	 * 
  	 */
  	
  	if((count($this->whereExpr) == 0)||($this->whereExpr[0] === null)) {
      return $this;
  	}
  	
  	/* 
  	 * if we are trying to do a binary op more than once, 
  	 * it would be a syntax error, because have not supplied
  	 * an intermediate argument to chain them together.
  	 * 
  	 */
  	
  	if($this->whereExpr[0]->type != 'expr') {
  	  $this->error("_binOp($op) - too few operands.");
  	  return $this;
  	}  	
  	
  	/* 
  	 * whatever we have already becomes the left hand side 
  	 * and we prepare to receive the right hand side
  	 * 
  	 */
  	
  	$lhs = array_shift($this->whereExpr);
  	  
  	array_unshift($this->whereExpr, (object)array(
  	  'type' => strtolower($op),
  	  'lhs'  => $lhs,
  	  'op'   => strtoupper($op),
  	  'rhs'  => null
  	));
  	
    /* all done */
  	
  	return $this;
  }
  
  /**
   * 
   * startGroup() - explicitly start a '(' ... ')'
   * grouping to force precedence.
   * 
   * @return this.
   * 
   */
  
  public function startGroup() {
  
    /* are ready? */
  	
    if(!$this->isReady()) {
      $this->error("bad startGroup(), object not ready.");
      return $this;
  	}
  	
  	if(!is_array($this->whereExpr)) {
      $this->whereExpr = array();
  	}
  	
  	/* start a new grouping */
  	
  	array_unshift($this->whereExpr, null);
  	
  	return $this;
  }
  
  /**
   * 
   * endGroup() - explicitly end a '(' ... ')'
   * grouping to force precedence.
   * 
   * @return this.
   * 
   */
  
  public function endGroup() {
  	
    /* are ready? */
  	
    if(!$this->isReady()) {
      $this->error("bad startGroup(), object not ready.");
      return $this;
  	}
  	
  	if(!is_array($this->whereExpr)||(count($this->whereExpr) == 0)) {
  	  return $this;
  	}
  	
  	if($this->whereExpr[0] !== null) {
  		
  	  /* 
  	   * if we are trying to close a group but we have 
  	   * an open operations or  something, then we are
  	   * creating a syntax error.
  	   * 
  	   */
  	
  	  if($this->whereExpr[0]->type != 'expr') {
  	    $this->warning("endGroup() - closing but inner expression is not complete.");
  	  }
  	}
  	
  	/* did we get a grouped expression? */
  	
  	$lhs = array_shift($this->whereExpr);
  	if($lhs === null) {
      return $this;
  	}
  	$lhs->grouped = true;
  	
    if(count($this->whereExpr) >= 2) {
      if($this->whereExpr[0] === null) {
        array_shift($this->whereExpr);  	
      }
    }
  	
  	/* merge it with the existing expression */
  	
  	$this->addWhere($lhs);
  		
  	return $this;
  }
  
  /**
   * 
   * addWhere() - internal helper that queues up
   * where clauses in a basic expression tree.
   * Later flattenWhere() is called to  actually
   * flatten the tree into an expression we can
   * hand to the database.
   * 
   * @param object $expr a where clause node
   * 
   */
  
  private function addWhere($expr) {
  	
  	/* are ready? */
  	
    if(!$this->isReady()) {
      $this->error("bad addWhere(), object not ready.");
      return ;
  	}

  	/* first clause? */
  	
  	if(count($this->whereExpr) == 0) {
  	
  	  $this->whereExpr = array();
  	 
  	  array_unshift($this->whereExpr, (object)array(
  	    'type' => 'expr',
  	    'lhs'  => $expr,
  	    'op'   => null,
  	    'rhs'  => null
  	  ));
  	  
  	  return ;
  	}
  	
  	/* first clause in a grouping? */
  	
  	if($this->whereExpr[0] === null) {
  		
  	  $this->whereExpr[0] = (object)array(
  	    'type' => 'expr',
  	    'lhs'  => $expr,
  	    'op'   => null,
  	    'rhs'  => null
  	  );
  	  
  	  return ;
  	}
  	
  	/* 
  	 * if we just have an expression currently, then we
  	 * AND it with this one by default.
  	 * 
  	 */
  	
  	if($this->whereExpr[0]->type == 'expr') {
  
  	  /* string together clauses using AND by default  */
  		
  	  $lhs = array_shift($this->whereExpr);
  	  
  	  array_unshift($this->whereExpr, (object)array(
  	    'type' => 'expr',
  	    'lhs'  => $lhs,
  	    'op'   => 'AND',
  	    'rhs'  => $expr
  	  ));
  	  
  	  return ;
  	}
  	 
  	if(($this->whereExpr[0]->type == 'not')||
       ($this->whereExpr[0]->type == 'or') ||
       ($this->whereExpr[0]->type == 'and')||
       ($this->whereExpr[0]->type == 'xor')) {
  		
      if($this->whereExpr[0]->type == 'not') {
      
        /* complete the not */
      	
      	$this->whereExpr[0]->lhs  = $expr;
  	    $this->whereExpr[0]->type = 'expr';
  	    
      } else {
      	
  	    /* complete the outstanding or/and/xor */
  		
  	    $this->whereExpr[0]->rhs  = $expr;
  	    $this->whereExpr[0]->type = 'expr';
      }
      
      
      if(isset($this->whereExpr[1])) {
      	if(isset($this->whereExpr[1]->type)) {
  	      if($this->whereExpr[1]->type != 'expr') {
  	        $op = array_shift($this->whereExpr); 
  	        $this->addWhere($op);
  	      }
      	}	
  	  }
  	  
    
  	} else {
  		
  	  /* trying to add something in a weird way */
  		
  	  $this->error("bad addWhere(), can not determine merge method.");
  	}
  	
    /* all done */
  	

  }
  
  /**
   * 
   * flattenWhere() - helper function to flatten the where
   * expression tree.  
   * 
   * @param $expr tree - the expression tree we are flattening
   * 
   * @return string the where expression of the given sub-tree.
   * return exactly false if there is a problem.
   * 
   */
  
  private function flattenWhere($expr=null) {
  	
  	/* anything to do? */
  	
    if(!is_object($expr)) {
      return "";
    }

    /* operand? */
    
    if(!isset($expr->type)) {
      
      $var  = $expr->var;
      
      /* make sure we are referring to a valid column */
      
      $colName = "";
      
      if(!empty($expr->col) && ($expr->col !== null)) {
        $colName = $this->resolveCol($expr->col);
        if($colName === false) {
          $this->error("flattenWhere() - bad column name: ".$expr->col);
          return false;	
        }
      }
      
      /* set up the where clause */
      
      $text = "";
      if(!empty($colName)) {
        $text = $colName.' ';
      }
      
      /*
       * When adding in the value expression, we do not quote it,
       * because we don't want to limit the layer above us to using
       * just literals like stirngs or integers.  They should be 
       * able to use reasonably simple SQL expressions that are 
       * commong to any SQL database.
       * 
       * This is inherently unsafe though, eventually we plan to add
       * prepared queries and parameter binding to help secure things.
       * 
       */
      
      $text .= $expr->op;
      if(!isset($expr->unary)) {
        $text .= ' '."$expr->expr";
      }
      
      /* substitute if needed */
      
      /* TODO: */
      
      return $text;
    }
    
    /* gather up leaves */
  	
    $lhs  = trim($this->flattenWhere($expr->lhs));
    $rhs  = trim($this->flattenWhere($expr->rhs));
    
    if(($lhs === false)||($rhs === false)) {
      return false;
    }
    
    $type = $expr->type;
    $op   = $expr->op;
    
    $text = "";
    
    if(!isset($expr->unary)) {
      if($lhs != "") {
        $text .= $lhs.' ';
      }
      if($op != "") {
        $text .= $op.' ';
        if($rhs != "") {
          $text .= $rhs;
        }
      }
    } else {
      if($op != "") {
        $text .= $op.' ';
        if($lhs != "") {
          $text .= $lhs;
        }
      }
    }

    if(isset($expr->grouped)) {
      $text = "($text)";
    }
    $text = trim($text);
    
    /* all done */
    
    return $text;
  }
  
  /**
   * 
   * toSQL() - before executing a query, you can call
   * this method to see what SQL will be used. In 
   * the case of UPDATE, you need to pass in an array
   * of field name/values.
   * 
   * @param mixed $countOnly - for SELECT, you can
   * ask for (true)  count(*) only.  To see how 
   * many results there will be. Otherwise we fetch
   * the actual SELECT results. 
   * 
   * For UPDATE you must provide array of field name/value
   * pairs (the actual updates).
   * 
   * This method *DOES NOT* execute SQL, it only 
   * generates SQL.
   * 
   * @return string the generated SQL.
   * 
   */
  
  public function toSQL($countOnly=false) {
  	
    switch($this->command) {
    	
      case "SELECT":
        {
          return $this->toSQLSelect($countOnly);
        }
        break;
        
      case "UPDATE":
        {
          $fields = $countOnly;
          return $this->toSQLUpdate($fields);
        }
        break;
        
      case "DELETE":
        {
          return $this->toSQLDelete();
        }
        break;
    }
    
    return "";
  }
  
  /**
   * 
   * toSQLDelete() - the toSQL() variation for DELETE
   * 
   * @return string - the SQL string.
   * 
   */
  
  public function toSQLDelete() {
    
    if(!$this->isReady()) {
      $this->error("Can not do toSQLDelete() - object not ready.");
      return false;
    }
    
      /* build the SQL */
    
    $sql = $this->command." ";
    
    $tables = "";
    $from   = "";
    $using  = "";
    $where  = "";
    
    /* figure out the tables/from clause */
 
    if($this->db->getType() == LMD_MYSQL) {
      
      /*
       * MySQL has the form (inner join):
       * 
       *   DELETE T1.*
       *   FROM T1, T2,...
       *   WHERE e1, e2,...
       *   
       */
      
      $sql .= $this->tableName.".* ";
      
      /* 
       * add in extend() tables that are not 'this' 
       * table.
       * 
       */
      
      $toWalk = $this->queryTables();
      
      foreach($toWalk as $table) {
        $tables .= ",$table";
      }
      $tables = trim($tables, ',');
      
      /* no USING */
      
      $using = "";
      
      if(!empty($tables)) {
        $from = "FROM $tables";
      }
      
    } else if($this->db->getType() == LMD_PGSQL) {
    
      /*
       * PostgreSQL has the form (inner join):
       * 
       *   DELETE 
       *   FROM T1 
       *   USING T2,T3,...
       *   WHERE e1,e2,...
       *   
       */
      
      $from = "FROM ".$this->tableName." ";
      
      $toWalk = $this->queryTables();
      
      foreach($toWalk as $table) {
        
        if($table == $this->tableName) {
          continue;
        }
        
        $tables .= ",$table";
      }
      $tables = trim($tables, ',');
      
      if(!empty($tables)) {
        $using = "USING $tables";
      }
    }
    
    /* figure out the where clause */
      
    $joinWhere = "";
    
    foreach($this->joins as $joinObj) {
      
      foreach($joinObj->conds as $cond) {
        
        if(!empty($joinWhere)) {
          $joinWhere .= " AND ";
        }
        $joinWhere .= $cond;
      }
    }
    
    $where = $this->flattenWhere($this->whereExpr[0]);
    
    if(!empty($joinWhere)) {
      if(!empty($where)) {
        $where = $joinWhere." AND $where";
      } else {
        $where = $joinWhere;
      }
    }
    if(!empty($where)) {
      $where = "WHERE $where";
    }
    
    /* go team! */
    
    if(!empty($from)) {
      $sql .= $from." ";
    }
    
    if(!empty($using)) {
      $sql .= $using." ";
    }
    
    if(!empty($where)) {
      $sql .= $where;
    }
    
    if(!empty($sql)) {
      $sql .= ";";
    }
    
    /* all done */
    
    return $sql;
  }
  
  /**
   * 
   * toSQLUpdate() - the toSQL() variation for UPDATE
   * 
   * @return string - the SQL string.
   * 
   */
  
  public function toSQLUpdate($fields=array()) {
  	
    if(!$this->isReady()) {
      $this->error("Can not do toSQLUpdate() - object not ready.");
      return false;
    }
    
    /* 
     * make sure the columns we are trying to set are all 
     * inside the primary table. UPDATE can access more 
     * than one table at a time, but it can only update 
     * one table at a time.
     * 
     */
    
    $colMap = array();
    
    foreach($fields as $col => $expr) {
      
      $colName = $this->resolveCol($col);
      
      if($colName === false) {
        $this->error("toSQLUpdate() - can not resolve '$col'");
        return false;
      }
      
      $matches = array();
      if(!preg_match('/^([^:]+)[:.](.*)$/',$colName, $matches)) {
        $this->error("toSQLUpdate() - Can't parse column name '$colName'");
        return false;
      }
      
      $tableName = $matches[1];
    
      if($tableName != $this->tableName) { 
        $this->error("toSQLUpdate() - update column $colName is not in the primary table.");
        return false; 
      }
      
      $colMap[$colName] = $expr;
    }
    
    /* build the SQL */
    
    $sql = $this->command." ";
    
    $tables = "";
    $from   = "";
    $set    = "";
    $where  = "";
    
    /* figure out the tables/from clause */
    
    $tables .= $this->tableName." ";
    
    /* 
     * walk through the joins, add in any tables 
     * that were extended, PostgreSQL puts them into
     * the FROM clause while MySQL lets you list them
     * up front.  We assume that the join conditions 
     * are in the where() filters, and that an inner
     * join is ok...because that what is implied by
     * this syntax.
     * 
     */

    if($this->db->getType() == LMD_MYSQL) {
      
      /*
       * MySQL has the form (inner join):
       * 
       *   UPDATE T1, T2, ...
       *   SET e1,e2,...
       *   WHERE
       */
      
      /* 
       * add in extend() tables that are not 'this' 
       * table.
       * 
       */
      
      $toWalk = $this->queryTables();
      
      foreach($toWalk as $table) {
        
        if($table == $this->tableName) {
          continue;
        }
        
        $tables .= ",$table";
      }
      
      $from = "";
      
    } else if($this->db->getType() == LMD_PGSQL) {
    
      /*
       * PostgreSQL has the form (inner join):
       * 
       *   UPDATE T1
       *   SET e1,e2,...
       *   FROM T2,...
       *   WEHRE
       *   
       */
      
      $toWalk = $this->queryTables();
      
      foreach($toWalk as $table) {
        
        if($table == $this->tableName) {
          continue;
        }
        
        $from .= ",$table";
      }
      $from = trim($from,',');
      
      if(!empty($from)) {
        $from = "FROM $from";
      }
    }
    
    /* figure out the set clasuse */
    
    foreach($colMap as $col => $expr) {

      /*
       * PostgreSQL doens't allow table qualification 
       */
      
      if($this->db->getType() == LMD_PGSQL) {
        
        $matches = array();
        if(!preg_match('/^([^:]+)[:.](.*)$/',$col, $matches)) {
          $this->warning("toSQLUpdate() - Can't parse column name '$col'");
          continue;
        }
      
        $col = $matches[2];
        
      }
      
      $set .= "$col=$expr,";
    }
    
    $set = trim($set,', ');
    
    if(!empty($set)) {
      $set = "SET $set";
    }
    
    /* figure out the where clause */
      
    $joinWhere = "";
    
    foreach($this->joins as $joinObj) {
      
      foreach($joinObj->conds as $cond) {
        
        if(!empty($joinWhere)) {
          $joinWhere .= " AND ";
        }
        $joinWhere .= $cond;
      }
    }
    
    $where = $this->flattenWhere($this->whereExpr[0]);
    
    if(!empty($joinWhere)) {
      if(!empty($where)) {
        $where = $joinWhere." AND $where";
      } else {
        $where = $joinWhere;
      }
    }
    if(!empty($where)) {
      $where = "WHERE $where";
    }
      
    /* form of...SQL */
    
    if(!empty($tables)) {
      $sql .= $tables." ";
    }
    
    if(!empty($set)) {
      $sql .= $set." ";
    }
    
    if(!empty($from)) {
      $sql .= $from." ";
    }
    
    if(!empty($where)) {
      $sql .= $where;
    }
    
    if(!empty($sql)) {
      $sql .= ";";
    }
    
    /* all done */
    
    return $sql;
  }
  
  /**
   * 
   * toSQLSelect() - the toSQL() variation for SELECT
   * 
   * @return string - the SQL string.
   * 
   */
  
  public function toSQLSelect($countOnly=false) {
  	
    if(!$this->isReady()) {
      $this->error("Can not do toSQL() - object not ready.");
      return false;
    }
    
    /* before we get into it, make sure all the columns we
     * tried to use are valid.
     * 
     */	

    foreach($this->colsToCheck as $col) {
      if($this->resolveCol($col) === false) {
        $this->error("Can not do toSQL() - can not resolve col: $col");
        return false;
      }
    }
    
    /* we better have some tables to pull columns from! */
    
    if(count($this->joins) == 0) {
      $this->error("Can not do toSQL() - not tables!");
      return false;
    }
    
    /* ok try to calculate the various parts of the SQL statement... */
  	
    $where = "";
    $limit = "";
    $order = "";
    $group = "";
    $cols  = "";
    $from  = "";
    $cmt   = $this->cmt;
    
    $sql   = "";
  	
    /* figure out the kind of query, by default its select */
  	
    $sql .= $this->command." ";
    
    if($this->isDistinct) {
      $sql.= "DISTINCT ";	
    }
    
    /* figure out the colums we want */
  	
    /*
     * There is no way to force the data to automatically include
     * table name prefixes in the column names, various databases
     * might or might not do it at various times.  So we have to 
     * specify every column we want, and  quote/mangle it so that
     * it has table and column name in the column name.
     * 
     * For at least MySQL and PostgreSQL we just use:
     * 
     *   "<table>:<column>"
     *   
     * For each column to select.  When we get to pulling off the rows 
     * we can then determine which columns go into which objects.
     * 
     */

    if($countOnly !== false) {
    	
      $cols = "COUNT(*) AS count ";
      
    } else {
    
      /* 
       * considering what columns (or columns implied by tables) 
       * we need, and what output format we are using, get the 
       * set of columns we are going to fetch.
       * 
       */
    
      $needCols = $this->neededColumns();
    
      foreach($needCols as $colName) {
    
        if(preg_match('/\s+AS\s+/i', $colName)) {
          $cols .= $colName.",";
        } else {
          $alias = '"'.$colName.'"';
          $cols .= str_replace(':','.',$colName)." AS $alias,";
        }
      } 
    
      $cols  = trim($cols, ',');
    
      if(!empty($cols)) {
        $cols .= " ";
      }
    }
    
    /* figure out the table name list and the join conditions */
  	
    $tables = $this->queryTables();
    
    foreach($tables as $t) {
      $from .= "$t,";   
    }
    $from = "FROM ".trim($from, ',');
    
    $joinWhere = "";
    
    foreach($this->joins as $joinObj) {
    	
      foreach($joinObj->conds as $cond) {
      	
        if(!empty($joinWhere)) {
          $joinWhere .= " AND ";
        }
        $joinWhere .= $cond;
      }
    }

    /* figure out the where clause */
  	
    $where = $this->flattenWhere($this->whereExpr[0]);
    
    if(!empty($joinWhere)) {
  	  if(!empty($where)) {
        $where = $joinWhere." AND $where";
  	  } else {
  	  	$where = $joinWhere;
  	  }
    }
    if(!empty($where)) {
      $where = "WHERE $where";
    }
    
    if($countOnly === false) {
    	
      /* ordering decoration */
  	
      /*
       * TODO: double check that the order by columns 
       * are in select columns
       * 
       */
      
      if(count($this->orderCols) > 0) {
        $order = "ORDER BY ";
        foreach($this->orderCols as $col => $dir) {
          
          $dir = strtoupper($dir);
          
          if(empty($dir)) {
            $dir = 'ASC';
          }
          
          $order .= "$col $dir, ";	    	
        }
        $order = trim($order, ", ");	
      }
      
      /* grouping decoration */
    
      /*
       * TODO: double check that the group by columns 
       * are in select columns
       * 
       */
      
      if(count($this->groupCols) > 0) {
        $group = "GROUP BY ";
        foreach($this->groupCols as $col) {
          $group .= "$col, ";       
        }
        $group = trim($group, ", ");  
      }
      
  	  /* limit decoration */
 
      if($this->db->getType() == LMD_MYSQL) {
  		
        if(($this->pageStart > -1)&&($this->pageSize > -1)) {
      	  $limit = ($this->pageStart - 1).",".$this->pageSize;
        } else if($this->pageSize > -1) {
      	  $limit = $this->pageSize;
        }
    	
      } else if($this->db->getType() == LMD_PGSQL) {
  		
        if(($this->pageStart > -1)&&($this->pageSize > -1)) {
      	  $limit = $this->pageSize." OFFSET ".($this->pageStart - 1);
        } else if($this->pageSize > -1) {
      	  $limit = $this->pageSize;
        }
  	  }
  	  if(!empty($limit)) {
  	    $limit = "LIMIT $limit";
  	  }  
    }
  
    /* 
     * if there is a limit clause, but no order by clause, 
     * they are likely going to get unpredictable results.
     * We provide a warning but do not stop them.
     * 
     */
  	
  	if(!empty($limit) && empty($order)) {
  	  $this->warning("toSqlUpdate() - LIMIT is used without ORDER BY.");
  	}

    /* Avengers Assemble! */

    if(!empty($cmt)) {
      $sql .= "/* $cmt */ ";
    }

    if(!empty($cols)) {
      $sql .= $cols;
    }
    
    if(!empty($from)) {
      $sql .= $from." ";
    }
    
    if(!empty($where)) {
      $sql .= $where." ";
    }
    
    if(!empty($group)) {
      $sql .= $group." ";
    }
    
    if(!empty($order)) {
      $sql .= $order." ";
    }
    
    if(!empty($limit)) {
  	  $sql .= $limit." ";
    }
  	
    if(!empty($sql)) {
      $sql .= ";";
    }
    
  	return $sql;
  }
  
  /**
   * 
   * delete() - this action method will delete zero or more
   * rows in the primary table (the table this Query was created
   * relative to), based on the various where() clauses you
   * have added, also on any extend()'d tables you have joined
   * in.
   * 
   * If you have extend()'d (joined) tables, the delete will
   * do a cross-table delete, with the default join style of 
   * INNER, but only rows in the primary table will be deleted.
   * 
   * @return object an object describing the affected number
   * of rows. If there is a problem, then exactly false is
   * returned.
   * 
   */
  
  public function delete() {
    
    /* 
     * Delete whatever rows in primary table are filtered by
     * the exiting where() clauses.  In this implementaiton you
     * can access multiple tables during a delete,  but you can 
     * only actually delete from one table (the table the Query
     * is rooted in).
     * 
     * Note when they extend() to other tables we assume INNER JOIN,
     * at some point we can update this code to allow for other JOIN 
     * styles, INNER JOIN is just a lot simpler to generate.
     * 
     * reading on cross table delete syntax:
     * 
     *   http://www.electrictoolbox.com/article/mysql/cross-table-delete/
     *   http://www.postgresql.org/message-id/AANLkTik0=PnMF15WGQAFJbqPF5Qa42tADoYxzdH9w_OG@mail.gmail.com
     * 
     */ 
   
    /* good to go? */
    
    if(!$this->isReady()) {
      $this->error("Can not do delete() - object not ready.");
      return false;
    }
    
    $this->command = "DELETE";
    
    /* try to generate the SQL we need */
    
    $sql = $this->toSQL();
    
    if(empty($sql)) {
      $this->error("Can not do delete() - problem making SQL: ".$this->getError());
      return false;
    }
    
    /* do the query */
    
    $result = $this->db->query($sql);
      
    if($result === false) {
      $this->error("Can not do delete() - problem fetching set: ".$this->db->getError());
      return false;
    }
    
    $result = (object)array(
     "affected" => $this->db->getAffectedRows()
    );
    
    return $result;   
  }
  
  /**
   * 
   * update() - this action method will update zero or more
   * rows in the primary table (the table this Query was created
   * relative to), based on the various where() clauses you
   * have added, also on any extend()'d tables you have joined
   * in.
   * 
   * If you have extend()'d (joined) tables, the update will
   * do a cross-table update, with the default join style of 
   * INNER, but only rows in the primary table will be updated.
   * 
   * @return object an object describing the affected number
   * of rows. If there is a problem, then exactly false is
   * returned.
   * 
   */
  
  public function update($fields = array()) {
  	
    /*
     * $fields is an array of:
     *  
     *    <column name> => <value expression>
     *    
     * without parsing the epxression, we have no way of really 
     * knowing what <value expression> is, might be a column 
     * name, it might be an expression it might be whatever.  So
     * we treat it as "raw" input to the database.  This in 
     * inherently unsafe, but don't want to limit the layer above
     * us to literals.
     * 
     * Eventually we'll add prepared queries and parameter binding
     * to help secure things.
     * 
     * Note when they extend() to other tables we assume INNER JOIN,
     * at point we can update this code to allow for other JOIN 
     * styles, INNER JOIN is just a lot simpler to generate.
     * 
     */
  	
    /*
     * some details on update syntax:
     * 
     *   http://www.electrictoolbox.com/article/mysql/cross-table-update/
     *   http://stackoverflow.com/questions/18136370/updating-a-table
     *   
     */
  	
    /* good to go? */
    
    if(!$this->isReady()) {
      $this->error("Can not do update() - object not ready.");
      return false;
    }
  	
    if(count($fields) == 0) {
      return true;
    }
  	
    $this->command = "UPDATE";
  	
  	/* try to generate the SQL we need */
    
    $sql = $this->toSQL($fields);
    
    if(empty($sql)) {
      $this->error("Can not do update() - problem making SQL: ".$this->getError());
      return false;
    }
    
    /* do the query */
    
    $result = $this->db->query($sql);
      
    if($result === false) {
      $this->error("Can not do update() - problem fetching set: ".$this->db->getError());
      return false;
    }
  	
    $result = (object)array(
     "affected" => $this->db->getAffectedRows()
    );
    
    return $result;  	
  }
  
  /**
   * 
   * find() - search (with filtering) to return a set of
   * results in a QueryResult (Iterator) object.  You can
   * build more complex searches by using extend() to 
   * walk (join) to other tables, use methods like includeTable()
   * or select() to cherry pick tables and columns, and  
   * set the output format with methods like asArray() or
   * asObjects() (the default).
   * 
   * @param integer $start the row to start on, '1 based'
   * 
   * @param integer $limit how many rows to fetch from the 
   * start.
   * 
   * @return QueryResult - an array of objects or row data depending
   * on the query style selected.  Default format is objects.  If 
   * there was a problem, then exactly false is returned.
   * Use functions like asArray() or asObjects() to control the 
   * output formatting.
   * 
   * To avoid multiple trips to the database with complex
   * queries, use incluceTable() to tell the Query to pull
   * objects from related tables into the output results.
   * This is only possible when the output mode is object
   * based, but can be helpful in reducing database traffic.
   * 
   * When iterating through the results with QueryResult,
   * you can simply use the fetch() method to fetch matching
   * related  objects in the results for current primary 
   * object.
   * 
   * An example:
   * 
   *   $result = $query->where('first_name', 'like', 'mike')
   *     ->extend('event')
   *     ->asObjects()
   *     ->includeTable('event')
   *     ->find(1, 10);
   * 
   * Will return a QueryResult iterator object ($result),
   * which captures the results of looking in the primary
   * table where first_name is like 'mike' and force 
   * the output results to be object based.  The extend()
   * method joins the current table to the 'event' table, 
   * and the includeTable() method tells the query to 
   * pull out the related 'event' objects at the same
   * time, so we don't have to go back to the database
   * laster if we need them.
   * 
   * Now when iterating on the results:
   * 
   *   foreach($result as $pkey => $object) {
   * 
   *      // show the internal pseudo OID 
   *      
   *      echo "$pkey\n"; 
   *      
   *      // do something with the primary object
   *      
   *      $object->doWork();
   *      
   *      // fetch the related 'event' objects, in 
   *      // contect of this query...
   *      
   *      $events = $result->related($object, 'event');
   *      
   *      ...
   *      
   * The '$events' variable is an array of model objects
   * that are instances of the 'event' table.  Just like
   * $object is an instance of the model for the primary
   * table.
   * 
   * Because related object generally form a graph (they
   * aren't simply 1:1 always), the related() method can
   * be used to not just walk from $object to $events,
   * but also from any object in $events, to any of their
   * related objects...which may lead back to $object...
   * its a graph!
   * 
   * While this ability to pull related objects during
   * complex queries can avoid multiple queries and 
   * simplify your code, it also costs time to build 
   * up the additional results graph.
   * 
   * @return QueryResult - this method always returns
   * a QueryResult (iterator object) which can be used
   * like any PHP iterator, and directly in foreach 
   * loops etc.  The key/value data returned by QueryResult
   * depends on the output mode.
   * 
   * If there is a problem, then exactly false is returned.
   * 
   */
  
  public function find($start=-1, $limit=-1) {
  	
    if(!$this->isReady()) {
  	  $this->error("Can not do find() - object not ready.");
      return false;
  	}
  	
  	$this->command = "SELECT";
  	
  	/* 
  	 * if they specified paging, then override whatever 
  	 * the current paging setting is.
  	 * 
  	 */
  	
  	if($start != -1) {
      $this->pageStart = $start;
  	}
  	if($this->pageStart == 0) {
  	  $this->pageStart = 1;
  	}
  	
    if($limit != -1) {
      $this->pageSize = $limit;
  	}	
  	
  	/* 
  	 * if paging was requested, then we have to 
  	 * determine the total number of rows, because
  	 * we may be returning only a subset and can't
  	 * just count the results as the the total count.
  	 * 
  	 * This means doing two queries, and generally this 
  	 * is the expected way to get the total results 
  	 * value, some databases have special functions that
  	 * allow you to get the total at the same time, but
  	 * they don't always work, or have performance 
  	 * problems...so we do two queries...it works.
  	 * 
  	 */
  	
  	$numResults = -1;
  	
    if(($this->pageSize > -1)||($this->pageStart > -1)) {

      $sql = $this->toSQL(true);
      
      if(empty($sql)) {
      	$this->error("Can not do find() - problem making count() SQL: ".$this->getError());
        return false;
      }
      
      $result = $this->db->query($sql);
      
      if($result === false) {
      	$this->error("Can not do find() - problem counting result set: ".$this->db->getError());
        return false;
      }
      
      if(!isset($result[0]['count'])) {
      	$this->error("Can not do find() - no count for result set.");
        return false;
      }
      
      $numResults = $result[0]['count'];
    }
    
  	/* try to generate the SQL we need */
  	
  	$sql = $this->toSQL();
  	
    if(empty($sql)) {
      $this->error("Can not do find() - problem making SQL: ".$this->getError());
      return false;
    }

  	/* do the query */
  	
    $result = $this->db->query($sql);
      
    if($result === false) {
      $this->error("Can not do find() - problem fetching set: ".$this->db->getError());
      return false;
    }
      
  	/* format results as needed */
  	
    if($numResults < 0) {
      $numResults = count($result);
    }
 
    /* 
     * so now we are passing back an object that indidcates
     * the total matches,  the page co-ords (if applicable),
     * and the actual results.
     * 
     * The actual results can be objects, associative arrays,
     * or limited arrays that just have the object ids (for 
     * lazy loading mode).
     * 
     */
    
  	$results = (object)array(
  	  "total"     => $numResults,
  	  "pageStart" => ($this->pageStart < 0) ? 1 : $this->pageStart,
  	  "pageSize"  => ($this->pageSize < 0) ? $numResults : $this->pageSize,
  	  "pageNum"   => ($this->pageStart - 1) / $this->pageSize + 1,
  	  "results"   => array()
  	);
    
    /* format the actual results */
  	
  	$it = null;
  	 
    switch($this->outputStyle) {
    	
      case self::LAZY:
        {
          $it = $this->formatIDs($result);
        }
        break;
        
      case self::OBJECT:
        {
          $it = $this->formatObjects($result);
        }
        break;
        
      case self::ASSOC:
        {
          $it = $this->formatArray($result);
        }
        break;
    }
  	
    if($it !== null) {
    	
  	  /* configure the iterator to know where we are in the results */
          
      if($this->pageStart < 0) {
        $this->pageStart = 1;
      }
      if($this->pageSize < 0) {
        $this->pageSize = $numResults;
      }
      
      $it->total     = $numResults;
      $it->pageStart = ($this->pageStart < 0) ? 1 : $this->pageStart;
      $it->pageSize  = ($this->pageSize < 0) ? $numResults : $this->pageSize;
      $it->pageNum   = ($this->pageStart - 1) / $this->pageSize + 1;
    }
    
    return $it;
  }
  
  /**
   * 
   * formatIDs() - internal helper to setup a QueryResult
   * iterator object for lazy loading of objects.
   * 
   * @param array $results - results from the database.
   * 
   * @retuern QueryResult
   * 
   */
  
  private function formatIDs($results) {
  	
  	/*
  	 * just like formatArray() we are return rows of
  	 * column data, but its limited to the primary key
  	 * of the table this query is rooted in.
  	 * 
  	 */
  	
  	/* pass theresults directly into the iterator. */
  	
  	$it = new littlemdesign_db_QueryResult(
  	  self::LAZY, 
  	  (object)array(
  	    "rows" => $results
  	  )
  	);
    
    return $it;
  }
  
  /**
   * 
   * formatArray() - internal helper to setup a QueryResult
   * iterator object for associative array mode.
   * 
   * @param array $results - results from the database.
   * 
   * @retuern QueryResult
   * 
   */
  
  private function formatArray($results) {
  	
  	/* 
  	 * in array mode we put each column of a row 
  	 * into an array for the row, and then put that
  	 * row into an enclosing array to be able to
  	 * iterate over the rows. 
  	 * 
  	 * Column names include the tablename as a prefix,
  	 * so we can ensure columns are unique.
  	 * 
  	 * In this mode including additional tables is
  	 * not supported, we only show whatever columns 
  	 * they picked, so to get columns from other tables
  	 * include them in Query with select().
  	 * 
  	 */
  	
  	/* pass theresults directly into the iterator. */
  	
  	$it = new littlemdesign_db_QueryResult(
  	  self::ASSOC, 
  	  (object)array(
  	    "rows" => $results
  	  )
  	);
    
    return $it;
  }
  
  /**
   * 
   * formatObjects() - internal helper to setup a QueryResult
   * iterator object for full object mode.
   * 
   * @param array $results - results from the database.
   * 
   * @retuern QueryResult
   * 
   */
  
  private function formatObjects($results) {
  	
    $formatted = array();

    /* 
     * convert all the rows to a grouping so 
     * we can associate the related  objects with 
     * each other.
     * 
     */
    
    $objects = array();
    $roots   = array();
    
    foreach($results as $idx => $row) {
  		
      /* 
       * for each row must break the given columsn into
       * groups that we can read into objects.
       * 
       */
	
      $colMap  = array();
       
      foreach($row as $colName => $value) {
      	
        $matches = array();
        if(!preg_match('/^([^:]+):(.*)$/', $colName, $matches)) {
          continue;
        }

        $table = $matches[1];
        $col   = $matches[2];
        
        if(!isset($colMap[$table])) {
          $colMap[$table] = array();
        }
        
        $colMap[$table][$col] = $value;
      }
      
      /* make the objects */
      
      $linkables = array();
      
      foreach($colMap as $table => $data) {
         
        /* next object */
      	
        $object = littlemdesign_db_ORMTable::create($table);
        $key  = $object->getPKName();
      
        if(!isset($data[$key])) {
      	  $this->warning("formatObjects() can't find primary key for data: ".print_r($data,true));
          continue;
        }
        
        /* either use the existing object, or fill a new one */
        
        $id   = $data[$key];
        $pkey = "$table:$id";
        
        /* fill in the object the first time */
        	
        if(!isset($objects[$pkey])) {
          
          if(!$object->bulkSet($data)) {
      	    $this->error("formatObjects() can't format main object data: ".print_r($data,true));
            continue;
          }
          
          $objects[$pkey] = (object)array(
            "object" => $object,
            "links"  => array()
          );
          
        } else {
        	
          /* 
           * we already have this object, so use the one 
           * we already ahve.
           * 
           */
        	
          unset($object);
          $object = $objects[$pkey]->object;
        }
 
        /* 
         * track which objects we are going to link together
         * from this row of data.
         * 
         */
        
        $linkables[$pkey] = $object;
        
        /* 
         * track the table that is the primary one we are 
         * interested in.
         * 
         */
        
        if($table == $this->tableName) {
          if(!isset($roots[$pkey])) {
            $roots[$pkey] = $objects[$pkey];
          }
        }
      }
      
      /*
       * now that we got all the ojects for this row, link them 
       * together.
       * 
       */
        
      foreach($linkables as $pkey1 => $object2) {
      	
        foreach($linkables as $pkey2 => $object2) {
        
          if($pkey1 == $pkey2) {

          	/* don't link to self */
          	
          	continue;
          }

          $outgoing =& $objects[$pkey1]->links;
          $incoming =& $objects[$pkey2]->links;
          
          if(!isset($outgoing[$pkey2])) {
          	$outgoing[$pkey2] = $pkey2;
          }
          if(!isset($incoming[$pkey1])) {
          	$incoming[$pkey1] = $pkey1;
          }
        }
        
      } /* end of linking */
     
    } /* end of row walk */

    /* 
     * at this point the $roots list is our formatted result,
     * each object has a pointer to one of the primary objects,
     * and the various related objects implied by the context
     * of this query, if they were included.
     * 
     */

    $it = new littlemdesign_db_QueryResult(
      self::OBJECT, 
      (object)array(
        "objects" => $objects,
        "roots"   => $roots
      )
    );
    
    return $it;
  }

}

?>