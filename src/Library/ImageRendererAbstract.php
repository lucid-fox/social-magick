<?php
/**
 * Social Magick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2023 Lucid Fox
 * @license   GNU GPL v3 or later
 */

namespace LucidFox\Plugin\System\SocialMagick\Library;

defined('_JEXEC') || die();

abstract class ImageRendererAbstract implements ImageRendererInterface
{
	/**
	 * Should I create bounding boxes around the rendered text?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	protected bool $debugText = false;

	/**
	 * Generated image quality, 0-100
	 *
	 * This is used verbatim for WebP and JPEG. It's converted to a compression scale of 0-9 (100 maps to 0) for PNG.
	 * Completely ignored for GIF and other formats.
	 *
	 * @var   int
	 * @since 1.0.0
	 */
	protected int $quality = 80;

	/** @inheritDoc */
	public function __construct(int $quality = 80, bool $debugText = false)
	{
		$this->quality   = max(min($quality, 100), 0);
		$this->debugText = $debugText;
	}

	/** @inheritDoc */
	public function getOptionsKey(): string
	{
		return md5(
			get_class($this) . '_' .
			($this->debugText ? 'textDebug_' : '') .
			'q' . $this->quality
		);
	}


	/**
	 * Pre-processes the text before rendering.
	 *
	 * This method removes Emoji and Dingbats, collapses double spaces into single spaces and converts all whitespace
	 * into spaces. Finally, it converts non-ASCII characters into HTML entities so that GD can render them correctly.
	 *
	 * @param   string  $text
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function preProcessText(string $text, bool $htmlEntities = true)
	{
		$text = $this->stripEmoji($text);
		$text = preg_replace('/\s/', ' ', $text);
		$text = preg_replace('/\s{2,}/', ' ', $text);

		return $htmlEntities ? htmlentities($text) : $text;
	}

	/**
	 * Normalize the extension of an image file and return it without the dot.
	 *
	 * @param   string  $file  The image file path or file name
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	protected function getNormalizedExtension(string $file): ?string
	{
		if (empty($file))
		{
			return null;
		}

		$extension = pathinfo($file, PATHINFO_EXTENSION);

		switch (strtolower($extension))
		{
			// JPEG files come in different extensions
			case 'jpg':
			case 'jpe':
			case 'jpeg':
				return 'jpg';

			default:
				return $extension;
		}
	}

	/**
	 * Normalise the path to a font file.
	 *
	 * If the font file path is not absolute we look in the plugin's fonts fodler.
	 *
	 * @param   string  $font
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function normalizeFont(string $font): string
	{
		// Convert a relative path to absolute
		if (!@file_exists($font))
		{
			$font = JPATH_PLUGINS . '/system/socialmagick/fonts/' . $font;
		}

		// If the font doesn't exist or is unreadable fall back to OpenSans Bold shipped with the plugin
		if (!@file_exists($font) || !@is_file($font) || !@is_readable($font))
		{
			$font = JPATH_PLUGINS . '/system/socialmagick/fonts/OpenSans-Bold.ttf';
		}

		return $font;
	}

	/**
	 * Convert a hexadecimal color string to an array of Red, Green, Blue and Alpha values.
	 *
	 * @param   string  $hex
	 *
	 * @return  int[]  The [R,G,B,A] array of the color.
	 *
	 * @since   1.0.0
	 */
	protected function hexToRGBA(string $hex): array
	{
		// Uppercase the hex color string
		$hex = strtoupper($hex);

		// Remove the hash sign in front
		if (substr($hex, 0, 1) === '#')
		{
			$hex = substr($hex, 1);
		}

		// Convert ABC to AABBCC
		if (strlen($hex) === 3)
		{
			$bits = str_split($hex, 1);
			$hex  = $bits[0] . $bits[0] . $bits[1] . $bits[1] . $bits[2] . $bits[2];
		}

		// Make sure the hex color string is exactly 8 characters (format: RRGGBBAA
		if (strlen($hex) < 8)
		{
			$hex = str_pad(str_pad($hex, 6, '0'), 8, 'F');
		}

		$hex = substr($hex, 0, 8);

		$hexBytes = str_split($hex, 2);

		$ret = [0, 0, 0, 255];

		foreach ($hexBytes as $index => $hexByte)
		{
			$ret[$index] = hexdec($hexByte);
		}

		return $ret;
	}

	/**
	 * Set the PHP time limit, if possible.
	 *
	 * @param   int  $limit  Time limit in seconds.
	 *
	 *
	 * @since   1.0.0
	 */
	protected function setTimeLimit(int $limit = 0)
	{
		if (!function_exists('set_time_limit'))
		{
			return;
		}

		@set_time_limit($limit);
	}

	/**
	 * Strip Emoji and Dingbats off a string
	 *
	 * @param   string  $string  The string to process
	 *
	 * @return  string  The cleaned up string
	 *
	 * @since   1.0.0
	 */
	private function stripEmoji(string $string): string
	{

		// Match Emoticons
		$regex_emoticons = '/[\x{1F600}-\x{1F64F}]/u';
		$clear_string    = preg_replace($regex_emoticons, '', $string);

		// Match Miscellaneous Symbols and Pictographs
		$regex_symbols = '/[\x{1F300}-\x{1F5FF}]/u';
		$clear_string  = preg_replace($regex_symbols, '', $clear_string);

		// Match Transport And Map Symbols
		$regex_transport = '/[\x{1F680}-\x{1F6FF}]/u';
		$clear_string    = preg_replace($regex_transport, '', $clear_string);

		// Match Miscellaneous Symbols
		$regex_misc   = '/[\x{2600}-\x{26FF}]/u';
		$clear_string = preg_replace($regex_misc, '', $clear_string);

		// Match Dingbats
		$regex_dingbats = '/[\x{2700}-\x{27BF}]/u';
		$clear_string   = preg_replace($regex_dingbats, '', $clear_string);

		return $clear_string;
	}

}