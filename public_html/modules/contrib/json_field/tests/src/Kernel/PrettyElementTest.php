<?php

namespace Drupal\Tests\json_field\Kernel;

use Drupal\KernelTests\KernelTestBase as DrupalKernelTestBase;

/**
 * @covers \Drupal\json_field\Element\JsonPretty
 *
 * @group json_field
 */
class PrettyElementTest extends DrupalKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'json_field'];

  /**
   * Test callback.
   */
  public function testElement(): void {
    $json = (object) [
      'foo' => 123,
      'bar' => [
        'alpha',
        'beta',
        'gamma',
      ],
      'baz' => NULL,
    ];

    $elements = [
      '#type' => 'json_pretty',
      '#json' => $json,
    ];

    $renderer = $this->container->get('renderer');

    $expected_output = <<< 'HTML'
      <div class="json-field-pretty">
        <dl>
          <dt>foo</dt>
          <dd>123</dd>
          <dt>bar</dt>
            <dd>
              <ul>
                <li>alpha</li>
                <li>beta</li>
                <li>gamma</li>
              </ul>
            </dd>
          <dt>baz</dt>
          <dd>null</dd>
        </dl>
      </div>
    HTML;

    $actual_output = (string) $renderer->renderInIsolation($elements);
    self::assertSame(str_replace(["\n", '  '], '', $expected_output), $actual_output);
  }

  /**
   * Tests that empty JSON values are properly rendered.
   */
  public function testEmptyValues(): void {
    $elements_array = [
      '#type' => 'json_pretty',
      '#json' => [],
    ];
    $renderer = $this->container->get('renderer');
    $actual_output = (string) $renderer->renderInIsolation($elements_array);

    $expected_output = '<div class="json-field-pretty">[empty array]</div>';
    self::assertSame($expected_output, $actual_output);

    $elements_object = [
      '#type' => 'json_pretty',
      '#json' => (object) [],
    ];

    $actual_output = (string) $renderer->renderInIsolation($elements_object);

    $expected_output = '<div class="json-field-pretty">{empty object}</div>';
    self::assertSame($expected_output, $actual_output);
  }

}
