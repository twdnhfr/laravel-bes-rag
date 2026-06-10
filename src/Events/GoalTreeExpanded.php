<?php

namespace Twdnhfr\BesRag\Events;

use Twdnhfr\BesRag\Data\GoalNode;
use Twdnhfr\BesRag\Models\Run;

class GoalTreeExpanded
{
    public function __construct(
        public Run $run,
        public GoalNode $target,
        public int $newGoals,
    ) {}
}
