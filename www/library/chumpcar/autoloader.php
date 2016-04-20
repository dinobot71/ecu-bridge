<?php 

/**
 * 
 * autoloader.php - registered autoloading features for the
 * chumpcar library.
 * 
 * @package chumpar
 * 
 * @author Little m Design (Michael Garvin)
 * @copyright Copyright (c) 2016-, Littl m Design
 * 
 */

/**
 * 
 * loadChumpCarClass() - autoloader for Chump Car
 * classes. We expect the autoloader to be included from all
 * of our library classes, so if its  already been incldued,
 * don't redefine.
 * 
 * 
 */

if(!function_exists('loadChumpCarClass')) {
	
  function loadChumpCarClass($className) {

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
     * if they aren't loading a chump car class, skip.
     * 
     */
    
    if(!preg_match('/chumpcar/i', $className)) {
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
      error_log("[chumpcar\autoloader] ERROR - can not determin actual class name ($className)");
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

      error_log("[chumpcar\autoloader] ERROR - can not find class file for '$className'.");
      error_log("[chumpcar\autoloader] ERROR - search path was: ".implode($INCSEP, $searchPaths));	
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
        if(!class_exists($actual)&&!interface_exists($actual)) {
          class_alias($orig, $actual);
        }
      }
    }
    
    /* all done */
  }
}

/**
 * 
 * autorequire() - given a class auto-load its class file.
 * 
 */

if(!function_exists('autorequire')) {
  function autorequire($className) {
  	spl_autoload_call(trim($className,'\\'));
  }
}

/*
 * try to actually register our class auto-loader...
 * 
 */

if(!function_exists('spl_autoload_register')) {

  error_log("[chumpcar\autoloader] ERROR - PHP does not have 'spl_autoload_register', old PHP version (".PHP_VERSION.")?");

} else {
	
  /* if nothing is registered yet, then register ours */
	
  $funcs = spl_autoload_functions();
  if($funcs === false) {
  	
    spl_autoload_register('loadChumpCarClass');
  	
  } else {
  
    /* if we are not already registered, then register */
  
    if(!in_array('loadChumpCarClass', $funcs)) {
  	  spl_autoload_register('loadChumpCarClass');
    }
  }
   
  /* confirm */
  
  $funcs = spl_autoload_functions();
  if(!in_array('loadChumpCarClass', $funcs)) {
  	error_log("[chumpcar\autoloader] ERROR - could not register auto-loader.");
  }
}

/**
 * 
 * pull in any other autoloaders from libraries that we rely on,
 * that is folders beside this one.
 * 
 */

{
  /* figure out what the other auto-loaders are */
	
  $top     = dirname(dirname(__FILE__));
  $folder  = basename(dirname(__FILE__));
  $loaders = glob("$top/*/autoloader.php");
  
  $otherLoaders = array();
  foreach($loaders as $item) {
  	$otherFolder = basename(dirname($item));
  	if($otherFolder != $folder) {
      $otherLoaders[] = $item;
  	}
  }

  /* bring in the other auto-loaders */
  
  foreach($otherLoaders as $item) {
  	$path = realpath($item);
  	require_once($path);
  }
}

?>