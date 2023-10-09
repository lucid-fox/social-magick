<?php
/**
 * Social Magick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2023 Lucid Fox
 * @license   GNU GPL v3 or later
 */

/** @noinspection PhpComposerExtensionStubsInspection */

namespace LucidFox\Plugin\System\SocialMagick\Library;

defined('_JEXEC') || die();

use Joomla\CMS\Filesystem\File;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * An image renderer using the GD library
 *
 * @since       1.0.0
 */
class ImageRendererGD extends ImageRendererAbstract implements ImageRendererInterface
{
	/** @inheritDoc */
	public function isSupported(): bool
	{
		// Quick escape route if the GD extension is not loaded / compiled in.
		if (function_exists('extension_loaded') && extension_loaded('gd') !== true)
		{
			return false;
		}

		$functions = [
			'imagecreatetruecolor',
			'imagealphablending',
			'imagecolorallocatealpha',
			'imagefilledrectangle',
			'imagerectangle',
			'imagecopy',
			'imagedestroy',
			'imagesavealpha',
			'imagepng',
			'imagejpeg',
			'getimagesize',
			'imagecreatefromjpeg',
			'imagecreatefrompng',
			'imagecopyresampled',
		];

		return array_reduce($functions, fn($carry, $function) => $carry && function_exists($function), true);
	}

	/** @inheritDoc */
	public function makeImage(string $text, array $template, string $outFile, ?string $extraImage): void
	{
		// Get the template's dimensions
		$templateWidth  = $template['template-w'] ?? 1200;
		$templateHeight = $template['template-h'] ?? 630;

		// Get the base image (resized image file or solid color image)
		if ($template['base-image'])
		{
			// Joomla 4 append infomration to the image after either a question mark OR a hash sign. Let's fix that.
			$baseImage = $template['base-image'];

			$imageInfo = HTMLHelper::_('cleanImageURL', $baseImage);
			$baseImage = $imageInfo->url;

			if (!@file_exists($baseImage))
			{
				$baseImage = JPATH_ROOT . '/' . $baseImage;
			}

			[$image, $baseImageWidth, $baseImageHeight] = $this->loadImageFile($baseImage);
			$image = $this->resizeImage($image, $baseImageWidth, $baseImageHeight, $templateWidth, $templateHeight);
		}
		else
		{
			$opacity         = $template['base-color-alpha'];
			$alpha           = round($opacity * 127);
			$colorProperties = $this->hexToRGBA($template['base-color']);

			$image = imagecreatetruecolor($templateWidth, $templateHeight);

			imagealphablending($image, false);
			$color = imagecolorallocatealpha($image, $colorProperties[0], $colorProperties[1], $colorProperties[2], $alpha);
			imagefilledrectangle($image, 0, 0, $templateWidth, $templateHeight, $color);
			imagealphablending($image, true);
		}

		// Layer an extra image, if necessary
		if (!empty($extraImage) && ($template['use-article-image'] !== '0'))
		{
			$image = $this->layerExtraImage($image, $extraImage, $template);
		}

		// Overlay the text (if necessary)
		$this->renderOverlayText($text, $template, $image);

		// Write out the image file...
		$imageType = $this->getNormalizedExtension($outFile);
		imagesavealpha($image, true);
		@ob_start();

		switch ($imageType)
		{
			case 'png':
				if (function_exists('imagepng'))
				{
					$ret = @imagepng($image, null, min((int) ceil((100 - $this->quality) / 10), 9));
				}

				break;

			case 'gif':
				if (function_exists('imagegif'))
				{
					$ret = @imagegif($image, null);
				}

				break;
			case 'bmp':
				if (function_exists('imagebmp'))
				{
					$ret = @imagebmp($image, null);
				}

				break;
			case 'wbmp':
				if (function_exists('imagewbmp'))
				{
					$ret = @imagewbmp($image, null);
				}

				break;
			case 'jpg':
				if (function_exists('imagejpeg'))
				{
					$ret = @imagejpeg($image, null, $this->quality);
				}

				break;
			case 'xbm':
				if (function_exists('imagexbm'))
				{
					$ret = @imagexbm($image, null);
				}

				break;
			case 'webp':
				if (function_exists('imagewebp'))
				{
					$ret = @imagewebp($image, null, $this->quality);
				}

				break;
		}

		$imageData = @ob_get_clean();

		imagedestroy($image);

		if (!file_put_contents($outFile, $imageData))
		{
			File::write($outFile, $imageData);
		}
	}

	/**
	 * Resize and blend an extra image (if applicable) over/under the provided $image resource
	 *
	 * @param   resource     $image           GD image resource. The extra image is blended over or under it.
	 * @param   string|null  $extraImagePath  Full filesystem path of the image to blend over/under $image.
	 * @param   array        $template        The Social Magick template which defines the blending options.
	 *
	 * @return  resource  The resulting image resource
	 *
	 * @since   1.0.0
	 */
	private function layerExtraImage($image, ?string $extraImagePath, array $template)
	{
		// If we don't have an image, it doesn't exist or is unreadable return the original image unmodified
		if (empty($extraImagePath))
		{
			return $image;
		}

		if (!@file_exists($extraImagePath))
		{
			$extraImagePath = JPATH_ROOT . '/' . $extraImagePath;
		}

		if (!@file_exists($extraImagePath) || !@is_file($extraImagePath) || !@is_readable($extraImagePath))
		{
			return $image;
		}

		// Load the image
		[$tmpImg, $width, $height] = $this->loadImageFile($extraImagePath);


		// Create a transparent canvas
		$templateWidth  = $template['template-w'] ?? 1200;
		$templateHeight = $template['template-h'] ?? 630;
		$extraCanvas    = imagecreatetruecolor($templateWidth, $templateHeight);

		imagealphablending($extraCanvas, false);
		$color = imagecolorallocatealpha($extraCanvas, 255, 255, 255, 127);
		imagefilledrectangle($extraCanvas, 0, 0, $templateWidth, $templateHeight, $color);
		imagealphablending($image, true);

		if ($template['image-cover'] == '1')
		{
			$tmpWidth  = $templateWidth;
			$tmpHeight = $templateHeight;
			$imgX      = 0;
			$imgY      = 0;
		}
		else
		{
			$tmpWidth  = $template['image-width'];
			$tmpHeight = $template['image-height'];
			$imgX      = $template['image-x'];
			$imgY      = $template['image-y'];
		}

		$tmpImg = $this->resizeImage($tmpImg, $width, $height, $tmpWidth, $tmpHeight);

		imagealphablending($extraCanvas, true);
		imagecopy($extraCanvas, $tmpImg, $imgX, $imgY, 0, 0, $tmpWidth, $tmpHeight);
		imagedestroy($tmpImg);

		if ($template['image-z'] == 'under')
		{
			// Copy $image OVER $extraCanvas
			imagealphablending($extraCanvas, true);
			imagecopy($extraCanvas, $image, 0, 0, 0, 0, $templateWidth, $templateHeight);
			imagedestroy($image);

			$image = $extraCanvas;
		}
		else
		{
			// Copy $extraCanvas OVER image
			imagealphablending($image, true);
			imagecopy($image, $extraCanvas, 0, 0, 0, 0, $templateWidth, $templateHeight);
			imagedestroy($extraCanvas);
		}

		return $image;
	}

	/**
	 * Load an image file into a GD image and get its dimensions
	 *
	 * @param   string  $filePath  The fully qualified filesystem path of the file
	 *
	 * @return  array [$image, $width, $height]
	 *
	 * @since   1.0.0
	 */
	private function loadImageFile(string $filePath): array
	{
		// Make suer the file exists and is readable, otherwise pretend getimagesize() failed.
		if (@file_exists($filePath) && @is_file($filePath) && @is_readable($filePath))
		{
			$info = @getimagesize($filePath);
		}
		else
		{
			$info = false;
		}

		// If we can't open or get info for the image we're creating a dummy 320x200 solid black image.
		if ($info === false)
		{
			$width  = 320;
			$height = 200;
			$type   = PHP_INT_MAX;
		}
		else
		{
			[$width, $height, $type,] = $info;
		}

		switch ($type)
		{
			case IMAGETYPE_BMP:
				$image = imagecreatefrombmp($filePath);
				break;

			case IMAGETYPE_GIF:
				$image = imagecreatefromgif($filePath);
				break;

			case IMAGETYPE_JPEG:
				$image = imagecreatefromjpeg($filePath);
				break;

			case IMAGETYPE_PNG:
				$image = imagecreatefrompng($filePath);
				break;

			case IMAGETYPE_WBMP:
				$image = imagecreatefromwbmp($filePath);
				break;

			case IMAGETYPE_XBM:
				$image = imagecreatefromxpm($filePath);
				break;

			case IMAGETYPE_WEBP:
				$image = imagecreatefromwebp($filePath);
				break;

			default:
				$image = imagecreatetruecolor($width, $height);
				$black = imagecolorallocate($image, 0, 0, 0);
				imagefilledrectangle($image, 0, 0, $width, $height, $black);
				break;
		}

		return [$image, $width, $height];
	}

	/**
	 * Resize and crop an image
	 *
	 * @param   resource  $image      The GD image resource.
	 * @param   int       $oldWidth   Original image width, in pixels.
	 * @param   int       $oldHeight  Original image height, in pixels.
	 * @param   int       $newWidth   Required image width, in pixels.
	 * @param   int       $newHeight  Required image height, in pixels.
	 * @param   string    $focus      Crop focus. One of 'northwest', 'center', 'northeast', 'southwest', 'southeast'
	 *
	 * @return  resource
	 *
	 * @since   1.0.0
	 */
	private function resizeImage(&$image, int $oldWidth, int $oldHeight, int $newWidth, int $newHeight, string $focus = 'center')
	{
		if (($oldWidth === $newWidth) && ($oldHeight === $newHeight))
		{
			return $image;
		}

		// Get the resize dimensions
		$resizeWidth  = $newWidth;
		$resizeHeight = $oldHeight * $newWidth / $oldWidth;

		if ($oldWidth > $oldHeight)
		{
			$resizeWidth  = $oldWidth * $newHeight / $oldHeight;
			$resizeHeight = $newHeight;

			if ($resizeWidth < $newWidth)
			{
				$resizeWidth  = $newWidth;
				$resizeHeight = $oldHeight * $newWidth / $oldWidth;
			}
		}

		// Resize the image
		$newImage = imagecreatetruecolor((int) $resizeWidth, (int) $resizeHeight);
		imagealphablending($newImage, false);
		$transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
		imagefilledrectangle($newImage, 0, 0, (int) $resizeWidth, (int) $resizeHeight, (int) $transparent);

		imagecopyresampled($newImage, $image, 0, 0, 0, 0, (int) $resizeWidth, (int) $resizeHeight, $oldWidth, $oldHeight);
		imagedestroy($image);
		$image = $newImage;
		unset($newImage);

		// Crop the image
		$newImage = imagecreatetruecolor((int) $resizeWidth, (int) $resizeHeight);
		imagealphablending($newImage, false);
		$transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
		imagefilledrectangle($newImage, 0, 0, (int) $resizeWidth, (int) $resizeHeight, $transparent);

		switch ($focus)
		{
			case 'northwest':
				imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $newWidth, $newHeight);
				break;

			default:
			case 'center':
				imagecopyresampled($newImage, $image, 0, 0, (int) (abs($resizeWidth - $newWidth) / 2), (int) (abs($resizeHeight - $newHeight) / 2), $newWidth, $newHeight, $newWidth, $newHeight);
				break;

			case 'northeast':
				imagecopyresampled($newImage, $image, 0, 0, (int) abs($resizeWidth - $newWidth), 0, $newWidth, $newHeight, $newWidth, $newHeight);
				break;

			case 'southwest':
				imagecopyresampled($newImage, $image, 0, 0, 0, (int) abs($resizeHeight - $newHeight), $newWidth, $newHeight, $newWidth, $newHeight);
				break;

			case 'southeast':
				imagecopyresampled($newImage, $image, 0, 0, (int) abs($resizeWidth - $newWidth), (int) abs($resizeHeight - $newHeight), $newWidth, $newHeight, $newWidth, $newHeight);
				break;
		}

		imagedestroy($image);
		$image = $newImage;
		unset($newImage);

		// Sharpen the resized and cropped image. Necessary since GD doesn't do Lanczos resampling :(
		$intSharpness = $this->findSharp($oldWidth, $newWidth);

		$arrMatrix = [
			[
				-1,
				-2,
				-1,
			],
			[
				-2,
				$intSharpness + 12,
				-2,
			],
			[
				-1,
				-2,
				-1,
			],
		];

		imageconvolution($image, $arrMatrix, $intSharpness, 0);

		return $image;
	}

	/**
	 * Overlay the text on the image.
	 *
	 * @param   string    $text      The text to render.
	 * @param   array     $template  The OpenGraph image template definition.
	 * @param   resource  $image     The GD image resource to overlay the text.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function renderOverlayText(string $text, array $template, &$image): void
	{
		// Make sure we are told to overlay text
		if (($template['overlay_text'] ?? 1) != 1)
		{
			return;
		}

		// Get template parameters I'll be using later
		$templateWidth  = $template['template-w'] ?? 1200;
		$templateHeight = $template['template-h'] ?? 630;

		// Pre-render the text
		$fontSize = ((abs($template['font-size']) >= 1) ? abs($template['font-size']) : 24) * 0.755;
		$fontPath = $this->normalizeFont($template['text-font']);
		[
			$textImage, $textImageWidth, $textImageHeight,
		] = $this->renderText($text, $template['text-color'], $template['text-align'], $fontPath, $fontSize, $template['text-width'], $template['text-height'], $template['text-y-center'] == 1, 1.35);
		$centerVertically   = $template['text-y-center'] == 1;
		$verticalOffset     = $centerVertically ? $template['text-y-adjust'] : $template['text-y-absolute'];
		$centerHorizontally = $template['text-x-center'] == 1;
		$horizontalOffset   = $centerHorizontally ? $template['text-x-adjust'] : $template['text-x-absolute'];

		[
			$textOffsetX, $textOffsetY,
		] = $this->getTextRenderOffsets($templateWidth, $templateHeight, $textImageWidth, $textImageHeight, $centerVertically, $verticalOffset, $centerHorizontally, $horizontalOffset);

		// Render text
		imagealphablending($image, true);
		imagealphablending($textImage, true);
		imagecopy($image, $textImage, $textOffsetX, $textOffsetY, 0, 0, $textImageWidth + 100, $textImageHeight + 100);
		imagedestroy($textImage);
	}

	/**
	 * Render text as a transparent image that's 50px oversized in every dimension.
	 *
	 * @param   string  $text         The text to render.
	 * @param   string  $color        The hex color to render it in.
	 * @param   string  $alignment    Horizontal alignment: 'left', 'center', 'right'.
	 * @param   string  $font         The font file to render it with.
	 * @param   float   $fontSize     The font size, in points.
	 * @param   int     $maxWidth     Maximum text render width, in pixels.
	 * @param   int     $maxHeight    Maximum text render height, in pixels.
	 * @param   bool    $centerTextVertically
	 * @param   float   $lineSpacing  Line spacing factor. 1.35 is what Imagick uses by default as far as I can tell.
	 *
	 * @return  array  [$image, $textWidth, $textHeight]  The width and height include the 50px margin on all sides
	 *
	 * @since   1.0.0
	 */
	private function renderText(string $text, string $color, string $alignment, string $font, float $fontSize, int $maxWidth, int $maxHeight, bool $centerTextVertically, float $lineSpacing = 1.35)
	{
		// Pre-process text
		$text = $this->preProcessText($text);

		// Get the color
		$colorValues = $this->hexToRGBA($color);

		// Quick escape route: if the rendered string length is smaller than the maximum width
		$lines = $this->toLines($text, $fontSize, $font, $maxWidth);

		// Apply the line spacing
		$lines = $this->applyLineSpacing($lines, $lineSpacing);

		// Cut off the lines which would get us over the maximum height
		$lineCountBeforeMaxHeight = count($lines);
		$lines                    = $this->applyMaximumHeight($lines, $maxHeight);
		$lineCountAfterMaxHeight  = count($lines);

		// Add ellipses to the last line if the text didn't fit.
		if ($lineCountAfterMaxHeight < $lineCountBeforeMaxHeight)
		{
			$lastLine = array_pop($lines);

			// Try adding ellipses to the last line
			$testText       = $lastLine['text'] . '…';
			$testDimensions = $this->lineSize($testText, $fontSize, $font);

			/**
			 * If the last line is too big to fit remove the ellipses, the last word and space and re-add the ellipses,
			 * as long as there are more than one words.
			 */
			if ($testDimensions[0] > $maxWidth)
			{
				$words = explode(' ', $lastLine['text']);

				if (count($words) > 1)
				{
					array_pop($words);
					$lastLine['text'] = implode(' ', $words) . '…';
				}

				$lastLine['text'] = trim($lastLine['text']);
				$testDimensions   = $this->lineSize($lastLine['text'], $fontSize, $font);
			}

			$lastLine['width']  = $testDimensions[0];
			$lastLine['height'] = $testDimensions[1];

			$lines[] = $lastLine;
		}

		// Align the lines horizontally
		$lines = $this->horizontalAlignLines($lines, $alignment, $maxWidth);

		// Get the real width and height of the text
		$textWidth  = array_reduce($lines, fn(int $carry, array $line) => max($carry, $line['width']), 0);
		$textHeight = array_reduce($lines, fn(int $carry, array $line) => max($carry, $line['y'] + $line['height']), 0);

		// Create a transparent image with the text dimensions
		$image = imagecreatetruecolor($maxWidth + 100, $maxHeight + 100);
		imagealphablending($image, false);

		if (!$this->debugText)
		{
			$transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
			imagefilledrectangle($image, 0, 0, $maxWidth + 100, $maxHeight + 100, $transparent);
		}
		else
		{
			$transparent = imagecolorallocatealpha($image, 255, 0, 0, 90);
			imagefilledrectangle($image, 0, 0, 50, $maxHeight + 100, $transparent);
			imagefilledrectangle($image, $maxWidth + 50, 0, $maxWidth + 100, $maxHeight + 100, $transparent);
			imagefilledrectangle($image, 50, 0, $maxWidth + 50, 50, $transparent);
			imagefilledrectangle($image, 50, $maxHeight + 50, $maxWidth + 50, $maxHeight + 100, $transparent);

			$yellow = imagecolorallocatealpha($image, 255, 255, 0, 80);
			imagefilledrectangle($image, 50, 50, $maxWidth + 50, $maxHeight + 50, $yellow);

			$purple = imagecolorallocate($image, 255, 0, 255);
			imagerectangle($image, 0, 0, $maxWidth + 99, $maxHeight + 99, $purple);
		}

		imagealphablending($image, true);

		// Render the text on the transparent image
		$colorResource = imagecolorallocate($image, $colorValues[0], $colorValues[1], $colorValues[2]);

		// Get the y offset because GD is doing weird things
		$boundingBox   = imagettfbbox($fontSize, 0, $font, $lines[0]['text']);
		$yOffset       = -$boundingBox[7] + 1;
		$centerYOffset = 0;

		// At this point the text would be anchored to the top of the text box. We want it centred in the box.
		if ($centerTextVertically)
		{
			$centerYOffset = (int) ceil(($maxHeight - $textHeight) / 2.0);
		}

		foreach ($lines as $line)
		{
			$x1 = 50 + $line['x'];
			$y1 = 50 + $line['y'] + $centerYOffset;

			imagettftext($image, $fontSize, 0, (int) $x1, (int) $y1 + (int) $yOffset, $colorResource, $font, $line['text']);

			if ($this->debugText)
			{
				imagerectangle($image, $x1, $y1, $x1 + $line['width'], $y1 + $line['height'], $purple);
			}
		}

		return [$image, $maxWidth, $maxHeight];
	}

	/**
	 * Returns the rendering offsets for the text image over a base image.
	 *
	 * @param   int   $baseImageWidth      Base image width, in pixels.
	 * @param   int   $baseImageHeight     Base image height, in pixels.
	 * @param   int   $textImageWidth      Text image width, in pixels. This includes the 50px padding in either side.
	 * @param   int   $textImageHeight     Text image height, in pixels. This includes the 50px padding in either side.
	 * @param   bool  $centerVertically    Should I center the text vertically over the base image?
	 * @param   int   $verticalOffset      Offset in the vertical direction. Positive moves text down, negative moves
	 *                                     text up.
	 * @param   bool  $centerHorizontally  Should I center the text horizontally over the base image?
	 * @param   int   $horizontalOffset    Offset in the horizontal direction. Positive moves text right, negative
	 *                                     moves text left.
	 *
	 * @return  int[] Returns [x, y] where the text image should be rendered over the base image
	 *
	 * @since   1.0.0
	 */
	private function getTextRenderOffsets(int $baseImageWidth, int $baseImageHeight, int $textImageWidth, int $textImageHeight, bool $centerVertically = false, int $verticalOffset = 0, bool $centerHorizontally = false, int $horizontalOffset = 0): array
	{
		// Remember that our text image has 50px of margin on all sides? We need to subtract it.
		$realTextWidth  = $textImageWidth - 100;
		$realTextHeight = $textImageHeight - 100;

		// Start at the top left
		$x = 0;
		$y = 0;

		// If centering vertically we need to calculate a different starting Y coordinate
		if ($centerVertically)
		{
			// The -50 at the end is removing half of our 100px margin
			$y = (int) (($baseImageHeight - $realTextHeight) / 2) - 50;
		}

		// Apply any vertical offset
		$y += $verticalOffset;

		// If centering horizontally we need to calculate a different starting X coordinate
		if ($centerHorizontally)
		{
			// The -50 at the end is removing half of our 100px margin
			$x = (int) (($baseImageWidth - $realTextWidth) / 2) - 50;
		}

		// Apply any horizontal offset
		$x += $horizontalOffset;

		// Remember the 50px margin? We need to subtract it (yes, it may take us to negative dimensions, this is normal)
		$x -= 50;
		$y -= 50;

		return [$x, $y];
	}

	/**
	 * Sharpen images function.
	 *
	 * @param   int  $intOrig
	 * @param   int  $intFinal
	 *
	 * @return  int
	 * @since   1.0.0
	 *
	 * @see     https://github.com/MattWilcox/Adaptive-Images/blob/master/adaptive-images.php#L109
	 */
	private function findSharp(int $intOrig, int $intFinal): int
	{
		$intFinal = $intFinal * (750.0 / $intOrig);
		$intA     = 52;
		$intB     = -0.27810650887573124;
		$intC     = .00047337278106508946;
		$intRes   = $intA + $intB * $intFinal + $intC * $intFinal * $intFinal;

		return max(round($intRes), 0);
	}

	/**
	 * Returns the width and height of a line of text
	 *
	 * @param   string  $text  The text to render
	 * @param   float   $size  Font size, in points
	 * @param   string  $font  Font file
	 *
	 * @return  array  [width, height]
	 *
	 * @since   1.0.0
	 */
	private function lineSize(string $text, float $size, string $font): array
	{
		$boundingBox = imagettfbbox($size, 0, $font, $text);

		return [
			$boundingBox[2] - $boundingBox[0],
			$boundingBox[1] - $boundingBox[7],
		];

	}

	/**
	 * Chop the string to lines which are rendered up to a given maximum width
	 *
	 * @param   string  $text      The text to chop
	 * @param   float   $size      Font size, in points
	 * @param   string  $font      Font file
	 * @param   int     $maxWidth  Maximum width for the rendered text, in pixels
	 *
	 * @return  array[] The individual lines along with their width and height metrics
	 *
	 * @since   1.0.0
	 */
	private function toLines(string $text, float $size, string $font, int $maxWidth): array
	{
		// Is the line narrow enough to call it a day?
		$lineDimensions = $this->lineSize($text, $size, $font);

		if ($lineDimensions[0] < $maxWidth)
		{
			return [
				[
					'text'   => $text,
					'width'  => $lineDimensions[0],
					'height' => $lineDimensions[1],
				],
			];
		}

		// Too wide. We'll walk one word at a time to construct individual lines.
		$words             = explode(' ', $text);
		$lines             = [];
		$currentLine       = '';
		$currentDimensions = [0, 0];

		while (!empty($words))
		{
			$nextWord       = array_shift($words);
			$testLine       = $currentLine . ($currentLine ? ' ' : '') . $nextWord;
			$testDimensions = $this->lineSize($testLine, $size, $font);
			$isOversize     = $testDimensions[0] > $maxWidth;

			// Oversize word. Can't do much, your layout will suffer. I won't be doing hyphenation here!
			if ($isOversize && ($currentDimensions[0] === 0))
			{
				$lines[]           = [
					'text'   => $testLine,
					'width'  => $testDimensions[0],
					'height' => $testDimensions[1],
				];
				$currentLine       = '';
				$currentDimensions = [0, 0];
			}
			// We exceeded the maximum width. Let's commit the previous line and push back the current word to the array
			elseif ($isOversize)
			{
				$lines[]           = [
					'text'   => $currentLine,
					'width'  => $currentDimensions[0],
					'height' => $currentDimensions[1],
				];
				$currentLine       = '';
				$currentDimensions = [0, 0];

				array_unshift($words, $nextWord);
			}
			// We have not reached the limit just yet.
			else
			{
				$currentLine       = $testLine;
				$currentDimensions = $testDimensions;
			}
		}

		if (!empty($currentLine))
		{
			$lines[] = [
				'text'   => $currentLine,
				'width'  => $currentDimensions[0],
				'height' => $currentDimensions[1],
			];
		}

		return $lines;
	}

	/**
	 * Apply the horizontal alignment for the given lines.
	 *
	 * Sets the `x` element of each line accordingly.
	 *
	 * @param   array   $lines      Text lines definitions to align horizontally.
	 * @param   string  $alignment  Horizontal alignment: 'left', 'center', or 'right'.
	 * @param   int     $maxWidth   Maximum rendered image width, in pixels
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function horizontalAlignLines(array $lines, string $alignment, int $maxWidth): array
	{
		return array_map(function (array $line) use ($alignment, $maxWidth): array {
			switch ($alignment)
			{
				case 'left':
					$line['x'] = 0;
					break;

				case 'center':
					$line['x'] = ($maxWidth - $line['width']) / 2.0;
					break;

				case 'right':
					$line['x'] = $maxWidth - $line['width'];
					break;
			}

			return $line;
		}, $lines);
	}

	/**
	 * Apply a line spacing factor.
	 *
	 * All lines will have a height equal to the highest lines times $lineSpacing. This method sets the `y` element of
	 * each line accordingly.
	 *
	 * @param   array  $lines        Text lines definitions to apply line spacing to.
	 * @param   float  $lineSpacing  The line spacing factor, e.g. 1.05 for 5% whitespace between lines
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function applyLineSpacing(array $lines, float $lineSpacing): array
	{
		// Get the maximum line height
		$maxHeight = array_reduce($lines, fn(int $carry, array $line): int => max($carry, $line['height']), 0);

		$lineHeight = (int) ceil($maxHeight * $lineSpacing);
		$i          = -1;

		return array_map(function (array $line) use ($lineHeight, &$i) {
			$i++;
			$line['y'] = $i * $lineHeight;

			return $line;
		}, $lines);
	}

	/**
	 * Strips off any lines which would cause the text to exceed the maximum permissible image height.
	 *
	 * @param   array  $lines      The line definitions.
	 * @param   int    $maxHeight  The maximum permissible image height, in pixels.
	 *
	 * @return  array  The remaining lines
	 *
	 * @since   1.0.0
	 */
	private function applyMaximumHeight(array $lines, int $maxHeight): array
	{
		return array_filter($lines, fn(array $line): bool => ($line['y'] + $line['height']) <= $maxHeight);
	}
}