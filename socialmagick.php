<?php
/*
 * SocialMagick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2021 Lucid Fox
 * @license   GNU GPL v3 or later
 */

defined('_JEXEC') || die();

use Joomla\CMS\Form\Form;
use Joomla\CMS\Plugin\CMSPlugin;
use LucidFox\SocialMagick\ImageGenerator;

/**
 * System plugin to automatically generate Open Graph images
 *
 * @package      ogimages
 *
 * @since        1.0.0
 *
 * @noinspection PhpUnused
 */
class plgSystemSocialmagick extends CMSPlugin
{
	private $helper = null;

	/**
	 * plgSystemSocialmagick constructor.
	 *
	 * @param   mixed  $subject  The event or plugin dispatcher
	 * @param   array  $config   Configuration parameters
	 */
	public function __construct(&$subject, $config = [])
	{
		// Register the autoloader for the library
		if (version_compare(JVERSION, '3.999.999', 'le'))
		{
			/** @noinspection PhpMethodParametersCountMismatchInspection */
			JLoader::registerNamespace('LucidFox\\SocialMagick', __DIR__ . '/library', false, false, 'psr4');
		}
		else
		{
			JLoader::registerNamespace('LucidFox\\SocialMagick', __DIR__ . '/library');
		}

		parent::__construct($subject, $config);

		$this->helper = new ImageGenerator($this->params);
	}

	/**
	 * Runs when Joomla is preparing a form. Used to add extra form fieldsets to core pages.
	 *
	 * @param   Form   $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	public function onContentPrepareForm(Form $form, $data): bool
	{
		$this->loadLanguage();

		Form::addFormPath(__DIR__ . '/form');

		switch ($form->getName())
		{
			// A menu item is being added/edited
			case 'com_menus.item':
				$form->loadFile('socialmagick_menu', false);
				break;
		}

		return true;
	}

	/**
	 * Triggered when Joomla is saving content. Used to save the SocialMagick configuration.
	 *
	 * @param   string|null   $context  Context for the content being saved
	 * @param   Table|object  $table    Joomla table object where the content is being saved to
	 * @param   bool          $isNew    Is this a new record?
	 * @param   object        $data     Data being saved
	 *
	 * @return  bool
	 */
	public function onContentBeforeSave(?string $context, $table, $isNew = false, $data = null): bool
	{
		// Make sure I have data to save
		if (!isset($data['socialmagick']))
		{
			return true;
		}

		$key = null;

		switch ($context)
		{
			case 'com_menus.item':
				$key  = 'params';
				break;
		}

		if (is_null($key))
		{
			return true;
		}

		$params = @json_decode($table->{$key}, true) ?? [];
		$table->{$key} = json_encode(array_merge($params, ['socialmagick' => $data['socialmagick']]));

		return true;
	}

	/**
	 * Triggered when Joomla is loading content. Used to load the Engage configuration.
	 *
	 * This is used for both articles and article categories.
	 *
	 * @param   string|null  $context  Context for the content being loaded
	 * @param   object       $data     Data being saved
	 *
	 * @return  bool
	 */
	public function onContentPrepareData(?string $context, &$data)
	{
		$key = null;

		switch ($context)
		{
			case 'com_menus.item':
				$key  = 'params';
				break;
		}

		if (is_null($key))
		{
			return true;
		}

		if (!isset($data->{$key}) || !isset($data->{$key}['socialmagick']))
		{
			return true;
		}

		$data->socialmagick = $data->{$key}['socialmagick'];
		unset ($data->{$key}['socialmagick']);

		return true;
	}

	public function onSocialMagickGetTemplates(): array
	{
		return $this->helper->getTemplates();
	}
}