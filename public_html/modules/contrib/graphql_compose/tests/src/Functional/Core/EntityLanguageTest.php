<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Core;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\link\LinkItemInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\system\Entity\Menu;
use Drupal\system\MenuInterface;

/**
 * Test the entity languages are loading as expected.
 *
 * @group graphql_compose
 */
class EntityLanguageTest extends GraphQLComposeBrowserTestBase {

  /**
   * The test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * The test menu.
   *
   * @var \Drupal\system\MenuInterface
   */
  protected MenuInterface $menu;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'graphql_compose_menus',
    'graphql_compose_routes',
    'content_translation',
    'config_translation',
    'menu_link_content',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createContentType([
      'type' => 'test',
      'name' => 'Test node type',
      'translatable' => TRUE,
    ]);

    ConfigurableLanguage::createFromLangcode('ja')->save();
    ConfigurableLanguage::createFromLangcode('de')->save();

    // Create a non-standard language.
    ConfigurableLanguage::createFromLangcode('fr-CA')->save();

    // Enable translations for the test node type.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'test')
      ->setDefaultLangcode(LanguageInterface::LANGCODE_SITE_DEFAULT)
      ->setLanguageAlterable(TRUE)
      ->save();

    \Drupal::service('content_translation.manager')->setEnabled('node', 'test', TRUE);

    $this->menu = Menu::create([
      'id' => 'test',
      'label' => 'Test Menu',
    ]);
    $this->menu->save();

    $this->node = $this->createNode([
      'type' => 'test',
      'title' => 'Test',
      'status' => 1,
      'promote' => 1,
      'sticky' => 0,
      'langcode' => 'en',
      'path' => [
        'alias' => '/test',
      ],
    ]);

    $this->node->addTranslation('ja', [
      'title' => 'Test (JA)',
      'path' => [
        'alias' => '/test',
      ],
    ])->save();

    $this->node->addTranslation('de', [
      'title' => 'Test (DE)',
      'path' => [
        'alias' => '/test',
      ],
    ])->save();

    $this->node->addTranslation('fr-CA', [
      'title' => 'Test (fr-CA)',
      'path' => [
        'alias' => '/test',
      ],
    ])->save();

    $this->setEntityConfig('menu', 'test', [
      'enabled' => TRUE,
    ]);

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
      'routes_enabled' => TRUE,
    ]);
  }

  /**
   * Test load entity by id.
   */
  public function testNodeLoadByUuid(): void {
    $query = <<<GQL
      query {
        default: node(id: "{$this->node->uuid()}") {
          ... on NodeInterface {
            title
            langcode {
              id
            }
          }
        }

        en: node(id: "{$this->node->uuid()}", langcode: "en") {
          ... on NodeInterface {
            title
            langcode {
              id
            }
          }
        }

        ja: node(id: "{$this->node->uuid()}", langcode: "ja") {
          ... on NodeInterface {
            title
            langcode {
              id
            }
          }
        }

        de: node(id: "{$this->node->uuid()}", langcode: "de") {
          ... on NodeInterface {
            title
            langcode {
              id
            }
          }
        }

        frCA: node(id: "{$this->node->uuid()}", langcode: "fr-CA") {
          ... on NodeInterface {
            title
            langcode {
              id
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $default = $content['data']['default'];
    $this->assertEquals('Test', $default['title']);
    $this->assertEquals('en', $default['langcode']['id']);

    $en = $content['data']['en'];
    $this->assertEquals('Test', $en['title']);
    $this->assertEquals('en', $en['langcode']['id']);

    $ja = $content['data']['ja'];
    $this->assertEquals('Test (JA)', $ja['title']);
    $this->assertEquals('ja', $ja['langcode']['id']);

    $de = $content['data']['de'];
    $this->assertEquals('Test (DE)', $de['title']);
    $this->assertEquals('de', $de['langcode']['id']);

    $de = $content['data']['frCA'];
    $this->assertEquals('Test (fr-CA)', $de['title']);
    $this->assertEquals('fr-CA', $de['langcode']['id']);
  }

  /**
   * Test load entity by route (language).
   */
  public function testRouteLoadWithLangcode(): void {

    $query = <<<GQL
      query {
        en: route(path: "/test", langcode: "en") {
          ... on RouteInternal {
            entity {
              ... on NodeInterface {
                title
                langcode {
                  id
                }
              }
            }
          }
        }

        ja: route(path: "/test", langcode: "ja") {
          ... on RouteInternal {
            entity {
              ... on NodeInterface {
                title
                langcode {
                  id
                }
              }
            }
          }
        }

        de: route(path: "/test", langcode: "de") {
          ... on RouteInternal {
            entity {
              ... on NodeInterface {
                title
                langcode {
                  id
                }
              }
            }
          }
        }

        frCA: route(path: "/test", langcode: "fr-CA") {
          ... on RouteInternal {
            entity {
              ... on NodeInterface {
                title
                langcode {
                  id
                }
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertEquals('Test', $content['data']['en']['entity']['title']);
    $this->assertEquals('en', $content['data']['en']['entity']['langcode']['id']);

    $this->assertEquals('Test (JA)', $content['data']['ja']['entity']['title']);
    $this->assertEquals('ja', $content['data']['ja']['entity']['langcode']['id']);

    $this->assertEquals('Test (DE)', $content['data']['de']['entity']['title']);
    $this->assertEquals('de', $content['data']['de']['entity']['langcode']['id']);

    $this->assertEquals('Test (fr-CA)', $content['data']['frCA']['entity']['title']);
    $this->assertEquals('fr-CA', $content['data']['frCA']['entity']['langcode']['id']);
  }

  /**
   * Test load entity by route prefix (language).
   */
  public function testRouteLoadWithLangcodePrefix(): void {

    $query = <<<GQL
      query {
        en: route(path: "/test") {
          ... on RouteInternal {
            entity {
              ... on NodeInterface {
                title
                langcode {
                  id
                }
              }
            }
          }
        }

        ja: route(path: "/ja/test") {
          ... on RouteInternal {
            entity {
              ... on NodeInterface {
                title
                langcode {
                  id
                }
              }
            }
          }
        }

        de: route(path: "/de/test") {
          ... on RouteInternal {
            entity {
              ... on NodeInterface {
                title
                langcode {
                  id
                }
              }
            }
          }
        }

        frCA: route(path: "/fr-CA/test") {
          ... on RouteInternal {
            entity {
              ... on NodeInterface {
                title
                langcode {
                  id
                }
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertEquals('Test', $content['data']['en']['entity']['title']);
    $this->assertEquals('en', $content['data']['en']['entity']['langcode']['id']);

    $this->assertEquals('Test (JA)', $content['data']['ja']['entity']['title']);
    $this->assertEquals('ja', $content['data']['ja']['entity']['langcode']['id']);

    $this->assertEquals('Test (DE)', $content['data']['de']['entity']['title']);
    $this->assertEquals('de', $content['data']['de']['entity']['langcode']['id']);

    $this->assertEquals('Test (fr-CA)', $content['data']['frCA']['entity']['title']);
    $this->assertEquals('fr-CA', $content['data']['frCA']['entity']['langcode']['id']);
  }

  /**
   * Test load a menu by name with langcode.
   */
  public function testMenuLoadWithLangcode(): void {

    $link = MenuLinkContent::create([
      'title' => 'Test link',
      'link' => ['uri' => 'entity:node/' . $this->node->id()],
      'menu_name' => 'test',
      'langcode' => 'en',
      'default_langcode' => TRUE,
    ]);

    $link->save();

    $link->addTranslation('ja', [
      'title' => 'Test link (JA)',
    ])->save();

    $link->addTranslation('de', [
      'title' => 'Test link (DE)',
    ])->save();

    // Langcode on menu will change the entire response.
    // Each menu needs to be requested separately.
    $query = <<<GQL
      query {
        menu(name: TEST, langcode: "en") {
          items {
            langcode {
              id
            }
            title
            url
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertEquals('Test link', $content['data']['menu']['items'][0]['title']);
    $this->assertEquals('en', $content['data']['menu']['items'][0]['langcode']['id']);

    // JP.
    $query = <<<GQL
      query {
        menu(name: TEST, langcode: "ja") {
          items {
            langcode {
              id
            }
            title
            url
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertEquals('Test link (JA)', $content['data']['menu']['items'][0]['title']);
    $this->assertEquals('ja', $content['data']['menu']['items'][0]['langcode']['id']);

    // DE.
    $query = <<<GQL
      query {
        menu(name: TEST, langcode: "de") {
          items {
            langcode {
              id
            }
            title
            url
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertEquals('Test link (DE)', $content['data']['menu']['items'][0]['title']);
    $this->assertEquals('de', $content['data']['menu']['items'][0]['langcode']['id']);
  }

  /**
   * Test that a link field in a node is translated as expected.
   */
  public function testNodeLinkFieldUrlTranslated(): void {

    // Create a field with settings to validate.
    FieldStorageConfig::create([
      'field_name' => 'field_internal_link',
      'type' => 'link',
      'entity_type' => 'node',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_internal_link',
      'entity_type' => 'node',
      'bundle' => 'test',
      'settings' => [
        'title' => DRUPAL_OPTIONAL,
        'link_type' => LinkItemInterface::LINK_INTERNAL,
      ],
    ])->save();

    // Create a field with settings to validate.
    FieldStorageConfig::create([
      'field_name' => 'field_external_link',
      'type' => 'link',
      'entity_type' => 'node',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_external_link',
      'entity_type' => 'node',
      'bundle' => 'test',
      'settings' => [
        'title' => DRUPAL_OPTIONAL,
        'link_type' => LinkItemInterface::LINK_EXTERNAL,
      ],
    ])->save();

    // Enable the new fields.
    $this->setFieldConfig('node', 'test', 'field_internal_link', [
      'enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'field_external_link', [
      'enabled' => TRUE,
    ]);

    // Reload the node.
    $this->node = Node::load($this->node->id());
    $deNode = $this->node->getTranslation('de');
    $jaNode = $this->node->getTranslation('ja');

    // Baseline link back to itself.
    $this->node
      ->set('field_internal_link', [
        'uri' => 'internal:/test',
        'title' => 'Link title',
      ])
      ->set('field_external_link', [
        'uri' => 'https://example.com',
        'title' => 'External en link',
      ])
      ->save();

    // Link to the translated URL.
    // Expecting this to stay as /en/test.
    $deNode
      ->set('field_internal_link', [
        'uri' => 'internal:/en/test',
        'title' => 'Link back to EN',
      ])
      ->set('field_external_link', [
        'uri' => 'https://example.de',
        'title' => 'External de link',
      ])
      ->save();

    // Link to the non translated URL.
    // Expecting this to become /ja/test.
    $jaNode
      ->set('field_internal_link', [
        'uri' => 'internal:/test',
        'title' => 'Link title (JA)',
      ])
      ->set('field_external_link', [
        'uri' => 'https://example.ja',
        'title' => 'External ja link',
      ])
      ->save();

    $query = <<<GQL
      query {
        en: node(id: "{$this->node->uuid()}", langcode: "en") {
          ... on NodeTest {
            internalLink {
              title
              url
              internal
            }
            externalLink {
              title
              url
              internal
            }
          }
        }

        de: node(id: "{$this->node->uuid()}", langcode: "de") {
          ... on NodeTest {
            internalLink {
              title
              url
              internal
            }
            externalLink {
              title
              url
              internal
            }
          }
        }

        ja: node(id: "{$this->node->uuid()}", langcode: "ja") {
          ... on NodeTest {
            internalLink {
              title
              url
              internal
            }
            externalLink {
              title
              url
              internal
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    // Check internal links.
    $this->assertEquals('Link title', $content['data']['en']['internalLink']['title']);
    $this->assertEquals('Link back to EN', $content['data']['de']['internalLink']['title']);
    $this->assertEquals('Link title (JA)', $content['data']['ja']['internalLink']['title']);

    $this->assertEquals(base_path() . 'test', $content['data']['en']['internalLink']['url']);
    $this->assertEquals(base_path() . 'en/test', $content['data']['de']['internalLink']['url']);
    $this->assertEquals(base_path() . 'ja/test', $content['data']['ja']['internalLink']['url']);

    $this->assertTrue($content['data']['en']['internalLink']['internal']);
    $this->assertTrue($content['data']['de']['internalLink']['internal']);
    $this->assertTrue($content['data']['ja']['internalLink']['internal']);

    // Check external links.
    $this->assertEquals('External en link', $content['data']['en']['externalLink']['title']);
    $this->assertEquals('External de link', $content['data']['de']['externalLink']['title']);
    $this->assertEquals('External ja link', $content['data']['ja']['externalLink']['title']);

    $this->assertEquals('https://example.com', $content['data']['en']['externalLink']['url']);
    $this->assertEquals('https://example.de', $content['data']['de']['externalLink']['url']);
    $this->assertEquals('https://example.ja', $content['data']['ja']['externalLink']['url']);

    $this->assertFalse($content['data']['en']['externalLink']['internal']);
    $this->assertFalse($content['data']['de']['externalLink']['internal']);
    $this->assertFalse($content['data']['ja']['externalLink']['internal']);
  }

  /**
   * Test entity translations if unpublished.
   */
  public function testUnpublishedEntityTranslations(): void {

    $this->node = Node::load($this->node->id());
    $this->node->getTranslation('de')->setUnpublished()->save();
    $this->node->getTranslation('fr-CA')->setUnpublished()->save();

    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}", langcode: "en") {
          ... on NodeInterface {
            status
          }
          ... on NodeTest {
            translations {
              langcode {
                id
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);
    $translations = $content['data']['node']['translations'];

    $this->assertCount(2, $translations);
    $this->assertEquals('en', $translations[0]['langcode']['id']);
    $this->assertEquals('ja', $translations[1]['langcode']['id']);

    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}", langcode: "de") {
          ... on NodeInterface {
            id
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);
    $this->assertNull($content['data']['node']);
  }

}
