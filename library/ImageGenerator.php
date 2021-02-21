<?php
/**
 * Social Magick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2021 Lucid Fox
 * @license   GNU GPL v3 or later
 */

namespace LucidFox\SocialMagick;

defined('_JEXEC') || die();

use Exception;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
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
	 * The image renderer we'll be using
	 *
	 * @var   ImageRendererInterface
	 * @since 1.0.0
	 */
	private $renderer;

	/**
	 * Number of subfolder levels for generated images
	 *
	 * @var   int
	 * @since 1.0.0
	 */
	private $folderLevels = 0;

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
		$this->folderLevels = $pluginParams->get('folder_levels', 0);

		$rendererType = $pluginParams->get('library', 'auto');
		$textDebug    = $pluginParams->get('textdebug', '0') == 1;
		$quality      = 100 - $pluginParams->get('quality', '95');

		$this->parseImageTemplates($pluginParams->get('og-templates', null));

		switch ($rendererType)
		{
			case 'imagick':
				$this->renderer = new ImageRendererImagick($quality, $textDebug);
				break;

			case 'gd':
				$this->renderer = new ImageRendererGD($quality, $textDebug);
				break;

			case 'auto':
			default:
				$this->renderer = new ImageRendererImagick($quality, $textDebug);

				if (!$this->renderer->isSupported())
				{
					$this->renderer = new ImageRendererGD($quality, $textDebug);
				}

				break;
		}
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
		$document->setMetaData('og:image', $imageURL, 'property');
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
		return $this->renderer->isSupported();
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
		$template = array_merge([
			'base-image'        => '',
			'template-w'        => 1200,
			'template-h'        => 630,
			'base-color'        => '#000000',
			'base-color-alpha'  => 1,
			'overlay_text'      => 1,
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
			md5($text . $templateName . serialize($template) . ($extraImage ?? '') . $this->renderer->getOptionsKey())
		));
		$filename = FileDistributor::ensureDistributed(dirname($filename), basename($filename), $this->folderLevels);
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

		try
		{
			$this->renderer->makeImage($text, $template, $filename, $extraImage);
		}
		catch (Exception $e)
		{
			// Whoops. Things will be broken :(
		}

		$mediaVersion = ApplicationHelper::getHash(@filemtime($filename));

		return [$imageUrl . '?' . $mediaVersion, $templateWidth, $templateHeight];
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
}