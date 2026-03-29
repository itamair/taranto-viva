<?php

namespace Drupal\Tests\json_field\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\entity_test\Entity\EntityTest;

/**
 * @covers \Drupal\json_field\Plugin\Field\FieldFormatter\PrettyFormatter
 *
 * @group json_field
 */
class PrettyFormatterTest extends KernelTestBase {

  /**
   * Tests that the formatter is rendering an empty array.
   */
  public function testFormatter() {
    $this->createTestField();

    $entity_view_display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ]);
    $entity_view_display->setComponent('test_json_field', ['type' => 'pretty']);
    $entity_view_display->save();

    $entity = EntityTest::create([
      'test_json_field' => json_encode(['foo' => '<b>bar</b>']),
    ]);
    $entity->save();

    $build = $entity_view_display->build($entity);

    $content = (string) $this->container->get('renderer')->renderInIsolation($build);
    self::assertSame('<div class="json-field-pretty"><dl><dt>foo</dt><dd>&lt;b&gt;bar&lt;/b&gt;</dd></dl></div>', $content);
  }

  /**
   * Tests that the formatter properly handles empty JSON values.
   */
  public function testEmptyValues() {
    $this->createTestField();

    $entity_view_display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ]);
    $entity_view_display->setComponent('test_json_field', ['type' => 'pretty']);
    $entity_view_display->save();

    // It should show [empty array] instead of empty <ul></ul>.
    $entity = EntityTest::create([
      'test_json_field' => json_encode([]),
    ]);
    $entity->save();

    $build = $entity_view_display->build($entity);
    $content = (string) $this->container->get('renderer')->renderInIsolation($build);

    self::assertSame('<div class="json-field-pretty">[empty array]</div>', $content);

    // It should show {empty object} instead of empty <dl></dl>.
    $entity = EntityTest::create([
      'test_json_field' => json_encode((object) []),
    ]);
    $entity->save();

    $build = $entity_view_display->build($entity);
    $content = (string) $this->container->get('renderer')->renderInIsolation($build);

    self::assertSame('<div class="json-field-pretty">{empty object}</div>', $content);
  }

}
