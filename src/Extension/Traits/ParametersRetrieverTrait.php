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

use LucidFox\Plugin\System\SocialMagick\Library\ParametersRetriever;

trait ParametersRetrieverTrait
{
	private ?ParametersRetriever $paramsRetriever = null;

	protected function getParamsRetriever(): ParametersRetriever
	{
		/** @noinspection PhpParamsInspection */
		$this->paramsRetriever ??= new ParametersRetriever($this->getApplication());

		return $this->paramsRetriever;
	}
}