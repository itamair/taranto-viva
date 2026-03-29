<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\DocumentSplitter;

/**
 * Split a document into fixed length chunks.
 */
class FixedLengthDocumentSplitter implements DocumentSplitterInterface {

  /**
   * {@inheritdoc}
   */
  public function split(
    array $fields_to_embed,
    array $fields_to_prepend_to_all_chunks,
    array $document,
    int $max_length = 1000,
    int $word_overlap = 0,
  ): array {
    // Only merge the fields that are not also in the fields to prepend to all
    // chunks.
    $text = $this->mergeFields(\array_diff_key($fields_to_embed, $fields_to_prepend_to_all_chunks));

    $chunks = ValueSplitter::splitDocument(
      text        : $text,
      max_length  : $max_length,
      word_overlap: $word_overlap,
    );

    if (\count($fields_to_prepend_to_all_chunks) > 0) {
      $chunks = $this->enhanceChunks($chunks, $document, $fields_to_prepend_to_all_chunks);
    }

    return $chunks;
  }

  /**
   * Merge fields into a single string.
   */
  private function mergeFields(array $fields): string {
    $aggregated = [];

    foreach ($fields as $field_value) {
      if (\is_string($field_value)) {
        $aggregated[] = $field_value;
      }

      if (\is_array($field_value)) {
        foreach ($field_value as $value) {
          $aggregated[] = $value;
        }
      }
    }

    return \implode(' ', $aggregated);
  }

  /**
   * Enhance the chunks with the fields that should be in all chunks.
   */
  private function enhanceChunks(
    array $chunks,
    array $document,
    array $fields_all_chunks,
  ): array {
    $enhanced_chunks = [];

    $x = \array_filter($document, static function ($field_name) use ($fields_all_chunks) {
      return \in_array($field_name, $fields_all_chunks, TRUE);
    }, ARRAY_FILTER_USE_KEY);

    $y = $this->mergeFields($x);

    foreach ($chunks as $chunk) {
      $enhanced_chunks[] = $chunk->withText(
        $y . ' ' . $chunk->text,
      );
    }

    return $enhanced_chunks;
  }

}
