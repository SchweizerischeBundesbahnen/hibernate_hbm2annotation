<?php

require_once __DIR__ . '/../Utils/TypeUtil.php';

class ItemDescription
{

    private $nodeName;

    private $attributes = array();

    private $meta = array();

    private $xml = '';

    private $item;

    private $typeDefs = array();

    private $tokenInfo = null;

    private $annotation = null;

    public function __construct($nodeName, SimpleXMLElement $item, array $typeDefs)
    {
        $this->nodeName = $nodeName;
        $this->xml = $item->asXML();
        $this->typeDefs = $typeDefs;
        $this->item = $item;
    }

    public function getNodeName()
    {
        return $this->nodeName;
    }

    public function setNodeName($nodeName)
    {
        $this->nodeName = $nodeName;
    }

    public function addMeta($k, $v)
    {
        $this->meta[$k] = $v;
    }

    public function addAttribute($k, $v)
    {
        $this->attributes[$k] = $v;
    }

    public function getMeta()
    {
        return $this->meta;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function getXml(): string
    {
        return $this->xml;
    }

    public function getXMLElement(): SimpleXMLElement
    {
        return $this->item;
    }

    public function getTypeDefs()
    {
        return $this->typeDefs;
    }

    /**
     * Get the value of tokenInfo
     * @return The information where in java file this annotation needs to put at/above.
     */
    public function getTokenInfo(): ?TokenInfo
    {
        return $this->tokenInfo;
    }

    public function setTokenInfo(TokenInfo $tokenInfo): void
    {
        $this->tokenInfo = $tokenInfo;
    }

    public function getTypeAnnotation($typeName, bool $useType = false): ?string
    {
        global $typeToConverter;

        if (strpos($typeName, 'org.hibernate') !== false) {
            throw new Exception('Hybernate types are unsupported');
        }

        foreach ($this->typeDefs as $typeDef) {
            if ($typeDef->getName() === $typeName) {
                return $typeDef->getAnnotation($useType);
            }
        }

        if (!empty($typeToConverter) && !empty($typeToConverter[$typeName])) {
            // Also hibernate standart types can be mapped to converter.
            $typeDef = new TypeAnnotation();
            $typeDef->setClass($typeName);
            return $typeDef->getAnnotation($useType);
        }

        if (TypeUtil::isPrimitiveType($typeName)) {
            return null;
        }

        if (substr($typeName, 0, 10) === 'java.lang.') {
            return null;
        }

        return '@Type(type = "' . $typeName . '")';
    }

    public function getAnnotation(): ?Annotation
    {
        return $this->annotation;
    }

    public function setAnnotation(Annotation $annotation)
    {
        $this->annotation = $annotation;
    }

    /**
     * @return string: the access-type. If none is given or it's ill-defined, PROPERTY is assumed.
     */
    public function getAccessType() : string
    {
        if(!empty($this->getAttributes()['access'])){
            if(strtolower($this->getAttributes()['access'] === AccessType::FIELD)){
                return AccessType::FIELD;
            }
        }
        return AccessType::PROPERTY;
    }

}

/**
 * Describes the two access-types, FIELD and PROPERTY
 */
abstract class AccessType
{
    public const FIELD = "field";
    public const PROPERTY = "property";
}
