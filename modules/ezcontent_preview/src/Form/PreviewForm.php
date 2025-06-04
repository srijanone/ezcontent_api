<?php

namespace Drupal\ezcontent_preview\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the Preview add and edit forms.
 */
class PreviewForm extends EntityForm {

  /**
   * Constructs an PreviewForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $preview = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $preview->label,
      '#description' => $this->t("Label for this decoupled URL."),
      '#required' => TRUE,
    ];
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#maxlength' => 255,
      '#default_value' => $preview->url,
      '#description' => $this->t("Add decoupled URL, use absolute URL even for Drupal preview, you can add token from below."),
      '#required' => TRUE,
    ];
    $form['token_tree'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['node'],
      '#show_restricted' => TRUE,
      '#global_types' => TRUE,
      '#click_insert' => TRUE,
    ];
    $form['token_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token time'),
      '#maxlength' => 10,
      '#default_value' => $preview->token_time,
      '#description' => $this->t("Time in seconds to keep the 'Access Unpublished' module's token alive."),
      '#required' => TRUE,
    ];

    $nodeTypes = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    // If you need to display them in a drop down:
    $nodeOptions = [];
    foreach ($nodeTypes as $nodeType) {
      $nodeOptions[$nodeType->id()] = $nodeType->label();
    }
    $form['content_entity'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types'),
      '#options' => $nodeOptions,
      '#default_value' => $preview->content_entity ? $preview->content_entity : [],
      '#description' => $this->t("Check the content type for decoupled preview"),
      '#required' => FALSE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $preview->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
      ],
      '#disabled' => !$preview->isNew(),
    ];
    // You will need additional form elements for your custom properties.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $preview = $this->entity;
    $status = $preview->save();
    if ($status) {
      $this->messenger()->addMessage($this->t('%label saved.', [
        '%label' => $preview->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label was not saved.', [
        '%label' => $preview->label(),
      ]), MessengerInterface::TYPE_ERROR);
    }
    $form_state->setRedirect('entity.ezcontent_preview.collection');
  }

  /**
   * Helper function to check whether an Preview configuration entity exists.
   *
   * @param int $id
   *   Preview on the basis of id.
   */
  public function exist($id) {
    $entity = $this->entityTypeManager->getStorage('ezcontent_preview')->getQuery()
      ->condition('id', $id)
      ->accessCheck(FALSE)
      ->execute();
    return (bool) $entity;
  }

}
