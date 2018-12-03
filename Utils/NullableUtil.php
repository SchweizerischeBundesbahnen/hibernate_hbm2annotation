<?php

class NullableUtil
{
    /**
     * Generates nullable= from the not-null= in the HBM.
     * Nullable must be the inverted value.
     */
    public static function generateNullable(string $rawNotNull): ?string
    {
        $val = strtolower($rawNotNull);
        if ($val === 'true' || $val === 'false') {
            // must be inverted, because HBM is "not-null" and annotations is "nullable"
            $invertedVal = $val === 'true' ? 'false' : 'true';
            return $invertedVal;
        } else {
            echo sprintf("ERROR:: unkown value %s for nullable\n", $rawNotNull);
            return null;
        }
    }
}