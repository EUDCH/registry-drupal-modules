<?php

namespace Drupal\webform_geonames\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for Geonames autocomplete.
 */
class GeonamesController {

  /**
   * Callback for Geonames autocomplete.
   */
  public function autocomplete(Request $request) {
    // Get query and country from the request. The form sends the selected
    // country as a NAME (the era_country_names option set is keyed by name, not
    // ISO code), so resolve it to an ISO code below.
    $query = $request->query->get('query');
    $country = $request->query->get('country_code');

    // Validate input.
    if (empty($query) || empty($country)) {
      return new JsonResponse([]);
    }

    // Geonames needs an ISO 3166-1 alpha-2 country code. Resolve it from
    // Drupal's own country list rather than a third-party service (the client
    // used to call restcountries.com/v3.1, which has been retired and is
    // CORS-blocked). An already-ISO2 value passes straight through.
    $country_code = $this->resolveCountryCode((string) $country);
    if (!$country_code) {
      return new JsonResponse([]);
    }

    // Geonames API username.
    // Replace with your Geonames username.
    $username = 'jmartinos';

    // Build the Geonames API URL. http_build_query encodes each value, so a
    // query or country with spaces or '&' cannot break out of the query string.
    $url = 'http://api.geonames.org/searchJSON?' . http_build_query([
      'q' => $query,
      'maxRows' => 200,
      'country' => $country_code,
      'username' => $username,
      'featureClass' => 'P',
      'fuzzy' => 0.5,
    ]);
    try {
      // Make the API request.
      $response = \Drupal::httpClient()->get($url);
      $data = json_decode($response->getBody(), TRUE);

      // Process the Geonames API response.
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
    }
    catch (\Exception $e) {
      return new JsonResponse([]);
    }
  }

  /**
   * Resolve a country name to its ISO 3166-1 alpha-2 code.
   *
   * Uses Drupal core's country_manager as the source of truth. An input that is
   * already a valid alpha-2 code is returned as-is. Name matching is tolerant of
   * case and of "&" vs "and", because the era_country_names option set spells one
   * country "Bosnia and Herzegovina" where core uses "Bosnia & Herzegovina".
   */
  protected function resolveCountryCode(string $value): ?string {
    $value = trim($value);
    if ($value === '') {
      return NULL;
    }
    // [ISO2 => translated name].
    $list = \Drupal::service('country_manager')->getList();

    if (isset($list[strtoupper($value)])) {
      return strtoupper($value);
    }

    $normalize = static function (string $s): string {
      $s = mb_strtolower($s);
      $s = str_replace('&', 'and', $s);
      return preg_replace('/\s+/', ' ', trim($s));
    };
    $target = $normalize($value);
    foreach ($list as $code => $name) {
      if ($normalize((string) $name) === $target) {
        return $code;
      }
    }
    return NULL;
  }

}
