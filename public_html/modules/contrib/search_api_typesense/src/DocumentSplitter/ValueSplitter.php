<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\DocumentSplitter;

/**
 * Split a document into chunks.
 */
class ValueSplitter {

  /**
   * Spit a document into chunks.
   *
   * @return \Drupal\search_api_typesense\DocumentSplitter\Chunk[]
   *   An array of Chunk objects.
   */
  public static function splitDocument(
    string $text,
    int $max_length = 1000,
    string $separator = ' ',
    int $word_overlap = 0,
  ): array {
    if ($text === '') {
      return [];
    }
    if ($max_length <= 0) {
      return [];
    }

    if ($separator === '') {
      return [];
    }

    if (\strlen($text) <= $max_length) {
      return [new Chunk($text, 1)];
    }

    $chunks = [];
    $words = \explode($separator, $text);
    if ($word_overlap > 0) {
      $chunks = self::createChunksWithOverlap(
        $words,
        $max_length,
        $separator,
        $word_overlap,
      );
    }

    $splitted_documents = [];
    $chunkNumber = 1;
    foreach ($chunks as $chunk) {
      $newDocument = new Chunk($chunk, $chunkNumber);
      $chunkNumber++;
      $splitted_documents[] = $newDocument;
    }

    return $splitted_documents;
  }

  /**
   * Split an array of documents into chunks.
   *
   * @return \Drupal\search_api_typesense\DocumentSplitter\Chunk[]
   *   An array of Chunk objects.
   */
  public static function splitDocuments(
    array $documents,
    int $max_length = 1000,
    string $separator = '.',
    int $word_overlap = 0,
  ): array {
    $splitted_documents = [];
    foreach ($documents as $document) {
      $splitted_documents = \array_merge(
        $splitted_documents,
        ValueSplitter::splitDocument(
          $document,
          $max_length,
          $separator,
          $word_overlap,
        ),
      );
    }

    return $splitted_documents;
  }

  /**
   * Create chunks with overlap.
   *
   * @return array<string>
   *   An array of chunks.
   */
  private static function createChunksWithOverlap(
    array $words,
    int $max_length,
    string $separator,
    int $word_overlap,
  ): array {
    $chunks = [];
    $current_chunk = [];
    $current_chunk_length = 0;
    foreach ($words as $word) {
      if ($word === '') {
        continue;
      }

      if ($current_chunk_length + \strlen(
          $separator . $word,
        ) <= $max_length || $current_chunk === []) {
        $current_chunk[] = $word;
        $current_chunk_length = self::calcChunkLength($current_chunk, $separator);
      }
      else {
        // Add the chunk with overlap.
        $chunks[] = \implode($separator, $current_chunk);

        // Calculate overlap words.
        $calculated_overlap = \min($word_overlap, \count($current_chunk) - 1);
        $overlap_words = $calculated_overlap > 0 ? \array_slice(
          $current_chunk,
          -$calculated_overlap,
        ) : [];

        // Start a new chunk with overlap words.
        $current_chunk = [...$overlap_words, $word];
        $current_chunk[0] = \trim($current_chunk[0]);
        $current_chunk_length = self::calcChunkLength($current_chunk, $separator);
      }
    }

    if ($current_chunk !== []) {
      $chunks[] = \implode($separator, $current_chunk);
    }

    return $chunks;
  }

  /**
   * Calculate the length of a chunk.
   */
  private static function calcChunkLength(
    array $current_chunk,
    string $separator,
  ): int {
    return \array_sum(\array_map('strlen', $current_chunk)) + \count(
        $current_chunk,
      ) * \strlen($separator) - 1;
  }

}
