<?php
require_once __DIR__ . './OverrideSupporting.php';
require_once __DIR__ . './VerifyBeforeWriteColumn.php';

class DiscriminatorAnnotation extends Annotation
{
    public function generateAnnotationComponents(bool $removeTablePrefix = false): ?array
    {
        $attr = array();
        $itemAttributes = $this->itemDesc->getAttributes();

        $annotations = array();

        foreach ($itemAttributes as $k => $v) {
            switch ($k) {
                case 'not-null':
                    $attr['nullable'] = NullableUtil::generateNullable((string)$v);
                    if(empty($attr['nullable'])){
                        return null;
                    }
                    break;
                case 'column':
                    $prefix = '@DiscriminatorColumn(';
                    $attr['name'] = ($removeTablePrefix) ? $this->removeTablePrefix($v) : $v;
                    break;
                case 'formula':
                    $prefix = '@DiscriminatorFormula(';
                    $attr['formula'] = $v;
                    break;
                case 'type':
                    // string is the default, don't write it.
                    if(strtoupper($v) !== 'STRING'){
                        $attr['discriminatorType'] = 'DiscriminatorType.' . strtoupper($v);
                    }
                    break;
                default:
                    $this->setError('unknown attribute "' . $k . '" => "' . $v . '"');
                    return null;
            }
        }

        if (!(array_key_exists('column', $itemAttributes) xor array_key_exists('formula', $itemAttributes))) {
            $this->setError("Col and Formula set at the same time");
        }

        $annotations[self::COLUMN_NAME] = $prefix . implode(', ', array_map(function ($k, $v) {
                if ($k === 'formula') { // formula only takes one arg
                    return self::quote($v);
                }
                return $k . ' = ' . self::quote($v);
            }, array_keys($attr), $attr)) . ')';

        return $annotations;
    }
}