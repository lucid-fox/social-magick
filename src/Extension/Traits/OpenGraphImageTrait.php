<?php
/**
 * Social Magick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2023 Lucid Fox
 * @license   GNU GPL v3 or later
 */

namespace LucidFox\Plugin\System\SocialMagick\Extension\Traits;

\defined('_JEXEC') || die;

use Exception;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Menu\MenuItem;
use Throwable;

trait OpenGraphImageTrait
{
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
				$article = $this->getParamsRetriever()->getArticleById($this->article);
				$title   = empty($article) ? '' : ($article->title ?? '');
			}
			elseif ($this->category)
			{
				$category = $this->getParamsRetriever()->getCategoryById($this->category);
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
			$contentObject = $this->getParamsRetriever()->getArticleById($this->article);
		}
		elseif ($this->category)
		{
			$contentObject = $this->getParamsRetriever()->getCategoryById($this->category);
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

		$templates      = $this->getHelper()->getTemplates();
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
		$this->getHelper()->applyOGImage($text, $template, $extraImage, $overrideOG);
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
}