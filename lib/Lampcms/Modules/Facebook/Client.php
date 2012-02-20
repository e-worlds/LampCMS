<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms\Modules\Facebook;


use \Lampcms\Interfaces\FacebookUser;
use \Lampcms\FacebookAuthException;
use \Lampcms\FacebookAuthUserException;
use \Lampcms\FacebookApiException;
use \Lampcms\String;
use \Lampcms\TimeZone;
use \Lampcms\Utf8String;
use \Lampcms\UserFacebook;
use \Lampcms\Cookie;
use \Lampcms\HttpTimeoutException;
use \Lampcms\Http401Exception;
use \Lampcms\HttpResponseCodeException;
//use \Lampcms\UserFacebook;


require('SDK/base_facebook.php');
require('SDK/facebook.php');

/**
 * Class for Signing in with Facebook, creating
 * new user from Facebook profile.
 *
 * Post to wall info: http://developers.facebook.com/docs/reference/php/facebook-api/
 *
 *
 * @author admin
 *
 */
class Client
{
	/**
	 * Url of Facebook Graph API for posting message to wall
	 * This is a template url. %s will be replaced with actual facebookID
	 * of User
	 *
	 * This same url can also be used to get data from API, just
	 * set method to GET instead of post
	 * and can get the DATA from Facebook
	 *
	 * @var string
	 */
	const WALL_URL = 'https://graph.facebook.com/%s/feed';

	/**
	 *
	 * Enter description here ...
	 * @var unknown_type
	 */
	protected $Registry;

	/**
	 *
	 * Facebook Config array
	 * This is array from the [FACEBOOK] section
	 * in !config.ini
	 * @var array
	 */
	protected $aFBConfig;
	/**
	 *
	 * API Secret.
	 * This is NOT user's token, this
	 * is secret string for our own API
	 * You get this when you register your
	 * app with Facebook
	 *
	 * @var string
	 */
	protected $appSecret;

	/**
	 *
	 * App ID for our Facebook API
	 * Get this when registering your APP
	 * with Facebook
	 * @var string
	 */
	protected $appId;

	/**
	 * User object
	 *
	 * @var object UserFacebook
	 */
	protected $User;

	/**
	 * Array of user profile
	 * that we get from Facebook API
	 *
	 * @var array
	 */
	protected $aFbUserData;

	/**
	 * Facebook ID
	 * This is not the username, this is a numeric
	 * user id from facebook
	 * @var string
	 */
	protected $fbId;

	public function __construct(\Lampcms\Registry $Registry){

		$this->Registry = $Registry;
		$this->aFBConfig = $Registry->Ini->getSection('FACEBOOK');
		$this->appId = $this->aFBConfig['APP_ID'];
		$this->appSecret = $this->aFBConfig['APP_SECRET'];
	}

	/**
	 * Get object of type UserFacebook
	 * Either find existing user that has the facebook Id
	 * extracted from facebook cookie
	 * or create new user
	 *
	 * @return object of type UserFacebook
	 *
	 * @throws FacebookAuthException
	 */
	public function getFacebookUser(){
		if(empty($this->appId) || empty($this->appSecret)){
			throw new FacebookAuthException('Facebook API not setup. Read instructions in the !config.ini [FACEBOOK] section');
		}

		$this->getFacebookProfile();

		/**
		 * If we get Facebook Id then get user data
		 * from our own database by facebook ID.
		 */

		$aUser = $this->getUserArray($this->fbId);
		if(!empty($aUser)){
			$this->User = UserFacebook::factory($this->Registry, $aUser);
			d('existing user $this->User: '.print_r($this->User->getArrayCopy(), 1));
			$this->updateUser();
			d('cp');

			return $this->User;
		}



		/**
		 * See if we already have the user with the email
		 * address provided by facebook.
		 * In such case we just run updateUser()
		 * And then.... append array of access_token
		 *
		 * @todo potential problem:
		 * someone registers bogus account with someone else's email
		 * address.
		 *
		 * Then the real owner of that email registers via Facebook
		 * We then associate some bogus account with this one
		 *
		 * The bogus account cannot be used by hacker because hacker does
		 * not know the password so this is not a big problem.
		 *
		 *
		 */
		if(!empty($this->aFbUserData['email'])){
			d('trying to find existing user by email address: '.$this->aFbUserData['email']);
			$aByEmail = $this->Registry->Mongo->EMAILS->findOne(array('email' => \mb_strtolower($this->aFbUserData['email']) ));
			d('$aByEmail: '.print_r($aByEmail, 1) );
			if(!empty($aByEmail) && !empty($aByEmail['i_uid'])){
				$uidByEmail = (int)$aByEmail['i_uid'];
				d('$uidByEmail: '.$uidByEmail);
			}
		}

		/**
		 * This means this facebook user is not
		 * registered on our site.
		 * Not found either by facebook id or by
		 * email address. We are confident that this is
		 * NOT an existing Facebook user.
		 */
		if(empty($uidByEmail)){
			d('cp empty uid');
			$this->createNewUser();

			return $this->User;
		}

		/**
		 * @todo check everything below
		 *
		 */

		$aUser = $this->Registry->Mongo->USERS->findOne(array('_id' => $uidByEmail));
		d('aUser var type: '.gettype($aUser).' ' .print_r($aUser, 1));

		/**
		 * Found existing user
		 * If this is a Connect action then check if this is
		 * not the same user as Viewer and throw exception
		 * if this is the same uid as Viewer then just update
		 * Viewer record, it's OK and actually in case Viwer had FB
		 * access revoked before this will update their access back
		 * to "active" FB user by adding valid FB token to User object
		 */
		if(!empty($aUser)){
			$this->User = UserFacebook::factory($this->Registry, $aUser);
			d('existing user $this->User: '.print_r($this->User->getArrayCopy(), 1));
			$this->updateUser();

		} else {
			/**
			 * This is a very unlikely situation, not sure how
			 * this could be possible....
			 */
			d('Very unlikely situation occured found uid: but no user in USERS. ');
			$this->createNewUser();
		}

		return $this->User;
	}

	/**
	 * Get the profile from Facebook API
	 * for a user identified by a cookie OR
	 * for a user that is currently set as $this->User
	 * also add the 'token' to the profile data
	 * and set it as $this->aFbUserData
	 *
	 *
	 * @return object $this
	 */
	protected function getFacebookProfile(){
		// BEGIN getProfileFromFacebook
		/**
		 * Do not proceed further if
		 * there is no fbsr_ cookie
		 */
		$cookieName = 'fbsr_'.$this->appId;
		if(!isset($_COOKIE) || empty($_COOKIE[$cookieName])){
			throw new FacebookAuthException('No fbsr_ cookie present');
		}

		try{
			$Facebook = new \Facebook(array(
			  'appId'  => $this->appId,
			  'secret' => $this->appSecret
			));
		} catch(\Exception $e){
			throw new FacebookAuthException($e->getMessage());
		}

		$this->fbId = $Facebook->getUser();
		if(!$this->fbId){
			throw new FacebookAuthException('Not a facebook user');
		}

		try{
			$this->aFbUserData = $Facebook->api('/me');
			$token = $Facebook->getAccessToken();
		} catch(FacebookApiException $e){
			$details = $e->getResult();
			e('Error trying to get data from Facebook API: '.print_r($details, 1));
			throw new FacebookAuthException('Error trying to get data from Facebook API: '.print_r($details, 1));
		}
		d('cp');
		if(!$token){
			throw new FacebookAuthException('Could not get access token from Facebook object');
		}
		$this->aFbUserData['token'] = $token;
		d('$this->aFbUserData: '.print_r($this->aFbUserData, 1));

		return $this;
	}


	/**
	 * Update user data in USERS collection
	 * by using $this->User object's save() method
	 *
	 * @return object $this
	 */
	protected function updateUser(){
		d('cp');

		$this->User['fb_id'] = (string)$this->aFbUserData['id'];
		$this->User['fb_token'] = $this->aFbUserData['token'];
		$this->User['fn'] = $this->aFbUserData['first_name'];
		$this->User['ln'] = $this->aFbUserData['last_name'];
		$extAvatar = $this->User['avatar_external'];
		$srcAvatar = \trim($this->User->offsetGet('avatar'));

		if(empty($extAvatar)){
			$this->User['avatar_external'] = 'http://graph.facebook.com/'.$this->aFbUserData['id'].'/picture';

			/**
			 * If user also did not have any avatar
			 * then
			 * after this update we should also update
			 * the welcome block (removing it from SESSION will
			 * ensure that it updates on next page load) so that
			 * avatar on the welcome block will change to the
			 * external avatar
			 */
			if(empty($srcAvatar)){
				if(!empty($_SESSION) && !empty($_SESSION['welcome'])){
					unset($_SESSION['welcome']);
				}
			}
		}

		if(!empty($this->aFbUserData['link'])){
			$this->User['fb_url'] = $this->aFbUserData['link'];
		}


		try{
			$this->User->save();
			d('cp');
			$this->Registry->Dispatcher->post($this->User, 'onUserUpdate');

		} catch (\Exception $e){
			e('Error while saving user: '.$e->getMessage().' file: '.$e->getFile().' on line '.$e->getLine());
		}
			
		return $this;
	}


	/**
	 *
	 * Get array of data for user by the value
	 * of fb_id in USERS collection
	 *
	 * @param mixed $fb_id
	 * @return mixed null|array
	 *
	 */
	protected function getUserArray($fb_id){
		$fb_id = (string)$fb_id;
		$coll = $this->Registry->Mongo->USERS;
		$coll->ensureIndex(array('fb_id' => 1));

		return  $coll->findOne(array('fb_id' => $fb_id));
	}


	/**
	 * Create new record in EMAILS table for this new user
	 * but only if user has provided email address
	 *
	 * @return object $this
	 */
	protected function saveEmailAddress(){
		if(!empty($this->aFbUserData['email'])){
			$coll = $this->Registry->Mongo->EMAILS;
			$coll->ensureIndex(array('email' => 1), array('unique' => true));

			$a = array(
				'email' => \mb_strtolower($this->aFbUserData['email']),
				'i_uid' => $this->User->getUid(),
				'has_gravatar' => \Lampcms\Gravatar::factory($this->aFbUserData['email'])->hasGravatar(),
				'ehash' => hash('md5', $this->aFbUserData['email'])
			);
			try{
				$o = \Lampcms\Mongo\Doc::factory($this->Registry, 'EMAILS', $a)->insert();
			} catch (\Exception $e){
				e('Unable to save email address from Facebook to our EMAILS: '.$e->getMessage().' in '.$e->getFile().' on '.$e->getLine());
			}
		}

		return $this;
	}

	/**
	 *
	 * What if email address provided from Facebook
	 * already belongs to some other user?
	 *
	 * This would mean that existing user is just
	 * trying to signup with Facebook.
	 *
	 * In this case we should allow it but ONLY create
	 * a record in the USERS_FACEBOOK table and use users_id
	 * of use that we find by email address
	 *
	 * and then also insert avatar_external into USERS
	 *
	 * @todo create username for user based on Facebook username
	 * Facebook does not really have username, so we can use fn_ln
	 *
	 */
	protected function createNewUser(){
		$extAuth = new \Lampcms\ExternalAuth($this->Registry);
		d('cp');
		$this->Registry->Mongo->USERS->ensureIndex(array('fb_id' => 1));

		/**
		 * Time zone offset in seconds
		 * @var int
		 */
		$tzo = (array_key_exists('timezone', $this->aFbUserData)) ? $this->aFbUserData['timezone'] * 3600 : Cookie::get('tzo', 0);

		/**
		 * User language
		 * @var string
		 */
		$lang = (!empty($this->aFbUserData['locale'])) ? \strtolower(\substr($this->aFbUserData['locale'], 0, 2)) : $this->Registry->getCurrentLang();

		/**
		 * User locale
		 * @var string
		 */
		$locale = (!empty($this->aFbUserData['locale'])) ? $this->aFbUserData['locale'] : $this->Registry->Locale->getLocale();

		$this->tempPassword = String::makePasswd();

		/**
		 * Sid value use existing cookie val
		 * if possible, otherwise create a new one
		 * @var string
		 */
		$sid = (false === ($sid = Cookie::getSidCookie())) ? String::makeSid() : $sid;

		$displayName = (!empty($this->aFbUserData['name'])) ? $this->aFbUserData['name'] : $this->aFbUserData['first_name'].' '.$this->aFbUserData['last_name'];
		$username = $extAuth->makeUsername($displayName, true);


		if(!array_key_exists('email', $this->aFbUserData)){
			/**
			 * @todo if this becomes a common problem
			 * then we need to ask user for an email address
			 * at step 2 of registration, just like for Twitter users
			 * And the 'role' will then be different like 'unactivated_external'
			 */
			e('No email in Facebook data: '.print_r($this->aFbUserData, 1));
			$email = '';
		} else {
			$email = \mb_strtolower($this->aFbUserData['email']);
		}
		/**
		 * Create new record in USERS table
		 * do this first because we need uid from
		 * newly created record
		 */
		$aUser = array(
		'username' => $username,
		'username_lc' => \mb_strtolower($username, 'utf-8'),
		'fn' => $this->aFbUserData['first_name'],
		'ln' => $this->aFbUserData['last_name'],
		'rs' => $sid,
		'email' => $email, //Utf8String::factory($this->aFbUserData['email'])->toLowerCase()->valueOf(),
		'fb_id' => (string)$this->aFbUserData['id'], 
		'fb_token' => $this->aFbUserData['token'],
		'pwd' => String::hashPassword($this->tempPassword),
		'avatar_external' => 'http://graph.facebook.com/'.$this->aFbUserData['id'].'/picture',
		'i_reg_ts' => time(),
		'date_reg' => date('r'),
		'role' => 'external_auth',
		'lang' => $lang,
		'i_rep' => 1,
		'tz' => TimeZone::getTZbyoffset($tzo),
		'i_fv' => (false !== $intFv = Cookie::getSidCookie(true)) ? $intFv : time());

		if(!empty($this->aFbUserData['gender'])){
			$aUser['gender'] = ('male' === $this->aFbUserData['gender']) ? 'M' : 'F';
		}

		$aUser = \array_merge($this->Registry->Geo->Location->data, $aUser);

		if(!empty($this->aFbUserData['locale'])){
			$aUser['locale'] = $this->aFbUserData['locale'];
		}

		if(!empty($this->aFbUserData['link'])){
			$aUser['fb_url'] = $this->aFbUserData['link'];
		}

		d('aUser: '.print_r($aUser, 1));

		$this->User = UserFacebook::factory($this->Registry, $aUser);
		$this->User->insert();

		d('$this->User after insert: '.print_r($this->User->getArrayCopy(), 1));
		$this->Registry->Dispatcher->post($this->User, 'onNewUser');
		$this->Registry->Dispatcher->post($this->User, 'onNewFacebookUser');
		d('cp');

		$this->saveEmailAddress();
		d('cp');

		\Lampcms\PostRegistration::createReferrerRecord($this->Registry, $this->User);

		return $this;
	}



	/**
	 * Add Facebook token and stuff to existing user
	 *
	 * Logic:
	 * 1) If there is already another user with same
	 * Facebook account - throw exception - must be unique
	 *
	 * 2) If this user is already connected to this same FB account -
	 * this is OK, just update FB and User records
	 *
	 * 3) If Facebook's email address belongs to another user -?
	 * It should not really be a problem in this case. This means
	 * that someone (probably this same user) already has an account
	 * on this site but it's a different account. So NOW this user is
	 * connecting his second account to Facebook. This should not
	 * cause any problems in the future.
	 *
	 *
	 * @param User $User
	 * @return object $this
	 */
	public function connect(\Lampcms\User $User){
		d('cp');
		$this->User = $User;

		/**
		 * @todo
		 * Need to get profile array from facebook api, using SDK
		 *
		 */
		$this->getFacebookProfile();
		d('cp');

		$this->checkUniqueAccount($this->getUserArray($this->aFbUserData['id']));
		d('cp');

		$this->updateUser();
		d('cp');

		return $this;
	}


	/**
	 *
	 * Validation to check that user represented by
	 * $aUser array is the same account as $this->User
	 *
	 * @param array $aUser
	 * @throws \Lampcms\Exception is user from input array
	 * is different from $this->User.
	 *
	 * @return object $this
	 *
	 */
	protected function checkUniqueAccount(array $aUser = null){
		if(!is_object($this->User) || (!$this->User instanceof \Lampcms\User)){
			d('$this->User now set yet');

			throw new \Lampcms\DevException('$this->User now set yet');
		}

		if(!empty($aUser) && ((int)$aUser['_id'] !== $this->User->getUid())){
			d('Different user already exists');
			/**
			 * @todo
			 * Translate String
			 */
			throw new \Lampcms\Exception('This Facebook account is already connected to another user <strong>'.$aUser['fn']. ' '.$aUser['ln'].'</strong><br>
				<br>A Facebook account cannot be associated with more than one account on this site<br>');
		}

		d('cp');

		return $this;
	}


	public function postToWall(array $data){

		//Posting to
		try{
			$result = $facebook->api('/me/feed', 'post', $data);
			echo __METHOD__. ' '.__LINE__.' '.var_export($result, true);
		} catch(\Exception $e){
			echo get_class($e).' '.$e->getMessage();
		}
	}



	/**
	 * Post update to user Wall
	 *
	 *
	 * @param mixed array $aData | string can provide just
	 * a string it will be posted to Facebook User's Wall as a message
	 * it can contain some html code - it's up to Facebook to allow
	 * or disallow certain html tags
	 *
	 * @return mixed if successful post to Facebook API
	 * then it will return the string returned by API
	 * This could be raw string of json data - not json decoded yet
	 * or false in case there were some errors
	 *
	 * @throws FacebookApiException in case of errors with
	 * using API or more general \Lampcms\Exception in case there
	 * were some other problems sowhere along the line like
	 * in case with Curl object
	 *
	 */
	public function postUpdate(\Lampcms\Interfaces\FacebookUser $User, $aData){

		if(!is_string($aData) && !is_array($aData)){
			throw new \InvalidArgumentException('Invalid data type of $aData: '.\gettype($aData));
		}

		$Curl = new \Lampcms\Curl;
		$aData = \is_array($aData) ? $aData : array('message' => $aData);

		$facebookUid = $User->getFacebookUid();
		$facebookToken = $User->getFacebookToken();
		d('$facebookUid: '.$facebookUid.' $facebookToken: '.$facebookToken);

		if(empty($facebookUid) || empty($facebookToken)){
			d('User is not connected with Facebook');

			return false;
		}

		$aData['access_token'] = $User->getFacebookToken();
		d('$aData: '.print_r($aData, 1));

		$url = \sprintf(self::WALL_URL, $facebookUid);
		d('cp url: '.$url);;
		try{
			$Curl->getDocument($url, null, null, array('formVars' => $aData))->checkResponse();
			$retCode = $Curl->getHttpResponseCode();
			$body = $Curl->getResponseBody();
			d('retCode: '.$retCode.' resp: '.$body);
			return $body;
		} catch(\Lampcms\HttpTimeoutException $e ){
			d('Request to Facebook server timedout');
			throw new FacebookApiException('Request to Facebook server timed out. Please try again later');
		} catch(\Lampcms\Http401Exception $e){
			d('Unauthorized to get data from Facebook, most likely user unjoined the site');
			$User->revokeFacebookConnect();
			throw new FacebookApiException('Anauthorized with Facebook');
		} catch(\Lampcms\HttpResponseCodeException $e){
			if(function_exists('e')){
				e('LampcmsError Facebook response exception: '.$e->getHttpCode().' '.$e->getMessage().' body: '.$Curl->getResponseBody());
			}
			/**
			 * The non-200 response code means there is some kind
			 * of error, maybe authorization failed or something like that,
			 * or maybe Facebook server was acting up,
			 * in this case it is better to delete cookies
			 * so that we dont go through these steps again.
			 * User will just have to re-do the login fir GFC step
			 */

			throw new FacebookApiException('Error during authentication with Facebook server');
		}catch (\Exception $e){
			if(function_exists('e')){
				e('Unable to post: '.$e->getMessage().' code: '.$e->getCode());
			}
		}

		d('cp');

		return false;
	}

}
