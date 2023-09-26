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

use Joomla\CMS\Event\GenericEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Event\Event;
use Throwable;

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

		try
		{
			$app        = Factory::getApplication();
			$dispatcher = $app->getDispatcher();
		}
		catch (Throwable $e)
		{
			return [];
		}

		$event = new Event('onSocialMagickGetTemplates');
		$results = $dispatcher->dispatch($event->getName(), $event)->getArgument('result', []) ?: [];

		foreach ($results as $result)
		{
			if (empty($result) || !is_array($result))
			{
				return [
					'' => Text::_('PLG_SYSTEM_SOCIALMAGICK_FORM_COMMON_TEMPLATE_DISABLED')
				];
			}

			$templates = array_merge($templates, array_keys($result));
		}

		$options = array_map(fn($templateName) => HTMLHelper::_('select.option', $templateName, $templateName), $templates);

		$options = array_merge($options, parent::getOptions() ?? []);

		if (empty($options))
		{
			return [
				'' => '🚨 TEMPORARILY DISABLED. To enable: set Status to Enabled, click Save. 🚨'
			];
		}

		return $options;
	}
}