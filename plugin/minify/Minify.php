<?php
/**
 * MinifyLite - small dependency-free CSS / JS minifier
 *
 * @package   MinifyLite
 * @version   0.9.4
 * @license   MIT (see LICENSE)
 */

class Minify
{
    const VERSION = '0.9.4';

    /**
     * @var array in-memory cache (per request)
     */
    protected static $cache = array();

    /**
     * Minify a file by its extension (css / js).
     *
     * @param string $path
     * @return string
     */
    public static function file($path)
    {
        if (!is_file($path)) {
            return '';
        }

        $key = $path . ':' . filemtime($path);
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $source = file_get_contents($path);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext == 'css') {
            $result = self::css($source);
        } elseif ($ext == 'js') {
            $result = self::js($source);
        } else {
            $result = $source;
        }

        self::$cache[$key] = $result;

        return $result;
    }

    /**
     * Minify CSS source.
     *
     * @param string $css
     * @return string
     */
    public static function css($css)
    {
        $css = self::stripBlockComments($css);

        $out = '';
        $len = strlen($css);
        $quote = null;

        for ($i = 0; $i < $len; $i++) {
            $c = $css[$i];

            if ($quote !== null) {
                $out .= $c;
                if ($c == $quote && $css[$i - 1] != '\\') {
                    $quote = null;
                }
                continue;
            }

            if ($c == '"' || $c == "'") {
                $quote = $c;
                $out .= $c;
                continue;
            }

            if (self::isSpace($c)) {
                $last = strlen($out) ? $out[strlen($out) - 1] : '';
                $j = $i + 1;
                while ($j < $len && self::isSpace($css[$j])) {
                    $j++;
                }
                $next = $j < $len ? $css[$j] : '';
                $i = $j - 1;

                if ($last === '' || $next === '') {
                    continue;
                }
                if (strpos('{};:,>', $last) !== false || strpos('{};,>)', $next) !== false) {
                    continue;
                }
                $out .= ' ';
                continue;
            }

            $out .= $c;
        }

        return str_replace(';}', '}', $out);
    }

    /**
     * Conservative JS minification: removes blank lines, full-line
     * comments and indentation. Does not touch strings or regexes.
     *
     * @param string $js
     * @return string
     */
    public static function js($js)
    {
        $js = str_replace("\r\n", "\n", $js);
        $lines = explode("\n", $js);
        $out = array();
        $inComment = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if ($inComment) {
                if (strpos($line, '*/') !== false) {
                    $inComment = false;
                }
                continue;
            }

            if ($line[0] == '/' && strlen($line) > 1) {
                if ($line[1] == '/') {
                    continue;
                }
                if ($line[1] == '*') {
                    if (strpos($line, '*/', 2) === false) {
                        $inComment = true;
                    }
                    continue;
                }
            }

            $out[] = $line;
        }

        return implode("\n", $out);
    }

    /**
     * @param string $s
     * @return string
     */
    protected static function stripBlockComments($s)
    {
        $out = '';
        $len = strlen($s);

        for ($i = 0; $i < $len; $i++) {
            if ($s[$i] == '/' && $i + 1 < $len && $s[$i + 1] == '*') {
                $end = strpos($s, '*/', $i + 2);
                if ($end === false) {
                    break;
                }
                $i = $end + 1;
                continue;
            }
            $out .= $s[$i];
        }

        return $out;
    }

    /**
     * @param string $c
     * @return bool
     */
    protected static function isSpace($c)
    {
        return $c === ' ' || $c === "\t" || $c === "\n" || $c === "\r" || $c === "\f";
    }
}
