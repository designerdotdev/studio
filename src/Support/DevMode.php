<?php

namespace Designer\Studio\Support;

class DevMode
{
    /**
     * Dev mode lets the editor write section source files
     * (resources/designer/*) from the browser. Config wins when set;
     * the default (null) enables it only in the local environment so it
     * can never be silently available in production.
     */
    public static function enabled(): bool
    {
        $configured = config('studio.dev_mode');

        if ($configured !== null) {
            return (bool) $configured;
        }

        return app()->environment('local');
    }
}
