<?php

namespace App\Mcp\Support;

use RuntimeException;

/**
 * A tool call the MCP server won't make, though the admin might, e.g. editing a live page without
 * the `mcp:cms:publish` ability. Comes back to the assistant as a tool error, and is logged as denied.
 */
class ToolRefused extends RuntimeException {}
