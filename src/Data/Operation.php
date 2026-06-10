<?php

namespace Twdnhfr\BesRag\Data;

enum Operation: string
{
    case Seed = 'seed';
    case Expand = 'expand';
    case Combine = 'combine';
    case Delete = 'delete';
    case Translocate = 'translocate';
    case Crossover = 'crossover';
}
