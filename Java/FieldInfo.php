<?php
require_once __DIR__ . '/TokenInfo.php';

class FieldInfo extends TokenInfo
{
    private $isTransient;

    public static function createOrGetInstance(callable $findIndex, string $annotations, JavaClassFinder $classFinder, bool $implementedInSuperclass = false, string $name = '', bool $transient = false)
    {
        $key = $name;

        if (empty(self::$instances[$key])) {
            $instance = new FieldInfo($findIndex, $annotations, $classFinder, $implementedInSuperclass, $name);
            $instance->isTransient = $transient;
            self::$instances[$key] = $instance;
        }else{
            $instance = self::$instances[$key];
        }

        return $instance;
    }

    public function isTransient(): bool
    {
        return $this->isTransient;
    }
}
