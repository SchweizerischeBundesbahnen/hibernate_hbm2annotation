<?php
require_once __DIR__ . '/TokenInfo.php';

class MethodInfo extends TokenInfo
{
    private $overwritten;

    public static function createOrGetInstance(callable $findIndex, string $annotations, JavaClassFinder $classFinder, bool $overwritten = false, bool $implementedInSuperclass = false, string $name = '')
    {
        $key = $name;
        if (empty(self::$instances[$key])) {
            self::$instances[$key] = new MethodInfo($findIndex, $annotations, $classFinder, $overwritten, $implementedInSuperclass, $name);
        }

        return self::$instances[$key];
    }

    public function __construct(callable $findIndex, string $annotations, JavaClassFinder $classFinder, bool $overwritten = false, bool $implementedInSuperclass = false, string $name = '')
    {
        parent::__construct($findIndex, $annotations, $classFinder, $implementedInSuperclass, $name);
        $this->overwritten = $overwritten;
    }

    public function isOverwritten()
    {
        return $this->overwritten;
    }
}
