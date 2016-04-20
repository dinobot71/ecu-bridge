<?php
 
/**
 * 
 * web \ email \ constantcontact - this package provides 
 * features for working with email/marketing compaigns
 * that use ConstantConatact.  Packages will later be added
 * for other providers such as MailChimp, Infusionsoft etc.
 * 
 * @package littlemdesign.com
 * 
 * @author Little m Design (Michael Garvin)
 * @copyright Copyright (c) 2013-, Littl m Design
 *   
 * The Constant Contact developer pages:
 * 
 *   http://developer.constantcontact.com/get-started.html
 *   http://developer.constantcontact.com/docs/developer-guides/authentication.html
 *
 * to actually try out the REST API:
 *
 *   https://constantcontact.mashery.com/io-docs
 *  
 * API reference documentation is here:
 * 
 *   http://developer.constantcontact.com/docs/developer-guides/api-documentation-index.html
 *   
 * Constant Contact uses OAuth 2.0 and when you get an access
 * token its good for 10 years, so teh API is very simple
 * to use, you just need the API key and the access token
 * from logging in as the owner of the constant contact 
 * account you are intending to work with.  
 * 
 * Not fancy OAuth protocol is neeeded, teh request headers,
 * just have to include the ip address and the access token.
 * 
 * Usage:
 * 
 *   Account:
 *     
 *     account() - get general accoutn info
 *   
 *   Users:
 *     
 *     contactExists() - check for/return a current user
 *     
 *     addUserToList() - create a new user, or add a user to a list
 *     
 *   Lists:
 *     
 *     getLists() - get the mailing lists for this account
 *     
 *     listExists() - check for/return a list, by name/id (can use either)
 *     
 *     isListActive() - check if a given list (name/id) is active
 *     
 *     listMembers() - for this list (name/id) get all the name/emails,
 *     you can use contactExists() to get details on a user.
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

class littlemdesign_web_email_constantcontact_ConstantContact
  extends littlemdesign_util_Object {
    
  /* API atributes */
    
  private $key     = null;
  private $token   = null;
  private $baseURL = "https://api.constantcontact.com/";
  
  /* cached contact lists for this account */
  
  private $lists   = null;
  
  /**
   * 
   * __construct() - standard constructor
   * 
   * @param string $key - your Constant Contact API Key
   * @param string $token - your access token, to get one login
   * to your actual constant contact account, via the authentication
   * registration link in the developer portal (see the URLs above).
   * 
   */
  
  public function __construct($key, $token) {
    
    parent::__construct('ConstantContact', 'littlemdesign_web_email', true);

    $this->unReady();
    
    $this->info("Constructing...");

    $key    = trim($key);
    $token  = trim($token);
    
    if(empty($key)) {
      $this->error("No API key provided.");
    }
    if(empty($token)) {
      $this->error("No access token provided.");
    }
    
    $this->key    = $key;
    $this->token  = $token;
    
    /* 
     * try to actually connect with OAuth to see if
     * we have working credentials...
     * 
     */
    
    $result = 
      $this->request("GET", $this->baseURL."v2/account/info?api_key=".$key);
    
    if(!isset($result->data)) {
      $this->error("Could not get account info.");
      return ;
    }
    
    $info = json_decode($result->data);
    
    if(!isset($info->website)&&!isset($info->email)&&!isset($info->organization_name)) {
      $this->error("garbleed account info.");
      return ;
    }
    
    /* we connected ok and got reasonably looking account info. */ 
    
    $this->makeReady();
    
    $this->info("Ready.");
  }
  
  /**
   * 
   * ipAddress() - helper method to get local IP address.
   * 
   * @return string - IP addres so this server.
   * 
   */
  
  function ipAddress() {
    
    if(isset($_SERVER["SERVER_ADDR"])) {
      return $_SERVER["SERVER_ADDR"];
    }
    
    $host = gethostname();
    $ip   = gethostbyname($host); 
    
    return $ip;
  }
  
  /**
   * 
   * request() - our low level cURL wrapper helper
   * 
   * @param $method - GET, POST, etc.
   * @param $url - the URL to fetch.
   * @param $args - any URL parameters to merge in
   * @param $content - the *body* for POST or PUT.
   * 
   * @return object - the raw result, you must pick 
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
      'Authorization'    => "Bearer ".$this->token,
      'X-Originating-Ip' => $this->ipAddress()
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
  
  /**
   * 
   * doRequest() - helper function for the various API access points
   * to execute a request through to Constant Contact.  This is a 
   * wrapper for request() which does a little more processing so
   * we don't have to do that stuff every time we make a request.
   * 
   * @param $uri     - the service endpoint, for example "account/info"
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
    
    $url = littlemdesign_web_http_URL::create($this->baseURL.$uri);
    
    /* 
     * if they actually provide 'null' for arguments, then
     * we want NO arguments at all, not even api_key.
     * 
     */
    
    if($args !== null) {
      $url->mergeQuery(array('api_key' => $this->key));
      $url->mergeQuery($args);
    }
    
    $url->setEncoding(littlemdesign_web_http_URL::RFC_3986);
    
    $urlString = $url->toString();
    
    $this->info("doRequest() - doing ($method): $urlString");
    
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
   * acount() fetch the account details, including 
   * the associated account emails.
   * 
   * @return array - an array of accoutn details.
   * 
   */
  
  function account() {
    
    if(!$this->isReady()) {
      $this->error("account() - object not ready.");
      return false;
    }
    
    $info = $this->doRequest("v2/account/info");
    
    if($info === false) {
      $this->error("account() - can't get account info.");
      return false;
    }
    
    
    $emails = $this->doRequest("v2/account/verifiedemailaddresses");
    if($info === false) {
      $this->error("account() - can't get account emails.");
      return false;
    }
    
    $result = (object)array(
      "account" => $info,
      "emails"  => $emails
    );
     
    return $result;
  }
  
  /**
   * 
   * contactExists() - check to see if the given email
   * address is already known to our constant contact 
   * account (on one or more lists).
   * 
   * @param $email - the email address to test
   * 
   * @return mixed - if there is a problem return exactly
   * false. Otherwise return the info about this user.
   * 
   */
  
  function contactExists($email) {
    
    if(!$this->isReady()) {
      $this->error("contactExists() - object not ready.");
      return false;
    }
    
    $info = $this->doRequest("v2/contacts", "GET", array('email' => $email));
    
    if($info === false) {
      $this->error("contactExists() - can't make REST call.");
      return false;
    }

    if(isset($info->results)) {
      
      $arr = $info->results;
      
      if(count($arr) == 0) {
        $this->error("contactExists() - '$email' doesn't exist yet.");
        return false;
      }
      
      if(count($arr) > 1) {
        $this->error("contactExists() - '$email' has too many matches.");
        return false;
      }
      
      
    }
    
    /* pass back the result */
    
    return $info->results[0];
  }  
  
  /**
   * 
   * getLists() - fetch the list of contacts lists in 
   * this account.
   * 
   * @return mixed - the list of distribution lists.
   * 
   */
  
  function getLists() {
    
    if(!$this->isReady()) {
      $this->error("getLists() - object not ready.");
      return false;
    }
    
    if($this->lists !== null) {
      return $this->lists;
    }
    
    $info = $this->doRequest("v2/lists");
  
    if($info === false) {
      $this->error("getLists() - can't make REST call.");
      return false;
    }

    $this->lists = $info;
    
    /* pass back result */
    
    return $this->lists;
  }
  
  /**
   * 
   * listExists() - given the internal id or human readable
   * name of a distribution list, check if it exists, if it 
   * does, return the list details.
   * 
   * @param string - the internal id or human readable 
   * name of one of the distribution lists.
   * 
   * @return mixed - if the list doens't exist (or error)
   * return exactly false.  If it does exists return an 
   * object with details of the list.
   * 
   */
  
  function listExists($idOrName) {
          
    if(!$this->isReady()) {
      $this->error("listExists() - object not ready.");
      return false;
    }
    
    $idOrName = trim($idOrName);
    
    if(empty($idOrName)) {
      $this->error("listExists() - no id or name provided.");
      return false;
    }
    
    /* make sure we have the lists */
    
    $this->getLists();
    
    $status = false;
    
    foreach($this->lists as $id => $obj) {
      
      if($obj->id == $idOrName) {
        return $obj;
      }
      
      if($obj->name == $idOrName) {
        return $obj;
      }
    }
    
    return false;
  }
  
  /**
   * 
   * isListActive() - given the id or name of a distribution 
   * list, check to see if it is actually active or not.
   * 
   * @param string - the internal id or human readable 
   * name of one of the distribution lists.
   * 
   * @return boolean - return true/false if active or
   * not.
   * 
   */
  
  function isListActive($idOrName) {
    
    if(!$this->isReady()) {
      $this->error("isListActive() - object not ready.");
      return false;
    }
    
    $idOrName = trim($idOrName);
    
    if(empty($idOrName)) {
      $this->error("isListActive() - no id or name provided.");
      return false;
    }
    
    /* make sure we have the lists */
    
    $this->getLists();
    
    $status = false;
    
    foreach($this->lists as $id => $obj) {
      
      if($obj->id == $idOrName) {
        if(strtolower($obj->status) == "active") {
          $status = true;
        }
      }
      
      if($obj->name == $idOrName) {
        if(strtolower($obj->status) == "active") {
          $status = true;
        }
      }
    }
    
    return $status;
  }
  
  /**
   * 
   * idForList() - given either the id or the name of a
   * constant contact list, return the id so we have a way
   * to canonicalize list references.
   * 
   * @param string - the internal id or human readable 
   * name of one of the distribution lists.
   * 
   * @return mixed - if no such list, return -1, if there 
   * is an error, return exactly false.  Otherwise return 
   * the list id.
   * 
   */
  
  function idForList($idOrName) {
    
    if(!$this->isReady()) {
      $this->error("idForList() - object not ready.");
      return false;
    }
    
    $idOrName = trim($idOrName);
    
    if(empty($idOrName)) {
      $this->error("idForList() - no id or name provided.");
      return false;
    }
    
    /* make sure we have the lists */
    
    $this->getLists();
    
    foreach($this->lists as $id => $obj) {
      
      if($obj->id == $idOrName) {
        return $obj->id;
      }
      
      if($obj->name == $idOrName) {
        return $obj->id;
      }
    }
    
    /* if we get thsi far there is no such list. */
    
    return -1;
  }
  
  /**
   * 
   * addUserToList() - for a given user (by email address), 
   * we create a new user (if they don't yet exist), and add
   * them to the given list (implicitly an update to that 
   * user).  Otherwise we update the existing user.
   * 
   * Because constant contact updates all user fields when you 
   * do an update, we have to do a "complete" update, we can't 
   * just set the fields we want to twitch.  This means that
   * existing users must be fetched, modified, and then 
   * passed back to constant contact...so don't mess this up,
   * becuase it will destroy data in constant contact!
   * 
   * Note that new users must have at minimum an email address
   * and belong to at least one list.
   * 
   * @param $email - the user (possibly new) to add
   * 
   * @param $idOrName - the internal id or human readable name of
   * the distribution list to add them to.
   * 
   * @param $firstName - the optional first name.
   * 
   * @param $lastName - the optional last name.
   * 
   * @param $action - allows you to specify a user doing the 
   * action via this API or the account owner doing it via
   * this API.
   * 
   * @return mixed - if there is a problem, return exactly 
   * false. Otherwise return the new/modified user object.
   * 
   */
  
  function addUserToList($email, $idOrName, $firstName="", $lastName="", $action="ACTION_BY_VISITOR") {
    
    $this->info("addUserToList($email,$idOrName,$firstName,$lastName) starts...");
     
    /* check the parameters */
    
    if(!$this->isReady()) {
      $this->error("addUserToList() - object not ready.");
      return false;
    }
    
    $email = trim($email);
    
    if(empty($email)) {
      $this->error("addUserToList() - no email provided.");
      return false;
    }
    
    $idOrName = trim($idOrName);
    
    if(empty($idOrName)) {
      $this->error("addUserToList() - no list name/id provided.");
      return false;
    }
    
    /* does this list exist? */
    
    $mailList = $this->listExists($idOrName);
    if($mailList === false) {
      $this->error("addUserToList() - no such mailing list: $idOrName");
      return false;
    }
    
    /* does this user exist? */
    
    $contact = $this->contactExists($email);
    
    if($contact === false) {
    
      /* 
       * if the user doesn't exist we create a new one,
       * and fill in first and last name if we have it.
       * 
       */
    
      $method = "POST";
      
      $this->info("addUserToList() adding new contact...");
      
      $lists   = array();
      $lists[] = (object)array(
        'id'     => $mailList->id,
        'status' => $mailList->status
      );
      
      $emails   = array();
      $emails[] = (object)array(
        'email_address' => $email
      );  
      
      $contact = (object)array(
        'lists'           => $lists,
        'email_addresses' => $emails,
        'first_name'      => $firstName,
        'last_name'       => $lastName
      );
        
    } else {
      
      /*
       * if they are already on that list, we're done.
       * 
       */
      
      $method = "PUT";
      
      if(isset($contact->lists)) {
        foreach($contact->lists as $idx => $obj) {
          if($obj->id == $mailList->id) {
            $this->info("addUserToList() already in list.");
            return $contact;
          }
        }
      }
      
      /*
       * if the user already exists, then we only want 
       * to append this list to their lists.
       * 
       */
      
      $this->info("addUserToList() modifying contact...");
     
      if(isset($contact->lists)) {
        $contact->lists[] = (object)array(
          'id'     => $mailList->id,
          'status' => $mailList->status
        );
      }
      
    }
    
    /* convert the contact object back into JSON */
    
    $content = json_encode($contact);
    
    /* submit it back Constant contact */
    
    $contactId = "";
    if(isset($contact->id)) {
      $contactId = "/".$contact->id;
    }
    
    $uri = "v2/contacts$contactId";
    
    $url = littlemdesign_web_http_URL::create($this->baseURL.$uri);
    
    $url->mergeQuery(array(
      'api_key'   => $this->key,
      'action_by' => $action
    ));

    $url->setEncoding(littlemdesign_web_http_URL::RFC_3986);
    
    $urlString = $url->toString();
    
    $result = $this->request(
      $method, 
      $urlString, 
      array(), 
      $content);
      
    if($result === false) {
      $this->error("addUserToList() - problem updating user: ".$this->getError());
      return false;
    }
    
    /* check the HTTP status code to make sure it worked */
    
   
    $status = $result->meta['http_code'];
    if(($status != 200)&&($status != 201)) {
      
      /* there was a problem! */
      
      $this->error("addUserToList() - problem updating user ($status): ".$result->data);
      return false;  
    }
      
    /* 
     * if we get this far everything went ok, pass back the 
     * new/modified user.
     * 
     */
    
    $user = $this->contactExists($email);
    if($user === false) {
      $this->error("addUserToList() - can not confirm user: $email");
      return false;
    }
    
    $this->info("addUserToList() done.");
    
    return $user;
  }
  
  /**
   * 
   * listMembers() - for a given list (you can name it by name or
   * its internal id), fetch the member summary, that is each contact
   * as:
   *  
   *   [<last name>, <first name>] <email>
   *   
   * To get more details on a given user, you can use the conactExists()
   * method to get the full details for a given email (user).
   * 
   * @param $idOrName - the internal id or human readable name of
   * the distribution list to add them to.
   * 
   * @return mixed - return exactly false if there is an error, 
   * otherwise the array of users.
   * 
   */
  
  function listMembers($idOrName) {

    if(!$this->isReady()) {
      $this->error("listMembers() - object not ready.");
      return false;
    }
    
    $results  = array();
    $mailList = $this->listExists($idOrName);

    if($mailList === false) {
      $this->error("listMembers() - can not find mailing list: $idOrName");
      return false;
    }
    
    $uri = "v2/lists/".$mailList->id."/contacts";
    
    $page = 1;
    
    while(true) {
      
      $args = array('limit' => 100);
      if($page > 1) {
        $args = array();
      }
      
      $info = $this->doRequest($uri, "GET", $args);
  
      //echo "X: info: ".print_r($info,true)."\n";
      
      if($info === false) {
        $this->error("getLists() - can't make REST call.");
        return false;
      }

      if(!isset($info->meta->pagination)) {
        $this->error("getLists() - garbled response, no pagination.");
        return false;
      }
      if(!isset($info->results)) {
        $this->error("getLists() - garbled response, no results.");
        return false;
      }
      
      $data = $info->results;
      
      foreach($data as $idx => $obj) {
           
        $e = $obj->email_addresses[0]->email_address;
        $f = $obj->first_name;
        $l = $obj->last_name;
        
        /* accumulate */
        
        $contact   = "[$l, $f] $e";
        $results[] = $contact;        
      }
      
      if(isset($info->meta->pagination->next_link)) {
        
        /* we expect: /v2/lists/1/contacts?next=c3RhcnRBdD0xNTUmbGltaXQ9MTAw */
        
        $uri = $info->meta->pagination->next_link;
        $page++;
        
        continue;
      }
      
      /* if we don't have a next page, we are done. */
      
      break;
    }
    
    /* pass back the results */
    
    return $results;
  }
    
}

?>