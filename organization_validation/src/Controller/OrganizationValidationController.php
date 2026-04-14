<?php

namespace Drupal\organization_validation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\organization_validation\Helper\OrganizationValidationHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Component\Serialization\Json;

/**
* Controller for checking organisation after verification.
*/
class OrganizationValidationController extends ControllerBase
{
  public function checkOrganisation()
  {
    $current_user = \Drupal::currentUser();

    if ($current_user->isAnonymous()) {
      // Redirect to login page with a destination to return to the current page after login
      $current_path = \Drupal::service('path.current')->getPath(); // Get the current path
      $login_url = Url::fromRoute('user.login', [], ['query' => ['destination' => $current_path]])->toString();
      return new RedirectResponse($login_url);
    }

    $account = User::load($current_user->id());
    if (!$account) {
      return ['#markup' => $this->t('User not found.')];
    }

    // ✅ Extract user details
    $email = $account->getEmail();
    $country = $account->get('field_organisation_country')->value ?? 'Unknown';
    $country_label = $account->get('field_organisation_country')->
     getFieldDefinition()->getSetting('allowed_values')[$country] ?? $country;
    $web_address = $account->get('field_org_web_address')->value ?? 'N/A';
    $org_name = $account->get('field_organization_name')->value ?? 'N/A';

    $hasRequestOwnership = $account->get('field_has_requested_ownership')->value;

    // ✅ User Details Section
    $user_details_section = [
      '#type' => 'container',
      '#attributes' => ['class' => ['user-details-section']],
      'content' => [
        '#markup' => "
          <div class='user-info-box'>
            <h3>{$this->t('User and organisation information provided')}</h3>
            <p><strong>{$this->t('Organisation name:')}</strong> {$org_name}</p>
            <p><strong>{$this->t('Email:')}</strong> {$email}</p>
            <p><strong>{$this->t('Country:')}</strong> {$this->t($country_label)}</p>
            <p><strong>{$this->t('Website:')}</strong> {$web_address}</p>
          </div>
        ",
      ],
    ];

    // ✅ Organisation Check
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'organisation')
      ->condition('field_organisation_owners', $account->id(), 'IN')
      ->accessCheck(true);

    $owned_organisation_ids = $query->execute();
    $own_organisation = !empty($owned_organisation_ids) ? Node::load(reset($owned_organisation_ids)) : null;
    $matching_organisations = !$own_organisation ?
     OrganizationValidationHelper::findMatchingOrganisations($account) : [];

    $organisation_actions_section = [
      '#type' => 'container',
      '#attributes' => ['class' => ['organisation-actions-section']],
    ];

    // ✅ Create Organisation Button (Top Right)
    $create_button = [
      '#type' => 'link',
      '#title' => $this->t('Create organisation'),
      '#url' => \Drupal\Core\Url::fromUserInput('/form/organisation-registry'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
        'style' => 'float: right; margin-bottom: 10px;'
      ],
    ];

    if ($own_organisation) {
      $organisation_title = $own_organisation->getTitle();
      $organisation_country = $own_organisation->get('field_country')->value ?? 'Unknown';
      $organisation_submission_id = $own_organisation->get('field_submission_id')->value ?? null;

      $organisation_actions_section['content'] = [
        '#markup' => "
          <h3>{$this->t('Your Organisation')}</h3>
          <p><strong>{$this->t('Organisation:')}</strong> {$organisation_title}</p>
          <p><strong>{$this->t('Country:')}</strong> {$this->t($organisation_country)}</p>
          <p><strong>{$this->t('You have been assigned as the editor of the organisation’s profile.')}</strong></p>
        ",
      ];

      $edit_url = \Drupal\Core\Url::fromUserInput(
        "/webform/organisation_registry/submissions/{$organisation_submission_id}/edit"
      );
      $organisation_actions_section['edit_button'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit Organisation'),
        '#url' => $edit_url,
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
    } elseif ($hasRequestOwnership) {
      $organisation_actions_section['content'] = [
        '#markup' => "
          <p><strong>{$this->t('This organisation has already been registered by another user. They have been notified and must accept your request to manage the organisation’s profile. You will receive a notification once your request is accepted.<br><br>If you do not receive a notification within a few days, please contact the administrators at registry@edch.eu.')}</strong></p>
        ",
      ];
    } elseif (!empty($matching_organisations)) {
      $table_rows = [];

      foreach ($matching_organisations as $match) {
        if ($match['node'] instanceof Node) {
          $organisation = $match['node'];
          $name = $organisation->get('field_ipsp_name')->value ??
            $organisation->get('field_ipsp_name_en')->value ?? 'N/A';
          $email = $organisation->get('field_ipsp_contact_email')->value ?? 'N/A';
          $website = $organisation->get('field_ipsp_website_url')->first()->getValue()['uri'] ?? 'N/A';
          $country = $organisation->get('field_country')->value ?? 'N/A';
          $org_type = $match['type'];

          // ✅ Add the organisation as an option in the selection list
          $organisation_options[$organisation->id()] = $name;

          // ✅ Determine action buttons
          $action_buttons = [];
          $rendered_action_button = '';
          if ($org_type === 'organisations') {
            $edit_url = OrganizationValidationHelper::generateFormUrl($match['node']);
            // Organisation from bucket: Show Claim button
            $claim_button = [
              '#type' => 'link',
              '#title' => $this->t('Claim Organisation'),
              '#url' => \Drupal\Core\Url::fromUserInput($edit_url),
              '#attributes' => ['class' => ['button', 'button--primary'], 'style' => ['display', 'none']],
            ];

            $rendered_action_button = \Drupal::service('renderer')->render($claim_button);
          } elseif ($org_type === 'organisation') {
            // Organisation of type "organisation": Show Request Ownership button
            $request_ownership_button = [
              '#type' => 'link',
              '#title' => $this->t('Request Ownership'),
              'form' => \Drupal::formBuilder()->getForm(
                '\Drupal\organization_validation\Form\RequestOwnershipForm',
                $organisation->id(),
                $account->id()
              ),
              '#attributes' => ['class' => ['button', 'button--primary'],
               'style' => ['display:', 'none']],
            ];

            $rendered_action_button = \Drupal::service('renderer')->render($request_ownership_button);
          }

          $org_id = $organisation->id();

          // ✅ Render the action buttons
          $radio_button = [
            '#type' => 'html_tag',
            '#tag' => 'input',
            '#attributes' => [
              'type' => 'radio',
              'name' => 'selected_organisation',
              'value' => "$org_id^$org_type",
              'class' => ['organisation-radio'],
              // 'onclick' => 'toggleRadio(this)',
            ],
          ];

          \Drupal::logger('organization_validation_debug')->debug($website);

          $table_rows[] = [
            \Drupal::service('renderer')->render($radio_button), // Expand Arrow
            $name,
            $email,
            [
              'data' => filter_var($website, FILTER_VALIDATE_URL) ? [
                '#type' => 'link',
                '#title' => $website,
                '#url' => \Drupal\Core\Url::fromUri($website),
                '#attributes' => ['target' => '_blank'],
              ] : $website, // If invalid, show plain text
            ],
            $country,
            // $org_type,
          ];
        }
      }

      $organisation_actions_section['content'] = [
        '#markup' => '<h3>' . $this->t('Matching Organisations') . '</h3>',
      ];

      // ✅ Hidden Action Button (Appears when an organisation is selected)
      $action_button = [
        '#type' => 'button',
        '#value' => $this->t('Proceed with Selected Organisation'),
        // '#url' => \Drupal\Core\Url::fromUserInput('/path/to/action'),
        '#attributes' => [
          'class' => ['button', 'button--primary', 'action-button-2'],
          'style' => 'float: right; margin-bottom: 10px; display: none;', // Initially hidden
          'onclick' => 'submitSelectedOrganisation()', // Call JS function
        ],
      ];

      // ✅ Wrap Table and Button in a Flex Container
      $organisation_actions_section['table_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['table-header-container']],
        'buttons' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['organisation-buttons']],
          'create_button' => $create_button,
          'action_button' => $action_button, // Add the hidden button here
        ],
        'table' => [
          '#type' => 'table',
          '#header' => [
            $this->t(''),
            // $this->t('Select'),
            $this->t('Name'),
            $this->t('Email'),
            $this->t('Website'),
            $this->t('Country'),
            // $this->t('Type'),
          ],
          '#rows' => $table_rows,
          '#attributes' => ['class' => ['organisation-table']],
        ],
      ];
    } else {
      $organisation_actions_section['content'] = [
        '#markup' => '<h3>' . $this->t('No matches found in the Registry database. Please create your organisation’s profile.') . '</h3>',
        'buttons' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['organisation-buttons']],
          'create_button' => $create_button,
        ],
      ];
    }

    // ✅ Add JavaScript for toggling child rows and deselecting radio buttons
    $organisation_actions_section['#attached']['library'][] = 'organization_validation/toggle_rows';

    return [
      'user_details' => $user_details_section,
      'spacer' => ['#markup' => '<hr />'],
      'organisation_actions' => $organisation_actions_section,
    ];
  }

  public function manageSelectedOrganisation(Request $request)
  {
    $current_user = \Drupal::currentUser();

    if ($current_user->isAnonymous()) {
      // Redirect to login page with a destination to return to the current page after login
      $current_path = \Drupal::service('path.current')->getPath(); // Get the current path
      $login_url = Url::fromRoute('user.login', [], ['query' => ['destination' => $current_path]])->toString();
      return new RedirectResponse($login_url);
    }

    $account = User::load($current_user->id());
    if (!$account) {
      return ['#markup' => $this->t('User not found.')];
    }

    // ✅ Retrieve data from AJAX request
    $org_data = $request->request->get('organisation_data');

    if (!$org_data || !strpos($org_data, '^')) {
      return new JsonResponse(['error' => 'Invalid organisation data'], 400);
    }

    // ✅ Split into two parts: ID and Type
    list($organisation_id, $organisation_type) = explode('^', $org_data, 2);

    // ✅ Ensure extracted values are valid
    if (!is_numeric($organisation_id) || empty($organisation_type)) {
      return new JsonResponse(['error' => 'Invalid format'], 400);
    }

    // ✅ Load the node to get the actual content type
    $node = Node::load($organisation_id);
    if (!$node) {
      return new JsonResponse(['error' => 'Organisation not found'], 404);
    }

    // ✅ Get the actual content type
    $content_type = $node->bundle(); // Returns 'organisations' or 'organisation'

    // ✅ If type is "organisations", generate a redirect link
    if ($content_type === 'organisations') {
      $edit_url = OrganizationValidationHelper::generateFormUrl($node);
      return new JsonResponse(['redirect' => $edit_url]);
    }

    // ✅ If type is "organisation", call requestOwnership and redirect back
    if ($content_type === 'organisation') {
      $ownership_result = $this->requestOwnership($organisation_id);

      if ($ownership_result instanceof RedirectResponse) {
        return $ownership_result; // Redirects if necessary
      }

      // ✅ Redirect back to the same page after processing ownership request
      return new JsonResponse(['redirect' => $_SERVER['HTTP_REFERER'] ?? '/']);
    }

    // ✅ If no match, return error
    return new JsonResponse(['error' => 'Invalid content type'], 400);
  }

  /**
  * Handles the "Yes" action for requesting ownership.
  */
  public function requestOwnership($organisation_id)
  {
    $current_user = \Drupal::currentUser();

    if ($current_user->isAnonymous()) {
      // Redirect to login page with a destination to return to the current page after login
      $current_path = \Drupal::service('path.current')->getPath(); // Get the current path
      $login_url = Url::fromRoute('user.login', [], ['query' => ['destination' => $current_path]])->toString();
      return new RedirectResponse($login_url);
    }

    $account = User::load($current_user->id());
    if (!$account) {
      return new JsonResponse(['error' => 'Invalid user data'], 400);
    }

    $user_id = $current_user->id();

    $organisation = Node::load($organisation_id);
    $user = User::load($user_id);

    if (!$organisation || !$user) {
      \Drupal::logger('organization_validation')->error('Ownership request failed: Organisation or User not found.');
      return;
    }

    OrganizationValidationHelper::sendOwnershipRequest($organisation, $user);
  }


  /**
  * Approves an ownership request for an organisation.
  */
  public function approveOwnership($organisation, $user)
  {
    $current_user = \Drupal::currentUser();

    if ($current_user->isAnonymous()) {
      // Redirect to login page with a destination to return to the current page after login
      $current_path = \Drupal::service('path.current')->getPath(); // Get the current path
      $login_url = Url::fromRoute('user.login', [], ['query' => ['destination' => $current_path]])->toString();
      return new RedirectResponse($login_url);
    }

    $organisation = Node::load($organisation);
    $user = User::load($user);
    $current_user = \Drupal::currentUser();
    $current_user_entity = User::load($current_user->id());

    if (!$organisation || !$user || !$current_user_entity) {
      return ['#markup' => $this->t('Invalid organisation or user.')];
    }

    // ✅ Retrieve current owners of the organisation.
    $existing_owners = $organisation->get('field_organisation_owners')->getValue();
    $owner_ids = array_column($existing_owners, 'target_id');

    if (!empty($owner_ids)) {
      // Load all user entities at once (better performance)
      $all_owners = User::loadMultiple($owner_ids);
    }

    // ✅ Ensure the logged-in user is an existing owner or admin.
    if (!in_array($current_user->id(), $owner_ids) && !$current_user->hasPermission('administer nodes')) {
      return ['#markup' => $this->t('Access Denied. Only existing organisation owners or administrators can approve ownership requests.')];
    }

    // ✅ If the requesting user is not already an owner, add them.
    if (!in_array($user->id(), $owner_ids)) {
      $existing_owners[] = ['target_id' => $user->id()];
      $organisation->set('field_organisation_owners', $existing_owners);

      // ✅ Generate the Check Organisation URL
      $check_organisation_url = \Drupal::service('url_generator')->generateFromRoute(
        'organization_validation.check_organisation',
        [],
        ['absolute' => true]
      );

      \Drupal::logger('organization_validation')->debug('Before sending ownership_approved emai');

      $operasAdminsUids = \Drupal::entityQuery('user')
        ->accessCheck(false)
        ->condition('status', 1)
        ->condition('field_operas_admin', 1)
        ->execute();

      $operasAdminsUsers = User::loadMultiple($operasAdminsUids);
      foreach ($operasAdminsUsers as $operasAdminsUser) {
        $operasAdminUserEmails[] = $operasAdminsUser->getEmail();
      }

      \Drupal::logger('organization_validation')->debug('Check array @operasAdminUserEmails Params: @params', [
        '@params' => Json::encode($operasAdminUserEmails),
      ]);

      // ✅ Use centralized email function to notify the user
      $paramsUser = [
        'organisation_name' => $organisation->getTitle(),
        'edit_organisation_link' => $check_organisation_url,
        'full_name' => $user->get('field_full_name')->value ?? 'N/A',
        'actionOwnerFullName' => $current_user_entity->get('field_full_name')->value ?? 'N/A',
        'actionOwnerEmail' => $current_user_entity->getEmail(),
      ];

      // Step 2: Copy $paramsUser to $paramsOwners
      $paramsOwners = $paramsUser;

      // Step 3: Add 'newOwnerEmail' to $paramsOwners
      $paramsOwners['newOwnerEmail'] = $user->getEmail();

      // Step 4: Add headers to $paramsUser
      $paramsUser['headers'] = [
        'Bcc' => implode(',', $operasAdminUserEmails),
      ];

      \Drupal::logger('organization_validation')->debug('Check arra before sending ownership_approved email Params: @params', [
        '@params' => Json::encode($paramsUser),
      ]);

      // $paramsOwners = $paramsUser;
      // $paramsOwners['newOwnerEmail'] = $user->getEmail();

      \Drupal::service('plugin.manager.mail')->mail(
        'organization_validation',  // Module name
        'ownership_approved',       // Email key
        $user->getEmail(),          // Recipient email
        \Drupal::languageManager()->getDefaultLanguage()->getId(), // Language code
        $paramsUser,                    // Email parameters
        null,                       // Send from default site email
        true                        // Enable HTML emails
      );

      foreach ($all_owners as $owner) {
        \Drupal::logger('organization_validation')->debug('Owner: ' . json_encode($owner, JSON_PRETTY_PRINT));

        if ($owner && !empty($owner->getEmail())) {  // Ensure the owner has an email
          \Drupal::logger('organization_validation')->debug('Owner email: ' . $owner->getEmail());

          $paramsOwners['owner_full_name'] = $owner->get('field_full_name')->value ?? 'N/A';

          \Drupal::service('plugin.manager.mail')->mail(
            'organization_validation',
            'ownership_approved_notification_to_owners',
            $owner->getEmail(),
            \Drupal::languageManager()->getDefaultLanguage()->getId(),
            $paramsOwners,
            null,
            true
          );
        }
      }

      // ✅ Notify the approving owner.
      \Drupal::messenger()->addStatus($this->t('User @user has been added as an owner of @organisation.', [
        '@user' => $user->getDisplayName(),
        '@organisation' => $organisation->getTitle(),
      ]));

      // ✅ Log the approval.
      \Drupal::logger('organization_validation')->notice(
        'User @user has been approved as an owner of Organisation @organisation by @approver.',
        [
         '@user' => $user->getDisplayName(),
         '@organisation' => $organisation->getTitle(),
         '@approver' => $current_user_entity->getDisplayName(),
        ]
      );

      $user->set('field_has_requested_ownership', false);
      $user->save();

      $organisation->save();
    }

    //return new RedirectResponse('/node/' . $organisation->id());
    return $this->redirect('organization_validation.confirmation_page', [], [
     'query' => ['message' => $this->t(
       'You have successfully approved the ownership request for %organisation.',
       ['%organisation' => $organisation->getTitle()]
     )],
    ]);
  }


  /**
  * Confirms the user’s association with an existing organisation.
  */
  public function confirmOrganisation($organisation, $user)
  {
    $account = User::load($user);
    if ($account) {
      $account->set('field_organisation', $organisation);
      $account->save();
    }
    return new RedirectResponse('/user/' . $user);
  }

  /**
  * Redirects the user to create a new organisation.
  */
  public function createOrganisation($user)
  {
    return new RedirectResponse('/node/add/organisation?field_ipsp_name=' . urlencode(
      User::load($user)->get('field_organization_name')->value
    ));
  }
}

// /**
  //  * Handles the "Yes" action for requesting ownership.
  //  */
  // public function requestOwnership($form, \Drupal\Core\Form\FormStateInterface $form_state) {

  //     $organisation_id = $form_state->getTriggeringElement()['#attributes']['data-organisation'];
  //     $user_id = $form_state->getTriggeringElement()['#attributes']['data-user'];

  //     $organisation = Node::load($organisation_id);
  //     $user = User::load($user_id);

  //     if (!$organisation || !$user) {
  //         \Drupal::logger('organization_validation')->error('Ownership request failed: Organisation or User not found.');
  //         return;
  //     }

  //     OrganizationValidationHelper::sendOwnershipRequest($organisation, $user);

  //     $form_state->setRedirect('organization_validation.confirmation_page', [], [
  //         'query' => ['message' => $this->t('Your request for ownership has been sent to the organisation owners.')],
  //     ]);
  //     // \Drupal::messenger()->addStatus($this->t('Your request for ownership has been sent to the organisation owners.'));
  //     // $form_state->setRedirect('user.page');
  // }

/**
  * Verifies the organisational email for a user.
  */
  // public function verifyOrgEmail($uid, $timestamp, $hash) {
  //     // Load the user.
  //     $account = User::load($uid);
  //     if (!$account) {
  //         \Drupal::logger('organization_validation')->error('Invalid user ID for organisation email confirmation: @uid', ['@uid' => $uid]);
  //         return ['#markup' => $this->t('Invalid confirmation link.')];
  //     }

  //     // Validate the hash.
  //     if (!user_pass_rehash($account, $timestamp) === $hash) {
  //         \Drupal::logger('organization_validation')->error('Invalid hash for organisation email confirmation for user: @uid', ['@uid' => $uid]);
  //         return ['#markup' => $this->t('Invalid confirmation link.')];
  //     }

  //     // ✅ Mark the organisational email as verified.
  //     $account->set('field_org_email_verified', 1);
  //     $account->save();

  //     // ✅ Send notification email to all admins.
  //     $this->notifyAdmins($account);

  //     // ✅ Redirect to a confirmation page.
  //     // \Drupal::messenger()->addStatus($this->t('Your organisational email has been confirmed. An admin has been notified for review.'));
  //     // return new RedirectResponse('/user/' . $uid . '/edit');
  //     return $this->redirect('organization_validation.confirmation_page', [], [
  //         'query' => ['message' => $this->t('Your organisational email has been confirmed. An admin has been notified for review.')],
  //     ])->send();
  // }

  /**
  * Displays the admin verification page for a user.
  */
  // public function adminVerifyUser(User $user) {
  //     if (!$user) {
  //         return ['#markup' => $this->t('User not found.')];
  //     }

  //     // ✅ Get user details
  //     $user_details = [
  //         'Full Name' => $user->getDisplayName(),
  //         'Email' => $user->getEmail(),
  //         'Organisational Email' => $user->get('field_organisational_email')->value ?? 'Not provided',
  //         'Organisation Name' => $user->get('field_organization_name')->value ?? 'Not provided',
  //         'Verified' => $user->get('field_is_verified')->value ? $this->t('Yes') : $this->t('No'),
  //     ];

  //     // ✅ Check if the user matches an existing organisation
  //     $organisation_name = $user->get('field_organization_name')->value ?? '';
  //     $organisation_email = $user->get('field_organisational_email')->value ?? '';
  //     // $organisation = OrganizationValidationHelper::findMatchingOrganisation($organisation_name, $organisation_email);
  //     $organisations = OrganizationValidationHelper::findMatchingOrganisations($user);

  //     if (!$organisation) {
  //         $organisation_details = ['Message' => $this->t('No matching organisation found.')];
  //     } else {
  //         // ✅ Fetch additional organisation details
  //         $organisation_details = [
  //             'Title' => $organisation->getTitle(),
  //             'Country' => $organisation->get('field_country')->value ?? 'Unknown',
  //             'Website' => $organisation->get('field_ipsp_website_url')->value ?? 'Not available',
  //             'Contact Email' => $organisation->get('field_ipsp_contact_email')->value ?? 'Not available',
  //         ];

  //         // ✅ Fetch organisation owners
  //         $organisation_owners = [];
  //         $owners = $organisation->get('field_organisation_owners')->getValue();
  //         if (!empty($owners)) {
  //             foreach ($owners as $owner) {
  //                 $owner_user = User::load($owner['target_id']);
  //                 if ($owner_user) {
  //                     $organisation_owners[] = $owner_user->getDisplayName() . ' (' . $owner_user->getEmail() . ')';
  //                 }
  //             }
  //         } else {
  //             $organisation_owners[] = $this->t('No owners assigned.');
  //         }

  //         $organisation_details['Owners'] = implode(', ', $organisation_owners);
  //     }

  //     // ✅ Render the admin verification page
  //     return [
  //         '#title' => $this->t('Admin User Verification'),
  //         '#type' => 'container',
  //         'user_info' => [
  //             '#markup' => '<h3>' . $this->t('User Details') . '</h3>',
  //         ],
  //         'user_details' => [
  //             '#theme' => 'item_list',
  //             '#items' => array_map(function ($label, $value) {
  //                 return Markup::create("<strong>$label:</strong> $value");
  //             }, array_keys($user_details), $user_details),
  //             '#type' => 'ul',
  //         ],
  //         'organisation_info' => [
  //             '#markup' => '<h3>' . $this->t('Organisation Details') . '</h3>',
  //         ],
  //         'organisation_details' => [
  //             '#theme' => 'item_list',
  //             '#items' => array_map(function ($label, $value) {
  //                 return Markup::create("<strong>$label:</strong> $value");
  //             }, array_keys($organisation_details), $organisation_details),
  //             '#type' => 'ul',
  //         ],
  //         'verification_form' => \Drupal::formBuilder()->getForm('\Drupal\organization_validation\Form\VerifyUserForm', $user->id()),
  //     ];
  // }
