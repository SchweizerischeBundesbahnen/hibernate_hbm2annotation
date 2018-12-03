<?php

class JoinTableAnnotation{

    public static function generateAnnotation(array $joinTableAttributes): string
    {
        $annotation = '@JoinTable(name = %s, joinColumns = %s, inverseJoinColumns = %s)';
        return sprintf($annotation, $joinTableAttributes['name'], $joinTableAttributes['joinColumns'], $joinTableAttributes['inverseJoinColumns']);
    }
}
