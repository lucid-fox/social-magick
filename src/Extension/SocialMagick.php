<?php
/**
 * Social Magick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2023 Lucid Fox
 * @license   GNU GPL v3 or later
 */

namespace LucidFox\Plugin\System\SocialMagick\Extension;

defined('_JEXEC') || die();

use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Menu\MenuItem;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\UserHelper;
use Joomla\Registry\Registry;
use LucidFox\Plugin\System\SocialMagick\Library\ImageGenerator;
use LucidFox\Plugin\System\SocialMagick\Library\ParametersRetriever;
use Throwable;

/**
 * System plugin to automatically generate Open Graph images
 *
 * @since        1.0.0
 *
 * @noinspection PhpUnused
 */
class SocialMagick extends CMSPlugin
{
	/**
	 * The ImageGenerator instance used throughout the plugin
	 *
	 * @var   ImageGenerator|null
	 * @since 1.0.0
	 */
	private ?ImageGenerator $helper;

	/**
	 * The com_content article ID being rendered, if applicable.
	 *
	 * @var   int|null
	 * @since 1.0.0
	 */
	private ?int $article = null;

	/**
	 * The com_content category ID being rendered, if applicable.
	 *
	 * @var   int|null
	 * @since 1.0.0
	 */
	private ?int $category = null;

	/**
	 * The placeholder variable to be replaced by the image link when Debug Link is enabled.
	 *
	 * @var  string
	 */
	private string $debugLinkPlaceholder = '';

	/**
	 * plgSystemSocialmagick constructor.
	 *
	 * @param   mixed  $subject  The event or plugin dispatcher
	 * @param   array  $config   Configuration parameters
	 *
	 * @since   1.0.0
	 */
	public function __construct(&$subject, $config = [])
	{
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
	 * @since        1.0.0
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function onContentPrepareForm(Form $form, $data): bool
	{
		$this->loadLanguage();
		$this->loadLanguage('plg_system_socialmagick.sys');

		Form::addFormPath(__DIR__ . '/../../form');

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
	 * @param   string|null        $context  Context for the content being saved
	 * @param   Table|object       $table    Joomla table object where the content is being saved to
	 * @param   bool               $isNew    Is this a new record?
	 * @param   object|array|null  $data     Data being saved (Joomla 4)
	 *
	 * @return  bool
	 * @since        1.0.0
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function onContentBeforeSave(?string $context, object $table, bool $isNew = false, $data = null): bool
	{
		$data = (array) $data;

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
	 * Triggered when Joomla is loading content. Used to load the Social Magick configuration.
	 *
	 * This is used for both articles and article categories.
	 *
	 * @param   string|null   $context  Context for the content being loaded
	 * @param   object|array  $data     Data being saved
	 *
	 * @return  bool
	 * @since   1.0.0
	 */
	public function onContentPrepareData(?string $context, $data): bool
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
	 * @since        1.0.0
	 * @noinspection PhpUnused
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
	 * @since        1.0.0
	 * @noinspection PhpUnused
	 */
	public function onBeforeRender(): void
	{
		// Is this plugin even supported?
		if (!$this->helper->isAvailable())
		{
			return;
		}

		// Is this the frontend HTML application?
		if (!is_object($this->getApplication()) || !($this->getApplication() instanceof CMSApplication))
		{
			return;
		}

		if (!method_exists($this->getApplication(), 'isClient') || !$this->getApplication()->isClient('site'))
		{
			return;
		}

		try
		{
			if ($this->getApplication()->getDocument()->getType() != 'html')
			{
				return;
			}
		}
		catch (Throwable $e)
		{
			return;
		}

		// Try to get the active menu item
		try
		{
			//$menu        = AbstractMenu::getInstance('site');
			$menu        = $this->getApplication()->getMenu();
			$currentItem = $menu->getActive();
		}
		catch (Throwable $e)
		{
			return;
		}

		// Make sure there *IS* an active menu item.
		if (empty($currentItem))
		{
			return;
		}

		// Get the menu item parameters
		$params = ParametersRetriever::getMenuParameters($currentItem->id, $currentItem);

		/**
		 * In Joomla 4 when you access a /component/whatever URL you have the ItemID for the home page as the active
		 * item BUT the option parameter in the application is different. Let's detect that and get out if that's the
		 * case.
		 */
		$menuOption    = $currentItem->query['option'] ?? '';
		$currentOption = $this->getApplication()->input->getCmd('option', $menuOption);

		if (!empty($menuOption) && ($menuOption !== $currentOption))
		{
			$menuOption = $currentOption;
		}

		// Apply core content settings overrides, if applicable
		if ($menuOption == 'com_content')
		{
			$task        = $this->getApplication()->input->getCmd('task', $currentItem->query['task'] ?? '');
			$defaultView = '';

			if (strpos($task, '.') !== false)
			{
				[$defaultView,] = explode('.', $task);
			}

			$view = $this->getApplication()->input->getCmd('view', ($currentItem->query['view'] ?? '') ?: $defaultView);

			switch ($view)
			{
				case 'categories':
				case 'category':
					// Apply category overrides if applicable
					$category = $this->category ?: $this->getApplication()->input->getInt('id', $currentItem->query['id'] ?? null);

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
					$article = $this->article ?: $this->getApplication()->input->getInt('id', $currentItem->query['id'] ?? null);

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

		// Apply default site-wide settings if applicable
		$templateKeys    = array_keys($this->helper->getTemplates() ?? []);
		$defaultTemplate = count($templateKeys) ? array_shift($templateKeys) : '';

		$defaultPluginSettings = [
			'template'              => $defaultTemplate,
			'generate_images'       => 1,
			'og_title'              => 1,
			'og_title_custom'       => '',
			'og_description'        => 1,
			'og_description_custom' => '',
			'og_url'                => 1,
			'og_site_name'          => 1,
			'twitter_card'          => 2,
			'twitter_site'          => '',
			'twitter_creator'       => '',
			'fb_app_id'             => '',
		];

		foreach ($defaultPluginSettings as $key => $defaultValue)
		{
			$inheritValue = is_numeric($defaultValue) ? -1 : '';
			$paramsValue  = trim($params[$key]);
			$paramsValue  = is_numeric($paramsValue) ? ((int) $paramsValue) : $paramsValue;

			if ($paramsValue === $inheritValue)
			{
				$params[$key] = $this->params->get($key, $defaultValue);
			}
		}

		// Generate an Open Graph image, if applicable.
		if ($params['generate_images'] == 1)
		{
			$this->applyOGImage($params);
		}

		// Apply additional Open Graph tags
		$this->applyOpenGraphTags($params);
	}

	/**
	 * Runs when Joomla is about to display an article. Used to save some useful article parameters.
	 *
	 * @param   string|null  $context  The context of the event, basically the component and view
	 * @param   mixed        $row      The content being rendered
	 * @param   mixed        $params   Parameters for the content being rendered
	 * @param   int|null     $page     Page number in multipage articles because whatever, mate.
	 *
	 * @return  string  We always return an empty string since we don't want to display anything
	 *
	 * @since   1.0.0
	 */
	public function onContentBeforeDisplay(?string $context, $row, $params, ?int $page = 0): string
	{
		/**
		 * When Joomla is rendering an article in a Newsflash module it uses the same context as rendering an article
		 * through com_content (com_content.article). However, we do NOT want the newsflash articles to override the
		 * Social Magick settings!
		 *
		 * This is an ugly hack around this problem. It's based on the observation that the newsflash module is passing
		 * its own module options in the $params parameter to this event. As a result it has the `moduleclass_sfx` key
		 * defined, whereas this key does not exist when rendering an article through com_content.
		 */
		if (($params instanceof Registry) && $params->exists('moduleclass_sfx'))
		{
			return '';
		}

		if (!in_array($context, ['com_content.article', 'com_content.category', 'com_content.categories']))
		{
			return '';
		}

		switch ($context)
		{
			case 'com_content.article':
			case 'com_content.category':
				$this->article = $row->id;
				break;

			case 'com_content.categories':
				$this->category = $row->id;
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

		// Add the debug link if necessary
		if ($this->params->get('debuglink', 0) == 1)
		{
			return $this->getDebugLinkPlaceholder();
		}

		return '';
	}

	/**
	 * Runs after rendering the document but before outputting it to the browser.
	 *
	 * Used to add the OpenGraph declaration to the document head and applying the debug image link.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onAfterRender(): void
	{
		if ($this->params->get('add_og_declaration', '1') == 1)
		{
			$this->addOgPrefixToHtmlDocument();
		}

		if ($this->params->get('debuglink', 0) == 1)
		{
			$this->replaceDebugImagePlaceholder();
		}

	}

	/**
	 * AJAX handler
	 *
	 * @return  void
	 * @since        1.0.0
	 * @noinspection PhpUnused
	 */
	public function onAjaxSocialmagick()
	{
		$key     = trim($this->params->get('cron_url_key', ''));
		$maxExec = max(1, (int) $this->params->get('cron_max_exec', 20));
		$days    = max(1, (int) $this->params->get('old_images_after', 180));

		if (empty($key))
		{
			header('HTTP/1.0 403 Forbidden');

			return;
		}

		try
		{
			$this->helper->deleteOldImages($days, $maxExec);
		}
		catch (Exception $e)
		{
			header('HTTP/1.0 500 Internal Server Error');

			echo $e->getCode() . ' ' . $e->getMessage();

			return;
		}

		echo "OK";
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
			/** @noinspection PhpUndefinedFieldInspection */
			$appText = trim(@$this->getApplication()->socialMagickText ?? '');
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
				$title   = empty($article) ? '' : ($article->title ?? '');
			}
			elseif ($this->category)
			{
				$category = ParametersRetriever::getCategoryById($this->category);
				$title    = empty($category) ? '' : ($category->title ?? '');
			}

			if (!empty($title))
			{
				return $title;
			}
		}

		// Finally fall back to the page title, if this feature is enabled
		if ($useTitle)
		{
			return $currentItem->getParams()->get('page_title', $this->getApplication()->getDocument()->getTitle());
		}

		// I have found nothing. Return blank.
		return '';
	}

	/**
	 * Gets the additional image to apply to the article
	 *
	 * @param   string|null  $imageSource  The image source type: `none`, `intro`, `fulltext`, `custom`.
	 * @param   string|null  $imageField   The name of the Joomla! Custom Field when `$imageSource` is `custom`.
	 * @param   string|null  $staticImage  A static image definition
	 *
	 * @return  string|null  The (hopefully relative) image path. NULL if no image is found or applicable.
	 *
	 * @since   1.0.0
	 */
	private function getExtraImage(?string $imageSource, ?string $imageField, ?string $staticImage): ?string
	{
		/** @noinspection PhpUndefinedFieldInspection */
		$customImage = trim(@$this->getApplication()->socialMagickImage ?? '');

		if (!empty($customImage))
		{
			return $customImage;
		}

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

			case 'static':
				return $staticImage;

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
		}
	}

	/**
	 * Adds the `prefix="og: http://ogp.me/ns#"` declaration to the `<html>` root tag.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function addOgPrefixToHtmlDocument(): void
	{
		// Make sure I am in the front-end, and I'm doing HTML output
		/** @var SiteApplication $app */
		$app = $this->getApplication();

		if (!is_object($app) || !($app instanceof SiteApplication))
		{
			return;
		}

		try
		{
			if ($this->getApplication()->getDocument()->getType() != 'html')
			{
				return;
			}
		}
		catch (Throwable $e)
		{
			return;
		}

		$html = $app->getBody();

		$hasDeclaration = function (string $html): bool {
			$detectPattern = '/<html.*prefix\s?="(.*)\s?:(.*)".*>/iU';
			$count         = preg_match_all($detectPattern, $html, $matches);

			if ($count === 0)
			{
				return false;
			}

			for ($i = 0; $i < $count; $i++)
			{
				if (trim($matches[1][$i]) == 'og')
				{
					return true;
				}
			}

			return false;
		};

		if ($hasDeclaration($html))
		{
			return;
		}

		$replacePattern = '/<html(.*)>/iU';

		/** @noinspection HttpUrlsUsage */
		$app->setBody(preg_replace($replacePattern, '<html$1 prefix="og: http://ogp.me/ns#">', $html, 1));
	}

	/**
	 * Replace the debug image placeholder with a link to the OpenGraph image.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function replaceDebugImagePlaceholder(): void
	{
		// Make sure I am in the front-end, and I'm doing HTML output
		/** @var SiteApplication $app */
		$app = $this->getApplication();

		if (!is_object($app) || !($app instanceof SiteApplication))
		{
			return;
		}

		try
		{
			if ($this->getApplication()->getDocument()->getType() != 'html')
			{
				return;
			}
		}
		catch (Throwable $e)
		{
			return;
		}

		$imageLink = ($this->getApplication()->getDocument()->getMetaData('og:image') ?: $this->getApplication()->getDocument()->getMetaData('twitter:image')) ?: '';

		$this->loadLanguage();

		$message = Text::_('PLG_SYSTEM_SOCIALMAGICK_DEBUGLINK_MESSAGE');

		if ($message == 'PLG_SYSTEM_SOCIALMAGICK_DEBUGLINK_MESSAGE')
		{
			/** @noinspection HtmlUnknownTarget */
			$message = "<a href=\"%s\" target=\"_blank\">Preview OpenGraph Image</a>";
		}

		$message = $imageLink ? sprintf($message, $imageLink) : '';

		$app->setBody(str_replace($this->getDebugLinkPlaceholder(), $message, $app->getBody()));
	}


	/**
	 * Generate (if necessary) and apply the Open Graph image
	 *
	 * @param   array  $params  Applicable menu parameters, with any overrides already taken into account
	 *
	 * @return  void
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	private function applyOGImage(array $params): void
	{
		//$menu        = AbstractMenu::getInstance('site');
		$menu        = $this->getApplication()->getMenu();
		$currentItem = $menu->getActive();

		// Get the applicable options
		$template    = $params['template'];
		$customText  = $params['custom_text'];
		$useArticle  = $params['use_article'] == 1;
		$useTitle    = $params['use_title'] == 1;
		$imageSource = $params['image_source'];
		$imageField  = $params['image_field'];
		$staticImage = $params['static_image'] ?: '';
		$overrideOG  = $params['override_og'] == 1;

		// Get the text to render.
		$text = $this->getText($currentItem, $customText, $useArticle, $useTitle);

		$templates      = $this->helper->getTemplates();
		$templateParams = $templates[$template] ?? [];

		// If there is no text AND I am supposed to use overlay text I will not try to generate an image.
		if (empty($text) && ($templateParams['overlay_text'] ?? 1))
		{
			return;
		}

		// Get the extra image location
		$extraImage = $this->getExtraImage($imageSource, $imageField, $staticImage);

		// So, Joomla 4 adds some meta information to the image. Let's fix that.
		if (!empty($extraImage))
		{
			$extraImage = urldecode(HTMLHelper::cleanImageURL($extraImage)->url ?? '');
		}

		if (!is_null($extraImage) && (!@file_exists($extraImage) || !@is_readable($extraImage)))
		{
			$extraImage = null;
		}

		/** @noinspection PhpUndefinedFieldInspection */
		$template = trim(@$this->getApplication()->socialMagickTemplate ?? '') ?: $template;

		// Generate (if necessary) and apply the Open Graph image
		$this->helper->applyOGImage($text, $template, $extraImage, $overrideOG);
	}

	/**
	 * Apply the additional Open Graph tags
	 *
	 * @param   array  $params  Applicable menu item parameters
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function applyOpenGraphTags(array $params): void
	{
		// Apply Open Graph Title
		switch ($params['og_title'])
		{
			case 0:
				break;

			case 1:
				$this->conditionallyApplyMeta('og:title', $this->getApplication()->getDocument()->getTitle());
				break;

			case 2:
				$this->conditionallyApplyMeta('og:title', $params['og_title_custom'] ?? $this->getApplication()->getDocument()->getTitle());
				break;
		}

		// Apply Open Graph Description
		switch ($params['og_description'])
		{
			case 0:
				break;

			case 1:
				$this->conditionallyApplyMeta('og:description', $this->getApplication()->getDocument()->getDescription());
				break;

			case 2:
				$this->conditionallyApplyMeta('og:description', $params['og_description_custom'] ?? $this->getApplication()->getDocument()->getDescription());
				break;
		}

		// Apply Open Graph URL
		if (($params['og_url'] ?? 1) == 1)
		{
			$this->conditionallyApplyMeta('og:url', $this->getApplication()->getDocument()->getBase());
		}

		// Apply Open Graph Site Name
		if (($params['og_site_name'] ?? 1) == 1)
		{
			$this->conditionallyApplyMeta('og:site_name', $this->getApplication()->get('sitename', ''));
		}

		// Apply Facebook App ID
		$fbAppId = trim($params['fb_app_id'] ?? '');

		if (!empty($fbAppId))
		{
			$this->conditionallyApplyMeta('fb:app_id', $fbAppId);
		}

		// Apply Twitter options, of there is a Twitter card type
		$twitterCard    = trim($params['twitter_card'] ?? '');
		$twitterSite    = trim($params['twitter_site'] ?? '');
		$twitterCreator = trim($params['twitter_creator'] ?? '');

		switch ($twitterCard)
		{
			case 0:
				// Nothing further to do with Twitter.
				return;

			case 1:
				$this->conditionallyApplyMeta('twitter:card', 'summary', 'name');
				break;

			case 2:
				$this->conditionallyApplyMeta('twitter:card', 'summary_large_image', 'name');
				break;
		}

		if (!empty($twitterSite))
		{
			$twitterSite = (substr($twitterSite, 0, 1) == '@') ? $twitterSite : ('@' . $twitterSite);
			$this->conditionallyApplyMeta('twitter:site', $twitterSite, 'name');
		}

		if (!empty($twitterCreator))
		{
			$twitterCreator = (substr($twitterCreator, 0, 1) == '@') ? $twitterCreator : ('@' . $twitterCreator);
			$this->conditionallyApplyMeta('twitter:creator', $twitterCreator, 'name');
		}

		// Transcribe Open Graph properties to Twitter meta
		/** @var HtmlDocument $doc */
		$doc = $this->getApplication()->getDocument();

		$transcribes = [
			'title'       => $doc->getMetaData('og:title', 'property'),
			'description' => $doc->getMetaData('og:description', 'property'),
			'image'       => $doc->getMetaData('og:image', 'property'),
			'image:alt'   => $doc->getMetaData('og:image:alt', 'property'),
		];

		foreach ($transcribes as $key => $value)
		{
			$value = trim($value ?? '');

			if (empty($value))
			{
				continue;
			}

			$this->conditionallyApplyMeta('twitter:' . $key, $value, 'name');
		}
	}

	/**
	 * Apply a meta attribute if it doesn't already exist
	 *
	 * @param   string  $name       The name of the meta to add
	 * @param   mixed   $value      The value of the meta to apply
	 * @param   string  $attribute  Meta attribute, default is 'property', could also be 'name'
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function conditionallyApplyMeta(string $name, $value, string $attribute = 'property'): void
	{
		/** @var HtmlDocument $doc */
		$doc = $this->getApplication()->getDocument();

		$existing = $doc->getMetaData($name, $attribute);

		if (!empty($existing))
		{
			return;
		}

		$doc->setMetaData($name, $value, $attribute);
	}

	/**
	 * Get a random, unique placeholder for the debug OpenGraph image link
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	private function getDebugLinkPlaceholder(): string
	{
		if (!empty($this->debugLinkPlaceholder))
		{
			return $this->debugLinkPlaceholder;
		}

		$this->debugLinkPlaceholder = '{' . UserHelper::genRandomPassword(32) . '}';

		return $this->debugLinkPlaceholder;
	}
}
