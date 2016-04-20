<?php 

/**
 * 
 * autoloader.php - register autoloading features for the
 * little  m design library.
 * 
 * @package littlemdesign.com
 * 
 * @author Little m Design (Michael Garvin)
 * @copyright Copyright (c) 2013-, Littl m Design
 * 
 */

/**
 * 
 * loadLittlmDesignClass() - auto-loader for Little m Design
 * classes. We expect the autoloader to be included from all
 * of our library classes, so if its  already been incldued,
 * don't redefine.  This auto-loader is intended for regular
 * classes.  Special classes like Object Relational Mapping
 * (ORM) table classes ("model" classes) are loaded via the
 * loadModelClass() auto-loader (see below).
 * 
 */

if(!function_exists('loadLittlmDesignClass')) {
	
  function loadLittlmDesignClass($className) {

  	/*
  	 * If we are trying to load a "model" class; a class 
  	 * that represents a table in the datasbse (ORM 
  	 * feature), then we have a special search path, to 
  	 * make it easier for users to include their own 
  	 * model classes.
  	 * 
  	 */
  	
  	$pm = array();
  	if(preg_match('/^[mM]odel_(.*)$/',$className, $pm)) {
  	  return loadModelClass($pm[1]);
  	}
  	
    /* figure out how to work with paths on this system */
  	
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
     * if they aren't loading a little m design class, skip.
     * 
     */
    
    if(!preg_match('/littlemdesign[\\\\_]/i', $className)) {
      return ;
    }
    
    /* 
     * try to pull ou the vender name 
     * 
     */
    
    $pm     = array();
    $vendor = "";
    $actual = "";
    $orig   = str_replace("\\" , '_', $className);
    
    if(preg_match('/^([^\\\\_]+)_/', $className, $pm)) {
      $vendor = $pm[1];   	
    }
    
    if(preg_match('/([^\\\\_]+)$/', $className, $pm)) {
      $actual = $pm[1];   	
    }
    
    if(empty($actual)) {
      error_log("[littlemdesign.com\autoloader] ERROR - can not determin actual class name ($className)");
      return ;  	
    }
    
    /* 
     * replace _ with the directory separator, class names
     * are mangled by prepending with namespaces separated by 
     * _, this convention is intended to be usable accross 
     * a variety of PHP versions, where namesapces many not 
     * actually be implemented yet.
     * 
     */
    
    $className = str_replace("_" , $DS, $className);
    $className = str_replace("\\" , $DS, $className);
    
    /* 
     * the locations to search, we search from here down,
     * and from our parent directory down (because the
     * namespace may include our vendor name)
     * 
     */
    
    $searchPaths = array(
      dirname(__FILE__),
      dirname(dirname(__FILE__))
    );
    $searchPaths = array_merge($searchPaths, explode($INCSEP, get_include_path()));
    
    $suffixes = array(
      ".php",
      ".class.php",
      ".inc"
    );
    
    /* auto-load */
    
    $toRequire = "";
    foreach($searchPaths as $prefix) {
      
      /* next path */
    	
      foreach($suffixes as $suffix) {
      	
      	/* next naming style */
      	
        $classFile = $prefix.$DS.$className.$suffix;
              
        if(is_readable($classFile)) {
        	
          $toRequire = $classFile; 
             	
        } else {
        	
          $classSuffixes = array(
            ".com",
            ".net",
            ".org"
          );
          foreach($classSuffixes as $classSuffix) {
          	
          	$pm = array();
          	if(preg_match('/^([^\\\\\/]+)([\\\\\/]+)(.*)$/',$className, $pm)) {
              $otherName = $pm[1].$classSuffix.$DS.$pm[3];
              $classFile = $prefix.$DS.$otherName.$suffix;
              
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
      }
      if(!empty($toRequire)) {
      	break;
      }
    }
    
    if(empty($toRequire)) {
    
      /* we failed to find the class */

      error_log("[littlemdesign.com\autoloader] ERROR - can not find class file for '$className'.");
      error_log("[littlemdesign.com\autoloader] ERROR - search path was: ".implode($INCSEP, $searchPaths));	
      return ;
    }

    /* require it */
    
    require_once($toRequire);
    
    /* 
     * if we have the ability to create class name aliases,
     * then do it, and create an unqualified name for the
     * class in the current script.
     * 
     */

    if(version_compare(phpversion(), '5.3.0', '>')) {
      if(class_exists($orig,false)||interface_exists($orig,false)) {
        if(!class_exists($actual, false)&&!interface_exists($actual, false)) {	  
          class_alias($orig, $actual);
        }
      }
    }
    
    /* all done */
  }
  
  /**
   * 
   * loadModelClass() - when we know we are looking for a model
   * class (ORM feature), we use a special search path:
   * 
   *   <base dir>\schema\model\<table name>.php
   *   <base dir>\classes\model\<table name>.php
   *   <base dir>\model\<table name>.php
   *   <base dir>\*\model\<table name>.php
   *   <base dir>\*\schema\model\<table name>.php
   *   <base dir>\*\classes\model\<table name>.php
   *   
   * We search in that top to bottom order.  The first matching
   * file is used.  
   * 
   * Note that the .php exension can
   * 
   * <bas dir> is the directory that you installed the 
   * littlemdesign library folder into (i.e. your docroot).  The
   * <table name> is $className converted to lowercase with an
   * spaces or '_' characters removed.
   * 
   * @param $className the name of the class we are trying to
   * auto-load.
   * 
   */
  
  function loadModelClass($className) {
  	
  	$className = $className = str_replace('_', '', $className);
  	$className = $className = str_replace('_', '', $className);
  	
  	if(empty($className)) {
  	  return false;	
  	}
  	
    /* figure out how to work with paths on this system */
  	
    $DS        = "\\";
    $INCSEP    = ";";
    $toRequire = "";
    
    if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
      $DS     = "\\";
      $INCSEP = ";";
    } else {
      $DS     = "/";
      $INCSEP = ":";
    }
    
  	/* what is the basedir? */
  	
  	$baseDir = dirname(dirname(__FILE__));
  	
  	/* where do we look? */
  	
  	$searchPaths = array(
  	  $baseDir.$DS."schema".$DS."model".$DS,
      $baseDir.$DS."classes".$DS."model".$DS,
      $baseDir.$DS."model".$DS,
      $baseDir.$DS."*".$DS."model".$DS,
      $baseDir.$DS."*".$DS."schema".$DS."model".$DS,
      $baseDir.$DS."*".$DS."classes".$DS."model".$DS
  	);
  	
  	$suffixes = array(
      ".php",
      ".class.php",
      ".inc"
    );
    
    /* walk the search paths... */
    
    foreach($searchPaths as $pathSpec) {
    	
      $path      = $pathSpec.$className;
      $toRequire = false;
      $pos       = strpos($path, '*');
      
      if(($pos === false)||($pos<0)) {
      	
      	/* normal path */
      	 
        foreach($suffixes as $suffix) {
        	
          $toRequire = $path.$suffix;
          
          if(is_readable($toRequire)) {
            break;    
          }
          $toRequire = false;
        }

      } else {
      	
      	/* glob style */
      	
        foreach($suffixes as $suffix) {
        	
          $toRequire = $path.$suffix;

          $matches = glob($toRequire);
          
          if(count($matches)>0) {

          	$toRequire = $matches[0];
          	
            if(is_readable($toRequire)) {  
              break;    
            }
          }
          $toRequire = false;
        } 	
      }
      
      if($toRequire !== false) {
      	break;
      }
    }
    
    if($toRequire === false) {
      return false;
    }
    
    /* require it */
    
    require_once($toRequire);
    
    /* all done */
  }
}

/**
 * 
 * autorequire() - given a class auto-load its class file.
 * 
 * @param string $className the name of the class file to
 * load.  The name is normally also a qualified name that
 * indicates the namespace.  Either by '_' separating 
 * folders names and the actual class name, or the '\' 
 * character.
 * 
 * @param boolen noAlias normally when autoloading we also
 * define a class name alias (the unqualified version) for 
 * the qualified class name, so that you don't have to enter 
 * the qualified name everytime you want to use a class.  
 * Short names can potentially collide with local variables
 * or classes though.  So you can turn off class aliasing 
 * if you need to.
 * 
 */

if(!function_exists('autorequire')) {
  
  function autorequire($className) {

    spl_autoload_call(trim($className, '\\'));
        
  }
  
}

/*
 * try to actually register our class auto-loader...
 * 
 */

if(!function_exists('spl_autoload_register')) {

  error_log("[littlemdesign.com\autoloader] ERROR - PHP does not have 'spl_autoload_register', old PHP version (".PHP_VERSION.")?");

} else {
	
  /* if nothing is registered yet, then register ours */
	
  $funcs = spl_autoload_functions();
  if($funcs === false) {
  	
    spl_autoload_register('loadLittlmDesignClass');
  	
  } else {
  
    /* if we are not already registered, then register */
  
    if(!in_array('loadLittlmDesignClass', $funcs)) {
  	  spl_autoload_register('loadLittlmDesignClass');
    }
  }
   
  /* confirm */
  
  $funcs = spl_autoload_functions();
  if(!in_array('loadLittlmDesignClass', $funcs)) {
  	error_log("[littlemdesign.com\autoloader] ERROR - could not register auto-loader.");
  }
}

?>