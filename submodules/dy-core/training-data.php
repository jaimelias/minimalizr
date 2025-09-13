<?php 

if(!function_exists('concatenate_object')) {
	
    function concatenate_object($obj, $top_prefix = '* ', $child_prefix = '- ', $top_level_separator = "\n") {
        // --- helpers ------------------------------------------------------------
        $isAssoc = function(array $a): bool {
            return array_keys($a) !== range(0, count($a) - 1);
        };

        $allScalars = function(array $a): bool {
            foreach ($a as $v) {
                if (!(is_scalar($v) || $v === null)) return false;
            }
            return true;
        };

        $stringify = function($v): string {
            if (is_bool($v))  return $v ? 'true' : 'false';
            if ($v === null)  return 'null';
            if (is_string($v)) return str_replace(["\r\n", "\r", "\n"], '\n', $v);
            return (string)$v;
        };

        $toUpper = function(string $s): string {
            return function_exists('mb_strtoupper') ? mb_strtoupper($s, 'UTF-8') : strtoupper($s);
        };

        $seen  = new SplObjectStorage();
        $lines = [];

        $emit = function($key, $value, int $level) use (
            &$emit, &$lines, $seen, $stringify, $isAssoc, $allScalars, $top_prefix, $child_prefix, $toUpper
        ) {
            $indent = str_repeat("\t", $level);
            $prefix = $level === 0 ? $top_prefix : $child_prefix;

            // force top-level keys to UPPERCASE (leave deeper levels untouched)
            $keyStr = (string) $key;
            if ($level === 0) {
                $keyStr = $toUpper($keyStr);
            }

            // scalars
            if (is_scalar($value) || $value === null) {
                $lines[] = "{$indent}{$prefix}{$keyStr}: " . $stringify($value);
                return;
            }

            // arrays
            if (is_array($value)) {
                if (empty($value)) {
                    $lines[] = "{$indent}{$prefix}{$keyStr}: []";
                    return;
                }
                if (!$isAssoc($value) && $allScalars($value)) {
                    $inline = array_map($stringify, $value);
                    $lines[] = "{$indent}{$prefix}{$keyStr}: [" . implode(', ', $inline) . "]";
                    return;
                }
                $lines[] = "{$indent}{$prefix}{$keyStr}:";
                foreach ($value as $k => $v) {
                    $emit($k, $v, $level + 1);
                }
                return;
            }

            // objects
            if (is_object($value)) {
                if ($seen->contains($value)) {
                    $lines[] = "{$indent}{$prefix}{$keyStr}: [*RECURSION*]";
                    return;
                }
                $seen->attach($value);

                $props = get_object_vars($value);
                if (!empty($props)) {
                    $lines[] = "{$indent}{$prefix}{$keyStr}:";
                    foreach ($props as $k => $v) {
                        $emit($k, $v, $level + 1);
                    }
                } else {
                    if (method_exists($value, '__toString')) {
                        $lines[] = "{$indent}{$prefix}{$keyStr}: " . (string)$value;
                    } else {
                        $lines[] = "{$indent}{$prefix}{$keyStr}: {}";
                    }
                }
                return;
            }

            // unknown type
            $lines[] = "{$indent}{$prefix}{$keyStr}: [unprintable]";
        };

        // root handling (object or array)
        if (is_object($obj)) {
            $blocks = [];
            foreach (get_object_vars($obj) as $k => $v) {
                $start = count($lines);
                $emit($k, $v, 0);
                $blocks[] = implode("\n", array_slice($lines, $start));
            }
            return implode($top_level_separator, $blocks);
        } elseif (is_array($obj)) {
            $blocks = [];
            foreach ($obj as $k => $v) {
                $start = count($lines);
                $emit($k, $v, 0);
                $blocks[] = implode("\n", array_slice($lines, $start));
            }
            return implode($top_level_separator, $blocks);
        } else {
            // allow scalar root
            return ($top_prefix ?? '') . (string)$obj;
        }
    }

}


?>