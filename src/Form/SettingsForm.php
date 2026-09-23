<?php

namespace Drupal\daily_progress_tracker\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Daily Progress Tracker settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'daily_progress_tracker_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['daily_progress_tracker.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('daily_progress_tracker.settings');

    $form['default_target_hours'] = [
      '#type' => 'number',
      '#title' => $this->t('Default Target Hours'),
      '#default_value' => $config->get('default_target_hours') ?? 8,
      '#min' => 1,
      '#max' => 24,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('daily_progress_tracker.settings')
      ->set('default_target_hours', $form_state->getValue('default_target_hours'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}