<?php

namespace Vatts\Console;

abstract class Command
{
    protected string $name;
    protected string $description;

    public function __construct()
    {
        $this->name = $this->getName();
        $this->description = $this->getDescription();
    }

    abstract public function getName(): string;
    abstract public function getDescription(): string;
    abstract public function handle(array $args): void;

    public function execute(array $args): void
    {
        try {
            $this->handle($args);
        } catch (\Throwable $e) {
            echo "\033[31mError: " . $e->getMessage() . "\033[0m\n";
        }
    }

}

