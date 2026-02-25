<?php

declare(strict_types=1);

namespace Facettes\Analysis;

enum Decision: string
{
    case INDEX   = 'index';
    case NOINDEX = 'noindex';
}
