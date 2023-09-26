<?php
/**
 * Social Magick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2023 Lucid Fox
 * @license   GNU GPL v3 or later
 */

namespace LucidFox\Plugin\System\SocialMagick\Library;

use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Menu\MenuItem;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Component\Categories\Administrator\Model\CategoryModel;
use Joomla\Component\Content\Site\Model\ArticleModel;
use Joomla\Registry\Registry;

defined('_JEXEC') || die();

final class ParametersRetriever
{
	/**
	 * Default Social Magick parameters for menu items, categories and articles
	 *
	 * @since 1.0.0
	 */
	private array $defaultParameters = [
		'override'              => '0',
		'generate_images'       => '-1',
		'template'              => '',
		'custom_text'           => '',
		'use_article'           => '1',
		'use_title'             => '1',
		'image_source'          => 'fulltext',
		'image_field'           => '',
		'override_og'           => '0',
		'og_title'              => '-1',
		'og_title_custom'       => '',
		'og_description'        => '-1',
		'og_description_custom' => '',
		'og_url'                => '-1',
		'og_site_name'          => '-1',
		'static_image'          => '',
		'twitter_card'          => '-1',
		'twitter_site'          => '',
		'twitter_creator'       => '',
		'fb_app_id'             => '',
	];

	/**
	 * Cached parameters per menu item
	 *
	 * @var   array[]
	 * @since 1.0.0
	 */
	private array $menuParameters = [];

	/**
	 * Cached parameters per article ID
	 *
	 * @since 1.0.0
	 */
	private array $articleParameters = [];

	/**
	 * Cached parameters **FOR ARTICLES** per category ID
	 *
	 * @since 1.0.0
	 */
	private array $categoryArticleParameters = [];

	/**
	 * Cached parameters **FOR THE CATEGORY** per category ID
	 *
	 * @since 1.0.0
	 */
	private array $categoryParameters = [];

	/**
	 * Article objects per article ID
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private $articlesById = [];

	/**
	 * Category objects per category ID
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private $categoriesById = [];

	/**
	 * The CMS application we're running under
	 *
	 * @var   CMSApplication
	 * @since 2.0.0
	 */
	private CMSApplication $application;

	/**
	 * Public constructor
	 *
	 * @param   CMSApplication  $application  The application we're running under
	 *
	 * @since   2.0.0
	 */
	public function __construct(CMSApplication $application)
	{
		$this->application = $application;
	}


	/**
	 * Get the Social Magick parameters for a menu item.
	 *
	 * @param   int            $id        Menu item ID
	 * @param   MenuItem|null  $menuItem  The menu item object, if available.
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function getMenuParameters(int $id, ?MenuItem $menuItem = null): array
	{
		// Return cached results quickly
		if (isset($this->menuParameters[$id]))
		{
			return $this->menuParameters[$id];
		}

		// If there is no menu item or it's the wrong one retrieve it from Joomla
		if (empty($menuItem) || ($menuItem->id != $id))
		{
			$menu     = AbstractMenu::getInstance('site');
			$menuItem = $menu->getItem($id);
		}

		// Still no menu item? We return the default parameters.
		if (empty($menuItem) || ($menuItem->id != $id))
		{
			// This trick allows us to copy an array without creating a reference to the original.
			$this->menuParameters[$id] = array_merge([], $this->defaultParameters);

			return $this->menuParameters[$id];
		}

		$this->menuParameters[$id] = $this->getParamsFromRegistry($menuItem->getParams());

		return $this->menuParameters[$id];
	}

	/**
	 * Get the Social Magick parameters for an article.
	 *
	 * If the article doesn't define an override for the Social Magick parameters we check its category. If the category
	 * doesn't define an override we walk through all of its parent categories until we find an override or reach a top
	 * level category.
	 *
	 * @param   int   $id       The article ID.
	 * @param   null  $article  The article object, if you have it, thank you.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function getArticleParameters(int $id, $article = null): array
	{
		// Return cached results quickly
		if (isset($this->articleParameters[$id]))
		{
			return $this->articleParameters[$id];
		}

		// If we were given an invalid article object I need to find a new one
		if (empty($article) || !is_object($article) || ($article->id != $id))
		{
			$article = $this->getArticleById($id);
		}

		// Get the article parameters
		$this->articleParameters[$id] = $this->getParamsFromRegistry(new Registry($article->attribs));

		// If the article doesn't override parameters get category parameters (auto-recursively to parent categories)
		if ($this->articleParameters[$id]['override'] != 1)
		{
			$this->articleParameters[$id] = $this->getCategoryArticleParameters($article->catid);
		}

		return $this->articleParameters[$id];
	}

	/**
	 * Get the Social Magick parameters for the articles contained in a category.
	 *
	 * If the category does not define an override we walk through all of its parent categories until we find an
	 * override or reach a top level category.
	 *
	 * @param   int   $id        The category ID.
	 * @param   null  $category  The category object, if you have it, thank you.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function getCategoryArticleParameters(int $id, $category = null): array
	{
		// Return cached results quickly
		if (isset($this->categoryArticleParameters[$id]))
		{
			return $this->categoryArticleParameters[$id];
		}

		// Get the category object
		if (empty($category) || !is_object($category) || ($category->id != $id))
		{
			$category = $this->getCategoryById($id);
		}

		// Get the category's article parameters
		$this->categoryArticleParameters[$id] = $this->getParamsFromRegistry(new Registry($category->params), 'socialmagick.article_');

		// If the override option is set for this category we're done. Return now.
		if ($this->categoryArticleParameters[$id]['override'] == 1)
		{
			return $this->categoryArticleParameters[$id];
		}

		// Since there's no override I need to check the parent category for a Social Magick override
		$parentCategory = $this->getParentCategory($id);

		// No parent category? I've reached the top and I'm done. Return now.
		if (empty($parentCategory))
		{
			return $this->categoryArticleParameters[$id];
		}

		// Recursively get the parent category's / categories' options until I hit an override switch.
		$this->categoryArticleParameters[$id] = $this->getCategoryArticleParameters($parentCategory->id, $parentCategory);

		return $this->categoryArticleParameters[$id];
	}

	/**
	 * Get the Social Magick parameters for the category itself.
	 *
	 * If the category does not define an override we walk through all of its parent categories until we find an
	 * override or reach a top level category.
	 *
	 * @param   int   $id        The category ID.
	 * @param   null  $category  The category object, if you have it, thank you.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function getCategoryParameters(int $id, $category = null): array
	{
		// Return cached results quickly
		if (isset($this->categoryParameters[$id]))
		{
			return $this->categoryParameters[$id];
		}

		if (empty($category) || !is_object($category) || ($category->id != $id))
		{
			$category = $this->getCategoryById($id);
		}

		// Get the category parameters
		$this->categoryParameters[$id] = $this->getParamsFromRegistry(new Registry($category->params), 'socialmagick.category_');

		// If the override option is set for this category we're done. Return now.
		if ($this->categoryParameters[$id]['override'] == 1)
		{
			return $this->categoryParameters[$id];
		}

		// Since there's no override I need to check the parent category for a Social Magick override
		$parentCategory = $this->getParentCategory($id);

		// No parent category? I've reached the top and I'm done. Return now.
		if (empty($parentCategory))
		{
			return $this->categoryParameters[$id];
		}

		// Recursively get the parent category's / categories' options until I hit an override switch.
		$this->categoryArticleParameters[$id] = $this->getCategoryArticleParameters($parentCategory->id, $parentCategory);

		return $this->categoryParameters[$id];
	}

	/**
	 * Returns an article record given an article ID ID.
	 *
	 * @param   int  $id  The article ID
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function getArticleById(int $id): ?object
	{
		if (isset($this->articlesById[$id]))
		{
			return $this->articlesById[$id];
		}

		/** @var ArticleModel $model */
		try
		{
			/** @var MVCFactoryInterface $factory */
			$factory = $this->application->bootComponent('com_content')->getMVCFactory();
			/** @var ArticleModel $model */
			$model = $factory->createModel('Article', 'Administrator');

			$this->articlesById[$id] = $model->getItem($id) ?: null;
		}
		catch (Exception $e)
		{
			$this->articlesById[$id] = null;
		}

		return $this->articlesById[$id];
	}

	/**
	 * Get the category object given a category ID.
	 *
	 * @param   int  $id  The category ID
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function getCategoryById(int $id): ?object
	{
		if (isset($this->categoriesById[$id]))
		{
			return $this->categoriesById[$id];
		}

		try
		{
			/** @var MVCFactoryInterface $factory */
			$factory = $this->application->bootComponent('com_categories')->getMVCFactory();
			/** @var CategoryModel $model */
			$model = $factory->createModel('Category', 'Administrator');

			$this->categoriesById[$id] = $model->getItem($id) ?: null;
		}
		catch (Exception $e)
		{
			$this->categoriesById[$id] = null;
		}

		return $this->categoriesById[$id];
	}

	/**
	 * Retrieve the parameters from a Registry object, respecting the default values set at the top of the class.
	 *
	 * @param   Registry  $params     The Joomla Registry object which contains our parameters namespaced.
	 * @param   string    $namespace  The Joomla Registry namespace for our parameters
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 */
	private function getParamsFromRegistry(Registry $params, string $namespace = 'socialmagick.'): array
	{
		$parsedParameters = [];

		foreach ($this->defaultParameters as $key => $defaultValue)
		{
			$parsedParameters[$key] = $params->get($namespace . $key, $defaultValue);
		}

		return $parsedParameters;
	}

	/**
	 * Get the parent category object given a child's category ID
	 *
	 * @param   int  $childId
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	private function getParentCategory(int $childId): ?object
	{
		/** @var CategoryModel $childCategory */
		$childCategory = $this->getCategoryById($childId);

		if (empty($childCategory))
		{
			return null;
		}

		$parentId = $childCategory->parent_id;

		if ($parentId <= 0)
		{
			return null;
		}

		return $this->getCategoryById($parentId);
	}
}