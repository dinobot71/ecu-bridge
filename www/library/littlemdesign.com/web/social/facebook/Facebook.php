<?php 

/**
 * 
 * web \ social \ facebook \ Facebook.php - This is our E-Z 
 * adaptor for Facebook.  For the most part we try to just
 * provide basic access to Facebook feeds etc, and require
 * only app id/secret to be provided by the caller.  
 * 
 * Eventually we may provide a wrapper here to use our OAuth
 * based adaptor (OAuthFacebook) to provide E-Z facebook 
 * login. This adaptor is focused on public usage of Facebook
 * through a website application, so do not expect to require
 * a user to "login" to facebook to use these interfaces.
 * 
 * Where any methods here do require login, we will parameterize
 * the method with an access token to require the caller to 
 * first use OAuthFacebook to get an access token.
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

class littlemdesign_web_social_facebook_Facebook
  extends littlemdesign_util_Object {
    
  /* API atributes */
    
  private $key      = null;
  private $secret   = null;
  private $graphURL = "https://graph.facebook.com";
  private $fbURL    = "https://www.facebook.com";
  
  /**
   * 
   * __construct() - standard constructor
   * 
   * @param string $key - your App ID
   * @param string $secret - your App Secret
   * 
   */
  
  public function __construct($key, $secret) {
    
    parent::__construct('Facebook', 'littlemdesign_web_social_facebook', true);

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
    
    $url    = $this->graphURL."/{$this->key}?access_token={$this->key}|{$this->secret}";
    
    $result = $this->request("GET", $url);
    
    if(!isset($result->data)) {
      $this->error("Could not get account info.");
      return ;
    }
    
    $info = json_decode($result->data);
    
    if(!isset($info->id)&&!isset($info->name)&&!isset($info->category)) {
      $this->error("garbleed account info.");
      return ;
    }
    
    /* we connected ok and got reasonably looking account info. */ 
    
    $this->makeReady();
    
    $this->info("Ready.");
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
    
    $result = $curl->write(
      $method, 
      $url, 
      $content,
      $type,
      array());
    
    if($result === false) {
      echo "request() - Can't do curl: ".$curl->getError()."\n";
      exit(1);
    }
    
    /* pass back the result */
    
    return $result;
  }
  
  /**
   * 
   * graphRequest() - helper function for the to send a request through
   * the graph API.
   * 
   * @param $uri     - the service endpoint, for example "<id>/feed"
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
  
  function graphRequest($uri, $method="GET", $args=array(), $content='') {
    
    if(!$this->isReady()) {
      $this->error("graphRequest() - object not ready.");
      return false;
    }
    
    /* build up the full URL */
    
    $uri = trim($uri, "/");
    
    $url = littlemdesign_web_http_URL::create($this->graphURL."/".$uri);
    
    /* 
     * if they actually provide 'null' for arguments, then
     * we want NO arguments at all, not even api_key.
     * 
     */
        
    if($args !== null) {
      $url->mergeQuery(array('access_token' => "{$this->key}|{$this->secret}"));
      $url->mergeQuery($args);
    }
    
    $url->setEncoding(littlemdesign_web_http_URL::RFC_3986);
    
    $urlString = $url->toString();
    
    $this->info("graphRequest() - doing ($method): $urlString");
    
    /* actually make the request */
    
    $result = $this->request($method, $urlString);
    
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
   * simplePublicFeed() - facebook post listings are...a little 
   * cryptic, so send the results of publicFeed() through this
   * function to get a list of postings that is formatted
   * much more in a way that can be easily used by any web page.
   * 
   * @param $id - the id of the page you want the feed from.
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
      $author        = "";
      $authorLink    = "";
      $authorPicture = "";
      $text          = "";
      $picture       = "";
      $link          = "";
      $type          = "";
      $modified      = "";
      $likeCount     = 0;
      
      /* fetch whatever data we have */
      
      if(isset($item->updated_time)) {
        $modified = $this->timeAgo($item->updated_time);
      }
      if(isset($item->from->name)) {
        $author = $item->from->name;
      }
      
      if(isset($item->from->id)) {
        
        $authorId      = $item->from->id;
        $authorLink    = $this->userlinkOfId($authorId);
        
        $authorPicture = $this->pictureOfId($authorId);
      }
      
      if(isset($item->type)) {
        $type = $item->type;
      }
      
      if(isset($item->story)) {
        $text = $item->story;
      } else if(isset($item->message)) {
        $text = $item->message;
      }
      
      if(strtolower($type) == "link") {
        if(isset($item->message)) {
          $text = $item->message." (link)";
        }
      } 
      
      /* filter out any text we don't like */
      
      $text = preg_replace('/\s+on\stheir\sown\sphoto\.$/', '', $text);
      
      if(preg_match('/^"[^"]+"$/', $text)) {
        $text = trim($text, '"');
      }
      
      /* back to harvesting... */
      
      if(isset($item->link)) {
        $link = $item->link;
      }
      
      if(isset($item->picture)) {
        $picture = $item->picture;
        $picture = preg_replace('/_s\.png$/', '_n.png', $picture);
        $picture = preg_replace('/_s\.jpg$/', '_n.jpg', $picture);
        $picture = preg_replace('/_s\.gif$/', '_n.gif', $picture);
      }
      
      /* get the # of likes */
      
      $likeCount = $this->numLikes($id);
      
      /* format a posting object */
      
      $post = (object)array(
        "id"            => $id,
        "permlink"      => $this->permlinkOfPost($id),
        "author"        => $author,
        "authorlink"    => $authorLink,
        "authorpicture" => $authorPicture, 
        "text"          => $text,
        "picture"       => $picture,
        "pictureLink"   => $link,
        "type"          => $type,
        "modified"      => $modified,
        "likes"         => $likeCount
      );
      
      $output[] = $post;
    }
    
    /* pass it back */
    
    return $output;
  }
  
  /**
   * 
   * publicFeed() - fetch some of the most recent postings from a public
   * page.  You'll need to provide the id of the page.
   * 
   * @param $id - the id of the page you want the feed from.
   * 
   * @param $limit - how many of the most recent feed (posts) to fetch.
   * 
   * @return object - return the structured object that Facebook 
   * gives back,  if there was a problem, return exactly false.
   * 
   * NOTES:
   * 
   *   http://johndoesdesign.com/blog/2011/php/adding-a-facebook-news-status-feed-to-a-website/
   *   http://stackoverflow.com/questions/10208754/displaying-a-facebook-newsfeed-timeline-on-a-website
   *   https://developers.facebook.com/docs/facebook-login/access-tokens/#apptokens
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
      $this->error("publicFeed() - no page id provided.");
      return false;
    }
    
    if(empty($limit)||(!is_numeric($limit))) {
      $this->error("publicFeed() - limit is empty or not a number.");
      return false;
    }
    
    /* build up the request URL */
    
    $result = $this->graphRequest("$id/feed", "GET", array('limit' => $limit));
    
    if($result === false) {
      $this->error("publicFeed() - can't do graph request: ".$this->getError());
      return false;
    }
    
    if(!isset($result->data)) {
      $this->error("publicFeed() - can't find data.");
      return false;
    }
    
    /* pass it back */
    
    return $result->data;
  }
  
  /**
   * 
   * pictureOfId() - given an id of some object (like the 'from' of a
   * posting), convert that id into its picture/icon URL, and return
   * the URL.
   * 
   * @param $id
   * 
   * @return string - the URL of the picture/icon for the given object
   * id.  If there is an error, return exactly false.
   * 
   */
  
  function pictureOfId($id) {
     
    if(!$this->isReady()) {
      $this->error("pictureOfId() - object not ready.");
      return false;
    }
    
    $id    = trim($id);
    
    if(empty($id)) {
      $this->error("pictureOfId() - no page id provided.");
      return false;
    }
    
    /* build up the request URL */
    
    $result = $this->graphRequest("$id/picture", "GET", array("redirect" => "false"));
    
    if($result === false) {
      $this->error("publicFeed() - can't do graph request: ".$this->getError());
      return false;
    }
    
    if(!isset($result->data)) {
      $this->error("publicFeed() - can't find data.");
      return false;
    }
    if(!isset($result->data->url)) {
      $this->error("publicFeed() - can't find url.");
      return false;
    }
    
    /* pass it back */
    
    return $result->data->url;
    
  }  
  
  /**
   * 
   * permlinkOfPost() - for a given post id, convert to the 
   * the link to follow to see the posting on facebook, i.e.
   * the permlink.
   * 
   * @param $id - the id of the posting.
   * 
   * @return string - the permlink url of the given posting.  
   * If there is a problem, return exactly false.
   * 
   */
  
  function permlinkOfPost($id, $pageid='') {
       
    if(!$this->isReady()) {
      $this->error("permlinkOfPost() - object not ready.");
      return false;
    }
    
    $id = trim($id);
    
    if(empty($id)) {
      $this->error("permlinkOfPost() - no page id provided.");
      return false;
    }
    
    /*
     * If the id is still in <userid>_<postid> format, 
     * extract just the postid...
     * 
     */
    
    $postid  = $id;
    $matches = array();
    if(preg_match('/^([^_]+)_([^_]+)$/',$id, $matches)) {
      $postid = $matches[2];
      $pageid = $matches[1];
    }
    
    /* construct the permlink */
    
    $url = "{$this->fbURL}/$pageid/posts/$postid";
    
    /* all done */
    
    return $url;
  }
  
  /**
   * 
   * userlinkOfId() - for a given user id, reutrn the facebook
   * permlink for that user's home page.
   * 
   * @param $id - the user id
   * 
   * @return string the url of the user's home page.  Return 
   * exactly false if there is a problem.
   * 
   */
  
  function userlinkOfId($id) {
    
    if(!$this->isReady()) {
      $this->error("permlinkOfPost() - object not ready.");
      return false;
    }
    
    $id = trim($id);
    
    if(empty($id)) {
      $this->error("permlinkOfPost() - no page id provided.");
      return false;
    }
    
    /*
     * If the id is still in <userid>_<postid> format, 
     * extract just the postid...
     * 
     */
    
    $matches = array();
    if(preg_match('/^([^_]+)_([^_]+)$/',$id, $matches)) {
      $id = $matches[1];
    }
    
    /* construct the permlink */
    
    $url = "{$this->fbURL}/$id";
    
    /* all done */
    
    return $url;
  }
  
  /**
   * 
   * numLikes() - for a given posting, fetch the number of likes 
   * it has.
   * 
   * @param $postid - the post id you want the count for.
   * 
   * @return integer return the number of likes for this posting. 
   * On error, return exactly false.
   * 
   */
  
  function numLikes($postid) {
    
    if(!$this->isReady()) {
      $this->error("numLikes() - object not ready.");
      return false;
    }
    
    $postid = trim($postid);
    
    if(empty($postid)) {
      $this->error("numLikes() - no post id provided.");
      return false;
    }
    
    /* build up the request URL */
    
    $args   = array(
      "summary" => 1,
      "limit"   => 0
    );
    $result = $this->graphRequest("$postid/likes", "GET", $args);
    
    if($result === false) {
      $this->error("numLikes() - can't do graph request: ".$this->getError());
      return false;
    }
    
    if(!isset($result->summary)) {
      $this->error("numLikes() - can't find data.");
      return false;
    }
    if(!isset($result->summary->total_count)) {
      $this->error("numLikes() - can't find url.");
      return false;
    }
    
    /* pass back the count */
    
    return $result->summary->total_count;
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
}

?>