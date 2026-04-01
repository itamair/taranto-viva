<?php

namespace Drupal\Tests\entity_mesh\Kernel;

use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;

/**
 * Tests the Entity Mesh link auditing with multilingual support.
 *
 * @group entity_mesh
 */
class EntityMeshEntityRenderMultilingualTest extends EntityMeshTestBase {

  /**
   * Modules to enable.
   *
   * @var array<string>
   */
  protected static $modules = [
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install the necessary schemas.
    $this->installConfig(['content_translation']);

    // Enable the French language.
    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('it')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();
    ConfigurableLanguage::createFromLangcode('de')->save();

    $config = $this->config('language.negotiation');
    $config->set('url.prefixes', ['en' => 'en', 'fr' => 'fr', 'it' => 'it', 'es' => 'es', 'de' => 'de'])
      ->save();

    // Enable content translation for nodes.
    $this->container->get('content_translation.manager')->setEnabled('node', 'page', TRUE);

    ContentLanguageSettings::loadByEntityTypeBundle('node', 'page')->setLanguageAlterable(TRUE)->save();

    $this->container->get('kernel')->rebuildContainer();
    $this->container->get('router.builder')->rebuild();
    $this->createExampleAlias();
    $this->createExampleNodes();
  }

  /**
   * Creates a node with given content.
   */
  protected function createExampleNodes() {

    // Create a node entity in English.
    $node_fr = Node::create([
      'type' => 'page',
      'title' => 'Test Node FR',
      'nid' => 1,
      'body' => [
        'value' => '<p>Test node</p>',
        'format' => 'basic_html',
      ],
      'langcode' => 'fr',
    ]);

    // Translation it unpublished.
    $node_it = $node_fr->addTranslation('it', [
      'title' => 'Test Node IT',
      'body' => [
        'value' => '<p>Translation</p>',
        'format' => 'basic_html',
      ],
    ]);
    $node_it->setUnpublished();

    // Translation de published.
    $node_fr->addTranslation('de', [
      'title' => 'Test Node DE',
      'body' => [
        'value' => '<p>Translation</p>',
        'format' => 'basic_html',
      ],
    ]);

    $node_fr->save();

    $html_node_en = '
      <p>Internal valid link: <a href="/fr/node/1">Internal Node</a></p>
      <p>Internal access denied link invalid translation: <a href="/es/node/1">Internal access denied link invalid translation/a></p>
      <p>Internal access denied link not published translation: <a href="/it/node/1">Internal access denied link not published translation</a></p>
      <p>Internal valid link published translation: <a href="/de/node/1">Internal valid link published translation</a></p>
      <p>Internal access denied alias link not published translation: <a href="/it/node-1-it-alias">Internal access denied alias link not published translation</a></p>
      <p>Internal alias link published translation: <a href="/de/node-1-de-alias">Internal valid alias link published translation</a></p>
      <p>Internal alias link without prefix (fallback to EN): <a href="/node-1-no-prefix">Alias without language prefix</a></p>
    ';

    // Create a node entity in French.
    $node_en = Node::create([
      'type' => 'page',
      'title' => 'Test Node EN',
      'nid' => 2,
      'body' => [
        'value' => $html_node_en,
        'format' => 'basic_html',
      ],
    ]);

    $html_node_de = '
      <p>Internal alias link published translation: <a href="/de/node-1-de-alias">Internal valid alias link published translation</a></p>
      <p>Internal alias link without prefix (fallback to DE): <a href="/node-1-de-no-prefix">Alias without language prefix for German</a></p>
    ';

    // Translation de published.
    $node_en->addTranslation('de', [
      'title' => 'Test Node 2 DE',
      'body' => [
        'value' => $html_node_de,
        'format' => 'basic_html',
      ],
    ]);

    $html_node_it = '
      <p>Internal alias link published translation: <a href="/de/node-1-de-alias">Internal valid alias link published translation</a></p>
    ';

    // Translation it unpublished.
    $node_en->addTranslation('it', [
      'title' => 'Test Node 2 IT Unpublished',
      'status' => 0,
      'body' => [
        'value' => $html_node_it,
        'format' => 'basic_html',
      ],
    ]);

    $node_en->save();
  }

  /**
   * Create alias.
   */
  protected function createExampleAlias() {
    $cases = [
      ['lang' => 'it', 'path' => '/node/1', 'alias' => '/node-1-it-alias'],
      ['lang' => 'de', 'path' => '/node/1', 'alias' => '/node-1-de-alias'],
      // Aliases without language prefixes for fallback langcode testing.
      ['lang' => 'en', 'path' => '/node/1', 'alias' => '/node-1-no-prefix'],
      ['lang' => 'de', 'path' => '/node/1', 'alias' => '/node-1-de-no-prefix'],
      ['lang' => 'fr', 'path' => '/node/1', 'alias' => '/node-1-fr-no-prefix'],
    ];
    foreach ($cases as $case) {
      /** @var \Drupal\path_alias\PathAliasInterface $path_alias */
      $path_alias = \Drupal::entityTypeManager()->getStorage('path_alias')->create([
        'path' => $case['path'],
        'alias' => $case['alias'],
        'langcode' => $case['lang'],
      ]);
      $path_alias->save();
    }
  }

  /**
   * Provides test cases for different types of links.
   */
  public static function linkCasesProvider() {
    $defaults = self::$providerDefaults;

    return [

      'Internal valid link EN' => array_merge($defaults, [
        'source_entity_id'                => 2,
        'target_href'                     => '/fr/node/1',
        'expected_target_link_type'       => 'internal',
        'expected_target_subcategory'     => 'link',
        'expected_target_title'           => 'Test Node FR (node - 1)',
        'expected_target_entity_type'     => 'node',
        'expected_target_entity_bundle'   => 'page',
        'expected_target_entity_id'       => 1,
        'expected_target_entity_langcode' => 'fr',
      ]),

      'Internal fallback link invalid translation EN' => array_merge($defaults, [
        'source_entity_id'                => 2,
        'target_href'                     => '/es/node/1',
        'expected_target_link_type'       => 'internal',
        'expected_target_subcategory'     => 'link',
        'expected_target_title'           => 'Test Node FR (node - 1)',
        'expected_target_entity_type'     => 'node',
        'expected_target_entity_bundle'   => 'page',
        'expected_target_entity_id'       => 1,
        'expected_target_entity_langcode' => 'es',
      ]),

      'Internal access denied link not published translation EN' => array_merge($defaults, [
        'source_entity_id'                => 2,
        'target_href'                     => '/it/node/1',
        'expected_target_link_type'       => 'internal',
        'expected_target_subcategory'     => 'access-denied-link',
        'expected_target_title'           => 'Test Node IT (node - 1)',
        'expected_target_entity_type'     => 'node',
        'expected_target_entity_bundle'   => 'page',
        'expected_target_entity_id'       => 1,
        'expected_target_entity_langcode' => 'it',
      ]),

      'Internal valid link translation EN' => array_merge($defaults, [
        'source_entity_id'                => 2,
        'target_href'                     => '/de/node/1',
        'expected_target_link_type'       => 'internal',
        'expected_target_subcategory'     => 'link',
        'expected_target_title'           => 'Test Node DE (node - 1)',
        'expected_target_entity_type'     => 'node',
        'expected_target_entity_bundle'   => 'page',
        'expected_target_entity_id'       => 1,
        'expected_target_entity_langcode' => 'de',
      ]),

      'Internal valid alias link translation DE' => array_merge($defaults, [
        'source_entity_id'                => 2,
        'target_href'                     => '/de/node-1-de-alias',
        'expected_target_link_type'       => 'internal',
        'expected_target_subcategory'     => 'link',
        'expected_target_title'           => 'Test Node DE (node - 1)',
        'expected_target_entity_type'     => 'node',
        'expected_target_entity_bundle'   => 'page',
        'expected_target_entity_id'       => 1,
        'expected_target_entity_langcode' => 'de',
      ]),

      'Internal access denied alias link not published translation IT' => array_merge($defaults, [
        'source_entity_id'                => 2,
        'target_href'                     => '/it/node-1-it-alias',
        'expected_target_link_type'       => 'internal',
        'expected_target_subcategory'     => 'access-denied-link',
        'expected_target_title'           => 'Test Node IT (node - 1)',
        'expected_target_entity_type'     => 'node',
        'expected_target_entity_bundle'   => 'page',
        'expected_target_entity_id'       => 1,
        'expected_target_entity_langcode' => 'it',
      ]),

      'Check source title DE => DE' => array_merge($defaults, [
        'source_entity_id'                => 2,
        'source_entity_langcode'          => 'de',
        'expected_source_title'           => 'Test Node 2 DE (node - 2)',
        'target_href'                     => '/de/node-1-de-alias',
        'expected_target_link_type'       => 'internal',
        'expected_target_subcategory'     => 'link',
        'expected_target_title'           => 'Test Node DE (node - 1)',
        'expected_target_entity_type'     => 'node',
        'expected_target_entity_bundle'   => 'page',
        'expected_target_entity_id'       => 1,
        'expected_target_entity_langcode' => 'de',
      ]),

      'Check source not exists unpublished IT' => array_merge($defaults, [
        'source_entity_id'                => 2,
        'source_entity_langcode'          => 'it',
        'source_should_exist'             => FALSE,
      ]),

      // Node 1 has no English translation (only fr, it, de), so the alias
      // resolves via fallback langcode to the default translation, which is
      // accessible and returned as a valid link.
      'Fallback langcode alias without prefix EN' => array_merge($defaults, [
        'source_entity_id'                => 2,
        'source_entity_langcode'          => 'en',
        'target_href'                     => '/node-1-no-prefix',
        'expected_target_link_type'       => 'internal',
        'expected_target_subcategory'     => 'link',
        'expected_target_title'           => 'Test Node FR (node - 1)',
        'expected_target_entity_type'     => 'node',
        'expected_target_entity_bundle'   => 'page',
        'expected_target_entity_id'       => 1,
        'expected_target_entity_langcode' => 'en',
      ]),

      'Fallback langcode alias without prefix DE' => array_merge($defaults, [
        'source_entity_id'                => 2,
        'source_entity_langcode'          => 'de',
        'target_href'                     => '/node-1-de-no-prefix',
        'expected_target_link_type'       => 'internal',
        'expected_target_subcategory'     => 'link',
        'expected_target_title'           => 'Test Node DE (node - 1)',
        'expected_target_entity_type'     => 'node',
        'expected_target_entity_bundle'   => 'page',
        'expected_target_entity_id'       => 1,
        'expected_target_entity_langcode' => 'de',
      ]),

    ];
  }

}
