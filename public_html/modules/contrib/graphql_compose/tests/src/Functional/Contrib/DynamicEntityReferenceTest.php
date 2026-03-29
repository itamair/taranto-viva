<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Contrib;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\block_content\BlockContentInterface;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;

/**
 * Test the DynamicEntityReference Field integration.
 *
 * @group graphql_compose
 */
class DynamicEntityReferenceTest extends GraphQLComposeBrowserTestBase {

  use TaxonomyTestTrait;

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
   * The linked node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $linkedNode;

  /**
   * The linked term.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected TermInterface $linkedTerm;

  /**
   * The linked block.
   *
   * @var \Drupal\block_content\BlockContentInterface
   */
  protected BlockContentInterface $linkedBlock;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dynamic_entity_reference',
    'graphql_compose_blocks',
    'block_content',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $block_type = BlockContentType::create([
      'id' => 'basic_block',
      'label' => 'Basic block',
    ]);
    $block_type->save();

    $vocabulary = Vocabulary::create([
      'name' => 'Test',
      'vid' => 'test',
    ]);
    $vocabulary->save();

    $this->createContentType([
      'type' => 'test',
      'name' => 'Test node type',
    ]);

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_dynamic',
      'type' => 'dynamic_entity_reference',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'exclude_entity_types' => FALSE,
        'entity_type_ids' => [
          'node',
          'taxonomy_term',
          'block_content',
        ],
      ],
    ]);

    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'test',
      'label' => 'Dynamic entity field test',
      'settings' => [
        'node' => [
          'handler' => 'default:node',
          'handler_settings' => [
            'target_bundles' => NULL,
          ],
        ],
        'taxonomy_term' => [
          'handler' => 'default:taxonomy_term',
          'handler_settings' => [
            'target_bundles' => NULL,
          ],
        ],
        'block_content' => [
          'handler' => 'default:block_content',
          'handler_settings' => [
            'target_bundles' => NULL,
          ],
        ],
      ],
    ]);

    $field->save();

    $this->linkedTerm = $this->createTerm($vocabulary, [
      'name' => 'Test term A',
      'weight' => 99,
    ]);

    $this->linkedTerm->save();

    $this->linkedBlock = BlockContent::create([
      'info' => 'My content block',
      'type' => 'basic_block',
    ]);

    $this->linkedBlock->save();

    $this->linkedNode = $this->createNode([
      'type' => 'test',
      'title' => 'Linked test',
      'body' => [
        'value' => 'Linked test content',
        'format' => 'plain_text',
      ],
      'status' => 1,
    ]);

    $this->node = $this->createNode([
      'type' => 'test',
      'title' => 'Test',
      'body' => [
        'value' => 'Test content',
        'format' => 'plain_text',
      ],
      'status' => 1,
      'field_dynamic' => [
        ['target_id' => $this->linkedNode->id(), 'target_type' => 'node'],
        ['target_id' => $this->linkedTerm->id(), 'target_type' => 'taxonomy_term'],
        ['target_id' => $this->linkedBlock->id(), 'target_type' => 'block_content'],
      ],
    ]);

    // Enable nodes and terms.
    $this->setEntityConfig('taxonomy_term', 'test', [
      'enabled' => TRUE,
    ]);

    $this->setEntityConfig('block_content', 'basic_block', [
      'enabled' => TRUE,
    ]);

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'field_dynamic', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Test load entity by id.
   */
  public function testDynamicEntityReference(): void {
    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}") {
          ... on NodeTest {
            dynamic {
              __typename
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertNotNull($content['data']['node']['dynamic'] ?? NULL);

    $dynamic = $content['data']['node']['dynamic'];

    $this->assertEquals('NodeTest', $dynamic[0]['__typename']);
    $this->assertEquals('TermTest', $dynamic[1]['__typename']);
    $this->assertEquals('BlockContentBasicBlock', $dynamic[2]['__typename']);
  }

}
