<?php

namespace Designer\Studio\View\Components\Layouts;

use Illuminate\View\Component;

class Iframe extends Component
{
    public bool $tailwindCdn;
    public bool $alpineCdn;
    public array $alpinePlugins;
    public array $extraStyles;
    public array $extraScripts;
    public string $bodyClass;
    public string $headHtml;
    public string $htmlClass;
    public \Illuminate\Support\HtmlString $siteChrome;

    public function __construct()
    {
        $this->tailwindCdn = config('studio.iframe.tailwind_cdn', true);
        $this->alpineCdn = config('studio.iframe.alpine_cdn', true);
        $this->alpinePlugins = array_keys(array_filter(
            config('studio.iframe.alpine_plugins', [])
        ));
        $this->extraStyles = config('studio.iframe.extra_styles', []);
        $this->extraScripts = config('studio.iframe.extra_scripts', []);
        $this->headHtml = config('studio.iframe.head_html', '');

        // An imported template's palette, fonts, and motion scripts — the
        // canvas has to load them or the preview is not the page.
        $chrome = app(\Designer\Studio\Support\SiteChrome::class);
        $this->siteChrome = $chrome->head();
        $this->bodyClass = $chrome->bodyClass();
        $this->htmlClass = $chrome->htmlClass();
    }

    public function render()
    {
        return view('studio::components.layouts.iframe');
    }
}
