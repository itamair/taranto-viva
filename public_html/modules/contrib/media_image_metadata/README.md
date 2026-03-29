# Media Image Metadata

A Drupal module that extracts embedded metadata from image files (EXIF, IPTC, XMP) and makes it available for use in media entities.

## Features

- **Metadata Extraction**: Automatically extracts EXIF, IPTC, and XMP metadata from uploaded images
- **Field Mapping**: Maps extracted metadata to Drupal media entity fields
- **Alt Text Population**: Automatically populates image alt text from embedded metadata
- **Media Library Integration**: Enhanced upload forms for seamless metadata handling

## Requirements

- Media module (core dependency)
- (Optional) PHP 8.2+ with EXIF extension enabled
  - Only if the metadata you are interested in is present in EXIF data. If the php function
    `exif_read_data()` doesn't exist, all EXIF metadata will be skipped.

## Installation

1. Place the module in your `modules/custom` directory
2. Enable the module: `drush en media_image_metadata`

## Configuration

### Media Type Setup

1. Navigate to **Structure → Media types**
2. Edit an existing Image media type or create a new one
3. In the media type configuration:
   - Set **Extract embedded metadata** to "Yes"
   - Optionally enable **Populate ALT property** to auto-fill alt text from metadata
4. Save the media type form
5. Configure field mappings to connect metadata attributes to your media fields, and save the form again

### Available Metadata Attributes

The module extracts and normalizes the following metadata:

#### Basic Information
- **Title**: Image title from IPTC, XMP, or EXIF
- **Alt**: Alternative text for accessibility
- **Caption**: Image description/caption
- **Credit**: Photo credit information from IPTC, EXIF, or XMP
- **Byline**: Photographer/author name
- **Copyright**: Copyright information from IPTC, EXIF, or XMP

#### Location Data
- **City**: Location city
- **Province**: State/province
- **Country**: Country name

#### Technical Data
- **Date Created**: When the image was created from IPTC, EXIF, or XMP
- **Source**: Image source
- **Headline**: Image headline from IPTC or XMP
- **Category**: Primary category
- **Supplemental Category**: Additional categories

#### Camera Information
- **Camera Model**: Camera make/model
- **ISO Speed**: ISO setting
- **Exposure Time**: Shutter speed
- **Aperture**: F-stop value
- **Focal Length**: Lens focal length

## Usage

### Basic Usage

Once configured, metadata extraction happens automatically when images are uploaded through:
- Media Library forms (pre-populates the form)
- Inline Entity forms (pre-populates the form)
- Direct media entity creation (populates values upon form submission / entity save)

### Programmatic Access

Access metadata programmatically through the media source plugin:

```php
$media = \Drupal\media\Entity\Media::load($media_id);
$source = $media->getSource();

// Get specific metadata
$title = $source->getMetadata($media, 'title');
$caption = $source->getMetadata($media, 'caption');
$camera_model = $source->getMetadata($media, 'camera_model');
```

### Using the Helper Service

Access raw metadata through the helper service:

```php
$helper = \Drupal::service(ImageMetadataHelper::class);
$raw_metadata = $helper->getMetadataRaw('/path/to/image.jpg');
$normalized = $helper->normalizeMetadata($raw_metadata);
```

## Metadata Attributes Provided

<table id="metadata-table" style="text-align: center;">
  <thead>
  <tr>
    <th class="col-attr" style="text-align: center; vertical-align: middle;">Metadata Attribute</th>
    <th class="col-first" style="text-align: center; vertical-align: middle;">1st source</th>
    <th class="col-second" style="text-align: center; vertical-align: middle;">2nd source</th>
    <th class="col-third" style="text-align: center; vertical-align: middle;">3rd source</th>
    <th class="col-fourth" style="text-align: center; vertical-align: middle;">4th source</th>
  </tr>
  </thead>
  <tbody>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>title</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(IPTC) <br><code>2#005</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>(XMP) <br><code>dc:title|rdf:Alt|rdf:li</code></sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>(IPTC) <br><code>2#105</code></sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>(EXIF) <br><code>ImageDescription</code></sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>alt</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(XMP) <br><code>Iptc4xmpCore:AltTextAccessibility|rdf:Alt|rdf:li</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>(IPTC) <br><code>2#105</code></sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>(IPTC) <br><code>2#120</code></sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>caption</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(IPTC) <br><code>2#120</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>(XMP) <br><code>dc:description|rdf:Alt|rdf:li</code></sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>(EXIF) <br><code>ImageDescription</code></sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>credit</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(XMP) <br><code>@photoshop:Credit</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>(IPTC) <br><code>2#110</code></sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>(EXIF) <br><code>Artist</code></sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>copyright</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(IPTC) <br><code>2#116</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>(EXIF) <br><code>Copyright</code></sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>(XMP) <br><code>dc:rights|rdf:Alt|rdf:li</code></sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>headline</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(IPTC) <br><code>2#105</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>(XMP) <br><code>@photoshop:Headline</code></sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>date_created</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(IPTC) <br><code>2#055</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>(EXIF) <br><code>DateTime</code></sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>(XMP) <br><code>@photoshop:DateCreated</code></sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>(EXIF) <br><code>FileDateTime</code></sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>byline</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(IPTC) <br><code>2#080</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>city</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(IPTC) <br><code>2#090</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>province</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(IPTC) <br><code>2#095</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>country</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(IPTC) <br><code>2#101</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>source</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(IPTC) <br><code>2#115</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>category</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(IPTC) <br><code>2#015</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>supp_cat</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(IPTC) <br><code>2#020</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>camera_model</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(EXIF) <br><code>IFD0.Model</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>(EXIF) <br><code>EXIF.Model</code></sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>iso</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(EXIF) <br><code>ISOSpeedRatings</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>exposure</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(EXIF) <br><code>ExposureTime</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>aperture</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(EXIF) <br><code>FNumber</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
    </tr>
    <tr>
      <td data-label="Metadata Attribute" style="text-align: center; vertical-align: middle;"><sup><code>focal_length</code></sup></td>
      <td data-label="1st source" style="text-align: center; vertical-align: middle;"><sup>(EXIF) <br><code>FocalLength</code></sup></td>
      <td data-label="2nd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="3rd source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
      <td data-label="4th source" style="text-align: center; vertical-align: middle;"><sup>—</sup></td>
    </tr>
  </tbody>
</table>

The above table shows the prioritization order for each metadata attribute. For example, when looking for the `caption` metadata attribute, the module will first look at the `2#120` value (in IPTC data), and if this is empty, it will fall back to `dc:description` (in XMP data), and finally to `ImageDescription` (in EXIF data).

For attributes with multiple fallback sources, the module uses a prioritized approach: it checks each source in order and uses the first non-empty value found.

**Note:** The `title` attribute will fall back to the filename, if none of the above
 sources returned a non-empty value.

## I am not getting the metadata I need!

You can easily change the mapping used by this module and get a different piece of metadata if
you know where it's stored.

Implement `hook_media_image_metadata_alter()` and change the value to be used for a given metadata attribute:

```php
function mymodule_media_image_metadata_alter(array &$normalized_metadata, array $raw_metadata) {
  // Use IPTC "Special Instruction" as caption.
  if (!empty($raw_metadata['iptc']['2#40'])) {
    $normalized_metadata['caption'] = $raw_metadata['iptc']['2#40'];
  }
}
```

## Settings

### Logging Configuration

If you want the module to log exceptions thrown when trying to extract metadata from images, you can set
this value in your `settings.php` file:

```php
$settings['media_image_metadata_log_extraction_failures'] = TRUE;
```

## License

This module is licensed under the GPL-2.0+ license.
