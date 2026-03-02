# Changelog

All notable changes to this repository are documented here.

## [1.0.1] – Update contact points to use edch.eu

### Changed

- doc: point to new hostname.
- feat: use `no-reply@edch.eu` as fallback address.
- feat: update mail templates to use addresses under @edch.eu.

## [1.0.0] – Initial import

### Added

- **computed_address**: Computed address string for entities.
- **email_protect**: Obfuscates organisation contact email into a “Protected Email” computed value.
- **org_moderation_sync**: Syncs organisation moderation state with linked users.
- **organization_listing**: Public organisations table at `/organizations` with country filter/search.
- **webform_geonames**: City autocomplete for Webforms via GeoNames and restcountries lookups.
- **organization_validation**: Organisation validation during user registration, email templates, admin manageSelectedOrganisations endpoint, workflow event subscriber.

### Notes

- Drupal core 10 targeted; some modules compatible with 9 as indicated.
- Additional UI routes in `organization_validation` are present but currently commented out.
