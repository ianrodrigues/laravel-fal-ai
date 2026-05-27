<?php

namespace IanRodrigues\FalAi\Image;

use IanRodrigues\FalAi\Exceptions\UnknownModelException;

class ModelHandlerRegistry
{
    /** @var array<int, ModelHandler> */
    protected array $handlers = [];

    public function register(ModelHandler $handler): self
    {
        array_unshift($this->handlers, $handler);

        return $this;
    }

    public function resolve(string $model): ModelHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($model)) {
                return $handler;
            }
        }

        $registered = implode(', ', array_map(fn (ModelHandler $h) => $h::class, $this->handlers))
            ?: 'none';

        throw new UnknownModelException(
            "No fal image model handler is registered for [{$model}]. Registered: [{$registered}].",
        );
    }

    /** @return array<int, ModelHandler> */
    public function all(): array
    {
        return $this->handlers;
    }
}
