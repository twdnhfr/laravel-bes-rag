<?php

namespace Twdnhfr\BesRag\Data;

enum StepType: string
{
    case SearchQuery = 'search_query';
    case RetrievedChunks = 'retrieved_chunks';
    case EvidenceSelection = 'evidence_selection';
    case SynthesisNote = 'synthesis_note';
    case Answer = 'answer';
}
