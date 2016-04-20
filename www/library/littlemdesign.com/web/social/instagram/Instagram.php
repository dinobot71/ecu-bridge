<?php 

/**
 * 
 * web \ social \ instagram \ Instagram.php - This is our E-Z 
 * adaptor for Instagram.  Unlike Facebook and Twitter, 
 * Instgram doesn't have a super awesome API...it has an API,
 * but its geared for mobile devices, not web sites, so you 
 * are forced to be authenticated as a specific user, in order
 * to do anything at all useful.
 * 
 * Fortunatley (for now), Instagram doens't expire its access
 * tokens, so we can a one time access token generation and
 * then just use it where we don't have a user context, such
 * as anonymous users browsing a web site.
 * 
 * The upshot is that we must construct Instagram with an
 * access token and client id, not the usual key/secret.
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


function ig_feed_sort($a, $b) {
  
  $delta = $b->stamp - $a->stamp;
  
  return $delta;
}

class littlemdesign_web_social_instagram_Instagram
  extends littlemdesign_util_Object {
    
  /* API atributes */
    
  private $key      = null;
  private $secret   = null;
  private $token    = null;
  private $baseURL  = "https://api.instagram.com";
  
  /**
   * 
   * __construct() - standard constructor
   * 
   * @param string $key - your App ID
   * @param string $secret - your App Secret
   * 
   */
  
  public function __construct($key, $secret, $token) {
    
    parent::__construct('Instagram', 'littlemdesign_web_social_instagram', true);

    $this->unReady();
    
    $this->info("Constructing...");

    $key    = trim($key);
    $secret = trim($secret);
    $token  = trim($token);
    
    if(empty($key)) {
      $this->error("No App key provided.");
    }
    if(empty($secret)) {
      $this->error("No App secret provided.");
    }
    if(empty($token)) {
      $this->error("No access token provided.");
    }
    
    $this->key    = $key;
    $this->secret = $secret;
    $this->token  = $token;
    
    /* try it out... */
    
    $this->makeReady();
        
    $result = $this->doRequest("v1/users/self");
    if(($result === false)||!isset($result->data->username)) {
      $this->unReady();
      $this->error("can't do self user check.");
      return ;
    }
    
    $this->info("On behalf of: {$result->data->username}, {$result->data->full_name}");
    
    /* we connected ok and we can do queries */ 
     
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
   * simplePublicFeed() - Instagram feed fetching, we just
   * wrap publicFeed().
   * 
   * @param $id - the user name (we will look up the id) of the user 
   * you want the feed from.
   * 
   * @param $limit - how many of the most recent feed (posts) to fetch.
   * 
   * @return array - returns a well formatted posting array that
   * can be easiliy consumed and used by a webpage.
   * 
   */
  
  function simplePublicFeed($id, $limit=10) {
    
    /* fetch the raw feed */
    
    $data = $this->publicFeed($id,$limit);
    
    if($data === false) {
      $this->error("simplePublicFeed() - can't fetch publicFeed(): ".$this->getError());
      return false;
    }
    
    /*
     * walk through the posting list, and for each one 
     * figure out more usable data...
     * 
     */
    
    $output = array();
    
    foreach($data as $idx => $item) {
      
      $id            = $item->id;
      $author        = $item->author;
      $authorLink    = $item->authorlink;
      $authorPicture = $item->authorpicture;
      $text          = $item->text;
      $picture       = $item->picture;
      $pictureLink   = $item->pictureLink;
      $link          = $item->permlink;
      $type          = $item->type;
      $modified      = $item->modified;
      $likes         = $item->likes;
    
      $post = (object)array(
        "id"            => $id,
        "permlink"      => $link,
        "author"        => $author,
        "authorlink"    => $authorLink,
        "authorpicture" => $authorPicture, 
        "text"          => $text,
        "picture"       => $picture,
        "pictureLink"   => $pictureLink,
        "type"          => $type,
        "modified"      => $modified,
        "likes"         => $likes
      );
      
      $output[] = $post;
    }
    
    /* pass it back */
    
    return $output;
  }
  
  /**
   * 
   * userIdOfName() - convert a user name to an internal
   * instagram user id.
   * 
   * @param string $name - the user name to convert to a
   * numerical id.
   * 
   * @return string the internal instagram user id.  Return
   * exactly false if  there is a problem. Empty string means
   * no match.
   * 
   */
  
  function userIdOfName($name) {
    
    if(!$this->isReady()) {
      $this->error("userIdOfName() - object not ready.");
      return false;
    }
    
    $name = trim($name);
    
    if(empty($name)) {
      $this->error("userIdOfName() - no user id provided.");
      return false;
    }
    
    $userid = "";
    
    $args = array(
      'q'           => "$name",
      'count'       => 1
    );
    
    $result = $this->doRequest("v1/users/search", "GET", $args);
    
    if($result === false) {
      $this->error("userIdOfName() - can't do request: ".$this->getError());
      return false;
    }
    
    if(!isset($result->data)) {
      $this->error("userIdOfName() - can't find 'data'");
      return false;
    }
    
    if(count($result->data) == 0) {
      
      /* no match */
      
      return "";
    }
    
    $user   = $result->data[0];
    $userid = $user->id;
    
    /* pass it back */
    
    return $userid;
    
  }
  
  /**
   * 
   * publicFeed() - helper to fetch the Instagram feed for a given user.
   * 
   * @param $id - the user name (we will look up the id) of the instagram 
   * feed to fetch the most recent pictures from.
   * 
   * @param $limit - how far back to go.
   * 
   * @return array a list of updates, or exactly false if there is a 
   * problem.
   * 
   */
   
  function publicFeed($id, $limit=10) {
    
    if(!$this->isReady()) {
      $this->error("publicFeed() - object not ready.");
      return false;
    }
    
    $id    = trim($id);
    $limit = trim($limit);
    
    if(empty($id)) {
      $this->error("publicFeed() - no user id provided.");
      return false;
    }
    
    if(!is_numeric($id)) {
      
      /* try to auto convert */
      
      $tmp = $this->userIdOfName($id);
      if($tmp === false) {
        $this->error("publicFeed() - can not convert userid ($id): ".$this->getError());
        return false;
      }
      
      if(empty($tmp)) {
        $this->error("publicFeed() - no such user ($id).");
        return false;
      }
      
      $id = $tmp;
    }
     
    if(empty($limit)||(!is_numeric($limit))) {
      $this->error("publicFeed() - limit is empty or not a number.");
      return false;
    }
    
    /* build up the request URL */
    
    $args = array(
      'count'       => $limit
    );
    
    $result = $this->doRequest("v1/users/$id/media/recent", "GET", $args);
    
    if($result === false) {
      $this->error("publicFeed() - can't do request: ".$this->getError());
      return false;
    }
    
    if(!isset($result->data)) {
      $this->error("publicFeed() - can't find 'data'");
      return false;
    }
    
    $feed = array();
    
    foreach($result->data as $idx => $item) {
   
      $id            = $item->id;
      $author        = "";
      $authorId      = "";
      $authorLink    = "";
      $authorPicture = "";
      $text          = "";
      $picture       = "";
      $pictureLink   = "";
      $link          = "";
      $type          = "";
      $modified      = "";
      $stamp         = 0;
      $numLikes      = 0;
       
      /* fetch whatever data we have */
      
      if(isset($item->user)) {
        $authorId      = $item->user->id;
        $author        = $item->user->username;
        $authorLink    = "http://instagram.com/$author#";
        $authorPicture = $item->user->profile_picture;
      }
      
      if(isset($item->link)) {
        $link = $item->link;
      }
      
      if(isset($item->likes)) {
        $numLikes = $item->likes->count;
      }
      

      if(isset($item->created_time)) {
        $stamp    = (int)$item->created_time;
        $theTime  = date('Y-m-d H:i:s', $stamp);
        $modified = $this->timeAgo($theTime);
      }
      
      if(isset($item->type)) {
        $type = $item->type;
      }
      
      if(isset($item->caption->text)) {
        $text = $item->caption->text;
      }
      
      if(isset($item->images->standard_resolution)) {
        $picture     = $item->images->standard_resolution->url;
        $pictureLink = $link;
      }
      
      /* format a posting object */
      
      $status = (object)array(
        "id"            => $id,
        "permlink"      => $link,
        "author"        => $author,
        "authorlink"    => $authorLink,
        "authorpicture" => $authorPicture, 
        "text"          => $text,
        "picture"       => $picture,
        "pictureLink"   => $pictureLink,
        "type"          => $type,
        "modified"      => $modified,
        "likes"         => $numLikes,
        "stamp"         => $stamp
      );
     
      $feed[] = $status;
    }
    
    /* 
     * before we return the matches, sort them base don the 
     * time stamp...instagram doens't give us the matches
     * fully sorted...just kind of sorted.
     * 
     */
    
    usort($feed, 'ig_feed_sort');
    
    return $feed;
  }
  
  /**
   * 
   * doRequest() - helper function for the various API access points
   * to execute a request through to Instagram.  This is a 
   * wrapper for request() which does a little more processing so
   * we don't have to do that stuff every time we make a request.
   * 
   * @param $uri     - the service endpoint, for example "account/settings"
   * 
   * @param $method  - the method, for example "GET"
   * 
   * @param $args    - additioanl URL query parameters, if you don't
   * want any arguments all, not even api_key, then provide null.
   * 
   * @param $content - content, for post methods (the body)
   * 
   * @return mixed, the decoded JSON result, as a PHP data value.
   * 
   */
  
  function doRequest($uri, $method="GET", $args=array(), $content='') {
    
    if(!$this->isReady()) {
      $this->error("doRequest() - object not ready.");
      return false;
    }
    
    /* build up the full URL */
    
    $uri = trim($uri, "/");
    
    $url = littlemdesign_web_http_URL::create("{$this->baseURL}/$uri");
    
    if($args !== null) {
      $url->mergeQuery(array(
        'client_id'    => $this->key,
        'access_token' => $this->token
      ));
      $url->mergeQuery($args);
    }
    
    $url->setEncoding(littlemdesign_web_http_URL::RFC_3986);
    
    $urlString = $url->toString();
    
    $this->info("doRequest() - doing ($method): $urlString");
    
    /* actually make the request */
    
    $result = $this->request($method, $urlString, $args, $content);
    
    /* havest the result */
    
    if(!isset($result->data)) {
      $this->error("Could not get account info.");
      return ;
    }
    
    /* pass it back */
    
    $info = json_decode($result->data);
    
    return $info;
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
    
    $headers = array();
     
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