<?php
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
 * Upload an asset to a release. If the asset exists it is re-uploaded.
 *
 * See https://developer.github.com/v3/repos/releases/#upload-a-release-asset for information on properties.
 */
class GitHubAssetTask extends GitHubTask
{
	/**
	 * The ID of the release where the assets will be uploaded to
	 *
	 * @var   int
	 */
	protected $releaseId = null;

	/**
	 * Content type for the file. Defaults to MIME auto-detection.
	 *
	 * @var   string
	 */
	protected $contentType;

	/**
	 * The file name as it will be reported by GitHub. Defaults to the basename of the uploaded file.
	 *
	 * @var   string
	 */
	protected $remoteName = '';

	/**
	 * The description of the file shown in the GitHub release page instead of the filename
	 *
	 * @var   string
	 */
	protected $label = '';

	/**
	 * The full path to the file to upload
	 *
	 * @var   string
	 */
	protected $file = '';

	/**
	 * The Phing property I will set with the public download URL of the asset
	 *
	 * @var   string
	 */
	protected $downloadProperty = 'github.asset.url';

	/**
	 * The Phing property I will set with the GitHub ID of the asset
	 *
	 * @var   string
	 */
	protected $idProperty = 'github.asset.id';

	/**
	 * Called by the project to let the task do its work.
	 *
	 * @throws   BuildException  If an build error occurs.
	 */
	public function main()
	{
		parent::main();

		$fileName  = $this->getFile();
		$releaseId = $this->getReleaseId();
		$asset     = $this->client
			->api('repo')
			->releases()
			->assets()
			->create(
				$this->organization, $this->repository, $releaseId,
				$this->getRemoteName(), $this->getContentType(), file_get_contents($fileName));

		$assetId  = $asset['id'];
		$assetUrl = $asset['browser_download_url'];

		$this->log(sprintf('Created asset for release %d, source file %s (ID#: %d)', $releaseId, $this->getFile(), $assetId));

		if (!empty($this->idProperty))
		{
			$this->log(sprintf('Assigning asset ID to property %s', $this->idProperty));
			$this->project->setProperty($this->idProperty, $assetId);
		}

		if (!empty($this->downloadProperty))
		{
			$this->log(sprintf('Assigning download URL to property %s', $this->downloadProperty));
			$this->project->setProperty($this->downloadProperty, $assetUrl);
		}
	}

	/**
	 * Set the Release ID we're uploading to
	 *
	 * @param   int  $releaseId
	 *
	 * @return  void
	 */
	public function setReleaseId(int $releaseId)
	{
		$this->releaseId = $releaseId;
	}

	/**
	 * Set the MIME content type of the uploaded file
	 *
	 * @param   string  $contentType
	 *
	 * @return  void
	 */
	public function setContentType(string $contentType)
	{
		$this->contentType = $contentType;
	}

	/**
	 * Set the filename on GitHub
	 *
	 * @param   string  $remoteName
	 *
	 * @return  void
	 */
	public function setRemoteName(string $remoteName)
	{
		$this->remoteName = $remoteName;
	}

	/**
	 * Set the label which will be used instead of the filename on GitHub
	 *
	 * @param   string  $label
	 *
	 * @return  void
	 */
	public function setLabel(string $label)
	{
		$this->label = $label;
	}

	/**
	 * Set the file to upload
	 *
	 * @param   string  $file
	 *
	 * @return  void
	 */
	public function setFile(string $file)
	{
		$this->file = $file;
	}

	/**
	 * Set the Phing property to save the public download URL to
	 *
	 * @param   string  $downloadProperty
	 *
	 * @return  void
	 */
	public function setDownloadProperty(string $downloadProperty)
	{
		$this->downloadProperty = $downloadProperty;
	}

	/**
	 * Set the Phing property to save the asset ID to
	 *
	 * @param   string  $idProperty
	 *
	 * @return  void
	 */
	public function setIdProperty(string $idProperty)
	{
		$this->idProperty = $idProperty;
	}

	/**
	 * Return the configured Release ID after making some sanity checks
	 *
	 * @return  int
	 */
	public function getReleaseId()
	{
		if (!isset($this->releaseId))
		{
			throw new BuildException("You must set the Release ID to upload an asset to it.");
		}

		if ($this->releaseId <= 0)
		{
			throw new BuildException("The Release ID must be a positive integer. You specified: {$this->releaseId}");
		}

		return $this->releaseId;
	}

	/**
	 * Return the MIME content type of the file being uploaded. If none was specified we will try to detect it.
	 *
	 * @return  string
	 */
	public function getContentType(): string
	{
		if (empty($this->contentType))
		{
			$fileName          = $this->getFile();
			$fileInfo          = finfo_open(FILEINFO_MIME_TYPE);
			$this->contentType = finfo_file($fileInfo, $fileName);
			finfo_close($fileInfo);

			if (empty($this->contentType))
			{
				throw new BuildException("Cannot detect content type for file $fileName");
			}
		}

		return $this->contentType;
	}

	/**
	 * Returns the file to upload after making some sanity checks
	 *
	 * @return  string
	 */
	public function getFile(): string
	{
		if (empty($this->file))
		{
			throw new BuildException("You must specify which file you want to upload to GitHub.");
		}

		if (!file_exists($this->file))
		{
			throw new BuildException("The file $this->file does not exist, so it cannot be uploaded to GitHub.");
		}

		return $this->file;
	}

	/**
	 * Get the filename as it will be reported by GitHub
	 *
	 * @return  string
	 */
	public function getRemoteName(): string
	{
		if (empty($this->remoteName))
		{
			$file = $this->getFile();

			$this->remoteName = basename($file);
		}

		return $this->remoteName;
	}
}
