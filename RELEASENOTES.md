Welcome to the second public beta of Social Magick! 🎉

This plugin automatically generates Open Graph images for your site's pages. These images are used when sharing a link to your site on social media (e.g. Facebook, Twitter, …) and chat applications (e.g. Slack, …). 

The images are generated from a template image with superimposed title text. By default, the article text used is (in order of preference) the core content category or article's title, the page title you've set up in the menu item or the page title Joomla has generated for the page.

Your templates can use an image as well. It can be the category image; the article intro or full text image; or an image you provide in a custom field, the name of which you can choose per menu item. This works best on sites which make extensive use of article images.

### Requirements

* Joomla 3.9, 3.10 or 4.0.
* PHP 7.2.
* The PHP `gd` or `imagick` extension installed and enabled.

### Quick start

* Download and install the plugin ZIP file.
* Publish the System – Social Magick plugin.
* Edit the menu item you want to have Open Graph images automatically generated. In its “Open Graph images” tab:
    * Set “Generate Open Graph images” to Yes.
    * Select the Solid template.
* Save your menu item.
* Go to [metatags.io](https://metatags.io/) and paste the URL to the page of your site that corresponds to the menu item you selected. You can now see that it has a preview image.

If you have menu items with core content (Joomla articles) categories and articles which make use of images you can select the Overlay template. You will need to set the “Extra image source” option to “Intro image” or “Full Article image”, depending on which image you want to use.

The templates provided are meant as examples; while you are welcome to use them on your live site, you can also replace the template images with ones that do not have the Social Magick watermark.

### Beta software

This is the _public beta_ version of this plugin. The main features are already there but we have not written any documentation yet. 

While we have done fairly extensive testing and we even use it on production sites please bear in mind that there might be some rough spots. If you find something is not quite right please file a GitHub issue and try to be as descriptive as possible. We promise that we'll eventually look into it but kindly note that it might take a while. We ask for your understanding.