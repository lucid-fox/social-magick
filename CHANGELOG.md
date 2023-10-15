# Social Magick 2.0.1
* Fix: errant entry in XML manifest prevents installation

# Social Magick 2.0.0

* Upgrade: Joomla 4.2, PHP 7.4+ required; compatible with Joomla! up to 5.0, PHP up to 8.3
* Fix: ImageRendererGD throws a PHP 8 notice
* Fix: ImageRendererImagick throws a PHP 8 notice
* Fix: Cannot handle images with spaces in their names (gh-38)
* Fix: Template drop-down is disabled after installation (gh-39)

# Social Magick 1.0.2

+ Option to use a static image as the overlay source (gh-35)

# Social Magick 1.0.1

* Fix: workaround for Joomla 4 links without an Item ID (e.g. /component/something)
* Fix: Joomla 4, cannot get the intro or full image, Joomla changed the internal structure of the image URLs again.

# Social Magick 1.0.0

* Fix: allow installation on Joomla 4.1
* Fix: use `HTMLHelper::cleanImage` on Joomla 4
* Fix: Using a newsflash module on a page would override Social Magick  (gh-31) 

# Social Magick 1.0.0.b4

* Fix: Default is to use no image but default menu params claim the default is to use the fulltext image (gh-28)
* Fix: Wrong parameters may be used when caching is enabled (gh-27)
* Fix: Prevents uploading images in Joomla 4

# Social Magick 1.0.0.b3

* Added: Support for `socialMagickTemplate` application property to override the template to use generating the image.
* Added: Support for `socialMagickImage` application property to override the image to use in the template.
* Added: Default site-wide template selection (gh-13)
* Added: Option to use no text in a template (gh-8)
* Added: Options to add other OG tags (gh-4)
* Added: Distribute the generated files in subdirectories (gh-22)
* Added: Auto-delete old images (gh-7)
* Fix: only trigger in the frontend HTML application
* Fix: hardcoded language strings in plugin XML manifest (gh-15)
* Fix: plugin installation file didn't have the version in the filename (gh-16)
* Fix: GD library pushes the text an additional 50px in the x and y axes

# Social Magick 1.0.0.b2

* Fix: Typo in the library makes PHP 8 complain about it.
* Fix: Spelling errors in en-GB language strings.
* Fix: No image is used when article is shown from a menu item pointing to a category list / blog layout.

# Social Magick 1.0.0.b1

First public beta