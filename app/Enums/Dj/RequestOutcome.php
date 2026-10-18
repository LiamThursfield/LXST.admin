<?php

namespace App\Enums\Dj;

/**
 * What happened to a song request that wasn't refused, sent to the site as the `outcome`
 */
enum RequestOutcome: string
{
    /** A new request in the queue */
    case Accepted = 'accepted';

    /** The song was already requested, so it got the visitor's upvote */
    case Upvoted = 'upvoted';

    /** The visitor had already requested or upvoted the song */
    case AlreadyRequested = 'already_requested';

    /** The visitor upvoted a request on the public list */
    case Voted = 'voted';

    /** The visitor had already upvoted this request */
    case AlreadyVoted = 'already_voted';

    public function message(): string
    {
        return __("dj.requests.outcomes.{$this->value}");
    }
}
