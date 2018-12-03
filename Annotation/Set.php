<?php
require_once __DIR__ . './JoinTable.php';

class SetAnnotation extends Annotation implements VerifyBeforeWriteAnnotation
{
    /**
     * @param bool $removeTablePrefix
     * @return array|null
     */
    public function generateAnnotationComponents(bool $removeTablePrefix = false): ?array
    {
        $attr = array();
        $oneToManyAttr = array();
        $manyToManyAttr = array();
        $joinTableAttributes = array();

        $annotations = array();

        $rawAttributes = $this->itemDesc->getAttributes();

        $hasOneToMany = !empty($rawAttributes['one-to-many']);
        $hasElement = !empty($rawAttributes['element']);
        $hasManyToMany = !empty($rawAttributes['many-to-many']);

        foreach ($rawAttributes as $k => $v) {
            switch ($k) {
                case 'name':
                    // name of getter
                    break;
                case 'batch-size':
                    $annotations[] = '@BatchSize(size = ' . (int)$v . ')';
                    break;
                case 'fetch':
                    $annotations[] = '@Fetch(FetchMode.' . strtoupper((string)$v) . ')';
                    break;
                case 'access':
                    $annotations[] = sprintf('@Access(AccessType.%s)', strtoupper($v));
                    break;
                case 'one-to-many':
                    //targetEntity not needed
                    break;
                case 'cascade':
                    if ($hasOneToMany || $hasManyToMany) {
                        if (strpos($v, 'delete-orphans') !== false) {
                            $oneToManyAttr['orphanRemoval'] = 'true';
                        } else if ($v === 'none' || empty($v)) {
                            break;
                        }
                        $annotations['cascade'] = CascadeAnnotation::generateAnnotation($v);
                    } else {
                        $this->setError('cascade without one-to-many is not supported');
                        return null;
                    }
                    break;
                case 'key':
                    if ($hasManyToMany) {
                        $joinTableAttributes['joinColumns'] = $this->generateKeyAnnotation($v);
                    } else if ($hasOneToMany && (empty($rawAttributes['inverse']) || strtolower((string)$rawAttributes['inverse']) !== 'true')) {
                        $annotations['joinColumn'] = $this->generateKeyAnnotation($rawAttributes['key']);
                    }
                    break;
                case 'element':
                    if ($hasOneToMany || $hasManyToMany) {
                        $this->setError('element and one-to-many is not supported');
                        return null;
                    }

                    $annotations[] = sprintf('@CollectionTable(name = "%s", joinColumns = %s)',
                        (string)$rawAttributes['table'],
                        $this->generateKeyAnnotation($rawAttributes['key'])
                    );

                    $columnExtra = '';
                    // <element type=*  will be thrown away.
                    if (!empty($v->getAttributes()['not-null'])) {
                        $nullableParam = NullableUtil::generateNullable((string)$v);
                        if (empty($nullableParam)) {
                            return null;
                        }
                        $columnExtra .= sprintf(', nullable = %s', $nullableParam);
                        break;
                    }

                    $annotations[] = sprintf('@Column(name = "%s"%s)', $v->getAttributes()['column'], $columnExtra);
                    break;
                case 'table':
                    if ($hasManyToMany) {
                        $joinTableAttributes['name'] = sprintf('"%s"', $v);
                        break;
                    }
                    if (!$hasElement && !$hasOneToMany) {
                        // in one-to-many we just throw it away
                        $this->setError('table is only supported with element');
                        return null;
                    }
                    break;
                case 'many-to-many':
                    $joinTableAttributes['inverseJoinColumns'] = sprintf('@JoinColumn(name = "%s")', $v->getAttributes()['column']);
                    break;
                case 'lazy':
                    // requires Hibernate
                    if ($hasOneToMany || $hasManyToMany) {
                        $annotations[] = sprintf('@LazyCollection(%s)', 'LazyCollectionOption.' . strtoupper($v));
                    } else {
                        $this->setError('lazy without one-to-many or many-to-many is not supported');
                        return null;
                    }
                    break;
                case 'inverse':
                    $v = strtolower((string)$v);
                    if ($v === 'true') {
                        if ($hasOneToMany) {
                            $inverseClassName = $this->findInverseClassName($rawAttributes);
                            $inverseHbmParser = $this->findInverseHbmParser($inverseClassName);

                            $key = $this->itemDesc->getAttributes()['key'];
                            if (array_key_exists('column', $key->getAttributes())) {
                                $joinColumn = $key->getAttributes()['column'];

                                foreach ($inverseHbmParser->getItemDescs() as $item) {
                                    if ($item->getNodeName() === 'many-to-one') {
                                        $inverseJoinColumn = (string)($item->getAttributes()['column']);
                                        if ($inverseJoinColumn === $joinColumn) {
                                            $oneToManyAttr['mappedBy'] = $item->getAttributes()['name'];
                                        }
                                    }
                                }
                            } else {
                                echo('WARN:: no attribute "column" for ' . $this->itemDesc->getNodeName() . '::' . $rawAttributes['name'] . ' in ' . $this->parser->getHbmFile() . PHP_EOL);
                            }
                        }
                        $this->setError('inverse is only allowed in one-to-many relations');
                    }
                    break;
                case 'mutable':
                    $v = strtolower((string)$v);
                    if ($v !== 'true') { // True is the default value.
                        $annotations[] = '@Immutable';
                    }
                    break;
                case 'list-index':
                    $annotations[] = sprintf('@OrderColumn(name="%s")', (string)$v->getAttributes()['column']);
                    break;
                case 'order-by':
                    $annotations[] = sprintf('@OrderBy("%s")', (string)$v);
                    break;
                case 'sort':
                    // requires Hibernate
                    $v = strtolower((string)$v);
                    if ($v === 'natural') {
                        $annotations[] = '@SortNatural';
                    } else {
                        $this->setError('unsupported value for "sort"="' . (string)$v . '"');
                        return null;
                    }
                    break;

                case 'collection-type':
                case 'composite-element':
                case 'filter':
                case 'index':
                case 'subselect':
                case 'where':
                default:
                    $this->setError('unknown/unsupported attribute "' . $k . '"');
                    return null;
            }
        }

        if ($hasOneToMany === true) {

            if (empty($oneToManyAttr)) {
                $annotations[] = '@OneToMany';
            } else {
                $annotations[] = '@OneToMany(' . implode(', ', array_map(function ($k, $v) {
                        return $k . ' = ' . self::quote($v) . '';
                    }, array_keys($oneToManyAttr), $oneToManyAttr)) . ')';
            }
        } else
            if ($hasManyToMany === true) {
                if (empty($manyToManyAttr)) {
                    $annotations[] = '@ManyToMany';
                } else {
                    $annotations [] = '@ManyToMany(' . implode(', ', array_map(function ($k, $v) {
                            return $k . ' = ' . self::quote($v) . '';
                        }, array_keys($manyToManyAttr), $manyToManyAttr)) . ')';
                }
                $annotations [] = JoinTableAnnotation::generateAnnotation($joinTableAttributes);
            }
        return $annotations;
    }

    private
    function generateKeyAnnotation(ItemDescription $keyItem): ?string
    {
        $attr = array();
        foreach ($keyItem->getAttributes() as $k => $v) {
            switch ($k) {
                case 'column':
                    $attr['name'] = $v;
                    break;
                case 'not-null':
                    $attr['nullable'] = NullableUtil::generateNullable((string)$v);
                    if (empty($attr['nullable'])) {
                        return null;
                    }
                    break;
                case 'update':
                    $attr['updatable'] = $v;
                    break;
                case 'property-ref':
                default:
                    $this->setError('unknown attribute for <key>: "' . $k . '"');
                    return null;
            }
        }

        return '@JoinColumn(' . implode(', ', array_map(function ($k, $v) {
                return $k . ' = ' . self::quote($v) . '';
            }, array_keys($attr), $attr)) . ')';
    }

    public
    function getName(): string
    {
        if (empty($this->itemDesc->getAttributes()['name'])) {
            print_r($this->itemDesc->getXml());
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            die("xx");
        }
        return $this->itemDesc->getAttributes()['name'];
    }

    public
    function checkFieldOverrideAnnotation(string $currentTotalAnnotations): bool
    {
        return $this->checkAnnotation($currentTotalAnnotations, false);
    }

    public
    function checkAnnotation(string $currentTotalAnnotations, bool $removeTablePrefix = false): bool
    {
        return
            strpos($currentTotalAnnotations, '@Column') === false &&
            strpos($currentTotalAnnotations, '@OneToMany') === false;
    }

    public function findInverseClassName($rawAttributes): string
    {
        $inverseClass = $rawAttributes['one-to-many']->getAttributes()['class'];
        $qualifiedClassName = explode('.', $inverseClass);
        return $qualifiedClassName[sizeof($qualifiedClassName) - 1]; //get last element
    }

    public function findInverseHbmParser($className): HbmParser
    {
        $converter = new HbmConverter($this->parser->getRootFilePath());
        $converter->iterateFiles(1, ".*$className\.hbm");
        return $converter->getParsers()[0];
    }
}
