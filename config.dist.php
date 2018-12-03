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