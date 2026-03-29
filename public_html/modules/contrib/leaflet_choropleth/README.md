# Leaflet Choropleth

## Overview

The Leaflet Choropleth module provides advanced choropleth mapping capabilities
for Drupal applications, enabling the visualization of statistical data through
color-coded geographic regions. This module seamlessly integrates with the
existing Drupal Leaflet ecosystem to deliver professional-grade thematic mapping
functionality.

## Installation

### Composer Installation

```bash
composer require drupal/leaflet_choropleth
```

### Module Installation

**Via Drupal Admin UI:**
1. Navigate to `/admin/modules`
2. Search for "Leaflet Choropleth"
3. Check the module checkbox
4. Click "Install"

**Via Drush:**
```bash
drush en leaflet_choropleth
```

## Configuration and Usage

### View Style Integration

The module provides a **leaflet_choropleth_map** view style plugin that
integrates seamlessly with the **leaflet_map** view style from the Leaflet
module and its "leaflet_view" submodule.
This integration adds and enables an additional "Choropleth Map Settings"
section where to configure choropleth-specific settings alongside standard
leaflet mapping options.

### Usage requirements and setup

The choropleth visualization applies only to features with polygon or
multipolygon geometry types.

To enable a choropleth map for your data, follow these steps:
- create a **leaflet_choropleth_map** view style that includes Drupal content or
  entities with a numeric field (integer or float) to be used as the basis for
  the choropleth data visualization
  Note: Of course first you need to set the Geofield filed type that should
  drive the Leaflet Map (@see the Leaflet & Leaflet Views module instructions)
- in the "Choropleth Map Settings" section enable the Choroplath Map option and
  set the data source to the desired numeric field
- configure additional settings such as classification methods, color scale,
  number of classes, geometries display options, legend configuration and other
  display preferences

### Classifications and Color Ranges

The module implements a scalable and custom extensible Drupal plugin system
for data classification and color schemes:

**Classification Methods:**
- **Equal Interval**: Divides data into equal-sized ranges
- **Geometric Interval**: Creates class breaks based on geometric progression
- **Natural Breaks (Jenks)**: Optimizes class boundaries to minimize variance
- **Quantile**: Divides data into equal-sized groups
- **Standard Deviation**: Classes based on statistical deviation from mean
- Etc: additional classification methods (custom implementations)
  available through the plugin system

**Color Ranges:**
- **Sequential**: Single-hue progressions (Blues, Greens, Reds, Grays)
- **Diverging**: Two-color endpoints with neutral midpoint (Red-Blue)
- **Categorical**: Distinct colors for categorical data (hint: refer to
  the leaflet_choropleth.api.php file and the
  leaflet_choropleth_color_scale_alter hook for suggestion on how to accomplish
  this)
- etc: additional color ranges (custom implementations)
  available through the plugin system

### Legend Configuration

The module provides a configurable legend system that can be:
- Added as a Leaflet Control directly within the map
- Positioned anywhere on the map interface
- Collapsable, and eventually collapsed by default
- Fully customized for styling and content
- Translatable throughout Drupal Configuration translation system
- Integrated with the choropleth theming system
- Configurable with custom & dynamic Labels for each class.

### Theming Integration

Leaflet choropleth layers can be seamlessly integrated and mixed with existing
leaflet mapping features, allowing for:
- Combining choropleth analysis with marker & geometries visualizations
- Multi-layer mapping compositions, with flexible theming and styling options

## Dependencies
- [Leaflet module](https://www.drupal.org/project/leaflet)
(and its & Leaflet Views submodule)

## Development Status and Roadmap

The Leaflet Choropleth module is being developed and deployed to
the Drupal community for testing and review, with the intention of eventual
integration into the main Leaflet module as a leaflet_choropleth submodule
once thoroughly validated and consolidated by the community.

## Support
For issues, feature requests, and community support, please visit the
[project page on Drupal.org](https://www.drupal.org/project/leaflet_choropleth).

## Authors and Credits
- **Italo Mairo** - [itamair](https://www.drupal.org/u/itamair)
