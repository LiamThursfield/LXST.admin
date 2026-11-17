# MCP server: let an AI assistant manage a site's content

Each site has an [MCP](https://modelcontextprotocol.io) server. An AI assistant such as Claude Code can use it to read and build the site's templates, pages, menus and CRM forms, instead of someone clicking through the admin. It runs on the site's own domain and needs one of the site's API tokens.

## Endpoint

```
POST https://{tenant-domain}/api/mcp
Authorization: Bearer {token}
```

The endpoint uses the Streamable HTTP transport, and it's stateless: every request is a single JSON-RPC message. It only exists on tenant domains, never on the central domain. Browser requests from other origins are refused.

## Connecting Claude Code

1. In the site's admin, go to **Site settings › API tokens** and create a token. Give it only the abilities it needs (see below). When you tick an MCP ability, the expiry date is pre-filled 90 days ahead. You can change it, or clear it for a token that never expires. Copy the token: it's only shown once.
2. Put the token in an environment variable, not in a file you commit:

   ```bash
   export LXST_MCP_TOKEN='lxst_…'
   ```

3. Add the server to the project's `.mcp.json`. Claude Code fills in `${LXST_MCP_TOKEN}` when it connects, so the token isn't written into the file:

   ```json
   {
       "mcpServers": {
           "lxst": {
               "type": "http",
               "url": "https://lxst.lxst.digital/api/mcp",
               "headers": {
                   "Authorization": "Bearer ${LXST_MCP_TOKEN}"
               }
           }
       }
   }
   ```

   Or add it for just yourself, with the token in your own Claude Code config:

   ```bash
   claude mcp add --transport http --scope user lxst https://lxst.lxst.digital/api/mcp --header "Authorization: Bearer $LXST_MCP_TOKEN"
   ```

4. Run `/mcp` in Claude Code to check it's connected. Only the tools the token can use are listed.

Locally, after `php artisan tenants:refresh {tenant} --seed`, the admin user has a token named **Claude Code (local)** (`McpSeeder::TOKEN`). It can read and draft content and change templates, menus and forms, but it can't publish. It's a local test token only.

## Abilities

A token needs at least one `mcp:*` ability to connect at all. Each tool needs one ability, and the token's user also needs the admin permission for it. A tool the token can't use isn't listed, and calling it fails as an unknown tool.

| Ability | Tools | The token's user also needs |
| --- | --- | --- |
| `mcp:cms:read` | `get_site_settings`, `list_layouts`, `get_layout`, `list_field_types`, `list_templates`, `get_template`, `list_pages`, `get_page`, `list_menus`, `get_menu` | View content (pages, and listing layouts) or view structure (a layout's content, templates, menus) |
| `mcp:cms:content` | `create_page`, `update_page` (drafts only) | Manage content |
| `mcp:cms:structure` | `create_layout`, `update_layout`, `create_template`, `update_template`, `create_menu`, `update_menu` | Manage structure |
| `mcp:cms:publish` | `publish_page`, `unpublish_page`, and editing pages that are live | Manage content |
| `mcp:crm:forms` | `list_forms`, `get_form`, `create_form`, `update_form` | View / manage forms |

- MCP abilities must be ticked on the token one by one. A token made in code with every ability (`*`) doesn't get them.
- A module's tools only exist when the site has that module (CMS or CRM).
- As with every API token, it stops working when its user can no longer manage API tokens.

**Recommended:** give the assistant's token `mcp:cms:read` and `mcp:cms:content`. Add `mcp:cms:structure` and `mcp:crm:forms` only while you're building the site's layouts, templates, menus and forms. Leave out `mcp:cms:publish`, so a person reviews drafts and publishes them in the admin.

## What the tools can and can't do

- **Same rules as the admin.** Every change runs the admin's own validation and permission checks as the token's user, and is saved the same way. Validation errors come back as a tool error listing each field's problem, and nothing is saved.
- **Pages are drafts.** `create_page` always makes a draft, and publishing fields sent with it are ignored. `update_page` can't change a page that's live, or rename or move a draft that has live pages under it, without `mcp:cms:publish`.
- **Layouts, templates, menus and forms go live straight away.** They have no draft state, which is why they have their own abilities. A layout's content shows on every page that uses it.
- **Nothing can be deleted.** There are no delete tools. `update_template` and `update_form` refuse to drop existing fields, as that would delete their content or answers. A person can remove fields in the admin.
- **Updates are partial.** Only what you send changes. Page and layout content, and page metadata, are merged field by field. A list you send (template fields, sections, menu items, form fields) replaces the whole list. Existing fields and sections are matched by `id`, or by slug or name when there's no id.
- **Rich text can be sent as HTML.** It's sanitised to what the editor allows and stored as the editor's JSON plus HTML, like content saved in the admin.
- Submissions, contacts, files and settings other than the public site settings aren't available.

`list_field_types` describes each field type's settings and the shape of its value in page content. The server's instructions, sent when a client connects, sum up the rules above for the assistant.

## Audit log

Every tool call is logged, including reads, refused calls and validation failures. Each entry records:

- the token (its name is kept even after it's revoked) and the token's user
- the tool and the target (e.g. `page #12`)
- the outcome: `success`, `denied`, `invalid`, `rate_limited` or `error`
- the arguments, with long values shortened
- the before and after value of each attribute that changed
- the IP address and user agent

Whoever can manage API tokens sees the log in **Site settings › API tokens › MCP activity**, filtered by token and outcome. Entries are deleted after 180 days (`API_MCP_AUDIT_RETENTION_DAYS`) by the daily `api:prune-mcp-audit-logs` command.

## Rate limits

- Every request counts towards the token's limit (`api.tokens.rate_limit_per_minute`, 120 a minute by default). Over it, the server responds `429` with `Retry-After`.
- Tools that change something have their own limit per token: 30 a minute by default (`API_MCP_WRITES_PER_MINUTE`). Over it, the tool returns an error and nothing is saved. Wait a minute and try again.

## Errors

| Status | When |
| --- | --- |
| `401` | No token, or an invalid, expired or revoked one, or its user can no longer manage API tokens. Comes with a `WWW-Authenticate` header |
| `403` | A token without any `mcp:*` ability, or a browser request from another origin |
| `404` | The central domain, which has no MCP server |
| `429` | Over the token's rate limit |

Inside a successful response, a tool that fails returns `isError: true` with a message: validation errors, a change that needs another ability, a record that doesn't exist, or the write limit. Unexpected errors are logged on the server and come back as a generic message.

## Security notes

- Keep tokens out of committed files, screenshots and shell history. Anyone with a token can do everything its abilities allow until it expires or is revoked. Revoke a token in **Site settings › API tokens** as soon as it might have leaked.
- An AI assistant acts on what it reads. If it's manipulated (prompt injection), it can make any change its token allows. Drafts-only pages, no deletes and the audit log limit the damage. Keeping `mcp:cms:structure` and `mcp:crm:forms` off day-to-day tokens closes the rest.
