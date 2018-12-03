<?php
require_once 'Abstract.php';

abstract class OverrideSupporting extends Annotation
{
    public function generateColumnAnnotation(): string
    {
        return $this->generateAnnotationComponents()[self::COLUMN_NAME];
    }

    public function generateFieldOverrideAnnotation(): string
    {
        // is independent of Mapping Declaration if type is optional, default is always a simple type.
        // integer isn't always correct, but as long as it's a simple type that doesn't matter.
        $type = empty($this->itemDesc->getAttributes()['type']) ? 'integer' : $this->itemDesc->getAttributes()['type'];
        $isPrimitiveType = $this->isPrimitiveOrUserType($type);
        $kind = $isPrimitiveType ? 'AttributeOverride' : 'AssociationOverride';
        $name = $this->itemDesc->getAttributes()['name'];

        $column = $this->generateColumnAnnotation();

        if ($kind === 'AttributeOverride') {
            return sprintf("@%s(name = \"%s\", column = %s)", $kind, $name, $column);
        } else if ($kind === 'AssociationOverride') {
            return sprintf("@%s(name = \"%s\", joinColumns = %s)", $kind, $name, str_replace('@Column', '@JoinColumn', $column));
        }
    }
}
