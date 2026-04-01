Image Styles Generator
--

Sometimes we need to warm up images on a site, for example to speed up
tests in CI/CD environments. This module provides a drush command capable 
of regenerating all images with all image styles defined on the site.

Optionally you can select which image styles will be warmed up by 
passing them as parameters.

### Usage
$ drush image_derivatives:generate

#### Options:
--image_styles[=IMAGE_STYLES] Coma separated image styles IDs.

### Similar modules
Image style warmer

