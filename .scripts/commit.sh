#!/usr/bin/env bash

set -euo pipefail

if [[ $# -lt 1 || -z "$1" ]]; then
	echo "Usage: $0 <commit message>" >&2
	exit 1
fi

message="$*"
repo_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"

cd "$repo_dir"

if [[ "$(git branch --show-current)" != "master" ]]; then
	echo "Error: minimalizr must be on the master branch." >&2
	exit 1
fi

new_version="$({ VERSION_REPO_DIR="$repo_dir" php <<'PHP'
<?php

$repo_dir = getenv('VERSION_REPO_DIR');

if (!is_string($repo_dir) || $repo_dir === '') {
	throw new RuntimeException('Repository directory is unavailable.');
}

$version_path = $repo_dir . '/version.json';
$functions_path = $repo_dir . '/functions.php';
$style_path = $repo_dir . '/style.css';
$version = json_decode(file_get_contents($version_path), true, 512, JSON_THROW_ON_ERROR);

foreach (['major', 'minor', 'patch'] as $part) {
	if (!isset($version[$part]) || !is_int($version[$part]) || $version[$part] < 0) {
		throw new RuntimeException("Invalid version.json value: {$part}");
	}
}

$version['patch']++;
$new_version = "{$version['major']}.{$version['minor']}.{$version['patch']}";
$functions = file_get_contents($functions_path);
$style = file_get_contents($style_path);

if (!is_string($functions) || !is_string($style)) {
	throw new RuntimeException('Unable to read the minimalizr version files.');
}

$replace_once = static function (string $contents, string $pattern, callable $replacement, string $label): string {
	$count = 0;
	$updated = preg_replace_callback($pattern, $replacement, $contents, -1, $count);

	if (!is_string($updated) || $count !== 1) {
		throw new RuntimeException("Expected exactly one {$label} version marker; found {$count}.");
	}

	return $updated;
};

$functions = $replace_once(
	$functions,
	"/(define\([ \\t]*'MINIMALIZR_VERSION'[ \\t]*,[ \\t]*')\\d+\\.\\d+\\.\\d+('[ \\t]*\);)/",
	static fn(array $matches): string => $matches[1] . $new_version . $matches[2],
	'MINIMALIZR_VERSION'
);
$style = $replace_once(
	$style,
	'/^(Version:[ \t]*)\d+\.\d+\.\d+([ \t]*\r?)$/m',
	static fn(array $matches): string => $matches[1] . $new_version . $matches[2],
	'theme header'
);

$encoded_version = json_encode($version, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if (!is_string($encoded_version)) {
	throw new RuntimeException('Unable to encode version.json.');
}

if (file_put_contents($functions_path, $functions) === false
	|| file_put_contents($style_path, $style) === false
	|| file_put_contents($version_path, $encoded_version . PHP_EOL) === false) {
	throw new RuntimeException('Unable to write version files.');
}

echo $new_version;
PHP
})"

git add .
git commit --quiet -m "${message} - ${new_version}"
git push --quiet origin master
