<?php

namespace Drupal\ezcontent_preview\Plugin\views\filter;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Views;

/**
 * Access Unpublished Token.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("published_access_unpublished_token")
 */
class AccessUnpublishedToken extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->valueTitle = $this->t('Published or Unpublish Access Token');
  }

  /**
   * Overriding to stop filtering when no options selected.
   */
  public function query() {
    // Build query and join tables.
    $configuration = [
      'table' => "node",
      'field' => 'nid',
      'left_table' => 'node_field_data',
      'left_field' => 'nid',
      'operator' => '=',
    ];
    $join = Views::pluginManager('join')->createInstance('standard', $configuration);
    $this->query->addRelationship('node', $join, 'node_field_data');

    // Default value for snippet.
    $snippet = "node_field_data.status = 1";

    $tokenNid = $this->checkTokenAuthNid();
    if ($tokenNid) {
      $snippet = "node_field_data.status = 1 OR node.nid = " . $tokenNid;
    }
    $this->query->addWhereExpression($this->options['group'], $snippet);
  }

  /**
   * Checking the token auth.
   */
  public function checkTokenAuthNid() {
    $tokenKey = \Drupal::config('access_unpublished.settings')->get('hash_key');
    if (\Drupal::request()->query->has($tokenKey)) {
      $storage = \Drupal::entityTypeManager()->getStorage('access_token');
      $object = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('value', \Drupal::request()->get($tokenKey))
        ->execute();
      if ($object) {
        $node = $storage->load(current($object));
        $nid = $node->get('entity_id')->value;
        return $nid;
      }
    }
    return FALSE;
  }

}
