<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\DocumentSplitter;

/**
 * Represents a chunk of text.
 */
final class Chunk {

  /**
   * Chunk constructor.
   *
   * @param string $text
   *   The text of the chunk.
   * @param int $chunk_number
   *   The number of the chunk.
   */
  public function __construct(
    public readonly string $text,
    public readonly int $chunk_number,
  ) {
  }

  /**
   * Return a new Chunk object with the given text.
   */
  public function withText(string $text): self {
    return new self($text, $this->chunk_number);
  }

}
