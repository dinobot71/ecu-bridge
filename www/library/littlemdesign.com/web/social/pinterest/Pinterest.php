<?php 

/**
 * 
 * web \ social \ pinterest \ Pinterest.php - Unfortunately 
 * Pinterest doesn't have a web application  API we can use.
 * To fill the gap, we scrape user feeds from the RSS feed
 * for a given Pinterest user.  Its about all we can do, until
 * Pinterest adds a web application API.
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

/* make sure we auto-load required stuff */

autorequire('littlemdesign_util_Object');
autorequire('littlemdesign_util_Error');
autorequire('littlemdesign_web_http_CURL');
autorequire('littlemdesign_web_http_URL');

class littlemdesign_web_social_pinterest_Pinterest
  extends littlemdesign_util_Object {
    
  /* API atributes */
    
  private $userid   = null;
  private $baseURL  = "http://www.pinterest.com";
  
  /**
   * 
   * __construct() - standard constructor
   * 
   * @param string $key - your App ID
   * @param string $secret - your App Secret
   * 
   */
  
  public function __construct($userid) {
    
    parent::__construct('Pinterest', 'littlemdesign_web_social_pinterest', true);

    $this->unReady();
    
    $this->info("Constructing...");

    $userid    = trim($userid);
    
    if(empty($userid)) {
      $this->error("No user id provided.");
      return ;
    }
    
    $this->userid = $userid;
 
    if(function_exists('libxml_use_internal_errors')) {
      libxml_use_internal_errors(true);
    }
    
    if(!function_exists('simplexml_load_string')) {
      $this->error("Missing function 'simplexml_load_string'.");
      return ;
    }
    
    $this->makeReady();
   
    /* ready to use */
    
    $this->info("Ready.");
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
  
  function timeAgo($date,$granularity=2) {
    
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
    
    return ' posted '.$retval.' ago';      
  }
  
  /**
   * 
   * simplePublicFeed() - Pinterest doens't have a web application  API,
   * so we scrape from its RSS feed (for the user we want).
   * 
   * @param $userid - the user id of the user you want the feed from.
   * 
   * @param $limit - how many of the most recent feed (posts) to fetch.
   * 
   * @return array - returns a well formatted posting array that
   * can be easiliy consumed and used by a webpage.
   * 
   */
  
  function simplePublicFeed($userid="", $limit=10) {
    
    $userid = trim($userid);
    
    if(empty($userid)) {
      $userid = $this->userid;
    }
    if(empty($userid)) {
      $this->error("simplePublicFeed() - no user id");
      return false;
    }
    
    $webData = $this->request("GET", "{$this->baseURL}/$userid/feed.rss");
    
    if($webData === false) {
      $this->error("simplePublicFeed() - problem getting Pinterest feed ($userid): ".$this->getError());
      return false;
    }
    
    /* convert to XML structure */
    
    $data = simplexml_load_string($webData->data);
    
    /* walk the feed and build up feed items we can show on a webpage */
    
    $author        = $userid;
    $authorLink    = "{$this->baseURL}/$userid";
    $authorPicture = "";
    $type          = "pin";
    
    if(!isset($data->channel->item)) {
      $this->error("simplePublicFeed() - can not find feed items.");
      return false;
    }
    
    $output = array();
        
    $count = 0;
    foreach($data->channel->item as $ids => $item) {
    
      $id            = "";
      $text          = "";
      $picture       = "";
      $pictureLink   = "";
      $link          = "";
      $modified      = "";
    
      if(isset($item->link)) {
        $pictureLink = reset($item->link);
        $link        = reset($item->link);
      }
      if(isset($item->pubDate)) {
        $modified = $this->timeAgo($item->pubDate);
      }
      
      /* try to pull out the pin id */
      
      if(isset($item->guid)) {
        
        $matches = array();
        
        if(preg_match('/\/([^\/]+)\/?$/',$item->guid, $matches)) {
          $id = $matches[1];
        }
      }
      
      /* the text is burried in the description */
      
      if(isset($item->description)) {
        
        $matches = array();
        
        if(preg_match('/<\/p><p>(.+)<\/p>$/', $item->description, $matches)) {
          
          $text = $matches[1];
        }
      }
      
      /* the picture is burried in the description */
      
      if(isset($item->description)) {
        
        $matches = array();
        
        if(preg_match('/src="([^"]+)"><\/a>/', $item->description, $matches)) {
          
          $picture = $matches[1];
        }
      }
      
      /* ok, accumulate our results... */
      
      $pin = (object)array(
        "id"            => $id,
        "permlink"      => $link,
        "author"        => $author,
        "authorlink"    => $authorLink,
        "authorpicture" => $authorPicture, 
        "text"          => $text,
        "picture"       => $picture,
        "pictureLink"   => $pictureLink,
        "type"          => $type,
        "modified"      => $modified
      );
     
      $output[] = $pin;
      
      /* next */
      
      $count++;
      
      if($count >= $limit) {
        break;
      }
    }
    
    /* pass it back */
    
    return $output;
  }
  
  /**
   * 
   * request() - our low level cURL wrapper helper
   * 
   * @param $method  - GET, POST, etc.
   * @param $url     - the URL to fetch.
   * @param $args    - any URL parameters to merge in
   * @param $content - the *body* for POST or PUT.
   * 
   * @return object  - the raw result, you must pick 
   * out the parts you want.
   * 
   */
  
  function request($method, $url, $args=array(), $content='', $type='application/json') {
    
    $curl = new CURL($url);
    
    if(!$curl->isReady()) {
      $this->error("request() - can not make curl object.");
      return false;
    }
    
    $headers = array(
    );
     
    $result = $curl->write(
      $method, 
      $url, 
      $content,
      $type,
      $headers);
    
    if($result === false) {
      echo "request() - Can't do curl: ".$curl->getError()."\n";
      exit(1);
    }
    
    /* pass back the result */
    
    return $result;
  }
  
}