<?php

namespace Drupal\Tests\content_access\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Automated BrowserTest Case for having a tiny test to run fast.
 *
 * @group Access
 */
class ContentAccessTinyTest extends BrowserTestBase {
  use ContentAccessTestHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['content_access', 'acl'];

  /**
   * A user with permission to non administer.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $testUser;

  /**
   * A user with permission to administer.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * Content type for test.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  protected $contentType;

  /**
   * Node object to perform test.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node1;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create test user with separate role.
    $this->testUser = $this->drupalCreateUser();

    // Get the value of the new role.
    // @see drupalCreateUser().
    $testUserRoles = $this->testUser->getRoles();
    foreach ($testUserRoles as $role) {
      if (!in_array($role, [AccountInterface::AUTHENTICATED_ROLE])) {
        $this->rid = $role;
        break;
      }
    }

    // Create admin user.
    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'administer content types',
      'grant content access',
      'grant own content access',
      'bypass node access',
      'access administration pages',
    ]);
    $this->drupalLogin($this->adminUser);

    // Rebuild content access permissions.
    node_access_rebuild();

    // Create test content type.
    $this->contentType = $this->drupalCreateContentType();

    // Create test node.
    $this->node1 = $this->drupalCreateNode(['type' => $this->contentType->id()]);
  }

  /**
   * Test Viewing accessibility with permissions for single users.
   */
  public function testViewAccess() {
    $this->enableContentAccessWithBaseSettings();
    // Allow access for test user.
    $edit = [
      'acl[view][add]' => $this->getAutocompleteInputString($this->testUser),
    ];
    $this->drupalGet('node/' . $this->node1->id() . '/access');
    $this->submitForm($edit, 'Add User');
    $this->submitForm([], 'Submit');

    // Logout admin, try to access the node anonymously.
    $this->drupalLogout();
    $this->drupalGet('node/' . $this->node1->id());
    $this->assertSession()->pageTextContains('Access denied');

    // Login test user, view access should be allowed now.
    $this->drupalLogin($this->testUser);
    $this->drupalGet('node/' . $this->node1->id());
    $this->assertSession()->pageTextNotContains('Access denied');

    // Login admin and disable per node access.
    $this->drupalLogin($this->adminUser);
    $this->changeAccessPerNode(FALSE);

    // Logout admin, try to access the node anonymously.
    $this->drupalLogout();
    $this->drupalGet('node/' . $this->node1->id());
    $this->assertSession()->pageTextContains('Access denied');

    // Login test user, view access should be denied now.
    $this->drupalLogin($this->testUser);
    $this->drupalGet('node/' . $this->node1->id());
    $this->assertSession()->pageTextContains('Access denied');
  }

  /**
   * Test if the node.node_access_needs_rebuild is set when needed.
   */
  public function testNodeAccessRebuiltSet(): void {
    $this->assertNotTrue(\Drupal::state()->get('node.node_access_needs_rebuild'), 'Node access permissions do not need to be rebuilt');
    // Add a new role for the test user, this should trigger
    // content_access_user_update() and a message about content
    // access permissions have to be rebuilt.
    $rid = $this->drupalCreateRole([]);
    $this->testUser->addRole($rid)->save();
    $this->assertTrue(\Drupal::state()->get('node.node_access_needs_rebuild'), 'Node access permissions need to be rebuilt');
  }

  /*
   * Test Editing accessibility with permissions for single users.
   */
  /*
  public function testEditAccess() {
  // Exit test if ACL module could not be enabled.
  if (!\Drupal::moduleHandler()->moduleExists('acl')) {
  $this->pass('No ACL module present, skipping test');
  return;
  }

  // Enable per node access control.
  $this->changeAccessPerNode();

  // Allow edit access for test user.
  $edit = [
  'acl[update][add]' => $this->testUser->getAccountName(),
  ];
  $this->drupalGet('node/' . $this->node1->id() . '/access');
  $this->submitForm($edit, 'Add User');
  $this->submitForm([], 'Submit');

  // Logout admin, try to edit the node anonymously.
  $this->drupalLogout();
  $this->drupalGet('node/' . $this->node1->id() . '/edit');
  $this->assertSession()->pageTextContains('Access denied');

  // Login test user, edit access should be allowed now.
  $this->drupalLogin($this->testUser);
  $this->drupalGet('node/' . $this->node1->id() . '/edit');
  $this->assertSession()->pageTextNotContains('Access denied');

  // Login admin and disable per node access.
  $this->drupalLogin($this->adminUser);
  $this->changeAccessPerNode(FALSE);

  // Logout admin, try to edit the node anonymously.
  $this->drupalLogout();
  $this->drupalGet('node/' . $this->node1->id() . '/edit');
  $this->assertSession()->pageTextContains('Access denied');

  // Login test user, edit access should be denied now.
  $this->drupalLogin($this->testUser);
  $this->drupalGet('node/' . $this->node1->id() . '/edit');
  $this->assertSession()->pageTextContains('Access denied');
  }
   */

  /*
   * Test Deleting accessibility with permissions for single users.
   */
  /*
  public function testDeleteAccess() {
  // Exit test if ACL module could not be enabled.
  if (!\Drupal::moduleHandler()->moduleExists('acl')) {
  $this->pass('No ACL module present, skipping test');
  return;
  }

  // Enable per node access control.
  $this->changeAccessPerNode();

  // Allow delete access for test user.
  $edit = [
  'acl[delete][add]' => $this->testUser->getAccountName(),
  ];
  $this->drupalGet('node/' . $this->node1->id() . '/access');
  $this->submitForm($edit, 'Add User');
  $this->submitForm([], 'Submit');

  // Logout admin, try to delete the node anonymously.
  $this->drupalLogout();
  $this->drupalGet('node/' . $this->node1->id() . '/delete');
  $this->assertSession()->pageTextContains('Access denied');

  // Login test user, delete access should be allowed now.
  $this->drupalLogin($this->testUser);
  $this->drupalGet('node/' . $this->node1->id() . '/delete');
  $this->assertSession()->pageTextNotContains('Access denied');

  // Login admin and disable per node access.
  $this->drupalLogin($this->adminUser);
  $this->changeAccessPerNode(FALSE);

  // Logout admin, try to delete the node anonymously.
  $this->drupalLogout();
  $this->drupalGet('node/' . $this->node1->id() . '/delete');
  $this->assertSession()->pageTextContains('Access denied');

  // Login test user, delete access should be denied now.
  $this->drupalLogin($this->testUser);
  $this->drupalGet('node/' . $this->node1->id() . '/delete');
  $this->assertSession()->pageTextContains('Access denied');
  }
   */

}
