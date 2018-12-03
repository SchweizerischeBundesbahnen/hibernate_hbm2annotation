<?php
require_once __DIR__ . '/Array.php';

class ImportUtil
{
    const IMPORT_MAPPING = [
        // JPA Imports
        'access' => ['javax.persistence.Access', 'javax.persistence.AccessType'],
        'associationoverride' => ['javax.persistence.AssociationOverride', 'javax.persistence.JoinColumn'],
        'associationoverrides' => 'javax.persistence.AssociationOverrides',
        'attributeoverride' => ['javax.persistence.AttributeOverride', 'javax.persistence.Column'],
        'attributeoverrides' => 'javax.persistence.AttributeOverrides',
        'basic' => ['javax.persistence.Basic', 'javax.persistence.FetchType'],
        'collectiontable' => 'javax.persistence.CollectionTable',
        'column' => 'javax.persistence.Column',
        'convert' => 'javax.persistence.Convert',
        'discriminatorcolumn' => ['javax.persistence.DiscriminatorColumn', 'javax.persistence.DiscriminatorType'],
        'discriminatorvalue' => 'javax.persistence.DiscriminatorValue',
        'embedded' => 'javax.persistence.Embedded',
        'embeddable' => 'javax.persistence.Embeddable',
        'entity' => 'javax.persistence.Entity',
        'generatedvalue' => ['javax.persistence.GeneratedValue', 'javax.persistence.GenerationType'],
        'id' => 'javax.persistence.Id',
        'joincolumn' => 'javax.persistence.JoinColumn',
        'jointable' => ['javax.persistence.JoinTable', 'javax.persistence.JoinColumn'],
        'manytomany' => 'javax.persistence.ManyToMany',
        'manytoone' => 'javax.persistence.ManyToOne',
        'mappedsuperclass' => 'javax.persistence.MappedSuperclass',
        'onetomany' => 'javax.persistence.OneToMany',
        'onetoone' => 'javax.persistence.OneToOne',
        'ordercolumn' => 'javax.persistence.OrderColumn',
        'sequencegenerator' => 'javax.persistence.SequenceGenerator',
        'table' => 'javax.persistence.Table',
        'transient' => 'javax.persistence.Transient',
        'version' => 'javax.persistence.Version',
        // Hibernate Imports
        'batchsize' => 'org.hibernate.annotations.BatchSize',
        'cascade' => ['org.hibernate.annotations.Cascade', 'org.hibernate.annotations.CascadeType'],
        'discriminatorformula' => 'org.hibernate.annotations.DiscriminatorFormula',
        'fetch' => ['org.hibernate.annotations.Fetch', 'org.hibernate.annotations.FetchMode'],
        'genericgenerator' => 'org.hibernate.annotations.GenericGenerator',
        'immutable' => 'org.hibernate.annotations.Immutable',
        'lazycollection' => ['org.hibernate.annotations.LazyCollection', 'org.hibernate.annotations.LazyCollectionOption'],
        'orderby' => 'org.hibernate.annotations.OrderBy',
        'parameter' => 'org.hibernate.annotations.Parameter',
        'type' => 'org.hibernate.annotations.Type',
        'columntransformer' => 'org.hibernate.annotations.ColumnTransformer',
        'formula' => 'org.hibernate.annotations.Formula',
        // Rcs Imports
        'rcsdbsequence' => 'ch.sbb.aa.bb.cc.MyObsfuscatedClass',
    ];

    const IGNORED_ANNOTATIONS = [
        '',
        'Override',
        'SuppressWarnings',
        'PublicInterface',
        'DontCompare',
        'FieldNameMapping',
        'Deprecated',
        'BooleanValue',
        'MultilangStringFieldNameMapping',
        'ReferenceResolution',
        'StammdatumModel',
        'UNOImported',
        'TruncateTime',
        'MutationHistory',
    ];

    public static function generateImports(array &$lines): array
    {
        $exitingImports = array();
        $toImport = array();
        ArrayUtils::addAll($toImport, self::generateImportsFromAnnotations(implode('', $lines), $exitingImports));
        ArrayUtils::addAll($toImport, self::normalizeConverterAnnotations($lines));

        $toImport = array_unique(array_filter($toImport, function ($import) use ($exitingImports) {
            return !in_array($import, $exitingImports);
        }));

        return $toImport;
    }

    private static function generateImportsFromAnnotations(string $allLines, array &$existingImports): array
    {
        $toImport = array();
        $matches = array();
        preg_match_all('/^\h*(@(?<annotation>\w+)|import\s(?<import>[\w.]+);)/m', $allLines, $matches);
        $matchedAnnotations = array_unique($matches['annotation']);
        $existingImports = array_unique($matches['import']);
        $matchedAnnotations = array_diff($matchedAnnotations, self::IGNORED_ANNOTATIONS);
        foreach ($matchedAnnotations as $matchedAnnotation) {
            $origForLoggging = $matchedAnnotation;
            $matchedAnnotation = strtolower($matchedAnnotation);
            if (array_key_exists($matchedAnnotation, self::IMPORT_MAPPING)) {
                $import = self::IMPORT_MAPPING[$matchedAnnotation];
                if (is_array($import)) {
                    // adds the elements of $import to the end of $toImport
                    //... expands the $toImport array to a list of args
                    ArrayUtils::addAll($toImport, $import);
                } else {
                    ArrayUtils::add($toImport, $import);
                }
            } else {
                echo "WARN:: '$origForLoggging' isn't a known key. Please add it so imports can be generated!\n";
            }
        }

        return $toImport;
    }

    /**
     * Find all converter with FQDN
     * shorten them to class and add matching import.
     *
     * @param array $lines
     * @return array toImport
     */
    private static function normalizeConverterAnnotations(array &$lines): array
    {
        $toImport = array();

        foreach ($lines as $index => $line) {
            $lines[$index] = preg_replace_callback( //
                '/^(?<head>\h*@convert\h*\([\)]*converter\h*=\h*\"?)(?<class>\w+\.[\w\.]+)(?<tail>\.class.*)/i', //
                function ($matches) use (&$toImport) {
                    $toImport[] = $matches['class'];
                    $parts = explode('.', $matches['class']);

                    return $matches['head'] . end($parts) . $matches['tail'];
                }, //
                $line);
        }

        return $toImport;
    }

    public static function containsPersistenceAnnotations(string $annotations): bool
    {
        foreach (self::extractAnnotations($annotations) as $annotation) {
            if (array_key_exists(strtolower(trim($annotation)), self::IMPORT_MAPPING)) {
                return true;
            }
        }
        return false;
    }

    private static function extractAnnotations(string $annotations): array
    {
        $matches = array();
        preg_match_all('/^\h*(@(?<annotation>\w+))/m', $annotations, $matches);
        return array_unique($matches['annotation']);
    }

}
