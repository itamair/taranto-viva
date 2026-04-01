<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\search_api_typesense\Entity\TypesenseSchema;

/**
 * Event fired when a Typesense schema is altered.
 */
class TypesenseSchemaEvent extends Event {

  /**
   * TypesenseSchemaEvent constructor.
   *
   * @param \Drupal\search_api_typesense\Entity\TypesenseSchema $schema
   *   The schema array.
   * @param array $generatedSchema
   *   The generated schema array.
   */
  public function __construct(
    private readonly TypesenseSchema $schema,
    private array $generatedSchema,
  ) {}

  /**
   * Gets the schema.
   *
   * @return \Drupal\search_api_typesense\Entity\TypesenseSchema
   *   The schema.
   */
  public function getSchema(): TypesenseSchema {
    return $this->schema;
  }

  /**
   * Gets the generated schema.
   *
   * @return array
   *   The generated schema.
   */
  public function getGeneratedSchema(): array {
    return $this->generatedSchema;
  }

  /**
   * Sets the generated schema.
   *
   * @param array $generated_schema
   *   The generated schema.
   */
  public function setGeneratedSchema(array $generated_schema): void {
    // TODO: Validate the generated schema.
    $this->generatedSchema = $generated_schema;
  }

}
