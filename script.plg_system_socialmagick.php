<?php
/**
 * Social Magick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2021 Lucid Fox
 * @license   GNU GPL v3 or later
 */

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Installer\Adapter\PluginAdapter;
use Joomla\CMS\Log\Log as JLog;

class plgSystemSocialmagickInstallerScript
{
	/**
	 * Obsolete files and folders to remove. Use path names relative to the site's root.
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	protected $removeFiles = [
		'files'   => [
		],
		'folders' => [
		],
	];

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

	protected $newInstallation = false;

	/**
	 * Runs before Joomla has the chance to install the plugin
	 *
	 * @param   string         $route
	 * @param   PluginAdapter  $adapter
	 *
	 * @return  bool
	 * @since   1.0.0
	 */
	public function preflight($route, $adapter)
	{
		if (version_compare(PHP_VERSION, '7.2.0', 'lt'))
		{
			JLog::add('You need PHP 7.2.0 or higher to install this plugin.', JLog::WARNING, 'jerror');

			return false;
		}

		if (version_compare(JVERSION, '3.9.0', 'lt'))
		{
			JLog::add('You need Joomla 3.9 to install this plugin.', JLog::WARNING, 'jerror');

			return false;
		}

		if (version_compare(JVERSION, '4.0.999', 'gt'))
		{
			JLog::add('This plugin is not compatible with Joomla 4.1 or later.', JLog::WARNING, 'jerror');

			return false;
		}

		// Clear op-code caches to prevent any cached code issues
		$this->clearOpcodeCaches();

		return true;
	}

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
		// Is this a breand new installation?
		$this->newInstallation = in_array($type, ['install', 'discover', 'discover_install', 'discover_update']);

		// Remove obsolete files and folders on update
		if (!$this->newInstallation)
		{
			$this->removeFilesAndFolders($this->removeFiles);
		}

		// Apply default plugin settings on brand new installation
		if ($this->newInstallation)
		{
			$this->applyDefaultSettings();
		}

		// Clear the opcode caches again - in case someone accessed the extension while the files were being upgraded.
		$this->clearOpcodeCaches();
	}

	/**
	 * Clear PHP opcode caches
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	protected function clearOpcodeCaches()
	{
		// Always reset the OPcache if it's enabled. Otherwise there's a good chance the server will not know we are
		// replacing .php scripts. This is a major concern since PHP 5.5 included and enabled OPcache by default.
		if (function_exists('opcache_reset'))
		{
			/** @noinspection PhpComposerExtensionStubsInspection */
			opcache_reset();
		}
		// Also do that for APC cache
		elseif (function_exists('apc_clear_cache'))
		{
			/** @noinspection PhpComposerExtensionStubsInspection */
			@apc_clear_cache();
		}
	}

	/**
	 * Removes obsolete files and folders
	 *
	 * @param   array  $removeList  The files and directories to remove
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function removeFilesAndFolders($removeList)
	{
		// Remove files
		if (isset($removeList['files']) && !empty($removeList['files']))
		{
			foreach ($removeList['files'] as $file)
			{
				$f = JPATH_ROOT . '/' . $file;

				if (!is_file($f))
				{
					continue;
				}

				File::delete($f);
			}
		}

		// Remove folders
		if (isset($removeList['folders']) && !empty($removeList['folders']))
		{
			foreach ($removeList['folders'] as $folder)
			{
				$f = JPATH_ROOT . '/' . $folder;

				if (!is_dir($f))
				{
					continue;
				}

				Folder::delete($f);
			}
		}
	}

	/**
	 * Apply the default plugin settings on new installation
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function applyDefaultSettings()
	{
		$db       = Factory::getDbo();
		$query    = $db->getQuery(true)
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
