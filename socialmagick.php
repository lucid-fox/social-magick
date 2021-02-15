<?php
/*
 * SocialMagick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2021 Lucid Fox
 * @license   GNU GPL v3 or later
 */

defined('_JEXEC') || die();

use Joomla\CMS\Plugin\CMSPlugin;

/**
 * System plugin to automatically generate Open Graph images
 *
 * @package     ogimages
 *
 * @since       1.0.0
 *
 * @noinspection PhpUnused
 */
class plgSystemSocialmagick extends CMSPlugin
{
	public function __construct(&$subject, $config = [])
	{
		// Register the autoloader for the library
		if (version_compare(JVERSION, '3.999.999', 'le'))
		{
			JLoader::registerNamespace('LucidFox\\SocialMagick', __DIR__ . '/library', false, false, 'psr4');
		}
		else
		{
			JLoader::registerNamespace('LucidFox\\SocialMagick', __DIR__ . '/library', false, false, 'psr4');
		}

		parent::__construct($subject, $config);
	}

}