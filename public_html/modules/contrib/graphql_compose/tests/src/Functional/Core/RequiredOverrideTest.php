<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Core;

use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Test if a field required override works.
 *
 * @group graphql_compose
 */
class RequiredOverrideTest extends GraphQLComposeBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createContentType([
      'type' => 'test',
      'name' => 'Test node type',
    ]);

    // Add a required field.
    // Look for or add the specified field to the requested entity bundle.
    FieldStorageConfig::create([
      'field_name' => 'required_field',
      'type' => 'text',
      'entity_type' => 'node',
    ])->save();

    FieldConfig::create([
      'field_name' => 'required_field',
      'entity_type' => 'node',
      'bundle' => 'test',
      'label' => 'Required field',
      'required' => TRUE,
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'optional_field',
      'type' => 'text',
      'entity_type' => 'node',
    ])->save();

    FieldConfig::create([
      'field_name' => 'optional_field',
      'entity_type' => 'node',
      'bundle' => 'test',
      'label' => 'Optional field',
      'required' => FALSE,
    ])->save();

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);
  }

  /**
   * Test default state of requirement.
   */
  public function testRequiredDefaultIsRequired(): void {

    $this->setFieldConfig('node', 'test', 'required_field', [
      'enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'optional_field', [
      'enabled' => TRUE,
    ]);

    $query = <<<GQL
      query {
        __type(name: "NodeTest") {
          name
          fields {
            name
            type {
              kind
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $map = [];
    foreach ($content['data']['__type']['fields'] as $field) {
      $map[$field['name']] = $field;
    }

    $this->assertArrayHasKey('requiredField', $map);
    $this->assertArrayHasKey('optionalField', $map);

    $this->assertEquals('NON_NULL', $map['requiredField']['type']['kind']);
    $this->assertNotEquals('NON_NULL', $map['optionalField']['type']['kind']);
  }

  /**
   * Test with explicit TRUE, it's required.
   */
  public function testRequiredIsRequired(): void {

    $this->setFieldConfig('node', 'test', 'required_field', [
      'enabled' => TRUE,
      'required' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'optional_field', [
      'enabled' => TRUE,
      'required' => TRUE,
    ]);

    $query = <<<GQL
      query {
        __type(name: "NodeTest") {
          name
          fields {
            name
            type {
              kind
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $map = [];
    foreach ($content['data']['__type']['fields'] as $field) {
      $map[$field['name']] = $field;
    }

    $this->assertArrayHasKey('requiredField', $map);
    $this->assertArrayHasKey('optionalField', $map);

    $this->assertEquals('NON_NULL', $map['requiredField']['type']['kind']);
    $this->assertEquals('NON_NULL', $map['optionalField']['type']['kind']);
  }

  /**
   * Test with explicit FALSE, it's not required.
   */
  public function testRequiredIsNotRequired(): void {

    $this->setFieldConfig('node', 'test', 'required_field', [
      'enabled' => TRUE,
      'required' => FALSE,
    ]);

    $this->setFieldConfig('node', 'test', 'optional_field', [
      'enabled' => TRUE,
      'required' => FALSE,
    ]);

    $query = <<<GQL
      query {
        __type(name: "NodeTest") {
          name
          fields {
            name
            type {
              kind
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $map = [];
    foreach ($content['data']['__type']['fields'] as $field) {
      $map[$field['name']] = $field;
    }

    $this->assertArrayHasKey('requiredField', $map);
    $this->assertArrayHasKey('optionalField', $map);

    $this->assertNotEquals('NON_NULL', $map['requiredField']['type']['kind']);
    $this->assertNotEquals('NON_NULL', $map['optionalField']['type']['kind']);
  }

}
