#!/usr/bin/env bash
set -euo pipefail

cat <<'NOTICE'
This repository ZIP does not contain .git history, so contributor cleanup cannot be performed here.

Removing names from GitHub's Contributors graph requires rewriting the commits attributed to those accounts. Do this only from a fresh clone, after backing up the repository and coordinating with anyone who has an existing clone.

Recommended tool: git-filter-repo.

1. Create a fresh mirror clone:
   git clone --mirror git@github.com:WPAuroraAdmin/aurora-admin.git aurora-admin-clean.git
   cd aurora-admin-clean.git

2. Inspect all author identities and co-author trailers:
   git log --all --format='%an <%ae>' | sort -u
   git log --all --format='%B' | grep -i '^Co-authored-by:' | sort -u || true

3. Replace the exact unwanted author names/emails using a mailmap callback or git-filter-repo callback.
   Do not guess the email addresses. Copy them from the inspection commands above.

4. Remove unwanted Co-authored-by trailers where appropriate.

5. Verify every branch and tag, then force-push the mirror:
   git push --force --mirror

GitHub's contributor graph may take time to refresh. Rewriting history changes commit IDs and requires collaborators to re-clone or carefully reset their local branches.
NOTICE
