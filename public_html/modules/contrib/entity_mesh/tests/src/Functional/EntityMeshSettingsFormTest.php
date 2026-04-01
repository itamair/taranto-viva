<?php

namespace Drupal\Tests\entity_mesh\Functional;

use Drupal\entity_mesh\DummyAccount;
use Drupal\entity_mesh\Repository;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Entity Mesh settings form.
 *
 * @group entity_mesh
 */
class EntityMeshSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_mesh',
    'node',
    'field',
    'filter',
    'text',
    'user',
    'system',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to administer entity mesh.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a user with permissions to access the settings form.
    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'administer entity_mesh configuration',
    ], NULL, TRUE);
    $this->drupalLogin($this->adminUser);
    $this->drupalCreateRole(['access content'], 'redactor', 'Redactor');
    $this->drupalCreateRole(['access content'], 'administrator', 'Administrator');

    // Ensure default config is set for functional tests.
    $config = $this->config('entity_mesh.settings');
    $config->set('processing_mode', 'asynchronous');
    $config->save();
  }

  /**
   * Tests the Entity Mesh settings form.
   */
  public function testSettingsForm() {
    $this->drupalGet('admin/config/system/entity-mesh/config');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Entity Mesh settings');

    // Check form elements exist.
    $this->assertSession()->fieldExists('processing_mode');
    $this->assertSession()->fieldExists('synchronous_limit');

    // Check radio button options.
    $this->assertSession()->fieldValueEquals('processing_mode', 'asynchronous');
    $page = $this->getSession()->getPage();
    // Radio buttons use IDs like edit-processing-mode-asynchronous.
    $asynchronous_radio = $page->findById('edit-processing-mode-asynchronous');
    $synchronous_radio = $page->findById('edit-processing-mode-synchronous');
    $this->assertNotNull($asynchronous_radio, 'Asynchronous radio button should exist.');
    $this->assertNotNull($synchronous_radio, 'Synchronous radio button should exist.');

    // Check that synchronous_limit field exists.
    $synchronous_limit = $page->findField('synchronous_limit');
    $this->assertNotNull($synchronous_limit, 'Synchronous limit field should exist.');

    // Test form submission with synchronous mode.
    $edit = [
      'processing_mode' => 'synchronous',
      'synchronous_limit' => 20,
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify the configuration was saved correctly.
    $config = $this->config('entity_mesh.settings');
    $this->assertEquals('synchronous', $config->get('processing_mode'));
    $this->assertEquals(20, $config->get('synchronous_limit'));

    // Test form submission with asynchronous mode.
    $this->drupalGet('admin/config/system/entity-mesh/config');
    $edit = [
      'processing_mode' => 'asynchronous',
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify the configuration was saved correctly.
    $config = $this->config('entity_mesh.settings');
    $this->assertEquals('asynchronous', $config->get('processing_mode'));
  }

  /**
   * Tests the dynamic visibility of descriptions based on processing mode.
   */
  public function testDynamicDescriptions() {
    $this->drupalGet('admin/config/system/entity-mesh/config');
    $page = $this->getSession()->getPage();

    // Check that descriptions exist in the form (they should always be in HTML)
    $this->assertSession()->pageTextContains('In asynchronous mode');
    $this->assertSession()->pageTextContains('In synchronous mode');

    // Change to synchronous mode.
    $page->selectFieldOption('processing_mode', 'synchronous');

    // Check that synchronous_limit field becomes visible.
    $synchronous_limit = $page->findField('synchronous_limit');
    $this->assertNotNull($synchronous_limit, 'Synchronous limit field should exist.');
  }

  /**
   * Tests validation of synchronous_limit field.
   */
  public function testSynchronousLimitValidation() {
    $this->drupalGet('admin/config/system/entity-mesh/config');

    // Select synchronous mode.
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('processing_mode', 'synchronous');

    // Valid value should work.
    $edit = [
      'processing_mode' => 'synchronous',
      'synchronous_limit' => 50,
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify the value was saved.
    $config = $this->config('entity_mesh.settings');
    $this->assertEquals(50, $config->get('synchronous_limit'));
  }

  /**
   * Tests the analyzer_account configuration field.
   */
  public function testAnalyzerAccountConfiguration() {
    $user = $this->createUser([], 'test_user');

    $this->drupalGet('admin/config/system/entity-mesh/config');
    $this->assertSession()->statusCodeEquals(200);

    // Check that analyzer_account fields exist.
    $this->assertSession()->fieldExists('account_type');
    $this->assertSession()->pageTextContains('Analyzer account');
    $this->assertSession()->pageTextContains('Account type');

    // Test anonymous account type selection.
    $edit = [
      'processing_mode' => 'asynchronous',
      'account_type' => 'anonymous',
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify the configuration was saved correctly.
    $config = $this->config('entity_mesh.settings');
    $analyzer_account = $config->get('analyzer_account');
    $this->assertEquals('anonymous', $analyzer_account['type']);
    $this->assertNull($analyzer_account['roles']);
    $this->assertNull($analyzer_account['user_id']);

    // Test authenticated account type with roles.
    $this->drupalGet('admin/config/system/entity-mesh/config');
    $edit = [
      'processing_mode' => 'asynchronous',
      'account_type' => 'authenticated',
      'roles[administrator]' => 'administrator',
      'roles[redactor]' => 'redactor',
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify the configuration was saved correctly.
    $config = $this->config('entity_mesh.settings');
    $analyzer_account = $config->get('analyzer_account');
    $this->assertEquals('authenticated', $analyzer_account['type']);
    $this->assertContains('administrator', $analyzer_account['roles']);
    $this->assertContains('redactor', $analyzer_account['roles']);
    $this->assertNull($analyzer_account['user_id']);

    // Test user account type.
    $this->drupalGet('admin/config/system/entity-mesh/config');
    $edit = [
      'processing_mode' => 'asynchronous',
      'account_type' => 'user',
      'user_id' => 'test_user (' . $user->id() . ')',
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify the configuration was saved correctly.
    $config = $this->config('entity_mesh.settings');
    $analyzer_account = $config->get('analyzer_account');
    $this->assertEquals('user', $analyzer_account['type']);
    $this->assertNull($analyzer_account['roles']);
    $this->assertEquals($user->id(), $analyzer_account['user_id']);
  }

  /**
   * Tests that getMeshAccount() uses configured analyzer_account.
   */
  public function testMeshAccountUsesConfiguredAccount() {
    // Test anonymous account configuration.
    $config = \Drupal::configFactory()->getEditable('entity_mesh.settings');
    $config->set('analyzer_account', [
      'type' => 'anonymous',
      'roles' => NULL,
      'user_id' => NULL,
    ]);
    $config->save();

    // Get the repository service and test getMeshAccount().
    $repository = \Drupal::service('entity_mesh.repository');
    $account = $repository->getMeshAccount();

    $this->assertInstanceOf(DummyAccount::class, $account);
    $this->assertTrue($account->isAnonymous());
    $this->assertContains('anonymous', $account->getRoles());

    // Test authenticated account with roles.
    $config->set('analyzer_account', [
      'type' => 'authenticated',
      'roles' => ['administrator', 'redactor'],
      'user_id' => NULL,
    ]);
    $config->save();

    // Clear cache by creating a new repository instance.
    $container = \Drupal::getContainer();
    $new_repository = new Repository(
      $container->get('database'),
      $container->get('logger.factory')->get('entity_mesh'),
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('config.factory'),
      $container->get('file_url_generator')
    );

    $account = $new_repository->getMeshAccount();
    $this->assertFalse($account->isAnonymous());
    $this->assertTrue($account->isAuthenticated());

    $roles = $account->getRoles();
    $this->assertContains('authenticated', $roles);
    $this->assertContains('administrator', $roles);
    $this->assertContains('redactor', $roles);

    // Test user account type.
    $config->set('analyzer_account', [
      'type' => 'user',
      'roles' => NULL,
      'user_id' => $this->adminUser->id(),
    ]);
    $config->save();

    // Create another new repository instance.
    $another_repository = new Repository(
      $container->get('database'),
      $container->get('logger.factory')->get('entity_mesh'),
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('config.factory'),
      $container->get('file_url_generator')
    );

    $account = $another_repository->getMeshAccount();
    $this->assertFalse($account->isAnonymous());
    $this->assertTrue($account->isAuthenticated());
    // The admin user should have authenticated role at minimum.
    $this->assertContains('authenticated', $account->getRoles());
  }

}
