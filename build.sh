#!/usr/bin/env bash
set -euo pipefail

# Builds a release zip of this repo (without the .git folder) into release/,
# names it from the latest git tag, then writes and verifies a SHA-256 sum.
# Tag the release before running, e.g.: git tag v1.3.0

cd "$(dirname "$0")"

NAME="sppb-uploadcustomicon-hardening-restore"
SUFFIX="_j3_j4_j5_j6"
RELEASE_DIR="release"

# Version comes from the latest git tag; strip a leading 'v' for the filename.
if ! TAG=$(git describe --tags --abbrev=0 2>/dev/null); then
    echo "error: no git tag found. Create one first, e.g. git tag v1.3.0" >&2
    exit 1
fi
VERSION=${TAG#v}
ZIP="${NAME}-${VERSION}${SUFFIX}.zip"

mkdir -p "$RELEASE_DIR"

# git archive of the tag: no .git, no untracked files, no .gitignore'd paths.
git archive --format=zip -o "$RELEASE_DIR/$ZIP" "$TAG"

# SHA-256 written filename-relative so `-c` works from inside release/.
( cd "$RELEASE_DIR" && sha256sum "$ZIP" > "$ZIP.sha256" )

# Verify the sum matches the built zip.
( cd "$RELEASE_DIR" && sha256sum -c "$ZIP.sha256" )

echo "Built $RELEASE_DIR/$ZIP (from tag $TAG)"
