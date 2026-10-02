<?php

declare(strict_types=1);

namespace App\Helpers\Filter;

use App\Helpers\Filter\Contracts\FilterHandler;
use App\Helpers\Filter\Handlers\DateFilterHandler;
use App\Helpers\Filter\Handlers\MultipleSelectFilterHandler;
use App\Helpers\Filter\Handlers\NormalFilterHandler;
use App\Helpers\Filter\Handlers\NumberFilterHandler;
use App\Helpers\Filter\Handlers\TextFilterHandler;
use InvalidArgumentException;

final class FilterRegistry
{
    /** @var array<FilterHandler> */
    private array $handlers = [];

    /**
     * Initialize with default handlers.
     */
    public function __construct()
    {
        $this->registerDefaults();
    }

    /**
     * Register a custom filter handler.
     */
    public function register(FilterHandler $handler): self
    {
        $this->handlers[] = $handler;

        return $this;
    }

    /**
     * Find a handler that supports the given filter type.
     *
     * @throws InvalidArgumentException if no handler is found.
     */
    public function resolve(string $type): FilterHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($type)) {
                return $handler;
            }
        }

        // Fallback to normal filter handler
        return new NormalFilterHandler();
    }

    /**
     * Register all default handlers.
     */
    private function registerDefaults(): void
    {
        $this->handlers = [
            new DateFilterHandler(),
            new NumberFilterHandler(),
            new TextFilterHandler(),
            new MultipleSelectFilterHandler(),
            new NormalFilterHandler(), // Always register last as fallback
        ];
    }
}
