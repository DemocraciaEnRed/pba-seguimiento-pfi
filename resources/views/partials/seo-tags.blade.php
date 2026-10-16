<title>{{ $metadata['title'] }}</title>
<meta name="title" content="{{ $metadata['title'] }}">
<meta name="description" content="{{ $metadata['description'] }}">
@if ($metadata['url'])
<link rel="canonical" href="{{ $metadata['url'] }}">
@endif

<meta property="og:type" content="website">
@if ($metadata['url'])
<meta property="og:url" content="{{ $metadata['url'] }}">
@endif
<meta property="og:title" content="{{ $metadata['title'] }}">
<meta property="og:description" content="{{ $metadata['description'] }}">
<meta property="og:image" content="{{ $metadata['image'] }}">

<meta name="twitter:card" content="summary_large_image">
@if ($metadata['url'])
<meta name="twitter:url" content="{{ $metadata['url'] }}">
@endif
<meta name="twitter:title" content="{{ $metadata['title'] }}">
<meta name="twitter:description" content="{{ $metadata['description'] }}">
<meta name="twitter:image" content="{{ $metadata['image'] }}">
