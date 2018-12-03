<?php
require_once __DIR__ . './OverrideSupporting.php';
require_once __DIR__ . './VerifyBeforeWriteColumn.php';
require_once __DIR__ . '/../Utils/ClassUtils.php';

class IdAnnotation extends OverrideSupporting implements VerifyBeforeWriteAnnotation
{
    use VerifyBeforeWriteColumnAnnotation;

    public static $GEN_VAL_IDX = 'generatedValueAnnotation';
    public static $GEN_IDX = 'generatorAnnotation';
    public static $RCS_ID_GEN = 'rcsSequenceAnnotation';

    private $assigned = false;

    public function generateAnnotationComponents(bool $removeTablePrefix = false): ?array
    {
        $attr = array();
        $extra = '';
        $itemAttributes = $this->completeItemAttributes($this->itemDesc->getAttributes());

        foreach ($itemAttributes as $k => $v) {
            switch ($k) {
                case 'name':
                    // name of getter
                    break;
                case 'column':
                    $attr['name'] = ($removeTablePrefix) ? $this->removeTablePrefix($v) : $v;
                    break;
                case 'type':
                    try {
                        $type = $this->itemDesc->getTypeAnnotation($v, true);
                        if (!empty($type)) {
                            $extra .= "\n" . $type;
                        }
                    } catch (Exception $e) {
                        $this->setError('Unsupported type "' . $v . '": ' . $e->getMessage());
                        return null;
                    }
                    break;
                case 'access':
                    $extra .= sprintf("\n@Access(AccessType.%s)", strtoupper($v));
                    break;
                case 'class':
                case 'sequence_name':
                case 'schema':
                case 'generator__value':
                    // do nothing, these are handled separately
                    break;
                default:
                    $this->setError('unknown attribute "' . $k . '"');
                    return null;
            }
        }

        $annotations = array();

        $this->generateSequenceAnnotations($annotations,
            empty($itemAttributes['class']) ? null : $itemAttributes['class'],
            empty($itemAttributes['sequence_name']) ? null : $itemAttributes['sequence_name'],
            empty($itemAttributes['schema']) ? null : $itemAttributes['schema']);

        $annotations['id'] = '@Id';

        $annotations[self::COLUMN_NAME] = "@Column(" . implode(', ', array_map(function ($k, $v) {
                return $k . ' = ' . self::quote($v);
            }, array_keys($attr), $attr)) . ')';

        $annotations[self::EXTRA_NAME] = $extra;

        return $annotations;
    }

    private function generateSequenceAnnotations(array &$annotations, string $class = null, string $sequence = null, string $schema = null): void
    {
        if (empty($class) || empty($sequence)) {
            return;
        }

        $sequence = strtoupper($sequence);
        $generatorName = $this->generateGeneratorName($sequence);
        $optParams = array();

        switch ($class) {
            case GeneratorType::ASSIGNED:
                $rcsSequenceAnnotation = sprintf("@RcsDbSequence(name = \"%s\")", $sequence);
                $this->assigned = true;
                break;
            case GeneratorType::SEQUENCE:
                // Sequences from other schema MAY be used.
                $schemaParam = empty($schema) ? '' : sprintf(", schema = \"%s\"", $schema);
                $generatorAnnotation = sprintf("@SequenceGenerator(name = \"%s\", sequenceName = \"%s\"%s)", $generatorName, $sequence, $schemaParam);
                break;
            case GeneratorType::SEQUENCE_STYLE:
            case GeneratorType::PERSISTENT_OBJ_ID:
                $optParams[] = sprintf("    @Parameter(name = \"sequence_name\", value = \"%s\"),", $sequence);
                if(!empty($optParams)){
                    $params = sprintf(", parameters = { //\n%s //\n}", implode(" //\n", $optParams));
                }else{
                    $params = '';
                }
                $generatorAnnotation = sprintf("@GenericGenerator(name = \"%s\", strategy = \"%s\"%s)", $generatorName, $class, $params);
                break;
            default:
                $this->setError("Unknown GeneratorType " . $class);
                return; // abort, we don't know this one
        }

        $annotations[self::$GEN_VAL_IDX] = $this->generateGeneratedValueAnnotation($generatorName);

        if (!empty($generatorAnnotation)) {
            $annotations[self::$GEN_IDX] = $generatorAnnotation;
        } else if (!empty($rcsSequenceAnnotation)) {
            $annotations[self::$RCS_ID_GEN] = $rcsSequenceAnnotation;
        }
    }

    private function generateGeneratorName(string $sequenceName): string
    {
        $generatorSuffix = "_GENERATOR";
        return $sequenceName . $generatorSuffix;
    }

    private function generateGeneratedValueAnnotation(string $generatorName): string
    {
        return sprintf("@GeneratedValue(generator = \"%s\", strategy = GenerationType.SEQUENCE)", $generatorName);
    }

    public function generateGeneratorAnnotation(bool $removeTablePrefix = false): ?string
    {
        $annotationComponents = $this->generateAnnotationComponents($removeTablePrefix);

        if (!empty($annotationComponents[self::$RCS_ID_GEN])) {
            return $annotationComponents[self::$RCS_ID_GEN];
        } else {
            return null;
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

    public function isAssigned(): bool
    {
        return $this->assigned;
    }
}

/**
 * Different Types of generators and how to treat them.
 */
abstract class GeneratorType
{
    public const ASSIGNED = 'assigned';
    public const SEQUENCE = 'sequence';
    public const SEQUENCE_STYLE = 'org.hibernate.id.enhanced.SequenceStyleGenerator';
    public const PERSISTENT_OBJ_ID = 'ch.sbb.aa.bb.cc.MyObsfuscatedClass';
}
