<?php

declare(strict_types=1);

namespace Drupal\mcp\ServerFeatures;

/**
 * Tool Annotations Class.
 *
 * Additional properties describing a Tool to clients.
 */
class ToolAnnotations implements \JsonSerializable {

  public function __construct(
    public ?string $title = NULL,
    public ?bool $readOnlyHint = NULL,
    public ?bool $idempotentHint = NULL,
    public ?bool $destructiveHint = NULL,
    public ?bool $openWorldHint = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    $data = [];

    if ($this->title !== NULL) {
      $data['title'] = $this->title;
    }

    if ($this->readOnlyHint !== NULL) {
      $data['readOnlyHint'] = $this->readOnlyHint;
    }

    if ($this->idempotentHint !== NULL) {
      $data['idempotentHint'] = $this->idempotentHint;
    }

    if ($this->destructiveHint !== NULL) {
      $data['destructiveHint'] = $this->destructiveHint;
    }

    if ($this->openWorldHint !== NULL) {
      $data['openWorldHint'] = $this->openWorldHint;
    }

    return $data;
  }

}
