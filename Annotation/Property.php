<?php
require_once __DIR__ . './OverrideSupporting.php';
require_once __DIR__ . './VerifyBeforeWriteColumn.php';

class PropertyAnnotation extends OverrideSupporting implements VerifyBeforeWriteAnnotation
{
    use VerifyBeforeWriteColumnAnnotation;

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

                case 'length':
                case 'unique':
                    $attr[$k] = $v;
                    break;

                case 'insert':
                    $attr['insertable'] = $v;
                    break;

                case 'update':
                    $attr['updatable'] = $v;
                    break;

                case 'not-null':
                    $attr['nullable'] = NullableUtil::generateNullable((string)$v);
                    if(empty($attr['nullable'])){
                        return null;
                    }
                    break;

                case 'column':
                    $attr['name'] = strtoupper(($removeTablePrefix) ? $this->removeTablePrefix($v) : $v);
                    break;

                case 'type':
                    try {
                        $type = $this->itemDesc->getTypeAnnotation($v);
                        if (!empty($type)) {
                            $extra .= "\n" . $type; 
                        }
                    } catch (Exception $e) {
                        $this->setError('Unsupported type "' . $v . '": ' . $e->getMessage());
                        return null;
                    }
                    break;

                case 'access':
                    $type = strtoupper($v);
                    $extra .= "\n@Access(AccessType.$type)";
                    break;

                case 'lazy':
                    if ($k === 'true') {
                        $extra .= "\n@Basic(fetch = FetchType.LAZY)";
                    } else {
                        $extra .= "\n@Basic(fetch = FetchType.EAGER)";
                    }
                    break;

                case 'formula':
                    $formula = addslashes(preg_replace('/\s+/m', ' ', trim($v)));
                    $extra .= "\n@Formula(\"$formula\")";
                    break;
                    
                case 'read': // Used once, may be change the annotation to something useful.
                    $for = strtoupper(($removeTablePrefix) ? $this->removeTablePrefix($itemAttributes['name']) : $itemAttributes['name']);
                    $read = addslashes($v);
                    $extra .= "\n@ColumnTransformer(forColumn = \"$for\", read = \"$read\")";
                    break;

                default:
                    $this->setError('unknown attribute "' . $k . '" => "' . $v . '"');
                    return null;
            }
        }

        $annotations = array();

        // if the field name matches the column name, no attributes may be needed
        // thus if there are no attributes, no @Column annotation is needed
        $annotations[self::COLUMN_NAME] = '@Column(' . implode(', ', array_map(function ($k, $v) {
                return $k . ' = ' . self::quote($v);
            }, array_keys($attr), $attr)) . ')';


        if (!empty($extra)) {
            $annotations[self::EXTRA_NAME] = trim($extra);
        }

        return $annotations;
    }

    public function getName() : string
    {
        if (empty($this->itemDesc->getAttributes()['name'])) {
            print_r($this->itemDesc->getXml());
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            die("xx");
        }
        return $this->itemDesc->getAttributes()['name'];
    }
}