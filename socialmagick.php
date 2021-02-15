<?php
/*
 * SocialMagick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2021 Lucid Fox
 * @license   GNU GPL v3 or later
 */

defined('_JEXEC') || die();

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Menu\MenuItem;
use Joomla\CMS\Plugin\CMSPlugin;
use LucidFox\SocialMagick\ImageGenerator;

/**
 * System plugin to automatically generate Open Graph images
 *
 * @package      ogimages
 *
 * @since        1.0.0
 *
 * @noinspection PhpUnused
 */
class plgSystemSocialmagick extends CMSPlugin
{
	/**
	 * The ImageGenerator instance used throughout the plugin
	 *
	 * @var   ImageGenerator
	 * @since 1.0.0
	 */
	private $helper = null;

	/**
	 * The title of the com_content article being rendered, if applicable
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	private $articleTitle = '';

	/**
	 * The images of the com_content article being rendered, if applicable
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private $articleImages = [];

	/**
	 * The Joomla! custom fields of the com_content article being rendered, if applicable
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private $articleFields = [];

	/**
	 * plgSystemSocialmagick constructor.
	 *
	 * @param   mixed  $subject  The event or plugin dispatcher
	 * @param   array  $config   Configuration parameters
	 */
	public function __construct(&$subject, $config = [])
	{
		// Register the autoloader for the library
		if (version_compare(JVERSION, '3.999.999', 'le'))
		{
			/** @noinspection PhpMethodParametersCountMismatchInspection */
			JLoader::registerNamespace('LucidFox\\SocialMagick', __DIR__ . '/library', false, false, 'psr4');
		}
		else
		{
			JLoader::registerNamespace('LucidFox\\SocialMagick', __DIR__ . '/library');
		}

		parent::__construct($subject, $config);

		$this->helper = new ImageGenerator($this->params);
	}

	/**
	 * Runs when Joomla is preparing a form. Used to add extra form fieldsets to core pages.
	 *
	 * @param   Form   $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	public function onContentPrepareForm(Form $form, $data): bool
	{
		$this->loadLanguage();

		Form::addFormPath(__DIR__ . '/form');

		switch ($form->getName())
		{
			// A menu item is being added/edited
			case 'com_menus.item':
				$form->loadFile('socialmagick_menu', false);
				break;
		}

		return true;
	}

	/**
	 * Triggered when Joomla is saving content. Used to save the SocialMagick configuration.
	 *
	 * @param   string|null   $context  Context for the content being saved
	 * @param   Table|object  $table    Joomla table object where the content is being saved to
	 * @param   bool          $isNew    Is this a new record?
	 * @param   object        $data     Data being saved
	 *
	 * @return  bool
	 */
	public function onContentBeforeSave(?string $context, $table, $isNew = false, $data = null): bool
	{
		// Make sure I have data to save
		if (!isset($data['socialmagick']))
		{
			return true;
		}

		$key = null;

		switch ($context)
		{
			case 'com_menus.item':
				$key = 'params';
				break;
		}

		if (is_null($key))
		{
			return true;
		}

		$params        = @json_decode($table->{$key}, true) ?? [];
		$table->{$key} = json_encode(array_merge($params, ['socialmagick' => $data['socialmagick']]));

		return true;
	}

	/**
	 * Triggered when Joomla is loading content. Used to load the Engage configuration.
	 *
	 * This is used for both articles and article categories.
	 *
	 * @param   string|null  $context  Context for the content being loaded
	 * @param   object       $data     Data being saved
	 *
	 * @return  bool
	 */
	public function onContentPrepareData(?string $context, &$data)
	{
		$key = null;

		switch ($context)
		{
			case 'com_menus.item':
				$key = 'params';
				break;
		}

		if (is_null($key))
		{
			return true;
		}

		if (!isset($data->{$key}) || !isset($data->{$key}['socialmagick']))
		{
			return true;
		}

		$data->socialmagick = $data->{$key}['socialmagick'];
		unset ($data->{$key}['socialmagick']);

		return true;
	}

	public function onSocialMagickGetTemplates(): array
	{
		return $this->helper->getTemplates();
	}

	public function onBeforeRender()
	{
		// Is this plugin even supported?
		if (!$this->helper->isAvailable())
		{
			return;
		}

		// Make sure we have a valid application and that it's the frontend of the site
		try
		{
			$app = Factory::getApplication();
		}
		catch (Exception $e)
		{
			return;
		}

		if (!($app instanceof CMSApplication))
		{
			return;
		}

		if (!method_exists($app, 'isClient') || !$app->isClient('site'))
		{
			return;
		}

		// Try to get the active menu item
		try
		{
			$menu        = \Joomla\CMS\Menu\AbstractMenu::getInstance('site');
			$currentItem = $menu->getActive();
		}
		catch (Exception $e)
		{
			return;
		}

		// Make sure there *IS* an active menu item.
		if (empty($currentItem))
		{
			return;
		}

		// Am I supposed to generate an Open Graph image?
		$params       = $currentItem->getParams();
		$willGenerate = $params->get('socialmagick.generate_images', 0) == 1;

		if (!$willGenerate)
		{
			return;
		}

		// Get my options
		$template    = $params->get('socialmagick.template', '');
		$customText  = $params->get('socialmagick.custom_text', '');
		$useArticle  = $params->get('socialmagick.use_article', 1) == 1;
		$useTitle    = $params->get('socialmagick.use_title', 1) == 1;
		$imageSource = $params->get('socialmagick.image_source', 'none');
		$imageField  = $params->get('socialmagick.image_field', '');
		$overrideOG  = $params->get('socialmagick.override_og', 0) == 1;

		// Get the text to render.
		$text = $this->getText($currentItem, $customText, $useArticle, $useTitle);

		// No text? No image.
		if (empty($text))
		{
			return;
		}

		$extraImage = $this->getExtraImage($imageSource, $imageField);

		if (!is_null($extraImage))
		{
			// So, Joomla 4 adds some crap to the image. Let's fix that.
			$questionMarkPos = strrpos($extraImage, '?');

			if ($questionMarkPos !== false)
			{
				$extraImage = substr($extraImage, 0, $questionMarkPos);
			}

			// Is this an absolute path?
			if (@file_exists(JPATH_ROOT . '/' . $extraImage))
			{
				$extraImage = JPATH_ROOT . '/' . $extraImage;
			}
		}

		if (!is_null($extraImage) && (!@file_exists($extraImage) || !@is_readable($extraImage)))
		{
			$extraImage = null;
		}

		$this->helper->applyOGImage($text, $template, $extraImage, $overrideOG);
	}

	/**
	 * Runs when Joomla is about to display an article. Used to save some useful article parameters.
	 *
	 * @param   string|null  $context  The context of the event, basically the component and view
	 * @param   mixed        $row      The content being rendered
	 * @param   mixed        $params   Parameters for the content being rendered
	 * @param   int|null     $page     Page number in multi-page articles because whatever, mate.
	 *
	 * @return  string  We always return an empty string since we don't want to diosplay anything
	 *
	 * @since   1.0.0
	 */
	public function onContentBeforeDisplay(?string $context, &$row, &$params, ?int $page = 0): string
	{
		if ($context != 'com_content.article')
		{
			return '';
		}

		// Save the article title, images and fields for later use
		$this->articleTitle  = $row->title;
		$this->articleImages = json_decode($row->images, true) ?? $row->images;
		$this->articleImages = is_array($this->articleImages) ? $this->articleImages : [];
		$this->articleFields = $row->jcfields;
		$this->articleFields = is_array($this->articleFields) ? $this->articleFields : [];

		return '';
	}

	/**
	 * Get the appropriate text for rendering on the auto-generated Open Graph image
	 *
	 * @param   MenuItem  $currentItem  The current menu item.
	 * @param   string    $customText   Any custom text the admin has entered for this menu item/
	 * @param   bool      $useArticle   Should I do a fallback to the core content article's title, if one exists?
	 * @param   bool      $useTitle     Should I do a fallback to the Joomla page title?
	 *
	 * @return  string  The text to render oin the auto-generated Open Graph image.
	 *
	 * @since   1.0.0
	 */
	private function getText(MenuItem $currentItem, string $customText, bool $useArticle, bool $useTitle): string
	{
		// First try using the magic socialMagickText app object variable.
		try
		{
			/** @var SiteApplication $app */
			$app     = Factory::getApplication();
			$appText = trim(@$app->socialMagickText ?? '');
		}
		catch (Exception $e)
		{
			$appText = '';
		}

		if (!empty($appText))
		{
			return $appText;
		}

		// The fallback to the custom text entered by the admin
		$customText = trim($customText);

		if (!empty($customText))
		{
			return $customText;
		}

		// The fallback to the core content article title, if one exists and this feature is enabled
		if ($useArticle)
		{
			$articleText = trim($this->articleTitle);

			if (!empty($articleText))
			{
				return $articleText;
			}
		}

		// Finally fall back to the page title, if this feature is enabled
		if ($useTitle)
		{
			return $currentItem->getParams()->get('page_title', $app->getDocument()->getTitle());
		}

		// I have found nothing. Return blank.
		return '';
	}

	private function getExtraImage(?string $imageSource, ?string $imageField): ?string
	{
		if (empty($imageSource))
		{
			return null;
		}

		switch ($imageSource)
		{
			default:
			case 'none':
				return null;
				break;

			case 'intro':
			case 'fulltext':
				if (empty($this->articleImages))
				{
					return null;
				}

				return ($this->articleImages['image_' . $imageSource]) ?: null;
				break;

			case 'custom':
				if (empty($this->articleFields) || empty($imageField))
				{
					return null;
				}

				foreach ($this->articleFields as $fieldInfo)
				{
					if ($fieldInfo->name != $imageField)
					{
						continue;
					}

					$rawvalue = $fieldInfo->rawvalue ?? '';
					$value    = @json_decode($rawvalue, true);


					if (empty($value) && is_string($rawvalue))
					{
						return $rawvalue;
					}

					if (empty($value) || !is_array($value))
					{
						return null;
					}

					return trim($value['imagefile'] ?? '') ?: null;
				}

				return null;
				break;
		}
	}
}