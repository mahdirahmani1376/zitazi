---
description: Stage changes and commit with an AI-generated message
---

Analyze the current git diff (`git diff HEAD`) and staged changes, then:

1. Review `git status --short` and `git diff HEAD` to understand what changed.
2. Run `git add -A` to stage all changes.
3. Generate a concise Conventional Commits message (`fix:`, `feat:`, `chore:`, `refactor:`, etc.) that describes the change.
4. Commit it with `git commit -m "<message>"`.

Follow the repository's existing commit style. If the change spans multiple logical areas, use a short summary body separated by a blank line. Do not amend or push — commit only.
