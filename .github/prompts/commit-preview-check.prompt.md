---
name: commit-preview-check
description: "Use when: preparing a commit preview for a branch, splitting work by concern, calculating the final unique file count after commits, and waiting for approval before creating commits."
---

Prepare a commit preview for the current branch.

Rules:
- Check the current branch and git status.
- Identify the exact recently changed files in the working tree.
- Split the those recently changed files by concern when useful: backend, frontend, routes, tests, docs, config.
- Show the file list and a proposed commit message for each group of those recently changed files.
- Wait for the user to explicitly approve with the word "approved" before creating any commit.
- After approval, create only the approved files and commit them in those groups.
- Recalculate the final unique file count relative to the default branch after the commits are created.
- Include any previous branch commits in the final unique file count.
- If no diff is present, say so clearly and do not invent files.

Output format:
- Current branch
- Grouped file list by concern
- Proposed commit message for each group
- Total unique files after commit creation
- Note that approval is required: type "approved" to proceed
