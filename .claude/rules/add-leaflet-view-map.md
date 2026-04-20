# Add Leaflet View Map using with field_component reference

## When adding or creating a new Leaflet View Map style using any Content with field_component reference set the following options:

### Language:
- Rendering Language: Interface text language selected for page

### Leaflet Map Settings:
- Leaflet Map Tiles Layer: "Multilayers"
- Map Icon as "Icon Image Url/Path" with Icon URL: geomarker_icon_url field replacement
- set the following Feature Additional Properties value: "{"content_type":"{{ type }}","zoom_visibility_range":"{{ field_zoom_visibility_range_1 }}","exclude_from_map_bounds":"{{ field_exclude_from_map_bounds }}","active_type":"{{ field_active_type }}","active_status":"{{ active_status }}","active_level":"{{ active_level }}","popup_disabled":"{{ field_map_popup_disabled }}","tooltip_disabled":"{{ field_tooltip_disabled }}","icon_size_multiplier":1,"google_maps_address":"{{ field_google_maps_address }}"}"

### Fields
Among the others that you choose:
- add the "Geo Marker Icon Url" (geomarker_icon_url) computed Views field and make it hidden (exclude: true)
- add the field: Paragraph: Location Icon Width and make it hidden (exclude: true)
- add the field: Taxonomy term: Zoom Visibility Range
- add the field: Paragraph: Tooltip Disabled

## Filters
Among the others that you choose:
- Content Translation language: Interface text language selected for page
