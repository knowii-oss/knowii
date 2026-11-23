@inject('headers', 'Bepsvpt\SecureHeaders\SecureHeaders')

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1" />
  <meta name="description" content="Knowii is a place for your community's Knowledge, Ideas and Inspiration." />
  <meta name="robots" content="{{ \App\Support\Robots::forRequest(request()) }}" />

  <!--
    Per-request nonce for the <style> elements that scripts inject (Inertia progress bar and error dialog, Vite dev server).
    style-src does not allow 'unsafe-inline': read by main.tsx (Inertia) and by the Vite client.
  -->
  <meta property="csp-nonce" nonce="{{ csp_nonce('style') }}" />

  <!--
    Theme: applied before the first paint to avoid a flash of the wrong theme.
    Saved choice first, then the system preference. Dark when nothing is known.
    WARNING: the storage key and the colors MUST remain aligned with THEME_STORAGE_KEY and THEME_BACKGROUND_COLORS (libs/common constants.ts)
  -->
  <script nonce="{{ csp_nonce('script') }}">
    (function () {
      var theme = 'dark';
      try {
        var saved = window.localStorage.getItem('knowii-theme');
        if (saved === 'light' || saved === 'dark') {
          theme = saved;
        } else if (window.matchMedia('(prefers-color-scheme: light)').matches) {
          theme = 'light';
        }
      } catch (e) {
        // Storage not available: keep the default
      }
      document.documentElement.setAttribute('data-theme', theme);
    })();
  </script>

  <link href="/icons/favicon.ico" rel="shortcut icon" />

  <!-- Updated to the active theme by the front-end (theme.ts) -->
  <meta name="theme-color" content="#303843" />
  <meta name="msapplication-TileColor" content="#303843" />
  <!-- Safari Pinned Tab Icon: https://developer.apple.com/library/archive/documentation/AppleApplications/Reference/SafariWebContent/pinnedTabs/pinnedTabs.html -->
  <link color="#303843" href="/icons/favicon.svg" rel="mask-icon" />

  <!-- Structured data of the site (#1653): built from the configuration of the instance. The publisher is there only when the instance names one -->
  <script id="site-microdata-script" type="application/ld+json" nonce="{{ csp_nonce('script') }}">
    {!! json_encode(\App\Support\InstanceValues::structuredData(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) !!}
  </script>

  <!-- TODO add manifests -->
  <!--  <link href="/static/site.webmanifest" rel="manifest" />-->
  <!--  <meta content="/browserconfig.xml" name="msapplication-config" />-->

  <title data-inertia>{{ config('app.name', 'Laravel') }}</title>

  <!-- Scripts -->
  @routes(nonce: csp_nonce('script'))
  @viteReactRefresh

  {{
  // WARNING: All paths below are relative to the root /public folder
  // Reference: https://laravel.com/api/11.x/Illuminate/Support/Facades/Vite.html
  Vite::useManifestFilename('manifest.json')
  ->withEntryPoints(["apps/knowii/src/main.tsx","apps/knowii/src/Pages/{$page['component']}.tsx"])
  }}

  @inertiaHead
</head>
<body class="font-sans antialiased">
@inertia
</body>
</html>
</html>
