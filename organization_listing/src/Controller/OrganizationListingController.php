<?php

namespace Drupal\organization_listing\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for listing published organizations.
 */
class OrganizationListingController extends ControllerBase {

  /**
   * List published organizations with search and filter functionality.
   */
  public function listorganizations(Request $request) {
    $connection = Database::getConnection();

    // Get search and filter parameters from the query string.
    $search = $request->query->get('search', '');
    $country_filter = $request->query->get('country', '');

    // Build the query to fetch organizations.
    $query = $connection->select('node_field_data', 'n')
      ->fields('n', ['nid', 'title'])
      ->condition('n.type', 'organizations')
      ->condition('n.status', 1);

    // Join to fetch country and IPSP ID.
    $query->join('node__field_country', 'c', 'c.entity_id = n.nid');
    $query->fields('c', ['field_country_value']);

    $query->join('node__field_ipsp_id', 'ipsp', 'ipsp.entity_id = n.nid');
    $query->fields('ipsp', ['field_ipsp_id_value']);

    // Apply search filter.
    if (!empty($search)) {
      $query->condition('n.title', '%' . Database::getConnection()->escapeLike($search) . '%', 'LIKE');
    }

    // Apply country filter.
    if (!empty($country_filter)) {
      $query->condition('c.field_country_value', $country_filter);
    }

    $query->orderBy('n.title', 'ASC');

    // Execute the query and fetch results.
    $result = $query->execute();
    $organizations = $result->fetchAll();

    // Fetch distinct countries for the filter dropdown.
    $country_query = $connection->select('node__field_country', 'c')
      ->distinct()
      ->fields('c', ['field_country_value'])
      ->orderBy('field_country_value', 'ASC');
    $countries = $country_query->execute()->fetchCol();

    // Build the filter and search form.
    $build['filter_form'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['organization-filter-form']],
      'search' => [
        '#type' => 'textfield',
        '#title' => 'Search',
        '#default_value' => $search,
        '#attributes' => ['placeholder' => 'Search organizations...'],
      ],
      'country' => [
        '#type' => 'select',
        '#title' => 'Country',
        '#options' => ['' => '- All Countries -'] + array_combine($countries, $countries),
        '#default_value' => $country_filter,
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => 'Filter',
        '#attributes' => ['onclick' => 'this.form.submit();'],
      ],
    ];

    // Build the table of organizations.
    $rows = [];
    foreach ($organizations as $organization) {
      $rows[] = [
        'data' => [
          [
            'data' => [
              '#type' => 'link',
              '#title' => $organization->title,
              '#url' => Url::fromRoute('entity.node.canonical', ['node' => $organization->nid]),
            ],
          ],
          $organization->field_ipsp_id_value,
          $organization->field_country_value,
        ],
      ];
    }

    $build['organization_table'] = [
      '#type' => 'table',
      '#header' => ['Name', 'IPSP ID', 'Country'],
      '#rows' => $rows,
      '#empty' => $this->t('No organizations found.'),
    ];

    return $build;
  }

}
