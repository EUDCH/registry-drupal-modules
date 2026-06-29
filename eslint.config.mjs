// Flat config for ESLint v9/v10 (the legacy .eslintrc.* format was dropped in
// ESLint v10, which MegaLinter 9.5.0 bundles).
//
// Self-contained ON PURPOSE: this file imports nothing. MegaLinter runs its
// bundled ESLint against the workspace, which has no node_modules in CI, so a
// bare `import '@eslint/js'` fails there with ERR_MODULE_NOT_FOUND. Inlining the
// rules keeps the config resolvable everywhere.
//
// Drupal-aligned: Drupal front-end code is browser-context IIFE behaviors
// (sourceType: script) against a known set of Drupal/jQuery globals, linted
// with a curated subset of ESLint's recommended correctness rules.
export default [
  // Never traverse composer-installed Drupal core or vendored deps.
  { ignores: ['vendor/**', 'core/**'] },
  {
    files: ['**/*.js'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'script',
      globals: {
        // Browser runtime
        window: 'readonly',
        document: 'readonly',
        console: 'readonly',
        alert: 'readonly',
        fetch: 'readonly',
        setTimeout: 'readonly',
        clearTimeout: 'readonly',
        MutationObserver: 'readonly',
        // Drupal front-end globals
        Drupal: 'readonly',
        drupalSettings: 'readonly',
        drupalTranslations: 'readonly',
        jQuery: 'readonly',
        $: 'readonly',
        once: 'readonly',
      },
    },
    // Curated subset of eslint:recommended (correctness). no-unused-vars warns;
    // Drupal behavior signatures carry unused args, so args are not checked.
    rules: {
      'no-undef': 'error',
      'no-unused-vars': ['warn', { args: 'none' }],
      'no-dupe-args': 'error',
      'no-dupe-keys': 'error',
      'no-dupe-else-if': 'error',
      'no-unreachable': 'error',
      'no-cond-assign': 'error',
      'no-constant-condition': 'error',
      'no-debugger': 'error',
      'no-redeclare': 'error',
      'no-self-assign': 'error',
      'no-fallthrough': 'error',
      'no-irregular-whitespace': 'error',
      'use-isnan': 'error',
      'valid-typeof': 'error',
    },
  },
];
