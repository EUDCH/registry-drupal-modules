# EDCH Registry – Custom Drupal Modules

This repository contains custom Drupal modules used by the
[EDCH Registry](https://registry.edch.eu), and developed in the context of
the [DIAMAS project](https://diamasproject.eu/).

They target Drupal 10 and implement registry-specific UX, validation, and
integration features.

## Modules

### 1) computed_address

**Purpose:** Provides a computed address string (e.g., concatenating street,
city, postal, country) derived from existing entity fields so displays/exports
are consistent and auto-updating.

**Highlights**

- Computed field/property; no duplication of stored data.
- Useful for listings, exports, search indexing.

**Depends on:** Drupal core field/entity API (and typically structured address fields).

---

### 2) email_protect

**Purpose:** Adds a computed “Protected Email” for **Organisation** nodes by
obfuscating `field_ipsp_contact_email` (replaces `@` with `(at)`) to deter
simple scraping.

**Highlights**

- Only for `node` bundle `organisation`.
- Returns obfuscated email when present.

**Depends on:** `drupal:node` (Core 10).

---

### 3) org_moderation_sync

**Purpose:** Synchronizes **organisation** node moderation state with **linked
users** (via `field_ipsp_organisation_ref`), keeping visibility/access aligned
with the organisation’s status.

**Highlights**

- Listens on entity/node presave.
- Updates users referencing the organisation when moderation changes.

**Depends on:** `drupal:node`, likely `drupal:content_moderation`.

---

### 4) organization_listing

**Purpose:** Public listing page for **published organisations** with basic
filtering/search.

**Route:** `/organizations` → `OrganizationListingController::listOrganizations`
**UI:** Table with **Name**, **IPSP ID**, **Country**; a country filter is built from `node__field_country`.

**Depends on:** `drupal:views` (declared), core 9/10.

---

### 5) webform_geonames

**Purpose:** Adds a **city autocomplete** to Drupal Webforms backed by
**GeoNames** (and `restcountries.com` for country-code lookups).

**Highlights**

- JS (`webform_geonames_autocomplete.js`) enhances inputs with class `.webform-city-autocomplete`.
- Backend route: `/webform-geonames/autocomplete` serves JSON suggestions.
- Debounced fetch, 10-result dropdown, clears on country change.

**Depends on:** Webform module; GeoNames credentials (username).

---

### 6) organization_validation

**Purpose:** Validates organisation during **user registration** and supports
admin review/confirmation flows with email notifications.

**Highlights**

- Email templates for user verification and admin review notices.
- Libraries for confirmation styling and admin table row toggling.
- Route: `POST /organization-validation/manageSelectedOrganisations` for bulk/selected actions.
- Workflow event subscriber to react to moderation transitions.

**Depends on:** `drupal:user`, `drupal:node` (Core 10).
Additional moderation features are supported via an event subscriber.

## Release process

1. **Update the CHANGELOG** — move the `[Unreleased]` section to the new version (e.g. `[1.3.0]`) and add a fresh empty `[Unreleased]` section at the top.
2. **Bump module versions** — update the `version` field in the `.info.yml` of any module whose code changed.
3. **Open a PR** with the CHANGELOG and version bump changes, get it merged.
4. **Push a git tag** matching the new version:
   ```sh
   git tag 1.3.0
   git push upstream 1.3.0
   ```
5. The **Release workflow** runs automatically: it creates a draft GitHub release and attaches a `registry-drupal-modules-<tag>.tar.gz` archive containing all custom module directories.
6. **Review the draft** on GitHub and publish it once satisfied.

> To re-run the workflow against an existing draft (e.g. after a failed run), go to **Actions → Release → Run workflow** and select the tag.

## Funded by the European Union

This work has been funded by the European Union's Horizon Europe research and
innovation programme under grant agreement No
[101058007 (DIAMAS)](https://cordis.europa.eu/project/id/101058007).
