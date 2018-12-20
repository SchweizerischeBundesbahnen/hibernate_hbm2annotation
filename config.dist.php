<?php

// You need to delete the "class2fileCache.json" if $rootFilePath or $bundles are change
$rootFilePath = "path/to/your/java/repository";

// May be used to improve performance to jail to some model bundles not alway scann full project.
// find ./ -name "*.hbm.xml" | cut -d"/" -f2 | uniq
$bundles = array(
    // $rootFilePath . DIRECTORY_SEPARATOR . 'ch.xx.aa.bb' . DIRECTORY_SEPARATOR,
    // $rootFilePath . DIRECTORY_SEPARATOR . 'ch.xx.aa.cc' . DIRECTORY_SEPARATOR,
);

// List of activator files were to replace hbm file to class file registration.
$activatorFiles = array(
    // $rootFilePath . DIRECTORY_SEPARATOR . 'ch.xx.aa.bb/src/.../....java'
);

// List of user types that should be converted to "@Converter" if you prefer jpa over hibernate.
$typeToConverter = array(
    // 'ch.xxxx.common.db.UserTypeDate' => 'ch.xxxx.common.db.DateConverter',
);

// Defines when the script generates an Attribute/AssociationOverride-Annotation.
// HIERARCHY may generate unnecessary overrides, but is generally more compatible and should be used.
// HIERARCHY is assumed as default if nothing else is specified or the config is malformed.
// if you use COUNT, be sure to migrate all the children of a particular class at the same time, otherwise not all Overrides will be generated
#$overrideStrategy="COUNT";     // generated when a property which is defined in a parent class is present in two or more child classes.
$overrideStrategy="HIERARCHY"; //  generated when a property which is defined in a parent class is present in a child class (always).
