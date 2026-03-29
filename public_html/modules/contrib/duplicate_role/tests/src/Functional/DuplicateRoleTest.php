<?php

namespace Drupal\Tests\duplicate_role\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;

/**
 * Functional tests for Duplicate Role module.
 *
 * @group duplicate_role
 */
class DuplicateRoleTest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'duplicate_role'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('local_actions_block');
  }

  /**
   * Tests module permission, links visibility, and page access.
   */
  public function testDuplicateRolePermissions() {
    $this->drupalLogin($this->createUser(['administer permissions']));
    $this->drupalGet('/admin/people/roles');
    $this->assertSession()->statusCodeEquals(200);

    // Duplicate role links should not be visible.
    $this->assertSession()->linkNotExists('Duplicate role');
    $this->assertSession()->linkNotExists('Duplicate');

    // Page should not be accessible.
    $this->drupalGet('/admin/people/roles/duplicate');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->createUser([
      'administer permissions',
      'administer duplicate role',
    ]));
    $this->drupalGet('/admin/people/roles');
    $this->assertSession()->statusCodeEquals(200);

    // Duplicate role links should be visible and pages should be accessible.
    $this->assertSession()->linkExists('Duplicate role');
    $this->assertSession()->linkExists('Duplicate');

    $this->drupalGet('/admin/people/roles/duplicate');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests the "Duplicate" functionality via UI interaction.
   */
  public function testDuplicateRoleUi() {
    $this->drupalLogin($this->createUser([
      'administer permissions',
      'administer duplicate role',
    ]));
    $this->drupalGet('/admin/people/roles');

    // Verify "Duplicate role" local action.
    $this->clickLink('Duplicate role');
    $this->assertSession()->addressEquals('/admin/people/roles/duplicate');
    $this->assertSession()->elementExists('css', 'select[name="base_role"]');

    $base_role = Role::load('authenticated');
    user_role_grant_permissions($base_role->id(), ['administer duplicate role']);

    $new_role_name = '123';
    $edit = [
      'base_role' => $base_role->id(),
      'label' => $new_role_name,
      'id' => $new_role_name,
    ];
    $this->submitForm($edit, 'Duplicate');
    $this->assertSession()->responseContains($this->t('Role %label has been added.', ['%label' => 123]));
    $this->assertSession()->addressEquals('/admin/people/roles');
    /** @var \Drupal\user\RoleInterface $new_role */
    $new_role = Role::load($new_role_name);
    $this->assertIsObject($new_role);

    // Verify that new role have the same permissions.
    $this->assertEquals($new_role->getPermissions(), $base_role->getPermissions());
    $new_role->hasPermission('administer duplicate role');

    // Verify "Duplicate" entity operation.
    $this->drupalGet('/admin/people/roles');
    // Click on the first "Duplicate" link, which should be operation for
    // the "Anonymous" user role.
    $this->clickLink('Duplicate');
    $this->assertSession()->addressEquals('/admin/people/roles/duplicate/anonymous');
    // Select list should not be visible.
    $this->assertSession()->elementNotExists('css', 'select[name="base_role"]');
    $new_role_name = 'copy';
    $edit = [
      'label' => $new_role_name,
      'id' => $new_role_name,
    ];
    $this->submitForm($edit, 'Duplicate');
    $this->assertSession()->responseContains($this->t('Role %label has been added.', ['%label' => 'copy']));
    $new_role = Role::load($new_role_name);
    $this->assertIsObject($new_role);

    // Verify the form validation.
    $this->drupalGet('/admin/people/roles/duplicate');
    $edit = [
      'base_role' => $base_role->id(),
      'label' => $new_role_name,
      'id' => $new_role_name,
    ];
    $this->submitForm($edit, 'Duplicate');
    $this->assertSession()->responseContains('The machine-readable name is already in use. It must be unique.');
  }

}
