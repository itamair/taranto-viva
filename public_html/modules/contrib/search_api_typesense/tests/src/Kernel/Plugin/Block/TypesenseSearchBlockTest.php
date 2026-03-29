<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_typesense\Kernel\Plugin\Block;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Server;
use Drupal\search_api_typesense\Plugin\Block\TypesenseSearchBlock;

/**
 * @coversDefaultClass \Drupal\search_api_typesense\Plugin\Block\TypesenseSearchBlock
 *
 * @group search_api_typesense
 */
class TypesenseSearchBlockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'search_api',
    'search_api_typesense',
  ];

  /**
   * The block to test.
   *
   * @var \Drupal\search_api_typesense\Plugin\Block\TypesenseSearchBlock
   */
  protected TypesenseSearchBlock $block;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig([
      'search_api',
    ]);

    $server = Server::create([
      'id' => 'typesense',
      'name' => 'Typesense',
      'backend' => 'search_api_typesense',
    ]);
    $server->save();

    $block = $this->container->get('plugin.manager.block')->createInstance('search_api_typesense_search_block');
    \assert($block instanceof TypesenseSearchBlock);
    $this->block = $block;
  }

  /**
   * @covers ::build
   */
  public function testBuild(): void {
    $this->block->setConfiguration([
      'server' => 'typesense',
      'collections' => [],
    ]);
    $build = $this->block->build();
    self::assertEmpty($build, 'Block build should return empty array when the Typesense server is not available.');
  }

  /**
   * @covers ::blockForm
   */
  public function testBlockForm(): void {
    $form = [];
    $form_state = new FormState();

    $config_form = $this->block->buildConfigurationForm($form, $form_state);
    self::assertIsArray($config_form);
    self::assertArrayHasKey('typesense', $config_form);
    self::assertArrayHasKey('server', $config_form['typesense']);
    self::assertArrayHasKey('collections_wrapper', $config_form['typesense']);
    self::assertArrayHasKey('collections', $config_form['typesense']['collections_wrapper']);
    self::assertArrayHasKey('federated_search', $config_form['typesense']['collections_wrapper']);
    self::assertArrayHasKey('hits_per_page', $config_form['typesense']);
  }

}
