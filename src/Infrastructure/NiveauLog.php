<?php

declare(strict_types=1);

namespace Facettes\Infrastructure;

enum NiveauLog: string
{
    case DEBUG   = 'debug';
    case INFO    = 'info';
    case WARNING = 'warning';
    case ERROR   = 'error';

    public function priorite(): int
    {
        return match ($this) {
            self::DEBUG   => 0,
            self::INFO    => 1,
            self::WARNING => 2,
            self::ERROR   => 3,
        };
    }
}
