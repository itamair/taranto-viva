<?php

namespace Drupal\meaofd\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\meaofd\Services\Fixer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the Mismatched entity and/or field definitions report.
 */
class MismatchedEntityAndOrFieldDefinitionsReportController extends ControllerBase {

  /**
   * The mismatched entity and/or field definitions fixer service.
   *
   * @var \Drupal\meaofd\Services\Fixer
   */
  protected $fixer;

  /**
   * Constructor.
   *
   * @param \Drupal\meaofd\Services\Fixer $fixer
   *   The mismatched entity and/or field definitions fixer service.
   */
  public function __construct(Fixer $fixer) {
    $this->fixer = $fixer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('meaofd.fixer')
    );
  }

  /**
   * Generates a report of mismatched entity and/or field definitions.
   *
   * @return array
   *   A render array containing the report table or a message.
   */
  public function report(): array {
    $change_summary = $this->fixer->getChangeSummary();
    if (empty($change_summary)) {
      return [
        '#markup' => $this->t('No mismatched entity and/or field definitions found.'),
      ];
    }

    $rows = [];
    $header = [
      $this->t('Entity ID'),
      $this->t('Mismatched entity and/or field definitions report'),
      $this->t('Actions'),
    ];

    $is_fix_allowed = $this->currentUser()->hasPermission('fix mismatched entity and/or field definitions');

    foreach ($change_summary as $entity_type => $changes) {
      if (empty($changes)) {
        continue;
      }

      // Add a link for fixing mismatched entities if the user has permissions
      // or a disabled button if the user lacks them.
      $actions = [];
      if ($is_fix_allowed) {
        $actions = [
          'data' => [
            '#type' => 'link',
            '#title' => (count($changes) == 1) ? $this->t('Fix') : $this->t('Fix all'),
            '#url' => Url::fromRoute('meaofd.fix', ['entity_type' => $entity_type]),
            '#disabled' => TRUE,
            '#attributes' => [
              'class' => [
                'btn',
                'button',
              ],
            ],
          ],
        ];
      }
      else {
        $actions = [
          'data' => [
            '#type' => 'container',
            '#markup' => (count($changes) == 1) ? $this->t('Fix') : $this->t('Fix all'),
            '#disabled' => TRUE,
            '#attributes' => [
              'class' => [
                'btn',
                'button',
                'disabled',
              ],
            ],
          ],
        ];
      }

      $rows[] = [
        $entity_type,
        Markup::create(implode('<br>', $changes)),
        $actions,
      ];
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attached' => [
        'library' => [
          'meaofd/report',
        ],
      ],
    ];
  }

}
