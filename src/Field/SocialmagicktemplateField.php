<?php
/**
 * Social Magick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2023 Lucid Fox
 * @license   GNU GPL v3 or later
 */

namespace LucidFox\Plugin\System\SocialMagick\Field;

defined('_JEXEC') || die();

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * Select a SocialMagick template
 *
 * @package      Joomla\CMS\Form\Field
 *
 * @since        1.0.0
 * @noinspection PhpUnused
 */
class SocialmagicktemplateField extends ListField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $type = 'Socialmagicktemplate';

	protected function getOptions()
	{
		$templates = [];

		foreach (Factory::getApplication()->triggerEvent('onSocialMagickGetTemplates') as $result)
		{
			if (empty($result) || !is_array($result))
			{
				return [];
			}

			$templates = array_merge($templates, array_keys($result));
		}

		$options = array_map(fn($templateName) => HTMLHelper::_('select.option', $templateName, $templateName), $templates);

		return array_merge($options, parent::getOptions() ?? []);
	}
}