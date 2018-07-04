<?php
/**
 * @package PLG_JSHOPPINGPRODUCTS_watermark
 * @author BoxApp Studio <info@boxapp.net>
 * @copyright Copyright © BoxApp
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @version 3.0.1
 */

defined('_JEXEC') or die;

class plgjshoppingproductsWatermark extends JPlugin
{
    public function __construct(&$subject, $config = array())
    {
        parent::__construct($subject, $config);
        $this->loadLanguage();
    }

    public function onAfterSaveProductFolerImage($productId, $nameFull, $nameImage, $nameThumb)
    {
        $addWatermarkWhenSelectImageFromExisting = (bool) $this->params->get('addWatermarkWhenSelectImageFromExisting', false);
        if($addWatermarkWhenSelectImageFromExisting) {
            $this->addWatermark($nameFull, $nameImage, $nameThumb);
        }
    }

    public function onAfterSaveProductImage($productId, $nameImage)
    {
        $nameFull = 'full_' . $nameImage;
        $nameThumb = 'thumb_' . $nameImage;

        $this->addWatermark($nameFull, $nameImage, $nameThumb);
    }

    protected function addWatermark($nameFull, $nameImage, $nameThumb)
    {
        $app = JFactory::getApplication();
        $jshopConfig = JSFactory::getConfig();

        $pathFull = $nameFull ? $jshopConfig->image_product_path . '/' . $nameFull : '';
        $pathImage = $nameImage ? $jshopConfig->image_product_path . '/' . $nameImage : '';
        $pathThumb = $nameThumb ? $jshopConfig->image_product_path . '/' . $nameThumb : '';

        $pattern = $this->params->get('patternPath', '');
        if (empty($pattern)) {
            $app->enqueueMessage(sprintf(JText::_('PLG_JSHOPPINGPRODUCTS_WATERMARK_PATTERN_PATH_EMPTY'), JText::_('PLG_JSHOPPINGPRODUCTS_WATERMARK')), 'warning');
            return false;
        }

        $patternPath = JPATH_SITE . '/' . $pattern;
        if (!file_exists($patternPath)) {
            $app->enqueueMessage(JText::_('PLG_JSHOPPINGPRODUCTS_WATERMARK_PATTERN_FILE_NOT_FOUND'), 'warning');
            return false;
        }

        $horizontalAlignment = $this->params->get('horizontalAlignment', 'right');
        $verticalAlignment = $this->params->get('verticalAlignment', 'bottom');

        $destX = intval($this->params->get('destX', 0));
        $destY = intval($this->params->get('destY', 0));
        $coordinateUnits = $this->params->get('coordinateUnits', 'pixel');

        $scaleWatermarkImage = (bool) $this->params->get('scaleWatermarkImage', false);
        $baseImageForScaleWatermark = (string) $this->params->get('baseImageForScaleWatermark', 'base');
        $scaleFull = null;
        $scaleImage = null;
        $scaleThumb = null;
        if($scaleWatermarkImage) {
            list($widthFull, $heightFull) = getimagesize($pathFull);
            list($widthImage, $heightImage) = getimagesize($pathImage);
            list($widthThumb, $heightThumb) = getimagesize($pathThumb);

            switch ($baseImageForScaleWatermark) {
                case 'full' :
                    $scaleFull = 1;
                    $scaleImage = $widthImage / $widthFull;
                    $scaleThumb = $widthThumb / $widthFull;
                    break;
                case 'thumb' :
                    $scaleFull = $widthFull / $widthThumb;
                    $scaleImage = $widthImage / $widthThumb;
                    $scaleThumb = 1;
                    break;
                case 'base' :
                default :
                    $scaleFull = $widthFull / $widthImage;
                    $scaleImage = 1;
                    $scaleThumb = $widthThumb / $widthImage;
            }
        }

        $addToFullImage = $this->params->get('addToFullImage', null);
        if (file_exists($pathFull) && $addToFullImage) {
            try {
                $this->imageWatermark($pathFull, $patternPath, $horizontalAlignment, $verticalAlignment, $destX, $destY, $coordinateUnits, $scaleFull);
            } catch (Exception $e) {
                $app->enqueueMessage($e->getMessage(), 'warning');
            }
        }

        $addToBaseImage = $this->params->get('addToBaseImage', null);
        if (file_exists($pathImage) && $addToBaseImage) {
            try {
                $this->imageWatermark($pathImage, $patternPath, $horizontalAlignment, $verticalAlignment, $destX, $destY, $coordinateUnits, $scaleImage);
            } catch (Exception $e) {
                $app->enqueueMessage($e->getMessage(), 'warning');
            }
        }

        $addToThumbImage = $this->params->get('addToThumbImage', null);
        if (file_exists($pathImage) && $addToThumbImage) {
            try {
                $this->imageWatermark($pathThumb, $patternPath, $horizontalAlignment, $verticalAlignment, $destX, $destY, $coordinateUnits, $scaleThumb);
            } catch (Exception $e) {
                $app->enqueueMessage($e->getMessage(), 'warning');
            }
        }
    }

    protected function imageWatermark($image, $watermark, $horizontalAlignment = 'right', $verticalAlignment = 'bottom', $destX = 0, $destY = 0, $coordinateUnits = 'pixel', $scale = null, $saveFileName = null, $qty = 100)
    {
        if (!$saveFileName) {
            $saveFileName = $image;
        }

        if (!file_exists($saveFileName)) {
            throw new Exception(sprintf(JText::_('Image save file not found on the path "%s"'), $saveFileName));
        }

        $watermark = $this->getImageResource($watermark);
        $watermarkWidth = $originalWatermarkWidth = imagesx($watermark);
        $watermarkHeight = $originalWatermarkHeight = imagesy($watermark);

        $image = $this->getImageResource($image);
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        if(!is_null($scale)) {
            $scale = floatval($scale);
            $watermarkWidth = intval($originalWatermarkWidth * $scale);
            $watermarkHeight = intval($originalWatermarkHeight * $scale);
        }

        switch ($horizontalAlignment) {
            case 'left' :
                $destX = $this->getLengthValue($destX, $coordinateUnits, $imageWidth);
                break;
            case 'middle' :
                $destX = ($imageWidth - $watermarkWidth) / 2 + $this->getLengthValue($destX, $coordinateUnits, $imageWidth);
                break;
            case 'right' :
            default :
                $destX = $imageWidth - $watermarkWidth - $this->getLengthValue($destX, $coordinateUnits, $imageWidth);
        }

        switch ($verticalAlignment) {
            case 'top' :
                $destY = $this->getLengthValue($destY, $coordinateUnits, $imageHeight);
                break;
            case 'middle' :
                $destY = ($imageHeight - $watermarkHeight) / 2 + $this->getLengthValue($destY, $coordinateUnits, $imageHeight);
                break;
            case 'bottom' :
            default :
                $destY = $imageHeight - $watermarkHeight - $this->getLengthValue($destY, $coordinateUnits, $imageHeight);
        }

        imagesavealpha($image, true);
        imagecopyresampled($image, $watermark, $destX, $destY, 0, 0, $watermarkWidth, $watermarkHeight, $originalWatermarkWidth, $originalWatermarkHeight);

        $this->createImageFromResource($image, $saveFileName, $qty);

        imagedestroy($image);
        imagedestroy($watermark);
        return true;
    }

    /**
     * @param $value
     * @param $coordinateUnits
     * @param $length
     * @return float|int
     */
    protected function getLengthValue($value, $coordinateUnits, $length)
    {
        switch ($coordinateUnits) {
            case 'percent' :
                $value = intval($value);
                if ($value < 0 || $value > 100) {
                    return 0;
                }
                return $length / 100 * $value;
            case 'pixel' :
            default :
                return intval($value);
        }
    }

    /**
     * @param $imageResource
     * @param $saveFileName
     * @param int $quality
     * @return bool|null
     * @throws Exception
     */
    protected function createImageFromResource($imageResource,  $saveFileName, $quality = 100)
    {
        $savePathInfo = (object) pathinfo($saveFileName);

        if (!file_exists($savePathInfo->dirname)) {
            throw new Exception(sprintf(JText::_('Save image folder not found on the path "%s"'), $savePathInfo->dirname));
        }

        $result = null;
        switch ($savePathInfo->extension) {
            case 'png' :
                $result = imagepng($imageResource, $saveFileName);
                break;
            case 'gif' :
                $result = imagegif($imageResource, $saveFileName);
                break;
            case 'jpg' :
            case 'jpeg' :
            default :
                $result = imagejpeg($imageResource, $saveFileName, $quality);
        }
        return $result;
    }

    /**
     * @param $image string
     * @return null|resource
     * @throws Exception
     */
    protected function getImageResource($image)
    {
        $image = (string) $image;
        if (!file_exists($image)) {
            throw new Exception(sprintf(JText::_('Image file not found on the path "%s"'), $image));
        }

        $imageObject = null;
        $imagePathInfo = pathinfo($image);
        $ext = $imagePathInfo['extension'];

        if (!in_array($ext, array('jpg', 'jpeg', 'gif', 'png'))) {
            throw new Exception(JText::_('Image file must be *.jpg, *.gif or *.png'));
        }

        switch ($ext) {
            case 'jpg' :
            case 'jpeg' :
                $imageObject = imagecreatefromjpeg($image);
                break;
            case 'gif' :
                $imageObject = imagecreatefromgif($image);
                break;
            case 'png' :
                $imageObject = imagecreatefrompng($image);
                break;
        }

        return $imageObject;
    }
}