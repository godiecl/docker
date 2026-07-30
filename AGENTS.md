# Agent skills

## Coding Rules
Never use ruff. Always use ty and/or pyrefly.

## Never Touch
.env files - never read or modify

<!-- codebase-memory-mcp:start -->
## Codebase Knowledge Graph (codebase-memory-mcp)

This project uses codebase-memory-mcp to maintain a knowledge graph of the codebase.
ALWAYS prefer MCP graph tools over grep/glob/file-search for code discovery.

### Priority Order
1. `search_graph` â€” find functions, classes, routes, variables by pattern
2. `trace_path` â€” trace who calls a function or what it calls
3. `get_code_snippet` â€” read specific function/class source code
4. `query_graph` â€” run Cypher queries for complex patterns
5. `get_architecture` â€” high-level project summary

### When to fall back to grep/glob
- Searching for string literals, error messages, config values
- Searching non-code files (Dockerfiles, shell scripts, configs)
- When MCP tools return insufficient results

### Examples
- Find a handler: `search_graph(name_pattern=".*OrderHandler.*")`
- Who calls it: `trace_path(function_name="OrderHandler", direction="inbound")`
- Read source: `get_code_snippet(qualified_name="pkg/orders.OrderHandler")`
<!-- codebase-memory-mcp:end -->
