<?php
/*
 * Social Magick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2021 Lucid Fox
 * @license   GNU GPL v3 or later
 */

defined('_JEXEC') || die;

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

	/**
	 * Runs before Joomla has the chance to install the plugin
	 *
	 * @param   string         $route
	 * @param   PluginAdapter  $adapter
	 *
	 * @return  bool
	 * @since   1.0.0.b1
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
	 * @since   1.0.0.b1
	 */
	public function postflight($type, $adapter)
	{
		// Remove obsolete files and folders
		$this->removeFilesAndFolders($this->removeFiles);
	}

	/**
	 * Removes obsolete files and folders
	 *
	 * @param   array  $removeList  The files and directories to remove
	 *
	 * @return  void
	 * @since   1.0.0.b1
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
}
