<?php

interface VerifyBeforeWriteAnnotation {

    function generateAnnotationComponents(bool $removeTablePrefix = false): ?array;

    /**
     * Check if filed overwrite annotation should be written.
     *
     * @return boolean FALSE: if should not be written.
     */
    function checkFieldOverrideAnnotation(string $currentTotalAnnotations): bool;

    /**
     * Check if annotation should be written.
     *
     * @return boolean FALSE: if should not be written.
     */
    function checkAnnotation(string $currentTotalAnnotations, bool $removeTablePrefix = false): bool;

}