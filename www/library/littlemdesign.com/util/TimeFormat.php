<?php 

/**
 * 
 * util \ TimeFormat - provide helper methods for formatting
 * and working with time/dates.
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
 * TimeFormat - provide helper methods for working 
 * with time and date.
 * 
 * @api
 * 
 */

class littlemdesign_util_TimeFormat {

  
  public function __construct() {
  
  }
  
  /*
   * 
   * ago() - format a date in facebook style time format.
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

  public static function ago($date,$granularity=2) {

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

    if(empty($retval)) {
        return "just now";
    }

    return $retval.' ago';
  }
}

?>