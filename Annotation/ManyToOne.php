<?php
require_once __DIR__ . '/OverrideSupporting.php';
require_once __DIR__ . '/VerifyBeforeWriteColumn.php';

class ManyToOneAnnotation extends OverrideSupporting implements VerifyBeforeWriteAnnotation
{
    use VerifyBeforeWriteColumnAnnotation;

    public function generateAnnotationComponents(bool $removeTablePrefix = false): ?array
    {
        $attr = array();
        $extra = '';
        $itemAttributes = $this->completeItemAttributes($this->itemDesc->getAttributes());

        $annotations = array();

        $colAttr = array();

        $hasUnique = false;

        foreach ($itemAttributes as $k => $v) {
            switch ($k) {
                case 'name':
                    // name of getter
                    break;
                case 'class':
                    //maybe type?
                    break;
                case 'cascade':
                    //TODO ask HHEN why One-To-Many is special
                    //$oneToManyAttr['cascade'] = strtoupper((string) $v);
                    if (strpos($v, 'delete-orphans') !== false) {
                        $attr['orphanRemoval'] = 'true';
                    } else if ($v === 'none' || empty($v)) {
                        break;
                    }
                    $annotations['cascade'] = CascadeAnnotation::generateAnnotation($v);
                    break;
                case 'unique':
                    $hasUnique = true;
                    break;
                case 'not-null':
                    $colAttr['nullable'] = NullableUtil::generateNullable((string)$v);
                    if(empty($colAttr['nullable'])){
                        return null;
                    }
                    break;
                case 'column':
                    $colAttr['name'] = ($removeTablePrefix) ? $this->removeTablePrefix($v) : $v;
                    break;
                case 'lazy':
                    // requires Hibernate
                    $annotations['lazy'] = sprintf('@LazyCollection(%s)', 'LazyCollectionOption.' . strtoupper($v));
                    break;
                case 'fetch':
                    $annotations['fetch'] = '@Fetch(FetchMode.' . strtoupper((string)$v) . ')';
                    break;
                default:
                    $this->setError('unknown attribute "' . $k . '" => "' . $v . '"');
                    return null;
            }
        }

        if (!empty($attr)) {
            $annotations[] = $hasUnique ? '@OneToOne(' : '@ManyToOne(' . implode(', ', array_map(function ($k, $v) {
                    return $k . ' = ' . self::quote($v);
                }, array_keys($attr), $attr)) . ')';
        } else {
            $annotations[] = $hasUnique ? '@OneToOne' : '@ManyToOne';
        }
        $annotations[self::COLUMN_NAME] = '@JoinColumn(' . implode(', ', array_map(function ($k, $v) {
                return $k . ' = ' . self::quote($v);
            }, array_keys($colAttr), $colAttr)) . ')';

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