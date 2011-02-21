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
 *    the website's Questions/Answers functionality is powered by lampcms.com
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

 
namespace Lampcms;


/**
 * This static class is responsible
 * for responding
 * to cache control request headers
 * and/or to send out cache control
 * request headers
 *
 * @author Dmitri Snytkine
 *
 */
class CacheHeaders
{
	/**
	 * Process the If-Modified-Since
	 * and If-None-Match headers
	 * This method is only called from classes
	 * in which it makes sense to make use of these headers
	 * If this method is called it will check
	 * if content has changes and if it has not,
	 * then it will send the 304 status header and exit
	 * This will save processing resources and of cause
	 * will save bandwidth since no body has to be sent
	 *
	 * If the If-Modified-Since nor If-None-Matched
	 * are used in the request, then this metod will
	 * send out the headers
	 * Last-Modified and/or Etag
	 * as passed to this method. This way a browser
	 * will use these values when making the next request
	 *
	 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec13.html#sec13.3.4
	 * Says that ideally the server should send both
	 * Last-Modified and Etag but it's not a requirement
	 *
	 * @param string $lastModified must be valid time string
	 * @param string $etag any unique string
	 * @param int $maxAge maximum time during which client considers the cache entry is
	 * not stale. This number must be in seconds. By default is 15 seconds, but can be
	 * set to 0 to indicate that cache entry should alwasy be revalidated with the server
	 *
	 * @param bool $bCheckForChange if set to false, then will only send out
	 * the Last-Modified and/or Etag headers but will not
	 * look at request headers at all.
	 * Sometimes we may want to only send out headers but
	 * don't return 304 code if script has not changed.
	 * This is not a good idea usually, so it's set to true by default
	 *
	 * @return mixed true on success or nothing at all
	 * because script will exit after sending the 304 header
	 */
	public static function processCacheHeaders($etag = null, $lastModified = null, $maxAge = 10, $bCheckForChange = true)
	{
		d('$etag '.$etag.' $lastModified: '.$lastModified);
		if(empty($lastModified) && empty($etag)){
			d('No headers values provided, exising');
				
			return $this;
		}


		/**
		 * A small but important check to see
		 * if headers have already been sent, in which
		 * case we can't send any more headers or it will generate
		 * the 'Cannot modify header information'... error.
		 * In such case we log this as error so the admin
		 * may be notified
		 *
		 */
		if(headers_sent($file, $line)){
			e('LampcmsError Headers have already been sent in file '.$file. ' on line '.$line);

			return true;
		}

		/**
		 * This tells browsers (at least this is what it's supposed to tell)
		 * is "it's OK to cache this page" but
		 * before serving the cached content alwasy check back
		 * with the server to see if content has changed.
		 *
		 * If we set the maxage to 600 (for example), then
		 * it would tell the server that if the content was downloaded
		 * less than 10 minues ago, then don't even attempt to contant
		 * the server, just serve the cached content.
		 *
		 * The private meas per-user cache. Basically this means
		 * that proxy server should not treat this cached entry as
		 * suitable for every user.
		 * Since one user may have a different language preference
		 * than the other, the same page can be served in English
		 * to one user and in Italian to another. So each client
		 * can still cache his own version of page, but it's
		 * not one fits all.
		 *
		 * More importantly the Header of page may include the 'welcome back'
		 * block which would include user's username like "Welcome back Sam"
		 * Surely this sort of page is indended only for Sam's browser cache
		 * and not for just any user, so proxies should not
		 * serve this copy to all users.
		 * However, the search bots may ignore the cache-control
		 * if its marked private since it does look to them
		 * to be user-specific and they don't like that.
		 * Search engines like to know that the page they see
		 * is the same page a user will see.
		 * Just to be on the safe side with them we will mark
		 * it as public and then take precautions that
		 * userID and language are part of Etag value.
		 * This way a logged in user will get different etag
		 * while Search bots will all get the same etag with
		 * the value of non-logged-in user.
		 *
		 * Important:
		 * Pragma is ignored when Cache-Control header is present!
		 * this is only for older http 1.0 browsers and only to
		 * override the php's default no-cache value of Pragma
		 */
		header("Pragma: public");
		header("Cache-Control: public, maxage=$maxAge, must-revalidate");

		/**
		 * header_remove is only available
		 * as of php 5.3
		 * The php by default (default in php.ini)
		 * will send Expires header with the date
		 * long in the past. This basically tells the
		 * browser not to use cached version of the site
		 * without checking with the site first.
		 * It does not mean no to cache, just not
		 * to use cached version without checking
		 * with the server. Some browsers may still interpret
		 * it as 'not to cache' since it does not make
		 * sense to cache page that is already expired.
		 * Basically it's better to unset this header, but
		 * not unsetting it should not hurt modern browsers.
		 *
		 * Also as of HTTP 1.1 the value in Cache-Control maxage
		 * always override the Last-Modified header, so as long
		 * as we send out Cache-Control maxage, we should not worry
		 * about this "Expires" header that php add without asking us
		 *
		 */
		if(function_exists('header_remove')){
			
			header_remove("Expires");
		} else {
			
			header('Expires: ');
		}

		/**
		 * Now the logic part:
		 * First of all we must return the
		 * Etag and Last-Modified values
		 * in response headers regardless of
		 * the outcome of the 'nochange' check
		 * So we can just include these headers here now
		 */
		if(!empty($lastModified)){

			header("Last-Modified: $lastModified");
		}
			
		if(!empty($etag)){
			
			header("Etag: $etag");
		}

		/**
		 * If $bCheckForChange is false or null, then
		 * we not using the values of If-Modified-Since
		 * and If-None-Match to compare to our supplied values
		 * in which case no further action is going to be done here
		 */
		if(!$bCheckForChange){
			
			return true;
		}

		/**
		 * As per http://www.w3.org/Protocols/rfc2616/rfc2616-sec13.html#sec13.3.4
		 *
		 * An HTTP/1.1 origin server, upon receiving a conditional request
		 * that includes both a Last-Modified date
		 * (e.g., in an If-Modified-Since or If-Unmodified-Since header field)
		 * and one or more entity tags
		 * (e.g., in an If-Match, If-None-Match, or If-Range header field)
		 * as cache validators,
		 * MUST NOT return a response status of 304 (Not Modified)
		 * unless doing so is consistent with all of the conditional header fields
		 * in the request.
		 *
		 * This means that BOTH conditions should be checked
		 * and 304 returned only if BOTH conditions
		 * indicate 'NO Change', more specific
		 * both must "NOT indicate change"
		 * If either one condition indicates a definite 'change'
		 * then we must NOT return 304
		 */

		$noChangeByEtag = $noChangeByTimestamp = false;

		/**
		 * If we can determing change/no change by timestamp then do it
		 * otherwise we skip this test
		 */
		if(!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (null !== $lastModified)){
			/**
			 * If change is detected, then return right away,
			 * no need to run a possible second check (no need to compare etag)
			 */
			if(false === $noChangeByTimestamp = self::detectNoChangeByTimestamp($lastModified)){
				return true;
			}

		}

		/**
		 * If we can determine change/no change by etag then do it
		 * otherwise we skip this test
		 */
		if(!empty($_SERVER['HTTP_IF_NONE_MATCH']) && (null !== $etag)){
			/**
			 * If change is detected (no match for etag)
			 * then return
			 */
			if(false === $noChangeByEtag = self::isEtagMatch($etag)){
				
				return true;
			}
		}

		/**
		 * Now if either one of the conditional checks return true,
		 * meaning that 'no change' has been detected
		 * we return 304 header but ONLY if request method is GET or HEAD,
		 * for all others return special code
		 */
		if($noChangeByEtag || $noChangeByTimestamp)
		{
			if($noChangeByEtag && ('GET' !== $_SERVER['REQUEST_METHOD'] && 'HEAD' !== $_SERVER['REQUEST_METHOD']))
			{
				header("HTTP/1.1 412 (Precondition Failed)");
				exit;
			}

			header("HTTP/1.1 304 Not Modified");
			exit;
		}

		return true;
	}


	/**
	 * Etag parsing
	 * Not as simple as just comparing value!
	 * The  If-None-Match may include multiple comma-separated etag values!
	 *
	 * From: http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.26
	 * Instead, if the request method was GET or HEAD,
	 * the server SHOULD respond with a 304 (Not Modified) response,
	 * including the cache- related header fields (particularly ETag)
	 * of one of the entities that matched.
	 * For all other request methods,
	 * the server MUST respond with a status of 412 (Precondition Failed).
	 *
	 * From: http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.26
	 * ... server MUST NOT perform the requested method,
	 * unless required to do so because the resource's modification date
	 * fails to match that supplied in an
	 * If-Modified-Since header field in the request.
	 *
	 * This means that if supplied etag matched our etag we still must
	 * check the If-Modified-Since header
	 *
	 * Note about weak validator:
	 * (Etag is a validator)
	 * From this url:
	 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec13.html#sec13.3.3
	 *
	 * The weak comparison function: in order to be considered equal,
	 * both validators MUST be identical in every way, but either or
	 * both of them MAY be tagged as "weak" without affecting the
	 * result.
	 *
	 * @param string $etag value of our ACTUAL etag for this page
	 * This value must be unique not only for this page but for
	 * the whole domain. This means that just having the timestamp of
	 * the message is not enough since another message may have the exact same timestamp
	 * To uniquely identify the page we should include messageID followed by a timestamp
	 * and may append any other string to help uniquly identify the page
	 *
	 * @return bool true if ANY of the etags (in case more than one is included)
	 * matches OUR etag (value of $etag)
	 * true means DEFINITE 'NO CHANGE' detected
	 *
	 * false means 'no match', meaning content changed or
	 * unable to determine
	 */
	protected static function isEtagMatch($etag = null)
	{
		/**
		 * Special case http1.1 allows for wildcard
		 * of etag and it matches any value
		 */
		if('*' === $_SERVER['HTTP_IF_NONE_MATCH']){

			return true;
		}

		if(!strstr($etag, ', ')){

			return ($etag === $_SERVER['HTTP_IF_NONE_MATCH']);

		}

		$aEtags = explode(',', $_SERVER['HTTP_IF_NONE_MATCH']);

		foreach($aEtags as $tag){
			if(trim($tag) === $etag){
				return true;
			}
		}

		return false;
	}


	/**
	 * MUST return true ONLY if
	 * we are certain that content has not changed
	 * This means that both If-Modified-Since header
	 * and $lastModified values are present
	 * and after examining them we determine that there
	 * is definetely no change.
	 *
	 * @param $lastModified
	 * @return bool true means definete 'no change', false
	 * means content has changed
	 */
	protected static function detectNoChangeByTimestamp($lastModified)
	{
		if($_SERVER['HTTP_IF_MODIFIED_SINCE'] === $lastModified){
				
			/**
			 * A perfect match means no change!
			 */
			return true;
		}

		/**
		 * Handle the case where client composed an arbitrary value
		 * of If-Modified-Since
		 * This is not recommended, but we still must be able
		 * to handle this gacefully
		 * If value of If-Modified-Since greater than our Last-Modified
		 * that would mean that contant has indeed been modified
		 * For example, client asks for a content that has been modified
		 * after Dec 5 2009, but our content was last modified on Dec 4 2009
		 * As far as client is concerned, there has been no change
		 */
		if(strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= strtotime($lastModified) ){

			return true;
		}

		return false;

	}
}
