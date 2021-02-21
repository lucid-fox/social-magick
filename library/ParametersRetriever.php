<?php
/**
 * Social Magick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2021 Lucid Fox
 * @license   GNU GPL v3 or later
 */

namespace LucidFox\SocialMagick;

use CategoriesModelCategory;
use ContentModelArticle;
use Exception;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Menu\MenuItem;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Table\Table;
use Joomla\Component\Categories\Administrator\Model\CategoryModel;
use Joomla\Component\Content\Site\Model\ArticleModel;
use Joomla\Registry\Registry;

defined('_JEXEC') || die();

abstract class ParametersRetriever
{
	/**
	 * Default Social Magick parameters for menu items, categories and articles
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private static $defaultParameters = [
		'override'              => '0',
		'generate_images'       => '-1',
		'template'              => '',
		'custom_text'           => '',
		'use_article'           => '1',
		'use_title'             => '1',
		'image_source'          => 'none',
		'image_field'           => '',
		'override_og'           => '0',
		'og_title'              => '-1',
		'og_title_custom'       => '',
		'og_description'        => '-1',
		'og_description_custom' => '',
		'og_url'                => '-1',
		'og_site_name'          => '-1',
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
	private static $menuParameters = [];

	/**
	 * Cached parameters per article ID
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private static $articleParameters = [];

	/**
	 * Cached parameters **FOR ARTICLES** per category ID
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private static $categoryArticleParameters = [];

	/**
	 * Cached parameters **FOR THE CATEGORY** per category ID
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private static $categoryParameters = [];

	/**
	 * Article objects per article ID
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private static $articlesById = [];

	/**
	 * Category objects per category ID
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private static $categoriesById = [];

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
	public static function getMenuParameters(int $id, ?MenuItem $menuItem = null)
	{
		// Return cached results quickly
		if (isset(self::$menuParameters[$id]))
		{
			return self::$menuParameters[$id];
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
			self::$menuParameters[$id] = array_merge([], self::$defaultParameters);

			return self::$menuParameters[$id];
		}

		self::$menuParameters[$id] = self::getParamsFromRegistry($menuItem->getParams());

		return self::$menuParameters[$id];
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
	public static function getArticleParameters(int $id, $article = null): array
	{
		// Return cached results quickly
		if (isset(self::$articleParameters[$id]))
		{
			return self::$articleParameters[$id];
		}

		// If we were given an invalid article object I need to find a new one
		if (empty($article) || !is_object($article) || ($article->id != $id))
		{
			$article = self::getArticleById($id);
		}

		// Get the article parameters
		self::$articleParameters[$id] = self::getParamsFromRegistry(new Registry($article->attribs));

		// If the article doesn't override parameters get category parameters (auto-recursively to parent categories)
		if (self::$articleParameters[$id]['override'] != 1)
		{
			self::$articleParameters[$id] = self::getCategoryArticleParameters($article->catid);
		}

		return self::$articleParameters[$id];
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
	public static function getCategoryArticleParameters(int $id, $category = null): array
	{
		// Return cached results quickly
		if (isset(self::$categoryArticleParameters[$id]))
		{
			return self::$categoryArticleParameters[$id];
		}

		// Get the category object
		if (empty($category) || !is_object($category) || ($category->id != $id))
		{
			$category = self::getCategoryById($id);
		}

		// Get the category's article parameters
		self::$categoryArticleParameters[$id] = self::getParamsFromRegistry(new Registry($category->params), 'socialmagick.article_');

		// If the override option is set for this category we're done. Return now.
		if (self::$categoryArticleParameters[$id]['override'] == 1)
		{
			return self::$categoryArticleParameters[$id];
		}

		// Since there's no override I need to check the parent category for a Social Magick override
		$parentCategory = self::getParentCategory($id);

		// No parent category? I've reached the top and I'm done. Return now.
		if (empty($parentCategory))
		{
			return self::$categoryArticleParameters[$id];
		}

		// Recursively get the parent category's / categories' options until I hit an override switch.
		self::$categoryArticleParameters[$id] = self::getCategoryArticleParameters($parentCategory->id, $parentCategory);

		return self::$categoryArticleParameters[$id];
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
	public static function getCategoryParameters(int $id, $category = null): array
	{
		// Return cached results quickly
		if (isset(self::$categoryParameters[$id]))
		{
			return self::$categoryParameters[$id];
		}

		if (empty($category) || !is_object($category) || ($category->id != $id))
		{
			$category = self::getCategoryById($id);
		}

		// Get the category parameters
		self::$categoryParameters[$id] = self::getParamsFromRegistry(new Registry($category->params), 'socialmagick.category_');

		// If the override option is set for this category we're done. Return now.
		if (self::$categoryParameters[$id]['override'] == 1)
		{
			return self::$categoryParameters[$id];
		}

		// Since there's no override I need to check the parent category for a Social Magick override
		$parentCategory = self::getParentCategory($id);

		// No parent category? I've reached the top and I'm done. Return now.
		if (empty($parentCategory))
		{
			return self::$categoryParameters[$id];
		}

		// Recursively get the parent category's / categories' options until I hit an override switch.
		self::$categoryArticleParameters[$id] = self::getCategoryArticleParameters($parentCategory->id, $parentCategory);

		return self::$categoryParameters[$id];
	}

	/**
	 * Returns an article record given an article ID ID.
	 *
	 * @param   int  $id  The article ID
	 *
	 * @return  ContentModelArticle|ArticleModel|null
	 *
	 * @since   1.0.0
	 */
	public static function getArticleById(int $id)
	{
		if (isset(self::$articlesById[$id]))
		{
			return self::$articlesById[$id];
		}

		/** @var ContentModelArticle|ArticleModel $model */
		try
		{
			if (version_compare(JVERSION, '3.999.999', 'le'))
			{
				if (!class_exists('ContentModelArticle'))
				{
					BaseDatabaseModel::addIncludePath(JPATH_SITE . '/components/com_content/models');
				}

				$model = BaseDatabaseModel::getInstance('Article', 'ContentModel');
			}
			else
			{
				/** @var SiteApplication $app */
				$app = Factory::getApplication();
				/** @var MVCFactoryInterface $factory */
				$factory = $app->bootComponent('com_content')->getMVCFactory();
				/** @var ArticleModel $model */
				$model = $factory->createModel('Article', 'Administrator');
			}

			self::$articlesById[$id] = $model->getItem($id) ?: null;
		}
		catch (Exception $e)
		{
			self::$articlesById[$id] = null;
		}

		return self::$articlesById[$id];
	}

	/**
	 * Get the category object given a category ID.
	 *
	 * @param   int  $id  The category ID
	 *
	 * @return  CategoriesModelCategory|CategoryModel|null
	 *
	 * @since   1.0.0
	 */
	public static function getCategoryById(int $id)
	{
		if (isset(self::$categoriesById[$id]))
		{
			return self::$categoriesById[$id];
		}

		try
		{
			if (version_compare(JVERSION, '3.999.999', 'le'))
			{
				if (!class_exists('CategoriesModelCategory'))
				{
					BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_categories/models');
				}

				if (!class_exists('CategoriesTableCategory'))
				{
					Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_categories/tables');
				}
			}
			if (version_compare(JVERSION, '3.999.999', 'le'))
			{
				/** @var CategoriesModelCategory $model */
				$model = BaseDatabaseModel::getInstance('Category', 'CategoriesModel');
			}
			else
			{
				/** @var SiteApplication $app */
				$app = Factory::getApplication();
				/** @var MVCFactoryInterface $factory */
				$factory = $app->bootComponent('com_categories')->getMVCFactory();
				/** @var CategoryModel $model */
				$model = $factory->createModel('Category', 'Administrator');
			}

			self::$categoriesById[$id] = $model->getItem($id) ?: null;
		}
		catch (Exception $e)
		{
			self::$categoriesById[$id] = null;
		}

		return self::$categoriesById[$id];
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
	private static function getParamsFromRegistry(Registry $params, $namespace = 'socialmagick.'): array
	{
		$parsedParameters = [];

		foreach (self::$defaultParameters as $key => $defaultValue)
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
	 * @return  CategoriesModelCategory|CategoryModel|null
	 *
	 * @since   1.0.0
	 */
	private static function getParentCategory(int $childId)
	{
		/** @var CategoryModel $childCategory */
		$childCategory = self::getCategoryById($childId);

		if (empty($childCategory))
		{
			return null;
		}

		$parentId = $childCategory->parent_id;

		if ($parentId <= 0)
		{
			return null;
		}

		return self::getCategoryById($parentId);
	}
}