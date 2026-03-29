<?php

namespace Drupal\entity_mesh;

/**
 * Helper trait to provide common methods.
 */
trait HelperTrait {

  /**
   * Sanitize a URL.
   *
   * Truncates the URL to match the database field length limit (256 characters)
   * for target_href, target_path, and target_scheme fields.
   *
   * @param string $url
   *   The URL to sanitize.
   *
   * @return string
   *   The sanitized URL, truncated to 256 characters.
   */
  public function sanitizeUrl(string $url): string {
    $url = trim($url, ' ');
    return mb_substr($url, 0, 256);
  }

  /**
   * Generate a hash from two strings.
   *
   * @param string $string
   *   The first string.
   *
   * @return string
   *   The generated hash.
   */
  public function generateHash(string $string): string {
    // If string is empty, we use the current time.
    if ($string === '') {
      $string = (string) time();
    }
    return hash('md5', $string);
  }

}
