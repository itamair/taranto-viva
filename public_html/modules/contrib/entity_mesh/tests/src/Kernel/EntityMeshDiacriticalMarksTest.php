<?php

namespace Drupal\Tests\entity_mesh\Kernel;

use Drupal\node\Entity\Node;

/**
 * Tests Entity Mesh handling of URLs with diacritical marks.
 *
 * @group entity_mesh
 */
class EntityMeshDiacriticalMarksTest extends EntityMeshTestBasic {

  /**
   * Test end-to-end processing of a node with diacritical marks in links.
   */
  public function testEndToEndProcessingWithDiacriticalMarks() {
    // Ensure the body field is properly configured for the view mode.
    $this->container->get('entity_display.repository')
      ->getViewDisplay('node', 'page', 'full')
      ->setComponent('body', [
        'type' => 'text_default',
        'label' => 'hidden',
      ])
      ->save();

    $url_to_encode = '/+ñáéíóú';
    $url_encoded = rawurlencode($url_to_encode);
    // Create a node with content containing links with diacritical marks.
    $node_content = '<p>Links with diacritical marks:</p><a href="/múltiple-áccents-+ñáéíóú">Diacritical marks</a>';
    $node_content .= "<p>Another link: <a href=\"$url_encoded\">Url encoded</a></p>";

    $node = Node::create([
      'type' => 'page',
      'title' => 'Test Node with Diacritical Marks',
      'body' => [
        'value' => $node_content,
        'format' => 'basic_html',
      ],
    ]);
    $node->save();

    // Process the node through Entity Mesh.
    $entity_render = $this->container->get('entity_mesh.entity_render');
    $entity_render->processEntity($node);

    // Check that URLs were stored correctly in the database.
    $database = $this->container->get('database');
    $query = $database->select('entity_mesh', 'em')
      ->fields('em', ['target_href', 'source_entity_id', 'source_entity_type', 'target_path', 'subcategory'])
      ->condition('source_entity_id', $node->id())
      ->execute();

    $records = [];
    $stored_hrefs = [];
    while ($record = $query->fetchAssoc()) {
      $records[] = $record;
      if (!empty($record['target_href'])) {
        $stored_hrefs[] = $record['target_href'];
      }
    }

    // Verify diacritical marks are preserved in storage.
    $this->assertNotEmpty($stored_hrefs, 'Entity Mesh should have stored URLs');
    $this->assertContains('/múltiple-áccents-+ñáéíóú', $stored_hrefs,
      'URL with diacritical marks should be stored correctly');
    $this->assertContains($url_to_encode, $stored_hrefs,
      'Encoded URL marks should be stored correctly');

    // Verify the URL was not corrupted during storage.
    foreach ($stored_hrefs as $href) {
      $this->assertStringNotContainsString('Ã', $href, 'URL should not contain corrupted UTF-8 sequences');
      $this->assertStringNotContainsString('Ì', $href, 'URL should not contain corrupted UTF-8 sequences');
    }
  }

}
