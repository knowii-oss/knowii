@inject('headers', 'Bepsvpt\SecureHeaders\SecureHeaders')

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1" />
  <meta name="description" content="Knowii is a place for your community's Knowledge, Ideas and Inspiration." />
  <meta name="robots" content="index,follow,max-image-preview:large" />

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

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Noto+Sans:ital,wght@0,100..900;1,100..900&display=swap"
    rel="stylesheet"
  />

  <script
    defer
    data-domain="knowii.net"
    data-api="https://blue-bar-dsebastien-19fd.developassion.workers.dev/api/v1/event"
    src="https://blue-bar-dsebastien-19fd.developassion.workers.dev/content/script.js"
    nonce="{{ csp_nonce('script') }}"
  ></script>

  <link href="/icons/favicon.ico" rel="shortcut icon" />

  <!-- Updated to the active theme by the front-end (theme.ts) -->
  <meta name="theme-color" content="#37404c" />
  <meta name="msapplication-TileColor" content="#37404c" />
  <!-- Safari Pinned Tab Icon: https://developer.apple.com/library/archive/documentation/AppleApplications/Reference/SafariWebContent/pinnedTabs/pinnedTabs.html -->
  <link color="#37404c" href="/icons/favicon.svg" rel="mask-icon" />

  <script id="site-microdata-script" type="application/ld+json" nonce="{{ csp_nonce('script') }}">
    @verbatim
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "Knowii",
      "alternateName": "Knowii",
      "description": "Where all communities belong. A place for your Knowledge, your Ideas and Inspiration.",
      "publisher": {
        "@context": "https://schema.org/",
        "@type": "Person",
        "name": "Sébastien Dubois",
        "familyName": "Dubois",
        "givenName": "Sébastien",
        "image": "https://www.dsebastien.net/content/images/size/w2000/2024/04/Seb-2022.jpg",
        "url": "https://x.com/dSebastien",
        "sameAs": [
          "https://www.linkedin.com/in/sebastiend/",
          "https://github.com/dsebastien",
          "https://dsebastien.medium.com/",
          "https://dev.to/dsebastien",
          "https://www.youtube.com/channel/UCz0x-VJhvKwV-PK3E_i5G1w",
          "https://www.twitch.tv/dsebastien",
          "https://stackoverflow.com/users/226630/dsebastien",
          "https://www.facebook.com/trankill",
          "https://dsebastien.hashnode.dev/",
          "https://updates.dsebastien.net/",
          "https://www.reddit.com/user/lechtitseb/"
        ],
        "jobTitle": "Founder",
        "worksFor": {
          "@type": "Organization",
          "name": "DeveloPassion",
          "url": "https://developassion.be"
        }
      },
      "url": "https://www.knowii.net"
    }
    @endverbatim
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
