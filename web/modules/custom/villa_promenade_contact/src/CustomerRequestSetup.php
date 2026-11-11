<?php

namespace Drupal\villa_promenade_contact;

use Drupal\Core\Config\FileStorage;
use Drupal\views\Entity\View;

/**
 * Installs the Customer request content structure without copying content.
 */
final class CustomerRequestSetup {

  public const FIELDS = ['email', 'event', 'request_status', 'details', 'admin_notes', 'is_demo', 'planned_date_sort', 'real_date', 'approve_note'];

  /**
   * Installs missing configuration without overwriting field UI changes.
   */
  public static function configure(): void {
    $path = \Drupal::service('extension.list.module')->getPath('villa_promenade_contact');
    $source = new FileStorage($path . '/config/install');
    $entities = ['node_type' => ['node.type.customer_request']];
    foreach (self::FIELDS as $field) {
      $entities['field_storage_config'][] = 'field.storage.node.' . $field;
      $entities['field_config'][] = 'field.field.node.customer_request.' . $field;
    }
    foreach (['title', 'status', 'promote'] as $field) {
      $entities['base_field_override'][] = 'core.base_field_override.node.customer_request.' . $field;
    }
    $entities['entity_form_display'] = ['core.entity_form_display.node.customer_request.default'];
    $entities['entity_view_display'] = ['core.entity_view_display.node.customer_request.default'];
    foreach ($entities as $entity_type => $names) {
      $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
      foreach ($names as $name) {
        $data = $source->read($name);
        if (!$data) {
          throw new \RuntimeException('Missing customer request configuration: ' . $name);
        }
        $id = $data['id'] ?? $data['type'];
        if ($storage->load($id)) {
          continue;
        }
        if ($entity_type === 'field_storage_config' && isset($data['settings']['allowed_values'])) {
          $allowed = [];
          foreach ($data['settings']['allowed_values'] as $option) {
            $allowed[$option['value']] = $option['label'];
          }
          $data['settings']['allowed_values'] = $allowed;
        }
        $storage->create($data)->save();
      }
    }
    CustomerRequestAnswers::configure();
  }

  /**
   * Switches an existing legacy View to the bundled node-based configuration.
   */
  public static function configureView(): void {
    $view = View::load('villa_customer_requests');
    if ($view && $view->get('base_table') === 'node_field_data') {
      return;
    }
    $path = \Drupal::service('extension.list.module')->getPath('villa_promenade_contact');
    $data = (new FileStorage($path . '/config/install'))->read('views.view.villa_customer_requests');
    if (!$view) {
      View::create($data)->save();
      return;
    }
    \Drupal::service('views.views_data')->clear();
    $view->getExecutable()->destroy();
    $view->set('base_table', 'node_field_data')->set('base_field', 'nid')->set('display', $data['display'])->save();
  }

}
