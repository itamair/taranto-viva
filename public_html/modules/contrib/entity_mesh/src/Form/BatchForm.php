<?php

namespace Drupal\entity_mesh\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_mesh\Batches\EntityTrackerBatch;
use Drupal\entity_mesh\Batches\NodeBatch;
use Drupal\entity_mesh\TrackerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form with examples on how to use cache.
 */
class BatchForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity mesh tracker service.
   *
   * @var \Drupal\entity_mesh\TrackerInterface
   */
  protected $tracker;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager service.
   * @param \Drupal\entity_mesh\TrackerInterface $tracker
   *   The entity mesh tracker service.
   */
  final public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager, TrackerInterface $tracker) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->tracker = $tracker;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('entity_mesh.tracker')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_mesh_batch_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#type' => 'item',
      '#markup' => '<h2>' . $this->t('Entity Mesh Batch Operations') . '</h2>',
    ];

    $form['details']['info'] = [
      '#markup' => $this->t('This process may take a while depending on the number of entities in your site.'),
    ];

    // Get tracker statistics.
    $pending_count = $this->tracker->getPendingCount();
    $failed_count = count($this->tracker->getFailedEntities());
    $tracked_count = $this->tracker->getTotalCount();

    // Get total entities to be tracked (optimized count query).
    $total_entities = EntityTrackerBatch::getItemsCount();

    $form['tracker_section'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Entity Tracker'),
      '#description' => $this->t('Add entities to the tracker for processing.'),
    ];

    $form['tracker_section']['tracker_stats'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity-mesh-tracker-stats']],
    ];

    $form['tracker_section']['tracker_stats']['stats'] = [
      '#markup' => '<div class="entity-mesh-stats">' .
      '<div class="stat-item"><strong>' . $this->t('Entities configured:') . '</strong> ' . $total_entities . '</div>' .
      '<div class="stat-item"><strong>' . $this->t('Entities tracked:') . '</strong> ' . $tracked_count . '</div>' .
      '<div class="stat-item"><strong>' . $this->t('Pending in tracker:') . '</strong> ' . $pending_count . '</div>' .
      '<div class="stat-item"><strong>' . $this->t('Failed:') . '</strong> ' . $failed_count . '</div>' .
      '</div>',
      '#attached' => [
        'library' => ['entity_mesh/batch_form'],
      ],
    ];

    $form['tracker_section']['tracker_info'] = [
      '#markup' => '<p>' . $this->t('This operation will add all configured entities to the tracker queue for Entity Mesh processing.') . '</p>',
    ];

    // Processing section.
    $form['processing_section'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Process Tracked Entities'),
      '#description' => $this->t('Process entities that are in the tracker queue.'),
    ];

    $has_pending = $pending_count > 0;

    if (!$has_pending) {
      $form['processing_section']['warning'] = [
        '#markup' => '<div class="messages messages--warning">' .
        $this->t('There are no entities in the tracker queue.') .
        '</div>',
      ];
    }

    $form['processing_section']['processing_info'] = [
      '#markup' => '<p>' . $this->t('This operation will process all pending entities from the tracker queue.') . '</p>',
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['track_entities'] = [
      '#type' => 'submit',
      '#value' => $this->t('Track entities'),
      '#button_type' => 'primary',
      '#submit' => ['::submitTrackEntities'],
    ];

    $form['actions']['process_entities'] = [
      '#type' => 'submit',
      '#value' => $this->t('Process tracked entities'),
      '#button_type' => 'primary',
      '#submit' => ['::submitProcessEntities'],
      '#disabled' => !$has_pending,
      '#attributes' => [
        'title' => !$has_pending ? $this->t('No entities available to process. Track entities first.') : '',
      ],
    ];

    // By default, render the form using system-config-form.html.twig.
    $form['#theme'] = 'system_config_form';

    return $form;
  }

  /**
   * Submit handler for tracking entities.
   */
  public function submitTrackEntities(array &$form, FormStateInterface $form_state) {
    $batch = EntityTrackerBatch::generateBatch();
    batch_set($batch->toArray());
  }

  /**
   * Submit handler for processing tracked entities.
   */
  public function submitProcessEntities(array &$form, FormStateInterface $form_state) {
    $batch = NodeBatch::generateBatch();
    batch_set($batch->toArray());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Default submit handler - not used since buttons have specific handlers.
  }

}
