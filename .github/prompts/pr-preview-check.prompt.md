---
name: pr-preview-check
description: "Use when: preparing a PR preview from the current branch, verifying the branch diff and unique file count, checking route definitions against source files, and waiting for approval before creating a GitHub PR."
---

Prepare a pull request preview for the current branch.

Rules:
- Check the current branch and git status using `git branch --show-current` and `git status -sb`.
- Verify the working tree state before preparing the PR.
- Identify the exact files changed on the current branch relative to the default branch using `git diff --name-only origin/main...HEAD`.
- Count the final unique files after the branch diff is computed and include any prior branch commits in that total.
- If no diff is present, say so clearly and do not invent files.
- Display the branch name, current status, and the unique file count in the PR preview.
- If the branch has 15 or more changed files, show the warning message in capital letters and wait for acknowledgment before proceeding:
  - `THIS BRANCH HAS {N} CHANGED FILES. CONSIDER SPLITTING IT INTO SMALLER, FOCUSED BRANCHES BEFORE PROCEEDING.`
- Split the changed files by concern when useful: backend, frontend, routes, tests, docs, config.
- Show the file list and a proposed PR summary for each group of changed files.
- Before writing the PR description, read every relevant route file (for example `routes/api.php`, `routes/web.php`, `routes/auth.php`) and cross-reference each endpoint mentioned in the PR description — HTTP method, full path, and parameter names — against the actual `Route::` declarations.
- Never invent, paraphrase, or carry over endpoint details from commit messages alone; every route reference in the PR must exactly match the source route declarations.
- Wait for the user to explicitly approve with the exact word `approved` before creating any pull request.
- After approval, create only the approved PR preview content and then create the GitHub PR.
- The PR description must follow this structure, including only the sections that apply:

  Title — imperative, prefixed with the conventional commit type (for example `feat:`, `fix:`, `chore:`).

  ## Summary — 2–4 sentences describing what was added or changed and why.

  ## Changes — grouped by layer (for example Backend, Frontend, Tests). Each group lists bullet points prefixed with the commit type in bold backticks (for example **`feat: ...`**), followed by an em dash and a one-sentence explanation of what the commit does and any non-obvious decisions made.

  ## Files Changed — a markdown table with columns `File` and `Change`, one row per file, describing what changed and what the file contains.

  ## QA Steps — numbered, concrete steps a reviewer must follow to manually verify the changes in a local or staging environment. Each step must be specific and actionable (navigate to URL, run command, assert exact outcome), covering the happy path, relevant edge cases, and error paths. Include any non-obvious setup prerequisites (credentials, env vars, seed data) as the first step when applicable.

- Use a conventional PR title that matches the work being delivered.
- Keep the PR brief, factual, and grounded in the actual diff.
- Ensure the output clearly states the number of unique files in the branch for the PR.
- Include a note that approval is required: type `approved` to proceed.

Output format:
- Current branch
- Unique files in this PR
- Title
- Summary
- Changes
- Files Changed
- QA Steps
- Note that approval is required: type "approved" to proceed
