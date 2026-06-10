<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->each->not->toBeUsed();

arch('contracts are interfaces')
    ->expect('Twdnhfr\BesRag\Contracts')
    ->toBeInterfaces();

arch('the package never evaluates generated code')
    ->expect(['eval', 'exec', 'shell_exec', 'system', 'passthru'])
    ->each->not->toBeUsed();
