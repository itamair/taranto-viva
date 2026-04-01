CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Recommended modules
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------


The library in views module is designed to attach JS and CSS library in views,
by providing just library name.

REQUIREMENTS
------------

No special requirements.


RECOMMENDED MODULES
-------------------

 views.


INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module.
   See: https://www.drupal.org/node/895232 for further information.




CONFIGURATION
-------------


Installation is as simple as copying the module into your 'modules' contrib
directory, then enabling the module.

To add library in view follow below steps
1) Create or edit view
2) Find Attach Library section
3) Click on add library or edit library
4) Add or edit library name in textfield.


For example there is a custom module called "abc" and this module has
library called "xyz" and this library has 2 js(abc1.js , abc2.js)
and 1 css(abc1.css) file.

so add "portal_abc/xyz" in textfield which is nothing but just library name,
where "abc" is module or theme name and "xyz" is library name.
so this view will include 2 js(abc1.js , abc2.js) and 1 css(abc1.css) file.


For a full description visit project page:
https://www.drupal.org/project/views_attach_library

Bug reports, feature suggestions and latest developments:
http://drupal.org/project/issues/views_attach_library



MAINTAINERS
-----------

Current maintainers:
* Hardik Patel - https://www.drupal.org/user/3316709
* Yogesh Chougule - https://www.drupal.org/user/724666
* Rahul Lamkhade - https://www.drupal.org/user/2718915
* Bao NGUYEN - https://www.drupal.org/u/lazzyvn
