<?php

class ArrayUtils
{
    public static function flatten(array $arr): array
    {
        $return = array();
        array_walk_recursive($arr, function ($a) use (&$return) {
            $return[] = $a;
        });
        return $return;
    }

    /**
     * Inserts a given $newElement into the given $array (in place) at the given $index.
     * The rest of the array is shifted backwards.
     */
    public static function insert($newElement, int $index, &$array): void
    {
        if (!is_array($newElement)) {
            $newElement = array($newElement);
        }
        array_splice($array, $index, 0, $newElement);
    }

    public static function addAll(array &$collection, array $a): void
    {
        if (empty($a)) {
            return;
        }

        array_push($collection, ...$a);
    }

    public static function add(array &$collection, $a): void
    {
        array_push($collection, $a);
    }
}
