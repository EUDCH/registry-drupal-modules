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
    // The form sends the selected country as a NAME (era_country_names is keyed
    // by name, not ISO code). Accept a `country` parameter, falling back to the
    // legacy `country_code`. `?:` (not `??`) also falls back on an empty string.
    $query = $request->query->get('query');
    $country = $request->query->get('country') ?: $request->query->get('country_code');

    // Validate input.
    if (empty($query) || empty($country)) {
      return new JsonResponse([]);
    }

    // Resolve the country NAME to an ISO 3166-1 alpha-2 code from Drupal core's
    // country list (the client used to call restcountries.com, since removed).
    // An already-ISO2 value passes straight through.
    $country_code = $this->resolveCountryCode((string) $country);
    if (!$country_code) {
      // Log rather than fail silently: an unresolved country is indistinguishable
      // from a genuine zero-match search, and that silence hid the original breakage.
      \Drupal::logger('webform_geonames')->warning('Could not resolve country to an ISO code: @country', ['@country' => $country]);
      return new JsonResponse([]);
    }

    // OPERAS-owned geonames account (replaces the former shared jmartinos one).
    $username = 'bgrenier_operas';

    // secure.geonames.org is geonames' documented HTTPS endpoint (free; geonames
    // forum thread 39842); api.geonames.org is the HTTP one. http_build_query
    // encodes each value, so the free-text query cannot break out.
    $url = 'https://secure.geonames.org/searchJSON?' . http_build_query([
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

      // A malformed or non-JSON body still arrives as HTTP 200, so the catch
      // never sees it; decode failure would otherwise be another silent [].
      if (!is_array($data)) {
        \Drupal::logger('webform_geonames')->error('Geonames returned a non-JSON response.');
        return new JsonResponse([]);
      }

      // Geonames signals quota / invalid-username / disabled-account as HTTP 200
      // with a `status` error object (not a non-2xx), so the catch never sees it.
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
      // Log the class and code, not getMessage(): a Guzzle request-exception
      // message embeds the full URL (shared username + user-typed city).
      \Drupal::logger('webform_geonames')->error('Geonames request failed: @type (@code)', ['@type' => get_class($e), '@code' => $e->getCode()]);
      return new JsonResponse([]);
    }
  }

  /**
   * Resolve a country name to its ISO 3166-1 alpha-2 code via country_manager.
   *
   * Already-ISO2 input passes through. Name matching is tolerant of case,
   * whitespace and "&" vs "and" (core spells several countries with "&").
   */
  protected function resolveCountryCode(string $value): ?string {
    $value = trim($value);
    if ($value === '') {
      return NULL;
    }
    // Names are TranslatableMarkup in the UI language, so also match the
    // untranslated (English) source — else a non-English UI breaks resolution.
    $list = \Drupal::service('country_manager')->getList();

    if (isset($list[strtoupper($value)])) {
      return strtoupper($value);
    }

    $normalize = static function (string $s): string {
      // Decode HTML entities first: a client could send an escaped "&amp;",
      // which would otherwise miss the "&" -> "and" folding below.
      $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $s = str_replace('&', 'and', mb_strtolower($s));
      // /u collapses the Unicode whitespace html_entity_decode() can yield;
      // ?? '' keeps the string return on /u's NULL for malformed UTF-8.
      return preg_replace('/\s+/u', ' ', trim($s)) ?? '';
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
