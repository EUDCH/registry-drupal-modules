<?php

namespace Drupal\organization_validation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Organisation Confirmation Form.
 */
class ConfirmOrganisationForm extends FormBase
{
    /**
    * {@inheritdoc}
    */
    public function getFormId()
    {
        return 'confirm_organisation_form';
    }

    /**
    * {@inheritdoc}
    */
    public function buildForm(array $form, FormStateInterface $form_state, $org_id = null)
    {
        $form['org_id'] = [
          '#type' => 'hidden',
          '#value' => $org_id,
        ];

        $form['confirmation'] = [
          '#type' => 'radios',
          '#title' => t('Is this your organisation?'),
          '#options' => [
            'yes' => t('Yes'),
            'no' => t('No'),
          ],
          '#required' => true,
        ];

        $form['submit'] = [
          '#type' => 'submit',
          '#value' => t('Continue'),
        ];

        return $form;
    }

    /**
    * {@inheritdoc}
    */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $org_id = $form_state->getValue('org_id');
        $confirmation = $form_state->getValue('confirmation');

        if ($confirmation === 'yes') {
            // Redirect user to organisation page.
            $org_url = \Drupal::service('entity_type.manager')
              ->getStorage('node')
              ->load($org_id)
              ->toUrl()
              ->toString();

            $response = new RedirectResponse($org_url);
            $response->send();
            exit();
        }

        // Redirect back to user registration completion.
        $form_state->setRedirect('organization_validation.thank_you_page');
    }
}
