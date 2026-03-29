<?php

namespace Drupal\mcp\ServerFeatures;

/**
 * Tool Class.
 *
 * Represents a tool that can be executed.
 */
class Tool implements ToolInterface, \JsonSerializable {

  public function __construct(
    string $name,
    string $description,
    mixed $inputSchema,
    ?string $title = NULL,
    mixed $outputSchema = NULL,
    ?ToolAnnotations $annotations = NULL,
  ) {
    $this->name = $name;
    $this->description = $description;
    $this->inputSchema = $inputSchema;
    $this->title = $title;
    $this->outputSchema = $outputSchema;
    $this->annotations = $annotations;
  }

  /**
   * Tool name.
   */
  public string $name;

  /**
   * Tool Description.
   */
  public string $description;

  /**
   * Input Schema definition.
   */
  public mixed $inputSchema;

  /**
   * Tool title (optional human-readable display name).
   */
  public ?string $title;

  /**
   * Output Schema definition (optional).
   */
  public mixed $outputSchema;

  /**
   * Optional additional tool information.
   *
   * Display name precedence order is: title, annotations.title, then name.
   *
   * @var \Drupal\mcp\ServerFeatures\ToolAnnotations|null
   */
  public ?ToolAnnotations $annotations;

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    $data = [
      'name' => $this->name,
      'description' => $this->description,
      'inputSchema' => $this->inputSchema,
    ];

    if ($this->title !== NULL) {
      $data['title'] = $this->title;
    }

    if ($this->outputSchema !== NULL) {
      $data['outputSchema'] = $this->outputSchema;
    }

    if ($this->annotations !== NULL) {
      $annotationsData = $this->annotations->jsonSerialize();
      if (!empty($annotationsData)) {
        $data['annotations'] = $annotationsData;
      }
    }

    return $data;
  }

}
