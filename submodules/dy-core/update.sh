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
new_version=""

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

	new_version="$({ DY_CORE_DIR="$dy_core_dir" php <<'PHP'
<?php

$dy_core_dir = getenv('DY_CORE_DIR');

if (!is_string($dy_core_dir) || $dy_core_dir === '') {
	throw new RuntimeException('dy-core directory is unavailable.');
}

$version_path = $dy_core_dir . '/version.json';
$loader_path = $dy_core_dir . '/loader.php';
$version = json_decode(file_get_contents($version_path), true, 512, JSON_THROW_ON_ERROR);

foreach (['major', 'minor', 'patch'] as $part) {
	if (!isset($version[$part]) || !is_int($version[$part]) || $version[$part] < 0) {
		throw new RuntimeException("Invalid version.json value: {$part}");
	}
}

$version['patch']++;
$new_version = "{$version['major']}.{$version['minor']}.{$version['patch']}";
$loader = file_get_contents($loader_path);

if (!is_string($loader)) {
	throw new RuntimeException('Unable to read loader.php.');
}

$count = 0;
$loader = preg_replace_callback(
	"/(define\([ \\t]*'DY_CORE_VERSION'[ \\t]*,[ \\t]*')\\d+\\.\\d+\\.\\d+('[ \\t]*\);)/",
	static fn(array $matches): string => $matches[1] . $new_version . $matches[2],
	$loader,
	-1,
	$count
);

if (!is_string($loader) || $count !== 1) {
	throw new RuntimeException("Expected exactly one DY_CORE_VERSION marker; found {$count}.");
}

$encoded_version = json_encode($version, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if (!is_string($encoded_version)) {
	throw new RuntimeException('Unable to encode version.json.');
}

if (file_put_contents($loader_path, $loader) === false
	|| file_put_contents($version_path, $encoded_version . PHP_EOL) === false) {
	throw new RuntimeException('Unable to write dy-core version files.');
}

echo $new_version;
PHP
})"

	printf 'Updated dy-core to %s.\n' "$new_version"
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
	"$dynamicpackages_dir/.scripts/commit.sh" --dy-core "$new_version" "$message"
	"$dynamicaviation_dir/.scripts/commit.sh" --dy-core "$new_version" "$message"
	"$minimalizr_dir/.scripts/commit.sh" --dy-core "$new_version" "$message"
fi
