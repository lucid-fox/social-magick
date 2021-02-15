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
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * Automatic Open Graph image generator.
 *
 * @package     LucidFox\SocialMagick
 *
 * @since       1.0.0
 */
final class ImageGenerator
{
	/**
	 * Is this plugin in Development Mode? In this case the images are forcibly generated.
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	private $devMode = false;

	/**
	 * Open Graph image templates, parsed from the plugin options
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private $templates = [];

	/**
	 * ImageGenerator constructor.
	 *
	 * @param   Registry  $pluginParams  The plugin parameters. Used to set up internal properties.
	 *
	 * @since   1.0.0
	 */
	public function __construct(Registry $pluginParams)
	{
		$this->devMode = $pluginParams->get('devmode', 0) == 1;
		$this->parseImageTemplates($pluginParams->get('og-templates', null));
	}

	/**
	 * Generates an Open Graph image given set parameters, and sets appropriate meta tags.
	 *
	 * @param   string       $text        Test to overlay on image.
	 * @param   string       $template    Preset template name.
	 * @param   string|null  $extraImage  Additional image to layer below template.
	 * @param   bool         $force       Should I override an already set OpenGraph image?
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function setOGImage(string $text, string $template, ?string $extraImage = null, bool $force = false): void
	{
		try
		{
			$app      = Factory::getApplication();
			$document = $app->getDocument();
		}
		catch (Exception $e)
		{
			return;
		}

		if (!($document instanceof HtmlDocument))
		{
			return;
		}

		$ogImage = $document->getMetaData('og:image');

		// Only run if there's not already an open graph image set
		if (!empty($ogImage) && !$force)
		{
			return;
		}

		// Try to generate (or get an already generated) image
		try
		{
			[$image, $templateHeight, $templateWidth] = $this->generateOGImage($text, $template, $extraImage);
		}
		catch (Exception $e)
		{
			return;
		}

		// Set the page metadata
		$document->setMetaData('og:image', $image, $attribute = 'property');
		$document->setMetaData('og:image:alt', stripcslashes($text), 'property');
		$document->setMetaData('og:image:height', $templateHeight, 'property');
		$document->setMetaData('og:image:width', $templateWidth, 'property');
	}

	/**
	 * Are all the requirements met to automatically generate Open Graph images?
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	public function isAvailable(): bool
	{
		// Quick escape route if the Imagick extension is not loaded / compiled in.
		if (function_exists('extension_loaded') && !extension_loaded('imagick') !== true)
		{
			return false;
		}

		// Make sure the Imagick and ImagickPixel classes are not disabled.
		return class_exists('Imagick') && class_exists('ImagickPixel');
	}

	/**
	 * Parse the image templates from the raw options returned by Joomla
	 *
	 * @param   mixed  $ogTemplatesRaw  The raw options returned by Joomla
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function parseImageTemplates($ogTemplatesRaw): void
	{
		$this->templates = [];
		$ogTemplatesRaw  = empty($ogTemplatesRaw) ? [] : (array) $ogTemplatesRaw;

		foreach ($ogTemplatesRaw as $variables)
		{
			$variables                                    = (array) $variables;
			$this->templates[$variables['template-name']] = $variables;
		}
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
	 * Returns the generated Open Graph image and its information.
	 *
	 * @param   string       $text
	 * @param   string       $template
	 * @param   string|null  $extraImage
	 *
	 * @return  array [$imageURL, $height, $width]
	 *
	 * @throws \ImagickException
	 *
	 * @since  1.0.0
	 */
	private function generateOGImage(string $text, string $template, ?string $extraImage): array
	{
		$templateParams = $app->getTemplate(true)->params;

		$name      = md5($text . ' ' . $template);
		$filename  = JPATH_ROOT . '/images/og-generated/' . $name . '.png';
		$isDevMode = $templateParams->get('devmode', 0) == 1;

		if (!file_exists($filename) || $isDevMode)
		{
			mkdir(dirname($filename), 0755, true);

			$template = array_merge([
				'base-image'        => '',
				'template-w'        => 1200,
				'template-h'        => 630,
				'base-color'        => '#000000',
				'base-color-alpha'  => 1,
				'text-font'         => '',
				'font-size'         => 24,
				'text-color'        => '#ffffff',
				'text-width'        => 1200,
				'text-height'       => 630,
				'text-align'        => 'left',
				'text-y-center'     => 1,
				'text-y-adjust'     => 0,
				'text-y-absolute'   => 0,
				'text-x-center'     => 1,
				'text-x-adjust'     => 0,
				'text-x-absolute'   => 0,
				'use-article-image' => 0,
				'image-z'           => 'under',
				'image-cover'       => 1,
				'image-width'       => 1200,
				'image-height'      => 630,
				'image-x'           => 0,
				'image-y'           => 0,
			], $this->templates[$template] ?? []);

			/* Create some objects */
			$image = new Imagick();

			$templateWidth  = $template['template-w'] ?? 0;
			$templateHeight = $template['template-h'] ?? 0;

			if ($template['base-image'])
			{
				$image = $this->resize(JPATH_ROOT . '/' . $template['base-image'], $templateWidth, $templateHeight);
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
					$tmpImg = $this->resize($extraImage, $templateWidth, $templateHeight);
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
					$tmpImg = $this->resize($extraImage, $template['image-width'], $template['image-height']);
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

		$imageUrl = Uri::base() . 'images/og-generated/' . $name . '.png';
	}
}