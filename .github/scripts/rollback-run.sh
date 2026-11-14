#!/usr/bin/env bash
# rollback-run.sh <run id | full commit SHA>: the run of website.yml on main whose `site` artifact a rollback
# deploys again, printed as "<run id> <commit>". A run id must name a successful run of this workflow on main; a
# commit takes the latest such run built from it whose `site` artifact is still kept (90 days). Reads GitHub's
# API with gh (GH_TOKEN; actions: read) for GITHUB_REPOSITORY. Exit 1, with the reason on stderr, otherwise.
set -euo pipefail
target=${1:-}
repository=${GITHUB_REPOSITORY:?GITHUB_REPOSITORY names the repository (owner/name)}
workflow=.github/workflows/website.yml

fail() { echo "$*" >&2; exit 1; }

# The `site` artifact of a run, kept: its id, or nothing.
site_of() {
    gh api "repos/$repository/actions/runs/$1/artifacts?name=site" --jq '[.artifacts[] | select(.expired | not)][0].id // empty'
}

if [[ "$target" =~ ^[0-9]+$ ]]; then
    IFS=$'\t' read -r path branch conclusion sha < <(gh api "repos/$repository/actions/runs/$target" --jq '[.path, .head_branch, .conclusion, .head_sha] | @tsv')
    [ "$path" = "$workflow" ] && [ "$branch" = main ] && [ "$conclusion" = success ] \
        || fail "Run $target is not a successful run of $workflow on main ($path, $branch, $conclusion)."
    [ -n "$(site_of "$target")" ] || fail "Run $target keeps no site artifact (expired, or a run that deploys nothing)."
    echo "$target $sha"
elif [[ "$target" =~ ^[0-9a-f]{40}$ ]]; then
    runs=$(gh api "repos/$repository/actions/workflows/website.yml/runs?branch=main&status=success&head_sha=$target&per_page=50" --jq '.workflow_runs | sort_by(.created_at) | reverse | .[].id')
    for run in $runs; do
        if [ -n "$(site_of "$run")" ]; then
            echo "$run $target"
            exit 0
        fi
    done
    fail "No successful run of $workflow on main built commit $target and still keeps its site artifact."
else
    fail "rollback names a run by its id (digits) or a commit by its full SHA (40 hexadecimal characters): \"$target\"."
fi
