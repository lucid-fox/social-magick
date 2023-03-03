<?php
/**
 * Social Magick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2023 Lucid Fox
 * @license   GNU GPL v3 or later
 */

defined('_JEXEC') || die;

use Joomla\CMS\Installer\Adapter\PluginAdapter;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\Database\DatabaseDriver;

class plgSystemSocialmagickInstallerScript extends InstallerScript
{
	protected $minimumJoomla = '4.2.0';

	protected $minimumPhp = '7.4.0';

	protected $defaultSettingsJson = <<< JSON
{
	"og-templates": {
		"og-templates0": {
			"template-name": "Overlay",
			"base-image": "plugins\\/system\\/socialmagick\\/images\\/overlay.png",
			"template-w": 1200,
			"template-h": 640,
			"base-color": "#000000",
			"base-color-alpha": "100",
			"text-font": "OpenSans-Bold.ttf",
			"font-size": 32,
			"text-color": "#ffffff",
			"text-height": 270,
			"text-width": 500,
			"text-align": "center",
			"text-y-center": "1",
			"text-y-adjust": -40,
			"text-y-absolute": "",
			"text-x-center": "1",
			"text-x-adjust": 0,
			"text-x-absolute": "",
			"use-article-image": "1",
			"image-z": "under",
			"image-cover": "1",
			"image-width": 1200,
			"image-height": 630,
			"image-x": 0,
			"image-y": 0
		},
		"og-templates1": {
			"template-name": "Solid",
			"base-image": "plugins\\/system\\/socialmagick\\/images\\/solid.png",
			"template-w": 1200,
			"template-h": 640,
			"base-color": "#000000",
			"base-color-alpha": "100",
			"text-font": "OpenSans-Bold.ttf",
			"font-size": 32,
			"text-color": "#ffffff",
			"text-height": 280,
			"text-width": 600,
			"text-align": "center",
			"text-y-center": "1",
			"text-y-adjust": 0,
			"text-y-absolute": "",
			"text-x-center": "1",
			"text-x-adjust": 0,
			"text-x-absolute": "",
			"use-article-image": "0",
			"image-z": "under",
			"image-cover": "1",
			"image-width": 1200,
			"image-height": 630,
			"image-x": 0,
			"image-y": 0
		},
		"og-templates2": {
			"template-name": "Cutout",
			"base-image": "plugins\\/system\\/socialmagick\\/images\\/cutout.png",
			"template-w": 1200,
			"template-h": 640,
			"base-color": "#000000",
			"base-color-alpha": "100",
			"text-font": "OpenSans-Bold.ttf",
			"font-size": 32,
			"text-color": "#ffffff",
			"text-height": 415,
			"text-width": 430,
			"text-align": "left",
			"text-y-center": "1",
			"text-y-adjust": -20,
			"text-y-absolute": "",
			"text-x-center": "0",
			"text-x-adjust": 165,
			"text-x-absolute": 165,
			"use-article-image": "1",
			"image-z": "under",
			"image-cover": "0",
			"image-width": 420,
			"image-height": 420,
			"image-x": 660,
			"image-y": 90
		}
	},
	"output_folder": "images\\/og-generated",
	"quality": "95",
	"devmode": "0",
	"textdebug": "0",
	"library": "auto"
}
JSON;

	/**
	 * Runs after install, update or discover_update. In other words, it executes after Joomla! has finished installing
	 * or updating your plugin. This is the last chance you've got to perform any additional installations, clean-up,
	 * database updates and similar housekeeping functions.
	 *
	 * @param   string         $type     install, update or discover_update
	 * @param   PluginAdapter  $adapter  Parent object
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function postflight($type, $adapter)
	{
		// Apply default plugin settings on brand-new installation
		if (in_array($type, ['install', 'discover', 'discover_install', 'discover_update']))
		{
			$this->applyDefaultSettings($adapter->getParent()->getDbo());
		}
	}

	/**
	 * Apply the default plugin settings on new installation
	 *
	 * @param   DatabaseDriver  $db  The database driver
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function applyDefaultSettings(DatabaseDriver $db)
	{
		$query = $db->getQuery(true)
			->update($db->qn('#__extensions'))
			->set($db->qn('params') . ' = ' . $db->q($this->defaultSettingsJson))
			->where($db->qn('type') . ' = ' . $db->q('plugin'))
			->where($db->qn('element') . ' = ' . $db->q('socialmagick'))
			->where($db->qn('folder') . ' = ' . $db->q('system'));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// No problem if this fails
		}
	}
}
