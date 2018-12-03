<?php
require_once __DIR__ . './Abstract.php';

class PlaceboAnnotation extends Annotation {
    public function generateAnnotationComponents(bool $removeTablePrefix = false): ?array
    {
        throw new Exception('The placebo annotation is just for getting the name');
    }

    public function getName() : ?string
    {
        if (empty($this->itemDesc->getAttributes()['name'])) {
            return null;
        }
        return $this->itemDesc->getAttributes()['name'];
    }
}