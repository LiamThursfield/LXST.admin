<?php

namespace App\Enums\Api;

use App\Enums\Traits\HasLabels;

/**
 * How an MCP tool call ended, as recorded in the audit log
 */
enum McpOutcome: string
{
    use HasLabels;

    case Success = 'success';

    /** The token, its user or the tenant can't use the tool, or the change needs another ability */
    case Denied = 'denied';

    /** The arguments failed validation, so nothing was saved */
    case Invalid = 'invalid';

    /** The token made too many changes in a minute */
    case RateLimited = 'rate_limited';

    case Error = 'error';
}
