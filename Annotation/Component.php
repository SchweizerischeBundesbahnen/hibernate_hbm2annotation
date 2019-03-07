<?php
require_once __DIR__ . '/OverrideSupporting.php';
require_once __DIR__ . '/Property.php';

class ComponentAnnotation extends OverrideSupporting implements VerifyBeforeWriteAnnotation
{
    public function generateAnnotationComponents(bool $removeTablePrefix = false): ?array
    {
        $annotations = array();
        $annotations['embedet'] = '@Embedded';
        $itemAttributes = $this->itemDesc->getAttributes();

        $attributeOverrides = array();

        foreach ($itemAttributes as $k => $v) {
            switch ($k) {
                case 'name':
                    // name of getter
                    break;

                case 'class':
                    // annotate that class with @Embeddable
                    if (empty($v)) {
                        $this->setError("No class value provided in " . $this->parser->getHbmFile());
                    }
                    $this->putEmbeddableAnnotation((string)$v);
                    break;

                case 'access':
                    $type = strtoupper($v);
                    $annotations[] = "@Access(AccessType.$type)";
                    break;

                case 'properties':
                    foreach ($v as $subItemDesc) {
                        $propertyAnnotation = new PropertyAnnotation($subItemDesc, $this->parser);

                        $attributeOverrides[] = sprintf("@AttributeOverride(name = \"%s\", column = %s)",
                            $propertyAnnotation->getName(),
                            $propertyAnnotation->generateAnnotationComponents()[self::COLUMN_NAME]);
                    }
                    break;

                default:
                    $this->setError('unknown attribute "' . $k . '"');
                    return null;
            }
        }

        $annotations[self::COLUMN_NAME] = "@AttributeOverrides(value = { //\n    " . implode(", //\n    ", $attributeOverrides) . " //\n})";

        return $annotations;
    }

    private function putEmbeddableAnnotation(string $class): void
    {
        //TODO import
        $embeddableAnnotation = '@Embeddable';
        $targetClass = ClassUtils::getInstance($class, $this->parser->getJavaClass(), $this->parser->getRootFilePath());
        if (strpos($targetClass->getAnnotations(), $embeddableAnnotation) === false) {
            $targetClass->addClassAnnotation($embeddableAnnotation);
            $targetClass->writeFile();
        }
    }

    public function getName(): string
    {
        if (empty($this->itemDesc->getAttributes()['name'])) {
            print_r($this->itemDesc->getXml());
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            die("xx");
        }
        return $this->itemDesc->getAttributes()['name'];
    }

    public function checkFieldOverrideAnnotation(string $currentTotalAnnotations): bool
    {
        return $this->checkAnnotation($currentTotalAnnotations, false);
    }

    public function checkAnnotation(string $currentTotalAnnotations, bool $removeTablePrefix = false): bool
    {
        return strpos($currentTotalAnnotations, '@AttributeOverrides') === false;
    }
}