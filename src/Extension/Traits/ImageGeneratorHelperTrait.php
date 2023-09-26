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

use LucidFox\Plugin\System\SocialMagick\Library\ImageGenerator;

trait ImageGeneratorHelperTrait
{
	/**
	 * The ImageGenerator instance used throughout the plugin
	 *
	 * @var   ImageGenerator|null
	 * @since 1.0.0
	 */
	protected ?ImageGenerator $helper = null;

	protected function getHelper(): ?ImageGenerator
	{
		$this->helper ??= call_user_func(function () {
			$helper = new ImageGenerator($this->params);
			$helper->setDatabase($this->getDatabase());
			/** @noinspection PhpParamsInspection */
			$helper->setApplication($this->getApplication());

			return $helper;
		});

		return $this->helper;
	}
}