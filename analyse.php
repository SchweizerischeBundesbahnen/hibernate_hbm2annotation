<?php
require_once __DIR__ . './Hbm/Converter.php';
require_once __DIR__ . './Java/ClassFinder.php';

require_once __DIR__ . './config.php';

JavaClassFinder::fillFileCache($rootFilePath);
$converter = new HbmConverter($rootFilePath);
$converter->iterateFiles();
print_r($converter->getFindings());
$converter->printStats();
