<?php
/**
 * Social Magick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2023 Lucid Fox
 * @license   GNU GPL v3 or later
 */

namespace LucidFox\Plugin\System\SocialMagick\Extension\Traits;

\defined('_JEXEC') || die;

use Joomla\CMS\User\UserHelper;

trait DebugPlaceholderTrait
{
	/**
	 * Get a random, unique placeholder for the debug OpenGraph image link
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	private function getDebugLinkPlaceholder(): string
	{
		if (!empty($this->debugLinkPlaceholder))
		{
			return $this->debugLinkPlaceholder;
		}

		$this->debugLinkPlaceholder = '{' . UserHelper::genRandomPassword(32) . '}';

		return $this->debugLinkPlaceholder;
	}

}