<?php
/**
 * SocialMagick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2021 Lucid Fox
 * @license   GNU GPL v3 or later
 */

namespace LucidFox\SocialMagick;

defined('_JEXEC') || die();

use Exception;
use Imagick;
use ImagickPixel;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\Path;
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
	 * Path relative to the site's root where the generated images will be saved
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	private $outputFolder = '';

	/**
	 * ImageGenerator constructor.
	 *
	 * @param   Registry  $pluginParams  The plugin parameters. Used to set up internal properties.
	 *
	 * @since   1.0.0
	 */
	public function __construct(Registry $pluginParams)
	{
		$this->devMode      = $pluginParams->get('devmode', 0) == 1;
		$this->outputFolder = $pluginParams->get('output_folder', 'images/og-generated') ?: 'images/og-generated';
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
	public function applyOGImage(string $text, string $template, ?string $extraImage = null, bool $force = false): void
	{
		// Don't try if the server requirements are not met
		if (!$this->isAvailable())
		{
			return;
		}

		// Make sure we have a front-end, HTML document — otherwise OG images are pointless
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

		// Only run if there's not already an open graph image set or if we're told to forcibly apply one
		$ogImage = $document->getMetaData('og:image');

		if (!empty($ogImage) && !$force)
		{
			return;
		}

		// Try to generate (or get an already generated) image
		try
		{
			[$imageURL, $templateHeight, $templateWidth] = $this->getOGImage($text, $template, $extraImage);
		}
		catch (Exception $e)
		{
			return;
		}

		// Set the page metadata
		$document->setMetaData('og:image', $imageURL, $attribute = 'property');
		$document->setMetaData('og:image:alt', stripcslashes($text), 'property');
		$document->setMetaData('og:image:height', $templateHeight, 'property');
		$document->setMetaData('og:image:width', $templateWidth, 'property');
	}

	/**
	 * Returns the array of parsed templates.
	 *
	 * This is used by the plugin to create an event that returns the templates, used by teh custom XML form field which
	 * allows the user to select a template in menu items.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function getTemplates(): array
	{
		return $this->templates;
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
		if (function_exists('extension_loaded') && extension_loaded('imagick') !== true)
		{
			return false;
		}

		// Make sure the Imagick and ImagickPixel classes are not disabled.
		return class_exists('Imagick') && class_exists('ImagickPixel');
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
	public function getOGImage(string $text, string $templateName, ?string $extraImage): array
	{
		// Get the image template
		$template       = array_merge([
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
		], $this->templates[$templateName] ?? []);
		$templateWidth  = $template['template-w'] ?? 1200;
		$templateHeight = $template['template-h'] ?? 630;

		// Get the generated image filename and URL
		$outputFolder     = trim($this->outputFolder, '/\\');
		$outputFolder     = str_replace('\\', '/', $outputFolder);
		$filename         = Path::clean(sprintf("%s/%s/%s.png",
			JPATH_ROOT,
			$outputFolder,
			md5($text . $templateName . serialize($template))
		));
		$realRelativePath = ltrim(substr($filename, strlen(JPATH_ROOT)), '/');
		$imageUrl         = Uri::base() . $realRelativePath;

		// If the file exists return early
		if (@file_exists($filename) && !$this->devMode)
		{
			$mediaVersion = ApplicationHelper::getHash(@filemtime($filename));

			return [$imageUrl . '?' . $mediaVersion, $templateWidth, $templateHeight];
		}

		// Create the folder if it doesn't already exist
		$imageOutputFolder = dirname($filename);

		if (!@is_dir($imageOutputFolder) && !@mkdir($imageOutputFolder, 0777, true))
		{
			Folder::create($imageOutputFolder);
		}

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

		// Setup the base image upon which we will superimpose the layered image (if any) and the text
		$image = new Imagick();

		if ($template['base-image'])
		{
			// So, Joomla 4 adds some crap to the image. Let's fix that.
			$baseImage       = $template['base-image'];
			$questionMarkPos = strrpos($baseImage, '?');

			if ($questionMarkPos !== false)
			{
				$baseImage = substr($baseImage, 0, $questionMarkPos);
			}

			$image = $this->resize(JPATH_ROOT . '/' . $baseImage, $templateWidth, $templateHeight);
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

		// Set up the text
		$fontPath = JPATH_PLUGINS . '/system/socialmagick/fonts/';

		$theText = new Imagick();
		$theText->setBackgroundColor('transparent');

		/* Font properties */
		$theText->setFont($fontPath . $template['text-font']);
		$theText->setPointSize($template['font-size']);

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

		if ($template['text-y-center'] === '1')
		{
			$yPos = ($image->getImageHeight() - $theText->getImageHeight()) / 2.0 + $template['text-y-adjust'];
		}

		// Figure out text horizontal position
		$xPos = $template['text-x-absolute'];

		if ($template['text-x-center'] === '1')
		{
			$xPos = ($image->getImageWidth() - $theText->getImageWidth()) / 2.0 + $template['text-x-adjust'];
		}

		// Add extra image
		if ($template['use-article-image'] !== '0' && $extraImage)
		{
			$extraCanvas      = new Imagick();
			$transparentPixel = new ImagickPixel('transparent');
			$extraCanvas->newImage($templateWidth, $templateHeight, $transparentPixel);
			$transparentPixel->destroy();

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

			$extraCanvas->destroy();
		}

		// Composite bestfit caption over base image.
		$image->compositeImage(
			$theText,
			Imagick::COMPOSITE_DEFAULT,
			$xPos,
			$yPos);

		$theText->destroy();

		// Write the image as a PNG file
		$image->setImageFormat('png');

		File::write($filename, $image);

		$image->destroy();

		$mediaVersion = ApplicationHelper::getHash(@filemtime($filename));

		return [$imageUrl . '?' . $mediaVersion, $templateWidth, $templateHeight];
	}

	/**
	 * Set the PHP time limit, if possible.
	 *
	 * @param   int  $limit  Time limit in seconds.
	 *
	 *
	 * @since   1.0.0
	 */
	private function setTimeLimit(int $limit = 0)
	{
		if (!function_exists('set_time_limit'))
		{
			return;
		}

		@set_time_limit($limit);
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

			default:
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
}