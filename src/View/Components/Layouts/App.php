<?php

namespace Designer\Studio\View\Components\Layouts;

use Illuminate\View\Component;

class App extends Component
{
    /**
     * @param  string|null  $openUrl  Where the top bar's "open in a new tab" button goes (the draft or live page)
     * @param  string|null  $openLabel  Its label
     */
    public function __construct(
        public ?string $openUrl = null,
        public ?string $openLabel = null,
    ) {
    }

    public function render()
    {
        return view('studio::components.layouts.app');
    }
}
