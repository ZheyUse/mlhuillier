---
title: Mirror `/test` into `generate-file-structure.php`
description: |
  When the user says "mirror", treat the `/test` folder as the canonical source
  of truth and update `generate-file-structure.php` so the generator produces the
  same structure and contents as `/test`.
---

Purpose
-------
- Make it explicit for the agent and collaborators what "mirror" means in this
  project and how the agent should act when asked to mirror the runtime test
  folder into the scaffolder generator file.

Rules (enforced when the user says "mirror")
--------------------------------------------
1. Source of truth: `/test` is the authoritative structure and content set to
   replicate. The agent must read `/test` and treat it as the desired output.
2. Target: `generate-file-structure.php` is the scaffolder that must be updated
   so that running it reproduces `/test` exactly (files, directories, and the
   contents of template entries where applicable).
3. Behavior: The agent should update the scaffolder incrementally and safely —
   add directories first, then files, and insert template entries into the
   existing `$templates` array using minimal, context-aware patches.
4. Do not delete or rewrite unrelated scaffolder content. Only append or
   update entries that correspond to items under `/test`.
5. On ambiguous contexts (patch failed, multi-line mismatch), stop and ask the
   user before making a large or risky change.

Operation steps (what the agent does when user requests a mirror)
-----------------------------------------------------------------
1. Inspect `/test` recursively and collect file paths and contents.
2. Locate `generate-file-structure.php` and parse its `$templates` and
   directory list sections (read nearby context before editing).
3. For each directory present in `/test` but missing in the scaffolder,
   insert the directory entry into the scaffolder's directory list.
4. For each file in `/test` that should be scaffolded, create a template
   (heredoc) entry in `$templates` with the path as the key and the file
   contents as the value. Do this in small batches to avoid large context
   mismatches.
5. Validate the patches by re-reading `generate-file-structure.php` and
   ensuring each inserted template is present and context matches.

Safety and quality notes
------------------------
- Prefer multiple small, reversible patches instead of a single very large
  insertion. This reduces chances of "Invalid context" failures.
- If the scaffolder uses placeholder markers (e.g., `// INSERT NEW TEMPLATES
  HERE`), use them. If not, search for the `$templates = [` start and append
  new entries near similar templates.
- Keep file contents verbatim, using nowdoc/heredoc where the scaffolder uses
  them (match the existing quoting style in `generate-file-structure.php`).
- Preserve indentation, array order, and existing code formatting.

Critical generator parity rules
------------------------------
- When validating scaffold output, run the local generator directly:
   `php generate-file-structure.php create <project_name>`
- Do not rely on `ml create` for parity checks unless you have confirmed it is
   using the local repository copy. The wrapper can delegate to an installed CLI
   copy in `C:\ML CLI\Tools`, which may use a different generator version.
- Treat audit results as valid only when the template dump is produced by the
   same local generator file that will be committed.

Audit interpretation guidance
----------------------------
- `Missing`/`Extra` only checks file path presence.
- `Diffs` means byte-level file content differences exist.
- `Normal`/`Error` is a post-classification step; `Error` must be `0` before
   considering mirror complete.
- If behavior differs but `Missing=0`, inspect the specific generated file
   contents and compare with `/test` directly.

Examples (user prompts the agent)
---------------------------------
- "Mirror the `/test` folder into the generator" → Agent will collect `/test`
  and apply incremental patches to `generate-file-structure.php` to reproduce it.
- "Mirror only `test/src/pages/maintenance/accountmanagement`" → Agent will
  only update scaffolder entries relevant to that subtree.

When to ask the user
---------------------
- If a proposed patch would modify more than 10 template entries at once.
- If a template insertion fails due to context mismatch.
- If a file in `/test` contains secrets or environment-specific values.

Next steps (recommended)
------------------------
1. Confirm you want me to perform the mirror now (I can apply patches
   incrementally).  
2. Or, I can produce the exact patches as text so you can review and paste
   them manually.

---
Saved: copilot-instructions.md
