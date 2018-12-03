<?php

require_once __DIR__ . '/VerifyBeforeWrite.php';
require_once __DIR__ . '/../Hbm/Parser.php';
require_once __DIR__ . '/../Hbm/ItemDescription.php';
require_once __DIR__ . '/Placebo.php';
require_once __DIR__ . '/Id.php';
require_once __DIR__ . '/Set.php';
require_once __DIR__ . '/ManyToOne.php';
require_once __DIR__ . '/Property.php';
require_once __DIR__ . '/TypeAnnotation.php';
require_once __DIR__ . '/Version.php';
require_once __DIR__ . '/Component.php';
require_once __DIR__ . '/Cascade.php';
require_once __DIR__ . '/DiscriminatorValue.php';
require_once __DIR__ . '/Discriminator.php';
require_once __DIR__ . '/Todo.php';
require_once __DIR__ . '/../Utils/TypeUtil.php';
require_once __DIR__ . '/../Utils/NullableUtil.php';
require_once __DIR__ . '/../Utils/ImportUtil.php';

abstract class Annotation
{

    protected $itemDesc;

    protected $parser;

    private $error = '';

    const COLUMN_NAME = 'column';
    const EXTRA_NAME = 'extra';


    public function __construct(ItemDescription $itemDesc, HbmParser $parser)
    {
        $this->itemDesc = $itemDesc;
        $this->parser = $parser;
    }

    public function generateAnnotations(bool $removeTablePrefix = false): ?string
    {
        $annotationComponents = $this->generateAnnotationComponents($removeTablePrefix);
        if (empty($annotationComponents)) {
            return TodoAnnotation::generateTodoAnnotation();
        }

        $tokenInfo = $this->itemDesc->getTokenInfo();

        $isOnField = $tokenInfo instanceof FieldInfo;
        $isIdAnnotation = $this instanceof IdAnnotation;

        if ($isOnField) {
            // if we use Field Access, we need to explicitly specify it
            $annotationComponents[] = '@Access(AccessType.FIELD)';
            if ($isIdAnnotation) {
                // hibernate assumes the AccessType used on ID for every  other field
                // thus we need to set an explicit default
                $this->parser->getJavaClass()->addClassAnnotation('@Access(AccessType.PROPERTY)');
            }

            // Not the case in our codebase, but might be for others
            // If we annotate a field, but it's transient, that's no good
            // Something needs to be refactored.
            /* @var $tokenInfo FieldInfo */
            if($tokenInfo->isTransient()){
                $annotationComponents[] = TodoAnnotation::generateTodoAnnotation();
            }
        }

        if($isIdAnnotation){
            // remove the @RcsDbSequence annotation, as it doesn't belong on the getter/field
            // it's generated separately again for putting on the class
            unset($annotationComponents[IdAnnotation::$RCS_ID_GEN]);
        }

        return implode("\n", $annotationComponents);
    }

    public abstract function generateAnnotationComponents(bool $removeTablePrefix = false): ?array;

    public function generateColumnAnnotation(): string
    {
        throw new Exception("generateColumnAnnotation is not supported in " . get_class($this));
    }

    protected function isPrimitiveOrUserType(string $type): bool
    {
        return TypeUtil::isPrimitiveType($type) || TypeUtil::isUserType($type);
    }

    public function getName()
    {
        throw new Exception('UnsupportedOperation: getName() is not implemented in ' . get_class($this));
    }

    public function getMethodeName($doUcFirst = true, $isBoolean = false)
    {
        if ($isBoolean && substr($this->getName(), 0, 2) === 'is') {
            return $this->getName() . '()';
        }
        $name = ($doUcFirst === true) ? ucfirst($this->getName()) : $this->getName();
        if ($isBoolean === true) {
            return 'is' . $name . '()';
        }
        return 'get' . $name . '()';
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function setError(string $error)
    {
        $this->error = $error;
    }

    protected function removeTablePrefix(string $column): string
    {
        $prefix = rtrim($this->parser->getTablePrefix(), '_') . '_';
        if (strtoupper($prefix) === strtoupper(substr($column, 0, strlen($prefix)))) {
            return substr($column, strlen($prefix));
        }
        return $column;
    }

    public static function extractColumnNameFromAnnotation(string $annotation): ?string
    {
        $annotation = trim(strtr($annotation, array(
            '@Override' => ''
        )));
        if (empty($annotation)) {
            return null;
        }

        $matches = array();
        $hit = preg_match('/\s*\@Column\s*\([^\)]*name \=\ "(?<column_name>[^\"]*)\"/U', $annotation, $matches);

        if ($hit !== 1) {
            return null;
        }

        return $matches['column_name'];
    }

    public static function extractAttributeOverrideNameFromAnnotation(string $annotation): ?string
    {
        if (empty($annotation)) {
            return null;
        }

        $matches = array();
        $hit = preg_match('/\s*\@(AttributeOverride|AssociationOverride)\s*\([^\)]*name \= (?:\'|\")(?<override_name>[^\"\']+)(?:\'|\")/U', $annotation, $matches);

        if ($hit !== 1) {
            return null;
        }

        return $matches['override_name'];
    }

    public static function extractAttributeOverrideColumnNameFromAnnotation(string $assosiationName, string $annotation): ?string
    {
        if (empty($annotation)) {
            return null;
        }

        $matches = array();
        $hit = preg_match('/\s*\@(AttributeOverride|AssociationOverride)\s*\([^\)]*name \= (?:\'|\")' . preg_quote($assosiationName) . '(?:\'|\")[^\)]*\@Column\([^\)]*name \= \"(?<column_name>[^\"]*)/i', $annotation, $matches);

        if ($hit !== 1) {
            return null;
        }

        return $matches['column_name'];
    }

    /**
     * Takes an array of item attributes ($itemDesc->getAttributes())
     * and if it doesn't contain a 'column' pair, creates one from the
     * 'name' pair. While for hibernate this is implicit,
     * it helps readability and this script to always specify the col name.
     */
    public function completeItemAttributes(array $itemAttributes): array
    {
        if (empty($itemAttributes['column'])) {
            $itemAttributes['column'] = $itemAttributes['name'];
        }

        return $itemAttributes;
    }

    /**
     * surrounds $value in Quotes if it isn't a number or boolean
     */
    protected static function quote(string $value): string
    {
        return self::needsQuotes($value) ? '"' . $value . '"' : $value;
    }

    /**
     * returns TRUE if the value isn't a number or boolean
     */
    protected static function needsQuotes(string $value): bool
    {
        /*
         * Things that don't need quotes:
         * - true
         * - false
         * - numbers (also negative)
         * - anything wrapped in {} //NOSONAR
         * - classes
         */
        return preg_match('/^(false|true|\-?\d+|.+\.class|{.+})$/i', $value) === 0;
    }
}
