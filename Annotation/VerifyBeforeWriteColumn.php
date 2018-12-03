<?php

trait VerifyBeforeWriteColumnAnnotation {

    public function checkFieldOverrideAnnotation(string $currentTotalAnnotations): bool
    {
        $annotations = $this->generateFieldOverrideAnnotation();

        $assosiationName = self::extractAttributeOverrideNameFromAnnotation($annotations);
        if (empty($assosiationName)) {
            return true;
        }

        $currentColumnName = self::extractAttributeOverrideColumnNameFromAnnotation($assosiationName, $currentTotalAnnotations);
        if ($currentColumnName === null) {
            return true;
        }

       
        $newColumnName = self::extractAttributeOverrideColumnNameFromAnnotation($assosiationName, $annotations);

        if (strtolower($currentColumnName) === strtolower($newColumnName)) {
            // The second+ run, everythink looks like expected.
            return false;
        }

        if (PRINT_COLUMN_OVER_WRITE_WANINGS) {
            echo "Annotation @AttributeOverride(name='$assosiationName'...@Column allready exists: $currentColumnName != $newColumnName\n";
        }

        return false;
    }

    public function checkAnnotation(string $currentTotalAnnotations, bool $removeTablePrefix = false): bool
    {
        $currentColumnName = self::extractColumnNameFromAnnotation($currentTotalAnnotations);
        if ($currentColumnName === null) {
            return true;
        }

        $annotations = $this->generateAnnotationComponents($removeTablePrefix);
        $newColumnName = self::extractColumnNameFromAnnotation($annotations[self::COLUMN_NAME]);

        if (strtolower($currentColumnName) === strtolower($newColumnName)) {
            // The second+ run, everythink looks like expected.
            return false;
        }

        if (PRINT_COLUMN_OVER_WRITE_WANINGS) {
            echo "Annotation @Column allready exists: $currentColumnName != $newColumnName\n";
        }
        
        return false;
    }
} 