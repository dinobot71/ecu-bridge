<?php

  /*
   * NOTE: we expect that php.ini (/etc/php5/cli/php.ini) has 
   * been updated to have an appropriate include path so when we
   * ask for files relative to this web folder, we can get them
   * ok.
   * 
   */

  /* how do paths work? */

  if(!defined('DS')) {
    if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
      define("DS", "\\");
      define("INCSEP", ";");
    } else {
      define("DS", "/");
      define("INCSEP", ":");
    }
  }
  
  /* the root directory to 'include' stuff from */
  
  $parts = explode(DS, __FILE__);
  $root  = dirname(__FILE__);

  define("TMPLT_BASE",          $root);
  define("TMPLT_PARTS",         $root.DS."parts");
 
  $incpath = ini_get("include_path");
  $incpath = $root.INCSEP.$incpath;
  $incpath = TMPLT_PARTS.INCSEP.$incpath;

  ini_set("include_path", $incpath);
  
  /* figure out the top of the document tree */
  
  $parts = explode("/", $_SERVER['REQUEST_URI']);
  $root  = "";
  
  foreach($parts as $comp) {
    $root .= $comp;
    $root .= "/";
    if(($comp=="en")||($comp=="fr")) {
      $root=dirname($root);
      break;
    }
    if($comp=="proto") {
      break;
    }

  }
  
  /* general settings */
  
  define("TMPLT_SITE_TITLE", "Chump Car");

  //if($root=="/") {
    $root="";
  //}
  
  define("TMPLT_HTML_PARTS", $root."/parts");
  define("TMPLT_DOCROOT",    $root);
  define("TMPLT_CSS",        TMPLT_DOCROOT."/css");
  define("TMPLT_IMAGES",     TMPLT_DOCROOT."/images");
  define("TMPLT_FLASH",      TMPLT_DOCROOT."/flash");
  define("TMPLT_VIDEO",      TMPLT_DOCROOT."/video");
  define('TMPLT_SCRIPTS',    TMPLT_DOCROOT."/Scripts");
  define("TMPLT_DOMAIN",     $_SERVER['HTTP_HOST']);

  /* server IP address */
    
  /* make sure we can work with the database */

  require_once("autoloader.php");

  /* 
   * If using a database, pull in SQLFactgory here 
   * 
   */
    
  /*
   * - - - - - - - - - - - - - - - - - - - - - - - - - - -
   * 
   *
   * past this point the general environment is configured and ready for use
   *
   *
   * - - - - - - - - - - - - - - - - - - - - - - - - - - -
   */
   
  /* 
   * Useful global function 
   * 
   */
  
  /**
   * 
   * toAscii() - convert a given string to a "slug" that we can
   * use in URL names that are SEO friendly.
   * 
   * @param string str - the string to convert to a slug
   * 
   * @param array repalce - set of characters to replace
   * first before we do anything so that we can separate i'll
   * and ill. 
   * 
   * @param char delimiter the character to replace any 
   * non-slug character with.
   * 
   * @return string - the converted slug.
   * 
   * NOTE: 
   * 
   *   http://cubiq.org/the-perfect-php-clean-url-generator
   *   
   */
  
  setlocale(LC_ALL, 'en_US.UTF8');
  
  function toAscii($str, $replace=array(), $delimiter='-') {
    
    if( !empty($replace) ) {
      $str = str_replace((array)$replace, ' ', $str);
    }

    $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
    $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
    $clean = strtolower(trim($clean, '-'));
    $clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);

    return $clean;
  }
  
  /**
   * 
   * timeAgo() - format a date in facebook style time format.
   * 
   * @param $date - the date to cast
   * @param $granularity - how many fractions to show
   * 
   * @return string the date formatted as a Facebook style date.
   * 
   * NOTES:
   * 
   *   http://stackoverflow.com/questions/6679010/converting-a-unix-time-stamp-to-twitter-facebook-style
   *   
   */
  
  function timeAgo($date, $granularity=2) {
    
    $date       = strtotime($date);
    $difference = time() - $date;
    
    $periods    = array('decade' => 315360000,
      'year'   => 31536000,
      'month'  => 2628000,
      'week'   => 604800, 
      'day'    => 86400,
      'hour'   => 3600,
      'minute' => 60,
      'second' => 1
    );

    $retval  = "";
    foreach($periods as $key => $value) {
      
      if($difference >= $value) {
        $time = floor($difference/$value);
        $difference %= $value;
        
        $retval .= ($retval ? ' ' : '').$time.' ';
        $retval .= (($time > 1) ? $key.'s' : $key);
        $granularity--;
      }
      
      if($granularity == '0') { 
        break; 
      }
    }
    
    return $retval;      
  }

?>