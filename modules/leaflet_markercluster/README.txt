
The Leaflet MarkerCluster module depends on the Leaflet Module (https://www.drupal.org/project/leaflet)
so that here it is intended the presence of the module.

INSTALLATION (manual)
============
1.a Naturally you need to have Leaflet (the module and the javascript library)
installed before you can use Leaflet MarkerCluster.

Then download the MarkerCluster library from:
https://github.com/danzel/Leaflet.markercluster

The core part of the zip file is a directory named 'dist'. Make sure this
directory ends up in libraries/leaflet_markercluster, so that the path
to the essential javascript file becomes libraries/leaflet_markercluster/dist/leaflet.markercluster.js
(from the Drupal code base root)

INSTALLATION (with Composer)
============

1.b Run $ composer require drupal/leaflet_markecluster:~1.0
   (or directly add the requirement in the composer.json file.)

2.b Add the proper repository to your composer.json file to be able to require
   the JS library, so that the relevant composer.json part look like the following:

    "leaflet": {
        "type": "package",
        "package": {
          "name": "leaflet/leaflet",
          "version": "v1.0.3",
          "type": "drupal-library",
          "dist": {
            "url": "https://github.com/Leaflet/Leaflet/archive/v1.0.3.zip",
            "type": "zip"
          }
        }
      },
      "leaflet_markercluser": {
        "type": "package",
        "package": {
          "name": "leaflet/leaflet_markercluster",
          "version": "v1.0.1",
          "type": "drupal-library",
          "dist": {
            "url": "https://github.com/Leaflet/Leaflet.markercluster/archive/v1.0.1.zip",
            "type": "zip"
          }
        }
      }

3.b Run $ composer require leaflet/leaflet_markercluster
    (or directly add the requirement in the composer.json file.)

Note: In Drupal managed by Composer, the folder destination of the leaflet/leaflet_markercluster library
will be driven by the composer/installers library, and defined by the extra/installer_path property setup in the composer.json.
The following are the most probable settings, depending on the template used to generate your new Drupal site via Composer
(https://www.drupal.org/docs/develop/using-composer/using-composer-to-manage-drupal-site-dependencies):

(Option A) drupal-composer/drupal-project:

  "extra": {
    "installer-paths": {
      "web/libraries/{$name}": ["type:drupal-library"],
      ...
    },

(Option B) drupal/drupal:

  "extra": {
    "installer-paths": {
      "libraries/{$name}": ["type:drupal-library"],
      ...
    },

Also in this case you should end up with the leaflet_markercluster library installed at the following path:
libraries/leaflet_markercluster/dist/leaflet.markercluster.js.
(from the Drupal code base root)

============

4. Enable Drupal modules as usual.

Visit the Status Report page, admin/reports/status, to check all's ok.

There are no permissions to configure.

This module does not itself have a UI to set MarkerCluster configuration
parameters. However parameters may be set through Drupal code as part of the
creation of the map and will thus be passed to the underlying javascript
library. See the section below.


FOR PROGRAMMERS
===============

You can set Leaflet MarkerCluster parameters in the same way that you set
Leaflet map parameters.
Example:

  $map_id = 'OSM Mapnik'; // default map that comes with Leaflet
  $map = leaflet_map_get_info($map_id);

  $map['settings']['zoom']                    = 10; // Leaflet parameter
  $map['settings']['maxClusterRadius']        = 50; // Leaflet MarkerCluster parameter
  $map['settings']['disableClusteringAtZoom'] = 2;  // Leaflet MarkerCluster parameter

  $features = ... // see the README.txt of the Leaflet module

  $output = '<div>' . leaflet_render_map($map, $features, '300px') . '</div>';

The following MarkerCluster parameters may be configured this way:

  animateAddingMarkers (default: FALSE)
  disableClusteringAtZoom (NULL)
  maxClusterRadius (80)
  showCoverageOnHover (TRUE)
  singleMarkerMode (FALSE)
  skipDuplicateAddTesting (FALSE)
  spiderfyOnMaxZoom (TRUE)
  zoomToBoundsOnClick (TRUE)

See the bottom reference for an explanation of these parameters.

References:

o http://leaflet.cloudmade.com/2012/08/20/guest-post-markerclusterer-0-1-released.html
o https://github.com/danzel/Leaflet.markercluster/blob/master/README.md
