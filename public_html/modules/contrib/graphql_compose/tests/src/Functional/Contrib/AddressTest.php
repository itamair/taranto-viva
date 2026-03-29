<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Contrib;

use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;

/**
 * Test the Address Field integration.
 *
 * @group legacy
 */
class AddressTest extends GraphQLComposeBrowserTestBase {

  /**
   * We aren't concerned with strict config schema for contrib.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE; // @phpcs:ignore

  /**
   * The test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'address',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createContentType([
      'type' => 'test',
      'name' => 'Test node type',
    ]);

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_address',
      'entity_type' => 'node',
      'type' => 'address',
    ]);

    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'test',
      'label' => 'Address',
    ]);

    $field->save();

    $this->node = $this->createNode([
      'type' => 'test',
      'title' => 'Test',
      'body' => [
        'value' => 'Test content',
        'format' => 'plain_text',
      ],
      'status' => 1,
      'field_address' => [
        'organization' => 'My Org',
        'given_name' => 'Bob',
        'family_name' => 'Smith',
        'country_code' => 'AU',
        'address_line1' => '90210 Melbourne Street',
        'address_line2' => NULL,
        'locality' => 'Melbourne',
        'administrative_area' => 'VIC',
        'postal_code' => '3000',
      ],
    ]);

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'field_address', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Test load entity by id.
   */
  public function testAddress(): void {
    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}") {
          ... on NodeTest {
            address {
              organization
              givenName
              familyName
              country {
                name
                code
              }
              addressLine1
              addressLine2
              locality
              administrativeArea
              postalCode
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertNotNull($content['data']['node']['address'] ?? NULL);

    $address = $content['data']['node']['address'];

    $this->assertEquals('My Org', $address['organization']);
    $this->assertEquals('Bob', $address['givenName']);
    $this->assertEquals('Smith', $address['familyName']);
    $this->assertEquals('Australia', $address['country']['name']);
    $this->assertEquals('AU', $address['country']['code']);
    $this->assertEquals('90210 Melbourne Street', $address['addressLine1']);
    $this->assertNull($address['addressLine2']);
    $this->assertEquals('Melbourne', $address['locality']);
    $this->assertEquals('VIC', $address['administrativeArea']);
    $this->assertEquals('3000', $address['postalCode']);

  }

}
