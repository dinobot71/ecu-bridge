<?php 

/**
 * 
 * web \ social \ twitter \ Twitter.php - This is our E-Z 
 * adaptor for Twitter.  For the most part we try to just
 * provide basic access to Facebook feeds etc, and require
 * only app id/secret to be provided by the caller.  This
 * is all done "on behalf of the application", not the 
 * user.
 * 
 * Eventually we may provide a wrapper here to use our OAuth
 * based adaptor (OAuthTwitter) to provide E-Z Twitter 
 * login. This adaptor is focused on public usage of Twitter
 * through a website application, so do not expect to require
 * a user to "login" to twitter to use these interfaces.
 * 
 * Where any methods here do require login, we will parameterize
 * the method with an access token to require the caller to 
 * first use OAuthTwitter to get an access token.
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

class littlemdesign_web_social_twitter_Twitter
  extends littlemdesign_util_Object {
    
  /* API atributes */
    
  private $key      = null;
  private $secret   = null;
  private $bearer   = null;
  private $baseURL  = "https://api.twitter.com";
  
  /**
   * 
   * __construct() - standard constructor
   * 
   * @param string $key - your App ID
   * @param string $secret - your App Secret
   * 
   */
  
  public function __construct($key, $secret) {
    
    parent::__construct('Twitter', 'littlemdesign_web_social_twitter', true);

    $this->unReady();
    
    $this->info("Constructing...");

    $key    = trim($key);
    $secret = trim($secret);
    
    if(empty($key)) {
      $this->error("No App key provided.");
    }
    if(empty($secret)) {
      $this->error("No App secret provided.");
    }
    
    $this->key    = $key;
    $this->secret = $secret;
    
    /* 
     * get a bearer token, formtat the credentials...
     * 
     */
    
    $cred = 
      littlemdesign_web_http_URL::percentEncode($this->key) .
      ':' .
      littlemdesign_web_http_URL::percentEncode($this->secret);
      
    $cred = base64_encode($cred);       

    /*
     * make the bearer token request...
     * 
     */
    
    $url     = "{$this->baseURL}/oauth2/token";
    $content = "grant_type=client_credentials";
    $type    = "application/x-www-form-urlencoded;charset=UTF-8";
    $headers = array(
      "Authorization" => "Basic $cred"
    );
    
    $curl = new CURL($url);
    
    if(!$curl->isReady()) {
      $this->error("can not make curl object.");
      return ;
    }
    
    $result = $curl->write(
      "POST", 
      $url, 
      $content,
      $type,
      $headers);
    
    if(($result === false)||(!isset($result->data))) {
      $this->error("problem fetching bearer token: ".$curl->getError());
      return ;
    }
    
    $data = json_decode($result->data);
    if($data === false) {
      $this->error("problem fetching bearer token, can't decode result.");
      return ;
    }
    
    if(!isset($data->access_token)) {
      $this->error("problem fetching bearer token, no access token: {$result->data}");
      return ;
    }
    
    /* ok, we can now do requests on behalf of this application */
    
    $this->bearer = $data->access_token;
    
    /* quick check to make sure its working */
    
    $this->makeReady();
        
    $result = $this->doRequest("1.1/application/rate_limit_status.json?resources=statuses");
    
    if(!isset($result->rate_limit_context)) {
      $this->unReady();
      $this->error("can't do rate limit check.");
      return ;
    }
    
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
   * simplePublicFeed() - Twitter isn't cryptic about the feed
   * like facebook is, this function basically passes data 
   * through, we don't really need to process it much.
   * 
   * @param $id - the id of the user you want the feed from.
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
      $retweets      = $item->retweets;
      
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
        "retweets"      => $retweets
      );
      
      $output[] = $post;
    }
    
    /* pass it back */
    
    return $output;
  }
  
  /**
   * 
   * publicFeed() - helper to fetch the Twitter feed for a given user.
   * 
   * @param $id - the userid of the twitter feed to fetch the most recent
   * updates from.
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
    
    if(empty($limit)||(!is_numeric($limit))) {
      $this->error("publicFeed() - limit is empty or not a number.");
      return false;
    }
    
    /* build up the request URL */
    
    $args = array(
      'q'           => "from:$id",
      'result_type' => 'recent',
      'count'       => $limit
    );
    
    $result = $this->doRequest("1.1/search/tweets.json", "GET", $args);
    
    if($result === false) {
      $this->error("publicFeed() - can't do request: ".$this->getError());
      return false;
    }
    
    if(!isset($result->statuses)) {
      $this->error("publicFeed() - can't find 'statuses'");
      return false;
    }
    
    $feed = array();
    
    foreach($result->statuses as $idx => $item) {
   
      $id            = $item->id_str;
      $author        = "";
      $authorId      = "";
      $authorLink    = "";
      $authorPicture = "";
      $text          = "";
      $picture       = "";
      $pictureLink   = "";
      $link          = "https://twitter.com/TalizeThrift/status/$id";
      $type          = "";
      $modified      = "";
      $retweets      = 0;
       
      /* fetch whatever data we have */
      
      if(isset($item->metadata->result_type)) {
        $type = $item->metadata->result_type;
      }
        
      if(isset($item->created_at)) {
        $modified = $this->timeAgo($item->created_at);
      }
      
      if(isset($item->user)) {
      
        $authorId   = $item->user->id;
        $author     = $item->user->screen_name;
        $authorLink = "https://twitter.com/$author";
       
        if(isset($item->user->profile_image_url_https)) {
          $authorPicture = $item->user->profile_image_url_https;
        }
        
      }
      
      if(isset($item->retweet_count)) {
        $retweets = $item->retweet_count;
      }
      
      if(isset($item->text)) {
        $text = $item->text;
      } 
      if(isset($item->entities->media) && (count($item->entities->media)>0)) {
        
        $pic = $item->entities->media[0];
        
        if(isset($pic->media_url)) {
          $picture     = $pic->media_url;
          $pictureLink = $pic->url; 
        }
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
        "retweets"      => $retweets
      );
     
      $feed[] = $status;
    }
    
    return $feed;
  }
  
  /**
   * 
   * doRequest() - helper function for the various API access points
   * to execute a request through to Twitter.  This is a 
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
    
    $headers = array(
      'Authorization'    => "Bearer ".$this->bearer,
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