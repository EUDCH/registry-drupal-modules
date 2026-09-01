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
    // The country arrives as a NAME (era_country_names is keyed by name), so
    // accept `country`, falling back to the legacy `country_code`. Trim each
    // (Unicode-aware /u — trim() is ASCII-only and would miss U+00A0 etc.)
    // BEFORE the fallback, so a whitespace-only `country` still yields to
    // `country_code` rather than winning the ?: and being dropped.
    $trim = static fn ($v): string => preg_replace('/^\s+|\s+$/u', '', (string) $v) ?? '';
    $query = $trim($request->query->get('query'));
    $country = $trim($request->query->get('country')) ?: $trim($request->query->get('country_code'));

    // Reject empty and whitespace-only input: otherwise it reaches
    // resolveCountryCode(), fails to resolve, and logs a warning on every call.
    if ($query === '' || $country === '') {
      return new JsonResponse([]);
    }

    // Resolve the country NAME to an ISO alpha-2 code via Drupal core; an
    // already-ISO2 value passes through.
    $country_code = $this->resolveCountryCode((string) $country);
    if (!$country_code) {
      // Log rather than fail silently: an unresolved country looks identical
      // to a zero-match search, and that silence hid the original breakage.
      \Drupal::logger('webform_geonames')->warning('Could not resolve country to an ISO code: @country', ['@country' => $country]);
      return new JsonResponse([]);
    }

    // OPERAS-owned account. Hardcoded and public, and geonames quota is
    // per-username, so a third party can burn it and empty the autocomplete.
    // Recovery: register a new geonames account and swap the value here.
    $username = 'bgrenier_operas';

    // secure.geonames.org is geonames' documented HTTPS endpoint
    // (web-services.html); free, but has intermittently demanded premium
    // (forum 39842) — the re-check trigger if autocomplete empties.
    // http_build_query encodes each value, so $query cannot break out.
    // name_startsWith is geonames' prefix-autocomplete parameter.
    $url = 'https://secure.geonames.org/searchJSON?' . http_build_query([
      'name_startsWith' => $query,
      'maxRows' => 200,
      'country' => $country_code,
      'username' => $username,
      'featureClass' => 'P',
    ]);
    try {
      $response = \Drupal::httpClient()->get($url);
      $data = json_decode($response->getBody(), TRUE);

      // A non-JSON body still arrives as HTTP 200 (the catch never sees it), so
      // guard decode failure explicitly — otherwise another silent [].
      if (!is_array($data)) {
        \Drupal::logger('webform_geonames')->error('Geonames returned a non-JSON response.');
        return new JsonResponse([]);
      }

      // Geonames signals quota, invalid username or disabled account as HTTP
      // 200 with a `status` object (not a non-2xx), so the catch never sees it.
      if (isset($data['status'])) {
        \Drupal::logger('webform_geonames')->error('Geonames returned an error: @message', ['@message' => $data['status']['message'] ?? 'unknown']);
        return new JsonResponse([]);
      }

      $cities = [];
      if (!empty($data['geonames'])) {
        foreach ($data['geonames'] as $city) {
          // Store the normalized English city name: toponymName is canonical,
          // whereas `name` under name_startsWith echoes the typed variant.
          $name = $city['toponymName'] ?? $city['name'];
          $cities[] = [
            'value' => $name,
            'label' => $name . ', ' . $city['adminName1'] . ', ' . $city['countryName'],
          ];
        }
      }

      return new JsonResponse($cities);
    }
    catch (\Exception $e) {
      // Log the class and code, not getMessage(): a Guzzle request-exception
      // message embeds the full URL (username + user-typed city).
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
      // hook_countries_alter() can swap a name for a plain string, so guard.
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
