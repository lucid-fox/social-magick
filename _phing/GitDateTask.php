<?php
/**
 * Social Magick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2023 Lucid Fox
 * @license   GNU GPL v3 or later
 */

/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

//require_once 'phing/Task.php';

class GitDateTask extends \Phing\Task
{
	/**
	 * Git.date
	 *
	 * @var    string
	 */
	private $propertyName = "git.date";

	/**
	 * The working copy.
	 *
	 * @var        string
	 */
	private $workingCopy;

	/**
	 * The date format. Uses Unix timestamp by default.
	 *
	 * @var    string
	 *
	 * @see        http://www.php.net/manual/en/function.date.php
	 */
	private $format = 'U';

	/**
	 * Sets the path to the working copy
	 *
	 * @param   string  $workingCopy
	 */
	public function setWorkingCopy($workingCopy)
	{
		$this->workingCopy = $workingCopy;
	}

	/**
	 * Returns the path to the working copy
	 *
	 * @return  string
	 */
	public function getWorkingCopy()
	{
		return $this->workingCopy;
	}

	/**
	 * Sets the name of the property to use
	 *
	 * @param   string $propertyName
	 */
	function setPropertyName($propertyName)
	{
		$this->propertyName = $propertyName;
	}

	/**
	 * Returns the name of the property to use
	 *
	 * @return  string
	 */
	function getPropertyName()
	{
		return $this->propertyName;
	}

	/**
	 * Gets the date format
	 *
	 * @return  string
	 */
	function getFormat()
	{
		return $this->format;
	}

	/**
	 * Sets the date format
	 *
	 * @param   string  $format
	 */
	function setFormat($format)
	{
		$this->format = $format;
	}

	/**
	 * The main entry point
	 *
	 * @throws  BuildException
	 */
	function main()
	{
		if ($this->workingCopy == '..')
		{
			$this->workingCopy = '../';
		}

		$cwd               = getcwd();
		$this->workingCopy = realpath($this->workingCopy);

		chdir($this->workingCopy);
		exec('git log --format=%at -n1 ' . escapeshellarg($this->workingCopy), $timestamp);
		chdir($cwd);

		$date = date($this->format, trim($timestamp[0]));
		$this->project->setProperty($this->getPropertyName(), $date);
	}
}
