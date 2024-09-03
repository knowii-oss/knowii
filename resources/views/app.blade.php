<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1" />
  <meta name="description" content="Knowii is a place for your community's Knowledge, Ideas and Inspiration." />
  <meta name="robots" content="index,follow,max-image-preview:large" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link
    href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap"
    rel="stylesheet"
  />
  <link
    href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:ital,wght@0,200;0,300;0,400;0,600;0,700;0,900;1,200;1,300;1,400;1,600;1,700;1,900&display=swap"
    rel="stylesheet"
  />

  <link href="/icons/favicon.ico" rel="shortcut icon" />

  <meta name="theme-color" content="#ffffff" />
  <meta name="msapplication-TileColor" content="#ffffff" />
  <link href="/icons/apple-touch-icon.png" rel="apple-touch-icon" sizes="180x180" />
  <link href="/icons/favicon-32x32.png" rel="icon" sizes="32x32" type="image/png" />
  <link href="/icons/favicon-16x16.png" rel="icon" sizes="16x16" type="image/png" />
  <!-- Safari Pinned Tab Icon: https://developer.apple.com/library/archive/documentation/AppleApplications/Reference/SafariWebContent/pinnedTabs/pinnedTabs.html -->
  <link color="#4a9885" href="/icons/favicon.svg" rel="mask-icon" />

  <title inertia>{{ config('app.name', 'Laravel') }}</title>

  <!-- Scripts -->
  @routes
  @viteReactRefresh

  {{
  // WARNING: All paths below are relative to the root /public folder
  // Reference: https://laravel.com/api/11.x/Illuminate/Support/Facades/Vite.html
  Vite::useManifestFilename('.vite/manifest.json')
  ->withEntryPoints(["apps/knowii/src/main.tsx","apps/knowii/src/Pages/{$page['component']}.tsx"])
  }}

  @inertiaHead
</head>
<body class="font-sans antialiased">
@inertia
</body>
</html>
</html>
