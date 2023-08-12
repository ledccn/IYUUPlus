<?php

namespace Rhilip\Bencode;

if (!function_exists('array_is_list')) {
    /**
     * polyfill of array_key_last for PHP < 8.1.0
     *
     * @param array $array
     */
    function array_is_list($array)
    {
        if ([] === $array || $array === array_values($array)) {
            return true;
        }

        $nextKey = -1;

        foreach ($array as $k => $v) {
            if ($k !== ++$nextKey) {
                return false;
            }
        }

        return true;
    }
}

/**
 * Class Bencode
 *
 * Convert Map:
 *  - Dictionary (starts with d, ends with e, with key sort)
 *  - List (starts with l, ends with e)
 *  - Integer (starts with i, ends with e)
 *  - String (starts with number denoting number of characters followed by : and then the string)
 *
 * @package Rhilip\Bencode
 * @author Rhilip
 * @license MIT
 *
 * @see https://en.wikipedia.org/wiki/Bencode
 * @see https://wiki.theory.org/index.php/BitTorrentSpecification
 */
class Bencode
{
    /**
     * Decode bencoded data from string
     *
     * @param string $data
     * @param int $pos
     * @return mixed
     * @throws ParseException
     */
    public static function decode($data, &$pos = 0)
    {
        $start_decode = ($pos === 0);   // If it is the root call ?
        if ($start_decode && (!is_string($data) || strlen($data) == 0)) {
            throw new ParseException('Decode Input is not valid String');
        }

        if ($pos >= strlen($data)) {
            throw new ParseException('Unterminated bencode string literal');
        }

        if ($data[$pos] === 'd') {
            $pos++;
            $return = [];
            while ($data[$pos] !== 'e') {
                $key = self::decode($data, $pos);
                $value = self::decode($data, $pos);
                if ($key === null || $value === null) {
                    break;
                }
                if (!is_string($key)) {
                    throw new ParseException('Non string key found in the dictionary');
                } elseif (array_key_exists($key, $return)) {
                    throw new ParseException('Duplicate Dictionary key exist before: ' . $key);
                }
                $return[$key] = $value;
            }
            ksort($return, SORT_STRING);
            $pos++;
        } elseif ($data[$pos] === 'l') {
            $pos++;
            $return = [];
            while ($data[$pos] !== 'e') {
                $value = self::decode($data, $pos);
                $return[] = $value;
            }
            $pos++;
        } elseif ($data[$pos] === 'i') {
            $pos++;
            $digits = strpos($data, 'e', $pos) - $pos;
            $value = substr($data, $pos, $digits);
            $return = self::checkInteger($value);
            $pos += $digits + 1;
        } else {
            $digits = strpos($data, ':', $pos) - $pos;
            $len = self::checkInteger(substr($data, $pos, $digits));
            if ($len < 0) {
                throw new ParseException('Cannot have non-digit values for String length');
            }

            $pos += ($digits + 1);
            $return = substr($data, $pos, $len);

            if (strlen($return) != $len) {  // Check for String length is match or not
                throw new ParseException('String length is not match for: ' . $return . ', want ' . $len);
            }

            $pos += $len;
        }

        if ($start_decode && $pos !== strlen($data)) {
            throw new ParseException('Could not fully decode bencode string');
        }
        return $return;
    }

    /**
     * This private function help us filter value like `-13` `13` will pass the filter and return it's int value
     * Other value like ``,`-0`, `013`, `-013`, `2.127`, `six` will throw A ParseException
     *
     * @param string $value
     * @return int
     * @throws ParseException
     */
    private static function checkInteger($value)
    {
        $int = (int)$value;
        if ((string)$int !== $value) {
            throw new ParseException('Invalid integer format or integer overflow: ' . $value);
        }
        return $int;
    }

    /**
     * Encode arbitrary data to bencode string
     *
     * @param mixed $data
     * @return string
     */
    public static function encode($data)
    {
        if (is_array($data)) {
            $return = '';
            if (array_is_list($data)) {
                $return .= 'l';
                foreach ($data as $value) {
                    $return .= self::encode($value);
                }
            } else {
                $return .= 'd';
                ksort($data, SORT_STRING);
                foreach ($data as $key => $value) {
                    $return .= self::encode((string)$key);
                    $return .= self::encode($value);
                }
            }
            $return .= 'e';
        } elseif (is_integer($data)) {
            $return = 'i' . $data . 'e';
        } else {
            $return = strlen($data) . ':' . $data;
        }
        return $return;
    }

    /**
     * Load data from bencoded file
     *
     * @param string $path
     * @return mixed
     * @throws ParseException
     */
    public static function load($path)
    {
        return self::decode(file_get_contents($path));
    }

    /**
     * Dump data to bencoded file
     *
     * @param string $path
     * @param $data
     * @return mixed
     */
    public static function dump($path, $data)
    {
        return file_put_contents($path, self::encode($data));
    }
}
