#!/usr/bin/env bash

set -euo pipefail

if [[ $# -lt 1 || -z "$1" ]]; then
	echo "Usage: $0 <commit message>" >&2
	exit 1
fi

message="$*"
dy_core_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
dynamicpackages_dir="$(cd -- "$dy_core_dir/.." && pwd)"
dynamicaviation_dir="$(cd -- "$dynamicpackages_dir/../dynamicaviation" && pwd)"
minimalizr_dir="$(cd -- "$dynamicpackages_dir/../../themes/minimalizr" && pwd)"

for script in \
	"$dynamicpackages_dir/commit.sh" \
	"$dynamicaviation_dir/commit.sh" \
	"$minimalizr_dir/commit.sh"; do
	if [[ ! -x "$script" ]]; then
		echo "Error: missing executable script: $script" >&2
		exit 1
	fi
done

rm -rf -- "$dynamicaviation_dir/submodules/dy-core"
mkdir -p -- "$dynamicaviation_dir/submodules"
cp -R -- "$dy_core_dir" "$dynamicaviation_dir/submodules/dy-core"

rm -rf -- "$minimalizr_dir/submodules/dy-core"
mkdir -p -- "$minimalizr_dir/submodules"
cp -R -- "$dy_core_dir" "$minimalizr_dir/submodules/dy-core"

"$dynamicpackages_dir/commit.sh" "$message"
"$dynamicaviation_dir/commit.sh" "$message"
"$minimalizr_dir/commit.sh" "$message"
