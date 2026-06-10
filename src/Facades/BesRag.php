<?php

namespace Twdnhfr\BesRag\Facades;

use Illuminate\Support\Facades\Facade;
use Twdnhfr\BesRag\BesRagManager;

/**
 * @method static \Twdnhfr\BesRag\PendingSearch make()
 * @method static \Twdnhfr\BesRag\BesResult result(int|\Twdnhfr\BesRag\Models\Run $run)
 *
 * @see BesRagManager
 */
class BesRag extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BesRagManager::class;
    }
}
