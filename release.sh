#!/bin/bash
set -euo pipefail

REPO="laravel/installer"
BRANCH="master"

# Ensure we are on correct branch and the working tree is clean
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
  echo "Error: must be on $BRANCH branch (current: $CURRENT_BRANCH)" >&2
  exit 1
fi

if [ -n "$(git status --porcelain)" ]; then
  echo "Error: working tree is not clean. Commit or stash changes before releasing." >&2
  git status --porcelain
  exit 1
fi

get_current_version() {
    # Get the latest version tag, stripping the 'v' prefix
    local latest_tag=$(git describe --tags --abbrev=0 2>/dev/null || echo "")
    if [ -z "$latest_tag" ]; then
        echo "0.0.0"
    else
        echo "${latest_tag#v}"
    fi
}

bump_version() {
    local version=$1
    local bump_type=$2

    local major=$(echo "$version" | cut -d. -f1)
    local minor=$(echo "$version" | cut -d. -f2)
    local patch=$(echo "$version" | cut -d. -f3)

    case $bump_type in
        "patch")
            patch=$((patch + 1))
            ;;
        "minor")
            minor=$((minor + 1))
            patch=0
            ;;
        "major")
            major=$((major + 1))
            minor=0
            patch=0
            ;;
        *)
            echo "Invalid version type. Please choose patch/minor/major"
            exit 1
            ;;
    esac

    echo "$major.$minor.$patch"
}

git pull

CURRENT_VERSION=$(get_current_version)
echo ""
echo "Current version: v$CURRENT_VERSION"
echo ""

echo "Merged PRs since v$CURRENT_VERSION:"
echo ""

if [ "$CURRENT_VERSION" = "0.0.0" ]; then
    COMMITS=$(git log --oneline)
else
    COMMITS=$(git log "v$CURRENT_VERSION"..HEAD --oneline)
fi

PR_NUMBERS=$(echo "$COMMITS" | grep -oE '#[0-9]+' | tr -d '#' | sort -rn)

if [ -z "$PR_NUMBERS" ]; then
    echo "  No PRs found since last release."
else
    for pr in $PR_NUMBERS; do
        gh pr view "$pr" --json number,title,url | jq -r '"  #\(.number) — \(.title) (\(.url))"' 2>/dev/null || true
    done
fi

echo ""

echo "Select version bump type:"
echo "1) patch (bug fixes)"
echo "2) minor (new features)"
echo "3) major (breaking changes)"
echo

read -p "Enter your choice (1-3): " choice

case $choice in
    1)
        RELEASE_TYPE="patch"
        ;;
    2)
        RELEASE_TYPE="minor"
        ;;
    3)
        RELEASE_TYPE="major"
        ;;
    *)
        echo "❌ Invalid choice. Exiting."
        exit 1
        ;;
esac

NEW_VERSION=$(bump_version "$CURRENT_VERSION" "$RELEASE_TYPE")
TAG="v$NEW_VERSION"

echo ""
echo "Bumping version: v$CURRENT_VERSION → $TAG"
echo ""

read -p "Proceed with release $TAG? (y/n): " confirm
if [ "$confirm" != "y" ]; then
    echo "❌ Release cancelled."
    exit 1
fi

sed -i '' "s/'Laravel Installer', '[^']*'/'Laravel Installer', '$NEW_VERSION'/" bin/laravel

git add bin/laravel
git commit -m "$TAG"

git tag -a "$TAG" -m "$TAG"
git push
git push --tags

gh release create "$TAG" --generate-notes

echo ""
echo "✅ Release $TAG completed successfully."
echo "🔗 https://github.com/$REPO/releases/tag/$TAG"
