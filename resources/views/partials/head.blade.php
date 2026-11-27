@props(['title' => null, 'description' => null, 'canonical' => null, 'ogImage' => null, 'ogType' => 'website', 'noindex' => false, 'jsonLd' => []])
@php
    $siteName = 'MáToCenu.cz';
    $fullTitle = $title ? $title.' – '.$siteName : $siteName.' – '.setting('brand.slogan');
    $description = \Illuminate\Support\Str::limit(strip_tags($description ?? setting('seo.default_description')), 180);
    $canonical ??= url()->current();
    $ogImage ??= asset('og-default.png');
    $noindex = $noindex || ! setting('seo.indexing') || ! app()->isProduction();
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $canonical }}">
@if ($noindex)
    <meta name="robots" content="noindex, nofollow">
@endif
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="cs_CZ">
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:title" content="{{ $title ?? $siteName }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#244A35">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/manifest.webmanifest">
<script @cspNonce>
    (function () { var p = 'system'; try { p = localStorage.getItem('mtc-theme') || 'system'; } catch (e) {}
      if (p === 'dark' || (p === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); })();
</script>
@foreach ((array) $jsonLd as $ld)
    <script type="application/ld+json" @cspNonce>{!! json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endforeach
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
