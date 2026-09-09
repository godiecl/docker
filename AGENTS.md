# Agent skills

## Coding Rules

* Never use ruff, instead use ty and/or pyrefly.
* Do not preserve backward compatibility.
* Choose the simplest implementation that fully meets the current requirements.
* Prefer established, well-maintained libraries over custom implementations.

## Never Touch

- .env files - never read or modify.

## Codebase Knowledge Graph (codebase-memory)

This project uses skill codebase-memory (MCP) to maintain a knowledge graph of the codebase.
ALWAYS prefer MCP graph tools over grep/glob/file-search for code discovery.

## Write technical text

Write or rewrite technical text with the rules of ASD-STE100 Simplified Technical English so it is clear, unambiguous, and free of AI slop. Use for documentation, READMEs, runbooks, procedures, error messages, release notes, incident reports, and API guides. Also use when the user says "STE", "Simplified Technical English", "ASD-STE100", "de-slop", "make this readable", "write for non-native readers", or asks for docs that translate well. Enforces the standard's 53 rules: 20/25-word sentence limits, one word one meaning, simple tenses, active voice, condition before command.
