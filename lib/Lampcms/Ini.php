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
 * Object represents the parsed !config.ini file
 * has accessorts for the whole section via getSection
 * or access values from the CONSTANTS section via
 * the magic __get method like this:
 * oIni->ADMIN_EMAIL
 *
 *
 * @author admin
 *
 */
class Ini extends LampcmsArray
{

	public function __construct($iniFile = null){
		$iniFile = (!empty($iniFile)) ? $iniFile : LAMPCMS_PATH.DIRECTORY_SEPARATOR.'!config.ini';

		$aIni = \parse_ini_file($iniFile, true);

		if ( empty($aIni)) {
			throw new IniException('Unable to parse ini file: '.$iniFile.' probably a syntax error in file');
		}

		parent::__construct($aIni);
	}
	
	
	/**
	 * Get value of config var from
	 * object
	 * 
	 * @param string $name
	 * @throws IniException if CONSTANTS key
	 * does not exist OR if var 
	 * does not exist and is a required var
	 * 
	 * @return string value of $name
	 */
	public function getVar($name){
		if (!$this->offsetExists('CONSTANTS')) {
			throw new IniException('"CONSTANTS" section of ini file is missing');
		}

		$aConstants = $this->offsetGet('CONSTANTS');

		/**
		 * TEMP_DIR returns path
		 * to temp always ends with DIRECTORY_SEPARATOR
		 * if TEMP_DIR not defined in !config.ini
		 * then will use system's default temp dir
		 *
		 */
		if ('TEMP_DIR' === $name) {
			if (!empty($aConstants['TEMP_DIR'])) {

				$tmpDir = \rtrim($aConstants['TEMP_DIR'], '/');
				$tmpDir .= DIRECTORY_SEPARATOR;

				return $tmpDir;
			}

			return \sys_get_temp_dir();			
		}



		if (!array_key_exists($name, $aConstants)) {

			throw new IniException('Error: configuration param: '.$name.' does not exist');
		}

		if ('MAGIC_MIME_FILE' === $name) {
			if (!empty($aConstants['MAGIC_MIME_FILE']) && !is_readable($aConstants['MAGIC_MIME_FILE']) ) {
				throw new IniException('magic mime file does not exist in this location or not readable: '.$aConstants['MAGIC_MIME_FILE']);
			}
		}

		switch($name){
			case 'SITE_URL':
				if(empty($aConstants['SITE_URL'])){
					throw new IniException('Value of SITE_URL in !config.inc file SHOULD NOT be empty!');
				}

				$ret = \rtrim($aConstants['SITE_URL'], '/');
				break;

				/**
				 * If these constants are not specifically set
				 * then we should return the path to our
				 * main website.
				 * This is because we need to use absolute url, not
				 * relative url for these.
				 * The reason is if using virtual hosting, then
				 * relative urls will point to just /images/
				 * so they will actually resolve to individual's own domain + path
				 * for example http://somedude.outsite.com/images/
				 * and on another user's site http://johnny.oursite.com/images/
				 * This will cause chaos in browser caching.
				 * Browser will think (rightfully so) that these are different sites.
				 *
				 * That's why we must point to our main site
				 * for all images, css, js, etc... so that no matter whose
				 * site we are on the browser can use cached files and most
				 * importantly will not keep storing the same images in cache for each
				 * sub-domain
				 */
			case 'THUMB_IMG_SITE':
			case 'ALBUM_THUMB_SITE':
			case 'ORIG_IMG_SITE':
			case 'AVATAR_IMG_SITE':
			case 'IMG_SITE':
			case 'JS_SITE':
			case 'CSS_SITE':
				$ret = (empty($aConstants[$name])) ? $this->__get('SITE_URL') : \rtrim($aConstants[$name], '/');
				break;

			case 'WWW_DIR':
			case 'EMAIL_ADMIN':
				if(empty($aConstants[$name])){
					throw new IniException($name.' param in !config.inc file has not been set! Please make sure it is set');
				}

				$ret = \trim($aConstants[$name], "\"'");
				break;

			case 'LOG_FILE_PATH':
				if((substr(PHP_SAPI, 0, 3) === 'cli') || (substr(PHP_SAPI, 0, 3) === 'cgi')){
					$ret = $aConstants['LOG_FILE_PATH_CGI'];
				} else {
					$ret = $aConstants['LOG_FILE_PATH'];
				}
				break;

			default:
				$ret = $aConstants[$name];
				break;
		}

		return $ret;
	}


	/**
	 * Magic method to get
	 * a value of config param
	 * from ini array's CONSTANTS section
	 * 
	 * This is how other objects get values
	 * from this object 
	 * most of the times
	 *
	 * @return string a value of $name
	 * @param string $name
	 * @throws LampcmsIniException if $name
	 * does not exist as a key in this->aIni
	 *
	 */
	public function __get($name){
		return $this->getVar($name);
	}


	public function __set($name, $val){
		throw new IniException('Not allowed to set value this way');
	}
	
	
	/**
	 *
	 * @param string $name name of section in !config.ini file
	 *
	 * @return array associative array of
	 * param => val of all params belonging to
	 * one section in !config.ini file
	 */
	public function getSection($name){
		if(!$this->offsetExists($name)){
			d('no section '.$name.' in config file');

			throw new IniException('Section '.$name.' does not exist in config');
		}

		return $this->offsetGet($name);
	}



	/**
	 * Setter to set particular section
	 * This is useful during unit testing
	 * We can set the "MONGO" section with arrays
	 * of out test database so that read/write
	 * operations are performed only on test database
	 * Also can set values of other sections like "TWITTER", "FACEBOOK",
	 * "GRAVATAR" or any other section that we want to "mock" during test
	 *
	 * @param string $name name of section in !config.ini file
	 *
	 * @param array $val array of values for this section
	 *
	 * @return object $this
	 */
	public function setSection($name, array $val){
		$this->offsetSet($name, $val);

		return $this;
	}



	/**
	 * Creates and returns array of
	 * some config params;
	 *
	 * This array is usually added as json object
	 * to some of the pages that then use javascript
	 * to get values from it.
	 *
	 * @return array
	 */
	public function getSiteConfigArray(){
		$a = array();

		if('' !== $albThum = $this->ALBUM_THUMB_SITE){
			$a['ALBUM_THUMB_SITE'] = $albThum;
		}

		if('' !== $imgThum = $this->THUMB_IMG_SITE){
			$a['THUMB_IMG']= $imgThum;
		}

		if('' !== $imgSite = $this->IMG_SITE){
			$a['IMG_SITE']= $imgSite;
		}

		if('' !== $origSite = $this->ORIG_IMG_SITE){
			$a['ORIG_IMG_SITE']= $origSite;
		}

		if('' !== $avatarSite = $this->AVATAR_IMG_SITE){
			$a['AVATAR_IMG_SITE']= $avatarSite;
		}

		if('' !== $cssSite = $this->CSS_SITE){
			$a['CSS_SITE']= $cssSite;
		}

		if('' !== $jsSite = $this->JS_SITE){
			$a['JS_SITE']= $jsSite;
		}


		return $a;
	}

}
