<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonrpc\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Test installation and uninstallation.
 *
 * @group jsonrpc
 */
class JsonRpcInstallTest extends KernelTestBase {

  private const MODULE_NAME = 'jsonrpc';

  /**
   * Test that the module can be installed and uninstalled.
   */
  public function testInstallUninstall(): void {
    \Drupal::service('module_installer')->install([self::MODULE_NAME]);

    \Drupal::service('module_installer')->uninstall([self::MODULE_NAME]);

    // Try installing and uninstalling again.
    \Drupal::service('module_installer')->install([self::MODULE_NAME]);

    \Drupal::service('module_installer')->uninstall([self::MODULE_NAME]);
  }

}
