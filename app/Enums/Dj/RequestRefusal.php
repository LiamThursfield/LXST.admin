<?php

namespace App\Enums\Dj;

use Symfony\Component\HttpFoundation\Response;

/**
 * Why a public DJ API call was refused (a song request, a vote, or viewing a list), sent to the
 * site as the `reason`
 */
enum RequestRefusal: string
{
    case Closed = 'requests_closed';
    case PassRequired = 'pass_required';
    case CustomNotAllowed = 'custom_not_allowed';
    case VisitorLimit = 'visitor_limit';
    case QueueFull = 'queue_full';
    case ListHidden = 'list_hidden';
    case UpvotesOff = 'upvotes_off';
    case NotVotable = 'not_votable';
    case TracklistHidden = 'tracklist_hidden';

    public function httpStatus(): int
    {
        return match ($this) {
            self::Closed, self::ListHidden, self::UpvotesOff, self::TracklistHidden => Response::HTTP_FORBIDDEN,
            self::PassRequired => Response::HTTP_UNAUTHORIZED,
            self::CustomNotAllowed, self::NotVotable => Response::HTTP_UNPROCESSABLE_ENTITY,
            self::VisitorLimit, self::QueueFull => Response::HTTP_TOO_MANY_REQUESTS,
        };
    }

    public function message(): string
    {
        return __("dj.requests.refusals.{$this->value}");
    }
}
