#!/usr/bin/env bash

# Exit immediately if a command fails (-e),
# treat unset variables as errors (-u),
# and make pipelines fail if any command fails (pipefail).
set -euo pipefail

# ============================================================
# Configuration
# ============================================================

# The patch file can be passed as the first argument:
#   ./apply-patch-and-tag.sh my-changes.patch
#
# If no argument is provided, the script will ask for it.
PATCH_FILE="${1:-}"

# ============================================================
# Helper functions
# ============================================================

log() {
    echo
    echo "============================================================"
    echo "$1"
    echo "============================================================"
}

error() {
    echo
    echo "[ERROR] $1" >&2
    exit 1
}

# ============================================================
# Check prerequisites
# ============================================================

log "Checking prerequisites"

command -v git >/dev/null 2>&1 || error "git is not installed."

if [[ -z "$PATCH_FILE" ]]; then
    read -rp "Enter the path to the diff patch file: " PATCH_FILE
fi

[[ -f "$PATCH_FILE" ]] || error "Patch file not found: $PATCH_FILE"

# Make sure we are inside a Git repository.
git rev-parse --is-inside-work-tree >/dev/null 2>&1 \
    || error "This directory is not inside a Git repository."

echo "[OK] Git repository detected."
echo "[OK] Patch file: $PATCH_FILE"

# ============================================================
# Show current status
# ============================================================

log "Current Git status"

git status --short

# ============================================================
# Check whether the patch can be applied
# ============================================================

log "Checking patch"

if ! git apply --check "$PATCH_FILE"; then
    error "Patch cannot be applied cleanly. No changes have been made."
fi

echo "[OK] Patch can be applied."

# ============================================================
# Apply patch
# ============================================================

log "Applying patch"

git apply "$PATCH_FILE"

echo "[OK] Patch applied successfully."

# ============================================================
# Show changes
# ============================================================

log "Changes after applying patch"

git status --short

echo
read -rp "Press ENTER to continue with staging and committing..."

# ============================================================
# Ask for commit message
# ============================================================

log "Commit"

while true; do
    read -rp "Enter commit message: " COMMIT_MESSAGE

    if [[ -n "$COMMIT_MESSAGE" ]]; then
        break
    fi

    echo "[WARNING] Commit message cannot be empty."
done

# ============================================================
# Stage all changes
# ============================================================

log "Staging changes"

git add -A

echo "[OK] All changes staged."

# Show what will be committed.
echo
echo "Files staged for commit:"
git status --short

# ============================================================
# Commit
# ============================================================

log "Creating commit"

git commit -m "$COMMIT_MESSAGE"

echo "[OK] Commit created successfully."

# ============================================================
# Push commit
# ============================================================

log "Pushing commit"

CURRENT_BRANCH=$(git branch --show-current)

[[ -n "$CURRENT_BRANCH" ]] \
    || error "Could not determine the current branch."

echo "Branch: $CURRENT_BRANCH"

git push origin "$CURRENT_BRANCH"

echo "[OK] Commit pushed successfully."

# ============================================================
# Ask for tag
# ============================================================

log "Create Git tag"

while true; do
    read -rp "Enter new tag name: " TAG_NAME

    if [[ -z "$TAG_NAME" ]]; then
        echo "[WARNING] Tag name cannot be empty."
        continue
    fi

    # Check if the tag already exists locally.
    if git rev-parse "$TAG_NAME" >/dev/null 2>&1; then
        echo "[WARNING] Tag '$TAG_NAME' already exists."
        read -rp "Choose another tag name: " TAG_NAME
        continue
    fi

    break
done

# ============================================================
# Create tag
# ============================================================

log "Creating tag"

git tag "$TAG_NAME"

echo "[OK] Tag '$TAG_NAME' created."

# ============================================================
# Push tag
# ============================================================

log "Pushing tag"

git push origin "$TAG_NAME"

echo "[OK] Tag '$TAG_NAME' pushed successfully."

# ============================================================
# Done
# ============================================================

log "Operation completed successfully"

echo "Commit : $COMMIT_MESSAGE"
echo "Branch : $CURRENT_BRANCH"
echo "Tag    : $TAG_NAME"
echo
echo "Everything has been applied, committed, pushed, tagged and pushed."