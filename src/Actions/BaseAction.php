<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Actions;

abstract class BaseAction
{
    abstract public function handle(): bool;

    public static function make(): self
    {
        $class = get_called_class();

        return new $class(...func_get_args());
    }
}