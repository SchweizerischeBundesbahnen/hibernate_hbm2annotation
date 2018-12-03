<?php

class ChildDescripton
{

    private $name;

    private $requiredAttributes = array();

    private $optionalAttributes = array();

    //require override

    function __construct(string $name, array $requiredAttributes = array(), array $optionalAttributes = array())
    {
        $this->name = $name;
        $this->requiredAttributes = $requiredAttributes;
        $this->optionalAttributes = $optionalAttributes;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRequiredAttributes()
    {
        return $this->requiredAttributes;
    }

    public function getOptionalAttributes()
    {
        return $this->optionalAttributes;
    }
}

class ValueChildDescripton extends ChildDescripton
{
    function __construct(string $name)
    {
        parent::__construct($name, [], []);
    }
}

class ParamChildDescripton extends ChildDescripton
{
}

class ColumnChildDescription extends ChildDescripton
{
}
