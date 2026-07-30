---
name: codebase-memory-mcp
description: Rules for using structural code intelligence and knowledge graph search instead of raw text parsing.
compatibility: ">=1.0.0"
---

# Codebase Memory MCP Protocol

## When to Use
* Prioritize `codebase-memory` tools over generic `grep` or `glob` when mapping codebase architectures, tracing call stacks, or searching for cross-service API routes.
* Use it immediately at session startup to understand dependencies without burning context window limits.

## Operational Constraints
* Treat any architectural memory naming a specific file or function as a claim from a frozen point in time. 
* Always verify structural claims using a quick targeted tool validation before making final edits to a file.
* If a code structural query yields zero results, gracefully fall back to native standard search commands.
