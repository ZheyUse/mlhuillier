Analyze PBAC (user prompt template)

Use this template when you want the repository scanned for a Permission-Based
Access Control (PBAC) implementation where Access Levels = menus and
Permissions = submenus/actions.

Template
```
/analyze-access path={path} filters={filters} format=report
```

Example (fill in):
```
/analyze-access path=rbac/ filters=pbac format=report
```

Instructions for the assistant (required):
- Use the `access-analysis` skill to perform a read-only scan.
- Return a single JSON object matching the skill output schema.
- For every `role`, `permission`, `mapping`, or `flow` claim include an
  `evidence` array with workspace-relative file paths and 1-based line ranges.
- If no evidence is found for the requested path/filters, respond exactly with
  the string: "No evidence found." (do not invent data).

Output format reminder (JSON keys)
- `roles`, `permissions`, `mappings`, `flows`, `issues`, `confidence`
