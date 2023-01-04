<?php
/**
 * Social Magick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2023 Lucid Fox
 * @license   GNU GPL v3 or later
 */

defined('_JEXEC') || die();

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;

FormHelper::loadFieldClass('list');

/**
 * Select a SocialMagick template
 *
 * @package      Joomla\CMS\Form\Field
 *
 * @since        1.0.0
 * @noinspection PhpUnused
 */
class JFormFieldSocialmagicktemplate extends JFormFieldList
{
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

		$options = array_map(function($templateName) {
			return HTMLHelper::_('select.option', $templateName, $templateName);
		}, $templates);

		return array_merge($options, parent::getOptions() ?? []);
	}

}