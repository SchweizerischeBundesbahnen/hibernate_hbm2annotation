<?php
require_once __DIR__ . './OverrideSupporting.php';
require_once __DIR__ . './VerifyBeforeWriteColumn.php';

class DiscriminatorValueAnnotation extends Annotation
{

    public function getSubclassXML(): SimpleXMLElement
    {
        return $this->itemDesc->getXMLElement();
    }

    public function getMatchingSubclass(): JavaClassFinder
    {
        $subclassName = $this->itemDesc->getAttributes()['name'];
        if(count(explode('.', $subclassName)) < 3){
            // not FQCN
            $fqcnParts = explode('.', $this->parser->getClassName());
             //replace current class with subclass, which is presumably in the same package if it's not FQ
            $fqcnParts[count($fqcnParts) - 1] = $subclassName;
            $subclassName = implode('.', $fqcnParts);
        }
        return JavaClassFinder::getInstance($this->parser->getRootFilePath(), $subclassName);
    }

    public function generateAnnotationComponents(bool $removeTablePrefix = false): ?array
    {
        $itemAttributes = $this->completeItemAttributes($this->itemDesc->getAttributes());

        $annotations = array();

        if(!empty($itemAttributes['discriminator-value'])){
            $annotations[] = sprintf('@DiscriminatorValue("%s")', $itemAttributes['discriminator-value']);
        }

        return $annotations;
    }
}