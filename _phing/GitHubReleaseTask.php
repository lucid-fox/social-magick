<?php
/**
 * Social Magick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2021 Lucid Fox
 * @license   GNU GPL v3 or later
 */

/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

if (!class_exists('GitHubTask'))
{
	require_once __DIR__ . '/library/GitHubTask.php';
}

/**
 * Create or edit a release
 *
 * See https://developer.github.com/v3/repos/releases/#create-a-release for information on properties.
 */
class GitHubReleaseTask extends GitHubTask
{
	/**
	 * Tag name upon which the release is made.
	 *
	 * @var   string
	 */
	protected $tagName;

	/**
	 * Specifies the commitish value that determines where the Git tag is created from. Can be any branch or
	 * commit SHA. Unused if the Git tag already exists. Default: the repository's default branch (usually
	 * master).
	 *
	 * Note: it's best if you create the tag before the release.
	 *
	 * @var   string
	 */
	protected $commitish;

	/**
	 * The name of the release. Typically that would be the version number in the format "v.1.2.3"
	 *
	 * @var   string
	 */
	protected $releaseName;

	/**
	 * Text describing the contents of the tag. Typically that would be the release notes. I believe that
	 * can be Markdown.
	 *
	 * @var   string
	 */
	protected $releaseBody;

	/**
	 * True to create a draft (unpublished) release, false to create a published one. Default: false
	 *
	 * @var   bool
	 */
	protected $draft = false;

	/**
	 * true to identify the release as a prerelease. false to identify the release as a full release.
	 * Default: false
	 *
	 * Note: if you provide a release or tag name that starts with "v.0.", "0.", "dev", "rev", "git" or ends
	 * in ".a#", ".b#" or ".rc#" (where # is an optional integer) this flag is automatically set. Therefore
	 * make sure you set this attribute AFTER the tag name and release name in your XML file.
	 *
	 * @var   bool
	 */
	protected $prerelease = false;

	/**
	 * The property name which will receive the GitHub release ID once it's fetched from GitHub.
	 *
	 * Default: github.release.id
	 *
	 * @var   string
	 */
	protected $propName = 'github.release.id';

	/**
	 * Called by the project to let the task do its work.
	 *
	 * @throws   BuildException  If an build error occurs.
	 */
	public function main()
	{
		parent::main();

		// Convert Phing attributes to GitHub API parameters.
		$map    = [
			'tagName'     => 'tag_name',
			'commitish'   => 'target_commitish',
			'releaseName' => 'name',
			'releaseBody' => 'body',
			'draft'       => 'draft',
			'prerelease'  => 'prerelease',
		];

		$apiParameters = [];

		foreach ($map as $phingProperty => $apiParameter)
		{
			if (!isset($this->$phingProperty))
			{
				continue;
			}

			$apiParameters[$apiParameter] = $this->$phingProperty;
		}

		// Does the release exist?
		$release = $this->getReleaseByTag($apiParameters['tag_name']);

		if (empty($release))
		{
			$release = $this->createRelease($apiParameters);

			$this->log(sprintf('Created release for tag %s (ID#: %d)', $release['tag_name'], $release['id']));
		}
		else
		{
			$this->log(sprintf('Found release for tag %s (ID#: %d)', $release['tag_name'], $release['id']));

			// Do I have to edit the release?
			$mustEdit = false;

			foreach ($apiParameters as $key => $value)
			{
				if (!isset($release[$key]))
				{
					continue;
				}

				if ($release[$key] != $value)
				{
					$mustEdit = true;

					break;
				}
			}

			if (!$mustEdit)
			{
				$this->log('There is no need to edit the release; skipping');
			}
			else
			{
				$release = $this->editRelease($release['id'], $apiParameters);
				$this->log(sprintf('Edited release for tag %s (ID#: %d)', $release['tag_name'], $release['id']));
			}
		}

		if (!empty($this->propName))
		{
			$this->log(sprintf('Assigning release ID to property %s', $this->propName));
			$this->project->setProperty($this->propName, $release['id']);
		}
	}

	/**
	 * Set the tag name. Required.
	 *
	 * @param   string  $tagName
	 *
	 * @return  void
	 */
	public function setTagName($tagName)
	{
		$this->tagName = $tagName;

		if ($this->isPrereleaseVersion($tagName))
		{
			$this->setPrerelease(true);
		}
	}

	/**
	 * Set the commitish
	 *
	 * @param   string  $commitsh
	 *
	 * @return  void
	 */
	public function setCommitish($commitsh)
	{
		$this->commitish = $commitsh;
	}

	/**
	 * Set the release name
	 *
	 * @param   string  $releaseName
	 *
	 * @return  void
	 */
	public function setReleaseName($releaseName)
	{
		$this->releaseName = $releaseName;

		if ($this->isPrereleaseVersion($releaseName))
		{
			$this->setPrerelease(true);
		}
	}

	/**
	 * Set the release body
	 *
	 * @param   string  $releaseBody
	 *
	 * @return  void
	 */
	public function setReleaseBody($releaseBody)
	{
		$this->releaseBody = $releaseBody;
	}

	/**
	 * Set the draft (unpublished) flag
	 *
	 * @param   bool  $draft
	 *
	 * @return  void
	 */
	public function setDraft($draft)
	{
		$this->draft = (bool) $draft;
	}

	/**
	 * Set the pre-release flag
	 *
	 * @param   bool  $prerelease
	 *
	 * @return  void
	 */
	public function setPrerelease($prerelease)
	{
		$this->prerelease = $prerelease;
	}

	/**
	 * Set the property name for the release ID
	 *
	 * @param   string  $id
	 *
	 * @return  void
	 */
	public function setPropName($id)
	{
		$this->propName = $id;
	}

	/**
	 * Check if the provided $tag implies that this is a pre-release.
	 *
	 * @param   string  $tag
	 *
	 * @return  bool
	 */
	private function isPrereleaseVersion(string $tag): bool
	{
		// Convert the version tag to lower case
		$tag = trim(strtolower($tag));

		// Remove any "v." or "v" prefix
		if (substr($tag, 0, 2) == 'v.')
		{
			$tag = trim(substr($tag, 2));
		}

		if (substr($tag, 0, 1) == 'v')
		{
			$tag = trim(substr($tag, 1));
		}

		// If the version tag begins with rev, dev, git or svn it's a prerelease
		if (in_array(substr($tag, 0, 3), ['rev', 'dev', 'git', 'svn']))
		{
			return true;
		}

		// If it's a 0.x version it's a prerelease
		if (substr($tag, 0, 3) == '0.')
		{
			return true;
		}

		// Look for a suffix after a dash, e.g. 1.2.3-beta or 1.2.3-b1 (you are bonkers or a Joomla! PLT member)
		$parts = explode('-', $tag);

		if (count($parts) > 1)
		{
			$suffix = array_pop($parts);
			$suffix = preg_replace('/\d+/u', '', $suffix);

			if (in_array($suffix, ['a', 'b', 'alpha', 'beta', 'rc', 'dev', 'test']))
			{
				return true;
			}
		}

		// Look for a suffix after a dot, e.g. 1.2.3.beta or 1.2.3.b1 (recommended)
		$parts = explode('.', $tag);

		if (count($parts) > 1)
		{
			$suffix = array_pop($parts);
			$suffix = preg_replace('/\d+/u', '', $suffix);

			if (in_array($suffix, ['a', 'b', 'alpha', 'beta', 'rc', 'dev', 'test']))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Find the first matching release for a given tag.
	 *
	 * @param   string  $tag  The tag to get the release for
	 *
	 * @return  array|null  The release or, if it doesn't exist, null
	 */
	private function getReleaseByTag(string $tag)
	{
		/**
		 * Using ->tag() does not return releases which are currently draft. As a result releasing software never works
		 * since we end up trying to create yet another release with the same tag as the one we had in draft status. So
		 * I have to do it the entirely stupid way, iterating through all releases manually. WTF...
		 */
		try
		{
			$releases = $this->client->api('repo')->releases()->all(
				$this->organization,
				$this->repository
			);
		}
		catch (Http\Client\Exception\HttpException $e)
		{
			if ($e->getCode() == 404)
			{
				return null;
			}

			throw $e;
		}

		foreach ($releases as $aRelease)
		{
			if ($aRelease['tag_name'] == $tag)
			{
				return $aRelease;
			}
		}

		return null;
	}

	/**
	 * Creates a new release based on the provided GitHub API parameters.
	 *
	 * @param   array   $apiParameters  The GitHub API parameters
	 *
	 * @return  array
	 */
	private function createRelease(array $apiParameters)
	{
		$release = $this->client->api('repo')->releases()->create(
			$this->organization,
			$this->repository,
			$apiParameters
		);

		return $release;
	}

	/**
	 * Modifies an existing release to the provided GitHub API parameters.
	 *
	 * @param   int     $id             The release ID to edit
	 * @param   array   $apiParameters  The GitHub API parameters
	 *
	 * @return  array
	 */
	private function editRelease(int $id, array $apiParameters)
	{
		$release = $this->client->api('repo')->releases()->edit(
			$this->organization,
			$this->repository,
			$id,
			$apiParameters
		);

		return $release;
	}
}
