<?php

namespace Drupal\Tests\entity_mesh\Kernel;

use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the Entity Mesh link auditing without content_translation support.
 *
 * @group entity_mesh
 */
class EntityMeshEntityRenderTest extends EntityMeshTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container->get('router.builder')->rebuild();
    $entity_mesh = $this->config('entity_mesh.settings')->getRawData();
    $entity_mesh['target_types']['internal']['taxonomy_term']['enabled'] = TRUE;
    $entity_mesh['target_types']['internal']['media']['enabled'] = TRUE;

    $this->config('entity_mesh.settings')->setData($entity_mesh)->save();
    $this->createExampleNodes();
  }

  /**
   * Creates a node with given content.
   */
  protected function createExampleNodes() {
    // Create HTML content containing various link scenarios.
    $html_node_1 = '
      <p>External link https schema: <a href="https://example.com">Example</a></p>
      <p>External link http schema: <a href="http://www.example.com">Example</a></p>
      <p>Internal broken link: <a href="/non-existent">Broken</a></p>
      <p>Internal broken link node: <a href="/node/123">Broken Internal Node</a></p>
      <p>Iframe content: <iframe src="https://example.com/iframe"></iframe></p>
      <p>Mailto link: <a href="mailto:hola@metadrop.net">Mailto link</a></p>
      <p>Tel link: <a href="tel:+34654654654">Tel link</a></p>
    ';

    $node_1 = Node::create([
      'type' => 'page',
      'title' => 'Test Node 1',
      'nid' => 1,
      'body' => [
        'value' => $html_node_1,
        'format' => 'basic_html',
      ],
    ]);

    $node_1->save();

    $html_node_2 = '
      <p>Internal valid link: <a href="/node/1">Internal Node</a></p>
    ';

    $node_2 = Node::create([
      'type' => 'page',
      'title' => 'Test Node 2',
      'nid' => 2,
      'body' => [
        'value' => $html_node_2,
        'format' => 'basic_html',
      ],
    ]);
    $node_2->save();

    // Create node to check medias.
    $media = $this->generateMedia('test.pdf', $this->fileMediaType, 1);
    $media->save();

    // Get the url from uri.
    $url = EntityMeshEntityRenderTest::getUrlFromMedia('1');

    $node_3 = Node::create([
      'type' => 'page',
      'title' => 'Test Node 3',
      'nid' => 3,
      'body' => [
        'value' => "<p>This is a link to a document <a href=\"$url\">Link to a document file</a></p>",
        'format' => 'basic_html',
      ],
    ]);
    $node_3->save();

    // Create node with absolute internal URLs.
    $domain = $this->container->get('request_stack')->getCurrentRequest()->getHost();
    $html_node_4 = '
      <p>Internal absolute link: <a href="https://' . $domain . '/node/1">Internal Absolute Node</a></p>
    ';

    $node_4 = Node::create([
      'type' => 'page',
      'title' => 'Test Node 4',
      'nid' => 4,
      'body' => [
        'value' => $html_node_4,
        'format' => 'basic_html',
      ],
    ]);
    $node_4->save();

    // Create node with protocol-relative URLs (scheme-less URLs).
    $html_node_5 = '
      <p>External protocol-relative link: <a href="//www.example.com/foo/bar">External Scheme-less</a></p>
      <p>Internal protocol-relative link: <a href="//' . $domain . '/node/1">Internal Scheme-less</a></p>
      <p>Protocol-relative iframe: <iframe src="//www.example.com/iframe"></iframe></p>
    ';

    $node_5 = Node::create([
      'type' => 'page',
      'title' => 'Test Node 5',
      'nid' => 5,
      'body' => [
        'value' => $html_node_5,
        'format' => 'basic_html',
      ],
    ]);
    $node_5->save();
  }

  /**
   * Tests different types of links.
   *
   * @dataProvider linkCasesProvider
   */
  public function testLinks(
    $source_entity_id,
    $source_entity_langcode,
    $target_href,
    $expected_target_link_type,
    $expected_target_subcategory = NULL,
    $expected_target_title = NULL,
    $expected_target_scheme = NULL,
    $expected_target_host = NULL,
    $expected_target_entity_type = NULL,
    $expected_target_entity_bundle = NULL,
    $expected_target_entity_id = NULL,
    $expected_target_entity_langcode = NULL,
    $expected_source_entity_type = NULL,
    $expected_source_entity_bundle = NULL,
    $expected_source_entity_langcode = NULL,
    $expected_source_title = NULL,
    $source_should_exist = TRUE,
    $custom_config = [],
  ) {

    if ($custom_config) {
      foreach ($custom_config as $name => $new_config) {
        $config = $this->config($name);
        foreach ($new_config as $k => $v) {
          $config->set($k, $v);
        }
        $config->save();
      }
    }

    // Resave after update custom configuration.
    $nodes = $this->container->get('entity_type.manager')->getStorage('node')->loadMultiple();
    foreach ($nodes as $node) {
      $node->save();
    }

    $target_href = $target_href ? str_replace('[host_domain]', $this->container->get('request_stack')->getCurrentRequest()->getHost(), $target_href) : $target_href;
    $expected_target_title = $expected_target_title ? str_replace('[host_domain]', $this->container->get('request_stack')->getCurrentRequest()->getHost(), $expected_target_title) : $expected_target_title;
    $expected_target_host = $expected_target_host ? str_replace('[host_domain]', $this->container->get('request_stack')->getCurrentRequest()->getHost(), $expected_target_host) : $expected_target_host;

    parent::testLinks(
      $source_entity_id,
      $source_entity_langcode,
      $target_href,
      $expected_target_link_type,
      $expected_target_subcategory,
      $expected_target_title,
      $expected_target_scheme,
      $expected_target_host,
      $expected_target_entity_type,
      $expected_target_entity_bundle,
      $expected_target_entity_id,
      $expected_target_entity_langcode,
      $expected_source_entity_type,
      $expected_source_entity_bundle,
      $expected_source_entity_langcode,
      $expected_source_title,
      $source_should_exist
    );
  }

  /**
   * Tests setDataTargetFromRoute with subdirectory installations.
   *
   * @dataProvider subdirectoryLinkCasesProvider
   */
  public function testSubdirectoryInstallations($base_path, $source_entity_id, $target_href, $expected_target_entity_type, $expected_target_entity_id, $expected_subcategory) {
    // Create a proper request with session to avoid deprecation warnings.
    $request = Request::create('/');
    if ($base_path) {
      $request->server->set('SCRIPT_NAME', $base_path . '/index.php');
      $request->server->set('SCRIPT_FILENAME', '/var/www/html' . $base_path . '/index.php');
    }

    // Set a session to avoid deprecation warnings.
    $session = new Session(new MockArraySessionStorage());
    $request->setSession($session);

    $request_stack = $this->container->get('request_stack');
    $request_stack->push($request);

    // Create the entity render service.
    $entity_render = $this->container->get('entity_mesh.entity_render');

    // Get the source entity.
    $source_entity = $this->container->get('entity_type.manager')->getStorage('node')->load($source_entity_id);

    // Create source from entity.
    $source = $entity_render->createSourceFromEntity($source_entity);

    // Find the target with the specified href.
    $found_target = NULL;
    foreach ($source->getTargets() as $target) {
      if ($target->getHref() === $target_href) {
        $found_target = $target;
        break;
      }
    }

    $this->assertNotNull($found_target, "Target with href '$target_href' should exist");
    $this->assertEquals($expected_target_entity_type, $found_target->getEntityType());
    $this->assertEquals($expected_target_entity_id, $found_target->getEntityId());
    $this->assertEquals($expected_subcategory, $found_target->getSubcategory());
  }

  /**
   * Provides test cases for subdirectory installations.
   */
  public static function subdirectoryLinkCasesProvider() {
    return [
      'Subdirectory /drupal_site with valid node link' => [
        'base_path' => '/drupal_site',
        'source_entity_id' => 2,
        'target_href' => '/node/1',
        'expected_target_entity_type' => 'node',
        'expected_target_entity_id' => '1',
        'expected_subcategory' => 'link',
      ],
      'Subdirectory /sites/drupal with valid node link' => [
        'base_path' => '/sites/drupal',
        'source_entity_id' => 2,
        'target_href' => '/node/1',
        'expected_target_entity_type' => 'node',
        'expected_target_entity_id' => '1',
        'expected_subcategory' => 'link',
      ],
      'Subdirectory /drupal_site with broken link' => [
        'base_path' => '/drupal_site',
        'source_entity_id' => 1,
        'target_href' => '/non-existent',
        'expected_target_entity_type' => NULL,
        'expected_target_entity_id' => NULL,
        'expected_subcategory' => 'broken-link',
      ],
      'Root installation with normal link' => [
        'base_path' => '',
        'source_entity_id' => 2,
        'target_href' => '/node/1',
        'expected_target_entity_type' => 'node',
        'expected_target_entity_id' => '1',
        'expected_subcategory' => 'link',
      ],
    ];
  }

  /**
   * Provides test cases for different types of links.
   */
  public static function linkCasesProvider() {
    $defaults = self::$providerDefaults;

    return [
      'External link HTTPS Node 1' => array_merge($defaults, [
        'source_entity_id' => 1,
        'target_href' => 'https://example.com',
        'expected_target_link_type' => 'external',
        'expected_target_subcategory' => 'link',
        'expected_target_title' => 'https://example.com (external)',
        'expected_target_scheme' => 'https',
        'expected_target_host' => 'example.com',
        'expected_source_entity_type' => 'node',
        'expected_source_entity_bundle' => 'page',
        'expected_source_title' => 'Test Node 1 (node - 1)',
      ]),
      'External link HTTP Node 1' => array_merge($defaults, [
        'source_entity_id' => 1,
        'target_href' => 'http://www.example.com',
        'expected_target_link_type' => 'external',
        'expected_target_subcategory' => 'link',
        'expected_target_title' => 'http://www.example.com (external)',
        'expected_target_scheme' => 'http',
        'expected_target_host' => 'www.example.com',
      ]),
      'Broken link alias Node 1' => array_merge($defaults, [
        'source_entity_id' => 1,
        'target_href' => '/non-existent',
        'expected_target_link_type' => 'internal',
        'expected_target_subcategory' => 'broken-link',
        'expected_target_title' => '/non-existent ()',
      ]),
      'Broken link node Node 1' => array_merge($defaults, [
        'source_entity_id' => 1,
        'target_href' => '/node/123',
        'expected_target_link_type' => 'internal',
        'expected_target_subcategory' => 'broken-link',
        'expected_target_title' => '/node/123 ()',
      ]),
      'Iframe link Node 1' => array_merge($defaults, [
        'source_entity_id' => 1,
        'target_href' => 'https://example.com/iframe',
        'expected_target_link_type' => 'external',
        'expected_target_subcategory' => 'iframe',
        'expected_target_title' => 'https://example.com/iframe (external)',
        'expected_target_scheme' => 'https',
        'expected_target_host' => 'example.com',
      ]),
      'Mailto link Node 1' => array_merge($defaults, [
        'source_entity_id' => 1,
        'target_href' => 'mailto:hola@metadrop.net',
        'expected_target_link_type' => 'external',
        'expected_target_subcategory' => 'link',
        'expected_target_scheme' => 'mailto',
      ]),
      'Tel link Node 1' => array_merge($defaults, [
        'source_entity_id' => 1,
        'target_href' => 'tel:+34654654654',
        'expected_target_link_type' => 'external',
        'expected_target_subcategory' => 'link',
        'expected_target_scheme' => 'tel',
      ]),
      'Internal valid link Node 2' => array_merge($defaults, [
        'source_entity_id' => 2,
        'target_href' => '/node/1',
        'expected_target_link_type' => 'internal',
        'expected_target_subcategory' => 'link',
        'expected_target_title' => 'Test Node 1 (node - 1)',
        'expected_target_entity_type' => 'node',
        'expected_target_entity_bundle' => 'page',
        'expected_target_entity_id' => 1,
      ]),
      'Internal valid link Node 3' => array_merge($defaults, [
        'source_entity_id' => 3,
        'target_href' => "files/test.pdf",
        'expected_target_link_type' => 'internal',
        'expected_target_subcategory' => 'link',
        'expected_target_entity_type' => 'media',
        'expected_target_entity_bundle' => 'document',
        'expected_target_entity_id' => 1,
      ]),
      'Absolute internal link Node 4 (self_domain_internal Enabled)' => array_merge($defaults, [
        'source_entity_id' => 4,
        'target_href' => "https://[host_domain]/node/1",
        'expected_target_link_type' => 'internal',
        'expected_target_subcategory' => 'link',
        'expected_target_title' => 'Test Node 1 (node - 1)',
        'expected_target_entity_type' => 'node',
        'expected_target_entity_bundle' => 'page',
        'expected_target_entity_id' => 1,
        'expected_target_scheme' => 'https',
        'expected_target_host' => '[host_domain]',
        'custom_config' => [
          'entity_mesh.settings' => [
            'self_domain_internal' => TRUE,
          ],
        ],
      ]),
      'Absolute internal link Node 4 (self_domain_internal Disabled)' => array_merge($defaults, [
        'source_entity_id' => 4,
        'target_href' => 'https://[host_domain]/node/1',
        'expected_target_link_type' => 'external',
        'expected_target_subcategory' => 'link',
        'expected_target_title' => 'https://[host_domain]/node/1 (external)',
        'expected_target_entity_type' => NULL,
        'expected_target_entity_bundle' => NULL,
        'expected_target_entity_id' => NULL,
        'expected_target_scheme' => 'https',
        'expected_target_host' => '[host_domain]',
        'custom_config' => [
          'entity_mesh.settings' => [
            'self_domain_internal' => FALSE,
          ],
        ],
      ]),
      'Protocol-relative external link Node 5' => array_merge($defaults, [
        'source_entity_id' => 5,
        'target_href' => '//www.example.com/foo/bar',
        'expected_target_link_type' => 'external',
        'expected_target_subcategory' => 'link',
        'expected_target_title' => '//www.example.com/foo/bar (external)',
        'expected_target_scheme' => '',
        'expected_target_host' => 'www.example.com',
      ]),
      'Protocol-relative internal link Node 5 (self_domain_internal Enabled)' => array_merge($defaults, [
        'source_entity_id' => 5,
        'target_href' => '//[host_domain]/node/1',
        'expected_target_link_type' => 'internal',
        'expected_target_subcategory' => 'link',
        'expected_target_title' => 'Test Node 1 (node - 1)',
        'expected_target_entity_type' => 'node',
        'expected_target_entity_bundle' => 'page',
        'expected_target_entity_id' => 1,
        'expected_target_scheme' => '',
        'expected_target_host' => '[host_domain]',
        'custom_config' => [
          'entity_mesh.settings' => [
            'self_domain_internal' => TRUE,
          ],
        ],
      ]),
      'Protocol-relative internal link Node 5 (self_domain_internal Disabled)' => array_merge($defaults, [
        'source_entity_id' => 5,
        'target_href' => '//[host_domain]/node/1',
        'expected_target_link_type' => 'external',
        'expected_target_subcategory' => 'link',
        'expected_target_title' => '//[host_domain]/node/1 (external)',
        'expected_target_entity_type' => NULL,
        'expected_target_entity_bundle' => NULL,
        'expected_target_entity_id' => NULL,
        'expected_target_scheme' => '',
        'expected_target_host' => '[host_domain]',
        'custom_config' => [
          'entity_mesh.settings' => [
            'self_domain_internal' => FALSE,
          ],
        ],
      ]),
      'Protocol-relative iframe Node 5' => array_merge($defaults, [
        'source_entity_id' => 5,
        'target_href' => '//www.example.com/iframe',
        'expected_target_link_type' => 'external',
        'expected_target_subcategory' => 'iframe',
        'expected_target_title' => '//www.example.com/iframe (external)',
        'expected_target_scheme' => '',
        'expected_target_host' => 'www.example.com',
      ]),
    ];
  }

}
