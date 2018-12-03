<?php

class TypeAnnotation
{
    private $name;
    private $class;
    private $parameter = array();

    function __construct(SimpleXMLElement $typeDef = null)
    {
        if ($typeDef !== null) {
            $this->name = (string) $typeDef->attributes()['name'];
            $this->class = (string) $typeDef->attributes()['class'];
    
            foreach ($typeDef->children() as $child) {
                if (!empty($child['name'])) {
                    $this->parameter[ (string)$child['name'] ] = (string) $child;
                }
            }
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function setClass(string $class): void
    {
        $this->class = $class;
    }

    public function getParameter(): array
    {
        return $this->parameter;
    }

    public function getAnnotation(bool $useType = false): string
    {
        global $typeToConverter ;

        if (empty($this->parameter)) {
            if (!empty($typeToConverter) && !empty($typeToConverter[$this->class]) && !$useType) {
                return sprintf('@Convert(converter = %s.class)', $typeToConverter[$this->class]);
            }

            return sprintf('@Type(type = "%s")', $this->class);
        }

        $parameters = array();
        foreach($this->parameter as $key => $val) {
            $parameters[] = sprintf('@Parameter(name = "%s", value = "%s")', $key, $val);
        }


        return sprintf("@Type(type = \"%s\", parameters = {\n    %s})", $this->class, implode(",\n    ", $parameters));
    }
}