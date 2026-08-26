<?php

namespace Drupal\webform_geonames\Controller;

use Drupal\Core\StringTranslation\TranslatableMarkup;
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
    // The form sends the selected country as a NAME (the era_country_names
    // option set is keyed by name, not ISO code). Accept a clear `country`
    // parameter and fall back to the legacy `country_code` name.
    $query = $request->query->get('query');
    $country = $request->query->get('country') ?? $request->query->get('country_code');

    // Validate input.
    if (empty($query) || empty($country)) {
      return new JsonResponse([]);
    }

    // Geonames needs an ISO 3166-1 alpha-2 code. Resolve it from Drupal's own
    // country list rather than a third-party service (the client used to call
    // restcountries.com/v3.1, since removed and CORS-blocked). An already-ISO2
    // value passes straight through.
    $country_code = $this->resolveCountryCode((string) $country);
    if (!$country_code) {
      // Log rather than fail silently: an unresolved country returns the same
      // empty result a genuine zero-match search does, and that indistinguishable
      // silence is what let the previous breakage run unnoticed for months.
      \Drupal::logger('webform_geonames')->warning('Could not resolve country to an ISO code: @country', ['@country' => $country]);
      return new JsonResponse([]);
    }

    // Geonames account. The username is environment-specific and currently a
    // shared account; replacing it (with config + HTTPS) is tracked in issue #61.
    $username = 'jmartinos';

    // http_build_query encodes each value, so a free-text city query cannot
    // break out of the query string.
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

      // Geonames signals quota exhaustion, an invalid username or a disabled
      // account as HTTP 200 with a `status` error object, not a non-2xx status,
      // so the catch below never sees it. Detect and log it, otherwise it is
      // just another silent empty result.
      if (isset($data['status'])) {
        \Drupal::logger('webform_geonames')->error('Geonames returned an error: @message', ['@message' => $data['status']['message'] ?? 'unknown']);
        return new JsonResponse([]);
      }

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
      \Drupal::logger('webform_geonames')->error('Geonames request failed: @message', ['@message' => $e->getMessage()]);
      return new JsonResponse([]);
    }
  }

  /**
   * Resolve a country name to its ISO 3166-1 alpha-2 code.
   *
   * Uses Drupal core's country_manager as the source of truth. An input that is
   * already a valid alpha-2 code is returned as-is. Name matching is tolerant of
   * case, whitespace and "&" vs "and", because core spells several countries with
   * "&" (Bosnia & Herzegovina, Trinidad & Tobago, and others) where an
   * ISO-derived option set may use "and".
   */
  protected function resolveCountryCode(string $value): ?string {
    $value = trim($value);
    if ($value === '') {
      return NULL;
    }
    // [ISO2 => country name]. The names are TranslatableMarkup rendered in the
    // request's interface language, so match against the untranslated (English)
    // source string as well — otherwise a non-English UI language renders e.g.
    // "Bosnie-Herzégovine" and nothing matches the English option set.
    $list = \Drupal::service('country_manager')->getList();

    if (isset($list[strtoupper($value)])) {
      return strtoupper($value);
    }

    $normalize = static function (string $s): string {
      $s = str_replace('&', 'and', mb_strtolower($s));
      return preg_replace('/\s+/', ' ', trim($s));
    };
    $target = $normalize($value);
    foreach ($list as $code => $name) {
      $candidates = [(string) $name];
      // hook_countries_alter() can replace a name with a plain string, so guard.
      if ($name instanceof TranslatableMarkup) {
        $candidates[] = $name->getUntranslatedString();
      }
      foreach ($candidates as $candidate) {
        if ($normalize($candidate) === $target) {
          return $code;
        }
      }
    }
    return NULL;
  }

}
