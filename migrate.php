<?php
require_once __DIR__ . './Hbm/Converter.php';
require_once __DIR__ . './Hbm/RegistrationMigrator.php';
require_once __DIR__ . './Java/ClassFinder.php';

require_once __DIR__ . './config.php';

$shortopts = '';
$longopts  = array(
    'printColumOverWriteWarnings', // If your have annoations in abstract classes, you may find a field definitions in different *.hbm files. If the definition not all match you will get a warning.
    'printDetectedAnnotations', // Part of analyse.php prints global count per annoation type.
    'printWriteStats', // Print statistics (bash colored) for each hbm file, how mutch annotaions could be converted.
    'printAnnotationCreationErrors', // Print all annoation creation errors.
    'collectUnsupportedAnnoationsFile:', // File path to txt file to log all unsupported annotations to.
    'hbmFilter:', // Regex to filter hbm files. Can be used to migrate bundle by bundle.
    'migrateHbmToClassRegistration', // Hbm files as java files with annotations needs to registerd in hibernate configuration.
    'deleteHbmlFile', // Delete HBM files after migrating it.
    'countManualAnnotatiosAsSuccesfull', // If a methods / fields has already any jpa or hibernate annotations, count it in "printWriteStats" as sucessfull even if it was not changed.
    'addTransient' // adds @Transient to any getter that does not have persistence annotations.
);
$options = getopt($shortopts, $longopts);

define('PRINT_COLUMN_OVER_WRITE_WANINGS', isset($options['printColumOverWriteWarnings']));
define('PRINT_ANNOTATION_CREATION_ERRORS', isset($options['printAnnotationCreationErrors']));
define('ADD_TRANSIENT', isset($options['addTransient']));
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
