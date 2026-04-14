<?php

namespace Drupal\organization_validation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\organization_validation\Helper\OrganizationValidationHelper;

/**
 * Handles the request ownership form.
 */
class RequestOwnershipForm extends FormBase
{
    /**
     * Returns the form ID.
     */
    public function getFormId()
    {
        return 'request_ownership_form';
    }

    /**
     * Builds the ownership request form.
     */
    public function buildForm(array $form, FormStateInterface $form_state, $organisation_id = null, $user_id = null)
    {
        $form['#attributes']['class'][] = 'button-container';

        $form['organisation_id'] = [
            '#type' => 'hidden',
            '#value' => $organisation_id,
        ];

        $form['user_id'] = [
            '#type' => 'hidden',
            '#value' => $user_id,
        ];

        $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Yes'),
            '#attributes' => ['class' => ['button', 'button--primary']],
        ];

        return $form;
    }

    /**
     * Handles form submission.
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $organisation_id = $form_state->getValue('organisation_id');
        $user_id = $form_state->getValue('user_id');

        if ($organisation_id && $user_id) {
            $organisation = Node::load($organisation_id);
            $user = User::load($user_id);

            if ($organisation && $user) {
                OrganizationValidationHelper::sendOwnershipRequest($organisation, $user);
                \Drupal::messenger()->addStatus($this->t(
                    'Your request for ownership has been sent to the organisation owners.'
                ));
            }
        }

        $form_state->setRedirect('user.page');
    }
}
