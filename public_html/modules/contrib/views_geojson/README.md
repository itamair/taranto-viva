# Views GeoJSON

## Table of contents

- Summary
- Requirements
- Installation
- Usage
    - Bounding Box Support
- To Do
- Credits
- Current Maintainers

## Summary

Views GeoJSON is a style plugin for Views to deliver location-specific data
in GeoJSON format, see
[RFC 7946: The GeoJSON Format](view-source:https://www.rfc-editor.org/rfc/rfc7946).

Each row is output as a GeoJSON "Features" including geospatial data and
optional metadata. All features are wrapped in a "Feature Collection" object.

## Requirements

Drupal core modules

- Views
- RESTful Web Services
- Serialization

External projects

- [itamair/geophp](https://packagist.org/packages/itamair/geophp)

Optional Drupal modules

- [Geofield](https://www.drupal.org/project/geofield)
- [Geolocation](https://www.drupal.org/project/geolocation)

## Installation

Install as you would normally install a contributed Drupal module.
Visit https://www.drupal.org/node/1897420 for further information.

## Usage

1. Create a View showing an entity (likely "Content") that includes
geospatial data in its fields
2. Click "Add" on the Views configuration screen and choose "GeoJSON export"
from the Display type choices
3. Add fields to the Display that include either: longitude and latitude
coordinates, a Geofield or Geolocation field, or WKT data
4. The Format for the Display should show "GeoJSON". Choose that format if not
already set
5. In the Format area click "Settings" (next to "GeoJSON"), and:
    - Choose "Map Data Sources" specifying the type of data in your field(s)
    (Geofield, lat/lon, etc.)
    - Assign the particular field(s) that include that data
6. Optionally add fields for title and description of each point/feature, and
add these to the Format settings
7. Optionally set a JSONP prefix in Format settings,
(see https://en.wikipedia.org/wiki/JSONP)
8. Set the Path, perhaps using your site's API endpoint URL structure or ending
with ".json" or ".geojson"

### Bounding Box Filtering

GeoJSON views can accept a bounding box as an argument to return only the features
within that box.

It has been tested with OpenLayers' Bounding Box Strategy but should work with
any mapping tool that requests bounding box coordinates as
"?bbox=left,bottom,right,top" in the query string. Argument ID "bbox" is
default for OpenLayers but can be changed.

1. Create a GeoJSON view as above in USAGE
2. Add a layer to OpenLayers of type GeoJSON, at
`/admin/structure/openlayers/layers/add/openlayers_layer_type_geojson`,
specifying the URL to your GeoJSON feed and checking the box for "Use Bounding
Box Strategy"
3. In your GeoJSON View configuration, add a Contextual Filter of type:
"Custom: Bounding box"
4. In the Contextual Filter settings, under "When the filter value is NOT in
the URL as a normal Drupal argument", choose: "Provide default value"
5. In the "Type" dropdown, choose: "Bounding box from query string"
6. For OpenLayers, leave "Query argument ID" as "bbox" and click Apply

#### Advanced Bounding Box Options

The bounding box filter supports two modes of operation:

- **Point Logic (Default)**: A simpler filtering mode that checks if a feature's
latitude/longitude coordinates fall within the bounding box. This works well for
point features but may not accurately filter polygons and polylines where the
coordinates represent the feature's centroid.

- **Full Geometry Logic**: A more comprehensive filtering mode that properly
handles polygons and polylines by checking if any part of their geometry
overlaps the bounding box instead of only checking the centroid. This mode,
currently implemented only for Geofield, is used when point-only logic is
disabled.

To configure these options:

1. In your View's Contextual Filter settings for the bounding box
2. Look for the "Apply point logic only" checkbox
3. Uncheck this option if you need proper filtering for polygons or polylines
stored in Geofields

## To Do

- Support full geometry logic for field types other than geofield (e.g.,
geolocation fields not currently supported).
- Support an optional altitude coordinate for Point positions
- Support additional coordinate systems

## Credits

This module was originally born from a patch by tmcw (Tom MacWright) to the
[OpenLayers module](https://drupal.org/node/889190#comment-3376628) and adapted
to model the
[Views Datasource module](https://drupal.org/project/views_datasource).

Much of the code is drawn directly from these sources.

## Maintainers

- Jeff Schuler (jeffschuler) - <https://www.drupal.org/u/jeffschuler>
- Pol Dellaiera (pol) - <https://www.drupal.org/u/pol>