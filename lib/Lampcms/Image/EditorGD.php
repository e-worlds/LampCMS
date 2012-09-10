<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is licensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
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
 *       the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attributes
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
 * @copyright  2005-2012 (or current year) Dmitri Snytkine
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms\Image;

use \Lampcms\Validate;

/**
 * Image manipulation class
 * based on php GD2
 *
 * @author Dmitri Snytkine
 *
 */
class EditorGD extends Editor
{

    /**
     * Flag indicates that work
     * image GD resource is true color
     *
     * @var bool
     */
    protected $bTrueColor = false;

    /**
     * GD resource that holds
     * the original image
     * this may consume alot
     * of memory if the original
     * image is large
     *
     * @var resource of type 'gd'
     */
    protected $hdlOrig;

    /**
     * GD resource of image
     * that is currently being manipulated
     *
     * @var resource of type 'gd'
     */
    protected $hdlWork;


    /**
     * Must create $this->hdlOrig resource
     * from the input image path
     * or throw exception if could not do this
     *
     * @param $sPath a path to image
     *
     * @throws \Lampcms\ImageException if unable to load
     * image from path or image is not one of the supported
     * formats
     * @throws \UnexpectedValueException if file in sPath is not an image
     *
     * @return object $this
     */
    public function loadImage($sPath)
    {

        d('$sPath: ' . $sPath);
        if (false === $this->aOrigSize = @\getimagesize($sPath)) {
            throw new \UnexpectedValueException('@@Unable to parse uploaded file. Possibly unsupported image format or not an image@@');
        }

        d('$aOrigSize:  ' . print_r($this->aOrigSize, true));

        switch ( $this->aOrigSize['mime'] ) {
            case 'image/jpeg':
                $this->origType = 'jpeg';
                if (!function_exists('imagecreatefromjpeg')) {
                    throw new \Lampcms\ImageException('imagecreatefromjpeg function not available on your php. Your php GD does not have support for jpeg image format');
                }
                $this->hdlOrig = \imagecreatefromjpeg($sPath);
                break;

            case 'image/gif':
                $this->origType = 'gif';
                if (!function_exists('imagecreatefromgif')) {
                    throw new \Lampcms\ImageException('imagecreatefromgif function not available on your php Your php GD does not have support for gif image format');
                }
                $this->hdlOrig = \imagecreatefromgif($sPath);
                break;

            case 'image/png':
                $this->origType = 'png';
                if (!function_exists('imagecreatefrompng')) {
                    throw new \Lampcms\ImageException('imagecreatefrompng function not available on your php. Your php GD does not have support for png image format');
                }
                $this->hdlOrig = \imagecreatefrompng($sPath);
                break;

            default:
                throw new \Lampcms\ImageException('Unsupported image format: ' . $this->aOrigSize['mime']);
        }

        if (false === $this->hdlOrig) {
            throw new \Lampcms\ImageException('Unable to create GD resource for image path ' . $sPath);
        }

        d('$this->origType: ' . $this->origType);

        return $this;
    }


    /**
     * Set GD resource $hdlGD to be the
     * $this->hdlOrig resource
     *
     * @param object $hdlGD object of type gd resource
     *
     * @return object $this
     */
    public function setOrig($hdlGD)
    {
        Validate::type($hdlGD, array('resource' => 'gd'));
        d('setting new $this->hdlOrig resource');
        $this->hdlOrig = $hdlGD;

        return $this;
    }


    /**
     * Getter method for
     * $this->hdlOrig
     *
     * @return resource GD resource $this->hdlOrig
     */
    public function getOrig()
    {
        return $this->checkHdlOrig()->hdlOrig;
    }


    /**
     * Returns the file extension for the type
     * of image used as hdlOrig
     *
     * @return string file extension - '.jpg' for jpeg,
     * '.gif' for gif
     * or '.png' for png
     * Note: a dot is included before the extension
     *
     */
    public function getExtension()
    {

        $ext = \image_type_to_extension($this->aOrigSize['2']);

        return ('.jpeg' === $ext) ? '.jpg' : $ext;
    }


    /**
     * Saves image stored as GD resource
     * into a file
     *
     * @param string   $strDestination a full path to
     *                                 file in which the image should be saved
     *
     * @param resource $hdlImg         resource of type 'gd'
     *
     * @param bool|int $intCompression compression level
     *                                 this is only used for jpeg images
     *
     * @param bool     $bPreserveAlpha
     *
     * @param bool     $bKeepResource  is set to true, then don't destroy
     *                                 the GD resource after saving image to a file, otherwise
     *                                 the GD resource is destroyed after image is saved
     *
     *
     * @throws \Lampcms\ImageException if image could not
     * be saved in destination
     * @return object $this
     */
    public function save($strDestination, $hdlImg = null, $intCompression = false, $bPreserveAlpha = false, $bKeepResource = false)
    {
        d('$strDestination: ' . $strDestination . ' $hdlImg: ');
        if (null !== $hdlImg) {
            Validate::type($hdlImg, array('resource' => 'gd'));
        }

        $hdlImg = (null !== $hdlImg) ? $hdlImg : $this->hdlWork;

        $width = imagesx($hdlImg);
        d('$width ' . $width);

        if ('png' === $this->origType && $this->bTrueColor) {
            d('cp');
            $bPreserveAlpha = true;
        }

        if ((true === $bPreserveAlpha) && ('jpeg' !== $this->origType)) {
            $this->preserveTransparancy();
        }

        /**
         * Save image
         *
         * @todo add compression option for png images, which is different from
         *       jpg compression - has levels 1 - 9
         *  ( ('png' === $this->origType) && (false !== $intCompression)){
         *       do stuff here first recalculate compression based so that level 75 becomes 8 for example
         *  }
         */
        if (('jpeg' === $this->origType) && (false !== $intCompression)) {

            $intCompression = (!empty($intCompression)) ? $intCompression : $this->oSettings->QUALITY_COMPRESSION_WORK;
            if (false === \imagejpeg($hdlImg, $strDestination, $intCompression)) {
                throw new \Lampcms\ImageException('Error Unable to save image to  ' . $strDestination);
            }

        } elseif (('png' === $this->origType) && (false !== $intCompression)) {

            $intCompression = ceil((100 - $intCompression) / 10);
            d('$intCompression: ' . $intCompression);
            if (false === \imagepng($hdlImg, $strDestination, $intCompression)) {
                throw new \Lampcms\ImageException('Error Unable to save image to  ' . $strDestination);
            }

        } else {
            if (false === \imagegif($hdlImg, $strDestination)) {
                throw new \Lampcms\ImageException('Error Unable to save image to  ' . $strDestination);
            }
        }

        /**
         * After saving must destroy resource?
         * Not always, but usually a good idea
         */
        if (\is_resource($hdlImg) && true !== $bKeepResource) {
            d('cp');
            \imagedestroy($hdlImg);
        }

        return $this;
    }


    /**
     * This is the main method for making
     * thumbnails
     * the intX and intY are used if user has
     * 'smart thumb' feature enabled
     * in which case we calculate the X and Y offsets
     * based on user's setting of % cut, then pass them here
     * for thumb to be created
     *
     * @param int $intMaxWidth  maximum width of thumbnail
     *
     * @param int $intMaxHeight maximum height of thumbnail
     *
     * @throws \InvalidArgumentException
     * @return object $this
     */
    public function scale($intMaxWidth, $intMaxHeight)
    {

        /**
         * @todo
         * Check if hdlWork already exists, if yes,
         * then check its width and height
         * if they are the same as $intWidth, $intHeight,
         * then assume that image has already been
         * resized to this size just has not yet been saved
         * in this case throw an exception
         */
        $intX = $intY = 0;

        /**
         * @todo
         * If smart_resize then calculate
         * values of intX, intY, intCropWidth, intCropHeight
         * they can be all calculated in a separate method
         * and returned as array, then use list() to extract
         * all these values (or just use extract())
         */

        $this->checkHdlOrig();

        if ($intMaxWidth <= 0 || $intMaxHeight <= 0 || !is_numeric($intMaxWidth) || !is_numeric($intMaxHeight)) {
            throw new \InvalidArgumentException('Invalid arguments. $intWidth or $intHeight must be > 0. width: ' . $intMaxWidth . ' height: ' . $intMaxHeight);
        }

        if (false === $aNewSize = $this->getFactor($intMaxWidth, $intMaxHeight)) {

            return $this->copyOrig();
        }

        $this->createWorkGDResource($aNewSize[0], $aNewSize[1]);

        $fnResize = ($this->bTrueColor) ? 'imagecopyresampled' : 'imagecopyresized';
        d('$fnResize: ' . $fnResize);

        $res = $fnResize($this->hdlWork, $this->hdlOrig, 0, 0, $intX, $intY, $aNewSize[0], $aNewSize[1], $this->aOrigSize[0], $this->aOrigSize[1]);
        d('resized result: ' . $res);

        return $this;
    }


    /**
     * Get width and height of current work GD resource
     *
     * @return array with 2 elements: 0=>width, 1=>height
     */
    protected function getWorkWidthHeight()
    {
        d('getWorkWidthHeight');
        d('$this->hdlWork: ' . gettype($this->hdlWork));

        Validate::type($this->hdlWork, array('resource' => 'gd'));

        $this->aWorkSize[0] = \imagesx($this->hdlWork);
        $this->aWorkSize[1] = \imagesy($this->hdlWork);

        d('$this->aWorkSize: ' . print_r($this->aWorkSize, 1));

        return $this;
    }


    /**
     * Make the $this->hdlWork to be an exact copy
     * of $this->hdlOrig resource
     *
     * @return object $this
     */
    protected function copyOrig()
    {
        d('start cloning orig resource');

        $tmpfname = \tempnam($this->Ini->TEMP_DIR, "imgclone");
        d('$tmpfname: ' . $tmpfname);

        \imagegd2($this->hdlOrig, $tmpfname);
        $this->hdlWork = \imagecreatefromgd2($tmpfname);

        @\unlink($tmpfname);

        return $this;
    }


    /**
     * Calculate new width and height
     * and calculate the resize factor
     * based on maxWidth and maxHeight
     *
     * @param $intMaxWidth
     * @param $intMaxHeight
     *
     * @return mixed false if no resize is necessary
     * or array with 2 values 0 => new_width and 1 => new_height
     *
     * Also sets the $this->factor value
     */
    public function getFactor($intMaxWidth, $intMaxHeight)
    {
        $intCropWidth  = $this->aOrigSize[0];
        $intCropHeight = $this->aOrigSize[1];

        /**
         * Do not resize if orig width
         * and orig height are smaller that
         * what we have to resize to...
         * This means that image is already
         * not larger than desired size
         */

        if ($intCropWidth <= $intMaxWidth && $intCropHeight <= $intMaxHeight) {

            return false;
        }


        $x = $intCropWidth / $intMaxWidth;
        $y = $intCropHeight / $intMaxHeight;

        if ($x > $y) {

            /**
             * If crop by width factor
             * is greater than crop by height factor
             * then we crop by width
             */
            d('cp');
            $new_height   = round(($intMaxWidth / $intCropWidth) * $intCropHeight, 0);
            $new_width    = $intMaxWidth;
            $this->factor = $intCropWidth / $new_width;
        } else {

            /**
             * crop by height
             */
            d('cp');
            $new_width    = round(($intMaxHeight / $intCropHeight) * $intCropWidth, 0);
            $new_height   = $intMaxHeight;
            $this->factor = $intCropHeight / $new_height;
        }

        d('$new_width ' . $new_width . ' $new_height: ' . $new_height . ' $this->factor: ' . $this->factor);

        return array($new_width, $new_height);
    }


    /**
     * Output active image resource to browser
     *
     */
    public function showImage()
    {
        if (empty($this->hdlWork)) {
            throw new \Lampcms\ImageException('Cannot show image because $this->hdlWork is empty');
        }

        switch ( $this->origType ) {
            case 'jpeg':
                $f = 'imagejpeg';
                break;

            case 'png':
                $f = 'imagepng';
                break;

            case 'gif':
                $f = 'imagegif';
                break;
        }

        d('$f is: ' . $f);

        header('Content-type: image/' . $this->origType);
        $f($this->hdlWork);
        $this->__destruct();
        exit();
    }


    /**
     * A small check to make sure
     * $this->hdlOrig gd resource exists
     *
     * @returns object $this
     *
     * @throws \Lampcms\ImageException if $this->hdlOrig
     * does not exist
     */
    protected function checkHdlOrig()
    {
        if (!isset($this->hdlOrig) || !is_resource($this->hdlOrig)) {
            throw new \Lampcms\ImageException('Error: hdlOrig GD resource does not exist');
        }

        return $this;
    }


    /**
     * Always pass $bTrueColor true when creating square image!
     *
     */
    protected function createWorkGDResource($intWidth, $intHeight, $bTrueColor = false)
    {

        $this->hdlWork = $this->createGDResource($intWidth, $intHeight, $bTrueColor = false);

        return $this;
    }


    /**
     * Creates new GD resource
     * of defined intWidth and intHeight
     * takes clues from hdlOrig about what type
     * of GD resource to create and whether or not
     * to set transparency
     */
    protected function createGDResource($intWidth = 0, $intHeight = 0, $bTrueColor = false)
    {
        $this->checkHdlOrig();

        if (!is_numeric($intWidth) || !is_numeric($intHeight)) {
            throw new \Lampcms\ImageException('$intWidth and $intHeight must be numeric. Supplied value were $intWidth: ' . $intWidth . ' $intHeight: ' . $intHeight);
        }

        $intWidth  = ($intWidth > 0) ? $intWidth : $this->aOrigSize[0];
        $intHeight = ($intHeight > 0) ? $intHeight : $this->aOrigSize[1];

        /**
         * @todo
         * Should we check if this->hdlWork
         * already exists?
         * It should not exist because work size canvas is
         * always destroyed after work image
         * is saved
         */

        $this->bTrueColor = (false !== $bTrueColor) ? (bool)$bTrueColor : imageistruecolor($this->hdlOrig);

        d('bTrueColor: ' . $this->bTrueColor);

        if ($this->bTrueColor) {
            $hdlGD = \imagecreatetruecolor($intWidth, $intHeight);

            /**
             * Transparancy thingy
             * Only png can have transparancy and be truecolor
             */
            if ('png' === $this->origType) {

                \imagealphablending($hdlGD, false);

                $color = \imagecolorallocatealpha($hdlGD, 0, 0, 0, 127);

                \imagefill($hdlGD, 0, 0, $color);
                \imagesavealpha($hdlGD, true);

            }
        }

        if (!isset($hdlGD)) {
            d('cp');

            $hdlGD = \imagecreate($intWidth, $intHeight);
            \imagepalettecopy($hdlGD, $this->hdlOrig);
            $color = \imagecolortransparent($this->hdlOrig);
            if ($color != -1) {
                \imagecolortransparent($hdlGD, $color);
                \imagefill($hdlGD, 0, 0, $color);
            }

            d('$hdlGD: ' . gettype($hdlGD));
        }

        return $hdlGD;
    }


    /**
     * Make square size image from original
     * this usually means some pixels will be cut-off
     * from image in each size in case of landscape
     * or from bottom in case of portrait
     *
     * @param int $intWidth the width of the desired
     *                      square result (height is the same)
     *
     * (non-PHPdoc)
     *
     * @see Lampcms\Image.Editor::makeSquare()
     *
     * @return object $this
     */
    public function makeSquare($intWidth)
    {
        $src_x    = 0;
        $intWidth = (int)$intWidth;

        /**
         * Passing the third arg as true
         * will force creation of truecolor gd resource
         * This is necessary otherwise making square thumbs
         * from gif or png may fail!
         */
        $this->createWorkGDResource($intWidth, $intWidth, true);

        list($width_orig, $height_orig) = $this->aOrigSize;
        d('$width_orig: ' . $width_orig . ' $height_orig: ' . $height_orig);

        /**
         * If landscape then shave off
         * some pixels on left and right
         */
        if ($width_orig > $height_orig) {
            d('image is landscape');
            $src_x = \floor(($width_orig - $height_orig) / 2);
            d('$src_x: ' . $src_x);
            $src_w = $src_h = $height_orig;
        } else {
            $src_w = $src_h = $width_orig;
        }

        $res = \imagecopyresampled($this->hdlWork, $this->hdlOrig, 0, 0, $src_x, 0, $intWidth, $intWidth, $src_w, $src_h);

        d('$res: ' . $res);

        return $this;
    }


    /**
     * Draws a border around the current
     * active GD resource image
     *
     * @param int $intBorderWidth width of border to draw
     *
     * @return object $this
     */
    protected function addBorder($intBorderWidth = 1)
    {
        Validate::type($this->hdlWork, array('resource' => 'gd'), __METHOD__);

        if ($intBorderWidth > 1) {
            \imagesetthickness($this->hdlWork, $intBorderWidth);
        }

        $resBorderColor = \imagecolorallocate($this->hdlWork, 0, 0, 0); // default is black color border

        $x2 = \imagesx($this->hdlWork) - 1;
        $y2 = \imagesy($this->hdlWork) - 1;

        $bool1 = \imageline($this->hdlWork, 0, 0, 0, $y2, $resBorderColor);
        $bool2 = \imageline($this->hdlWork, 0, $y2, $x2, $y2, $resBorderColor);
        $bool3 = \imageline($this->hdlWork, $x2, 0, 0, 0, $resBorderColor);
        $bool4 = \imageline($this->hdlWork, $x2, $y2, $x2, 0, $resBorderColor);

        d('imageline results:  ' . $bool1 . ' ' . $bool2 . ' ' . $bool3 . ' ' . $bool4);

        return $this;
    }


    /**
     * Must release orig
     * and work resources
     */
    public function __destruct()
    {
        if (is_resource($this->hdlOrig)) {
            \imagedestroy($this->hdlOrig);
        }

        if (is_resource($this->hdlWork)) {
            \imagedestroy($this->hdlWork);
        }
    }


    /**
     * A way to preserve transparant colors
     * when resizing.
     *
     * This method is not currently used, has to be
     * thoroughly tested first!
     *
     */
    protected function preserveTransparancy()
    {
        if ('png' === $this->origType) {
            \imagealphablending($this->hdlWork, false);

            $colorTransparent = \imagecolorallocatealpha(
                $this->hdlWork,
                255, /*should use default 255*/
                255, /*should use default 255*/
                255, /*should use default 255*/
                0
            );

            imagefill($this->hdlWork, 0, 0, $colorTransparent);
            imagesavealpha($this->hdlWork, true);
        }

        if ('gif' === $this->origType) {
            $colorTransparent = \imagecolorallocate(
                $this->hdlWork,
                0, /*should use default 0*/
                0, /*should use default 0*/
                0 /*should use default 0*/
            );

            \imagecolortransparent($this->hdlWork, $colorTransparent);
            \imagetruecolortopalette($this->hdlWork, true, 256);
        }
    }


    public function __toString()
    {
        return 'object EditorGD';
    }

}
