<?php
/**
 * Created by PhpStorm.
 * User: U225792
 * Date: 2018-09-19
 * Time: 14:40
 */

abstract class TokenInfo
{
    private $index = null;
    private $findIndex;

    private $annotations;

    private $classFinder;

    private $implementedInSuperclass;

    private $useCount = 0;

    private $name;

    protected static $instances = array();

    public function __construct(callable $findIndex, string $annotations, JavaClassFinder $classFinder, bool $implementedInSuperclass = false, string $name)
    {
        $this->findIndex = $findIndex;
        $this->annotations = $annotations;
        $this->classFinder = $classFinder;
        $this->implementedInSuperclass = $implementedInSuperclass;
        $this->name = $name;

        $this->classFinder->registerTokenInfo($this);
    }

    public function getIndex(): int
    {
        if ($this->index === null) {
            $this->index = ($this->findIndex)();
        }
        return $this->index;
    }

    public function forceIndexSearch(): void {
        $this->index = null;
    }

    /**
     * @return string The annotations above the methode/field in java class.
     */
    public function getAnnotations(): string
    {
        return $this->annotations;
    }

    public function appendAnnotations(array $annotations): void
    {
        if (empty($annotations)) {
            return;
        }

        $this->annotations = trim(
            $this->annotations . "\n" .
            implode("\n", $annotations)
        );
    }

    public function getClassFinder(): JavaClassFinder
    {
        return $this->classFinder;
    }

    public function isImplementedInSuperclass(): bool
    {
        return $this->implementedInSuperclass;
    }

    public function setImplementedInSuperclass(bool $implementedInSuperclass): void
    {
        $this->implementedInSuperclass = $implementedInSuperclass;
    }

    /**
     * @return string A unique identification for this java methode/field.
     */
    public function getIdent(): string
    {
        // echo $this->getClassFinder()->getFilePath() . ':' . $this->getIndex() . '____'. $this->getName() . "\n";
        return $this->getClassFinder()->getFilePath() . ':' . $this->getIndex();
    }

    /**
     * Get the use count of this token.
     * How many hbm file have a reference to this java code line.
     * Should onyly for fields id, version, creater, create_date ....  be greater 1.
     */
    public function getUseCount(): int
    {
        return $this->useCount;
    }

    public function setUseCount(int $useCount): void
    {
        $this->useCount = $useCount;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
