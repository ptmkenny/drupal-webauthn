<?php

declare(strict_types=1);

namespace Drupal\webauthn\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Module settings form.
 */
class SettingsForm extends ConfigFormBase {

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

    $form['relying_party'] = [
      '#type' => 'details',
      '#title' => $this->t('Relying party'),
      '#open' => TRUE,
      '#tree' => FALSE,
    ];

    $form['relying_party']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name', [], ['context' => 'WebAuthn']),
      '#description' => $this->t('The name for the site under WebAuthn context. Defaults to Drupal site name.', [], ['context' => 'WebAuthn']),
      '#required' => TRUE,
      '#default_value' => $config->get('relying_party.name') ?? $this->config('system.site')
          ->get('name'),
    ];

    $form['relying_party']['id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Id', [], ['context' => 'WebAuthn']),
      '#description' => $this->t('The id for the site under WebAuthn context.', [], ['context' => 'WebAuthn']),
      '#required' => TRUE,
      '#default_value' => $config->get('relying_party.id'),
    ];

    $logo = $config->get('relying_party.logo');
    $form['relying_party']['logo'] = [
      '#type' => 'file',
      '#upload_location' => 'private://',
      '#upload_validators' => [
        'file_validate_extensions' => ['jpg', 'jpeg', 'ico', 'svg', 'png'],
      ],
      '#title' => $this->t('Logo', [], ['context' => 'WebAuthn']),
      '#description' => $this->t('(optional) The logo for the site under WebAuthn context. Is stored using Base64 encoding.', [], ['context' => 'WebAuthn']),
    ];

    if (!empty($logo)) {
      $form_state->set('current_logo', $logo);
      $form['relying_party']['current_logo'] = [
        '#type' => 'html_tag',
        '#tag' => 'img',
        '#attributes' => [
          'src' => $logo,
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $all_files = $this->getRequest()->files->get('files', []);
    if (!empty($all_files['logo'])) {
      $file_upload = $all_files['logo'];
      if ($file_upload->isValid()) {
        $form_state->setValue('logo', $this->fileToBase64($file_upload));
      }
      else {
        $form_state->setErrorByName('logo', $this->t('The file could not be uploaded.'));
      }
    }
  }

  /**
   * Process uploaded file and store it as base64 encoded data.
   *
   * @param \Symfony\Component\HttpFoundation\File\UploadedFile $file_upload
   *   Uploaded file.
   *
   * @return string
   *   The base64 encoded string from file.
   */
  private function fileToBase64(UploadedFile $file_upload): string {
    $data = file_get_contents($file_upload->getRealPath());
    return 'data:' . $file_upload->getClientMimeType() . ';base64,' . base64_encode($data);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $relying_party = [
      'name' => $form_state->getValue('name'),
      'id' => $form_state->getValue('id'),
      'logo' => $form_state->getValue('logo', $form_state->get('current_logo')),
    ];

    $this->config('webauthn.settings')
      ->set('replace_registration_form', $form_state->getValue('replace_registration_form'))
      ->set('replace_login_form', $form_state->getValue('replace_login_form'))
      ->set('relying_party', $relying_party)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'webauthn.settings',
    ];
  }

}
