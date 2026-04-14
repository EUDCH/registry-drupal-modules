<?php

namespace Drupal\webform_geonames\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for Geonames autocomplete.
 */
class GeonamesController
{
  /**
   * Callback for Geonames autocomplete.
   */
  public function autocomplete(Request $request)
  {
    // Get query and country_code from the request
    $query = $request->query->get('query');
    $country_code = $request->query->get('country_code');

    // Validate input
    if (empty($query) || empty($country_code)) {
      return new JsonResponse([]);
    }

    // Geonames API username
    $username = 'jmartinos'; // Replace with your Geonames username

    // Build the Geonames API URL
    $url = "http://api.geonames.org/searchJSON?q=$query&maxRows=200&country=$country_code&username=$username&featureClass=P&fuzzy=0.5";
    try {
      // Make the API request
      $response = \Drupal::httpClient()->get($url);
      $data = json_decode($response->getBody(), true);

      // Process the Geonames API response
      $cities = [];
      if (!empty($data['geonames'])) {
        foreach ($data['geonames'] as $city) {
          $cities[] = [
            'value' => $city['name'],
            'label' => $city['name'] . ', ' . $city['adminName1'] . ', ' . $city['countryName'],
          ];
        }
      }

      return new JsonResponse($cities);
    } catch (\Exception $e) {
      return new JsonResponse([]);
    }
  }
}
