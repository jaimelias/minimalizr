#!/usr/bin/env bash

set -euo pipefail

usage() {
	printf '%s\n' \
		"Usage:" \
		"  $0 --copy-only" \
		"  $0 --publish <commit message>"
}

if [[ $# -lt 1 ]]; then
	usage >&2
	exit 1
fi

mode="$1"
message=""

case "$mode" in
	--copy-only)
		if [[ $# -ne 1 ]]; then
			usage >&2
			exit 1
		fi
		;;
	--publish)
		shift

		if [[ $# -lt 1 || -z "$1" ]]; then
			usage >&2
			exit 1
		fi

		message="$*"
		;;
	-h|--help)
		usage
		exit 0
		;;
	*)
		usage >&2
		exit 1
		;;
esac

dy_core_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
dynamicpackages_dir="$(cd -- "$dy_core_dir/.." && pwd)"
dynamicaviation_dir="$(cd -- "$dynamicpackages_dir/../dynamicaviation" && pwd)"
minimalizr_dir="$(cd -- "$dynamicpackages_dir/../../themes/minimalizr" && pwd)"

if [[ "$mode" == "--publish" ]]; then
	for script in \
		"$dynamicpackages_dir/.scripts/commit.sh" \
		"$dynamicaviation_dir/.scripts/commit.sh" \
		"$minimalizr_dir/.scripts/commit.sh"; do
		if [[ ! -x "$script" ]]; then
			echo "Error: missing executable script: $script" >&2
			exit 1
		fi
	done
fi

rm -rf -- "$dynamicaviation_dir/submodules/dy-core"
mkdir -p -- "$dynamicaviation_dir/submodules"
cp -R -- "$dy_core_dir" "$dynamicaviation_dir/submodules/dy-core"
printf '%s\n' 'Copied dy-core to Dynamic Aviation.'

rm -rf -- "$minimalizr_dir/submodules/dy-core"
mkdir -p -- "$minimalizr_dir/submodules"
cp -R -- "$dy_core_dir" "$minimalizr_dir/submodules/dy-core"
printf '%s\n' 'Copied dy-core to Minimalizr.'

if [[ "$mode" == "--publish" ]]; then
	"$dynamicpackages_dir/.scripts/commit.sh" "$message"
	"$dynamicaviation_dir/.scripts/commit.sh" "$message"
	"$minimalizr_dir/.scripts/commit.sh" "$message"
fi
