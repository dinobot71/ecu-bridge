<?php

/**
 * 
 * db \ MySQLField - this helper class manages the type conversion
 * between the actual database fields and how we work with the fields
 * in PHP.
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

/**
 * 
 * class MySQLField - this is lightweight object is used to
 * convert between the PHP notion of a table column, and 
 * the database idea of a column.  Different databases 
 * implement column types in different ways, but we try 
 * to provide a consistent and simplified version of "fields"
 * to users of this API.
 * 
 * Field Types:
 * 
 *   'text' - text, reasonably small strings (<< 64K)
 *      'name'    - name of field
 *      'default' - initialization value (optional), *blobs can't have a default*
 *      'notnull' - value must be initialized (optional)
 *      'length'  - # of bytes
 *      'fixed'   - CHAR(n) instead of VARCHAR(n)
 *      
 *   'clob' - large object that is encoded as text
 *      'name'    - name of field
 *      'default' - initialization value (optional)
 *      'notnull' - value must be initialized (optional)
 *      'length'  - # of bytes
 *     
 *   'blob' - binary large object 
 *      'name'    - name of field
 *      'default' - initialization value (optional)
 *      'notnull' - value must be initialized (optional)
 *      'length'  - # of bytes 
 *      
 *   'integer' - integer of various sizes
 *      'name'    - name of field
 *      'default' - initialization value (optional)
 *      'notnull' - value must be initialized (optional)
 *      'length'  - # of bytes
 *     
 *   'decimal' - decimal (not floating)
 *      'name'    - name of field
 *      'default' - initialization value (optional)
 *      'notnull' - value must be initialized (optional)
 *      'length'  - # of digits on left
 *      'scale'   - # of digits on right
 *      
 *   'float'   - floating point number
 *      'name'    - name of field
 *      'default' - initialization value (optional)
 *      'notnull' - value must be initialized (optional)
 *      'length'  - # of digits on left
 *      'scale'   - # of digits on right 
 *      
 *   'boolean' - binary flag
 *      'name'    - name of field
 *      'default' - initialization value (optional)
 *      'notnull' - value must be initialized (optional)
 *      
 *   'date' - just the date
 *      'name'    - name of field
 *      'default' - initialization value (optional)
 *      'notnull' - value must be initialized (optional)
 *   
 *   'time' - just the time
 *      'name'    - name of field
 *      'default' - initialization value (optional)
 *      'notnull' - value must be initialized (optional)
 *   
 *   'timestamp' - julian timestamp
 *      'name'    - name of field
 *      'default' - initialization value (optional)
 *      'notnull' - value must be initialized (optional)
 *   
 * Notes:
 * 
 *   - notnull and fixed are boolean flags
 *   
 *   - if you provide notnull but no default value, a deafult 
 *     will be filled in for you.
 *     
 *   - numerical fields have their "word size" set by length.
 *   
 */

class littlemdesign_db_MySQLField {

  /* the PHP version of a field type */
	
  public $name     = null;
  public $type     = null;
  public $length   = null;
  public $scale    = null;
  public $fixed    = null;
  public $unsigned = null;
  public $notnull  = null;
  public $default  = null;
  
  /* the database version of a type */
  
  public $decl     = "";
  
  /* the type and any decoration like default value */
  
  public $fullDecl = "";
  
  /**
   * 
   * constructor - minimum field definition is the name and type, other 
   * parameters can have defaults assumed, that are appropriate for that 
   * type.
   * 
   */
  
  public function __construct(
    $name,
    $type, 
    $length   = '', 
    $unsigned = false, 
    $default  = '', 
    $notnull  = false, 
    $scale    = '', 
    $fixed    = false) {
  	
    $this->name     = strtolower(trim($name));
    $this->type     = strtolower(trim($type));

    if(($length === false)||($length === null)||empty($length)) {
      $this->length = '';
    } else {
      $this->length = $length;
    }
    
    if(($scale === false)||($scale === null)||empty($scale)) {
      $this->scale = '';
    } else {
      $this->scale = $scale;
    }

    if(($fixed === false)||($fixed === null)||(empty($fixed))) {
      $this->fixed = false;
    } else {
      $this->fixed = true;
    }
    
    if(($unsigned === false)||($unsigned === null)||(empty($unsigned))) {
      $this->unsigned = false;
    } else {
      $this->unsigned = true;
    }
    
    if(($notnull === false)||($notnull === null)||(empty($notnull))) {
      $this->notnull = false;
    } else {
      $this->notnull = true;
    }
    
    if(($default === false)||($default === null)||(empty($default))) {
      $this->default = false;
      if($default === null) {
      	$this->default = null;
      }
    } else {
      $this->default = $default;
    }
    
    /* figure out the database version of this field type */
    
    $this->decl = "";
    $default    = "";
          	
    if($this->type == "text") {
      
      if($this->fixed) {
      	      	
      	if(empty($this->length)) {
          $this->decl = 'CHAR(255)';
          if(($this->default)||($this->notnull)) {
          	$default = "DEFAULT '".$this->default."'";
          }
      	} else {
          $this->decl = 'CHAR('.$this->length.')';
          if(($this->default)||($this->notnull)) {
          	$default = "DEFAULT '".$this->default."'";
          }
      	}
      	
      } else {
      	
      	if(empty($this->length)) {
      	  $this->decl = 'TEXT';
      	} else {
          if($this->length > (65532/3)) {
          	$this->decl = 'TEXT';
          } else {
      	    $this->decl = 'VARCHAR('.$this->length.')';
      	    if(($this->default)||($this->notnull)) {
          	  $default = "DEFAULT '".$this->default."'";
            }
          }
      	}
      }
      
      $n = empty($this->notnull) ? '' : ' NOT NULL';
      $this->fullDecl = $this->name.' '.$this->decl.' '.$default.$n;
      
    } else if($this->type == "clob") {

      if (!empty($this->length)) {

        if ($this->length <= 255) {
          $this->decl = 'TINYTEXT';
        } else if ($this->length <= 65532) {
          $this->decl = 'TEXT';
        } else if ($this->length <= 16777215) {
          $this->decl = 'MEDIUMTEXT';
        } else {
          $this->decl = 'LONGTEXT';
        }
      } else {
      	$this->decl = 'LONGTEXT';
      }
      
      $n = empty($this->notnull) ? '' : ' NOT NULL';
      $this->fullDecl = $this->name.' '.$this->decl.$n;
      
    } else if($this->type == "blob") {
    	
      if (!empty($this->length)) {
 
        if ($this->length <= 255) {
          $this->decl = 'TINYBLOB';
        } else if ($this->length <= 65532) {
          $this->decl = 'BLOB';
        } else if ($this->length <= 16777215) {
          $this->decl = 'MEDIUMBLOB';
        } else {
          $this->decl = 'LONGBLOB';
        }
      } else {
        $this->decl = 'LONGBLOB';
      }	
      
      $n = empty($this->notnull) ? '' : ' NOT NULL';
      $this->fullDecl = $this->name.' '.$this->decl.$n;
      
    } else if($this->type == 'integer') {
    	
      if (!empty($this->length)) {

        if ($length <= 1) {
          $this->decl = 'TINYINT';
        } else if ($this->length == 2) {
          $this->decl = 'SMALLINT';
        } elseif ($this->length == 3) {
          $this->decl = 'MEDIUMINT';
        } elseif ($this->length == 4) {
          $this->decl = 'INT';
        } elseif ($this->length > 4) {
          $this->decl = 'BIGINT';
        }
      } else {
        $this->decl = 'INT';
      }
      
      if(empty($this->default)) {
      	if($this->notnull === false) {
      	  $this->default = 'NULL';
      	} else {
      	  $this->default = '0';
      	}
      }
      
      if($this->default == 'NULL') {
      	$default = " DEFAULT NULL";
      } else {
        $default = " DEFAULT '".$this->default."'";
      }
      
      $u = empty($this->unsigned) ? '' : ' UNSIGNED';
      $n = empty($this->notnull) ? '' : ' NOT NULL';
      $this->fullDecl = $this->name.' '.$this->decl.$u.$default.$n;
      
    } else if($this->type == 'decimal') {

      if(empty($this->length)) {
      	$this->length = 18;
      } 
      if(empty($this->scale)) {
      	$this->scale = 4;
      }
      
      $this->decl = 'DECIMAL('.$this->length.','.$this->scale.')';
      
      if(empty($this->default)) {
      	if($this->notnull === false) {
      	  $this->default = 'NULL';
      	} else {
      	  $this->default = '0';
      	}
      }
      
      if($this->default == 'NULL') {
      	$default = " DEFAULT NULL";
      } else {
        $default = " DEFAULT '".$this->default."'";
      }
      
      $u = empty($this->unsigned) ? '' : ' UNSIGNED';
      $n = empty($this->notnull) ? '' : ' NOT NULL';
      $this->fullDecl = $this->name.' '.$this->decl.$u.$default.$n;
      
    } else if($this->type == 'float') {
    
      $this->decl = 'DOUBLE';
      
      if(empty($this->scale)) {
      	$this->scale = 4;
      }
      
      if(!empty($this->length)) {
        $this->decl .= '('.$this->length;
        $this->decl .= ','.$this->scale.")";
      }
      
      if(empty($this->default)) {
      	if($this->notnull === false) {
      	  $this->default = 'NULL';
      	} else {
      	  $this->default = '0';
      	}
      }
      
      if($this->default == 'NULL') {
      	$default = " DEFAULT NULL";
      } else {
        $default = " DEFAULT '".$this->default."'";
      }
      
      $u = empty($this->unsigned) ? '' : ' UNSIGNED';
      $n = empty($this->notnull) ? '' : ' NOT NULL';
      $this->fullDecl = $this->name.' '.$this->decl.$u.$default.$n;

    } else if($this->type == 'boolean') {
    	
      $this->decl = 'TINYINT(1)';
      if(empty($this->default)) {
      	if($this->notnull !== false) {
          $this->default = '0';
      	}
      }
      if($this->default !== null) {
      	if($this->default) {
      	  $default = " DEFAULT '1'";
      	} else {
          $default = " DEFAULT '0'";
      	}
      }
      $n = empty($this->notnull) ? '' : ' NOT NULL';
      $this->fullDecl = $this->name.' '.$this->decl.$default.$n;

    } else if($this->type == 'date') {
    	
      $this->decl = 'DATE';
      
      if(!empty($this->default)) {
        $default = " DEFAULT '".$this->default."'";
      }
      $n = empty($this->notnull) ? '' : ' NOT NULL';
      $this->fullDecl = $this->name.' '.$this->decl.$default.$n;
      
    } else if($this->type == 'time') {
    	
      $this->decl = 'TIME';
      
      if(!empty($this->default)) {
        $default = " DEFAULT '".$this->default."'";
      }
      $n = empty($this->notnull) ? '' : ' NOT NULL';
      $this->fullDecl = $this->name.' '.$this->decl.$default.$n;
      
    } else if($this->type == 'timestamp') {
    	
      $this->decl = 'DATETIME';
      
      if(empty($this->default)) {
      	if($this->notnull !== false) {
      	  $this->default = 'CURRENT_TIMESTAMP';
      	}
      }
      if(!empty($this->default)) {
      	if($this->default == 'CURRENT_TIMESTAMP') {
          $default = " DEFAULT CURRENT_TIMESTAMP";
      	} else {
      	  $default = " DEFAULT '".$this->default."'";
      	}
      }
      
      $n = empty($this->notnull) ? '' : ' NOT NULL';
      $this->fullDecl = $this->name.' '.$this->decl.$default.$n;
    } 
    
  }
  
}

/*
$f = new littlemdesign_db_MySQLField(
    'myfield',
    'timestamp', 
    $length   = '5', 
    $unsigned = false, 
    $default  = '', 
    $notnull  = false, 
    $scale    = '', 
    $fixed    = false);
    
echo "X: ".print_r($f,true)."\n";
*/

?>
