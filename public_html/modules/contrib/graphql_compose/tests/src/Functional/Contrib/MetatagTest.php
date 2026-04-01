<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Contrib;

use Drupal\Component\Serialization\Json;
use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Test the Metatag module integration.
 *
 * @group graphql_compose
 */
class MetatagTest extends GraphQLComposeBrowserTestBase {

  /**
   * We aren't concerned with strict config schema for contrib.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE; // @phpcs:ignore

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'metatag',
    'graphql_compose_metatags',
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

    FieldStorageConfig::create([
      'field_name' => 'field_metatag',
      'type' => 'metatag',
      'entity_type' => 'node',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_metatag',
      'entity_type' => 'node',
      'bundle' => 'test',
      'label' => 'Metatags',
      'required' => FALSE,
    ])->save();

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);
  }

  /**
   * Test basic metatags on node.
   */
  public function testBasicMetatags(): void {
    $node = $this->createNode([
      'type' => 'test',
      'title' => 'Test node',
      'field_metatag' => serialize([
        'keywords' => 'test,node',
      ]),
    ]);

    $query = <<<GQL
      query {
        node(id: "{$node->uuid()}") {
          ... on MetaTagInterface {
            metatag {
              ... on MetaTag {
                __typename
                tag
                ... on MetaTagValue {
                  attributes {
                    name
                    content
                  }
                }
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $metatags = $content['data']['node']['metatag'];

    $this->assertContains([
      '__typename' => 'MetaTagValue',
      'tag' => 'meta',
      'attributes' => [
        'name' => 'keywords',
        'content' => 'test,node',
      ],
    ], $metatags);
  }

  /**
   * Test schema metatags on node.
   */
  public function testSchemaMetatags() {

    $this->container->get('module_installer')->install([
      'schema_metatag',
      'schema_web_site',
    ]);

    $node = $this->createNode([
      'type' => 'test',
      'title' => 'Test node',
      'field_metatag' => serialize([
        'schema_web_site_id' => 'AAA123',
        'schema_web_site_type' => 'WebSite',
      ]),
    ]);

    $query = <<<GQL
      query {
        node(id: "{$node->uuid()}") {
          ... on MetaTagInterface {
            metatag {
              ... on MetaTag {
                __typename
                tag
                ... on MetaTagScript {
                  content
                  attributes {
                    type
                  }
                }
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $metatags = $content['data']['node']['metatag'];

    $found = FALSE;
    foreach ($metatags as $metatag) {
      if ($metatag['__typename'] === 'MetaTagScript') {
        $this->assertEquals('application/ld+json', $metatag['attributes']['type']);

        $content = Json::decode($metatag['content']);

        $this->assertEquals('AAA123', $content['@graph'][0]['@id']);
        $this->assertEquals('WebSite', $content['@graph'][0]['@type']);

        $found = TRUE;
      }
    }
    $this->assertTrue($found);
  }

}
