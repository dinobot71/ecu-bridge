<?php 

/**
 * 
 * web \ oauth \ OAuthUser - light weight structure to provide a common
 * definition for user entities at remove authentication providers. Not
 * all providers will fill in all fields, providers like Twitter are 
 * exceptionally terse :( 
 * 
 * @package littlemdesign.com
 * 
 * @author Little m Design (Michael Garvin)
 * @copyright Copyright (c) 2013-, Littl m Design
 * 
 */

class littlemdesign_web_oauth_OAuthUser {
  
  /* id (at the provider */
  
  public $id = NULL;

  /* profile */

  public $profileURL = NULL;

  /* avatar */

  public $photoURL = NULL;

  /* display/screen name */

  public $displayName = NULL;

  /* name */

  public $firstName = NULL;
  public $lastName = NULL;

  /* sex */
  
  public $gender = NULL;

  /* age/birth date */

  public $birth   = NULL;

  /* email */

  public $email   = NULL;

  /* address */

  public $phone   = NULL;
  public $address = NULL;
  public $country = NULL;
  public $state   = NULL;
  public $city    = NULL;
  public $zip     = NULL; 
  
  /* 
   * the login credentials (OAuth) that we used to 
   * get this profile.
   * 
   */
  
  public $provider     = false;
  public $accessToken  = false;
  public $accessSecret = false;
  
  /* the expiry time, if we know it (unix time stamp) */
  
  public $expiry       = false;
}

?>