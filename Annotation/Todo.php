<?php

class TodoAnnotation extends Annotation
{
    const IDENTIFIER = '@HIBERNATE';

    private static $count = 0;
    private static $transCount = 0;
    private static $idCount = 0;

    public function generateAnnotationComponents(bool $removeTablePrefix = false): ?array
    {
        return array(self::generateTodoAnnotation());
    }

    public static function generateTodoAnnotation(): string
    {
        return self::generateCommon(self::IDENTIFIER, "", ++self::$count);
    }

    public static function generateTransientTodoAnnotation(): string
    {
        return self::generateCommon(self::IDENTIFIER, "TRANSIENT!", ++self::$transCount);
    }

    public static function generateIdTodoAnnotation(): string
    {
        return self::generateCommon(self::IDENTIFIER, "ID", ++self::$idCount);
    }

    private static function generateCommon(string $identifier, string $reason, int $count): string
    {
        return sprintf("// TODO %s %s MANUAL INTERVENTION #%02d\n", $identifier, $reason, $count);
    }

    public function getName()
    {
        if (empty($this->itemDesc->getAttributes()['name'])) {
            if(empty($this->itemDesc->getNodeName())){
                print_r($this->itemDesc->getXml());
                debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                die("xx");
            }
            return $this->itemDesc->getNodeName();
        }
        return $this->itemDesc->getAttributes()['name'];
    }
}