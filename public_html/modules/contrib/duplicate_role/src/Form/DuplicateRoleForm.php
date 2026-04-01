<?php

namespace Drupal\duplicate_role\Form;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for adding a new role.
 */
class DuplicateRoleForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a DuplicateRoleForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(EntityTypeManager $entity_type_manager, MessengerInterface $messenger, RouteMatchInterface $route_match) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'duplicate_role_form';
  }

  /**
   * Retrieves the names of available roles.
   *
   * @return array
   *   An associative array with the role id as the key and the role object as
   *   value.
   */
  protected function roleNames(): array {
    $roles = Role::loadMultiple();
    return array_map(static fn(RoleInterface $role) => $role->label(), $roles);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $note = NULL) {
    $u_roles = $this->roleNames();
    asort($u_roles);

    $options = [];
    $options[''] = $this->t('-select-');
    foreach ($u_roles as $key => $value) {
      $options[$key] = $value;
    }

    // Try to get a base role from route parameter.
    $base_role = $this->routeMatch->getParameter('role');
    $form['base_role'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose role to duplicate'),
      '#description' => $this->t('Select role to duplicate'),
      '#options' => $options,
      '#required' => TRUE,
      '#access' => !isset($options[$base_role]),
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('New role'),
      '#required' => TRUE,
      '#size' => 40,
      '#maxlength' => 40,
      '#description' => $this->t('The name for the duplicated role. Example: "Moderator", "Editorial board", "Site architect".'),
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => '',
      '#required' => TRUE,
      '#size' => 30,
      '#maxlength' => 64,
      '#machine_name' => [
        'exists' => [Role::class, 'load'],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Duplicate'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $roles = $this->roleNames();
    $base_role_id = $this->routeMatch->getParameter('role');
    if (!isset($roles[$base_role_id])) {
      $base_role_id = $form_state->getValue('base_role');
    }

    $new_role_name = $form_state->getValue('label');
    $new_role_id = $form_state->getValue('id');

    /** @var \Drupal\user\RoleInterface $role */
    $base_role = $this->entityTypeManager->getStorage('user_role')->load($base_role_id);
    if ($base_role !== NULL) {
      $new_role = Role::create(['id' => $new_role_id, 'label' => $new_role_name]);
      $new_role->save();
      user_role_grant_permissions($new_role->id(), $base_role->getPermissions());
      $this->messenger->addStatus($this->t('Role %role_name has been added.', ['%role_name' => $new_role_name]));
      $form_state->setRedirect('entity.user_role.collection');
    }
    else {
      $this->messenger->addError($this->t('Base role %base_role_id not found.', ['%base_role_id' => $base_role_id]));
    }
  }

}
