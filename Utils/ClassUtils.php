<?php

class ClassUtils
{
    /**
     * Finds the top-most class of the hierarchy and names the sequence after it
     */
    public static function findMostSuperClass(JavaClassFinder $class, &$allClasses = array()): JavaClassFinder
    {
        $super = $class->getSuperClassInstance();
        if (empty($super)) {
            return $class;
        }

        $allClasses[] = $super;
        return self::findMostSuperClass($super, $allClasses);
    }

    public static function findAllSuperClasses(JavaClassFinder $class): array
    {
        $allClases = array();
        self::findMostSuperClass($class, $allClases);
        return $allClases;
    }

    /**
     * generate Instance from class name, which may or may not be FQ
     */
    public static function getInstance(string $className, JavaClassFinder $caller, string $rootPath): JavaClassFinder
    {
        $targetParts = explode('.', $className);
        if(count($targetParts) < 3){
            //not FQ
            $callerFQName = $caller->getClassName();
            $callerParts = explode('.', $callerFQName);
            $callerParts[count($callerParts) - 1] = end($targetParts);
            $targetParts = $callerParts;
        }

        $classNameFQ = implode('.', $targetParts);

        $targetClass =  JavaClassFinder::getInstance($rootPath, $classNameFQ);
        if(empty($targetClass)){
            throw new Exception(sprintf("ERROR:: no such class %s (generated from %s)", $classNameFQ, $className));
        }
        return $targetClass;
    }
}