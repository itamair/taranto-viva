## Leaflet (3.0.x)

**Leaflet** module provides integration with [Leaflet map scripting
library](http://leafletjs.com).

It is based and dependant from:

  - the [Leaflet JS library](http://leafletjs.com);
  - the [Geofield](https://www.drupal.org/project/geofield) Module;
  - other additional Leaflet Js sub-libraries (such as
[leaflet.markercluster](https://github.com/Leaflet/Leaflet.markercluster),
[leaflet-geoman](https://github.com/geoman-io/leaflet-geoman), and more ...);

This **3.0.x branch** is experimental and not formally supported.
Its focus is to require all its dependencies using Composer, according to the
Drupal 8 & 9+ best practices (@see
[Using Composer to manage Drupal site dependencies](https://www.drupal.org/docs/develop/using-composer/using-composer-to-manage-drupal-site-dependencies)).

## Installation

#### Proper setup of composer.json to download required Leaflet JS Libraries.

First you need to update your `composer.json` in your project root as below to
include the "type:drupal-library" in the "installer-paths", and properly
download Leaflet JS libraries in the "libraries" folder
(via the composer-merge-plugin package):

```
    {
        ...
        "extra": {
            "installer-paths": {
                "web/libraries/{$name}": [
                    "type:drupal-library",
                ]
            },
            "installer-types": [
               ...,
               "drupal-library",
               ...
            ],
            "merge-plugin": {
                "include": [
                    "web/modules/contrib/leaflet/composer.libraries.json"
                ]
            }
        ...
        }
    }
```
**Note**: We assume your "libraries" folder is located under
the "web" folder (as usual in the
[Composer template for Drupal projects](https://github.com/drupal-composer/drupal-project)).
Adjust it accordingly to your specific setup if not your case.

Then require/add in your composer.json both the following packages:
- composer/installers
(`composer require composer/installers`)
-
- wikimedia/composer-merge-plugin
    (`composer require wikimedia/composer-merge-plugin`)

### Download/Require the Drupal Leaflet Module

Run the following command from your project root
(where the main composer.json file is sited):

    composer require drupal/leaflet:3.0.x-dev

That will also download the required [Geofield
Module](https://www.drupal.org/project/geofield) dependency and GeoPHP
library.

### Download required Leaflet JS Libraries

As a final step (and for this:
https://github.com/wikimedia/composer-merge-plugin#updating-sub-levels-composerjson-files)
run the following composer update again to download the required Leaflet Js
libraries (in the web/libraries folder):

`composer update drupal/leaflet`

## Usage

Enable the **Leaflet** module to be able to use:

  - The configurable **Leaflet Map** as Geofield Widget, with [Leaflet
    Geoman js library](https://geoman.io/leaflet-geoman);

  - The configurable **Leaflet Map** as Geofield Formatter;

  - Enable **Leaflet Views** (`leaflet_views`) submodule for **Leaflet
    Map Views integration** You need to add at least one geofield to the
    Fields list, and select the Leaflet Map style in the Display Format.
    In the settings of the style, select the geofield as the Data Source
    and select a field for Title and Description (which will be rendered
    in the popup).

  - Add, enable and configure ["Geoocoder"
    module](https://www.drupal.org/project/geocoder) to enable Geocoder
    Control (with Autocomplete) for quick Leaflet Map Address search &
    pan/zoom.

As a more powerful alternative, you can use node view modes to be
rendered in the popup. In the Description field, select "<entire node>"
and then select a View mode.

## API Usage

Rendering a map is as simple as instantiating the LeafletService and its
leafletRenderMap method

    \Drupal::service('leaflet.service')->leafletRenderMap($map, $features, $height)

which takes 3 parameters:

  - $map: An associative array defining a map. See
    hook\_leaflet\_map\_info(). The module defines a default map with a
    OpenStreet Maps base layer.

  - $features: This is an associative array of all the Leaflet features
    you want to plot on the map. A feature can be a point, linestring,
    polygon, multipolygon, multipolygon, or json object. Additionally,
    features can be grouped into [leaflet layer
    groups](http://leafletjs.com/reference-1.3.0.html#layergroup) so
    they can be controlled together,

  - $height: The map height, expressed in css units.

## Tips & Tricks

### Bind events on geojson (json) features

See: <https://www.drupal.org/project/leaflet/issues/3186029>

    $features[] = [
        'type' => 'json',
        'json' => $geojson,
        'events' => [
            'click' => 'Drupal.manageGeojsonClick', // or whatever callback
        ],
    ];
    $this->leaflet->leafletRenderMap($map_info, $features, $height),

## Authors/Credits

  - [itamair](https://www.drupal.org/u/itamair)
  - [levelos](http://drupal.org/user/54135)
  - [pvhee](http://drupal.org/user/108811)
