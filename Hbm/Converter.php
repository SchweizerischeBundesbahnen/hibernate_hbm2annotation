<?php
require_once __DIR__ . '/Parser.php';
require_once __DIR__ . '/../Utils/Colors.php';
require_once __DIR__ . '/../Utils/Array.php';

class HbmConverter
{

    private $rootFilePath;

    private $parsers = array();

    public function getParsers(): array
    {
        return $this->parsers;
    }

    public function __construct($rootFilePath)
    {
        $this->rootFilePath = $rootFilePath;
    }

    public function iterateFiles(int $maxFiles = 0, string $hbmlFilter = null): void
    {
        $cacheFile = __DIR__ . '/../hbms.json';
        if (is_file($cacheFile)) {
            $hbmFiles = json_decode(file_get_contents($cacheFile), true);
        } else {
            $hbmFiles = array();
            $Directory = new RecursiveDirectoryIterator($this->rootFilePath);
            $Iterator = new RecursiveIteratorIterator($Directory);
            $Regex = new RegexIterator($Iterator, '/^.+\.hbm\.xml$/i', RecursiveRegexIterator::GET_MATCH);
            $i = 0;
            foreach ($Regex as $info) {
                if (strpos($info[0], DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR) !== false) {
                    continue;
                }

                $i++;
                if (!empty($maxFiles) && $i > $maxFiles) {
                    break;
                }
                $hbmFiles[] = $info[0];
            }

            if (empty($maxFiles)) {
                file_put_contents($cacheFile, json_encode($hbmFiles, JSON_PRETTY_PRINT));
            }
        }

        $i = 0;
        foreach ($hbmFiles as $hbmFile) {
            if (!empty($hbmlFilter) && !preg_match('/' . $hbmlFilter . '/i', $hbmFile)) {
                continue;
            }

            $i++;
            if (!empty($maxFiles) && $i > $maxFiles) {
                break;
            }

            $this->parseFile($hbmFile);
        }
    }

    public function parseFile(string $hbmFile): void
    {
        $rootXmlElement = $this->simpleXMLElementFromFile($hbmFile);
        $mapping = $rootXmlElement->xpath('//hibernate-mapping');

        $schema = (string)$rootXmlElement->attributes()['schema'];

        $package = (!empty($mapping[0]->attributes()->package)) ? $mapping[0]->attributes()->package : null;

        $classes = $rootXmlElement->xpath('//hibernate-mapping/class');
        // case $nodeName == 'id':echo "File $i = $hbmFile\n";
        foreach ($classes as $class) {
            $dbTable = (string)$class->attributes()->table;
            $javaClass = $class->attributes()->name;
            if (strpos($javaClass, '.') === false && !empty($package)) {
                $javaClass = $package . '.' . $javaClass;
            }
            // echo "TABLE: $dbTable == $javaClass\n";

            if (empty($javaClass)) {
                echo "ERROR:: Invalid hbm file $hbmFile\n";
                continue;
            }

            try {
                $javaClassFinder = JavaClassFinder::getInstance($this->rootFilePath, $javaClass);
                // echo "OK: $hbmFile\n";

                // if the table name is the same as the class name, hibernate implicitly accept this.
                // we want it to be explicit.
                if (empty($dbTable)) {
                    $parts = explode('.', (string)$javaClass);
                    $dbTable = trim(end($parts));
                }
                $parser = new HbmParser($class, $hbmFile, $javaClassFinder, $this, $rootXmlElement->xpath('//hibernate-mapping/typedef'), $dbTable, $schema);
                $parser->parseXml();

                $this->parsers[] = $parser;
            } catch (Exception $e) {
                echo "ERROR:: " . $e->getMessage() . " => $hbmFile\n";
                continue;
            }
        }
    }

    private function simpleXMLElementFromFile(string $path): SimpleXMLElement
    {
        $string = file_get_contents($path);
        return new SimpleXMLElement($string);
    }

    public function getFindings(): array
    {
        $findings = array();

        foreach ($this->parsers as $parser) {
            foreach ($parser->getFindings() as $type => $count) {
                if (empty($findings[$type])) {
                    $findings[$type] = array();
                }

                $findings[$type][$parser->getHbmFile()] = $count;
            }
        }

        return $findings;
    }

    public function printStats(): void
    {
        foreach ($this->getFindings() as $type => $hits) {
            $hitCount = array_reduce($hits, function ($sum, $item) {
                $sum += $item;
                return $sum;
            }, 0);
            echo $type . "\t" . $hitCount . "\n";
        }
    }

    public function aggregateTablePrefixes(): void
    {
        foreach ($this->parsers as $parser) {
            /* @var $parser HbmParser */
            $parser->aggregateTablePrefix();
        }
    }

    /**
     * Find tokes for items descriptions
     * and inject tokenInfo into itemDesc.
     *
     * @return array Map<string token ident, Array<ItemDescription>> A map of grouped iten Descriptions by token info.
     */
    public function findTokensAndCreateAnnotations(): array
    {
        $tokenInfos = array();

        foreach ($this->parsers as $parser) {
            /* @var $parser HbmParser */
            $tokensInfosOfHbm = $parser->findTokensAndCreateAnnotations();

            foreach ($tokensInfosOfHbm as $tokenInfoIdent => $itemDescriptions) {
                if (empty($tokenInfos[$tokenInfoIdent])) {
                    $tokenInfos[$tokenInfoIdent] = $itemDescriptions;
                } else {
                    ArrayUtils::addAll($tokenInfos[$tokenInfoIdent], $itemDescriptions);
                }
            }
        }

        return $tokenInfos;
    }

    public function setUseCountOfTokens(array $tokenInfos): void
    {
        foreach ($tokenInfos as $tokenIdent => $itemDescriptions) {
            $c = count($itemDescriptions);
            // if ($c > 1) {
            //     echo $tokenIdent . "\t" . $c . "\n";
            // }
            foreach ($itemDescriptions as $itemDescription) {
                /* @var $itemDescription ItemDescription */
                $tokenInfo = $itemDescription->getTokenInfo();
                if (!empty($tokenInfo)) {
                    $tokenInfo->setUseCount($c);
                }
            }
        }
    }

    public function writeAnnotations(bool $countManualAnnotatiosAsSuccesfull = false): int
    {
        $successFullAnnotations = 0;
        foreach ($this->parsers as $parser) {
            /* @var $parser HbmParser */
            $parser->writeAnnotations($countManualAnnotatiosAsSuccesfull);
            $parser->writeClassAnnotations();
            $parser->writeFile();
            $successFullAnnotations += $parser->getSuccessFullAnnotations();
        }

        // find all classes that are extended
        JavaClassFinder::clearInstanceCache();
        $superClasses = array();
        foreach ($this->parsers as $parser) {
            /* @var $parser HbmParser */
            $supers = ClassUtils::findAllSuperClasses($parser->getJavaClass());
            $superClasses = array_merge($superClasses, $supers);
        }

        foreach ($superClasses as $superClass) {
            /* @var $superClass JavaClassFinder */
            $superClass->writeMappedSuperclassAnnotation();
        }

        foreach ($this->parsers as $parser) {
            /* @var $parser HbmParser */
            foreach ($parser->getSubclassAnnotations() as $annotation) {
                /* @var $annotation DiscriminatorValueAnnotation */
                $discriminatorAnnotationString = implode("\n", $annotation->generateAnnotationComponents());
                $subclass = $annotation->getMatchingSubclass();
                $subclassParser = new HbmParser($annotation->getSubclassXML(), $parser->getHbmFile(), $subclass, $this, $parser->getRawTypeDefs(), $parser->getDbTable());

                $subclassParser->parseXml();
                if(!empty($subclassParser->getItemDescs())){
                    $subclassParser->findTokensAndCreateAnnotations();
                    $subclassParser->writeAnnotations($countManualAnnotatiosAsSuccesfull);
                    $subclassParser->writeClassAnnotations();
                    $subclassParser->writeFile();
                    $successFullAnnotations += $subclassParser->getSuccessFullAnnotations();
                    $parser->addSuccessFullAnnotations($subclassParser->getSuccessFullAnnotations());
                }

                $subclass->addClassAnnotation($discriminatorAnnotationString);
                $subclass->writeFile();
            }
        }

        return $successFullAnnotations;
    }

    public function getRootFilePath(): string
    {
        return $this->rootFilePath;
    }

    public function printWriteStats(): void
    {
        $colors = new Colors();

        $colorize = function (int $successfull, int $total) use ($colors) {
            $percent = $successfull / $total * 100;

            $color = 'red';
            $bgColor = 'dark_gray';
            if ($percent === 100) {
                $bgColor = 'black';
                $color = 'green';
            } else if ($percent > 90) {
                $color = 'light_green';
            } else if ($percent > 50) {
                $color = 'yellow';
            } else if ($percent > 30) {
                $color = 'light_red';
            }

            return $colors->getColoredString(
                str_pad($successfull . '/' . $total, 11, ' ', STR_PAD_LEFT),
                $color,
                $bgColor
            );
        };

        $t = 0;
        $s = 0;
        foreach ($this->parsers as $parser) {
            /* @var $parser HbmParser */
            $totalAnnotations = count($parser->getItemDescs());
            $successfullAnnotations = $parser->getSuccessFullAnnotations();

            $t += $totalAnnotations;
            $s += $successfullAnnotations;

            echo str_pad(substr($parser->getHbmFile(), -100), 100, ' ', STR_PAD_RIGHT) .
                $colorize($successfullAnnotations, $totalAnnotations) .
                "\n";
        }
        echo "\n";
        if ($t > 0) {
            echo str_pad('Total:', 100, ' ', STR_PAD_LEFT) .
                $colorize($s, $t) .
                "\n";
            echo str_repeat('=', 111) . "\n";
        }
    }

    public function deleteHbmlFiles(): void
    {
        foreach ($this->parsers as $parser) {
            $parser->deleteHbmlFile();
        }
    }

    public function migrateHbmToClassRegistration(RegistrationMigrator $registrationMigrator): void
    {
        foreach ($this->parsers as $parser) {
            $parser->migrateHbmToClassRegistration($registrationMigrator);
        }
    }

    public function addTransient()
    {
        $cachePath = __DIR__ . '/../class2fileCache.json';
        if(!file_exists($cachePath)){
            throw new Exception("ERROR:: no such cache " . $cachePath);
        }
        $javaFiles = file_get_contents($cachePath);
        $javaClassName2Path = json_decode($javaFiles, true);
        $count = 0;
        /*@var $parser HbmParser*/
        foreach($this->parsers as $parser){
            /*@var $class JavaClassFinder*/
            $class = $parser->getJavaClass();
            $count += $class->addTransient();
        }
        echo sprintf("Generated %d @Transient annotations\n", $count);
    }
}
