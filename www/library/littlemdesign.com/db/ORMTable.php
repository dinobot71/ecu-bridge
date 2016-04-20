<?php

/**
 * 
 * db \ ORMTable - to provide basic support for Object Relational
 * Mapping (ORM); mapping PHP objects to database tables, we provide
 * this base class for all "DB Classes" to use.  Any class that maps
 * directly to a table in the database should be a sub-class of 
 * ORMTable. 
 * 
 * We do not intend to take ORM to the extreme here; we only want to 
 * make access and update of objects easier and cleaner.  We may later
 * add other ORM related support classes for query support, etc.
 * This base class is intended to just provide some reasonable out of 
 * the box support.  This is a light-weight ORM implementation.
 * 
 * Auto-loading Model classes
 * 
 * For details on auto-loading of classes in general see the 
 * autoloader.php file at the top of the littlemdesign library.
 * 
 * Model class auto-loading occurs when a Model class is needed 
 * to fetch data from some table in the database), the auto-load 
 * method is as such:
 * 
 *   1) The class name will be model_<table name>, where
 *      <table name> will be in lower case, with any spaces or
 *      underscores removed.  
 *      
 *   2) In keeping with class naming conventions of the littlemdesign
 *      library, the class name suffix implies a folder/file name 
 *      like this:
 *      
 *         model\<table name>.php
 *         
 *      the built in auto-loader will then try look for this
 *      sub-folder and file name in each of the auto-loader 
 *      search paths.  For details on all auto-loader search paths
 *      see the top-level autoloader.php file in the littlemdesign
 *      library folder.
 *      
 *      Model class search path prefixes (by default) will include 
 *      some useful locations.  If you installed the littlemdesign 
 *      library like this:
 *      
 *        <base dir>\library\autoloader.php
 *        <base dir>\library\db\
 *        ...
 *      
 *      Then the following search path prefixes will be used for 
 *      finding model classes, that is - classes that have \model\ in their
 *      implied namespace:
 *      
 *        <base dir>\schema\model\<table name>.php
 *        <base dir>\classes\model\<table name>.php
 *        <base dir>\model\<table name>.php
 *        <base dir>\*\model\<table name>.php
 *        <base dir>\*\schema\model\<table name>.php
 *        <base dir>\*\classes\model\<table name>.php
 *        
 *      If find better default paths, we can later add them to the 
 *      auto-loader.
 *      
 *      Note that the .php extension can be one of:
 *      
 *        .php
 *        .inc
 *        .class.php
 * 
 *   3) the model class inside <table name>.php must 
 *      have the exact class name:
 *      
 *         Model_<table name>
 *         
 *      Where <table name> has any spaces or underscores 
 *      removed.
 *      
 *   4) We don't currently support a table of the same 
 *      name in more than on database.  This coule be
 *      supported in the future by extending the automatic
 *      class name to something like:
 *      
 *        Model_<databse>_<table>
 *        
 *      and having appropriate search folders to allow for 
 *      grouping.  For now we as going to assume single 
 *      database.
 *      
 * Although this approach requires users to name their model
 * classes in a specific way, it is still very desirable, the
 * alternative is to require users to explicitly register 
 * which classes map to which tables, or to have to jump through
 * (many) hoops to define a schema...all such effort is typically
 * prone to error, and becoming out of date.  
 * 
 * This simple requirement of how to name the class, and storing 
 * it in some reasonably well known locations avoides all of 
 * the cmoplexity and problems we would otherwise have to deal 
 * with.
 * 
 * Working with ORB sub-classes:
 * 
 * - Your sub-class constructor should set $this->fieldTypes to be an
 *   array that maps the table column names to a simple type (like the
 *   types used in createTable() of db\SQLDatabase).  For example:
 *   
 *     $this->fieldTypes = array("column1" => "text");
 *     
 *   This is primary way of defining both what the columns are and what
 *   type conversion (if any) should be done going into and coming out 
 *   of the database.  Be sure to set $this->fieldTypes in your sub-class.
 *   
 * - fields (columns) are easily worked with, just use the normal -> 
 *   operator on your object.  Fields will not be auto loaded or auto saved
 *   per access.  If you haven't loaded the fields yet with restore()
 *   you'll get null values.  Likewise, nothing is saved back to the 
 *   database until you call explicitly call save().
 * 
 * - For quick delete of a row just call destroy()
 * 
 * - All model objects can be created from the static create() factory method.
 *   This is possible because we support auto-loading of model classes,
 *   for the table we want an object for, and because we can work wi
 *   the "default database connection".  For details on auto-loading
 *   of model classes see the above note on auto-loading.
 *   
 *   For database connections; all methods that requre a database
 *   connection, constructing an ORMTable object or using the 
 *   create() factory method, can take an explicit database connection
 *   parameter, but if you don't provide the database connection
 *   details, then ORMTable will still work by making use of the
 *   default database connection.  That is, if there is one and only
 *   one database connection in the database connection pool...it
 *   will be used.  For details on default connections see the 
 *   db\SQLFactory::connect() method.
 *   
 *   This ability to use the default database connection means that 
 *   when you use the factory or create an object you don't have
 *   to sprinkle your code with database connection details, and
 *   your script can be faster, because you just keep reusing the 
 *   same connection.
 *   
 *   But, if you really do need to use multiple database connections,
 *   then you can still provide the database to connect to, in the 
 *   ORMTable constructor and in the create() factory method.
 *   
 * Some reading:
 * 
 *   http://troels.arvin.dk/db/rdbms/
 *   http://guides.rubyonrails.org/association_basics.html
 *   http://www.slideshare.net/rob_knight/object-relational-mapping-in-php
 *   http://www.webgeekly.com/tutorials/php/the-benefits-of-using-object-relational-mapping-in-php/
 *   http://en.wikipedia.org/wiki/List_of_object-relational_mapping_software#PHP
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
autorequire('littlemdesign_db_SQLDatabase');
autorequire('littlemdesign_db_SQLFactory');

/**
 * 
 * ORMTable - base class for all classes that have a direct
 * mapping to a table in our database.
 *
 */

class littlemdesign_db_ORMTable
  extends littlemdesign_util_Object {

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
   * The table this object maps to.
   * 
   * @var string
   * 
   */
  
  protected $tableName;
  	
  /**
   * 
   * Sub-classes must define a list of field types so that
   * we know how to serialize and unserialize object data.
   * 
   * @var array
   * 
   */
  
  protected $fieldTypes;
  
  /**
   * 
   * The fields of this object that mirror the colulmns 
   * in the table in the database.  Sub-classes won't 
   * normally access $fields directly, they should just
   * use the PHP -> operator to access fields.
   * 
   * Note: to avoid name collisions with local table
   * columns, we not allow related models to be walked via 
   * the -> operator.  THis is less convenient; you 
   * would have to call belongsTo('parent') instead of
   * just doing $this->parent to get the belongs to 
   * model...but its better to not have to worry about
   * name collisions where possible.
   * 
   * @var array
   * 
   */
  
  protected $fields;
  
  /**
   * 
   * The various relation mappings, these mappings allow 
   * sub-classes to indicate how this model relates to other
   * models in the database.  We follow the association 
   * system used in Ruby (http://guides.rubyonrails.org/association_basics.html),
   * it seems reasonable and is used by various other ORM 
   * kits.
   * 
   * Each relation kind specification is an associative array 
   * that maps the other model name to a set of options 
   * for the relationship for that model.  For example
   * what key name to use if not the default.
   * 
   * If a given kind of relation exists between this model
   * and more than one other model, then the associative map
   * will have multiple key/option mappings.
   * 
   */
  
  protected $relBelongsTo           = array();
  protected $relHasOne              = array();
  protected $relHasMany             = array();
  protected $relHasManyThrough      = array();
  protected $relHasOneThrough       = array();
  protected $relHasAndBelongsToMany = array();  

  const BELONGS_TO              = 1;
  const HAS_ONE                 = 2;
  const HAS_MANY                = 4;
  const HAS_MANY_THROUGH        = 8;
  const HAS_ONE_THROUGH         = 16;
  const HAS_AND_BELONGS_TO_MANY = 32;
  
  /**
   * 
   * All ORM objects have a primary key that is unique
   * within their table.
   * 
   * @var integer
   * 
   */
  
  protected $id;
  
  /**
   * 
   * The name we are using for the primary key, by default
   * it is usually 'id'.
   * 
   * @var string
   * 
   */
  
  protected $idName;
  
  /**
   * 
   * Mark an ORM object as modified.
   * 
   * @var boolean
   * 
   */
  
  protected $dirty;
  
  /**
   * 
   * Standard cosntructor, you must provide the name of a valid table
   * in the database that this object relates to.  When specifying the
   * database connection ('$db'); you can provide a pseudo URL/DSN
   * (\db\DBConnectArgs), or an actual instance of SQLDatabse, or nothing.
   * If you provide nothing for the database (null), if there is one
   * and only active database connection (\db\SQLFactory)...then we use
   * that.
   * 
   * This allows sub-classes to not worry about the database details, as 
   * long as at least one active connection was generated from the database
   * connection factory.  Eventually SQLFactory will be extended to be 
   * able to create at least one connection if none-exist, based on ".ini"
   * system settings.
   * 
   * IMPORTANT: all sub-classes must set $this->fieldTypes to be a map
   * of column name to column type, where the type is similar to the 
   * types used in createTable() (\db\SQLDatabase), so that we know 
   * how to load/save objects and be type aware.  Setting of $this->fieldTypes
   * should be done directly in sub-class constructors.
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
  
  public function __construct($tableName, $className='ORMTable', $db=null, $logger=null) {

  	parent::__construct($className, 'littlemdesign_db', true);
  	
    $this->db         = null;
    $this->tableName  = strtolower(trim($tableName, " _"));
    $this->id         = -1;
    $this->idName     = 'id';
    $this->dirty      = false;
    $this->fieldTypes = array();
    $this->fields     = array();
    
    /*
     * NOTE: because we don't set fieldTypes on construction, we can't
     * actually load/save an object until the sub-class sets the fieldTypes,
     * so sub-classes should do that imediately in their constuctor.
     * 
     */
    
    $this->unReady();
    
    if($this->fields !== null) {
      if(!is_array($this->fields)) {
      	$this->error("ORMTable bad construction, expecting array for fields.");
        return ;
      }
    }
    
    /* figure out the database connection */
    
    if(!($db instanceof littlemdesign_db_SQLDatabase)) {
      
      $instance = littlemdesign_db_SQLFactory::createManaged($db);
      
      if($instance === false) {
      	$this->error("ORMTable could not create a managed db instance.");
      	return ;
      }
      
      if(!$instance->isReady()) {
      	$this->error("ORMTable created a db instance, but its not usable.");
      	return ;
      }
      
      $this->db = $instance;
      
    } else {
    	
      $this->db = $db;
      
      if(!$this->db->isReady()) {
      	$this->error("ORMTable db instance is not usable.");
      	return ;
      }
    }
    
    /* make sure the table exists */
    
    if(!$this->db->tableExists($this->tableName)) {
      $this->error("ORMTable bad construction, invalid table: ".$this->tableName);
      return ;
    }
    
    /* auto-detect the fields in the table */
    
    /*
     * we are not going to auto-detect the field types; we want the 
     * field/column types to be provided explicitly by the sub-class
     * so that they are types that are similar to what was used in
     * createTable() of \db\SQLDatabase. 
     * 
     * This ensures that a column type like 'boolean' is boolean 
     * regardless of database, and not boolean in one and TinyInt in
     * another.
     * 
     * 
     
    if($this->fieldTypes === null) {
    	
      $this->fieldTypes = $this->db->columnsOfTable($this->tableName);
      
      if($this->fieldTypes === false) {
        $this->fieldTypes = null;
        $this->error("ORMTable bad construction, can not auto-detect (".$this->tableName.") fields.");
        return ;
      }
    }
    
    *
    */
       
    /* ok, should be safe to use */
    
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
   * tableExists() - check to  see if $tableName is actually 
   * a table in the databse.
   * 
   * @param string $tableName
   * 
   * @return boolean we return true if the given table
   * exists.
   * 
   */
  
  public function tableExists($tableName) {
  	
    if(!$this->isReady()) {
      $this->error("Can not tableExists($tableName), object is not ready.");
      return false;
    }
  	
    if(!$this->db->tableExists($tableName)) {
   	  return false;
    }
    
    return true;
  }
  
  /**
   * 
   * getPK() fetch the primary key of this object.  Any
   * value of 0 or less indicate the object isn't in 
   * the table yet.
   * 
   * @return integer the current primary key of this 
   * object.
   * 
   */
  
  public function getPK() {
    return $this->id;	
  }
  
  /**
   * 
   * getPKName() get the name of the primary key for this 
   * table.  By default the primary key is presumed to be
   * named 'id'.
   * 
   * @return string the name of hte primary key in this 
   * table.
   * 
   */
  
  public function getPKName() {
    return $this->idName;	
  }
  
  
  /**
   * 
   * getTableName() get the name of the table this model
   * class maps to.
   * 
   * @return string the underlying table name.
   * 
   */
  
  public function getTableName() {
    return $this->tableName;	
  }
  
  /**
   *
   * cleanTableName() give a potential name for a table 
   * in the database, do any cleanup/formatting on it and
   * return the result.
   * 
   * @param string $trableName a table name
   * 
   * @return string formatted table name.
   * 
   */
  
  static public function cleanTableName($tableName) {
  	
  	$t = strtolower(trim($tableName));
    $t = preg_replace('/[ _]+/', '', $tableName);
    
    return $t;
  }
  
  public function getDB() {
    return $this->db;  
  }
  
  /**
   * 
   * getId() fetch the id of this object inside its model 
   * (row in the table).
   * 
   * @return integer the row id of this object.
   * 
   */
  
  public function getId() {
  	return $this->id;
  }
  
  /**
   * 
   * clearId() - anonymize the object so the next time it gets
   * saved, it will create a new object.  
   * 
   */
  
  public function clearId() {
    $this->id = -1;
  }
  
  /**
   * 
   * __set() - when we try to change the value of one of
   * the virtualized fields, that don't actually exist in
   * this object, but are columns in the database table, then 
   * we update $this->fields as appropriate.
   * 
   * @param string $name
   * @param mixed $value
   * 
   * @return void
   * 
   */
  
  public function __set($name, $value) {
  	
  	$this->fields[$name] = $value;
  	$this->dirty = true;
  	
  	return $this;
  }
  
  /**
   * 
   * __get() - when we try to fetch the value of a virtualized
   * field, that  doesn't exist in this object, but is a colum
   * in the database table, then we fetch the value from $this->fields.
   * If the given field doesn't exist yet, we set it to null 
   * and return that.  If they access fields that aren't in 
   * the database table...they will just be ignored later, 
   * because we have $this->fieldTypes to tell us which columns
   * to save/restore.
   * 
   * @param string $name
   * 
   * @return mixed the field value.
   * 
   */
  
  public function __get($name) {
  	
  	if(!isset($this->fields[$name])) {
  	  
  	  /* 
  	   * if it was never set yet, then add it...its ok to 
  	   * do this, becuase we have $this->fieldTypes to tell
  	   * us exacctly which columns to save/restore.  So 
  	   * if they give a bogus field, it will just be ignored.
  	   *  
  	   */
  		
  	  $this->fields[$name] = null;
  	  
  	}
  	
  	return $this->fields[$name];
  }
  
  /**
   * 
   * __isset() test to see if a field is set yet.
   * 
   * @param string $name
   * 
   * @return boolean return exactly false if the field
   * is not set yet.
   * 
   */
  
  public function __isset($name) {
  	return $this->fields[$name];
  }
  
  /**
   * 
   * __unset() clear a field.  Because these are virtual
   * columns in table in the database, we can't "unset",
   * but we can clear them.
   * 
   * @param string $name
   * 
   */
  
  public function __unset($name) {
  	$this->__set($name, null);
  	return $this;
  }
  
  /**
   * 
   * bulkSet() - if you have in hand all the fields (named
   * as they should be for this object), including the 
   * primary key, you can use this method to fill or "hydrate"
   * the object.  Essentially overwriting whateve is already
   * here.
   * 
   * @param array $data
   * 
   * @return boolean return exactly false if there is a problem.
   * 
   */
  
  public function bulkSet($data) {

    $key = $this->getPKName();
    
    if(!isset($data[$key])) {
  	  $this->error("bulkSet() - input data doesn't include primary key.");
  	  return false;
  	}
  	
  	/* clear */
  	
  	$this->id         = $data[$key];
    $this->dirty      = false;
    $this->fields     = array();
    
    foreach($data as $name => $value) {
      
      if(!isset($this->fieldTypes[$name])) {
      	continue;
      }
      
      $this->fields[$name] = self::dbToPHP($name, $value);
    }

    return true;
  }
  
  /**
   * 
   * getKey() - fetch the row id for this object.
   * 
   * @return integer the row id of this object.
   * 
   */
  
  public function getKey() {
    return $this->id;
  }
  
  /**
   * 
   * getFields() return an an associative array of 
   * our virtualized fields, name mapped to value.  Each 
   * field maps directly to a column in the table that
   * this object is related to.
   * 
   * @return array the virtualized fields.
   * 
   */
  
  public function getFields() {
  	return $this->fields;
  }
  
  /**
   * 
   * save() save this object back to the database, 
   * once saved, mark it as clean.  If id is < 0,
   * and the object itself doesn't have an id yet,
   * then we save and set the objects id to the row
   * that was added.  Return the id of the updated 
   * row.
   * 
   * When we save, we save object properties that 
   * have the same names as those in $fieldTypes.
   * 
   * @param integer $id the database row to save to.
   * 
   * @return integer the id of the new/updated row.
   * return exactly false if there was a problem.
   * 
   */
  
  public function save($id=-1) {
  	
  	/* check the inputs */
  	
  	if(empty($this->tableName)) {
  	  $this->error("Can not save, no table name.");
  	  return false;	
  	}
    if(count($this->fieldTypes)==0) {
  	  $this->error("Can not save, no columns defined.");
  	  return false;	
  	}
  	if(!is_numeric($id)) {
  	  $this->error("Can not save, non-numeric id: $id");
  	  return false;
  	}
  	if($id <= 0) {
  	  $id = $this->id;
  	}
  	
    if(!$this->isReady()) {
      $this->error("Can not save, object is not ready.");
      return ;
    }
  
    if((!$this->dirty) && ($id==$this->id) && ($id>0)) {
  		
  	  /* unnecessary */
  	
      $this->info("not saving, not needed.");
  	  return $id;
  	}

  	$rowId = $id;
  	
  	$cols = "";
    $vals = "";
    $updt = "";
      
    /* get the columns and the values for each column */
    
    foreach($this->fieldTypes as $name => $type) {
    	
      $cols .= ','.$name;
      
      $datum = '';
      if(isset($this->fields[$name])) {
        $datum = $this->fields[$name];
      }
      
      /* 
       * do any conversion for data being stored in 
       * the databse.
       * 
       */
      
      switch($type) {
      	
      	case 'boolean':
      	  {
      	    if(($datum === false)||(!strcmp($datum,'0'))||(!strcmp($datum,'false'))||(!strcmp($datum,'f'))||empty($datum)) {
      
      	      $datum = '0';
      	       
      	    } else {

      	      $datum = '1';
      	    }
      	  }
      	  break;
      	 
        case 'text':
          {
            $datum = $this->db->escapeLiteral($datum);
            
            if($datum === false) {
              $this->error("Can not save, SQL problem with text quoting: ".$this->db->getError());
              return false;
            }
          }
          break;
      		
      	case 'timestamp':
          {
            if(is_numeric($datum)) {
      		    $datum = date('Y-m-d H:i:s', $datum); 
            }
          }
          break;
      	  
      	default:
      	  break;	
      }

      $vals .= ",'$datum'";
      
      $updt .= ",$name="."'$datum'";
    }
    
    $cols = '('.ltrim($cols, ',').')';
    $vals = '('.ltrim($vals, ',').')';
    $updt = ltrim($updt, ',');

    /* update database */
  
    if($id <= 0) {
    	
      /* insert a new object */
    	
      $this->info("Creating $id (".$this->tableName.")");
      
      $sql = "INSERT INTO ".$this->tableName." $cols VALUES $vals;";
    	
      $result = $this->db->query($sql);
      if($result === false) {
      	$this->error("Can not save, SQL problem: ".$this->db->getError());
      	return false;
      }
      
      /* get the affected row id */
      
      $rowId = $this->db->getLastId();
    
    } else {
    	
      /* update an existing object */
    	
      $this->info("Saving $id (".$this->tableName.")");
      
      $sql = "UPDATE ".$this->tableName." SET $updt WHERE ".$this->getPKName()."='$id';";
      
      $result = $this->db->query($sql);
      if($result === false) {
      	$this->error("Can not save, SQL problem: ".$this->db->getError());
      	return false;
      }
    }
    
    /* house keeping */
    
    if($rowId == $this->id) {
      
      /* we just saved an existing object */
    	
      $this->dirty = false;
    }
    
    if($this->id <= 0) {
    	
      /* we just created a new object */
    	
      $this->id = $rowId;
    }
  	
    /* pass back the modifed row id */
    
    return $rowId;
  }
  
  /**
   * 
   * dbToPHP() - convert a single table column value 
   * to a PHP value.
   * 
   * @param $name the name of the field
   * 
   * @param $value the value of the field
   * 
   * @return mixed the converted value as it should be
   * in PHP.
   * 
   */
  
  protected function dbToPHP($name, $value) {
  	
    if(!isset($this->fieldTypes[$name])) {
      return false;
    }
      
    $datum = $value;
      
    if(!isset($this->fieldTypes[$name])) {
    	
      /* no such field */
    	
      return false;	
    }
    
    /* 
     * do any type conversionon the way out of teh
     * database.
     * 
     */
      
    switch($this->fieldTypes[$name]) {
      	
      case 'boolean':
        {
      	  if(($datum === false)||($datum == '0')||($datum=='false')||($datum=='f')||empty($datum)) {
      	    $datum = false;
      	  } else {
      	    $datum = true;
      	  }
      	}
      	break;
      	
      case 'text':
        {
          $datum = stripslashes($datum);
        }
      	  
      default:
        break;	
    }
    
    /* pass back the converted value */
    
    return $datum;
  }
  
  /**
   * 
   * restore() restore the row (id) from the 
   * database into this object. If id is < 0,
   * and this object itself has no id either, 
   * then we fail.  Otherwise restore the fields
   * into the properties of this object that 
   * have the same name, and then set (and 
   * return) this objects id to the id that
   * was used.
   * 
   * @param integer $id the database row to save to.
   * 
   * @return integer the id of the row we updated 
   * from.
   * 
   */
  
  public function restore($id=-1) {
  	
  	/* check the inputs */
  	
    if(!is_numeric($id)) {
  	  $this->error("Can not restore, non-numeric id: $id");
  	  return false;
  	}
  	if($id <= 0) {
  	  $id = $this->id;
  	}
  	if($id <= 0) {
  	  $this->error("Can not restore, no row id.");
  	  return false;
  	}
  	if(empty($this->tableName)) {
  	  $this->error("Can not restore, no table name.");
  	  return false;	
  	}
    if(count($this->fieldTypes)==0) {
  	  $this->error("Can not restore, no columns defined.");
  	  return false;	
  	}
  	
    if(!$this->isReady()) {
      $this->error("Can not restore, object is not ready.");
      return ;
    }
    
  	$this->info("Restoring $id (".$this->tableName.")");
  	
  	/* select out the row for this object */
  	
  	$sql = "SELECT * FROM ".$this->tableName." WHERE ".$this->getPKName()."='$id';";
    $result = $this->db->query($sql);
    if($result === false) {
      $this->error("Can not restore, SQL problem: ".$this->db->getError());
      return false;
    }
    
    if(empty($result)) {
      $this->error("Can not restore, no match.\n");
      return false;
    }
    
    /* harvest */
    
    $row = $result;
    if(is_array($result)) {
      $row = $result[0];
    }
    

    foreach($row as $name => $value) {
      
      if(!isset($this->fieldTypes[$name])) {
      	continue;
      }
      
      $this->fields[$name] = self::dbToPHP($name, $value);
    }
    
    $this->dirty = false;
    	
  	/* all done */
  	
  	$this->id = $id;
  	
  	return $this->id;
  }
  
  /**
   * 
   * destroy() - remove the given row (id) from the
   * table in the database.  If $id < 0, then try 
   * to use the id of this object...if that isn't 
   * set either then we fail.  When done, we set
   * the id of this object to -1 (if it was the one
   * we destroyed) and return true.
   * 
   * @param integer $id the row to delete
   * 
   * @return boolean return exactly false if there 
   * was a problem.
   * 
   */
  
  public function destroy($id=-1) {
  	
  	/* check the inputs */
  	
    if(!is_numeric($id)) {
  	  $this->error("Can not destroy, non-numeric id: $id");
  	  return false;
  	}
  	if($id <= 0) {
  	  $id = $this->id;
  	}
  	if($id <= 0) {
  	  $this->error("Can not destroy, no row id.");
  	  return false;
  	}
  	if(empty($this->tableName)) {
  	  $this->error("Can not destroy, no table name.");
  	  return false;	
  	}
  	
    if(!$this->isReady()) {
      $this->error("Can not destroy, object is not ready.");
      return ;
    }
      
  	$this->info("Destroying $id (".$this->tableName.")");
  	
  	/* do the deletion */
  	
  	$sql = "DELETE FROM ".$this->tableName." WHERE ".$this->getPKName()."='$id';";
    $result = $this->db->query($sql);
    if($result === false) {
      $this->error("Can not restore, SQL problem: ".$this->db->getError());
      return false;
    }
  	
  	/* house keeping */
  	
  	if($id == $this->id) {
  		
  	  /* just deleted self, have to invalidate */
  		
  	  $this->id         = -1;
  	  $this->dirty      = false;
  	  $this->fields     = array();
  	  $this->fieldTypes = array();
  	  
  	  $this->unReady();
  	}
  	
  	return true;
  }
  
  /*
   * getFieldTypes() - fetch the definition of columns
   * for this  table/mdoel.
   * 
   * @return array the column name => type mapping for 
   * this table/model.
   * 
   */
  
  public function getFieldTypes() {
    return $this->fieldTypes;
  }
  
  /* 
   * hasColumn() - quick test if a given column exists 
   * in this table.
   * 
   * @param string $colName the column name.
   * 
   * @return boolean return true if this column is 
   * in this table/model.
   * 
   */
  
  public function hasColumn($colName) {
  	
  	if(!isset($this->fieldTypes[$colName])) {
      if(isset($this->fieldTypes[strtolower($colName)])) {
        return true;
      }
  	  return false;
  	}
  	
  	return true;
  }
  
  /**
   * 
   * autoload() - take the steps to autoload the given model and
   * bring its symbols into the global namespace. 
   * 
   * @param string $tableName - the name of the table this object 
   * relates to
   * 
   * @return boolean - return exactly false if we can't autoload.
   * 
   */
  
  static public function autoload($tableName) {
    
    $modelClass = strtolower(trim("model_$tableName", " _"));
    
    /* 
     * ok let the auto-loder kick in, if they didn't provide a $db, 
     * then we rely on them having called SQLFactory::connect() at
     * some point before this.  At some point we'll make 
     * SQLFactor::connect() be able to load the database connection
     * parameters from the preferences (the system .ini file)
     * 
     */
    
    $obj = new $modelClass(-1,null,null);
    if(!is_object($obj)) {
      return false;
    }
    unset($obj);
    
    return true;
  }
  
  /**
   * 
   * create() - factory to create new objects based on tables in 
   * the database.  This method relies on auto-loading of model
   * classes (see notes at top of file).  Make sure you name your
   * classes correctly and place them in one of the default search
   * path locations.  This method is essentially the same as 
   * a constructor call, except that you can call it statically
   * and you direct which class to instantiate by indicating the
   * table name.  We may in the future extend this to support
   * (database,table) instead of just table.
   * 
   * The other parameters are optional, they allow you full control
   * if you really want it.
   * 
   * Your ORMTable sub-class constructor will be called like this:
   * 
   *   __construct($id, $db, $logger);
   *   
   * Your sub-class constructor is expect to call the ORMTable 
   * parent constructor idicatign table and class name, as well
   * as set $this->fieldTypes (see the notes above for ORMTable
   * __construct).  
   * 
   * Model classes are not cached; each call to create() produces
   * an entirely new class.
   * 
   * @param string $tableName - the name of the table this object 
   * relates to
   * 
   * @param string $className - name of the class to use for logging 
   * and logging filtering purposes.  This is not, and will not be used
   * for anything related to model class auto-loadeding.
   * 
   * @param integer $id - the row id of the row to auto-load into the 
   * new object imediately.
   * 
   * @param mixed $db - the database connection, if you don't provide
   * either a connection pseudo URL or a an actual database (\db\SQLDatabase),
   * an attempt will be made to use the one already established if there is 
   * one and only one already in the connection pool.
   * 
   * @param LogWriter $logger - can optionally specify the logger to use.
   * 
   */
  
  static public function create($tableName, $id=-1, $db=null, $logger=null) {

    if(empty($tableName)||($tableName===null)) {
      return false;
    }
    
  	$modelClass = strtolower(trim("model_$tableName", " _"));
  	
  	/* 
  	 * ok let the auto-loder kick in, if they didn't provide a $db, 
  	 * then we rely on them having called SQLFactory::connect() at
  	 * some point before this.  At some point we'll make 
  	 * SQLFactor::connect() be able to load the database connection
  	 * parameters from the preferences (the system .ini file)
  	 * 
  	 */
  	
  	$obj = new $modelClass($id,$db,$logger);

  	
  	/* 
  	 * pass back the model object, if its not ready...user will 
  	 * have to deal with it.
  	 * 
  	 */
  	
  	return $obj;
  }
  
  /**
   * 
   * find() - the primary way to select rows from the table. for
   * this model.
   * 
   * @param mixed $where use this where clause to select which 
   * row in the table you want.  If you provide a string it will
   * be taken literally as the "WHERE ..." clause o the select.
   * If you provide a WhereFilter, then the WhereFilter will be
   * used to generate the appropriate SQL for the WHERE clause.
   * 
   * @return array will return an array of matching objects, 
   * an empty array on no match and exactly false on error.  Items
   * in the array are instances of model objects for this table.
   * 
   */
  
  public function find($where="") {
  	
    if(!$this->isReady()) {
      $this->error("Can not do find, object is not ready.");
      return false;
    }

    $whereClause = "";
    
    if(is_object($where)) {
    	
      /* 
       * convert WhereFilter to string *?
       * 
       * TODO:
       * 
       */
    	
    } else if(is_string($where)) {
    	
      $whereClause = $where;
      
    } else {
    	
      $this->error("Can not do find, don't understand given wehre clause.");
      return false;
    }
    
    $tableName = strtolower(trim($this->tableName));
    $tableName = preg_replace('/[ _]+/', '', $tableName);
    
    if(empty($tableName)) {
      $this->error("Can not find, no table name.");
      return false;  	
    }
    
    /* select out the rows we want */
  	
  	$sql = "SELECT * FROM $tableName $whereClause;";
    $result = $this->db->query($sql);
    if($result === false) {
      $this->error("Can not find, SQL problem: ".$this->db->getError());
      return false;
    }
    
    if(empty($result)) {
      return array();
    }
    
    /* harvest */
    
    $results = array();
    
    foreach($result as $row) {
    	
      /* clone an object to use */
    	
      $obj = self::create($tableName);
      if(($obj === false)||($obj->isReady() === false)) {
        $this->error("Can do find, model ($tableName) not usable");
        return false;	
      }
      
      /* fill it */
      
      $this->rowToObj($obj, $row);
      
      /* save it */
      
      $results[] = $obj;
    }
    
  	/* all done */
  	
  	return $results;
  }
  
  /**
   * 
   * getOptions() - this helper function is used internally
   * to fetch the options for a relationship between models,
   * it first tries to set reasonable defaults, and then if
   * you have configured overrides, they are used.  You must
   * pass in the numeric code (such as ORMTable::BELONGS_TO)
   * to indicate which relation to get options for.
   * 
   * @param integer $kind the kind of relation to get the 
   * options for.
   * 
   * @param string $tableName you must indicate the table 
   * (model) that the relation is for.
   * 
   * @return array returns exactly false if there is a problem.
   * Otherwise return the merged set of options for the given 
   * relation.
   * 
   */
  
  public function getOptions($kind, $tableName) {
  	
  	/*
  	 * TODO:
  	 * 
  	 * To allow for self joins, we need to allow 
  	 * relations to declare an alias table name,
  	 * so that if they say for example hasOne['manager']
  	 * we can warp manager to 'employee', now if 
  	 * both employees and managers are stored in
  	 * the employee table...we can do a self join to
  	 * look for relations between managers and 
  	 * employees.
  	 * 
  	 * To allow that to happen, getOption() needs to
  	 * map tableName to the actual table name, and 
  	 * allow the foreign key to be specified (already
  	 * supported).  Then after any call to getOptions()
  	 * the calling methods show update their $tableName
     * to be the actual table name.  i.e. 
     * $options['actualtable']
  	 * 
  	 * TODO:
  	 * 
  	 */
  	
  	/* check inputs */
  	
    $tableName = self::cleanTableName($tableName);
    
    if(empty($tableName)) {
      $this->error("Can get options, no table name.");
      return false;  	
    }
    
    /* get the options */
    
    $options = array();
    
    switch($kind) {
      case self::BELONGS_TO:
      	{
          /* find any options for this relationship */
    
          $options = array(
            'fk' => $tableName."_id",
            'pk' => 'id'
          );
    
          if(isset($this->relBelongsTo[$tableName])) {
    	
            $opts = $this->relBelongsTo[$tableName];
      
            if(isset($opts['fk'])) {
              $options['fk'] = $opts['fk'];
            }
            if(isset($opts['pk'])) {
              $options['pk'] = $opts['pk'];
            }
          }
      	}
      	break;
      	
      case self::HAS_ONE:
      case self::HAS_MANY:
      	{
      	  $relData = $this->relHasMany;
          if($kind == self::HAS_ONE) {
            $relData = $this->relHasOne;
          }
          
      	  $options = array(
            'fk' => $this->tableName."_id",
      	    'pk' => 'id'
          );
    
          if(isset($relData[$tableName])) {
    	
            $opts = $relData[$tableName];
      
            if(isset($opts['fk'])) {
              $options['fk'] = $opts['fk'];
            }
            if(isset($opts['pk'])) {
              $options['pk'] = $opts['pk'];
            }
          }
      	}
      	break;
      	
      case self::HAS_ONE_THROUGH:
        {
          $relData = $this->relHasOneThrough;
          
          $options = array(
            'sk'      => $this->tableName."_id",
            'dk'      => false,
            'through' => false,
            'spk'     => 'id',
            'mpk'     => 'id',
            'dpk'     => 'id'
          );
    
          if(isset($relData[$tableName])) {
    	
            $opts = $relData[$tableName];
      
            if(isset($opts['through'])) {
              $options['through'] = $opts['through'];
            }
            if(isset($opts['dk'])) {
              $options['dk'] = $opts['dk'];
            } 
            if(isset($opts['sk'])) {
              $options['sk'] = $opts['sk'];
            }  
            if(isset($opts['spk'])) {
              $options['spk'] = $opts['spk'];
            }
            if(isset($opts['mpk'])) {
              $options['mpk'] = $opts['mpk'];
            } 
            if(isset($opts['dpk'])) {
              $options['dpk'] = $opts['dpk'];
            }  
          }
          
          if($options['dk'] === false) {
          	if($options['through'] !== false) {
              $options['dk'] = $options['through']."_id";
          	}
          }
        }
        break;
      	
      case self::HAS_MANY_THROUGH:
        {
          $relData = $this->relHasManyThrough;
          
          $options = array(
            'sk'      => $this->tableName."_id",
            'dk'      => $tableName."_id",
            'through' => false,
            'spk'     => 'id',
            'mpk'     => 'id',
            'dpk'     => 'id'
          );
    
          if(isset($relData[$tableName])) {
    	
            $opts = $relData[$tableName];
      
            if(isset($opts['through'])) {
              $options['through'] = $opts['through'];
            }
            if(isset($opts['dk'])) {
              $options['dk'] = $opts['dk'];
            } 
            if(isset($opts['sk'])) {
              $options['sk'] = $opts['sk'];
            }  
            if(isset($opts['spk'])) {
              $options['spk'] = $opts['spk'];
            }
            if(isset($opts['mpk'])) {
              $options['mpk'] = $opts['mpk'];
            } 
            if(isset($opts['dpk'])) {
              $options['dpk'] = $opts['dpk'];
            }  
          }
      	}
      	break;
      	
      case self::HAS_AND_BELONGS_TO_MANY:
      	{
      	  $relData = $this->relHasAndBelongsToMany;
      	  
          $options = array(
            'sk'        => $this->tableName."_id",
            'dk'        => $tableName."_id",
            'jointable' => false,
            'spk'       => 'id',
            'dpk'       => 'id'
          );
    
          /* default the join table name */
    
          {
            $sortable = array($this->tableName);
            foreach($relData as $key => $relOptions) {
              $sortable[] = strtolower(trim($key));
            }
            asort($sortable);
            $options['jointable'] = implode('_', $sortable);
          }

          /* overrides? */
    
          if(isset($relData[$tableName])) {
    	
            $opts = $relData[$tableName];
      
            /* see if they are setting the actual join table name */
      
            if(isset($opts['jointable'])) {
      	
      	      /* explicit set of join table name */
      	
              $options['jointable'] = strtolower(trim($opts['jointable']));
            } 
            
            if(isset($opts['dk'])) {
              $options['dk'] = $opts['dk'];
            } 
            if(isset($opts['sk'])) {
              $options['sk'] = $opts['sk'];
            }  
            if(isset($opts['spk'])) {
              $options['spk'] = $opts['spk'];
            }
            if(isset($opts['dpk'])) {
              $options['dpk'] = $opts['dpk'];
            }  
          }
      	}
      	break;
      	
  	  default:
  		{
  	      $this->error("Can not get options, bad kind: $kind");
  		  return false;	
  		}
  		break;
    }
    
    /* pass back */
    
    return $options;
  }
  
  /**
   * 
   * rowToObj() this helper function is used by various 
   * other methods to take a $row object (returned from 
   * the database layer) and fill any fileds in this object
   * tha we can, based on what is in the row.
   * 
   * You won't normally call this directly, to recover
   * an object, use one of the relational method or restore().
   * 
   * @param mixed $row a row object returned from 
   * \db\SQLDatabase.
   * 
   */
  
  public function rowToObj($obj, $row) {

    /* fill it */
      

    foreach($row as $name => $value) {
      
      if($name == $obj->getPKName()) {
        $obj->id = $value;
        continue;
      }
        
      if(!isset($obj->fieldTypes[$name])) {
        continue;
      }
     
      $obj->fields[$name] = $obj->dbToPHP($name, $value);
    }
    
    $obj->dirty = false;
  }
  
  /**
   * 
   * belongsTo() this model belongs to the other one; that 
   * is this model is a child of the other one, and we are
   * looking in the other one for its primary key to match
   * our local column that is the foreign key for that other
   * table.  The arrow is from this model to the other one.
   * 
   * @param string $tableName the name of the model that
   * this one belongs to.
   * 
   * @return object return the belongs to object (if there
   * is one), which will be whatever class instance maps
   * to that other model.  There can only be one of these
   * if there is one.
   * 
   */
  
  public function belongsTo($tableName) {
  	
    if(!$this->isReady()) {
      $this->error("Can not do belongsTo, object is not ready.");
      return false;
    }
    
    $tableName = self::cleanTableName($tableName);
    
    if(empty($tableName)) {
      $this->error("Can not do belongsTo, no table name.");
      return false;  	
    }
    
    if($this->id <= 0) {
      $this->error("Can not do belongsTo, object is not loaded yet.");
      return false;
    }
    
    if(!isset($this->relBelongsTo[$tableName])) {
    	
      /* 
       * they have not explicitliy told us that there is 
       * a belongsTo relationship with $tableName.  We
       * could go ahead and do it anyways, on the assumption
       * that the tables are setup as needed...but its better
       * to get them to be explicit so the code is obvious.
       * So we error out if they didn't declare the 
       * relationship.
       * 
       */
    	
      $this->error("Can not do belongsTo, no declared belongs_to relationship with $tableName.");
      return false;
    }
    
    /* get an instance of the other model */
    
    $other = self::create($tableName);
    if(($other === false)||($other->isReady() === false)) {
      $this->error("Can not do belongsTo, model ($tableName) not usable");
      return false;	
    }
    
    /* find any options for this relationship */
    
    $options = $this->getOptions(self::BELONGS_TO, $tableName);
    
    /* find the foreign key */
    
    if(!isset($this->fields[$options['fk']])) {
      $this->error("Can not do belongsTo, no such column: ".$options['fk']);
      return false;
    }
    
    $fk = $this->fields[$options['fk']];
    
    /* look up the other object */
    
    $other->restore($fk);
    
  	/* ok, pass it back */
    
    return $other;
  }
  
  /**
   * 
   * hasOne() - this model has an association with the 
   * other model, but it is that  other model which 
   * has a column that is a foreign key that identifies
   * this object in this model.  The arrow is from that 
   * model to this one.
   * 
   * @param string $tableName the name of the model that
   * has one of this model.
   * 
   * @return object return the has one object (if there
   * is one), which will be whatever class instance maps
   * to that other model.  There can only be one of these
   * if there is one.
   * 
   */
  
  public function hasOne($tableName) {

  	$results = self::has($tableName, 1);
  	if(($results === false)||(empty($results))) {
  	  return false;
  	}
  	
  	return $results[0];
  }
  
  /**
   * 
   * hasMany() - Similar to hasOne(), but can be used 
   * when we expect there to be more than one related 
   * object.
   * 
   * @param string $tableName the name of the model that
   * has instances of this model.
   * 
   * @return object return the array of has many objects 
   * (if there are any), which will be whatever class instance maps
   * to that other model.  There can only be 0 or more of
   * these matches.
   * 
   */
  
  public function hasMany($tableName) {
  	
    $results = self::has($tableName);
  	if($results === false) {
  	  return false;
  	}
  	
  	return $results;
  }
  
  /**
   * 
   * has() - helper methd is used to implement both hasOne() and hasMany().
   * 
   * @param string $tableName the name of the other table.
   * 
   * @param integer $expecting if we expect only a certain number of results
   * 
   * @return return exactly false on error, otherwise return an array
   * of the matches where each match is a model object on the given
   * table.
   * 
   */
  
  public function has($tableName, $expecting=-1) {
  	
    if(!$this->isReady()) {
      $this->error("Can not do has, object is not ready.");
      return false;
    }
    
    $tableName = self::cleanTableName($tableName);
    
    if(empty($tableName)) {
      $this->error("Can not do has, no table name.");
      return false;  	
    }
    
    if($this->id <= 0) {
      $this->error("Can not do has, object is not loaded yet.");
      return false;
    }
    
    $relData = $this->relHasMany;
    if($expecting == 1) {
      $relData = $this->relHasOne;
    }
    
    if(!isset($relData[$tableName])) {
    	
      /* 
       * they have not explicitliy told us that there is 
       * a relHasOne relationship with $tableName.  We
       * could go ahead and do it anyways, on the assumption
       * that the tables are setup as needed...but its better
       * to get them to be explicit so the code is obvious.
       * So we error out if they didn't declare the 
       * relationship.
       * 
       */
    	
      $this->error("Can not do has, no declared relationship with $tableName.");
      return false;
    }
    
    /* get an instance of the other model */
    
    $other = self::create($tableName);
    if(($other === false)||($other->isReady() === false)) {
      $this->error("Can not do has, model ($tableName) not usable");
      return false;	
    }
    
    /* find any options for this relationship */
    
    $options = array();
    if($expecting == 1) {
      $options = $this->getOptions(self::HAS_ONE, $tableName);
    } else {
      $options = $this->getOptions(self::HAS_MANY, $tableName);
    }
    
    /* find the foreign key */
    
    if(!isset($other->fields[$options['fk']])) {
      $this->error("Can not do has, no such column: ".$options['fk']);
      return false;
    }
    
    /* look up the other object */
    
    $matches = $other->find(strval("WHERE ".$options['fk']."='".$this->id."'"));
    if($matches === false) {
      $this->error("Can not do has, problem with find: ".$other->getError());
      return false;
    }
    
    if(empty($matches)) {
      return array();
    }
    
    if($expecting == 1) {
      if(count($matches) > 1) {
        $this->error("Can not do has, multiple matches, but expected only one.");
        return false;
      }
    }
    
  	/* ok, pass it back */
    
    return $matches;
  }

  /**
   * 
   * hasOneThrough() - walk through an intermediate table to get
   * one item in a target table for this item.  
   * 
   * @param $tableName string the name of the relaated model that 
   * we can walk to through a middle (join) model.  To specify the
   * middle table, configure the relHasOneThrough relation for
   * $tableName to with the option 'through' and set that option
   * to the name of middle model.  If you need to override the 
   * default column names, you can configure  the additional 
   * options 'sk' for the middle model column key to use for this 
   * model, and 'dk' for the middle model column key to use for the 
   * target model.
   * 
   * @return mixed will return exactly false on error, and otherwise
   * an instance of whatever model class is appropriate for 
   * $targetTable.
   *  
   */
  
  public function hasOneThrough($tableName) {

  	if(!$this->isReady()) {
      $this->error("Can not do hasOneThrough, object is not ready.");
      return false;
    }
    
    $tableName = self::cleanTableName($tableName);
    
    if(empty($tableName)) {
      $this->error("Can not do hasOneThrough, no table name.");
      return false;  	
    }
    
    if($this->id <= 0) {
      $this->error("Can not do hasOneThrough, object is not loaded yet.");
      return false;
    }
    
    $relData = $this->relHasOneThrough;
       
    if(!isset($relData[$tableName])) {
    	
      /* 
       * they have not explicitliy told us that there is 
       * a relHasOneThrough or relHasManyThrough relationship 
       * with $tableName.  We could go ahead and do it anyways, 
       * on the assumption that the tables are setup as needed...
       * but its better to get them to be explicit so the code 
       * is obvious. So we error out if they didn't declare the 
       * relationship.
       * 
       */
    	
      $this->error("Can not do hasOneThrough, no declared relationship with $tableName.");
      return false;
    }
    
    /* get an instance of the other model */
    
    $other = self::create($tableName);
    if(($other === false)||($other->isReady() === false)) {
      $this->error("Can not do hasOneThrough, model ($tableName) not usable");
      return false;	
    }
    
    /* configure */

    $options = $this->getOptions(self::HAS_ONE_THROUGH, $tableName);
    
    if($options['through'] === false) {
      $this->error("Can not do hasOneThrough, no joining model.");
      return false;	
    }
    
    /* get an instance of the middle model */
    
    $through = $options['through'];
    
    $middle = self::create($through);
    if(($middle === false)||($middle->isReady() === false)) {
      $this->error("Can not do hasOneThrough, model ($through) not usable");
      return false;	
    }
    
    /* 
     * double check and make sure the join model really is a join model 
     * for the source and sink...
     * 
     */
    
    if(!isset($middle->relBelongsTo[$this->tableName])) {
      $this->error("Can not do hasOneThrough, $through does not belong to ".$this->tableName);
      return false;
    }
    if(!isset($middle->relHasOne[$tableName])) {
      $this->error("Can not do hasOneThrough, $through does not have one ".$tableName);
      return false;
    }
    
    if(!isset($middle->fieldTypes[$options['sk']])) {
      $this->error("Can not do hasOneThrough, join model (".$through.") has no link column (".$options['sk'].") for model ".$this->tableName);
      return false;
    }
    if(!isset($other->fieldTypes[$options['dk']])) {
      $this->error("Can not do hasOneThrough, target model (".$tableName.") has no link column (".$options['dk'].") for model ".$through);
      return false;
    }
    
    /* 
     * ok, looks good, we should be able to go ahead and fill $other 
     * objects, based on a join through $middle.
     * 
     */
    
    $sql  = "SELECT D.* FROM ".$this->tableName." S, $through J, $tableName D ";
    $sql .= "WHERE S.".$this->getPKName()."='".$this->id."' AND "; 
    $sql .= "J.".$options['sk']."=S.".$this->getPKName()." AND ";
    $sql .= "D.".$options['dk']."=J.".$middle->getPKName()." ";
    $sql .= ";";

    $result = $this->db->query($sql);
    
    if($result === false) {
      $this->error("Can not do hasOneThrough, SQL problem: ".$this->db->getError());
      return false;
    }
    
    if(empty($result)) {
      return array();
    }
    
    /* harvest */
    
    $results = array();
    
    foreach($result as $row) {
    	
      /* clone an object to use */
    	
      $obj = self::create($tableName);
      if(($obj === false)||($obj->isReady() === false)) {
        $this->error("Can do hasOneThrough, model ($tableName) not usable");
        return false;	
      }
      
      $this->rowToObj($obj, $row);

      /* save it */
      
      $results[] = $obj;
    }
    
    if(count($results) > 1) {
      $this->error("Can not do hasOneThrough, multiple matches, but expected only one.");
      return false;
    }
    
    /* pass back the results */
    
    return $results;
  }
  
  /**
   * 
   * hasManyThrough() - walk through an intermediate table to get
   * many items in a target table for this item.  
   * 
   * @param $tableName string the name of the relaated model that 
   * we can walk to through a middle (join) model.  To specify the
   * middle table, configure the relHasOneThrough relation for
   * $tableName to with the option 'through' and set that option
   * to the name of middle model.  If you need to override the 
   * default column names, you can configure  the additional 
   * options 'sk' for the middle model column key to use for this 
   * model, and 'dk' for the middle model column key to use for the 
   * target model.
   * 
   * @return mixed will return exactly false on error, and otherwise
   * an array of matching instances of whatever model class is appropriate for 
   * $targetTable.  An empty array means no matches (not error).
   *  
   */
  
  public function hasManyThrough($tableName) {
  	
    $results = self::hasThrough($tableName);
  	if($results === false) {
  	  return false;
  	}
  	
  	return $results;
  }
  
  /**
   * 
   * hasThrough() this helper method is used to implement hasOneThrough() 
   * and hasManyThrough(), you normally won't call this method directly. 
   * Unlike hasOne() and hasMany(), we need to walk through some intermediate
   * table (a join) to get to the given table name.  The user must have 
   * configured the relHasManyThrough or relHasOneThrough relationship 
   * with a 'through' option to specify the table to walk through.
   * 
   * Eventually we'll upgrade the 'through' option to be an array of 
   * intermediate tables to allow for more complex joins.  For now we
   * are just walking through a single intermediate table.
   * 
   * @param string $tableName the name of the other table.
   * 
   * @param integer $expecting if we expect only a certain number of results
   * 
   * @return return exactly false on error, otherwise return an array
   * of the matches where each match is a model object on the given
   * table.
   * 
   */
  
  public function hasThrough($tableName, $expecting=-1) {
  	
    if(!$this->isReady()) {
      $this->error("Can not do hasThrough, object is not ready.");
      return false;
    }
    
    $tableName = self::cleanTableName($tableName);
    
    if(empty($tableName)) {
      $this->error("Can not do hasThrough, no table name.");
      return false;  	
    }
    
    if($this->id <= 0) {
      $this->error("Can not do hasThrough, object is not loaded yet.");
      return false;
    }
    
    $relData = $this->relHasManyThrough;
    if($expecting == 1) {
      $relData = $this->relHasOneThrough;
    }
    
    if(!isset($relData[$tableName])) {
    	
      /* 
       * they have not explicitliy told us that there is 
       * a relHasOneThrough or relHasManyThrough relationship 
       * with $tableName.  We could go ahead and do it anyways, 
       * on the assumption that the tables are setup as needed...
       * but its better to get them to be explicit so the code 
       * is obvious. So we error out if they didn't declare the 
       * relationship.
       * 
       */
    	
      $this->error("Can not do hasThrough, no declared relationship with $tableName.");
      return false;
    }
    
    /* get an instance of the other model */
    
    $other = self::create($tableName);
    if(($other === false)||($other->isReady() === false)) {
      $this->error("Can not do hasThrough, model ($tableName) not usable");
      return false;	
    }
    
    /* configure */

    $options = array();
    if($expecting == 1) {
      $options = $this->getOptions(self::HAS_ONE_THROUGH, $tableName);
    } else {
      $options = $this->getOptions(self::HAS_MANY_THROUGH, $tableName);
    }

    if($options['through'] === false) {
      $this->error("Can not do hasThrough, no joining model.");
      return false;	
    }
    
    /* get an instance of the middle model */
    
    $through = $options['through'];
    
    $middle = self::create($through);
    if(($middle === false)||($middle->isReady() === false)) {
      $this->error("Can not do hasThrough, model ($through) not usable");
      return false;	
    }
    
    /* 
     * double check and make sure the join model really is a join model 
     * for the source and sink...
     * 
     */
    
    if(!isset($middle->relBelongsTo[$this->tableName])) {
      $this->error("Can not do hasThrough, $through does not belong to ".$this->tableName);
      return false;
    }
    if(!isset($middle->relBelongsTo[$tableName])) {
      $this->error("Can not do hasThrough, $through does not belong to ".$tableName);
      return false;
    }
    
    if(!isset($middle->fieldTypes[$options['sk']])) {
      $this->error("Can not do hasThrough, join model (".$through.") has no link column (".$options['sk'].") for model ".$this->tableName);
      return false;
    }
    if(!isset($middle->fieldTypes[$options['dk']])) {
      $this->error("Can not do hasThrough, join model (".$through.") has no link column (".$options['dk'].") for model ".$tableName);
      return false;
    }
    
    /* 
     * ok, looks good, we should be able to go ahead and fill $other 
     * objects, based on a join through $middle.
     * 
     */
    
    $sql  = "SELECT D.* FROM ".$this->tableName." S, $through J, $tableName D ";
    $sql .= "WHERE S.".$this->getPKName()."='".$this->id."' AND "; 
    $sql .= "J.".$options['sk']."=S.".$this->getPKName()." AND ";
    $sql .= "J.".$options['dk']."=D.".$other->getPKName()." ";
    $sql .= ";";

    $result = $this->db->query($sql);
    if($result === false) {
      $this->error("Can not do hasThrough, SQL problem: ".$this->db->getError());
      return false;
    }
    
    if(empty($result)) {
      return array();
    }
    
    /* harvest */
    
    $results = array();
    
    foreach($result as $row) {
    	
      /* clone an object to use */
    	
      $obj = self::create($tableName);
      if(($obj === false)||($obj->isReady() === false)) {
        $this->error("Can do hasThrough, model ($tableName) not usable");
        return false;	
      }
      
      $this->rowToObj($obj, $row);

      /* save it */
      
      $results[] = $obj;
    }
    
    if($expecting == 1) {
      if(count($results) > 1) {
        $this->error("Can not do hasThrough, multiple matches, but expected only one.");
        return false;
      }
    }
    
    /* pass back the results */
    
    return $results;
  }
  
  /**
   * 
   * hasAndBelongsToMany() sometimes there is a need to walk
   * from one mdoel to another directly through a simple 
   * many-to-many join table, that doesn't have an explicit
   * model.  This method can be used to go from on model to
   * one of the others in the many-to-many table.  
   * 
   * Each row of the joining table is presumed to be a pair,
   * a tripplet, etc of keys that are all tightly related,
   * as a package. Each column is presumed to be a foreign 
   * key for this and other models.
   * 
   * IMPORTANT: Table Naming - each model that declares 
   * itself to have this relation, must identify all the 
   * other models that participate, so that the name of 
   * the join table canbe constructed from the names 
   * of the tables that are involved.  The  table names
   * involved are sorted alphabetically, lowercsaed, and
   * then joined left to right with an '_' character.  
   * 
   * For example if this model is A and its in a join 
   * table with model B, then the join table is assumed
   * to be named:
   * 
   *   a_b
   *   
   * The names of the columsn will (for each model involved)
   * be lowercased and:
   * 
   *   <table name>_id
   *   
   * where <table name> is the table that maps to the class
   * model.  You can override the names used by configuring
   * the relation when you set the relation in your 
   * constructor.
   * 
   * @param string $tableName the other table related items
   * to fetch from.
   * 
   * @return mixed will return exactly false on error, but 
   * otherwise an array of objects which are instances of
   * whatever model class is appropriate for $tableName.
   * If no matches, an empty array is returned.
   * 
   */
  
  public function hasAndBelongsToMany($tableName) {
  	
  	if(!$this->isReady()) {
      $this->error("Can not do hasAndBelongsToMany, object is not ready.");
      return false;
    }
    
    $tableName = self::cleanTableName($tableName);
    
    if(empty($tableName)) {
      $this->error("Can not do hasAndBelongsToMany, no table name.");
      return false;  	
    }
    
    if($this->id <= 0) {
      $this->error("Can not do hasAndBelongsToMany, object is not loaded yet.");
      return false;
    }
    
    $relData = $this->relHasAndBelongsToMany;

    if(!isset($relData[$tableName])) {
    	
      /* 
       * they have not explicitliy told us that there is 
       * a relHasOneThrough or relHasManyThrough relationship 
       * with $tableName.  We could go ahead and do it anyways, 
       * on the assumption that the tables are setup as needed...
       * but its better to get them to be explicit so the code 
       * is obvious. So we error out if they didn't declare the 
       * relationship.
       * 
       */
    	
      $this->error("Can not do $relHasAndBelongsToMany, no declared relationship with $tableName.");
      return false;
    }
    
    /* get an instance of the other model */
    
    $other = self::create($tableName);
    if(($other === false)||($other->isReady() === false)) {
      $this->error("Can not do hasAndBelongsToMany, model ($tableName) not usable");
      return false;	
    }
    
    /* configure */
    
    $options = $this->getOptions(self::HAS_AND_BELONGS_TO_MANY,$tableName);
    
    if($options['jointable'] === false) {
      $this->error("Can not do hasAndBelongsToMany, no joining model.");
      return false;	
    }
    
    /* 
     * ok, looks good, we should be able to go ahead and fill $other 
     * objects, based on being in the join table.
     * 
     */

    $sql  = "SELECT D.* FROM $tableName D, ".$this->tableName." S, ".$options['jointable']." J ";
    $sql .= "WHERE S.".$this->getPKName()."=J.".$options['sk']." AND "; 
    $sql .= "J.".$options['dk']."=D.".$other->getPKName()." AND S.".$this->getPKName()."='";
    $sql .= $this->id."';";

    $result = $this->db->query($sql);
    if($result === false) {
      $this->error("Can not do hasAndBelongsToMany, SQL problem: ".$this->db->getError());
      return false;
    }
    
    if(empty($result)) {
      return array();
    }
    
    /* harvest */
    
    $results = array();
    
    foreach($result as $row) {
      
      /* clone an object to use */
    	
      $obj = self::create($tableName);
      if(($obj === false)||($obj->isReady() === false)) {
        $this->error("Can do hasAndBelongsToMany, model ($tableName) not usable");
        return false;	
      }
      
      /* fill it */
      
      $this->rowToObj($obj, $row);
    	
      /* save it */
      
      $results[] = $obj;
    }
    
    /* pass back the results */
    
    return $results;
  }

  /**
   * 
   * defaultRelation() - determin the relationship between
   * this model and the given $tableName model, if there is
   * one.  Normally if the user has configured one and only
   * one relationship for the given $tableName, then we can 
   * use that. Otherwise we can not assume a default 
   * relationship.
   * 
   * @param string $tableName the name of the model we are 
   * trying to relate to.
   * 
   * @return mixed return exactly false if there is no 
   * default possible.  Otherwise return one of the relation
   * codes (for example self::BELONGS_TO)
   * 
   */
  
  public function defaultRelation($tableName) {

  	/*
  	 * TODO:
  	 * 
  	 * like getOption() we need to be able to
  	 * detect if a self join is going on, look for
  	 * options in relationship for $tableName that
  	 * indicate an alias name to use.
  	 * 
  	 */
  	
    $tableName = self::cleanTableName($tableName);
    
    if(empty($tableName)) {
      $this->error("Can not do defaultRelation, no table name.");
      return false;  	
    }
    
    $rels = array(
      self::BELONGS_TO              => $this->relBelongsTo,
      self::HAS_ONE                 => $this->relHasOne,
      self::HAS_MANY                => $this->relHasMany,
      self::HAS_MANY_THROUGH        => $this->relHasManyThrough,
      self::HAS_ONE_THROUGH         => $this->relHasOneThrough,
      self::HAS_AND_BELONGS_TO_MANY => $this->relHasAndBelongsToMany
    );
    
    $match = null;
    $cnt   = 0;
    foreach($rels as $code => $options) {
      if(isset($options[$tableName])) {
        $cnt++;
        $match = $code;
      }
    }
  	
    if($cnt == 1) {
      return $match;
    }
    
    return false;
  }

  /**
   * 
   * related() - fetch the related object(s) from another 
   * model.
   * 
   * @param string $tableName the name of the table to fetch
   * related objects from.
   * 
   * @param integer $kind if not provided, and there is exactly
   * one kind of relation for $tableName, we will use that 
   * relation type.  Otherwise you must indicate the knid of
   * relationship to walk (for example: self::BELONGS_TO)
   * 
   * @return mixed return exactly false on error, otherwise
   * an array, where empty array means no matches.
   * 
   */
  
  public function related($tableName, $kind=null) {
  	
    if(!$this->isReady()) {
      $this->error("Can not do related, object is not ready.");
      return false;
    }
    
  	/* do we have a table name */
  	
    $tableName = self::cleanTableName($tableName);
    
    if(empty($tableName)) {
      $this->error("Can not do related, no table name.");
      return false;  	
    }
    
  	/* do we have a relation kind? */
  	
  	if($kind === null) {
      $kind = $this->defaultRelation($tableName);
      if($kind === false) {
      	$this->error("Can not do related ($tableName) can't determine relation kind.");
        return false;  	
      }
  	}

  	/* ok, let the relating begin! */
  	
  	$result = array();
  	
  	switch($kind) {
      case self::BELONGS_TO:
      	{
          $result = $this->belongsTo($tableName);
          if(!empty($result)) {
            if(!is_array($result)) {
          	  $result = array($result);
            }
          }
      	}
      	break;
      	
      case self::HAS_ONE:
      	{
      	  $result = $this->hasOne($tableName);
      	  if($result !== false) {
      	  	if(!is_array($result)) {
      	      $result = array($result);
      	  	}
      	  }
      	}
      	break;
      	
      case self::HAS_MANY:
      	{
      	  $result = $this->hasMany($tableName);	
      	}
      	break;
      	
      case self::HAS_MANY_THROUGH:
      	{
      	  $result = $this->hasManyThrough($tableName);
      	}
      	break;
      	
      case self::HAS_ONE_THROUGH:
      	{
      	  $result = $this->hasOneThrough($tableName);
      	  if($result !== false) {
      	  	if(!is_arraY($result)) {
      	      $result = array($result);
      	  	}
      	  }
      	}
      	break;
      	
      case self::HAS_AND_BELONGS_TO_MANY:
      	{
      	  $result = $this->hasAndBelongsToMany($tableName);
      	}
        break;
        
      default:
      	{
          $this->error("Can not do related ($tableName) bad kind: $kind.");
          return false;
      	}
      	break;
  	}
  	 	
  	/* all done */
  	
  	return $result;
  }
 
  /**
   * 
   * canLinkTo() check to see if it is possible to walk from
   * this model tothe $other model.  If it is possible, then
   * fill in $tables with the tables that are involved in the 
   * join and fill in $conditions with the various where 
   * clauses that would be required.  This method is intended
   * to be a helper method to help other classes build up 
   * more complex queries.
   * 
   * @param string $other - the name of the other table we are
   * trying to walk to.
   * 
   * @param array $tables - reference to the tables variable 
   * we should fill in.
   * 
   * @param array $conditions - refernece to the conditions 
   * variable we should fill in.
   * 
   * @return boolean - we return true if you can walk from 
   * this table to $other, and false otherwise.
   * 
   */
  
  public function canLinkTo($other, &$tables, &$conditions) {

    if(!$this->isReady()) {
      $this->error("canLinkTo($other) - object is not ready.");
      return false;
    }
    
    /* convert table name to an instance of that table */
    
    $t = $other;
    if(!($other instanceof littlemdesign_db_ORMTable)) {      
      $other = self::create($other);
    } else {
      $t = $other->tableName;
    }
    
    if(($other === false)||($other->isReady() === false)) {
      $this->error("Can not canLinkTo($t) not usable.");
      return false;	
    }
    
    $outgoing = $this->defaultRelation($other->tableName);
    
    if($outgoing === false) {
      $this->warning("canLinkTo($t) - no relation from ".$this->tableName);
      return false;
    }
    
    $options = $this->getOptions($outgoing, $other->tableName);
    
    /* 
     * for the kind of relation invovled, figure out how to join
     * the tables exactly.
     * 
     */
    
    switch($outgoing) {
  		
      case self::BELONGS_TO:
        { 
          $tables     = array($this->tableName, $other->tableName);
          $conditions = array();
          
          $fk = $options['fk'];
          $pk = $options['pk'];
          
          $where = $this->tableName.".$fk=".$other->tableName.".$pk";
          $conditions[] = $where;

        }
        break;
        
      case self::HAS_ONE:
      case self::HAS_MANY:
        {
          $tables     = array($this->tableName, $other->tableName);
          $conditions = array();
          
          $fk = $options['fk'];
          $pk = $options['pk'];
          
          $where = $other->tableName.".$fk=".$this->tableName.".$pk";
          $conditions[] = $where; 
        
        }
        break;
        
      case self::HAS_ONE_THROUGH:
        {
  	    	
          /* get an instance of the middle model */
    
          $through = $options['through'];
    
          $middle = self::create($through);
          if(($middle === false)||($middle->isReady() === false)) {
            $this->error("canLinkTo($t) middle model ($through) not usable.");
            return false;
          }
    
          $tables = array($this->tableName, $through, $other->tableName);
          $conditions = array();
          
          $sk  = $options['sk'];
          $dk  = $options['dk'];
          $spk = $options['spk'];
          $mpk = $options['mpk'];
          $dpk = $options['dpk'];
          
          $where = $middle->tableName.".$sk=".$this->tableName.".$spk";
          $conditions[] = $where;
          
          $where = $other->tableName.".$dk=".$middle->tableName.".$mpk";
          $conditions[] = $where;

        }
        break;  

      case self::HAS_MANY_THROUGH:
        {
         
          $through = $options['through'];
    
          $middle = self::create($through);
          if(($middle === false)||($middle->isReady() === false)) {
            $this->error("canLinkTo($t) middle model ($through) not usable.");
            return false;
          }
    
          $tables = array($this->tableName, $through, $other->tableName);
          $conditions = array();
    
          $sk  = $options['sk'];
          $dk  = $options['dk'];
          $spk = $options['spk'];
          $mpk = $options['mpk'];
          $dpk = $options['dpk'];
          
          $where = $middle->tableName.".$sk=".$this->tableName.".$spk";
          $conditions[] = $where;
          
          $where = $middle->tableName.".$dk=".$other->tableName.".$dpk";
          $conditions[] = $where;
     
        }
        break;
        
      case self::HAS_AND_BELONGS_TO_MANY:
        {
         
          $tables = array($this->tableName, $options['jointable'], $other->tableName);
          $conditions = array();
          
          $sk   = $options['sk'];
          $dk   = $options['dk'];
          $join = $options['jointable'];
          
          $where = $join.".$sk=".$this->tableName.".$spk";
          $conditions[] = $where;
          
          $where = $join.".$dk=".$other->tableName.".$dpk";
          $conditions[] = $where;

        }
        break;  
    }
  	
    /* all done */
    
    return true;
  }
  
  /**
   * 
   * isLinked() check to see if this object is linked to the 
   * given $other object.  We check per the relationship that
   * is configured between the two objects.  This check is 
   * exact; if one or the other object is constructed but not
   * saved, we will return false (its not yet in the database).
   * See the notes at the top of this file for details on 
   * having your sub-class configure relationships with other
   * models.
   * 
   * @param ORMTable $other the object we want to check (in the 
   * database) if we are linked with. Here linked with means 
   * exactly this object, so if its constructed but not saved 
   * (id=-1) we return false.
   * 
   * @param boolean $exact when set to true (default) we match 
   * exactly the object given.  When set to false, we count 
   * any relations between the two tables, tied to $this object.
   * You can also use this flag to get a count of the relations
   * due to this object (we don't return boolean, we return the 
   * count of matches)
   * 
   * @return integer return the number of links for this relation.
   * 0 means no links.  Return exactly false on error.
   * 
   */
  
  public function isLinked($other, $exact=true) {
  	
  	/* good to go? */
  	
    if(!$this->isReady()) {
      $this->error("Can not do isLinked, object is not ready.");
      return false;
    }
    
    if(!($other instanceof littlemdesign_db_ORMTable)) {
      $this->error("Can not do isLinked, other object is not an ORMTasble.");
      return false;
    }
    
    if($other->id <= 0) {
    
      /* not even saved to db, can't be linked yet. */
    	
      return false;
    }
    
    /* count based on the kind of relatinoship */
    
    $tableName = $other->getTableName();
  	$outgoing  = $this->defaultRelation($tableName);
  	$incoming  = $other->defaultRelation($this->getTableName());
  	
  	/* find any options for this relationship */
    
    $options   = $this->getOptions($outgoing, $tableName);
    
  	switch($outgoing) {
  		
      case self::BELONGS_TO:
        {
          if(($incoming != self::HAS_MANY)&&($incoming != self::HAS_ONE)) {
          	$this->error("Can not do isLinked (has one/many), wrong kind of incoming link: $incoming (for belongs to)\n");
            return false;	
          }
          
          $fk = $options['fk'];
          
          $sql  = "SELECT count(*) FROM ".$this->getTableName()." WHERE $fk='".$other->id."' ";

          if($exact !== false) {
            $sql .= " AND ".$this->getPKName()."='".$this->id."';";
          } else {
          	$sql .= ";";
          } 
           
          $result = $this->db->query($sql);
          if($result === false) {
            $this->error("Can not isLinked (has one/many): ".$this->db->getError());
            return false;
          }
            
          $count = array_shift($result[0]);
            
          return $count;
        }
        break;
        
       case self::HAS_ONE:
       case self::HAS_MANY:
        {
          if($incoming != self::BELONGS_TO) {
            $this->error("Can not do isLinked (has one/many), wrong kind of incoming link: $incoming (for has many)\n");
            return false;	
          }
            
          /* find the foreign key */
    
          if(!isset($other->fields[$options['fk']])) {
            $this->error("Can do isLinked (has one/many), no such remote column: ".$options['fk']);
            return false;
          }
          
          $fk = $options['fk'];

          $sql = "SELECT count(*) FROM $tableName WHERE $fk='".$this->id."' ";
          if($exact !== false) {
          	$sql .= " AND ".$this->getPKName()."='".$other->id."' ";
          } else {
          	$sql .= ";";
          }
          
          $result = $this->db->query($sql);
          if($result === false) {
            $this->error("Can not isLinked (has one/many): ".$this->db->getError());
            return false;
          }
            
          $count = array_shift($result[0]);
          
          return $count;
        }
        break;
        
  	  case self::HAS_ONE_THROUGH:
  	    {
  	    	
  	      /* get an instance of the middle model */
    
          $through = $options['through'];
    
          $middle = self::create($through);
          if(($middle === false)||($middle->isReady() === false)) {
            $this->error("Can not isLinked (has one through), model ($through) not usable");
            return false;	
          }
    
          /* 
           * double check and make sure the join model really is a join model 
           * for the source and sink...
           * 
           */
    
          if(!isset($middle->relBelongsTo[$this->tableName])) {
            $this->error("Can not isLinked (has one through), $through does not belong to ".$this->tableName);
            return false;
          }
          if(!isset($middle->relHasOne[$tableName])) {
            $this->error("Can not isLinked (has one through), $through does not have one ".$tableName);
            return false;
          }
    
          if(!isset($middle->fieldTypes[$options['sk']])) {
            $this->error("Can not isLinked (has one through), join model (".$through.") has no link column (".$options['sk'].") for model ".$this->tableName);
            return false;
          }
          if(!isset($other->fieldTypes[$options['dk']])) {
            $this->error("Can not isLinked (has one through), target model (".$tableName.") has no link column (".$options['dk'].") for model ".$through);
            return false;
          }
    
          /* 
           * ok, looks good, we should be able to go ahead and fill $other 
           * objects, based on a join through $middle.
           * 
           */
    
          $sql  = "SELECT count(*) FROM ".$this->tableName." S, $through J, $tableName D ";
          $sql .= "WHERE S.".$this->getPKName()."='".$this->id."' AND "; 
          $sql .= "J.".$options['sk']."=S.".$this->getPKName()." AND ";
          $sql .= "D.".$options['dk']."=J.".$middle->getPKName()." ";
          if($exact !== false) {
          	$sql .= " AND D.".$other->getPKName()."='".$other->id."'";
          }
          $sql .= ";";

          $result = $this->db->query($sql);
    
          if($result === false) {
            $this->error("Can not do link (has one/many through), SQL problem: ".$this->db->getError());
            return false;
          }
            
          $count = array_shift($result[0]);
          
          return $count;	
        }
        break;
  	  	
      case self::HAS_MANY_THROUGH:
        {
          if($incoming != self::HAS_MANY_THROUGH) {
            $this->error("Can not do isLinked (has one/many through), wrong kind of incoming link: $incoming (for has one through)\n");
            return false;
          }
            
          if($options['through'] === false) {
            $this->error("Can not do isLinked (has one/many through), no joining model.");
            return false;
          }
    
          /* get an instance of the middle model */
    
          $through = $options['through'];
    
          $middle = self::create($through);
          if(($middle === false)||($middle->isReady() === false)) {
            $this->error("Can not do isLinked (has one/many through), model ($through) not usable");
            return false;	
          } 
    
          /* 
           * double check and make sure the join model really is a join model 
           * for the source and sink...
           * 
           */
    
          if(!isset($middle->relBelongsTo[$this->tableName])) {
            $this->error("Can not do isLinked (has one/many through), $through does not belong to ".$this->tableName);
            return false;
          }
          if(!isset($middle->relBelongsTo[$tableName])) {
            $this->error("Can not do isLinked (has one/many through), $through does not belong to ".$tableName);
            return false;
          }
    
          if(!isset($middle->fieldTypes[$options['sk']])) {
            $this->error("Can not do isLinked (has one/many through), join model (".$through.") has no link column (".$options['sk'].") for model ".$this->tableName);
            return false;
          }
          if(!isset($middle->fieldTypes[$options['dk']])) {
            $this->error("Can not do isLinked (has one/many through), join model (".$through.") has no link column (".$options['dk'].") for model ".$tableName);
            return false;
          }  
          
          $sql  = "SELECT count(*) FROM ".$this->tableName." S, $through J, $tableName D ";
          $sql .= "WHERE S.".$this->getPKName()."='".$this->id."' AND "; 
          $sql .= "J.".$options['sk']."=S.".$this->getPKName()." AND ";
          $sql .= "J.".$options['dk']."=D.".$other->getPKName();

          if($exact !== false) {
          	$sql .= " AND D.".$other->getPKName()."='".$other->id."'";
          }
          
          $sql .= ";";
          
          $result = $this->db->query($sql);
           
          if($result === false) {
            $this->error("Can not do link (has one/many through), SQL problem: ".$this->db->getError());
            return false;
          }
            
          $count = array_shift($result[0]);
          
          return $count;
        }
        break;
        
  	  case self::HAS_AND_BELONGS_TO_MANY:
        {
          if($incoming != self::HAS_AND_BELONGS_TO_MANY) {
            $this->error("Can not isLinked (has and belongs to), wrong kind of incoming link: $incoming (for has and belongs to many)\n");
            return false;	
          }
          
          if($options['jointable'] === false) {
            $this->error("Can not isLinked (has and belongs to), no joining model.");
            return false;	
          }
          
          $sql  = "SELECT count(*) FROM ".$options['jointable']." WHERE ";
          $sql .= $options['sk']."='".$this->id."' ";
          if($exact !== false) {
            $sql .= "AND ".$options['dk']."='".$other->id."';";
          }
          $sql .= ";";
          
          $result = $this->db->query($sql);
          
          if($result === false) {
            $this->error("Can not do isLinked (has and belongs to), SQL problem: ".$this->db->getError());
            return false;
          }
            
          $count = array_shift($result[0]);

          return $count;
          
        }
        break;
        
  	  default:
  	  	{
  	      $this->error("Can not do isLinked, unrecognized relationship ".$this->getTableName()." <=> ".$other->getTableName());
      	  return false;
  	  	}
  	  	break;
  	  	
  	}
  	
  	/* not linked */
  	
  	return false;
  }
  
  /**
   * 
   * link() given some other object (an instance of a model...
   * a row in table), link it to this one.  If that exact row
   * is already linked to this one...do nothing and return true.
   * Otherwise attempt to find the implied relationship, and 
   * link the objects as needed per their relationship.  This 
   * implies that both classes of the objects involved have
   * constructors that configured themselves so that we know
   * the relationships.  See the notes at the top of the file
   * for making sub-classes of ORMTable.
   * 
   * If you are linking through a join table that is a "through"
   * model, then you have the option of passing in a 3rd object
   * which is the through row you wish to update.  If you don't
   * pass in this $middle object, a default one will be created
   * and used.
   * 
   * @param ORMTable $other the other object you want to link with.
   * The implied relationship between this object and $other will
   * be used to guide how the linking is done in the actual database.
   * 
   * @param ORMTasble $middle the "through" table object, if you 
   * don't want to use a default constructed "through" table 
   * object.
   * 
   * @return boolean return exactly false on error, true otherwise.
   * 
   */
  
  public function link($other, $middle=null) {
  	
  	/* good to go? */
  	
    if(!$this->isReady()) {
      $this->error("Can not do link, object is not ready.");
      return false;
    }
    if(!($other instanceof littlemdesign_db_ORMTable)) {
      $this->error("Can not do link, other object is not an ORMTasble.");
      return false;
    }
    
    /*
     * Are these two things already linked?
     * 
     */
   	
    $relCount = $this->isLinked($other);
    
    if(($relCount !== false) && ($relCount >= 1)) {
    	
      /* nothing to do */
    	
      return true;
    }
   
    /* based on the kind of relatinoship, link them */
    
    $tableName = $other->getTableName();
  	$outgoing  = $this->defaultRelation($tableName);
  	$incoming  = $other->defaultRelation($this->getTableName());
  	
  	/* find any options for this relationship */
    
    $options   = $this->getOptions($outgoing, $tableName);
    
  	switch($outgoing) {
  		
      case self::BELONGS_TO:
        {
          if(($incoming != self::HAS_MANY)&&($incoming != self::HAS_ONE)) {
          	$this->error("Can not do link, wrong kind of incoming link: $incoming (for belongs to)\n");
            return false;	
          }
          
          /* find the foreign key */
    
          if(!array_key_exists($options['fk'], $this->fields)) {
            $this->error("Can not do link, no such local column: ".$options['fk']);
            return false;
          }
    
          $fk = $options['fk'];

          if($incoming == self::HAS_ONE) {
          	
          	$relCount = $this->isLinked($other, false);
          	
            if($relCount >= 1) {
          	  $this->error("Can not do link (has one/many), to many relations already.");
              return false;
            }  
          }
    
          if($other->id <= 0) {
            $other->save();
          }
          
          $this->__set($fk, $other->id);
          
          /* link! */
          
          if($this->save() === false) {
          	$this->error("Can not do link (belongs to), problem with save(): ".$this->getError());
            return false;
          }
    
        }
        break;
      	
      case self::HAS_ONE:
      case self::HAS_MANY:
        {
          if($incoming != self::BELONGS_TO) {
            $this->error("Can not do link, wrong kind of incoming link: $incoming (for has many)\n");
            return false;	
          }
            
          /* find the foreign key */
    
          if(!array_key_exists($options['fk'], $other->fields)) {
            $this->error("Can do link, no such remote column: ".$options['fk']);
            return false;
          }
          
          $fk = $options['fk'];
    
          if($outgoing == self::HAS_ONE) {
          		
            $relCount = $this->isLinked($other, false);
          	
            if($relCount >= 1) {
          	  $this->error("Can not do link (has one/many), to many relations already.");
              return false;
            }  
          }
    
          if($this->id <= 0) {
            $this->save();
          }
          
          $other->__set($fk, $this->id);
          
          /* link! */
          
          if($other->save() === false) {
          	$this->error("Can not do link (belongs to), problem with save(): ".$other->getError());
            return false;
          }
          
        }
        break;
      	
      case self::HAS_ONE_THROUGH:
        {
          /* get an instance of the middle model */
    
          $through = $options['through'];
    
          if($middle === null) {
            $middle = self::create($through);
            if(($middle === false)||($middle->isReady() === false)) {
              $this->error("Can not link (has one through), model ($through) not usable");
              return false;	
            }
          }
    
          $relCount = $this->isLinked($other, false);
          	
          if($relCount >= 1) {
            $this->error("Can not link (has one through, to many relations already.");
            return false;
          }  
            
          /* 
           * double check and make sure the join model really is a join model 
           * for the source and sink...
           * 
           */
    
          if(!isset($middle->relBelongsTo[$this->tableName])) {
            $this->error("Can not link (has one through, $through does not belong to ".$this->tableName);
            return false;
          }
          if(!isset($middle->relHasOne[$tableName])) {
            $this->error("Can not link (has one through, $through does not have one ".$tableName);
            return false;
          }
    
          if(!isset($middle->fieldTypes[$options['sk']])) {
            $this->error("Can not link (has one through, join model (".$through.") has no link column (".$options['sk'].") for model ".$this->tableName);
            return false;
          }
          if(!isset($other->fieldTypes[$options['dk']])) {
            $this->error("Can not link (has one through, target model (".$tableName.") has no link column (".$options['dk'].") for model ".$through);
            return false;
          }
    
          /* ok, link through the join table */
          
          $middle->__set($options['sk'], $this->id);
          if($middle->save() === false) {
          	$this->error("Can not do link (has one/many through), problem with save(): ".$middle->getError());
            return false;
          }
          
          $other->__set($options['dk'], $middle->id);
          if($other->save() === false) {
          	$this->error("Can not do link (has one/many through), problem with save(): ".$other->getError());
            return false;
          }
        }
        break;
      	
      case self::HAS_MANY_THROUGH:
        {     
          if($incoming != self::HAS_MANY_THROUGH) {
            $this->error("Can not do link (has many through), wrong kind of incoming link: $incoming (for has one through)\n");
            return false;	
          }
            
          if($options['through'] === false) {
            $this->error("Can not do link (has many through), no joining model.");
            return false;
          }
    
          /* get an instance of the middle model */
    
          $through = $options['through'];
    
          if($middle === null) {
            $middle = self::create($through);
            if(($middle === false)||($middle->isReady() === false)) {
              $this->error("Can not do link (has many through), model ($through) not usable");
              return false;	
            }
          }
    
          /* 
           * double check and make sure the join model really is a join model 
           * for the source and sink...
           * 
           */
    
          if(!isset($middle->relBelongsTo[$this->tableName])) {
            $this->error("Can not do link (has many through), $through does not belong to ".$this->tableName);
            return false;
          }
          if(!isset($middle->relBelongsTo[$tableName])) {
            $this->error("Can not do link (has many through), $through does not belong to ".$tableName);
            return false;
          }
    
          if(!isset($middle->fieldTypes[$options['sk']])) {
            $this->error("Can not do link (has many through), join model (".$through.") has no link column (".$options['sk'].") for model ".$this->tableName);
            return false;
          }
          if(!isset($middle->fieldTypes[$options['dk']])) {
            $this->error("Can not do link (has many through), join model (".$through.") has no link column (".$options['dk'].") for model ".$tableName);
            return false;
          }
          
          /* make sure we have row ids */
          
          if($this->id <= 0) {
            $this->save();
          }
          if($other->id <= 0) {
            $other->save();
          }
            
          /* ok, link through the join table */
          
          $middle->__set($options['sk'], $this->id);
          $middle->__set($options['dk'], $other->id);
          
          /* link! */
          
          if($middle->save() === false) {
          	$this->error("Can not do link (has many through), problem with save(): ".$middle->getError());
            return false;
          }
        }
        break;
      	
      case self::HAS_AND_BELONGS_TO_MANY:
        {
          if($incoming != self::HAS_AND_BELONGS_TO_MANY) {
            $this->error("Can not link (has and belongs to), wrong kind of incoming link: $incoming (for has and belongs to many)\n");
            return false;	
          }
          
          if($options['jointable'] === false) {
            $this->error("Can not link (has and belongs to), no joining model.");
            return false;	
          }
          
          /* make sure we have row ids */
          
          if($this->id <= 0) {
            $this->save();
          }
          if($other->id <= 0) {
            $other->save();
          }
          
          /* link! */
          
          $cols = "(".$options['sk'].",".$options['dk'].")";
          $vals = "('".$this->id."','".$other->id."')";
          
          $sql = "INSERT INTO ".$options['jointable']." $cols VALUES $vals;";
          
          $result = $this->db->query($sql);
          if($result === false) {
            $this->error("Can not link (has and belongs to), SQL problem: ".$this->db->getError());
            return false;
          } 
        }
        break;
        
      default:
      	{
      	  $this->error("Can not do link, unrecognized relationship ".$this->getTableName()." <=> ".$other->getTableName());
      	  return false;
        }
        break;
  	}
  	
  	/* all done */
  	
  	return true;
  }
  
  
  
  
  public function unlink($other) {
  	
  	/* good to go? */
  	
    if(!$this->isReady()) {
      $this->error("Can not do unlink, object is not ready.");
      return false;
    }
    if(!($other instanceof littlemdesign_db_ORMTable)) {
      $this->error("Can not do unlink, other object is not an ORMTasble.");
      return false;
    }
    
    if(($other->id < 0)||($this->id < 0)) {
      $this->error("can not do unlink, one, other or both objects not actually stored.");
      return false;
    }
    
    /*
     * Are these two things already linked?
     * 
     */
    
    $relCount = $this->isLinked($other);
    
    if(($relCount === false) || ($relCount < 1)) {
    	
      /* nothing to do */
    	
      return true;
    }
   
    /* based on the kind of relatinoship, unlink them */
    
    $tableName = $other->getTableName();
  	$outgoing  = $this->defaultRelation($tableName);
  	$incoming  = $other->defaultRelation($this->getTableName());
  	
  	/* find any options for this relationship */
    
    $options   = $this->getOptions($outgoing, $tableName);
    
  	switch($outgoing) {
  		
      case self::BELONGS_TO:
        {
          if(($incoming != self::HAS_MANY)&&($incoming != self::HAS_ONE)) {
          	$this->error("Can not do unlink, wrong kind of incoming link: $incoming (for belongs to)\n");
            return false;	
          }
          
          /* find the foreign key */
    
          if(!isset($this->fields[$options['fk']])) {
            $this->error("Can do unlink, no such local column: ".$options['fk']);
            return false;
          }
    
          $fk = $options['fk'];

          $this->__set($fk, -1);
          
          /* link! */
          
          if($this->save() === false) {
          	$this->error("Can not do link (belongs to), problem with save(): ".$this->getError());
            return false;
          }	
        }
        break;   	
        
      case self::HAS_ONE:
      case self::HAS_MANY:
        {
          if($incoming != self::BELONGS_TO) {
            $this->error("Can not do unlink (has one/many), wrong kind of incoming link: $incoming (for has many)\n");
            return false;	
          }
            
          /* find the foreign key */
    
          if(!isset($other->fields[$options['fk']])) {
            $this->error("Can do unlink (has one/many), no such remote column: ".$options['fk']);
            return false;
          }
          
          $fk = $options['fk'];
          
          $other->__set($fk, -1);
          
          /* link! */
          
          if($other->save() === false) {
          	$this->error("Can not do unlink (has one/many), problem with save(): ".$other->getError());
            return false;
          }
        }
        break;
        
      case self::HAS_ONE_THROUGH:
        {
          /* get an instance of the middle model */
    
          $through = $options['through'];
       
          $middle = self::create($through);
          if(($middle === false)||($middle->isReady() === false)) {
            $this->error("Can not unlink (has one through), model ($through) not usable");
            return false;	
          }
          
          /* 
           * double check and make sure the join model really is a join model 
           * for the source and sink...
           * 
           */
    
          if(!isset($middle->relBelongsTo[$this->tableName])) {
            $this->error("Can not unlink (has one through, $through does not belong to ".$this->tableName);
            return false;
          }
          if(!isset($middle->relHasOne[$tableName])) {
            $this->error("Can not unlink (has one through, $through does not have one ".$tableName);
            return false;
          }
    
          if(!isset($middle->fieldTypes[$options['sk']])) {
            $this->error("Can not unlink (has one through, join model (".$through.") has no link column (".$options['sk'].") for model ".$this->tableName);
            return false;
          }
          if(!isset($other->fieldTypes[$options['dk']])) {
            $this->error("Can not unlink (has one through, target model (".$tableName.") has no link column (".$options['dk'].") for model ".$through);
            return false;
          }
    
          /* figure out what the join object is */
          
          $matches = $middle->find(strval("WHERE ".$options['sk']."='".$this->id."'"));
          if($matches === false) {
            $this->error("Can not do unlink, problem with find: ".$middle->getError());
            return false;
          }
    
          $middle = array_shift($matches);
          
          if($middle->id != $other->__get($options['dk'])) {
          	$this->error("Can not do unlink, currupt has onethrough, join id should be: ".$other->__get($options['dk']." but got: ".$middle->id));
            return false;	
          }
          
          /* ok, link through the join table */
          
          $middle->__set($options['sk'], -1);
          if($middle->save() === false) {
          	$this->error("Can not do unlink (has one/many through), problem with save(): ".$middle->getError());
            return false;
          }
          
          $other->__set($options['dk'], -1);
          if($other->save() === false) {
          	$this->error("Can not do unlink (has one/many through), problem with save(): ".$other->getError());
            return false;
          }
          
          return $middle;
        }
        break;
        
      case self::HAS_MANY_THROUGH:
        {
          if($incoming != self::HAS_MANY_THROUGH) {
            $this->error("Can not do unlink (has many through), wrong kind of incoming link: $incoming (for has one through)\n");
            return false;	
          }
            
          if($options['through'] === false) {
            $this->error("Can not do unlink (has many through), no joining model.");
            return false;
          }
    
          /* get an instance of the middle model */
    
          $through = $options['through'];
    
          /* 
           * double check and make sure the join model really is a join model 
           * for the source and sink...
           * 
           */
    
          $middle = self::create($through);
          if(($middle === false)||($middle->isReady() === false)) {
            $this->error("Can not do unlink (has many through), model ($through) not usable");
            return false;	
          }
          
          if(!isset($middle->relBelongsTo[$this->tableName])) {
            $this->error("Can not do unlink (has many through), $through does not belong to ".$this->tableName);
            return false;
          }
          if(!isset($middle->relBelongsTo[$tableName])) {
            $this->error("Can not do unlink (has many through), $through does not belong to ".$tableName);
            return false;
          }
    
          if(!isset($middle->fieldTypes[$options['sk']])) {
            $this->error("Can not do unlink (has many through), join model (".$through.") has no link column (".$options['sk'].") for model ".$this->tableName);
            return false;
          }
          if(!isset($middle->fieldTypes[$options['dk']])) {
            $this->error("Can not do unlink (has many through), join model (".$through.") has no link column (".$options['dk'].") for model ".$tableName);
            return false;
          }
          
          $whereSQL  = "WHERE ".$options['sk']."='".$this->id."'";
          $whereSQL .= " AND ".$options['dk']."='".$other->id."'";
          
          $matches = $middle->find(strval($whereSQL));
          if($matches === false) {
            $this->error("Can not do unlink, problem with find: ".$middle->getError());
            return false;
          }
    
          if(count($matches) != 1) {
            $this->error("Can not do unlink, currupt has many through join, no link or too many links");
            return false;		
          }
          
          $middle = array_shift($matches);
            
          /* ok, link through the join table */
          
          $middle->__set($options['sk'], -1);
          $middle->__set($options['dk'], -1);
          
          /* unlink! */
          
          if($middle->save() === false) {
          	$this->error("Can not do unlink (has many through), problem with save(): ".$middle->getError());
            return false;
          }
          
          return $middle;
        }
        break;
      
      case self::HAS_AND_BELONGS_TO_MANY:
        {
          if($incoming != self::HAS_AND_BELONGS_TO_MANY) {
            $this->error("Can not unlink (has and belongs to), wrong kind of incoming link: $incoming (for has and belongs to many)\n");
            return false;	
          }
          
          if($options['jointable'] === false) {
            $this->error("Can not unlink (has and belongs to), no joining model.");
            return false;	
          }
          
          $whereSQL  = $options['sk']."='".$this->id."' AND ";
          $whereSQL .= $options['dk']."='".$other->id."';";
          
          $sql = "DELETE FROM ".$options['jointable']." WHERE $whereSQL;";

          $result = $this->db->query($sql);
          if($result === false) {
            $this->error("Can not link (has and belongs to), SQL problem: ".$this->db->getError());
            return false;
          } 
          
          return true;
        }
        break;
        
      default:
      	{
      	  $this->error("Can not do unlink, unrecognized relationship ".$this->getTableName()." <=> ".$other->getTableName());
      	  return false;
        }
        break;
  	}
  	
  	/* all done */
  	
  	return true;
  }
}

?>