<?php
/*
 * SocialMagick – Automatically generate Open Graph images on your site
 *
 * @package   socialmagick
 * @copyright Copyright 2021-2021 Lucid Fox
 * @license   GNU GPL v3 or later
 */

JFormHelper::loadFieldClass('radio');

/**
 * Yes/No switcher, compatible with Joomla 3 and 4
 *
 * @package      Joomla\CMS\Form\Field
 *
 * @since        1.0.0
 * @noinspection PhpUnused
 */
class JFormFieldFancyradio extends JFormFieldRadio
{
	public function __construct($form = null)
	{
		if (version_compare(JVERSION, '3.999.999', 'gt'))
		{
			$this->layout = 'joomla.form.field.radio.switcher';
		}
		else
		{
			$this->layout = 'joomla.form.field.radio';
		}

		parent::__construct($form);
	}

}