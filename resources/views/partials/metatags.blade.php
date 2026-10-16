@inject('seoMetadata', 'App\Services\SeoMetadata')
@include('partials.seo-tags', ['metadata' => $seoMetadata->page(request())])