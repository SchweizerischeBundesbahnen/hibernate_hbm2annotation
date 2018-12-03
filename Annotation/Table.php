<?php

class Table
{
    public static function generateAnnotation(string $dbTableName, string $schema) : string
    {
        $attributes = [
            "name"      => $dbTableName,
            "schema"    => $schema,
        ];

        // filters out empty attributes, as empty string is False-y
        $attributes = array_filter($attributes);

        $annotation = sprintf("@Table(%s)", implode(', ', array_map(function ($name, $value){
            return sprintf("%s = \"%s\"", $name, $value);
        }, array_keys($attributes), $attributes)));

        return $annotation;
    }
}