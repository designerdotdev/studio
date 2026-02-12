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

    public function __construct()
    {
        $this->tailwindCdn = config('studio.iframe.tailwind_cdn', true);
        $this->alpineCdn = config('studio.iframe.alpine_cdn', true);
        $this->alpinePlugins = array_keys(array_filter(
            config('studio.iframe.alpine_plugins', [])
        ));
        $this->extraStyles = config('studio.iframe.extra_styles', []);
        $this->extraScripts = config('studio.iframe.extra_scripts', []);
        $this->bodyClass = config('studio.iframe.body_class', 'min-h-screen w-full');
        $this->headHtml = config('studio.iframe.head_html', '');
    }

    public function render()
    {
        return view('studio::components.layouts.iframe');
    }
}
