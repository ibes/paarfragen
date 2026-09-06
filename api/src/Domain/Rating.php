<?php

declare(strict_types=1);

namespace Paarfragen\Domain;

enum Rating: int
{
    case VeryNegative = -5;
    case Negative = -1;
    case Positive = 1;
    case VeryPositive = 5;
}
