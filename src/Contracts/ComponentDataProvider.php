<?php

namespace Designer\Studio\Contracts;

interface ComponentDataProvider
{
    /**
     * Get components for the studio editor.
     *
     * Should return an array of components with the following structure:
     * [
     *     'id' => int,
     *     'name' => string,
     *     'html' => string,
     *     'title' => string (optional),
     *     'description' => string (optional),
     *     'fields' => array (optional, YAML-parsed fields),
     * ]
     *
     * @return array
     */
    public function getComponents(): array;

    /**
     * Get variables with default values for rendering.
     *
     * @return array
     */
    public function getVariables(): array;
}
