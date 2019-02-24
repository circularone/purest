<?php

namespace Drupal\purest;

use Drupal\user\UserInterface;
use Drupal\Core\Form\FormState;

/**
 * Interface PurestNormalizerFormBuilderInterface.
 */
interface PurestNormalizerFormBuilderInterface {

  /**
   * Returns a table with each row providing default config options for an
   * entity field.
   *
   * @param string $entity_type
   *   An entity type id.
   * @param string $bundle
   *   An enitity type bundle - e.g. a node bundle or taxonomy vocabulary.
   * @param array $form
   *   An array of form elements.
   * @param class $form_state
   *   Drupal\Core\Form\FormState.
   * @param array $config
   */
  public function buildEntityFieldsTable($entity_type, $bundle, &$form, FormState $form_state, $config);

  /**
   * Returns a table with each row providing default config options for an
   * entity field.
   *
   *
   * @param array $form
   *   An array of form elements.
   * @param class $form_state
   *   Drupal\Core\Form\FormState.
   * @param array $values
   *   An array of form values.
   * @param string $form_key
   *   The form key.
   * @param string $field_name
   *   A field key.
   * @param array $field_definition.
   */
  public function defaultFieldSettings(&$form, FormState $form_state, $values, $form_key, $field_name, $field_definition);

}
