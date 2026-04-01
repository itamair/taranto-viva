<?php

/**
 * @file
 * Hooks and events provided by the Search API Typesense module.
 */

use Drupal\search_api_typesense\Event\SearchRenderEvent;
use Drupal\search_api_typesense\Render\TypesenseSearchRenderContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Event subscriber example for altering Typesense search render array.
 *
 * This is not a traditional hook but an event that can be subscribed to.
 * Create an event subscriber class to listen for SearchRenderEvent::NAME.
 *
 * @param \Drupal\search_api_typesense\Event\SearchRenderEvent $event
 *   The search render event containing the render array and context.
 *
 * @see \Drupal\search_api_typesense\Event\SearchRenderEvent
 */
function example_search_api_typesense_search_render_event_subscriber(SearchRenderEvent $event) {
  $context = $event->getContext();
  $render_array = $event->getRenderArray();

  // Example 1: Override server configuration for development environment
  if (\Drupal::config('system.site')->get('name') === 'Development Site') {
    $js_settings = $event->getJavaScriptSettings();
    $js_settings['server']['nodes'][0]['host'] = 'localhost';
    $js_settings['server']['nodes'][0]['port'] = '8108';
    $js_settings['server']['nodes'][0]['protocol'] = 'http';
    $event->setJavaScriptSettings($js_settings);
  }

  // Example 2: Add custom JavaScript settings
  $event->mergeJavaScriptSettings([
    'custom_setting' => 'custom_value',
    'environment' => \Drupal::config('system.site')->get('name'),
  ]);

  // Example 3: Add additional CSS or JavaScript libraries
  $render_array = $event->getRenderArray();
  $render_array['content']['#attached']['library'][] = 'my_module/custom_search_styles';
  $event->setRenderArray($render_array);

  // Example 4: Modify theme variables based on context
  if ($context->isDebugEnabled()) {
    $render_array = $event->getRenderArray();
    $render_array['content']['#debug_info'] = [
      'backend' => get_class($context->getBackend()),
      'indexes' => count($context->getIndexes()),
      'api_key' => substr($context->getApiKey(), 0, 8) . '...',
    ];
    $event->setRenderArray($render_array);
  }
}

/**
 * Example event subscriber class.
 *
 * Create a service class like this to subscribe to the SearchRenderEvent:
 */
class ExampleSearchRenderEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchRenderEvent::NAME => ['onSearchRender', 0],
    ];
  }

  /**
   * Reacts to the search render event.
   *
   * @param \Drupal\search_api_typesense\Event\SearchRenderEvent $event
   *   The search render event.
   */
  public function onSearchRender(SearchRenderEvent $event): void {
    // Your customization logic here
    $context = $event->getContext();
    
    // Example: Override server nodes for different environments
    $environment = \Drupal::config('system.site')->get('name');
    if ($environment === 'Development Site') {
      $event->mergeJavaScriptSettings([
        'server' => [
          'nodes' => [
            [
              'host' => 'localhost',
              'port' => '8108',
              'protocol' => 'http',
            ],
          ],
        ],
      ]);
    }
  }

}

/**
 * Example services.yml entry for the event subscriber:
 *
 * my_module.search_render_event_subscriber:
 *   class: Drupal\my_module\EventSubscriber\ExampleSearchRenderEventSubscriber
 *   tags:
 *     - { name: event_subscriber }
 */

/**
 * @} End of "addtogroup hooks".
 */
