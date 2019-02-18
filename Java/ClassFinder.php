<?php
require_once __DIR__ . '/TokenInfo.php';
require_once __DIR__ . '/FieldInfo.php';
require_once __DIR__ . '/MethodInfo.php';
require_once __DIR__ . '/../Utils/Array.php';
require_once __DIR__ . '/../Utils/ImportUtil.php';

class JavaClassFinder
{

    // @Example: ch.sbb.aa.bb.cc.MyObsfuscatedClass
    private $className;

    private $rootFilePath;

    private $filePath;

    private $lines = null;

    private $allSuperMethods = null;
    private $allSuperFields = null;

    private $superClass = -1;

    private static $class2fileCache = array();

    private static $lastInstances = array();
    const INSTANCE_CACHE_SIZE = 150;

    private static $methodesCache = array();
    private static $fieldsCache = array();

    private $localMethods = array();
    private $localFields = array();

    private $addedClassAnnotations = 0;

    /* the complete class annotations */
    private $annotations = null;

    private $attributeOverrideAnnotations = array();
    private $associationOverrideAnnotations = array();

    private $imports = null;

    // the imports for the annotations which will be added in the end
    private $annotationImports = array();

    private $tokenInfos = array();

    const JAVA_IMPORTS_ORDER = array( // Eclipse default order
        'java',
        'javax',
        'org',
        'com'
    );

    const IMPORT_GROUP_JAVAX = 'javax.persistence';
    const IMPORT_GROUP_HIBERNATE = 'org.hibernate';

    public static function getInstance(string $rootFilePath, string $className): JavaClassFinder
    {
        foreach (self::$lastInstances as $inst) {
            if ($inst->getClassName() === $className) {
                return $inst;
            }
        }

        $inst = new JavaClassFinder($rootFilePath, $className);
        //write current class to cache
        ArrayUtils::add(self::$lastInstances, $inst);

        // remove oldest class from cache
        if (count(self::$lastInstances) > self::INSTANCE_CACHE_SIZE) {
            array_shift(self::$lastInstances);
        }

        return $inst;
    }

    public static function clearInstanceCache()
    {
        self::$lastInstances = array();
    }

    function __construct(string $rootFilePath, string $className, string $filePath = null)
    {
        $this->className = $className;
        $this->rootFilePath = $rootFilePath;

        if (empty($filePath)) {
            $this->filePath = $this->findFile();
        } else {
            $this->filePath = $filePath;
        }
    }

    public function readFile()
    {
        if ($this->lines === null) {
            $this->lines = file($this->filePath);
        }
    }

    public static function fillFileCache(string $rootFilePath)
    {
        $cacheFile = __DIR__ . '/../class2fileCache.json';

        if (is_file($cacheFile)) {
            self::$class2fileCache = json_decode(file_get_contents($cacheFile), true);
            return;
        }

        $bundles = self::findBundles($rootFilePath);

        foreach ($bundles as $i => $bundle) {
            $javaFiles = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($bundle)), '/^.+\.java$/i', RecursiveRegexIterator::GET_MATCH);
            foreach ($javaFiles as $javaFile) {
                $className = self::getClassOfFile($javaFile[0]);
                if (!empty($className)) {
                    echo '*';
                    if (!empty(self::$class2fileCache[$className])) {
                        self::$class2fileCache[$className][] = $javaFile[0];
                    } else {
                        self::$class2fileCache[$className] = array($javaFile[0]);
                    }
                }
            }
            echo "\n" . round(($i + 1) / count($bundles) * 100, 2) . ' ';
        }

        file_put_contents($cacheFile, json_encode(self::$class2fileCache, JSON_PRETTY_PRINT));
    }

    private static function findBundles(string $rootFilePath): array
    {
        global $bundles;

        if (!empty($bundles)) {
            return $bundles;
        }

        $bundles = array();
        foreach (new DirectoryIterator($rootFilePath) as $fileInfo) {
            if (!$fileInfo->isDot() && $fileInfo->isDir()) {
                $bundle = $rootFilePath . DIRECTORY_SEPARATOR . $fileInfo->getFilename() . DIRECTORY_SEPARATOR;
                $bundles[] = $bundle;
            }
        }

        return $bundles;
    }

    private function findFile(): string
    {
        $parts = explode('.', $this->className);
        $className = end($parts);
        if (array_key_exists($className, self::$class2fileCache) && count(self::$class2fileCache[$className]) === 1) {
            return self::$class2fileCache[$className][0];
        }

        if (count($parts) < 3) {
            throw new InvalidClassException('Only absolute class names are supported "' . $this->className . '"');
        }

        try {
            $path = $this->findFileByBundleName($parts);
        } catch (Exception $e) {
            $path = $this->findFileByFileName($parts);
        }

        if (array_key_exists($className, self::$class2fileCache)) {
            self::$class2fileCache[$this->className][] = $path;
        } else {
            self::$class2fileCache[$this->className] = array($path);
        }

        return $path;
    }

    private function findFileByBundleName($parts): string
    {
        $bundle = $this->findBundle($parts);
        if (empty($bundle)) {
            throw new InvalidClassException('Unable to find bundle for "' . $this->className . '"');
        }

        $path = $this->rootFilePath . DIRECTORY_SEPARATOR . $bundle . DIRECTORY_SEPARATOR . 'src';
        foreach ($parts as $part) {
            $path .= DIRECTORY_SEPARATOR . $part;
        }
        $path .= '.java';

        if (!is_file($path)) {
            throw new InvalidClassException('Unable to find java file "' . $path . '" for class "' . $this->className . '"');
        }

        return $path;
    }

    private function findFileByFileName($parts): string
    {
        $path = 'src';
        foreach ($parts as $part) {
            $path .= DIRECTORY_SEPARATOR . $part;
        }
        $path .= '.java';

        foreach (new DirectoryIterator($this->rootFilePath) as $fileInfo) {
            if (!$fileInfo->isDot() && $fileInfo->isDir()) {
                $fullPath = $this->rootFilePath . DIRECTORY_SEPARATOR . $fileInfo->getFilename() . DIRECTORY_SEPARATOR . $path;
                if (is_file($fullPath)) {
                    return $fullPath;
                }
            }
        }

        throw new InvalidClassException('Unable to find java file "' . $path . '" for class "' . $this->className . '" in any bundle');
    }

    private function findBundle($parts): ?string
    {
        for ($i = count($parts) - 2; $i > 1; $i--) {
            $bundle = implode('.', array_slice($parts, 0, $i));
            if (is_dir($this->rootFilePath . DIRECTORY_SEPARATOR . $bundle)) {
                return $bundle;
            }
        }

        return null;
    }

    public function isAbstract(): bool
    {
        $classDef = $this->lines[$this->getClassDefIndex()];
        return strpos($classDef, ' abstract ') !== false;
    }

    public function getMethodeInfo(string $methodeName): ?MethodInfo
    {
        // echo '### ' . $methodeName . ' ### ' . $this->filePath . ' ### ' . $this->className . " ###\n";
        $index = $this->findIndexOfMethod($methodeName);
        if ($index === false) {
            return null;
        }

        $that = $this;

        $annotations = $this->getAnnotationsAbove($index);

        $overwritten = $this->isOverwritten($methodeName);

        $implementedInSuperclass = $this->isMethodeImplementedInSuperclass($methodeName);

        return MethodInfo::createOrGetInstance(
            function () use ($that, $methodeName) {
                return $that->findIndexOfMethod($methodeName);
            },
            $annotations,
            $this,
            $overwritten,
            $implementedInSuperclass,
            $this->getClassName() . '::' . $methodeName
        );
    }

    private function findIndexOfMethod(string $methodName)
    {
        return $this->findIndexOfExpression('/^\h*(public\h+)?[\<\>\[\]\w]+\h' . preg_quote($methodName) . '(\h|{)/');
    }

    public function getFieldInfo(string $fieldName): ?FieldInfo
    {
        $index = $this->findIndexOfField($fieldName);
        if ($index === false) {
            return null;
        }

        $that = $this;

        $annotations = $this->getAnnotationsAbove($index);

        $implementedInSuperclass = $this->isFieldImplementedInSuperclass($fieldName);

        return FieldInfo::createOrGetInstance(
            function () use ($that, $fieldName) {
                return $that->findIndexOfField($fieldName);
            },
            $annotations,
            $this,
            $implementedInSuperclass,
            $this->getClassName() . '::' . $fieldName,
            strpos($this->lines[$index], 'transient') !== false
        );
    }

    private function findIndexOfField(string $fieldName)
    {
        return $this->findIndexOfExpression('/\h*(private|protected|public)?\h+[a-zA-Z0-9_<>]+\h+(?<fieldName>' . preg_quote($fieldName) . ').*;/');
    }

    private function findIndexToAddImports(string $sampleToPlaceAfter = ''): int
    {
        if (!empty($sampleToPlaceAfter)) {
            $idx = $this->findIndexOfImportWithLowerPriority($sampleToPlaceAfter);
            if ($idx !== false) {
                return $idx;
            }
        }

        //regex to find some word (%s) followed by a FQ class/package name.
        $baseRegex = '/^%s\s(\w+\.)+\w+;\s*$/';
        // $idx = $this->findIndexOfExpression(sprintf($baseRegex, 'import'));
        // if($idx !== false){
        //     return $idx;
        // }
        $idx = $this->findIndexOfExpression(sprintf($baseRegex, 'package'));
        if ($idx !== false) {
            return $idx + 1; //insert below package
        }
        throw new Exception("ERR: no existing imports or package defs in " . $this->getClassName());
    }

    private function findIndexOfImportWithLowerPriority(string $sampleToPlaceAfter)
    {
        $importToParts = function ($import) {
            return explode('.', rtrim(trim(
                strtr($import, array('import ' => ''))
            ), ';'));
        };
        $needle = $importToParts(strtolower($sampleToPlaceAfter));

        $this->readFile();

        $maxLine = 200;
        if (count($this->lines) <= $maxLine) {
            $maxLine = count($this->lines) - 1;
        }

        for ($i = $maxLine; $i > 1; $i--) {
            $line = $this->lines[$i];

            $matches = array();
            if (preg_match('/\s*import ([\w\.]+)\;/', $line, $matches)) {
                $import = $importToParts(strtolower($matches[1]));

                if ($this->compareImports($needle, $import) < 0) {
                    return $i;
                }
            }
        }

        return false;
    }

    /**
     * Compare 2 imports and return file order
     *
     * @param array $a
     * @param array $b
     * @return integer
     *   0      = if both imputs are identical
     *  >0 ( 1) = if A should be over B in file
     *  <0 (-1) = if B should be over A in file
     */
    private function compareImports(array $a, array $b): int
    {
        $levelZeroOrder = self::JAVA_IMPORTS_ORDER;

        if ($a[0] != $b[0]) {
            if (in_array($a[0], $levelZeroOrder)) {
                if (in_array($b[0], $levelZeroOrder)) {
                    return array_search($b[0], $levelZeroOrder) - array_search($a[0], $levelZeroOrder);
                }

                return 1;
            }
            return -1;
        }

        return strcmp(
            implode('.', $b),
            implode('.', $a)
        );
    }

    private function findIndexOfExpression(string $expression)
    {
        $this->readFile();

        foreach ($this->lines as $index => $line) {
            if (preg_match($expression, $line)) {
                return $index;
            }
        }

        return false;
    }

    // $index = index of method
    private function getAnnotationsAbove(int $index): string
    {
        $this->readFile();

        $annotations = array();
        for ($i = ($index - 1); $i > 0; $i--) {
            $line = trim($this->lines[$i]);
            if (empty($line)) {
                // Skip empty lines
                continue;
            }

            if ($this->isAnnotation($line)) {
                $annotations[] = $line;
                continue;
            }

            if ($this->isComment($line)) {
                // Skip comments.
                continue;
            }

            // Found a methode or variable or ...
            break;
        }

        return implode("\n", $annotations);
    }

    /**
     * Does the given line start with:
     * - /@/ (a normal annotation)
     * - /})/ (the end of @AttributeOverride)
     * - /+ "/ (continuation of @Formula)
     */
    private function isAnnotation(string $line): bool
    {
        return (preg_match('/^\h*(@|}\)|\+\h*")/', $line) === 1);
    }

    private function isComment($line): bool
    {
        return substr($line, 0, 1) === '*' || substr($line, 0, 2) === '/*' || substr($line, 0, 1) === '//';
    }

    private function isOverwritten(string $methodeName): bool
    {
        $superMethods = $this->getAllSuperMethods();
        return !empty($superMethods[$methodeName]);
    }

    private function isMethodeImplementedInSuperclass(string $methodName): bool
    {
        $matchableMethodName = substr($methodName, 0, -2);
        $localMethods = $this->getLocalMethods();
        if (in_array($matchableMethodName, $localMethods)) {
            return false;
        }
        $superMethods = $this->getAllSuperMethods();
        if (in_array($matchableMethodName, $superMethods)) {
            return true;
        }
        return false; // I don't think this should occur // special case
    }

    private function getAllSuperMethods()
    {
        if ($this->allSuperMethods === null) {
            $this->allSuperMethods = array();

            $superClass = $this->getSuperClass();
            if (empty($superClass)) {
                return false;
            }

            $super = JavaClassFinder::getInstance($this->rootFilePath, $superClass);
            $this->allSuperMethods = $super->getAllMethodesRecurisive();
        }
        return $this->allSuperMethods;
    }

    public function getAllMethodesRecurisive($level = 0): array
    {
        if (!empty(self::$methodesCache[$this->className])) {
            return self::$methodesCache[$this->className];
        }

        $this->readFile();

        $methodes[] = $this->getLocalMethods();

        $superClass = $this->getSuperClass();
        if (!empty($superClass)) {
            $super = JavaClassFinder::getInstance($this->rootFilePath, $superClass);
            //recursively for all superclasses
            $methodes = ArrayUtils::flatten(array_merge($methodes, $super->getAllMethodesRecurisive($level + 1)));
        }

        if ($level > 0) {
            // caching
            self::$methodesCache[$this->className] = array_unique($methodes);
            return self::$methodesCache[$this->className];
        }

        return array_unique($methodes);
    }

    /**
     * Returns a list of all public methods of this class that start with "get" or "is"
     */
    public function getLocalMethods(): array
    {
        if (!empty($this->localMethods)) {
            return $this->localMethods;
        }

        $this->readFile();

        $methods = [];
        foreach ($this->lines as $index => $line) {
            if (preg_match('/^\h*(public\s+)?\w+\s(?<method>(get|is)\w+)\(\)\s/', $line, $matches) !== false && !empty($matches['method'])) {
                $methods[] = $matches['method'];
            }
        }

        if (!empty($methods)) {
            $this->localMethods = $methods;
        }

        return $methods;
    }

    private function isFieldImplementedInSuperclass(string $fieldName): bool
    {
        $localFields = $this->getLocalFields();
        if (in_array($fieldName, $localFields)) {
            return false;
        }
        $superFields = $this->getAllSuperFields();
        if ($superFields !== false && in_array($fieldName, $superFields)) {
            return true;
        }
        return false; // I don't think this should occur // special case
    }

    /**
     * Returns a list of all public methods of this class that start with "get" or "is"
     */
    public function getLocalFields(): array
    {
        if (!empty($this->localFields)) {
            return $this->localFields;
        }

        $this->readFile();

        $fields = [];
        foreach ($this->lines as $index => $line) {
            // Auf package visibility wird verzichtet da ansonsten viele false positive mit localen variablen und anderen haben. 
            // Und die anzahl von { } zählen um class oder methode context zu unterscheiden ist zu aufwändig.
            if (preg_match('/^\h*(private|protected|public)\h+(?<type>[a-zA-Z0-9_<>]+)\h+(?<fieldName>\w+)\s*[\;\=]/', $line, $matches) !== false && !empty($matches['fieldName'])) {
                $fields[] = $matches['fieldName'];
            }
        }

        if (!empty($fields)) {
            $this->localFields = $fields;
        }

        return $fields;
    }

    private function getAllSuperFields()
    {
        if ($this->allSuperFields === null) {
            $this->allSuperFields = array();

            $superClass = $this->getSuperClass();
            if (empty($superClass)) {
                return false;
            }

            $super = JavaClassFinder::getInstance($this->rootFilePath, $superClass);
            $this->allSuperFields = $super->getAllFieldsRecurisive();
        }
        return $this->allSuperFields;
    }

    public function getAllFieldsRecurisive($level = 0): array
    {
        if (!empty(self::$fieldsCache[$this->className])) {
            return self::$fieldsCache[$this->className];
        }

        $this->readFile();

        $fields[] = $this->getLocalFields();

        $superClass = $this->getSuperClass();
        if (!empty($superClass)) {
            $super = JavaClassFinder::getInstance($this->rootFilePath, $superClass);
            //recursively for all superclasses
            $fields = ArrayUtils::flatten(array_merge($fields, $super->getAllFieldsRecurisive($level + 1)));
        }

        if ($level > 0) {
            // caching
            self::$fieldsCache[$this->className] = array_unique($fields);
            return self::$fieldsCache[$this->className];
        }

        return array_unique($fields);
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public static function getClassOfFile(string $fileName): ?string
    {
        $handle = fopen($fileName, 'r');
        if ($handle) {
            $i = 0;
            while (($line = fgets($handle)) !== false) {
                $i++;

                if (preg_match('/^\s*(public)?(\s+abstract)?(\s+final)?\sclass\s(?<class>\w+)/', $line, $matches) && !empty($matches['class'])) {
                    return $matches['class'];
                }

                if ($i > 150) {
                    return null;
                }
            }

            fclose($handle);
        }

        return null;
    }

    public function getSuperClassInstance(): ?JavaClassFinder
    {
        $superName = $this->getSuperClass();
        if (empty($superName)) {
            return null;
        }
        return JavaClassFinder::getInstance($this->rootFilePath, $superName);
    }

    public function getSuperClass(): ?string
    {
        // has been searched (null = was searched but has no super class)
        if ($this->superClass !== -1) {
            return $this->superClass;
        }

        $this->readFile();

        $parts = explode('.', $this->className);
        $className = end($parts);
        $extends = null;
        $extendsIndex = 0;
        $i = 0;
        foreach ($this->lines as $index => $line) {
            // finds class definition (matches "extends")
            $matches = [];
            if ($this->isClassDef($className, $line, $matches)) {
                if (empty($matches['extends'])) {
                    $this->superClass = null;
                    return $this->superClass;
                }
                $extendsIndex = $index;
                $extends = $matches['extends'];
                break;
            }

            $i++;

            if ($i > 150) {
                break;
            }
        }
        if (empty($extends)) {
            $this->superClass = null;
            return $this->superClass;
        }

        $package = null;
        foreach ($this->lines as $index => $line) {
            // finds package
            if (preg_match('/^\s*package (?<package>[\w\.]+)\;/', $line, $matches)) {
                $package = $matches['package'];
                // if superclass is in import, it's not in package
            } else if (preg_match('/^\s*import (?<className>[\w\.]+\.' . preg_quote($extends) . ')\;/', $line, $matches)) {
                $this->superClass = $matches['className'];
                return $this->superClass;
            }

            if ($index >= $extendsIndex) {
                if (empty($package)) {
                    throw new InvalidClassException('Unable to find import of extends "' . $extends . '" in "' . $this->filePath . '"');
                }

                // if no import found, assume superclass is in same package
                $this->superClass = $package . '.' . $extends;
                return $this->superClass;
            }
        }

        $this->superClass = null;
        return $this->superClass;
    }

    public function addAnnotation(TokenInfo $tokenInfo, string $annotationStr, bool $addImports = false)
    {
        $this->readFile();

        // We start processing at the bottom to not correct the index to mutch but class annotations (AttributeOverwrite), will allways be add at the top of the file.
        $index = $tokenInfo->getIndex() + $this->addedClassAnnotations;

        // finds required whitespaces in front of the annotation
        $prefix = $this->findWhitespacePrefix($this->lines[$index]);

        $lineEnding = $this->getLineEnding();
        $annotations = array_map(function ($l) use ($prefix, $lineEnding) {
            return $prefix . rtrim($l) . $lineEnding;
        }, array_filter(explode("\n", $annotationStr), function ($l) {
            return !empty(trim($l));
        }));

        array_splice($this->lines, $index, 0, $annotations);

        $tokenInfo->appendAnnotations($annotations);
    }

    public function addClassAnnotation(string $annotationStr)
    {
        $this->readFile();
        $classDefIdx = $this->getClassDefIndex();

        $lineEnding = $this->getLineEnding();
        $annotations = array_map(function ($l) use ($lineEnding) {
            return rtrim($l) . $lineEnding;
        }, explode("\n", $annotationStr));

        $this->addedClassAnnotations += count($annotations);

        array_splice($this->lines, $classDefIdx, 0, $annotations);

        $this->appendAnnotations($annotationStr);
    }

    /**
     * The ClassFinder will remember, but not actually add this annotation yet.
     * Call generateFinalFieldOverrideAnnotations() to generate the final version and add it
     */
    public function addFieldOverrideAnnotation(string $annotationStr)
    {
        if (strpos($annotationStr, '@AttributeOverride(') === 0) {
            $this->attributeOverrideAnnotations[] = $annotationStr;
        } else if (strpos($annotationStr, '@AssociationOverride(') === 0) {
            $this->associationOverrideAnnotations[] = $annotationStr;
        } else {
            echo sprintf("ERROR: unknown fieldOverrideAnnotation: %s in %s", $annotationStr, $this->getClassName());
            die("err");
        }
    }

    public function writeMappedSuperclassAnnotation()
    {
        $annotationStr = '@MappedSuperclass';
        if (strpos($this->getAnnotations(), '@Entity') === false &&
            strpos($this->getAnnotations(), $annotationStr) === false) {
            $this->addClassAnnotation($annotationStr);
            $this->writeFile();
        }
    }

    private function appendAnnotations($annotationStr): void
    {
        if ($this->annotations === null) {
            $this->getClassDefIndex();
        }

        $this->annotations = trim(
            $this->annotations . "\n" .
            $annotationStr
        );
    }

    public function getFieldOverrideAnnotations(): array
    {
        return array_merge($this->attributeOverrideAnnotations, $this->associationOverrideAnnotations);
    }

    public function getAnnotations(): string
    {
        if ($this->annotations === null) {
            $this->getClassDefIndex();
        }

        return $this->annotations;
    }

    private function getClassDefIndex(): int
    {
        $this->readFile();
        foreach ($this->lines as $index => $line) {
            // finds class definition (matches "extends")
            if ($this->isClassDef($this->className, $line)) {
                $this->annotations = $this->getAnnotationsAbove($index);
                return $index;
            }
        }
        throw new InvalidClassException('"' . $this->className . '" does not appear to contain a class definition');
    }

    public function isClassDef(string $classNameFQ, string $line, array &$matches = array()): bool
    {
        $classNameParts = explode('.', $classNameFQ);
        $className = end($classNameParts);
        return preg_match('/^\s*(public\s*)?(abstract\s*)?(final\s*)?class\s' . preg_quote($className) . '(\<[\w\<\>\ ]+\>)?\s([\w\s]*?)(extends (?<extends>\w+))?/', $line, $matches);
    }

    public function isFieldDef(string $fieldName, string $line, array &$matches = array()): bool
    {
        return preg_match('/^\h*(private|protected|public)*\h+[a-zA-Z0-9_<>]+\h+(?<fieldName>' . preg_quote($fieldName) . ');$/', $line, $matches);
    }

    public function getLineEnding(): string
    {
        $this->readFile();
        return (substr($this->lines[2], -2, 1) === "\r") ? "\r\n" : "\n";
    }

    public function writeFile()
    {
        $this->preSaveAction();
        file_put_contents($this->filePath, implode('', $this->lines));

        // Reset all tokenInfo indexes, because if we have written some at the top all indexes below are now false.
        foreach ($this->tokenInfos as $tokenInfo) {
            $tokenInfo->forceIndexSearch();
        }
    }

    private function preSaveAction()
    {
        $neededImports = ImportUtil::generateImports($this->lines);

        $importGroups = [self::IMPORT_GROUP_JAVAX, self::IMPORT_GROUP_HIBERNATE];
        $this->detectImportGroups($importGroups, $neededImports);

        $addedImports = [];

        foreach ($importGroups as $importGroup) {
            $imports = $this->generateAnnotationImports($importGroup, $neededImports);
            if (!empty($imports)) {
                sort($imports);

                $imports = array_filter($imports, function ($import) use ($addedImports) {
                    return !in_array($import, $addedImports);
                });

                if (empty($imports)) {
                    continue;
                }

                ArrayUtils::addAll($addedImports, $imports);
                $this->addAnnotationImportsToFile($imports);
            }
        }
    }

    private function detectImportGroups(array &$importGroups, array $neededImports): void
    {
        // Filter imports from known groups.
        foreach ($importGroups as $group) {
            $neededImports = array_filter($neededImports, function ($import) use ($group) {
                return strpos($import, $group) === false;
            });
        }

        // Detect unknown groups.
        foreach ($neededImports as $import) {
            $parts = explode('.', $import);
            array_pop($parts); // Assume that the bundle is equal the package. Because to detect the bundle name is hard.
            $bundle = implode('.', $parts);

            if (!in_array($bundle, $importGroups)) {
                $importGroups[] = $bundle;
            }
        }
    }

    private function generateAnnotationImports(string $group, array $neededImports): array
    {
        $newImports = array_filter($neededImports, function ($import) use ($group) {
            return strpos($import, $group) !== false;
        });
        return array_map(function ($import) {
            return sprintf("import %s;%s", trim($import), $this->getLineEnding());
        }, $newImports);
    }

    /**
     * Generates the final (wrapped) form of the field override annotations previously generated
     */
    public function generateFinalFieldOverrideAnnotations(): string
    {
        $overrideAnnotations = $this->addFieldOverrideAnnotations("@AttributeOverrides({ //\n    %s //\n})\n", $this->attributeOverrideAnnotations);
        $overrideAnnotations .= $this->addFieldOverrideAnnotations("@AssociationOverrides({ //\n    %s //\n})\n", $this->associationOverrideAnnotations);
        return $overrideAnnotations;
    }

    private function addFieldOverrideAnnotations(string $wrapper, array $annotations): string
    {
        if (empty($annotations)) {
            return '';
        }
        // array_reverse because this puts them in the same order as the getters they're overriding
        return sprintf($wrapper, implode(", //\n    ", array_reverse($annotations)));
    }

    public function printDebug(): void
    {
        echo $this->className . " --- " . $this->filePath . "\n";
        print_r($this->lines);
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getImports(): array
    {
        if ($this->imports === null) {
            $this->imports = array();
            for ($i = 0; $i < $this->getClassDefIndex(); $i++) {
                $line = trim($this->lines[$i]);
                if (empty($line)) {
                    continue;
                }

                if (substr($line, 0, 7) === 'import ') {
                    $this->imports[] = rtrim(substr($line, 7), ';');
                }
            }
        }

        return $this->imports;
    }

    public function addAnnotationImports(array $imports): void
    {
        $this->annotationImports = array_merge($this->annotationImports, $imports);
    }

    private function addAnnotationImportsToFile(array $imports): void
    {
        $this->readFile();
        $idx = $this->findIndexToAddImports($imports[0]) + 1;

        array_unshift($imports, $this->getLineEnding()); // Add empty line after block.
        ArrayUtils::insert($imports, $idx, $this->lines);
    }

    public function registerTokenInfo(TokenInfo $tokenInfo): void
    {
        $this->tokenInfos[] = $tokenInfo;
    }

    /**
     * add @Transient to every getter that doesn't have persistence annotations.
     */
    public function addTransient(): int
    {
        try {
            $classAnnotations = $this->getAnnotationsAbove($this->getClassDefIndex());
        } catch (InvalidClassException $e) {
            // if class doesn't contain a classdef we didn't annotate it anyways
            return 0;
        }

        // if this class isn't relevant, return
        if (strpos($classAnnotations, '@Entity') === false && strpos($classAnnotations, '@MappedSuperclass') === false) {
            return 0;
        }

        $getters = array();
        foreach ($this->lines as $idx => $line) {
            if (preg_match('/^\h*(public\h+)?(final\h)?(?<type>[\<\>\[\]\w]+|\w+<.+>)(?<!return)\h(?<method>(get|is)\w+)\(\)(\h|{)/', $line) === 1) {
                $annotationsAbove = trim($this->getAnnotationsAbove($idx));
                if (!ImportUtil::containsPersistenceAnnotations($annotationsAbove) && strpos($this->lines[$idx - 1], TodoAnnotation::IDENTIFIER) === false) {
                    $getters[$idx] = $line;
                }
            } else if (strpos($line, "static class") !== false && strpos($line, "implements Serializable") !== false) {
                // this is a workaround for Composite-IDs, as the getters for the ID components
                // don't require annotations, but also cannot be marked @Transient.
                // This workaround requires the CompositeKey inner class to be the last thing in the class.
                break;
            }
        }

        $transientAnnotation = '@Transient' . $this->getLineEnding();

        $count = 0;

        if (!empty($getters)) {
            // place annotations bottom-up, so the indices don't change
            $getters = array_reverse($getters, true);
            foreach ($getters as $idx => $getter) {
                $prefix = $this->findWhitespacePrefix($this->lines[$idx]);
                ArrayUtils::insert($prefix . $transientAnnotation, $idx, $this->lines);
                $count++;
            }
        }

        $this->writeFile();
        return $count;
    }

    private function findWhitespacePrefix(string $line): string
    {
        $matches = array();
        preg_match('/^(?<prefix>\h*).+$/', $line, $matches);
        return empty($matches['prefix']) ? '' : $matches['prefix'];
    }
}

class InvalidClassException extends Exception
{
}