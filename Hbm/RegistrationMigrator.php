<?php

/**
 * Change registration from hbm to annotation class. 
 * Search and replace in all $activatorFiles (defined in config.php)
 * for:
 *    .addResource("com.example/path/THE_CLASS_NAME.hbm.xml", classLoader
 * and replace it by:
 *    .addAnnotatedClass(THE_CLASS_NAME.class)
 *
 * If your framework wraps hibernate or dont looks like our,
 * you may change this class to match your architecture.
 **/
class RegistrationMigrator {

    private $activatorFiles = array();

    function __construct(array $activatorFiles) {
        foreach ($activatorFiles as $filePath) {
            $this->activatorFiles[ $filePath ] = file($filePath);
        }
    }

    function __destruct() {
        foreach ($this->activatorFiles as $filePath => $lines) {
            file_put_contents($filePath, implode('', $lines));
        }
    }

    public function migrate(string $hbmFile): bool {
        $name = basename($hbmFile, '.hbm.xml');
            $regex = '/(\.addResource\()[^)]*' . preg_quote($name) . '\.hbm.xml\", classLoader\)/';
            $subst  = '.addAnnotatedClass(' . $name. '.class)';
            foreach ($this->activatorFiles as $filePath => $lines) {
                foreach ($lines as $index => $line) {
                    if (preg_match($regex, $line)) {
                        $this->activatorFiles[$filePath][$index] =
                            preg_replace($regex, $subst, $line);
                        return true;
                    }
                }
        }
        return false;
    }
}
