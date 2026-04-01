<?php

namespace Drupal\Tests\image_style_warmer\Functional;

/**
 * Functional test to check settings form of Image Style Warmer.
 *
 * @group image_style_warmer
 */
class ImageStyleWarmerSettingsFormTest extends ImageStyleWarmerTestBase {

  /**
   * Anonymous users don't have access to settings page.
   */
  public function testAnonymousPermissionDenied() {

    // @todo Remove when Drupal 10.3.0 or higher is the minimum version.
    if (version_compare(\Drupal::VERSION, '10.3.0', '<')) {
      $this->drupalLogout();
    }
    else {
      $this->drupalResetSession();
    }

    $this->drupalGet('admin/config/development/performance/image-style-warmer');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Authenticated non-admin users don't have access to settings page.
   */
  public function testAuthenticatedPermissionDenied() {
    $this->drupalLogin($this->createUser());
    $this->drupalGet('admin/config/development/performance/image-style-warmer');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Test Image Style Warmer settings page.
   */
  public function testSettingsPage() {

    // The admin user can access image_style_warmer settings page.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/development/performance/image-style-warmer');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Image styles');
    $this->assertSession()->pageTextContains('Configure image styles which will be created initially or via queue worker by Image Style Warmer.');
    $this->assertSession()->pageTextContains('Initial image styles');
    $this->assertSession()->pageTextContains('Select image styles which will be created initial for an image.');
    $this->assertSession()->pageTextContains('Queue image styles');
    $this->assertSession()->pageTextContains('Select image styles which will be created via queue worker.');
    $this->assertSession()->buttonExists('Save configuration');

    // Can save settings with a selected initial and queue image style.
    $settings = [
      'initial_image_styles[test_initial]' => 'test_initial',
      'queue_image_styles[test_queue]' => 'test_queue',
    ];
    $this->submitForm($settings, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->checkboxChecked('initial_image_styles[test_initial]');
    $this->assertSession()->checkboxChecked('queue_image_styles[test_queue]');

    $this->assertIsArray($this->config('image_style_warmer.settings')->get('initial_image_styles'));
    $this->assertArrayHasKey('test_initial', $this->config('image_style_warmer.settings')->get('initial_image_styles'));
    $this->assertIsArray($this->config('image_style_warmer.settings')->get('queue_image_styles'));
    $this->assertArrayHasKey('test_queue', $this->config('image_style_warmer.settings')->get('queue_image_styles'));
  }

}
