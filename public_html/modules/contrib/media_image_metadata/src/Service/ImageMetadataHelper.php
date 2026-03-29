<?php

declare(strict_types=1);

namespace Drupal\media_image_metadata\Service;

/**
 * Helper service for extracting image metadata (EXIF, IPTC, XMP).
 */
class ImageMetadataHelper {

  /**
   * Get raw metadata from an image file.
   *
   * @param string $file_path
   *   Path to the image file.
   *
   * @return array
   *   Associative array with keys: 'exif', 'iptc', 'xmp'.
   *
   * @throws \RuntimeException
   *   When the file is not readable.
   */
  public function getMetadataRaw(string $file_path): array {
    $metadata = [
      'exif' => [],
      'iptc' => [],
      'xmp' => [],
    ];

    if (!is_readable($file_path)) {
      throw new \RuntimeException("File not readable: $file_path");
    }

    // EXIF Metadata.
    if (function_exists('exif_read_data')) {
      $exif = @exif_read_data($file_path, 'ANY_TAG', TRUE);
      if ($exif !== FALSE) {
        $metadata['exif'] = $exif;
      }
    }

    // IPTC Metadata.
    $info = [];
    $size = @getimagesize($file_path, $info);
    if (isset($info['APP13'])) {
      $iptc = iptcparse($info['APP13']);
      if ($iptc !== FALSE) {
        foreach ($iptc as $tag => $value) {
          $metadata['iptc'][$tag] = is_array($value) && count($value) === 1 ? $value[0] : $value;
        }
      }
    }

    // XMP Metadata.
    $metadata['xmp'] = $this->extractXmp($file_path);

    return $metadata;
  }

  /**
   * Extract XMP from the image file.
   *
   * @param string $file_path
   *   Path to the image file.
   *
   * @return array
   *   Key-value pairs from XMP data.
   */
  private function extractXmp(string $file_path): array {
    $result = [];
    $data = @file_get_contents($file_path);

    if ($data !== FALSE && preg_match('/<x:xmpmeta.*?<\/x:xmpmeta>/s', $data, $matches)) {
      $xmp = $matches[0];
      try {
        $dom = new \DOMDocument();
        // Suppress warnings for malformed XML and load as XML, not HTML.
        @$dom->loadXML($xmp);
        $xpath = new \DOMXPath($dom);

        // Register XMP namespaces for XPath queries.
        $this->registerXmpNamespaces($xpath, $dom);

        // First, extract all attributes from rdf:Description elements.
        // Most XMP properties are stored as attributes here.
        $descriptions = $xpath->query('//rdf:Description');
        foreach ($descriptions as $description) {
          assert($description instanceof \DOMElement);
          if ($description->hasAttributes()) {
            foreach ($description->attributes as $attr) {
              assert($attr instanceof \DOMAttr);
              $attr_name = $attr->nodeName;
              $attr_value = trim($attr->nodeValue);

              // Skip namespace declarations and RDF structural attributes.
              if (str_starts_with($attr_name, 'xmlns:') ||
                  $attr_name === 'rdf:about' ||
                  $attr_value === '') {
                continue;
              }

              // Prefix with @ to indicate this is an attribute value.
              $result['@' . $attr_name] = $attr_value;
            }
          }
        }

        // Second, extract nested text content from complex structures.
        // Handle special RDF containers (rdf:Alt, rdf:Seq, rdf:Bag).
        $this->extractXmpTextContent($xpath, $result);
      }
      catch (\Exception $e) {
        // If XML parsing fails, silently continue.
      }
    }

    return $result;
  }

  /**
   * Extract text content from XMP nested structures.
   *
   * @param \DOMXPath $xpath
   *   The XPath object for querying the XMP DOM.
   * @param array $result
   *   The result array to populate (passed by reference).
   */
  private function extractXmpTextContent(\DOMXPath $xpath, array &$result): void {
    // Handle rdf:Alt (alternative text) - extract the first or default value.
    $alt_elements = $xpath->query('//rdf:Alt/rdf:li');
    foreach ($alt_elements as $element) {
      assert($element instanceof \DOMElement);
      $value = trim($element->textContent);
      if ($value === '') {
        continue;
      }

      // Build the property name from ancestors.
      $property_name = $this->buildXmpPropertyName($element);
      if ($property_name && !isset($result[$property_name])) {
        $result[$property_name] = $value;
      }
    }

    // Handle rdf:Seq (sequences) and rdf:Bag (unordered arrays).
    foreach (['rdf:Seq', 'rdf:Bag'] as $container_type) {
      $elements = $xpath->query('//' . $container_type . '/rdf:li');
      foreach ($elements as $index => $element) {
        assert($element instanceof \DOMElement);
        $value = trim($element->textContent);
        if ($value === '') {
          continue;
        }

        $property_name = $this->buildXmpPropertyName($element);
        if ($property_name) {
          // Store array items with index for sequences/bags.
          $result[$property_name . '[' . $index . ']'] = $value;
        }
      }
    }

    // Extract other simple text elements that aren't in RDF containers.
    $text_elements = $xpath->query('//*[not(*)][text()]');
    foreach ($text_elements as $element) {
      assert($element instanceof \DOMElement);
      $value = trim($element->textContent);
      if ($value === '') {
        continue;
      }

      // Skip if this is an rdf:li we already processed.
      if ($element->nodeName === 'rdf:li') {
        continue;
      }

      $property_name = $this->buildXmpPropertyName($element);
      if ($property_name && !isset($result[$property_name])) {
        $result[$property_name] = $value;
      }
    }
  }

  /**
   * Build a property name from an element's ancestry.
   *
   * @param \DOMElement $element
   *   The DOM element.
   *
   * @return string|null
   *   The property name, or NULL if it couldn't be built.
   */
  private function buildXmpPropertyName(\DOMElement $element): ?string {
    $parts = [];
    $current = $element;

    // Walk up the tree, collecting node names.
    while ($current instanceof \DOMElement) {
      $name = $current->nodeName;

      // Stop at rdf:RDF or x:xmpmeta.
      if ($name === 'rdf:RDF' || $name === 'x:xmpmeta') {
        break;
      }

      // Skip rdf:Description as it's just a container.
      if ($name !== 'rdf:Description') {
        array_unshift($parts, $name);
      }

      $current = $current->parentNode;
    }

    // Use pipe separator to avoid confusion with namespace colons.
    return $parts ? implode('|', $parts) : NULL;
  }

  /**
   * Register common XMP/RDF namespaces with the XPath object.
   *
   * @param \DOMXPath $xpath
   *   The XPath object to register namespaces with.
   * @param \DOMDocument $dom
   *   The DOM document (unused but kept for consistency).
   */
  private function registerXmpNamespaces(\DOMXPath $xpath, \DOMDocument $dom): void {
    // Register common XMP/RDF namespaces.
    $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
    $xpath->registerNamespace('x', 'adobe:ns:meta/');
    $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
    $xpath->registerNamespace('photoshop', 'http://ns.adobe.com/photoshop/1.0/');
    $xpath->registerNamespace('xmp', 'http://ns.adobe.com/xap/1.0/');
    $xpath->registerNamespace('xmpMM', 'http://ns.adobe.com/xap/1.0/mm/');
    $xpath->registerNamespace('Iptc4xmpCore', 'http://iptc.org/std/Iptc4xmpCore/1.0/xmlns/');
    $xpath->registerNamespace('xmpRights', 'http://ns.adobe.com/xap/1.0/rights/');
    $xpath->registerNamespace('crs', 'http://ns.adobe.com/camera-raw-settings/1.0/');
    $xpath->registerNamespace('stEvt', 'http://ns.adobe.com/xap/1.0/sType/ResourceEvent#');
    $xpath->registerNamespace('stRef', 'http://ns.adobe.com/xap/1.0/sType/ResourceRef#');
  }

  /**
   * Normalize metadata into a flat, human-friendly array.
   *
   * @param array $raw
   *   Raw metadata as returned by self::getMetadataRaw().
   *
   * @return array
   *   Normalized metadata.
   */
  public function normalizeMetadata(array $raw): array {
    $iptc = $raw['iptc'];
    $xmp = $raw['xmp'];
    $exif = $raw['exif'];

    return [
      'title' => $this->firstNonEmpty([
        $iptc['2#005'] ?? NULL,
        $xmp['dc:title|rdf:Alt|rdf:li'] ?? NULL,
        $iptc['2#105'] ?? NULL,
        $exif['IFD0']['ImageDescription'] ?? NULL,
        $exif['FILE']['FileName'] ?? NULL,
      ]),
      'alt' => $this->firstNonEmpty([
        $xmp['Iptc4xmpCore:AltTextAccessibility|rdf:Alt|rdf:li'] ?? NULL,
        $iptc['2#105'] ?? NULL,
        $iptc['2#120'] ?? NULL,
      ]),
      'caption' => $this->firstNonEmpty([
        $iptc['2#120'] ?? NULL,
        $xmp['dc:description|rdf:Alt|rdf:li'] ?? NULL,
        $exif['IFD0']['ImageDescription'] ?? NULL,
      ]),
      'credit' => $this->firstNonEmpty([
        // XMP credit is now extracted from attributes (prefixed with @).
        $xmp['@photoshop:Credit'] ?? NULL,
        $iptc['2#110'] ?? NULL,
        $exif['IFD0']['Artist'] ?? NULL,
      ]),
      'copyright' => $this->firstNonEmpty([
        $iptc['2#116'] ?? NULL,
        $exif['IFD0']['Copyright'] ?? NULL,
        $xmp['dc:rights|rdf:Alt|rdf:li'] ?? NULL,
      ]),
      'headline' => $this->firstNonEmpty([
        $iptc['2#105'] ?? NULL,
        $xmp['@photoshop:Headline'] ?? NULL,
      ]),
      'date_created' => $this->firstNonEmpty([
        $iptc['2#055'] ?? NULL,
        $exif['IFD0']['DateTime'] ?? NULL,
        $xmp['@photoshop:DateCreated'] ?? NULL,
        $xmp['@xmp:CreateDate'] ?? NULL,
        $exif['FILE']['FileDateTime'] ?? NULL,
      ]),
      'byline' => $iptc['2#080'] ?? NULL,
      'city' => $iptc['2#090'] ?? NULL,
      'province' => $iptc['2#095'] ?? NULL,
      'country' => $iptc['2#101'] ?? NULL,
      'source' => $iptc['2#115'] ?? NULL,
      'category' => $iptc['2#015'] ?? NULL,
      'supp_cat' => $iptc['2#020'] ?? NULL,
      // Additional EXIF camera data.
      'camera_model' => $this->firstNonEmpty([
        $exif['IFD0']['Model'] ?? NULL,
        $exif['EXIF']['Model'] ?? NULL,
      ]),
      'iso' => $exif['EXIF']['ISOSpeedRatings'] ?? NULL,
      'exposure' => !empty($exif['EXIF']['ExposureTime']) ? $this->normalizeFraction($exif['EXIF']['ExposureTime']) : NULL,
      'aperture' => !empty($exif['EXIF']['FNumber']) ? $this->normalizeFraction($exif['EXIF']['FNumber']) : NULL,
      'focal_length' => !empty($exif['EXIF']['FocalLength']) ? $this->normalizeFraction($exif['EXIF']['FocalLength']) : NULL,
    ];
  }

  /**
   * Helper to return the first non-empty value of a series of values.
   *
   * @param array $values
   *   An indexed list of values to check.
   *
   * @return mixed|null
   *   The first non-empty value in the array, checked with !empty().
   */
  private function firstNonEmpty(array $values) {
    foreach ($values as $value) {
      if (!empty($value)) {
        return $value;
      }
    }
    return NULL;
  }

  /**
   * Normalize fractions.
   *
   * @param string $fraction
   *   The fraction string to normalize (e.g., "1/60", "28/10").
   *
   * @return string|int|float
   *   The normalized fraction value.
   */
  private function normalizeFraction(string $fraction) {
    $parts = explode('/', $fraction);
    $top = (int) $parts[0];
    $bottom = (int) $parts[1];

    if ($top > $bottom) {
      // Value > 1.
      if (($top % $bottom) == 0) {
        $value = ($top / $bottom);
      }
      else {
        $value = round(($top / $bottom), 2);
      }
    }
    else {
      if ($top == $bottom) {
        // Value = 1.
        $value = '1';
      }
      else {
        // Value < 1.
        if ($top == 1) {
          $value = '1/' . $bottom;
        }
        else {
          if ($top != 0) {
            $value = '1/' . round(($bottom / $top), 0);
          }
          else {
            $value = '0';
          }
        }
      }
    }
    return $value;
  }

}
