<?php

namespace Drupal\popup_message\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Asset\AssetCollectionGroupOptimizerInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\popup_message\Enum\PopupMessageDefaultValues;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provide form for settings popup_message module.
 */
class PopupMessageSettingsForm extends ConfigFormBase {

  const POPUP_MESSAGE_CSS_NAME = 'popup.css';

  /**
   * CssCollectionOptimizer service.
   *
   * @var \Drupal\Core\Asset\CssCollectionOptimizerLazy
   */
  protected AssetCollectionGroupOptimizerInterface $cssOptimizer;

  /**
   * JsCollectionOptimizer service.
   *
   * @var \Drupal\Core\Asset\JsCollectionOptimizerLazy
   */
  protected AssetCollectionGroupOptimizerInterface $jsOptimizer;

  /**
   * Drupal\Core\Entity\EntityRepositoryInterface service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * The cache tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected CacheTagsInvalidatorInterface $cacheTagsInvalidator;

  /**
   * Drupal\Core\Extension\ModuleExtensionList service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  private ModuleExtensionList $moduleExtensionList;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = parent::create($container);
    $instance->cssOptimizer = $container->get('asset.css.collection_optimizer');
    $instance->jsOptimizer = $container->get('asset.js.collection_optimizer');
    $instance->entityRepository = $container->get('entity.repository');
    $instance->moduleExtensionList = $container->get('extension.list.module');
    $instance->cacheTagsInvalidator = $container->get('cache_tags.invalidator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'popup_message_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('popup_message.settings');

    $form['popup_message_enable'] = [
      '#type' => 'radios',
      '#title' => $this->t('Enable Popup'),
      '#default_value' => $config->get('enable') ? $config->get('enable') : 0,
      '#options' => [
        1 => $this->t('Enabled'),
        0 => $this->t('Disabled'),
      ],
    ];

    $form['popup_message_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Popup message settings'),
      '#collapsed' => FALSE,
      '#collapsible' => TRUE,
    ];

    $form['popup_message_fieldset']['popup_message_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message title'),
      '#required' => TRUE,
      '#default_value' => $config->get('title'),
    ];

    $popup_message_body = $config->get('body');

    $form['popup_message_fieldset']['popup_message_body'] = [
      '#type' => 'text_format',
      '#base_type' => 'textarea',
      '#title' => $this->t('Message body'),
      '#default_value' => $popup_message_body['value'] ?? NULL,
      '#format' => $popup_message_body['format'] ?? NULL,
    ];

    $form['popup_message_fieldset']['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings'),
      '#expanded' => FALSE,
    ];

    $form['popup_message_fieldset']['cookie_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Cookie settings'),
      '#expanded' => FALSE,
    ];

    $form['popup_message_fieldset']['settings']['popup_message_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Window width'),
      '#required' => TRUE,
      '#default_value' => $config->get('width') ?? PopupMessageDefaultValues::POPUP_MESSAGE_DEFAULT_SIZE->value,
    ];

    $form['popup_message_fieldset']['settings']['popup_message_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Window height'),
      '#required' => TRUE,
      '#default_value' => $config->get('height') ?? PopupMessageDefaultValues::POPUP_MESSAGE_DEFAULT_SIZE->value,
    ];

    $form['popup_message_fieldset']['cookie_settings']['popup_message_check_cookie'] = [
      '#type' => 'radios',
      '#title' => $this->t('Check cookie'),
      '#description' => $this->t('If enabled message will be displayed considering the Cookie lifetime. If disabled, the message will be displayed everytime the page reloads considering the Visibility configuration.'),
      '#default_value' => $config->get('check_cookie') ? $config->get('check_cookie') : 0,
      '#options' => [
        1 => $this->t('Enabled'),
        0 => $this->t('Disabled'),
      ],
    ];

    $form['popup_message_fieldset']['cookie_settings']['popup_message_expire'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cookie lifetime in days'),
      '#description' => $this->t('Define lifetime of the cookie in days. Message will not reappear until the expiration time is exceeded. If 0, popup will reappear if browser has been closed.'),
      '#default_value' => $config->get('expire') ? $config->get('expire') : 0,
    ];

    $form['popup_message_fieldset']['settings']['popup_message_delay'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delay'),
      '#description' => $this->t('Message will show after this number of seconds. Set to 0 to show instantly.'),
      '#default_value' => $config->get('delay') ? $config->get('delay') : 0,
    ];

    $form['popup_message_fieldset']['settings']['popup_message_close_delay'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delay before auto close'),
      '#description' => $this->t('Message will close after this number of seconds. Set to 0 to disable it.'),
      '#default_value' => $config->get('close_delay') ? $config->get('close_delay') : 0,
    ];

    // Styles.
    // Find styles in module directory.
    $directory = $this->moduleExtensionList->getPath('popup_message') . '/styles';
    $subdirectories = scandir($directory);
    $styles = [];

    foreach ($subdirectories as $subdirectory) {
      if (is_dir($directory . '/' . $subdirectory)) {
        if (file_exists($directory . '/' . $subdirectory . '/' . self::POPUP_MESSAGE_CSS_NAME)) {
          $lib_path = $subdirectory . '/' . self::POPUP_MESSAGE_CSS_NAME;
          $styles[$lib_path] = $subdirectory;
        }
      }
    }

    $form['popup_message_fieldset']['settings']['popup_message_cover_opacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Background opacity (%)'),
      '#min' => 0,
      '#max' => 100,
      '#step' => 5,
      '#default_value' => $config->get('cover_opacity') ?? 70,
      '#description' => $this->t('Allows to set a custom background opacity value in percentage (0-100%).'),
    ];

    $form['popup_message_fieldset']['settings']['popup_message_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Popup style'),
      '#default_value' => empty($config->get('style')) ? 0 : $config->get('style'),
      '#options' => $styles,
      '#description' => $this->t('To add custom styles create directory and file "modules/popup_message/popup_message_styles/custom_style/popup.css" and set in this file custom CSS code.'),
    ];

    $form['popup_message_fieldset']['visibility']['path'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Pages'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#group' => 'visibility',
      '#weight' => 0,
    ];

    $options = [
      $this->t('All pages except those listed'),
      $this->t('Only the listed pages'),
    ];

    $description = $this->t("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. Example paths are %blog for the blog page and %blog-wildcard for every personal blog. %front is the front page.",
      [
        '%blog' => 'blog',
        '%blog-wildcard' => 'blog/*',
        '%front' => '<front>',
      ]
    );

    $title = $this->t('Pages');

    $form['popup_message_fieldset']['visibility']['path']['popup_message_visibility'] = [
      '#type' => 'radios',
      '#title' => $this->t('Show block on specific pages'),
      '#options' => $options,
      '#default_value' => $config->get('visibility') ? $config->get('visibility') : 0,
    ];

    $form['popup_message_fieldset']['visibility']['path']['popup_message_visibility_pages'] = [
      '#type' => 'textarea',
      '#default_value' => $config->get('visibility_pages') ? $config->get('visibility_pages') : '',
      '#description' => $description,
      '#title' => '<span class="element-invisible">' . $title . '</span>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $save = FALSE;
    $flush_cache_css = FALSE;
    $flush_cache_js = FALSE;

    $keys = [
      'enable',
      'title',
      'body',
      'height',
      'width',
      'check_cookie',
      'expire',
      'delay',
      'close_delay',
      'cover_opacity',
      'style',
      'visibility',
      'visibility_pages',
    ];

    $config = $this->config('popup_message.settings');
    foreach ($keys as $key) {
      $value = $form_state->getValue('popup_message_' . $key);
      if ($config->get($key) != $value) {
        $save = TRUE;
        $config->set($key, $value);

        if ($key === 'style') {
          $flush_cache_css = TRUE;
        }
        elseif ($key === 'check_cookie') {
          $flush_cache_js = TRUE;
        }
      }
    }

    $text = $form_state->getValue('popup_message_body')['value'];
    $uuids = $this->extractFilesUuid($text);
    $this->recordFileUsage($uuids);

    if ($save) {
      $config->save();
      $this->cacheTagsInvalidator->invalidateTags(['rendered']);
    }

    if ($flush_cache_css) {
      $this->cssOptimizer->deleteAll();
      $this->cacheTagsInvalidator->invalidateTags(['library_info']);
    }
    if ($flush_cache_js) {
      $this->jsOptimizer->deleteAll();
      $this->cacheTagsInvalidator->invalidateTags(['library_info']);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'popup_message.settings',
    ];
  }

  /**
   * Parse an HTML snippet for any linked file with data-entity-uuid attributes.
   *
   * @param string $text
   *   The partial (X)HTML snippet to load. Invalid markup will be corrected on
   *   import.
   *
   * @return array
   *   An array of all found UUIDs.
   */
  protected function extractFilesUuid(string $text): array {
    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);
    $uuids = [];
    foreach ($xpath->query('//*[@data-entity-type="file" and @data-entity-uuid]') as $file) {
      // @phpstan-ignore method.notFound (method exists in DOMNodeList)
      $uuids[] = $file->getAttribute('data-entity-uuid');
    }

    return $uuids;
  }

  /**
   * Records file usage of files referenced by formatted text fields.
   *
   * Every referenced file that does not yet have the FILE_STATUS_PERMANENT
   * state, will be given that state.
   *
   * @param array $uuids
   *   An array of file entity UUIDs.
   */
  protected function recordFileUsage(array $uuids): void {
    try {
      foreach ($uuids as $uuid) {
        if ($file = $this->entityRepository->loadEntityByUuid('file', $uuid)) {
          /** @var \Drupal\file\FileInterface $file */
          if (!$file->isPermanent()) {
            $file->setPermanent();
            $file->save();
          }
        }
      }
    }
    catch (EntityStorageException $exception) {
      $this->logger('popup_message')->warning($exception->getMessage());
    }
  }

}
