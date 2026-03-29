<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Contrib;

use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;

/**
 * Test the Social Media Links Field integration.
 *
 * @group graphql_compose
 */
class SocialMediaLinksTest extends GraphQLComposeBrowserTestBase {

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
    'social_media_links',
    'social_media_links_field',
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
      'field_name' => 'field_social',
      'entity_type' => 'node',
      'type' => 'social_media_links_field',
    ]);

    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'test',
      'label' => 'Social',
      'settings' => [
        'platforms' => [
          'linkedin' => [
            'enabled' => TRUE,
            'description' => 'Find me',
            'weight' => 1,
          ],
          'github' => [
            'enabled' => TRUE,
            'description' => 'Fork me',
            'weight' => 2,
          ],
          'facebook' => [
            'enabled' => TRUE,
            'description' => 'Like me',
            'weight' => 3,
          ],
          'tiktok' => [
            'enabled' => FALSE,
            'description' => 'Dance me',
            'weight' => 4,
          ],
        ],
      ],
    ]);

    $field->save();

    $this->node = $this->createNode([
      'type' => 'test',
      'title' => 'Test',

      'field_social' => [
        0 => [
          'platform' => NULL,
          'value' => NULL,
          'platform_values' => [
            'facebook' => [
              'value' => 'boomerang',
            ],
            'github' => [
              'value' => 'coder',
            ],
            'linkedin' => [
              'value' => 'business',
            ],
          ],
        ],
      ],
      'body' => [
        'value' => 'Test content',
        'format' => 'plain_text',
      ],
      'status' => 1,
    ]);

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'field_social', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Test social media field integration.
   */
  public function testSocialMediaField(): void {
    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}") {
          ... on NodeTest {
            social {
              id
              name
              value
              url
              weight
              description
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $social = $content['data']['node']['social'];

    $this->assertCount(3, $social);

    // Util find provider by id.
    $provider = fn ($id) => array_filter(
      $social,
      fn($item) => $item['id'] === $id
    );

    $linkedin = current($provider('linkedin'));
    $github = current($provider('github'));

    $this->assertEquals('linkedin', $linkedin['id']);
    $this->assertEquals('business', $linkedin['value']);
    $this->assertEquals('Find me', $linkedin['description']);
    $this->assertEquals(1, $linkedin['weight']);

    $this->assertEquals('https://github.com/coder', $github['url']);
    $this->assertEquals('coder', $github['value']);

  }

}
