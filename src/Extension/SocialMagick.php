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
use Joomla\CMS\Form\Form;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use LucidFox\Plugin\System\SocialMagick\Extension\Traits\ConditionalMetaTrait;
use LucidFox\Plugin\System\SocialMagick\Extension\Traits\DebugPlaceholderTrait;
use LucidFox\Plugin\System\SocialMagick\Extension\Traits\ImageGeneratorHelperTrait;
use LucidFox\Plugin\System\SocialMagick\Extension\Traits\OpenGraphImageTrait;
use LucidFox\Plugin\System\SocialMagick\Extension\Traits\ParametersRetrieverTrait;
use Throwable;

/**
 * System plugin to automatically generate Open Graph images
 *
 * @since        1.0.0
 *
 * @noinspection PhpUnused
 */
class SocialMagick extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
	use DatabaseAwareTrait;
	use ConditionalMetaTrait;
	use DebugPlaceholderTrait;
	use OpenGraphImageTrait;
	use ImageGeneratorHelperTrait;
	use ParametersRetrieverTrait;

	/**
	 * The com_content article ID being rendered, if applicable.
	 *
	 * @var   int|null
	 * @since 1.0.0
	 */
	protected ?int $article = null;

	/**
	 * The com_content category ID being rendered, if applicable.
	 *
	 * @var   int|null
	 * @since 1.0.0
	 */
	protected ?int $category = null;

	/**
	 * The placeholder variable to be replaced by the image link when Debug Link is enabled.
	 *
	 * @var  string
	 */
	protected string $debugLinkPlaceholder = '';

	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		return [
			'onAfterRender'              => 'onAfterRender',
			'onAjaxSocialmagick'         => 'onAjaxSocialmagick',
			'onBeforeRender'             => 'onBeforeRender',
			'onContentBeforeDisplay'     => 'onContentBeforeDisplay',
			'onContentBeforeSave'        => 'onContentBeforeSave',
			'onContentPrepareData'       => 'onContentPrepareData',
			'onContentPrepareForm'       => 'onContentPrepareForm',
			'onSocialMagickGetTemplates' => 'onSocialMagickGetTemplates',
		];
	}

	/**
	 * Runs when Joomla is preparing a form. Used to add extra form fieldsets to core pages.
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 *
	 * @since        1.0.0
	 */
	public function onContentPrepareForm(Event $event): void
	{
		/**
		 * @var Form  $form The form to be altered.
		 * @var mixed $data The associated data for the form.
		 */
		[$form, $data] = array_values($event->getArguments());
		$result   = $event->getArgument('result') ?: [];
		$result   = is_array($result) ? $result : [$result];
		$result[] = true;

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

		$event->setArgument('result', $result);
	}

	/**
	 * Triggered when Joomla is saving content. Used to save the SocialMagick configuration.
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 * @since        1.0.0
	 */
	public function onContentBeforeSave(Event $event): void
	{
		/**
		 * @var   string|null       $context Context for the content being saved
		 * @var   Table|object      $table   Joomla table object where the content is being saved to
		 * @var   bool              $isNew   Is this a new record?
		 * @var   object|array|null $data    Data being saved (Joomla 4)
		 */
		[$context, $table, $isNew, $data] = array_values($event->getArguments());
		$result   = $event->getArgument('result') ?: [];
		$result   = is_array($result) ? $result : [$result];
		$result[] = true;
		$event->setArgument('result', $result);

		$data = (array) $data;

		// Make sure I have data to save
		if (!isset($data['socialmagick']))
		{
			return;
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
			return;
		}

		$params        = @json_decode($table->{$key}, true) ?? [];
		$table->{$key} = json_encode(array_merge($params, ['socialmagick' => $data['socialmagick']]));
	}

	/**
	 * Triggered when Joomla is loading content. Used to load the Social Magick configuration.
	 *
	 * This is used for both articles and article categories.
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onContentPrepareData(Event $event): void
	{
		/**
		 * @var   string|null  $context Context for the content being loaded
		 * @var   object|array $data    Data being saved
		 */
		[$context, $data] = array_values($event->getArguments());
		$result   = $event->getArgument('result') ?: [];
		$result   = is_array($result) ? $result : [$result];
		$result[] = true;
		$event->setArgument('result', $result);

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
			return;
		}

		if (!isset($data->{$key}) || !isset($data->{$key}['socialmagick']))
		{
			return;
		}

		$data->socialmagick = $data->{$key}['socialmagick'];
		unset ($data->{$key}['socialmagick']);
	}

	/**
	 * Returns all Social Magick templates known to the plugin
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 *
	 * @since        1.0.0
	 */
	public function onSocialMagickGetTemplates(Event $event): void
	{
		$result   = $event->getArgument('result') ?: [];
		$result   = is_array($result) ? $result : [$result];
		$result[] = $this->getHelper()->getTemplates();
		$event->setArgument('result', $result);
	}

	/**
	 * Runs before Joomla renders the HTML document.
	 *
	 * This is the main event where Social Magick evaluates whether to apply an Open Graph image to the document.
	 *
	 * @return  void
	 *
	 * @since        1.0.0
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpUnused
	 */
	public function onBeforeRender(Event $event): void
	{
		// Is this plugin even supported?
		if (!$this->getHelper()->isAvailable())
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
		$params = $this->getParamsRetriever()->getMenuParameters($currentItem->id, $currentItem);

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
						$catParams = $this->getParamsRetriever()->getCategoryParameters($category);

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
						$articleParams = $this->getParamsRetriever()->getArticleParameters($article);

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
		$templateKeys    = array_keys($this->getHelper()->getTemplates() ?? []);
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
	 * @param   Event  $event
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function onContentBeforeDisplay(Event $event): void
	{
		[$context, $row, $params] = array_values($event->getArguments());
		$result = $event->getArgument('result') ?: [];
		$result = is_array($result) ? $result : [$result];

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
			return;
		}

		if (!in_array($context, ['com_content.article', 'com_content.category', 'com_content.categories']))
		{
			return;
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
			$result[] = $this->getDebugLinkPlaceholder();

			$event->setArgument('result', $result);

			return;
		}
	}

	/**
	 * Runs after rendering the document but before outputting it to the browser.
	 *
	 * Used to add the OpenGraph declaration to the document head and applying the debug image link.
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 * @since        1.0.0
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function onAfterRender(Event $event): void
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
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function onAjaxSocialmagick(Event $event)
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
			$this->getHelper()->deleteOldImages($days, $maxExec);
		}
		catch (Exception $e)
		{
			header('HTTP/1.0 500 Internal Server Error');

			echo $e->getCode() . ' ' . $e->getMessage();

			return;
		}

		echo "OK";
	}
}