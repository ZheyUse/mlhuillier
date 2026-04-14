---
name: access-analysis
description: >
  Skill to analyze a repository for Permission-Based Access Control (PBAC)
  implementations where Access Levels = menus (parents) and Permissions =
  submenus/actions (children). This is a read-only analysis skill: do not
  modify files and do not guess — every claim must be backed by file evidence
  (workspace-relative path + line range).
---

# access-analysis SKILL

Purpose
- Locate and extract concrete PBAC implementation details (menus → permissions)
- Produce a structured, evidence-backed report suitable for reverse-engineering
  and porting the PBAC design to another project.

Trigger phrases
- analyze access
- access-analysis
- PBAC
- permissions
- access level
- menu / submenu

ApplyTo (recommended)
- rbac/**
- pbac/**
- migration/**
- src/**
- public/**
- **/*.php
- **/*.sql
- **/*.json
- **/*.js
- **/*.html

Allowed tools
- `file_search` — locate candidate files by glob/keyword
- `grep_search` — search inside files for patterns and keywords
- `read_file` — read specific line ranges for evidence
- `list_dir` — enumerate directories when needed
- `semantic_search` — optional broader code-aware search

Input (JSON)
{
  "paths": ["."],         // workspace-relative paths to analyze
  "filters": ["pbac"],   // optional filters: pbac|rbac|sql|php|json|all
  "maxFiles": 200
}

Output (JSON schema)
{
  "roles": [
    {
      "name": "string",
      "description": "string (if found)",
      "evidence": [{"file":"path","start":1,"end":10,"excerpt":"..."}]
    }
  ],
  "permissions": [
    {
      "name": "string",
      "menu": "string (parent menu if mapped)",
      "evidence": [{"file":"path","start":1,"end":10}]
    }
  ],
  "mappings": [
    {
      "menu": "string",
      "permissions": ["permA","permB"],
      "evidence": [{"file":"path","start":1,"end":20}]
    }
  ],
  "flows": [
    {
      "event": "login|menu-open|api-call",
      "steps": ["step-by-step trace strings"],
      "evidence": [{"file":"path","start":1,"end":40}]
    }
  ],
  "issues": [
    {"type":"string","message":"string","evidence":[...]} 
  ],
  "confidence": "low|medium|high"
}

Evidence rule (mandatory)
- Every descriptive claim (role, permission, mapping, flow, or issue) MUST include at
  least one evidence entry with a workspace-relative `file` path and 1-based
  `start`/`end` line numbers. Include a short `excerpt` where helpful.
- If no evidence exists for a requested claim, return an explicit empty result
  for that section and set `confidence` to `low` or return: "No evidence found."

Procedure (recommended steps)
1. Use `file_search` with keywords: pbac, rbac, permission, access_level, accesslevel,
   menu, submenu, permissions, roles, access, userdb to locate candidate files.
2. Use `grep_search` inside candidates for SQL (`CREATE TABLE`, `INSERT INTO`),
   PHP array literals (e.g. `$permissions = [...]`), JSON keys (`"permissions"`),
   function names (`checkPermission`, `hasAccess`, `isAllowed`, `canAccess`), and
   route/middleware references.
3. For each match, call `read_file` to capture ~5 lines before and after the match
   and produce an evidence entry with exact `start`/`end` lines and an excerpt.
4. Build menu→permission mappings from schema files (SQL), config files (JSON/PHP),
   and controller/middleware checks (PHP/JS). Record all source locations.
5. Trace enforcement points by searching for middleware/guards, controller checks,
   and API endpoints that reference permission checks. Produce a step-by-step flow
   for login → load permissions → check on menu open / API call.
6. Produce `issues` for missing server-side checks, UI-only checks, or ambiguous
   mappings with code evidence.

Edge cases & heuristics
- Treat tables named `*_pbac` or `*_access`, or columns named `access_level`,
  `permission`, `permissions`, `access` as high-priority candidates.
- When detecting PHP arrays/JSON objects, prefer explicit key names `menu`, `permission`,
  `permissions`, `access_level` over inferred names.

Examples (short)
- Input: {"paths":["rbac/","src/"],"filters":["pbac"]}
- Output: roles: [...], permissions: [...], mappings: [...], flows: [...], confidence: "high"

Read-only & safety
- This skill MUST NOT edit files. Use only the allowed read tools listed above.
- Never invent a permission or flow without an evidence entry. If uncertain,
  prefer returning found raw excerpts and mark confidence accordingly.
