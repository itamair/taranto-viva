<?php

namespace Drupal\Tests\entity_mesh\Unit;

use Drupal\entity_mesh\Target;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the Target class URL validation.
 *
 * @group entity_mesh
 * @coversDefaultClass \Drupal\entity_mesh\Target
 */
class TargetTest extends UnitTestCase {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a mock request stack with a request.
    $this->requestStack = new RequestStack();
    $request = Request::create('http://example.com');
    $this->requestStack->push($request);
  }

  /**
   * Creates a Target instance for testing.
   *
   * @return \Drupal\entity_mesh\Target
   *   The target instance.
   */
  protected function createTarget(): Target {
    return Target::create($this->requestStack);
  }

  /**
   * Tests that valid telephone links are processed.
   *
   * @covers ::processHrefAndSetComponents
   * @covers ::isValidUrl
   */
  public function testTelephoneLinkCategorization() {
    $target = $this->createTarget();
    $target->processHrefAndSetComponents('tel:+34654654654');

    // Valid tel: links return validation code 2 and continue normal processing.
    $this->assertEquals('tel:+34654654654', $target->getHref());
    $this->assertEquals('tel', $target->getScheme());
  }

  /**
   * Tests that mailto links are processed.
   *
   * @covers ::processHrefAndSetComponents
   * @covers ::isValidUrl
   */
  public function testMailtoLinkCategorization() {
    $target = $this->createTarget();
    $target->processHrefAndSetComponents('mailto:test@example.com');

    // Mailto links return validation code 2 and continue normal processing.
    $this->assertEquals('mailto:test@example.com', $target->getHref());
    $this->assertEquals('mailto', $target->getScheme());
  }

  /**
   * Tests that javascript links are processed.
   *
   * @covers ::processHrefAndSetComponents
   * @covers ::isValidUrl
   */
  public function testJavascriptLinkCategorization() {
    $target = $this->createTarget();
    $target->processHrefAndSetComponents('javascript:void(0)');

    // Javascript links return validation code 2 and continue normal processing.
    $this->assertEquals('javascript:void(0)', $target->getHref());
    $this->assertEquals('javascript', $target->getScheme());
  }

  /**
   * Tests that data URIs are processed.
   *
   * @covers ::processHrefAndSetComponents
   * @covers ::isValidUrl
   */
  public function testDataUriCategorization() {
    $target = $this->createTarget();
    $target->processHrefAndSetComponents('data:image/png;base64,iVBORw0KGgo=');

    // Data URIs return validation code 2 and continue normal processing.
    $this->assertEquals('data:image/png;base64,iVBORw0KGgo=', $target->getHref());
    $this->assertEquals('data', $target->getScheme());
  }

  /**
   * Tests that FTP links are processed.
   *
   * @covers ::processHrefAndSetComponents
   * @covers ::isValidUrl
   */
  public function testFtpLinkCategorization() {
    $target = $this->createTarget();
    $target->processHrefAndSetComponents('ftp://ftp.example.com/file.txt');

    // FTP links return validation code 2 and continue normal processing.
    $this->assertEquals('ftp://ftp.example.com/file.txt', $target->getHref());
    $this->assertEquals('ftp', $target->getScheme());
  }

  /**
   * Tests that valid HTTP URLs are processed correctly.
   *
   * @covers ::processHrefAndSetComponents
   * @covers ::isValidUrl
   */
  public function testHttpUrlValidation() {
    $target = $this->createTarget();
    $target->processHrefAndSetComponents('http://external.com/page');

    $this->assertEquals('external', $target->getLinkType());
    $this->assertEquals('http', $target->getScheme());
    $this->assertEquals('external.com', $target->getHost());
  }

  /**
   * Tests that valid HTTPS URLs are processed correctly.
   *
   * @covers ::processHrefAndSetComponents
   * @covers ::isValidUrl
   */
  public function testHttpsUrlValidation() {
    $target = $this->createTarget();
    $target->processHrefAndSetComponents('https://external.com/page');

    $this->assertEquals('external', $target->getLinkType());
    $this->assertEquals('https', $target->getScheme());
    $this->assertEquals('external.com', $target->getHost());
  }

  /**
   * Tests that relative internal URLs are processed correctly.
   *
   * @covers ::processHrefAndSetComponents
   * @covers ::isValidUrl
   */
  public function testRelativeInternalUrlValidation() {
    $target = $this->createTarget();
    $target->processHrefAndSetComponents('/node/123');

    $this->assertEquals('internal', $target->getLinkType());
    $this->assertEquals('/node/123', $target->getPath());
  }

  /**
   * Tests that empty URLs are handled correctly.
   *
   * @covers ::processHrefAndSetComponents
   * @covers ::isValidUrl
   */
  public function testEmptyUrlValidation() {
    $target = $this->createTarget();
    $target->processHrefAndSetComponents('   ');

    // Empty URLs should be categorized as invalid.
    $this->assertEquals('link', $target->getCategory());
    $this->assertEquals('invalid-url', $target->getSubcategory());
  }

  /**
   * Tests that malformed URLs are categorized as invalid.
   *
   * @covers ::processHrefAndSetComponents
   * @covers ::isValidUrl
   */
  public function testMalformedUrlCategorization() {
    $target = $this->createTarget();
    $target->processHrefAndSetComponents('ht!tp://invalid url with spaces');

    $this->assertEquals('link', $target->getCategory());
    $this->assertEquals('invalid-url', $target->getSubcategory());
  }

  /**
   * Tests case-insensitive scheme detection.
   *
   * @covers ::processHrefAndSetComponents
   * @covers ::isValidUrl
   */
  public function testCaseInsensitiveSchemeDetection() {
    // Test uppercase TEL - href is preserved as-is, but validation works.
    $target = $this->createTarget();
    $target->processHrefAndSetComponents('TEL:+1234567890');

    $this->assertEquals('TEL:+1234567890', $target->getHref());
    // Scheme from parse_url is lowercase.
    $this->assertNotNull($target->getScheme());

    // Test mixed case MailTo.
    $target2 = $this->createTarget();
    $target2->processHrefAndSetComponents('MailTo:user@example.com');

    $this->assertEquals('MailTo:user@example.com', $target2->getHref());
    $this->assertNotNull($target2->getScheme());
  }

  /**
   * Tests URL decoding before validation.
   *
   * @covers ::processHrefAndSetComponents
   */
  public function testUrlDecoding() {
    $target = $this->createTarget();
    $encoded_url = 'https://example.com/path%20with%20spaces';
    $target->processHrefAndSetComponents($encoded_url);

    // The href should be stored decoded.
    $this->assertStringContainsString('path with spaces', $target->getHref());
  }

  /**
   * Tests that empty tel: links are marked as invalid.
   *
   * @covers ::processHrefAndSetComponents
   * @covers ::isValidUrl
   */
  public function testEmptyTelephoneValidation() {
    $target = $this->createTarget();
    $target->processHrefAndSetComponents('tel:');

    $this->assertEquals('link', $target->getCategory());
    $this->assertEquals('invalid-tel', $target->getSubcategory());
  }

  /**
   * Tests that tel: links with no digits are marked as invalid.
   *
   * @covers ::processHrefAndSetComponents
   * @covers ::isValidUrl
   */
  public function testTelephoneWithNoDigits() {
    $target = $this->createTarget();
    $target->processHrefAndSetComponents('tel:abc');

    $this->assertEquals('link', $target->getCategory());
    $this->assertEquals('invalid-tel', $target->getSubcategory());
  }

  /**
   * Tests that tel: links with invalid characters are marked as invalid.
   *
   * @covers ::processHrefAndSetComponents
   * @covers ::isValidUrl
   */
  public function testTelephoneWithInvalidCharacters() {
    // Test with script tag.
    $target = $this->createTarget();
    $target->processHrefAndSetComponents('tel:+34<script>alert(1)</script>');

    $this->assertEquals('link', $target->getCategory());
    $this->assertEquals('invalid-tel', $target->getSubcategory());

    // Test with spaces (RFC 3966 does not allow spaces as separators).
    $target2 = $this->createTarget();
    $target2->processHrefAndSetComponents('tel:+1 234 567 8901');

    $this->assertEquals('link', $target2->getCategory());
    $this->assertEquals('invalid-tel', $target2->getSubcategory());
  }

  /**
   * Tests that valid tel: links with various formats are accepted.
   *
   * RFC 3966 rules:
   * - Global numbers (starting with +) accept digits and visual separators
   *   (hyphens, dots, parentheses).
   * - Local numbers (no +) are only valid with ;phone-context= parameter.
   * - Optional ;ext=<digits> is supported on global numbers.
   *
   * @covers ::processHrefAndSetComponents
   * @covers ::isValidUrl
   */
  public function testValidTelephoneFormats() {
    // Standard global E.164 format.
    $target1 = $this->createTarget();
    $target1->processHrefAndSetComponents('tel:+1-555-123-4567');
    $this->assertEquals('tel:+1-555-123-4567', $target1->getHref());
    $this->assertNotEquals('invalid-tel', $target1->getSubcategory());

    // Valid local number with domain phone-context.
    $target2 = $this->createTarget();
    $target2->processHrefAndSetComponents(
      'tel:5551234;phone-context=example.com'
    );
    $this->assertEquals(
      'tel:5551234;phone-context=example.com',
      $target2->getHref()
    );
    $this->assertNotEquals('invalid-tel', $target2->getSubcategory());

    // Global number with extension.
    $target3 = $this->createTarget();
    $target3->processHrefAndSetComponents('tel:+15551234567;ext=101');
    $this->assertEquals('tel:+15551234567;ext=101', $target3->getHref());
    $this->assertNotEquals('invalid-tel', $target3->getSubcategory());

    // All visual separators: hyphens, dots, and parentheses.
    $target4 = $this->createTarget();
    $target4->processHrefAndSetComponents('tel:+1(555).123-4567');
    $this->assertEquals('tel:+1(555).123-4567', $target4->getHref());
    $this->assertNotEquals('invalid-tel', $target4->getSubcategory());

    // Global number E.164 format with plus only (no separators).
    $target5 = $this->createTarget();
    $target5->processHrefAndSetComponents('tel:+12345678901');
    $this->assertEquals('tel:+12345678901', $target5->getHref());
    $this->assertNotEquals('invalid-tel', $target5->getSubcategory());

    // Global with dots only.
    $target6 = $this->createTarget();
    $target6->processHrefAndSetComponents('tel:+1.234.567.8901');
    $this->assertEquals('tel:+1.234.567.8901', $target6->getHref());
    $this->assertNotEquals('invalid-tel', $target6->getSubcategory());

    // Global mixed dots and dashes.
    $target7 = $this->createTarget();
    $target7->processHrefAndSetComponents('tel:+34.654-654-654');
    $this->assertEquals('tel:+34.654-654-654', $target7->getHref());
    $this->assertNotEquals('invalid-tel', $target7->getSubcategory());

    // International number with 00 prefix.
    $target8 = $this->createTarget();
    $target8->processHrefAndSetComponents('tel:0045345353453');
    $this->assertEquals('tel:0045345353453', $target8->getHref());
    $this->assertNotEquals('invalid-tel', $target8->getSubcategory());

    // Local number without phone-context (common real-world usage).
    $target9 = $this->createTarget();
    $target9->processHrefAndSetComponents('tel:03448910196');
    $this->assertEquals('tel:03448910196', $target9->getHref());
    $this->assertNotEquals('invalid-tel', $target9->getSubcategory());

    // Plain local number (digits only).
    $target10 = $this->createTarget();
    $target10->processHrefAndSetComponents('tel:1234567');
    $this->assertEquals('tel:1234567', $target10->getHref());
    $this->assertNotEquals('invalid-tel', $target10->getSubcategory());
  }

}
