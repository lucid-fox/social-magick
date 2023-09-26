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

use Joomla\CMS\Document\HtmlDocument;

trait ConditionalMetaTrait
{
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

}