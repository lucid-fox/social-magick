<?php
/**
 * SocialMagick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2021 Lucid Fox
 * @license   GNU GPL v3 or later
 */

namespace LucidFox\SocialMagick;

use Exception;
use Imagick;
use ImagickPixel;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

final class ImageGenerator
{
	/**
	 * Resize and crop an image
	 *
	 * @param   string   $src    The path to the original image.
	 * @param   numeric  $new_w  New width, in pixels.
	 * @param   numeric  $new_h  New height, in pixels.
	 * @param   string   $focus  Focus of the image; default is center.
	 *
	 * @return  string
	 */
	public static function resize($src, $new_w, $new_h, $focus = 'center')
	{
		$image = new Imagick($src);
		$w     = $image->getImageWidth();
		$h     = $image->getImageHeight();

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
		else
		{
			$resize_w = $new_w;
			$resize_h = $h * $new_w / $w;
		}
		$image->resizeImage($resize_w, $resize_h, Imagick::FILTER_LANCZOS, 0.9);

		switch ($focus)
		{
			case 'northwest':
				$image->cropImage($new_w, $new_h, 0, 0);
				break;

			case 'center':
				$image->cropImage($new_w, $new_h, ($resize_w - $new_w) / 2, ($resize_h - $new_h) / 2);
				break;

			case 'northeast':
				$image->cropImage($new_w, $new_h, $resize_w - $new_w, 0);
				break;

			case 'southwest':
				$image->cropImage($new_w, $new_h, 0, $resize_h - $new_h);
				break;

			case 'southeast':
				$image->cropImage($new_w, $new_h, $resize_w - $new_w, $resize_h - $new_h);
				break;
		}

		return $image;
	}

	/**
	 * Generates an Open Graph image given set parameters, and sets appropriate meta tags.
	 *
	 * @param   string  $text        Test to overlay on image.
	 * @param   string  $template    Preset template name.
	 * @param   string  $extraImage  Additional image to layer below template.
	 *
	 * @return string
	 */
	public static function setOGImage($text, $template, $extraImage = null)
	{
		$app = Factory::getApplication();

		// only run if there's not already an open graph image set
		if (!isset($app->ogImgLoaded))
		{
			$document       = $app->getDocument();
			$templateParams = $app->getTemplate(true)->params;

			// Let's check whether we can perform the magick.
			if (true !== extension_loaded('imagick'))
			{
				throw new Exception('Imagick extension is not loaded.');
			}

			$name      = md5($text . ' ' . $template);
			$filename  = JPATH_ROOT . '/images/og-generated/' . $name . '.png';
			$isDevMode = $templateParams->get('devmode', 0) == 1;
			if (!file_exists($filename) || $isDevMode)
			{
				mkdir(dirname($filename), 0755, true);

				// Get OG templates

				$OGTemplates    = [];
				$ogTemplatesRaw = $templateParams->get('og-templates');
				$ogTemplatesRaw = empty($ogTemplatesRaw) ? [] : (array) $ogTemplatesRaw;

				foreach ($ogTemplatesRaw as $variables)
				{
					$variables                                = (array) $variables;
					$OGTemplates[$variables['template-name']] = $variables;
				}

				unset($ogTemplatesRaw);

				$template = $OGTemplates[$template];

				/* Create some objects */
				$image = new Imagick();

				$templateWidth  = $template['template-w'];
				$templateHeight = $template['template-h'];

				if ($template['base-image'])
				{
					$image = self::resize(JPATH_ROOT . '/' . $template['base-image'], $templateWidth, $templateHeight);

				}
				else
				{
					/* New image */
					$opacity = $template['base-color-alpha'];
					$alpha   = round($opacity * 255);
					$hex     = substr(base_convert(($alpha + 0x10000), 10, 16), -2, 2);
					$pixel   = new ImagickPixel($template['base-color'] . $hex);
					$image->newImage($templateWidth, $templateHeight, $pixel);
				}

				$theText = new Imagick();
				$theText->setBackgroundColor('transparent');

				/* Font properties */
				$theText->setFont(JPATH_THEMES . '/' . Factory::getApplication()->getTemplate() . '/fonts/' . $template['text-font']);
				$theText->setPointSize($template['font-size']);

				/* Create text */
				$theText->setGravity(Imagick::GRAVITY_CENTER);
				if ($template['text-align'] === 'center')
				{
					$theText->setGravity(Imagick::GRAVITY_CENTER);
				}
				elseif ($template['text-align'] === 'left')
				{
					$theText->setGravity(Imagick::GRAVITY_WEST);
				}
				elseif ($template['text-align'] === 'right')
				{
					$theText->setGravity(Imagick::GRAVITY_EAST);
				}

				// Create a `caption:` pseudo image that only manages text.
				$theText->newPseudoImage($template['text-width'],
					$template['text-height'],
					'caption:' . $text);
				// Remove extra height.
				$theText->trimImage(0.0);
				//set color of text
				$clut = new Imagick();
				$clut->newImage(1, 1, new ImagickPixel($template['text-color']));
				$theText->clutImage($clut);
				$clut->destroy();


				// Figure out text vertical position
				if ($template['text-y-center'] === '1')
				{
					$yPos = ($image->getImageHeight() - $theText->getImageHeight()) / 2.0; //centers image
					if ($template['text-y-adjust'] !== '0')
					{
						$yPos = ($image->getImageHeight() - $theText->getImageHeight()) / 2.0 + $template['text-y-adjust'];
					}
				}
				else
				{
					$yPos = $template['text-y-absolute'];
				}
				// debug
				// $yPos = 0;
				// Figure out text horizontal position
				if ($template['text-x-center'] === '1')
				{
					$xPos = ($image->getImageWidth() - $theText->getImageWidth()) / 2.0; //centers image
					if ($template['text-x-adjust'] !== '0')
					{
						$xPos = ($image->getImageWidth() - $theText->getImageWidth()) / 2.0 + $template['text-x-adjust'];
					}
				}
				else
				{
					$xPos = $template['text-x-absolute'];
				}
				// debug
				// echo $xPos;
				// $xPos = 0;

				// Add extra image
				if ($template['use-article-image'] !== '0' && $extraImage)
				{
					$extraCanvas = new Imagick();
					$extraCanvas->newImage($templateWidth, $templateHeight, new ImagickPixel('transparent'));
					if ($template['image-cover'] === '1')
					{
						$tmpImg = self::resize($extraImage, $templateWidth, $templateHeight);
						$imgX   = 0;
						$imgY   = 0;
						$extraCanvas->compositeImage(
							$tmpImg,
							Imagick::COMPOSITE_OVER,
							$imgX,
							$imgY);
					}
					else
					{
						$tmpImg = self::resize($extraImage, $template['image-width'], $template['image-height']);
						$imgX   = $template['image-x'];
						$imgY   = $template['image-y'];
						$extraCanvas->compositeImage(
							$tmpImg,
							Imagick::COMPOSITE_DEFAULT,
							0,
							0);
					}
					if ($template['image-z'] === 'under')
					{
						$extraCanvas->compositeImage(
							$image,
							Imagick::COMPOSITE_OVER,
							-$imgX,
							-$imgY);
						$image->compositeImage(
							$extraCanvas,
							Imagick::COMPOSITE_COPY,
							$imgX,
							$imgY);

					}
					elseif ($template['image-z'] === 'over')
					{
						$image->compositeImage(
							$extraCanvas,
							Imagick::COMPOSITE_DEFAULT,
							$imgX,
							$imgY);
					}
				}

				// Composite bestfit caption over base image.
				$image->compositeImage(
					$theText,
					Imagick::COMPOSITE_DEFAULT,
					$xPos,
					$yPos);


				/* Give image a format */
				$image->setImageFormat('png');

				file_put_contents($filename, $image);

			}
			$image = Uri::base() . 'images/og-generated/' . $name . '.png';

			// Set the page metadata
			$document->setMetaData('og:image', $image, $attribute = 'property');
			$document->setMetaData('og:image:alt', stripcslashes($text), $attribute = 'property');
			$document->setMetaData('og:image:height', $templateHeight, $attribute = 'property');
			$document->setMetaData('og:image:width', $templateWidth, $attribute = 'property');
			$app->ogImgLoaded = true;
		}

		return $image;
	}
}