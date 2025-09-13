<?php

if (!function_exists('concatenate_object_to_html')) {

    function concatenate_object_to_html(
        $obj,
        string $top_keyname_start = '<p><strong>',
        string $top_keyname_end   = '</strong> ',
        string $child_keyname_start = '<strong>',
        string $child_keyname_end   = '</strong> ',
        bool $escape_html = true
    ): string {

        // ---------- helpers ----------
        $esc = function ($s) use ($escape_html) {
            if (!$escape_html) return (string)$s;
            return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $stringify = function ($v) use ($esc): string {
            if (is_bool($v))  return $v ? 'true' : 'false';
            if ($v === null)  return 'null';
            if (is_string($v)) return nl2br($esc($v), false);
            return $esc($v);
        };

        $isAssoc = function (array $a): bool {
            return array_keys($a) !== range(0, count($a) - 1);
        };

        $toUpper = function (string $s): string {
            return function_exists('mb_strtoupper') ? mb_strtoupper($s, 'UTF-8') : strtoupper($s);
        };

        $seen = new SplObjectStorage();

        // Render nested (children) as <ul><li>...</li></ul>
        $renderChildren = function ($value) use (&$renderChildren, $esc, $stringify, $isAssoc, $child_keyname_start, $child_keyname_end, $seen): string {
            if (is_scalar($value) || $value === null) {
                return $stringify($value);
            }

            if (is_object($value)) {
                if ($seen->contains($value)) {
                    return '<ul><li><em>*RECURSION*</em></li></ul>';
                }
                $seen->attach($value);

                $props = get_object_vars($value);
                if (empty($props)) {
                    if (method_exists($value, '__toString')) {
                        return $esc((string)$value);
                    }
                    return '<ul><li><em>{}</em></li></ul>';
                }
                $items = $props;
            } elseif (is_array($value)) {
                if (empty($value)) {
                    return '<ul><li><em>[]</em></li></ul>';
                }
                $items = $value;
            } else {
                return '<ul><li><em>[unprintable]</em></li></ul>';
            }

            $html = '<ul>';
            if ($isAssoc($items)) {
                foreach ($items as $k => $v) {
                    $k = $esc((string)$k);
                    if (is_scalar($v) || $v === null) {
                        $html .= '<li>' . $child_keyname_start . $k . ':' . $child_keyname_end . $stringify($v) . '</li>';
                    } else {
                        $html .= '<li>' . $child_keyname_start . $k . ':' . $child_keyname_end . $renderChildren($v) . '</li>';
                    }
                }
            } else {
                foreach ($items as $idx => $v) {
                    $idxLabel = $esc((string)$idx);
                    if (is_scalar($v) || $v === null) {
                        $html .= '<li>' . $child_keyname_start . $idxLabel . ':' . $child_keyname_end . $stringify($v) . '</li>';
                    } else {
                        $html .= '<li>' . $child_keyname_start . $idxLabel . ':' . $child_keyname_end . $renderChildren($v) . '</li>';
                    }
                }
            }
            $html .= '</ul>';
            return $html;
        };

        // ---------- top-level emit (now UPPERCASES the key) ----------
        $emitTop = function ($key, $value) use ($esc, $stringify, $renderChildren, $top_keyname_start, $top_keyname_end, $toUpper): string {
            $kUpper = $toUpper((string)$key);
            $k = $esc($kUpper);

            $line = $top_keyname_start . $k . ':' . $top_keyname_end;

            if (is_scalar($value) || $value === null) {
                $line .= $stringify($value) . '</p>';
                return $line;
            }

            $needsCloseP = (stripos($top_keyname_start, '<p') !== false) && (stripos($top_keyname_end, '</p>') === false);
            if ($needsCloseP) {
                $line .= '</p>';
            }
            $line .= $renderChildren($value);
            return $line;
        };

        // ---------- root handling ----------
        if (is_object($obj)) {
            $out = '';
            foreach (get_object_vars($obj) as $k => $v) {
                $out .= $emitTop($k, $v);
            }
            return $out;
        }
        if (is_array($obj)) {
            $out = '';
            foreach ($obj as $k => $v) {
                $out .= $emitTop($k, $v);
            }
            return $out;
        }

        return '<p>' . $stringify($obj) . '</p>';
    }
}
?>
