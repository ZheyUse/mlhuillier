Quick alias: `/aa` (user prompt)

Use this short template to analyze a single file quickly. If you have a file open in the editor, you can omit `file` and just run `/aa` while that file is active.

Template
```
/aa file={path} extras="{short instructions}"
```

Examples
```
/aa file=src/config/auth.php extras="create pbac blueprint"
/aa extras="summarize permissions and enforcement points"  # uses active editor file
```

Assistant behavior (required)
- If `file` is provided, call the `access-analysis` SKILL with `paths:[file]`.
- If `file` is omitted and there is an active editor file, use that path.
- If `extras` is provided, include it as follow-up instructions after returning the SKILL JSON.
- Return a single JSON object matching the SKILL output schema (`roles`, `permissions`,
  `mappings`, `flows`, `issues`, `confidence`) and include `evidence` entries for every claim.
- If no evidence is found, return exactly: "No evidence found." (no invented data).

Notes
- This is a convenience alias for the full `/analyze-access` prompt.
- Use `extras` to request a secondary action (example: "make blueprint", "list enforcement points").
