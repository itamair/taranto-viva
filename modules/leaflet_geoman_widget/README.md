###Leaflet Geoman Widget
(replicate original functionlities of [Leaflet Widget](https://www.drupal.org/project/leaflet_widget) module for Drupal 8)

Allows the input & definition of Geofield values (Points and Multi Geometries) via Leaflet maps.

![](demo/demo.gif)

It supports drawing of
- Markers
- Polyline
- Rectangle
- Polygon / Multipolygon

It has
- Edit Mode
- Drag Mode
- Cut Mode
- Delete Mode

The GeoJSON Data is saved in the Geofield module.

![](demo/settings.gif)


For drawing of layers it used the library [Leaflet.Geoman](https://github.com/geoman-io/leaflet-geoman) instead of Leaflet.draw:

Because [Leaflet.Draw](http://leaflet.github.io/Leaflet.draw/docs/leaflet-draw-latest.html) does not support MULTIPOLYGON, the Leaflet.Geoman library was used.


####Authors/Credits

- [itamair](https://www.drupal.org/u/itamair): porting into Leaflet module for Drupal 8.8 & 9:
- [ayalon](https://www.drupal.org/u/ayalon): development of [Leaflet Widget](https://www.drupal.org/project/leaflet_widget) module for Drupal 8:
- [bforchhammer](https://www.drupal.org/u/bforchhammer): original development [on GitHub](https://github.com/bforchhammer/leaflet_widget) by
