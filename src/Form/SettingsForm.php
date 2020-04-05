<?php

declare(strict_types=1);

namespace Drupal\webauthn\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Module settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'webauthn.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webauthn_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('webauthn.settings');
    $form['replace_registration_form'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Replace registration form'),
      '#description' => $this->t('If checked Drupal default registration form will be replaced.'),
      '#default_value' => $config->get('replace_registration_form'),
    ];
    $form['replace_login_form'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Replace login form'),
      '#description' => $this->t('If checked Drupal default login form will be replaced.'),
      '#default_value' => $config->get('replace_login_form'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('webauthn.settings')
      ->set('replace_registration_form', $form_state->getValue('replace_registration_form'))
      ->set('replace_login_form', $form_state->getValue('replace_login_form'))
      ->save();
  }

}
