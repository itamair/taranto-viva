<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Contrib;

use Drupal\Core\Url;
use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\eck\EckEntityInterface;
use Drupal\eck\Entity\EckEntity;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Test the Eck Field integration.
 *
 * @group graphql_compose
 */
class EckTest extends GraphQLComposeBrowserTestBase {

  /**
   * We aren't concerned with strict config schema for contrib.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE; // @phpcs:ignore

  /**
   * The eck entity.
   *
   * @var \Drupal\eck\EckEntityInterface
   */
  protected EckEntityInterface $eck;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'eck',
    'graphql_compose_eck',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return [
      'administer eck entity types',
      'administer eck entities',
      'administer eck entity bundles',
      'bypass eck entity access',
      ...$this->graphqlPermissions,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $adminUser = $this->drupalCreateUser($this->getAdministratorPermissions());
    $this->drupalLogin($adminUser);

    $type = [
      'label' => 'Tester',
      'id' => 'tester',
      'created' => TRUE,
      'changed' => TRUE,
      'uid' => TRUE,
      'title' => TRUE,
      'status' => TRUE,
    ];

    $this->drupalGet(Url::fromRoute('eck.entity_type.add'));
    $this->submitForm($type, 'Create entity type');

    \Drupal::entityTypeManager()->clearCachedDefinitions();

    $bundle = [
      'name' => 'Test',
      'type' => 'test',
    ];

    $this->drupalGet(Url::fromRoute("eck.entity.tester_type.add"));
    $this->submitForm($bundle, 'Save bundle');

    // Add a body field to the bundle.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'body',
      'type' => 'text_long',
      'entity_type' => 'tester',
    ]);

    $field_storage->save();

    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'test',
      'label' => 'Body',
      'settings' => [
        'display_summary' => TRUE,
        'allowed_formats' => [],
      ],
    ])->save();

    $this->eck = EckEntity::create([
      'title' => 'Test entity',
      'entity_type' => 'tester',
      'type' => 'test',
      'body' => [
        'value' => 'Test content',
        'format' => 'plain_text',
      ],
      'status' => 1,
    ]);

    $this->eck->save();

    $this->setEntityConfig('tester', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    $this->setFieldConfig('tester', 'test', 'body', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Test load entity by id.
   */
  public function testEckReference(): void {
    $query = <<<GQL
      query {
        tester(id: "{$this->eck->uuid()}") {
          ... on TesterInterface {
            id
            title
            created {
              timestamp
            }
            changed {
              timestamp
            }
            status
          }
          ... on TesterTest {
            body {
              value
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertNotNull($content['data']['tester'] ?? NULL);

    $entity = $content['data']['tester'];

    $this->assertEquals($this->eck->uuid(), $entity['id']);
    $this->assertEquals('Test entity', $entity['title']);
    $this->assertEquals($this->eck->getCreatedTime(), $entity['created']['timestamp']);
    $this->assertEquals($this->eck->getChangedTime(), $entity['changed']['timestamp']);
    $this->assertEquals('Test content', $entity['body']['value']);
  }

}
