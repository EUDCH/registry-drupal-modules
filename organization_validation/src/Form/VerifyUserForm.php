<?php

namespace Drupal\organization_validation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

/**
 * Provides a form for verifying a user.
 */
class VerifyUserForm extends FormBase
{
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'organization_validation_verify_user_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $user_id = null)
  {
    $user = User::load($user_id);
    if (!$user) {
      return ['#markup' => $this->t('User not found.')];
    }

    $form['user_id'] = [
      '#type' => 'hidden',
      '#value' => $user_id,
    ];

    $form['confirmation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Confirm this user as verified'),
      '#required' => true,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Verify User'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $user_id = $form_state->getValue('user_id');
    $user = User::load($user_id);
    if ($user) {
      $user->set('field_is_verified', 1);
      $user->save();
      \Drupal::messenger()->addStatus($this->t('User has been verified.'));
    }
  }
}
