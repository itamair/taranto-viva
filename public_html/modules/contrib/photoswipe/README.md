
# README

## About

Uses [Photoswipe](https://photoswipe.com/) to display picture galleries on your
Drupal website. This Javascript lightbox library offers very nice mobile
browsing features (in particular swiping to the next picture)!


## Installation

### Manual Installation

- Require the module, e.g. via composer: "composer require drupal/photoswipe"
- Install the module
- Download the "PhotoSwipe-5.4.4" zip file
- Unzip and place the contents of the unzipped "PhotoSwipe-5.4.4" folder
into "library/photoswipe" folder so that the folder structure is:
"library/photoswipe/dist/photoswipe.js"
- Check the status report for errors

### Alternative composer installation (recommended)

- Require the module, e.g. via composer: "composer require drupal/photoswipe"
- Install the module
- Enable usage of third-party libraries using composer, see
[here](https://www.drupal.org/docs/develop/using-composer/manage-dependencies#third-party-libraries) for an explanation.
- Require the photoswipe library using
`composer require bower-asset/photoswipe:^5`
- Check your status report

Then simply configure your image fields to use photoswipe as their field display
formatter.

Note: If you would like to use the "Photoswipe Responsive" display formatter,
please enable the core "Responsive Image" module first.


## Usage

### Photoswipe images in entities
After adding an image or media entity field to any content type
(e.g. 'article'), you can select 'PhotoSwipe' or 'Photoswipe Responsive' as a
display mode in Structure -> Content types -> MyContentType in the tab
'Manage display'.

### Photoswipe images in Views
To use photoswipe in views you can either change the Row style options "View
mode" to the view mode display formatter, you have the photoswipe display
formatter applied to, or you can add a media / image field, where you set the
'Photoswipe' or 'Photoswipe Responsive' display mode similar to
"Images in entities".

By default, using the Photoswipe formatter on a views field will open single 
images (not a gallery). To have a gallery of images, you need to set a 
"photoswipe-gallery" class on any wrapper.

For example: If you have an image field with multiple values and you want to 
group them, set this class on the field wrapper.
If you have multiple image fields and you want to group them all, set the 
class on the views-row wrapper (this may or may not be possible depending on 
the display plugin used).

See the Views documentation page section
[Customize the output style of a view field or list > Customize a view list](https://www.drupal.org/docs/8/core/modules/views/customize-the-output-style-of-a-view-field-or-list#s-customize-a-view-list)
for details on how to add a class in a View.

### Photoswipe images in twig templates:
To use the photoswipe formatter for images inside a custom twig template, you
can use the `attach_photoswipe()` twig function.
Simply make sure:
- You use the function in the correct context.
- The anchor tag (`<a>`) wrapping the image needs the "photoswipe" class to mark it as photoswipe trigger

Here is an example on how to use the `attach_photoswipe()` twig function inside.
a twig template:
~~~
<div class="myContext">
  {{ attach_photoswipe() }}
  <div class="photoswipe-gallery">
    <a href="https://cdn.photoswipe.com/photoswipe-demo-images/photos/2/img-2500.jpg" class="photoswipe">
      <img src="https://cdn.photoswipe.com/photoswipe-demo-images/photos/2/img-2500.jpg" alt="Beautiful picture" />
    </a>
  </div>
</div>
~~~
You can also override the photoswipe settings through the attach function as
follows:
~~~
{{ attach_photoswipe({'bgOpacity': 0.2}) }}
~~~
