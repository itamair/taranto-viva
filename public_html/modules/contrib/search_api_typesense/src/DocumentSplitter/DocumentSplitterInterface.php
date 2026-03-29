<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\DocumentSplitter;

/**
 * Split a document into chunks.
 */
interface DocumentSplitterInterface {

  /**
   * Spit a document into chunks.
   *
   * @return \Drupal\search_api_typesense\DocumentSplitter\Chunk[]
   *   An array of Chunk objects.
   */
  public function split(
    array $fields_to_embed,
    array $fields_to_prepend_to_all_chunks,
    array $document,
    int $max_length = 1000,
    int $word_overlap = 0,
  ): array;

}
