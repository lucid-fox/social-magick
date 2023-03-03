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

use Imagick;
use ImagickDraw;
use ImagickPixel;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\HTML\HTMLHelper;

defined('_JEXEC') || die();

class ImageRendererImagick extends ImageRendererAbstract implements ImageRendererInterface
{
	public function makeImage(string $text, array $template, string $outFile, ?string $extraImage): void
	{
		/**
		 * ***** !!! WARNING !!! ***** !!! DO NOT REMOVE THIS LINE !!!! *****
		 *
		 * There is a really weird issue with Joomla 4 (does not happen in Joomla 3). This code:
		 * $foo = new Imagick();
		 * $foo->destroy();
		 * set_time_limit(30);
		 * causes an immediate timeout to trigger **even if** the wall clock time elapsed is under one second.
		 *
		 * Joomla calls set_time_limit() in its filesystem functions. So, any attempt to write the generated image file
		 * on a site using the FTP layer would result in an inexplicable error about the time limit being exceeded even
		 * when it doesn't happen.
		 *
		 * Even setting the time limits to ludicrous values, like 900000 (over a day!), triggers this weird bug.
		 *
		 * The only thing that works is setting a zero time limit.
		 *
		 * This is definitely a weird Joomla 4 issue which I am strongly disinclined to debug. I am just going to go
		 * through with this unholy, dirty trick and call it a day.
		 */
		$this->setTimeLimit(0);

		// Get the template's dimensions
		$templateWidth  = $template['template-w'] ?? 1200;
		$templateHeight = $template['template-h'] ?? 630;

		// Setup the base image upon which we will superimpose the layered image (if any) and the text
		$image = new Imagick();

		if ($template['base-image'])
		{
			// So, Joomla 4 adds some crap to the image. Let's fix that.
			$baseImage = $template['base-image'];

			$imageInfo = HTMLHelper::_('cleanImageURL', $baseImage);
			$baseImage = $imageInfo->url;

			if (!@file_exists($baseImage))
			{
				$baseImage = JPATH_ROOT . '/' . $baseImage;
			}

			$image = $this->resize($baseImage, $templateWidth, $templateHeight);
		}
		else
		{
			/* New image */
			$opacity = $template['base-color-alpha'];
			$alpha   = round($opacity * 255);
			$hex     = substr(base_convert(($alpha + 0x10000), 10, 16), -2, 2);
			$pixel   = new ImagickPixel($template['base-color'] . $hex);

			$image->newImage($templateWidth, $templateHeight, $pixel);

			$pixel->destroy();
		}

		// Add extra image
		if ($template['use-article-image'] != '0' && $extraImage)
		{
			$extraCanvas      = new Imagick();
			$transparentPixel = new ImagickPixel('transparent');
			$extraCanvas->newImage($templateWidth, $templateHeight, $transparentPixel);
			$transparentPixel->destroy();

			if ($template['image-cover'] == '1')
			{
				$tmpImg = $this->resize($extraImage, $templateWidth, $templateHeight);
				$imgX   = 0;
				$imgY   = 0;
				$extraCanvas->compositeImage(
					$tmpImg,
					Imagick::COMPOSITE_OVER,
					(int) $imgX,
					(int) $imgY
				);
			}
			else
			{
				$tmpImg = $this->resize($extraImage, $template['image-width'], $template['image-height']);
				$imgX   = $template['image-x'];
				$imgY   = $template['image-y'];
				$extraCanvas->compositeImage(
					$tmpImg,
					Imagick::COMPOSITE_DEFAULT,
					0,
					0);
			}

			if ($template['image-z'] == 'under')
			{
				$extraCanvas->compositeImage(
					$image,
					Imagick::COMPOSITE_OVER,
					-((int) $imgX),
					-((int) $imgY));
				$image->compositeImage(
					$extraCanvas,
					Imagick::COMPOSITE_COPY,
					(int) $imgX,
					(int) $imgY);

			}
			elseif ($template['image-z'] == 'over')
			{
				$image->compositeImage(
					$extraCanvas,
					Imagick::COMPOSITE_DEFAULT,
					(int) $imgX,
					(int) $imgY);
			}

			$extraCanvas->destroy();
		}

		// Overlay the text (if necessary)
		$this->renderOverlayText($text, $template, $image);

		// Write the image
		$imageFormat = $this->getNormalizedExtension($outFile);
		$image->setImageFormat($imageFormat);

		switch ($imageFormat)
		{
			case 'jpg':
				$image->setCompressionQuality($this->quality);
				$image->setImageCompression(Imagick::COMPRESSION_JPEG);
				break;

			case 'png':
				$image->setImageCompressionQuality(100 - $this->quality);
				break;
		}

		if (!file_put_contents($outFile, $image))
		{
			File::write($outFile, $image);
		}

		$image->destroy();
	}

	public function isSupported(): bool
	{
		// Quick escape route if the Imagick extension is not loaded / compiled in.
		if (function_exists('extension_loaded') && extension_loaded('imagick') !== true)
		{
			return false;
		}

		// Make sure the Imagick and ImagickPixel classes are not disabled.
		return class_exists('Imagick') && class_exists('ImagickPixel');
	}

	/**
	 * Resize and crop an image
	 *
	 * @param   string   $src    The path to the original image.
	 * @param   numeric  $new_w  New width, in pixels.
	 * @param   numeric  $new_h  New height, in pixels.
	 * @param   string   $focus  Focus of the image; default is center.
	 *
	 * @return  Imagick
	 *
	 * @throws \ImagickException
	 *
	 * @since   1.0.0
	 */
	private function resize(string $src, $new_w, $new_h, string $focus = 'center'): Imagick
	{
		$image = new Imagick($src);

		$w = $image->getImageWidth();
		$h = $image->getImageHeight();

		$resize_w = $new_w;
		$resize_h = $h * $new_w / $w;

		if ($w > $h)
		{
			$resize_w = $w * $new_h / $h;
			$resize_h = $new_h;

			if ($resize_w < $new_w)
			{
				$resize_w = $new_w;
				$resize_h = $h * $new_w / $w;
			}
		}

		$image->resizeImage((int) $resize_w, (int) $resize_h, Imagick::FILTER_LANCZOS, 0.9);

		switch ($focus)
		{
			case 'northwest':
				$image->cropImage((int) $new_w, (int) $new_h, 0, 0);
				break;

			default:
			case 'center':
				$image->cropImage((int) $new_w, (int) $new_h, (int) (($resize_w - $new_w) / 2), (int) (($resize_h - $new_h) / 2));
				break;

			case 'northeast':
				$image->cropImage((int) $new_w, (int) $new_h, (int) ($resize_w - $new_w), 0);
				break;

			case 'southwest':
				$image->cropImage((int) $new_w, (int) $new_h, 0, (int) ($resize_h - $new_h));
				break;

			case 'southeast':
				$image->cropImage((int) $new_w, (int) $new_h, (int) ($resize_w - $new_w), (int) ($resize_h - $new_h));
				break;
		}

		return $image;
	}

	/**
	 * Overlay the text on the image.
	 *
	 * @param   string   $text      The text to render.
	 * @param   array    $template  The OpenGraph image template definition.
	 * @param   Imagick  $image     The image to overlay the text.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function renderOverlayText(string $text, array $template, Imagick &$image): void
	{
		// Make sure we are told to overlay text
		if (($template['overlay_text'] ?? 1) != 1)
		{
			return;
		}

		// Normalize text
		$text = $this->preProcessText($text, false);

		// Set up the text
		$theText = new Imagick();
		$theText->setBackgroundColor('transparent');

		/* Font properties */
		$theText->setFont($this->normalizeFont($template['text-font']));

		if ($template['font-size'] > 0)
		{
			$theText->setPointSize($template['font-size']);
		}

		/* Create text */
		switch ($template['text-align'])
		{
			default:
			case 'center':
				$theText->setGravity(Imagick::GRAVITY_CENTER);
				break;

			case 'left':
				$theText->setGravity(Imagick::GRAVITY_WEST);
				break;

			case 'right':
				$theText->setGravity(Imagick::GRAVITY_EAST);
				break;
		}

		// Create a `caption:` pseudo image that only manages text.
		$theText->newPseudoImage($template['text-width'],
			$template['text-height'],
			'caption:' . $text);
		$theText->setBackgroundColor('transparent');

		// Remove extra height.
		$theText->trimImage(0.0);

		// Set text color
		$clut           = new Imagick();
		$textColorPixel = new ImagickPixel($template['text-color']);
		$clut->newImage(1, 1, $textColorPixel);
		$textColorPixel->destroy();
		$theText->clutImage($clut, 7);
		$clut->destroy();

		// Figure out text vertical position
		$yPos = $template['text-y-absolute'];

		if ($template['text-y-center'] == '1')
		{
			$yPos = ($image->getImageHeight() - $theText->getImageHeight()) / 2.0 + $template['text-y-adjust'];
		}

		// Figure out text horizontal position
		$xPos = $template['text-x-absolute'];

		if ($template['text-x-center'] == '1')
		{
			$xPos = ($image->getImageWidth() - $theText->getImageWidth()) / 2.0 + $template['text-x-adjust'];
		}

		if ($this->debugText)
		{
			$debugW = $theText->getImageWidth();
			$debugH = $theText->getImageHeight();

			$draw        = new ImagickDraw();
			$strokeColor = new ImagickPixel('#ff00ff');
			$fillColor   = new ImagickPixel('#ffff0050');
			$draw->setStrokeColor($strokeColor);
			$draw->setFillColor($fillColor);
			$draw->setStrokeOpacity(1);
			$draw->setStrokeWidth(2);
			$draw->rectangle(1, 1, $debugW - 1, $debugH - 1);

			$debugImage       = new Imagick();
			$transparentPixel = new ImagickPixel('transparent');
			$debugImage->newImage($debugW, $debugH, $transparentPixel);

			$debugImage->drawImage($draw);

			$strokeColor->destroy();
			$fillColor->destroy();
			$draw->destroy();
			$transparentPixel->destroy();

			$image->compositeImage(
				$debugImage,
				Imagick::COMPOSITE_OVER,
				(int) $xPos,
				(int) $yPos
			);
			$debugImage->destroy();
		}

		// Composite bestfit caption over base image.
		$image->compositeImage(
			$theText,
			Imagick::COMPOSITE_DEFAULT,
			(int) $xPos,
			(int) $yPos);

		$theText->destroy();
	}
}