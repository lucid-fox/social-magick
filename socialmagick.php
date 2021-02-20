<?php
/**
 * Social Magick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2021 Lucid Fox
 * @license   GNU GPL v3 or later
 */

defined('_JEXEC') || die();

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Menu\MenuItem;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Table;
use LucidFox\SocialMagick\ImageGenerator;
use LucidFox\SocialMagick\ParametersRetriever;

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
	public $app;

	/**
	 * The ImageGenerator instance used throughout the plugin
	 *
	 * @var   ImageGenerator
	 * @since 1.0.0
	 */
	private $helper = null;

	/**
	 * The com_content article ID being rendered, if applicable.
	 *
	 * @var   int
	 * @since 1.0.0
	 */
	private $article = '';

	/**
	 * The com_content category ID being rendered, if applicable.
	 *
	 * @var   int
	 * @since 1.0.0
	 */
	private $category = '';

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

			// A core content category is being added/edited
			case 'com_categories.categorycom_content':
				$form->loadFile('socialmagick_category', false);
				break;

			// An article is being added/edited
			case 'com_content.article':
				$form->loadFile('socialmagick_article', false);
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
	 * @param   object        $data     Data being saved (Joomla 4)
	 *
	 * @return  bool
	 */
	public function onContentBeforeSave(?string $context, $table, $isNew = false, $data = null): bool
	{
		// Joomla 3 does not pass the data from com_menus. Therefore, we have to fake it.
		if (is_null($data) && version_compare(JVERSION, '3.999.999', 'le'))
		{
			$input = Factory::getApplication()->input;
			$data  = $input->get('jform', [], 'array');
		}

		// Make sure I have data to save
		if (!isset($data['socialmagick']))
		{
			return true;
		}

		$key = null;

		switch ($context)
		{
			case 'com_menus.item':
			case 'com_categories.category':
				$key = 'params';
				break;

			case 'com_content.article':
				$key = 'attribs';
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
			case 'com_categories.category':
				$key = 'params';
				break;

			case 'com_content.article':
				$key = 'attribs';
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

	/**
	 * Returns all Social Magick templates known to the plugin
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function onSocialMagickGetTemplates(): array
	{
		return $this->helper->getTemplates();
	}

	/**
	 * Runs before Joomla renders the HTML document.
	 *
	 * This is the main event where Social Magick evaluates whether to apply an Open Graph image to the document.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onBeforeRender(): void
	{
		// Is this plugin even supported?
		if (!$this->helper->isAvailable())
		{
			return;
		}

		if (!is_object($this->app) || !($this->app instanceof CMSApplication))
		{
			return;
		}

		if (!method_exists($this->app, 'isClient') || !$this->app->isClient('site'))
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

		$params = ParametersRetriever::getMenuParameters($currentItem->id, $currentItem);

		if (($currentItem->query['option'] ?? '') == 'com_content')
		{
			$task        = $currentItem->query['task'] ?? '';
			$defaultView = '';

			if (strpos($task, '.') !== false)
			{
				[$defaultView, $task] = explode('.', $task);
			}

			$view = $this->app->input->getCmd('view', ($currentItem->query['view'] ?? '') ?: $defaultView);

			switch ($view)
			{
				case 'categories':
				case 'category':
					// Apply category overrides if applicable
					$category = $this->category ?? $this->app->input->getInt('id', $currentItem->query['id'] ?? null);
					if ($category)
					{
						$catParams = ParametersRetriever::getCategoryParameters($category);

						if ($catParams['override'] == 1)
						{
							$params = $catParams;
						}
					}

					$this->article  = null;
					$this->category = $category;
					break;

				case 'archive':
				case 'article':
				case 'featured':
					// Apply article overrides if applicable
					$article = $this->article ?? $this->app->input->getInt('id', $currentItem->query['id'] ?? null);

					if ($article)
					{
						$articleParams = ParametersRetriever::getArticleParameters($article);

						if ($articleParams['override'] == 1)
						{
							$params = $articleParams;
						}
					}

					$this->article  = $article;
					$this->category = null;

					break;
			}
		}

		// Am I supposed to generate an Open Graph image?
		$willGenerate = $params['generate_images'] == 1;

		if (!$willGenerate)
		{
			return;
		}

		// Get the applicable options
		$template    = $params['template'];
		$customText  = $params['custom_text'];
		$useArticle  = $params['use_article'] == 1;
		$useTitle    = $params['use_title'] == 1;
		$imageSource = $params['image_source'];
		$imageField  = $params['image_field'];
		$overrideOG  = $params['override_og'] == 1;

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
		if (!in_array($context, ['com_content.article', 'com_content.category', 'com_content.categories']))
		{
			return '';
		}

		switch ($context)
		{
			case 'com_content.article':
			case 'com_content.category':
				$this->article = $row;
				break;

			case 'com_content.categories':
				$this->category = $row;
		}

		// Save the article/category, images and fields for later use
		if ($context == 'com_content.categories')
		{
			$this->category = $row->id;
		}
		else
		{
			$this->article = $row->id;
		}

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
			$appText = trim(@$this->app->socialMagickText ?? '');
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
			$title = '';

			if ($this->article)
			{
				$article = ParametersRetriever::getArticleById($this->article);
				$title = empty($article) ? '' : ($article->title ?? '');
			}
			elseif ($this->category)
			{
				$category = ParametersRetriever::getCategoryById($this->category);
				$title = empty($category) ? '' : ($category->title ?? '');
			}

			if (!empty($title))
			{
				return $title;
			}
		}

		// Finally fall back to the page title, if this feature is enabled
		if ($useTitle)
		{
			return $currentItem->getParams()->get('page_title', $this->app->getDocument()->getTitle());
		}

		// I have found nothing. Return blank.
		return '';
	}

	/**
	 * Gets the additional image to apply to the article
	 *
	 * @param   string|null  $imageSource  The image source type: `none`, `intro`, `fulltext`, `custom`.
	 * @param   string|null  $imageField   The name of the Joomla! Custom Field when `$imageSource` is `custom`.
	 *
	 * @return  string|null  The (hopefully relative) image path. NULL if no image is found or applicable.
	 *
	 * @since   1.0.0
	 */
	private function getExtraImage(?string $imageSource, ?string $imageField): ?string
	{
		if (empty($imageSource))
		{
			return null;
		}

		$contentObject = null;
		$jcFields      = [];
		$articleImages = [];

		if ($this->article)
		{
			$contentObject = ParametersRetriever::getArticleById($this->article);
		}
		elseif ($this->category)
		{
			$contentObject = ParametersRetriever::getCategoryById($this->category);
		}

		if (!empty($contentObject))
		{
			// Decode custom fields
			$jcFields = $contentObject->jcfields ?? [];

			if (is_string($jcFields))
			{
				$jcFields = @json_decode($jcFields, true);
			}

			$jcFields = is_array($jcFields) ? $jcFields : [];

			// Decode images
			$articleImages = $contentObject->images ?? ($contentObject->params ?? []);
			$articleImages = is_string($articleImages) ? @json_decode($articleImages, true) : $articleImages;
			$articleImages = is_array($articleImages) ? $articleImages : [];
		}

		switch ($imageSource)
		{
			default:
			case 'none':
				return null;
				break;

			case 'intro':
			case 'fulltext':
				if (empty($articleImages))
				{
					return null;
				}

				if (isset($articleImages['image_' . $imageSource]))
				{
					return ($articleImages['image_' . $imageSource]) ?: null;
				}
				elseif (isset($articleImages['image']))
				{
					return ($articleImages['image']) ?: null;
				}
				else
				{
					return null;
				}

				break;

			case 'custom':
				if (empty($jcFields) || empty($imageField))
				{
					return null;
				}

				foreach ($jcFields as $fieldInfo)
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