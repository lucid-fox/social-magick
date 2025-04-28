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

use Github\AuthMethod;
use Github\Client;

if (!class_exists(\Github\Client::class))
{
	$autoloaderFile = __DIR__ . '/../../vendor/autoload.php';

	if (!file_exists($autoloaderFile))
	{
		echo <<< END

********************************************************************************
**                                   WARNING                                  **
********************************************************************************

You have NOT initialized Composer on the repository. This script is about to die
with an error.

--------------------------------------------------------------------------------
HOW TO FIX
--------------------------------------------------------------------------------

Go to the repository's root and run:

composer install


END;

		throw new \RuntimeException("Composer is not initialized in the repository");
	}

	require_once $autoloaderFile;
}

/**
 * Abstract base class for GitHub tasks
 */
abstract class GitHubTask extends \Phing\Task
{
	/**
	 * The GitHub client object
	 *
	 * @var   Client
	 */
	protected $client;

	/**
	 * The organization the repository belongs to. That's the part after github.com in the repo's URL.
	 *
	 * @var   string
	 */
	protected $organization;

	/**
	 * The name of the repository. That's the part after github.com/yourOrganization in the repo's URL.
	 *
	 * @var   string
	 */
	protected $repository;

	/**
	 * GitHub API token
	 *
	 * @var   string
	 */
	protected $token;

	/**
	 * Set the repository's organization
	 *
	 * @param   string  $organization
	 *
	 * @return  void
	 */
	public function setOrganization($organization)
	{
		$this->organization = $organization;
	}

	/**
	 * Set the repository's name
	 *
	 * @param   string  $repository
	 *
	 * @return  void
	 */
	public function setRepository($repository)
	{
		$this->repository = $repository;
	}

	/**
	 * Set the GitHub token
	 *
	 * @param   string  $token
	 *
	 * @return  void
	 */
	public function setToken($token)
	{
		$this->token = $token;
	}

	public function init()
	{
		// Create the API client object. Follow me...

		/**
		 * We need a Guzzle HTTP client which is explicitly told where the hell to look for the cacert.pem file because
		 * if curl.cainfo is not set in php.ini (you can't ini_set() this!) and you're on Windows it will consistently
		 * fail.
		 */
		$guzzleClient  = new \GuzzleHttp\Client([
			'verify' => __DIR__ . '/cacert.pem' ,
		]);

		// Then we need to create an HTTPlug client adapter to the Guzzle client
		$guzzleAdapter = new Http\Adapter\Guzzle6\Client($guzzleClient);
		// In turn, we need to make an HTTPBuilder object to that adapter
		$httpBuilder   = new Github\HttpClient\Builder($guzzleAdapter);
		// Finally we have our client.
		$this->client  = new Client($httpBuilder);
	}

	public function main()
	{
		// Make sure we have a token and apply authentication
		if (empty($this->token))
		{
			throw new RuntimeException('You need to provide your GitHub token.');
		}

		$this->client->authenticate($this->token, null, AuthMethod::ACCESS_TOKEN);
	}
}
