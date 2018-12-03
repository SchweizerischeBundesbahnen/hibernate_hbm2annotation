<?php

class CascadeAnnotation
{
    public static function generateAnnotation(string $cascadeType): string
    {
        $annotation = '@Cascade(%s)';
        $cascadeTypes = explode(',', $cascadeType);
        $types = array();
        foreach ($cascadeTypes as $cascadeType) {
            if (strtolower($cascadeType) !== 'none' && strtolower($cascadeType) !== 'delete-orphan') {
                $types[]= sprintf('CascadeType.%s', strtr(strtoupper((string)$cascadeType), "-", "_"));
            }
        }
        return sprintf($annotation, implode(', ', $types));
    }
}