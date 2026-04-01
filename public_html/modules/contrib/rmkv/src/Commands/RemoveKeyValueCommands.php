<?php

namespace Drupal\rmkv\Commands;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ProfileExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drush\Commands\DrushCommands;

/**
 * Implements form of remove key/value.
 *
 * Remove system.schema key/value storage.
 */
final class RemoveKeyValueCommands extends DrushCommands {

  /**
   * System schema of Key/value storage.
   */
  protected KeyValueStoreInterface $keyValueStore;

  /**
   * Constructs command of remove key/value.
   */
  public function __construct(
    protected ProfileExtensionList $profileExtensionList,
    protected ModuleHandlerInterface $moduleHandler,
    protected ThemeHandlerInterface $themeHandler,
    KeyValueFactoryInterface $keyvalue,
  ) {
    $this->keyValueStore = $keyvalue->get('system.schema');
  }

  /**
   * Check if be removable of the specified machine name from key/value storage.
   *
   * @command rmkv:check
   * @usage drush rmkv:check <machine_name>
   */
  public function rmkvCheck(string $machine_name) {
    if ($this->keyValueStore->has($machine_name)
      && !$this->profileExtensionList->exists($machine_name)
      && !$this->moduleHandler->moduleExists($machine_name)
      && !$this->themeHandler->themeExists($machine_name)
    ) {
      $this->logger()->notice(\dt('Specified machine name "@machine_name" is removable from the system.schema key/value storage.', [
        '@machine_name' => $machine_name,
      ]));
    }
    else {
      $this->logger()->warning(\dt('Specified machine name "@machine_name" is not removable from the system.schema key/value storage.', [
        '@machine_name' => $machine_name,
      ]));
    }
  }

  /**
   * Remove specified machine name from key/value storage.
   *
   * @command rmkv
   * @usage drush rmkv <machine_name>
   */
  public function rmkv(string $machine_name) {
    if (!$this->keyValueStore->has($machine_name)) {
      $this->logger()->error(\dt('Specified machine name "@machine_name" is not exists to the system.schema key/value storage.', [
        '@machine_name' => $machine_name,
      ]));
    }
    elseif ($this->profileExtensionList->exists($machine_name)
      || $this->moduleHandler->moduleExists($machine_name)
      || $this->themeHandler->themeExists($machine_name)
    ) {
      $this->logger()->error(\dt('Cannot specify the machine name "@machine_name" of the installed profile, module or theme. Specify the machine name of the uninstalled profile, module or theme.', [
        '@machine_name' => $machine_name,
      ]));
    }
    elseif ($this->keyValueStore->has($machine_name)) {
      if (!$this->profileExtensionList->exists($machine_name)
        && !$this->moduleHandler->moduleExists($machine_name)
        && !$this->themeHandler->themeExists($machine_name)
      ) {
        $this->keyValueStore->delete($machine_name);
        $this->logger()->success(\dt('Succeeded in removing "@machine_name" from system.schema key/value storage.', [
          '@machine_name' => $machine_name,
        ]));
      }
      else {
        $this->logger()->error(\dt('Aborted remove of "@machine_name" from system.schema key/value storage, because specified machine name is already installed.', [
          '@machine_name' => $machine_name,
        ]));
      }
    }
    else {
      $this->logger()->error(\dt('Specified machine name "@machine_name" is not exists to the system.schema key/value storage. (* This message is displayed if it may have already been deleted.)', [
        '@machine_name' => $machine_name,
      ]));
    }
  }

}
