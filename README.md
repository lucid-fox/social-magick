# ![Lucid Fox Social Magick](https://github.com/lucid-fox/social-magick/blob/main/_assets/social-magick-og.jpeg?raw=true)

A Joomla 3 and 4 plugin to automatically generate Open Graph images.

[Downloads](https://github.com/lucid-fox/social-magick/releases) • [Issues](https://github.com/lucid-fox/social-magick/issues)

## What is this

This plugin allows you to automatically generate Open Graph images for your site's pages, superimposing text and
graphics over an image or solid color background. Open Graph images are used by social media sites when sharing a URL to
any of your site's pages on them.

For example:
| Facebook | Twitter | LinkedIn | Slack |
|----------|---------|----------|-------|
|![Facebook Example](https://github.com/lucid-fox/social-magick/blob/main/_assets/Facebook-Example.png?raw=true)|![Twitter Example](https://github.com/lucid-fox/social-magick/blob/main/_assets/Twitter-Example.png?raw=true)|![LinkedIn Example](https://github.com/lucid-fox/social-magick/blob/main/_assets/LinkedIn-Example.png?raw=true)|![Slack Example](https://github.com/lucid-fox/social-magick/blob/main/_assets/Slack-Example.png?raw=true)|

(Want to check what your site shows now? Check out [metatags.io](https://metatags.io/), a site that shows you previews of all social media cards for your link.)

## Requirements

This plugin has the following minimum requirements:

* Joomla 3.9 or any later 3.x version; or Joomla 4.0
* PHP 7.2, 7.3, 7.4 or 8.0
* The Imagick or GD PHP extension installed and enabled. (If you're not sure how to do this, ask your host.)

## Quick start

* Download and install the plugin ZIP file.
* Publish the System – Social Magick plugin.
* Edit the menu item you want to have Open Graph images automatically generated. In its “Open Graph images” tab:
* Set “Generate Open Graph images” to Yes.
* Select the Solid template.
* Save your menu item.
* Go to [metatags.io](https://metatags.io/) and paste the URL to the page of your site that corresponds to the menu item you selected. You can now see that it has a preview image.

If you have menu items with core content (Joomla articles) categories and articles which make use of images you can select the Overlay template. You will need to set the “Extra image source” option to “Intro image” or “Full Article image”, depending on which image you want to use.

The templates provided are meant as examples; while you are welcome to use them on your live site, you can also replace the template images with ones that do not have the Social Magick watermark.
