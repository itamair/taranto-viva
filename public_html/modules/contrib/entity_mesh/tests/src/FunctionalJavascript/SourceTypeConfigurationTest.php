<?php

namespace Drupal\Tests\entity_mesh\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the source type configuration in Entity Mesh settings.
 *
 * @group entity_mesh
 */
class SourceTypeConfigurationTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'node',
    'user',
    'field',
    'filter',
    'text',
    'language',
    'entity_mesh',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create content types.
    $this->createContentType(['type' => 'page', 'name' => 'Page']);
    $this->createContentType(['type' => 'article', 'name' => 'Article']);

  }

  /**
   * Tests the source type configuration form behavior.
   */
  public function testSourceTypeConfiguration(): void {
    $admin = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $assert_session = $this->assertSession();

    // Visit the Entity Mesh settings page.
    $this->drupalGet('/admin/config/system/entity-mesh/config');

    $assert_session->waitForField('source_node_enabled');
    $page = $this->getSession()->getPage();

    // Test initial form state.
    $assert_session->checkboxChecked('source_node_enabled');
    $page->uncheckField('source_node_enabled');
    $assert_session->checkboxNotChecked('source_node_enabled');
    $assert_session->elementExists('css', '.entity-mesh-bundles-wrapper')->hasClass('hidden');

    // Enable node entity type and verify bundle options appear.
    $page->checkField('source_node_enabled');
    $assert_session->waitForElementVisible('css', '.entity-mesh-bundles-wrapper:not(.hidden)');

    // Verify bundle checkboxes are present.
    $this->assertSession()->fieldExists('source_node_bundles[article]');
    $this->assertSession()->fieldExists('source_node_bundles[page]');

    // Test saving configuration with some bundles selected.
    $page->checkField('source_node_bundles[article]');
    $page->pressButton('Save configuration');

    // Verify success message.
    $assert_session->pageTextContains('The configuration options have been saved.');

    // Verify configuration was saved correctly.
    $config = $this->config('entity_mesh.settings');
    $source_types = $config->get('source_types');
    $this->assertTrue($source_types['node']['enabled']);
    $this->assertTrue($source_types['node']['bundles']['article']);
    $this->assertFalse(isset($source_types['node']['bundles']['page']));
  }

  /**
   * Tests global configuration settings.
   */
  public function testGlobalSettings(): void {
    $admin = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('/admin/config/system/entity-mesh/config');

    // Test debug mode setting.
    $assert_session->checkboxNotChecked('debug');
    $page->checkField('debug');

    $page->pressButton('Save configuration');
    $assert_session->pageTextContains('The configuration options have been saved.');

    // Verify configuration was saved.
    $config = $this->config('entity_mesh.settings');
    $this->assertTrue($config->get('debug'));
  }

}
