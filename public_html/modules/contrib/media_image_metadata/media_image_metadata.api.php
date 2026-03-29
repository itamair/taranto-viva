<?php

/**
 * @file
 * Hooks specific to the Media Image Metadata module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the normalized metadata to be used by the media source plugin.
 *
 * The $normalized_metadata array contains the values that will be used by
 * the source plugin to map embedded metadata into media entity fields.
 * Sometimes, it's possible that the exact value you are looking for is also
 * embedded in the image as exif/iptc/xmp, but our normalization process didn't
 * use it because it's not the most common use case for this type of data. In
 * these cases, this hook can be used to overwrite the value in the
 * $normalized_metadata array with another, more appropriate value, that may be
 * available in $raw_metadata. This latter array contains all information this
 * module is able to extract from image files.
 */
function hook_media_image_metadata_alter(array &$normalized_metadata, array $raw_metadata) {
  // Use as the Caption the data from IPTC 2:40 ("Special Instruction").
  if (!empty($raw_metadata['iptc']['2#40'])) {
    $normalized_metadata['caption'] = $raw_metadata['iptc']['2#40'];
  }
}

/**
 * @} End of "addtogroup hooks".
 */
