<?php

namespace Vatts\Console\Terminal;

/**
 * Handle para linhas dinâmicas.
 */
class DynamicLine
{
    private string $_id;

    public function __construct(string $initialContent)
    {
        $this->_id = uniqid('dyn_', true);
        \Vatts\Console\Terminal\Console::registerDynamicLine($this->_id, $initialContent);
    }

    public function update(string $newContent): void
    {
        \Vatts\Console\Terminal\Console::updateDynamicLine($this->_id, $newContent);
    }

    public function end(string $finalContent): void
    {
        \Vatts\Console\Terminal\Console::endDynamicLine($this->_id, $finalContent);
    }
}