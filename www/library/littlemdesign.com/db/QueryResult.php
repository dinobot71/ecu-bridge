<?php

/**
 * 
 * db \ QueryResult - to allow for easy iteration and buffering
 * of query results, we use QueryResult to return results of
 * Query::find() back to users.
 * 
 * Normally the result is a map of objects any included (and
 * relted objects).  The main objects are the matching objects
 * from the table the query was launched from, while the 
 * "included" objects are any that the user called Query::includeTable()
 * on...so that follow up calls to fetch the related objects would 
 * not be necesary.
 * 
 * To provide the related objects in the context of the query, 
 * we make them available through QueryResults::related().  Given
 * one of the results that has been iterated on, the related()
 * method can then fetch related objects for the iterated object,
 * or in turn any object returned by related().  Allowing the user
 * to essentially walk the implied graph of results.
 *     
 * When the output/formatting mode is either lazy or associative,
 * then a simpler (and faster) basic array is used as the internal
 * representation, and iteration produces rows of columns.  In 
 * either of these modes includeTable() hs no effect...to include 
 * additional objects object output format must be used.  
 * 
 * Note however though that Query::seldct() can be used to cherry 
 * pick columns to output, so if you need a column from another 
 * table that is invovled in a complex query, just select() it
 * to include it.  One or more calls to  Query::select() will force
 * array associtive mode.
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

class littlemdesign_db_QueryResult
  extends littlemdesign_util_Object
  implements Iterator {
  	
  /**
   * 
   * the formatting mode, one of LAZY, OBJECT or ASSOC 
   * from Query.  Different results need to be handled
   * differently.
   * 
   * @var constant
   * 
   */
  	
  private $mode;
  
  const LAZY   = 1;
  const OBJECT = 2;
  const ASSOC  = 3;
  
  public $total     = -1;
  public $pageStart = -1;
  public $pageSize  = -1;
  public $pageNum   = -1;
  
  /**
   * 
   * the lazy loaded (current) object.
   * 
   * @var model
   * 
   */
  
  private $fetched = null;
  
  /**
   * 
   * The actual results object
   * 
   * @var object
   * 
   */
  
  private $results = -1;
  
  /**
   * 
   * standard constructor - do not call directly, only Query::find() should
   * construct these objects.  users will normally use them but not construct
   * them.
   * 
   */
  
  public function __construct($mode, $results, $className='QueryResult', $logger=null) {

  	parent::__construct($className, 'littlemdesign_db', true);

  	$this->unReady();
  	
  	switch($mode) {
      case self::LAZY:
      case self::OBJECT:
      case self::ASSOC:
        {
      	  $this->mode = $mode;	
        }	
        break;
      default:
      	{
      	  $this->error("Constructing, bad mode: $mode");
      	  return ;
      	}
        break;
  	}
  	
  	$this->results = $results;
  	
  	/* we are ready for action! */
    
    $this->makeReady();
    
    $this->rewind();  	
  }
  
  /**
   * 
   * rewind() reset the iterator to the beginning of 
   * the results.
   * 
   */
  
  function rewind() {
  	
    if(!$this->isReady()) {
      return null;
    }
    
    $this->fetched = null;
    
    if($this->total < 1) {
      return null;
    }
    
    switch($this->mode) {
    	
      case self::OBJECT:
        {
          $bucket = reset($this->results->roots);
          $object = $bucket->object;
          return $object;
        }
        break;
        
      case self::LAZY:
      case self::ASSOC:
        {
          return reset($this->results->rows);
        }
        break;
        
      default:
        {
      	  return null;	
        }
        break;
    }
  	
  }
  
  /**
   * 
   * current() - return the value of the current position.
   * 
   */

  function current() {
  	
    if(!$this->isReady()) {
      return null;
    }
    
    if($this->total < 1) {
      return null;
    }
    
    switch($this->mode) {

      case self::OBJECT:
        {
        
          $bucket = current($this->results->roots);
          $object = $bucket->object;
          
          return $object;
        }
        break;
        
      case self::LAZY:
        {
          $arr = current($this->results->rows);
          if(!is_array($arr)) {
            return null;
          }
          return current($arr);
        }
        break;
        
      case self::ASSOC:
        {
          return current($this->results->rows);
        }
        break;
        
      default:
        {
      	  return null;	
        }
        break;	
    }
  }
  
  /**
   * 
   * key() - fetch the current positions key
   * 
   */
  
  function key() {
  	
    if(!$this->isReady()) {
      return null;
    }
    
    switch($this->mode) {
      
      case self::OBJECT:
        {
          $key = key($this->results->roots);
          
          return $key;
        }
        break;
        
      case self::LAZY:
      case self::ASSOC:
        {
          return key($this->results->rows);
        }
        break;
        
      default:
        {
      	  return null;	
        }
        break;	
    }    
  }

  /**
   * 
   * next() - advance to the next position and return the 
   * value of that position.
   * 
   */
  
  function next() {
  	
    if(!$this->isReady()) {
      return null;
    }
    
    $this->fetched = null;
    
    switch($this->mode) {

      case self::OBJECT:
        {
          $bucket = next($this->results->roots);
          if(!is_object($bucket)) {
          	return null;
          }
          
          $object = $bucket->object;
          
          return $object;
        }
        break;
        
      case self::LAZY:
        {
          $arr = next($this->results->rows);
          if(!is_array($arr)) {
            return null;
          }
          return current($arr);
        }
        break;
      	
      case self::ASSOC:
        {
          return next($this->results->rows);
        }
        break;
        
      default:
        {
      	  return null;	
        }
        break;	
    } 
  }
  
  /**
   * 
   * valid() check to see if we are past th end.
   * 
   */

  function valid() {

    if(!$this->isReady()) {
      return null;
    }
    
    switch($this->mode) {

      case self::OBJECT:
        {
          $status = (key($this->results->roots) !== null);
          
          return $status;
        }
        break;
        
      case self::LAZY:
      case self::ASSOC:
        {
          return (key($this->results->rows) !== null);
        }
        break;
        
      default:
        {
      	  return null;	
        }
        break;	
    }     	
  }
  
  /**
   * 
   * related() - given one of objects in this result set, which
   * may be one of the main results or included results, walk from
   * it to the given kind ($tableName) of related objects.
   * 
   * @param model $object one of the objets (direct or indirect)
   * in this result set.
   * 
   * @param string $tableName the name of the related kind of model
   * we should walk to.  For this to work you must have done includeTable()
   * on $tableName in the Query.
   * 
   * @return array return one or more related instances if there are
   * any.  If no relations return empty array.  If there is a problem
   * return exactly false.
   * 
   */
  
  public function related($object, $tableName) {
  	
  	$results = array();
  	
    if(!$this->isReady()) {
      $this->error("related() - object is not ready.");
      return false;
    }
    
    if($this->mode != self::OBJECT) {
      $this->error("related() - not in object output mode.");
      return false;	
    }
    
    if(!($object instanceof littlemdesign_db_ORMTable)) {
      $this->error("related() - given object is not a model.");
      return false;	
    }
      
    $tableName = littlemdesign_db_ORMTable::cleanTableName($tableName);
    $pkey      = $object->getTableName().':'.$object->getId();
        
    if(!isset($this->results->objects[$pkey])) {
      $this->error("related() - can not find object $pkey");
      return false;
    }
    
    $bucket = $this->results->objects[$pkey];
    
    foreach($bucket->links as $linkPkey) {

      /* filter out unrelated or bad keys */

      if(!preg_match('/^'.$tableName.':/', $linkPkey)) {
        continue;
      }
      
      if(!isset($this->results->objects[$linkPkey])) {
      	continue;
      }
      
      $otherBucket = $this->results->objects[$linkPkey];
      $otherObject = $otherBucket->object;
      
      /* accumulate */
      
      $results[] = $otherObject;
    }
    
    /* all done */
    
    return $results;
  }
  
  public function fetch() {
  	
  	/*
  	 * if we haven't yet fetched the lazy loaded object for this
  	 * position, go get it.
  	 * 
  	 */
  	
    if($this->fetched !== null) {
    	
      /* its already set */
    	
      return $this->fetched;
    }
    
    /* if we are not in lazy mode, this method doesn't apply */
    
    if($this->mode != self::LAZY) {
      $this->error("fetch() - not in lazy load mode");
      return false;
    }
    
    /* are we at the end of the results? */
    
    if(!$this->valid()) {
      return null;
    }
    
    /* 
     * figure out the table we want, and what row we 
     * want from that table.
     * 
     */
    
    $arr       = current($this->results->rows);
    $pkey      = key($arr);
    $id        = current($arr);
    $tableName = "";
    $matches   = array();
    
    if(!preg_match('/^([^:]+):(.*)$/', $pkey, $matches)) {
      $this->error("fetch() - can't read table name from pkey ($pkey)");
      return false;
    }
    $tableName = $matches[1];
    
    $object = littlemdesign_db_ORMTable::create($tableName, $id);
    
    if(!is_object($object)) {
      $this->error("fetch() - problem making model $tablename:$id");
      return true;
    }
    
    return $object;
  }
}

?>