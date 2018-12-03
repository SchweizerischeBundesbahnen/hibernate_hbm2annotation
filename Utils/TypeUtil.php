<?php

class TypeUtil
{
    public static function isPrimitiveType(string $type): bool
    {
        $res = in_array(self::prepType($type), [
            'long',
            'integer',
            'double',
            'float',
            'boolean',
            'string'
        ]);
        return $res;
    }

    public static function isUserType(string $type): bool
    {
        return in_array(self::prepType($type), [
            'rcsdatetime',
            'rcsdate',
            'persistentobjectid',
            'adatetime',
            'dunoentityid'
        ]);
    }

    private static function prepType(string $type): string
    {
        $typeParts = explode('.', $type);
        $type = strtolower(trim(end($typeParts)));
        return $type === 'int' ? 'integer' : $type;
    }
}