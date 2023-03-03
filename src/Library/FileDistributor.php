<?php
/**
 * Social Magick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2023 Lucid Fox
 * @license   GNU GPL v3 or later
 */

namespace LucidFox\Plugin\System\SocialMagick\Library;

use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;

/**
 * Handles placing files in distributed folder locations.
 *
 * Putting thousands of files into the same folder increases the file access time on most servers (anything that does
 * not use a filesystem tuned with b-tree directory listing trees) and definitely makes PHP's filesystem functions much
 * slower for that folder (because PHP processes 32KB of directory index content at a time).
 *
 * The solution is to use a distributed or multi-level directory structure.
 *
 * For example, instead of having the file
 * /path/to/0123456789abcdef0123456789abcdef.png
 * we have
 * /path/to/ef/cd/ab/0123456789abcdef0123456789abcdef.png
 *
 * By using a subdirectory level for every last two digits of the generated image filename (i.e. up to 256 directories
 * in each level, since we're using MD5 sums for our image filenames) we can exponentially decrease the number of
 * entries per directory, allowing for fast access.
 *
 * One level of distributed folders is enough for a couple million photos. Two levels are enough for hundreds of
 * millions of photos. Anything bigger is an overkill but you MIGHT need it if you see that there's a bias towards a
 * subset of the subfolders (after all MD5 is NOT totally random or perfectly distributed).
 */
class FileDistributor
{
	/**
	 * Get the absolute path to a file using a distributed folder layout, making sure old files are moved accordingly.
	 *
	 * This method can move files in the following cases:
	 *
	 * - When the distribution level increases e.g. 0 to 1, 1 to 5 etc
	 * - When the distribution level DECREASES e.g. 5 to 1, 3 to 2 etc
	 *
	 * @param   string  $basePath  Absolute filesystem location of the path containing the distributed folders
	 * @param   string  $fileName  Filename of the file to put in a distributed folder
	 * @param   int     $levels    How many levels of directories you want to create
	 *
	 * @return  string
	 */
	public static function ensureDistributed(string $basePath, string $fileName, int $levels = 1): string
	{
		// If the filename contains a path throw an exception
		if ((strpos($fileName, '/') !== false) && (strpos($fileName, DIRECTORY_SEPARATOR) !== false))
		{
			throw new \InvalidArgumentException('Filename cannot include a path');
		}

		$basePath = rtrim($basePath, '/' . DIRECTORY_SEPARATOR) . '/';

		// Get the flat directory structure path
		$flatPath = $basePath . $fileName;

		// Get the distributed path
		$distributedPath = self::getDistributedPath($basePath, $fileName, $levels, false);

		// Make sure the distributed directory structure exists
		$relPath                     = dirname($distributedPath);
		$relPath                     = ($relPath === '.') ? '' : $relPath;
		$absoluteDistributedPath     = rtrim($basePath . $relPath, '/' . DIRECTORY_SEPARATOR);
		$absoluteDistributedPathName = $basePath . $distributedPath;

		if (!@is_dir($absoluteDistributedPath) && !@mkdir($absoluteDistributedPath, 0755, true))
		{
			Folder::create($absoluteDistributedPath, 0755);
		}

		// Hunt for an old, existing file anywhere in the path up to and including to $basePath
		while (!empty($relPath))
		{
			$relPath = dirname($relPath);
			$relPath = $relPath == '.' ? '' : $relPath;
			$relPath = empty($relPath) ? $relPath : ($relPath . '/');

			$sourceFile = $basePath . $relPath . $fileName;

			if (!@file_exists($sourceFile))
			{
				continue;
			}

			// Try to move the file from the legacy directory to the distributed path.
			$didMove = @rename($sourceFile, $absoluteDistributedPathName);

			if (!$didMove)
			{
				$didMove = File::move($sourceFile, $absoluteDistributedPathName);
			}

			return $didMove ? $absoluteDistributedPathName : $sourceFile;
		}

		// If the distributed file exists return its path
		if (@file_exists($absoluteDistributedPathName))
		{
			return $absoluteDistributedPathName;
		}

		// Move files from deeper nested folders back to a lower level, e.g. if you reduced the nesting levels
		for ($newLevel = $levels + 1; $newLevel <= 5; $newLevel++)
		{
			$sourcePath = self::getDistributedPath($basePath, $fileName, $newLevel, true);

			// If there is no such subdirectory finish early.
			if (!@is_dir(dirname($sourcePath)))
			{
				break;
			}

			if (!@file_exists($sourcePath))
			{
				continue;
			}

			// Try to move the file from the legacy directory to the distributed path.
			$didMove = @rename($sourcePath, $absoluteDistributedPathName);

			if (!$didMove)
			{
				$didMove = File::move($sourcePath, $absoluteDistributedPathName);
			}

			return $didMove ? $absoluteDistributedPathName : $sourcePath;
		}

		return $absoluteDistributedPathName;
	}

	public static function getDistributedPath(string $basePath, string $fileName, int $levels = 1, bool $absolute = true): string
	{
		// If the filename contains a path throw an exception
		if ((strpos($fileName, '/') !== false) && (strpos($fileName, DIRECTORY_SEPARATOR) !== false))
		{
			throw new \InvalidArgumentException('Filename cannot include a path');
		}

		// If we don't distribute to levels return the flat directory path
		if (($levels === 0))
		{
			return ($absolute ? (rtrim($basePath, '/' . DIRECTORY_SEPARATOR) . '/') : '') . $fileName;
		}

		// Get the bare name (without an extension)
		$fi        = new \SplFileInfo($fileName);
		$extension = $fi->getExtension();
		$bareName  = $fi->getBasename('.' . $extension);
		unset($fi);

		// Get $levels number of directory levels from the filename
		$paths = [];

		for ($i = 0; $i < $levels; $i++)
		{
			if (strlen($bareName) < 2)
			{
				break;
			}

			$paths[]  = substr($bareName, -2);
			$bareName = substr($bareName, 0, -2);
		}

		// Create the relative path of the file e.g. 'ef/cd/ab/0123456789abcdef0123456789abcdef.png'
		$relativePath = implode('/', $paths) . '/';
		$relativePath = ($relativePath === '/') ? '' : $relativePath;
		$relativePath .= $fileName;

		// Return the filepath distributed in $level subdirectories
		return ($absolute ? (rtrim($basePath, '/' . DIRECTORY_SEPARATOR) . '/') : '') . $relativePath;
	}
}