<?php

declare(strict_types=1);

namespace Drupal\ai_provider_amazeeio_no_trial_access_provisioning;

use Drupal\ai_provider_amazeeio\TrialAccess\ProgressReporterInterface;
use Drupal\ai_provider_amazeeio\TrialAccess\TrialAccountProvisionerFactoryInterface;
use Drupal\ai_provider_amazeeio\TrialAccess\TrialAccountProvisionerInterface;

/**
 * Null trial access provisioner factory.
 *
 * @internal
 */
final class NullTrialAccessProvisionerFactory implements TrialAccountProvisionerFactoryInterface {

  /**
   * {@inheritdoc}
   */
  public function create(ProgressReporterInterface $progressReporter): TrialAccountProvisionerInterface {
    return new NullTrialAccountProvisioner();
  }

}
