<?php
require_once __DIR__ . './OverrideSupporting.php';
require_once __DIR__ . './VerifyBeforeWriteColumn.php';

class VersionAnnotation extends OverrideSupporting implements VerifyBeforeWriteAnnotation
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

                case 'column':
                    $attr['name'] = ($removeTablePrefix) ? $this->removeTablePrefix($v) : $v;
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

                default:
                    $this->setError('unknown attribute "' . $k . '"');
                    return null;
            }
        }

        $annotations = array();

        // while @Version could be in extra, the purpose of the field is clearer when it's the first annotation
        $annotations['version'] = '@Version';

        $annotations[self::COLUMN_NAME] = "@Column(" . implode(', ', array_map(function ($k, $v) {
                return $k . ' = ' . self::quote($v);
            }, array_keys($attr), $attr)) . ')';

        if (!empty($extra)) {
            $annotations[self::EXTRA_NAME] = trim($extra);
        }

        return $annotations;
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
}
