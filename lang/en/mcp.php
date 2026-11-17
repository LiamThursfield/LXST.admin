<?php

/*
| Messages the MCP server (`/api/mcp`) sends back to AI assistants, and the MCP activity log
*/

return [
    'errors' => [
        'not_allowed' => 'This API token can\'t use this tool.',
        'rate_limited' => 'This API token has made too many changes in the last minute. Wait a minute and try again.',
        'not_found' => 'No :type matches that.',
        'live_page' => 'This page is live, so editing it needs the mcp:cms:publish ability. Ask a person to make the change in the admin, or to unpublish the page first.',
        'live_descendants' => 'Changing this page\'s slug or parent would change the URLs of live pages under it, which needs the mcp:cms:publish ability.',
        'removes_fields' => 'Removing fields isn\'t possible through MCP, as it deletes their content. Keep these fields in the list: :fields. A person can remove them in the admin.',
        'removes_sections' => 'Removing sections isn\'t possible through MCP. Keep these sections in the list: :sections. A person can remove them in the admin.',
        'id_or_slug' => 'Give either the id or the slug.',
    ],

    'activity' => [
        'title' => 'MCP activity',
        'description' => 'Every tool call AI assistants have made with this site\'s API tokens, including refused ones. Kept for :days days.',
        'empty' => 'No MCP activity yet.',
        'search' => 'Search tools...',
        'all_tokens' => 'All tokens',
        'all_outcomes' => 'All outcomes',
        'revoked_token' => ':name (revoked)',
        'unknown_token' => 'Unknown token',
        'details' => 'Details',
        'changes' => 'Changes',
        'arguments' => 'Arguments',
        'columns' => [
            'time' => 'When',
            'token' => 'Token',
            'tool' => 'Tool',
            'outcome' => 'Outcome',
        ],
    ],
];
