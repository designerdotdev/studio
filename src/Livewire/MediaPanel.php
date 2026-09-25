<?php

namespace Designer\Studio\Livewire;

use Livewire\Component;

/**
 * The Media panel shell. The browser itself is Alpine over the JSON
 * media endpoints (MediaController); Livewire only renders the frame.
 */
class MediaPanel extends Component
{
    public function render()
    {
        return view('studio::livewire.media-panel');
    }
}
