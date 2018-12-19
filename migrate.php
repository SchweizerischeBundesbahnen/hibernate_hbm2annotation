<?php
require_once __DIR__ . './Hbm/Converter.php';
require_once __DIR__ . './Hbm/RegistrationMigrator.php';
require_once __DIR__ . './Java/ClassFinder.php';

require_once __DIR__ . './config.php';

$shortopts = '';
$longopts  = array(
    'printColumOverWriteWarnings', // If you have annotations in abstract classes, you may find field definitions in different *.hbm files. If the definitions don't all match you will get a warning.
    'printDetectedAnnotations', // Part of analyse.php. Prints global count of each annoation type.
    'printWriteStats', // Print statistics (bash colored) for each hbm file about how many annotations could be converted.
    'printAnnotationCreationErrors', // Print all annoation creation errors.
    'collectUnsupportedAnnoationsFile:', // File path to txt file to log all unsupported annotations to.
    'hbmFilter:', // Regex to filter hbm files. Can be used to migrate bundle by bundle.
    'migrateHbmToClassRegistration', // Automatically register classes as annotated in hibernate configuration. Specify where in $activatorFiles
    'deleteHbmlFile', // Delete HBM files after migrating them.
    'countManualAnnotatiosAsSuccesfull', // If a method / field already has any jpa or hibernate annotations, count it in "printWriteStats" as sucessfull even if it was not changed by the script.
    'addTransient', // adds @Transient to any getter that does not have persistence annotations.
    'unknownTypeWarning', // emits warning when encountering a type no import is known for
);
$options = getopt($shortopts, $longopts);

define('PRINT_COLUMN_OVER_WRITE_WANINGS', isset($options['printColumOverWriteWarnings']));
define('PRINT_ANNOTATION_CREATION_ERRORS', isset($options['printAnnotationCreationErrors']));
define('ADD_TRANSIENT', isset($options['addTransient']));
define('UNKNOWN_TYPE_WARNING', isset($options['unknownTypeWarning']));
define('COLLECT_UNSUPPORTED_ANNOTATIONS', isset($options['collectUnsupportedAnnoationsFile']) ? $options['collectUnsupportedAnnoationsFile'] : null);
if (!empty(COLLECT_UNSUPPORTED_ANNOTATIONS)) {
    file_put_contents(COLLECT_UNSUPPORTED_ANNOTATIONS, '');
}

JavaClassFinder::fillFileCache($rootFilePath);
$converter = new HbmConverter($rootFilePath);
$converter->iterateFiles(0, empty($options['hbmFilter']) ? null : $options['hbmFilter']);

if (isset($options['printDetectedAnnotations'])) {
    $converter->printStats();
}

$converter->aggregateTablePrefixes();
$tokenInfos = $converter->findTokensAndCreateAnnotations();
$converter->setUseCountOfTokens($tokenInfos);

echo "\n\nAnnotation created: " . $converter->writeAnnotations(
    isset($options['countManualAnnotatiosAsSuccesfull'])
) ."\n";

if(ADD_TRANSIENT){
    $converter->addTransient();
}

if (isset($options['printWriteStats'])) {
    $converter->printWriteStats();
}

if (isset($options['migrateHbmToClassRegistration'])) {
    $registrationMigrator = new RegistrationMigrator($activatorFiles);
    $converter->migrateHbmToClassRegistration($registrationMigrator);
    unset($registrationMigrator);
}

if (isset($options['deleteHbmlFile'])) {
    $converter->deleteHbmlFiles();
}
