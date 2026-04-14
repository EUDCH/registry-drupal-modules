<?php

namespace Drupal\organization_validation\Helper;

use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

class OrganizationValidationHelper
{
    /**
     * ✅ Finds a matching organisations based on user entity
     */
    public static function findMatchingOrganisations($user)
    {
        if (!$user instanceof User) {
            return [];
        }

        $email = $user->getEmail();
        $country = $user->get('field_organisation_country')->value ?? '';
        $country_label = $user->get('field_organisation_country')->getFieldDefinition()->
           getSetting('allowed_values')[$country] ?? $country;
        $web_address = $user->get('field_org_web_address')->value ?? '';
        $org_name = $user->get('field_organization_name')->value ?? '';

        if (empty($email) && empty($country) && empty($web_address)) {
            return [];
        }

        // ✅ Extract email domain
        $email_domain = substr(strrchr($email, '@'), 1);

        // ✅ Search both `organization` and `organisations`
        $types = ['organisation', 'organisations'];
        $matching_organisations = [];

        foreach ($types as $type) {
            $query = \Drupal::entityQuery('node')
                ->condition('type', $type)
                ->accessCheck(false);

            $or_group = $query->orConditionGroup();

            // if (!empty($country)) {
            //     $or_group->condition('field_country', $country, 'LIKE');
            // }

            // ✅ Match Web Address
            if (!empty($web_address)) {
                $or_group->condition('field_ipsp_website_url', '%' . $web_address, 'LIKE');
            }

            // ✅ Match Email Domain against `field_ipsp_contact_email`
            $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $type);
            if (isset($field_definitions['field_ipsp_contact_email'])) {
                $or_group->condition('field_ipsp_contact_email', '%' . $email_domain, 'LIKE');
            }

            // ✅ Match Organisation Name
            if (isset($field_definitions['field_ipsp_name'])) {
                $or_group->condition('field_ipsp_name', '%' . $org_name, 'LIKE');
            }
            if (isset($field_definitions['field_ipsp_name_en'])) {
                $or_group->condition('field_ipsp_name_en', '%' . $org_name, 'LIKE');
            }

            // ✅ Apply OR conditions
            $query->condition($or_group);

            // ✅ Ensure the Country condition is checked separately
            if (!empty($country)) {
                $query->condition('field_country', $country, '=');
            }

            $result = $query->execute();

            if (!empty($result)) {
                foreach ($result as $node_id) {
                    $matching_organisations[] = [
                        'type' => $type,
                        'node' => Node::load($node_id),
                    ];
                }
            }
        }

        return $matching_organisations;
    }

    /**
     * Generates the form URL for a given organisation node.
     */
    public static function generateFormUrl(Node $organisation): string
    {
        \Drupal::logger('custom_module')->notice('Node type: @type', ['@type' => $organisation->getType()]);

        if ($organisation->getType() !== 'organisations') {
            return '';
        }

        $organisation_name = $organisation->get('field_ipsp_name')->value ?? 'Unknown';
        $organization_name_en = $organisation->get('field_ipsp_name_en')->value ?? '';
        $acronym = $organisation->get('field_acronym')->value ?? '';
        $organisation_email_adress = $organisation->get('field_ipsp_contact_email')->value ?? '';
        $website_url = $organisation->get('field_ipsp_website_url')->value ?? '';
        $type = $organisation->get('field_type')->value ?? '';
        $country = $organisation->get('field_country')->value ?? '';
        $city = $organisation->get('field_city')->value ?? '';
        $parent_organisation = $organisation->get('field_parent_organisation')->value ?? '';
        $parent_organisation_official_name = $organisation->get('field_parent_organisation_name')->value ?? '';
        $parent_organisation_official_name_EN = $organisation->get('field_parent_org_name_en')->value ?? '';
        $parent_organisation_acronym = $organisation->get('field_parent_org_acronym')->value ?? '';
        $legal_entity_type = $organisation->get('field_legal_entity_type_of_the_p')->value ?? '';
        $number_of_full_time_equivalent_FTE_paid_staff = $organisation->
           get('field_number_of_full_time_equiva')->value ?? '';
        $geographical_range_of_services = $organisation->get('field_geographical_range_of_serv')->value ?? '';
        $services_urls = $organisation->get('field_link_s_where_the_services')->value ?? '';
        $published_or_supported_output_types = $organisation->get('field_published_or_supported_out')->value ?? '';

        $query_params = [
            'organization_name' => $organisation_name,
            'organization_name_en' => $organization_name_en,
            'acronym' => $acronym,
            'website' => $website_url,
            'organisation_email_adress' => $organisation_email_adress,
            'type' => $type,
            'country' => $country,
            'city' => $city,
            'parent_organisation' => $parent_organisation,
            'parent_organisation_official_name' => $parent_organisation_official_name,
            'parent_organisation_official_name_EN' => $parent_organisation_official_name_EN,
            'parent_organisation_acronym' => $parent_organisation_acronym,
            'legal_entity_type' => $legal_entity_type,
            'number_of_full_time_equivalent_FTE_paid_staff' => $number_of_full_time_equivalent_FTE_paid_staff,
            'geographical_range_of_services' => $geographical_range_of_services,
            'services_urls' =>  $services_urls,
            'published_or_supported_output_types' =>  $published_or_supported_output_types,
        ];

        $query_string = http_build_query($query_params, '', '&', PHP_QUERY_RFC3986);

        \Drupal::logger('custom_module')->notice('Node type: @st', ['@st' => $query_string]);

        $url = \Drupal\Core\Url::fromUserInput('/form/organisation-registry?' . $query_string)->toString();

        \Drupal::logger('custom_module')->notice('Node type: @st', ['@st' => $url]);

        return $url;
    }


    /**
    * Sends an ownership request email to the organisation's owners.
    */
    public static function sendOwnershipRequest(Node $organisation, User $requesting_user)
    {
        $owner_emails = [];

        $owners = $organisation->get('field_organisation_owners')->getValue();
        foreach ($owners as $owner) {
            $owner_user = User::load($owner['target_id']);
            if ($owner_user && $owner_user->getEmail()) {
                $owner_emails[] = $owner_user->getEmail();
            }
        }

        if (empty($owner_emails)) {
            $admin_users = \Drupal::entityTypeManager()
                ->getStorage('user')
                ->loadByProperties([
                    'roles' => 'administrator',
                    'field_operas_admin' => 1,
                ]);

            // ✅ Collect admin email addresses.
            $admin_emails = [];
            foreach ($admin_users as $admin) {
                if (!empty($admin->getEmail())) {
                    $admin_emails[] = $admin->getEmail();
                }
            }

            $owner_emails = $admin_emails;
        }

        if (!empty($owner_emails)) {
            $approval_link = \Drupal::service('url_generator')->generateFromRoute(
                'organization_validation.approve_ownership',
                ['organisation' => $organisation->id(), 'user' => $requesting_user->id()],
                ['absolute' => true]
            );

            $operasAdminsUids = \Drupal::entityQuery('user')
                    ->accessCheck(false)
                    ->condition('status', 1)
                    ->condition('field_operas_admin', 1)
                    ->execute();

            $cc_emails = [];
            if (!empty($operasAdminsUids)) {
                $cc_users = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple($operasAdminsUids);
                foreach ($cc_users as $cc_user) {
                    $cc_emails[] = $cc_user->getEmail();
                }
            }

            // ✅ Use centralized email function
            foreach ($owner_emails as $email) {
                $owner_users = \Drupal::entityTypeManager()
                    ->getStorage('user')
                    ->loadByProperties(['mail' => $email]);

                $owner_user = reset($owner_users); // ✅ Get the first user entity

                // Retrieve additional fields from the requesting user (not the owner)
                $requesting_email = $requesting_user->getEmail();
                $full_name = $requesting_user->get('field_full_name')->value ?? 'N/A';
                //$organisation_country = $requesting_user->get('field_organisation_country')->value ?? 'N/A';
                $country = $requesting_user->get('field_organisation_country')->value ?? '';
                $country_label = $requesting_user->get('field_organisation_country')->
                   getFieldDefinition()->getSetting('allowed_values')[$country] ?? $country;
                $organisation_role = $requesting_user->get('field_organisation_role')->value ?? 'N/A';

                // $params = [
                //     'owner_name' => $owner_user->getDisplayName(),
                //     'user_name' => $requesting_user->getDisplayName(),
                //     'organisation_name' => $organisation->getTitle(),
                //     'approve_ownership_link' => $approval_link,
                // ];

                // Prepare email parameters
                $params = [
                    'owner_name' => $owner_user->getDisplayName(), // The recipient (owner)
                    'owner_full_name' => $owner_user->get('field_full_name')->value ?? 'N/A', // The recipient (owner)
                    'user_name' => $requesting_user->getDisplayName(), // The requesting user
                    'user_email' => $requesting_email, // The requesting user's email
                    'organisation_name' => $organisation->getTitle(),
                    'approve_ownership_link' => $approval_link,
                    'full_name' => $full_name, // Requesting user's full name
                    'organisation_country' => $country_label, // Requesting user's country
                    'organisation_role' => $organisation_role, // Requesting user's role
                    'headers' => [
                        'Cc' => implode(',', $cc_emails),
                    ],
                ];

                \Drupal::service('plugin.manager.mail')->mail(
                    'organization_validation',
                    'ownership_request',
                    $email,
                    \Drupal::languageManager()->getDefaultLanguage()->getId(),
                    $params,
                    null,
                    true
                );
            }

            // ✅ Log the email event
            \Drupal::logger('organization_validation')->
              notice('Ownership request sent to owners of Organisation @organisation for User @user', [
                '@organisation' => $organisation->getTitle(),
                '@user' => $requesting_user->getDisplayName(),
            ]);

            $requesting_user->set('field_has_requested_ownership', true);
            $requesting_user->save(); // ✅ Save the updated user entity
        }

        \Drupal::logger('organization_validation')->notice('Ownership request not send. Owners Empty');
    }

    /**
     * Sends an email notification to all admin users when a new user confirms their organisational email.
     */
    public static function notifyDiamasAdmins(Node $organisation)
    {
        // Get all admin users (users with the "administrator" role).
        $admin_users = \Drupal::entityTypeManager()
            ->getStorage('user')
            ->loadByProperties([
                'roles' => 'administrator',
                'field_operas_admin' => 1,
            ]);

        // ✅ Collect admin email addresses.
        $admin_emails = [];
        foreach ($admin_users as $admin) {
            if (!empty($admin->getEmail())) {
                $admin_emails[] = $admin->getEmail();
            }
        }

        if (!empty($admin_emails)) {
            $orgId = $organisation->id();
            $organisation_name = $organisation->get('field_ipsp_name')->value ?? 'Unknown';
            $organization_name_en = $organisation->get('field_ipsp_name_en')->value ?? '';
            $organisation_email_adress = $organisation->get('field_ipsp_contact_email')->value ?? '';
            // $website_url = $organisation->get('field_ipsp_website_url')->value ?? '';
            $website_url_array = $organisation->get('field_ipsp_website_url')->getValue(); // i.alevizos 18/3/25
            // i.alevizos 18/3/25
            $website_url = isset($website_url_array[0]['uri']) ? $website_url_array[0]['uri'] : '';
            $country = $organisation->get('field_country')->value ?? '';

            // ✅ Generate the user review link for admin.
            // $review_org_url = \Drupal::service('url_generator')->generateFromRoute(
            //     'organization_validation.admin_verify_user',
            //     ['user' => $user->id()],
            //     ['absolute' => TRUE]
            // );

            // \Drupal::logger('organization_validation')->debug('test'. $orgId);

            $review_org_url = \Drupal\Core\Url::fromRoute(
                'entity.node.canonical',
                ['node' => $orgId],
                ['absolute' => true]
            )->toString();

            // ✅ Email parameters with correct data.
            $params = [
                'org_name' => $organisation_name,
                'org_email' => $organisation_email_adress,
                'org_url' => $website_url,
                'org_country' => $country,
                'review_link' => $review_org_url,
            ];

            // ✅ Send email to each admin using the centralized email system.
            foreach ($admin_emails as $admin_email) {
                \Drupal::service('plugin.manager.mail')->mail(
                    'organization_validation',
                    'admin_notification',
                    $admin_email,
                    \Drupal::languageManager()->getDefaultLanguage()->getId(),
                    $params
                );
            }

            // ✅ Log the notification.
            \Drupal::logger('organization_validation')->
                notice('Admin notification sent: Organisation pending review. Org: @name (@email)', [
                '@name' => $organisation_name,
                '@email' => $organisation_email_adress,
            ]);
        }
    }

    /**
     * Sends an email notification to all admin users when a new user confirms their organisational email.
     */
    public static function notifyOwnersForPublish(Node $organisation)
    {
        // ✅ Retrieve current owners of the organisation.
        $existing_owners = $organisation->get('field_organisation_owners')->getValue();
        $owner_ids = array_column($existing_owners, 'target_id');

        if (!empty($owner_ids)) {
            // Load all user entities at once (better performance)
            $all_owners = User::loadMultiple($owner_ids);
        }

        // // ✅ Collect admin email addresses.
        // $owner_emails = [];
        // foreach ($all_owners as $owner) {
        //     if (!empty($owner->getEmail())) {
        //         $owner_emails[] = $owner->getEmail();
        //     }
        // }

        foreach ($all_owners as $owner) {
            if (!empty($owner->getEmail())) {
                $owner_email = $owner->getEmail();
                $fullName = $owner->get('field_full_name')->value ?? 'N/A';
                $orgId = $organisation->id();
                $organisation_name = $organisation->get('field_ipsp_name')->value ?? 'Unknown';
                $organization_name_en = $organisation->get('field_ipsp_name_en')->value ?? '';
                $organisation_email_adress = $organisation->get('field_ipsp_contact_email')->value ?? '';
                // $website_url = $organisation->get('field_ipsp_website_url')->value ?? '';
                $website_url_array = $organisation->get('field_ipsp_website_url')->getValue(); // i.alevizos 18/3/25
                // i.alevizos 18/3/25
                $website_url = isset($website_url_array[0]['uri']) ? $website_url_array[0]['uri'] : '';
                $country = $organisation->get('field_country')->value ?? '';

                $review_org_url = \Drupal\Core\Url::fromRoute(
                    'entity.node.canonical',
                    ['node' => $orgId],
                    ['absolute' => true]
                )->toString();

                // ✅ Email parameters with correct data.
                $params = [
                    'org_name' => $organisation_name,
                    'org_email' => $organisation_email_adress,
                    'org_url' => $website_url,
                    'org_country' => $country,
                    'review_link' => $review_org_url,
                    'full_name' => $fullName,
                ];

                // ✅ Send email to each admin using the centralized email system.
                \Drupal::service('plugin.manager.mail')->mail(
                    'organization_validation',
                    'owner_notification_when_org_published',
                    $owner_email,
                    \Drupal::languageManager()->getDefaultLanguage()->getId(),
                    $params
                );

                // ✅ Log the notification.
                \Drupal::logger('organization_validation')->notice(
                    'Owner notification sent: Organisation is published. Org: @name (@email)',
                    [
                    '@name' => $organisation_name,
                    '@email' => $organisation_email_adress,
                    ]
                );
            }
        }
    }
}

/**
     * ✅ Finds a matching organisation based on name or email.
     */
    // public static function findMatchingOrganisation($name, $email) {
    //     if (empty($name) || empty($email)) {
    //         return NULL;
    //     }

    //     // ✅ Extract the email domain
    //     $email_domain = substr(strrchr($email, '@'), 1);

    //     // ✅ Query for organisations
    //     $query = \Drupal::entityQuery('node')
    //         ->condition('type', 'organisations')
    //         ->accessCheck(FALSE);

    //     $or_group = $query->orConditionGroup();

    //     // ✅ Check exact match with Organisation Name
    //     $or_group->condition('field_ipsp_name', $name, '=');

    //     // ✅ Check domain match against `field_ipsp_website_url` and `field_ipsp_contact_email`
    //     $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'organisations');
    //     if (isset($field_definitions['field_ipsp_website_url'])) {
    //         $or_group->condition('field_ipsp_website_url', '%' . $email_domain, 'LIKE');
    //     }
    //     if (isset($field_definitions['field_ipsp_contact_email'])) {
    //         $or_group->condition('field_ipsp_contact_email', '%' . $email_domain, 'LIKE');
    //     }

    //     $query->condition($or_group);
    //     $result = $query->execute();

    //     return !empty($result) ? Node::load(reset($result)) : NULL;
    // }
