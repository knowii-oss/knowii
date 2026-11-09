@php
/** @var \Dedoc\Scramble\GeneratorResult $result */
@endphp
@if(\Dedoc\Scramble\Support\DevTools::enabled())
    @php
        $devToolsData = [
            'diagnostics' => $result->diagnostics()->toArray(),
            'renderer' => $renderer,
            'proNudge' => $result->proNudge()->message(),
        ];
    @endphp

    <script type="application/json" id="scramble-dev-tools-data">@json($devToolsData)</script>

    @if($viteServerUrl = \Dedoc\Scramble\Support\DevTools::viteServerUrl())
        <script type="module" nonce="{{ csp_nonce('script') }}" src="{{ $viteServerUrl }}/@@vite/client"></script>
        <script type="module" nonce="{{ csp_nonce('script') }}" src="{{ $viteServerUrl }}/resources/js/devtools.tsx"></script>
    @else
        <script type="module" nonce="{{ csp_nonce('script') }}" src="{{ route('scramble.dev-tools.asset') }}"></script>
    @endif
@endif
