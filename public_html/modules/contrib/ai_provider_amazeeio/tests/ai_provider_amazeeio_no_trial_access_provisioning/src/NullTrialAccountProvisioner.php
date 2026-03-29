<?php

declare(strict_types=1);

namespace Drupal\ai_provider_amazeeio_no_trial_access_provisioning;

use Drupal\ai_provider_amazeeio\TrialAccess\TrialAccountProvisionerInterface;
use Drupal\ai_provider_amazeeio\TrialAccess\TrialAccountProvisioningResult;

/**
 * Null trial account provisioner.
 *
 * @internal
 */
final class NullTrialAccountProvisioner implements TrialAccountProvisionerInterface {

  /**
   * {@inheritdoc}
   */
  public function provision(): TrialAccountProvisioningResult {
    return TrialAccountProvisioningResult::Provisioned;
  }

}
