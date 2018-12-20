<?php
require_once __DIR__ . '/ItemDescription.php';
require_once __DIR__ . '/ChildDescripton.php';
require_once __DIR__ . '/../Annotation/Abstract.php';
require_once __DIR__ . '/../Annotation/Table.php';
require_once __DIR__ . '/../Java/ClassFinder.php';
require_once __DIR__ . '/../Utils/ClassUtils.php';
require_once __DIR__ . '/../Utils/ConfigUtil.php';

class HbmParser
{

    private $class;
    private $raw_typedefs;
    private $typedefs = array();

    private $hbmFile;

    private $javaClass;

    private $converter;

    private $dbTable;

    private $schema;

    private $successFullAnnotations = 0;

    private $tablePrefix = '';

    private $findings;

    private $itemDescs;

    private $subclassAnnotations = array();

    private $discriminatorAnnotations = array();

    private static $ERROR_SEPERATOR = "\n\n###################\n\n";

    public function __construct(SimpleXMLElement $class, string $hbmFile, JavaClassFinder $javaClass, HbmConverter $converter, array $typedefs, string $dbTable, string $schema = "")
    {
        $this->class = $class;
        $this->raw_typedefs = $typedefs;
        $this->hbmFile = $hbmFile;
        $this->javaClass = $javaClass;
        $this->converter = $converter;
        $this->dbTable = $dbTable;
        $this->schema = $schema;
    }

    public function getClassName(): string
    {
        return $this->javaClass->getClassName();
    }

    public function getJavaClass(): JavaClassFinder
    {
        return $this->javaClass;
    }

    public function getRootFilePath(): string
    {
        return $this->converter->getRootFilePath();
    }

    public function getHbmFile(): string
    {
        return $this->hbmFile;
    }

    public function getRawTypeDefs(): array
    {
        return $this->raw_typedefs;
    }

    public function getDbTable(): string
    {
        return $this->dbTable;
    }

    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    private function createItemDesc(SimpleXMLElement $item): ItemDescription
    {
        $nodeName = strtolower($item->getName());
        return new ItemDescription($nodeName, $item, $this->typedefs);
    }

    /**
     * @return DiscriminatorValueAnnotation[]
     */
    public function getSubclassAnnotations(): array
    {
        return $this->subclassAnnotations;
    }

    /**
     * @return DiscriminatorAnnotation[]
     */
    public function getDiscriminatorAnnotations(): array
    {
        return $this->discriminatorAnnotations;
    }

    /**
     * <id name="id" column="BEN_ID" type="PersistentObjectId" access="property">
     * <generator class="assigned">
     * <param name="sequence_name">RCSSQ_BENUTZER</param>
     * </generator>
     * </id>
     */
    private function isId(SimpleXMLElement $item): ?ItemDescription
    {
        $itemDesc = $this->createItemDesc($item);


        if ($itemDesc->getNodeName() != 'id') {
            return null;
        }

        $children_valid = $this->validateChildren($item, [
            new ChildDescripton('generator', [], [
                'class',
            ]), new ColumnChildDescription('column', [
                'name'
            ], [
                'updateable',
                'nullable'
            ]), new ParamChildDescripton('param', [
                'name',
            ])
        ], $itemDesc);

        if (!$children_valid) {
            return null;
        }

        $attribute_valid = $this->hasAtributes($item, [
            'name',
        ], [
            'column',
            'access',
            'type'
        ], $itemDesc, false);

        if ($attribute_valid === true) {
            return $itemDesc;
        }

        return null;
    }

    /**
     * <version name="version" column="AAW_CC_VERSION"/>
     */
    private function isVersion(SimpleXMLElement $item): ?ItemDescription
    {
        $itemDesc = $this->createItemDesc($item);

        if ($itemDesc->getNodeName() != 'version' || !$this->validateChildren($item)) {
            return null;
        }

        $attribute_valid = $this->hasAtributes($item, [
            'name',
            'column',
        ], [
            'type',
        ], $itemDesc, false);

        if ($attribute_valid == true) {
            return $itemDesc;
        }
        return null;
    }

    /**
     * <property name="bezeichnungZNTelegrammB" formula="substr(BEZ_ZN_B, 0, 8)" insert="false" update="false"/>
     *
     * <property name="geschwindigkeitAblenkung"
     * formula="(select coalesce(min(gk.geschwindigkeit_ablenkung), 0) from gleisknoten gk where gk.id_weiche_kreuzung = ID_WEICHE_KREUZUNG)" />
     *
     * <property name="ausschaltbar" type="numeric_boolean"> <column read="nvl(AUSSCHALTBAR, 0)" name="AUSSCHALTBAR"/> </property>
     *
     * https://docs.jboss.org/hibernate/orm/3.6/reference/en-US/html/mapping.html#mapping-column-read-and-write
     */
    private function isPropertyFormula(SimpleXMLElement $item): ?ItemDescription
    {
        $itemDesc = $this->createItemDesc($item);

        if ($itemDesc->getNodeName() != 'property') {
            return null;
        }

        $attribute_valid = $this->hasAtributes($item, [
            'name',
        ], [
            'formula',
            'type',
            'insert',
            'update',
        ], $itemDesc, false);

        $childs_valid = $this->validateChildren($item, [
            new ChildDescripton('column', [
                'name',
            ], [
                'read',
                'not-null',
            ]),
            new ValueChildDescripton('formula'),
        ], $itemDesc);

        if ($attribute_valid == true && $childs_valid == true && (strpos($item->asXML(), 'formula') !== false || strpos($item->asXML(), 'read=') !== false)) {
            return $itemDesc;
        }

        return null;
    }

    /**
     * <property name="validVon" type="ADateTime" column="VALID_VON"
     * not-null="true">
     * </property>
     *
     * <property name="femProtocolTyp" column="ADR_FEM_PROTOCOL_TYP">
     * <type name="org.hibernate.type.EnumType">
     * <param name="enumClass">ch.sbb.aa.bb.cc.MyObsfuscatedClass</param>
     * </type>
     * </property>
     */
    private function isProperty(SimpleXMLElement $item): ?ItemDescription
    {
        $itemDesc = $this->createItemDesc($item);

        if ($itemDesc->getNodeName() != 'property') {
            return null;
        }

        $attribute_valid = $this->hasAtributes($item, [
            'name',
        ], [
            'column',
            'type',
            'not-null',
            'access',
            'length',
            'lazy',
            'unique',
            'insert',
            'update',
        ], $itemDesc);

        $childs_valid = $this->validateChildren($item, [
            new ChildDescripton('column', [
                'name',
            ], [
                'not-null',
            ]),
            new ChildDescripton('type', [
                'name',
            ]),
            new ParamChildDescripton('param', [
                'name',
            ]),
            new ChildDescripton('meta', [
                'attribute',
            ]),
        ], $itemDesc);

        if ($attribute_valid == true && $childs_valid == true) {
            return $itemDesc;
        }

        return null;
    }

    /**
     * <component name="vOpt" class="ch.sbb.aa.bb.cc.MyObsfuscatedClass">
     * <property name="typ" column="AFE_VOPT_TYP" type="VOptTyp" />
     * <property name="betrag" column="AFE_VOPT_BETRAG" />
     * </component>
     *
     * <component name="zusatzInformation" access="field" class="ch.sbb.aa.bb.cc.MyObsfuscatedClass">
     * <property name="begruendung" access="field" column="AFE_BEGRUENDUNG" type="FahrEmpfehlungsBegruendung" not-null="false"/>
     * <property name="haltNachKorridorEnde" access="field" column="AFE_HALT_NACH_KOR_ENDE" not-null="true"/>
     * </component>
     */
    private function isComponent(SimpleXMLElement $item): ?ItemDescription
    {
        $itemDesc = $this->createItemDesc($item);

        if ($itemDesc->getNodeName() != 'component') {
            return null;
        }

        $attribute_valid = $this->hasAtributes($item, [
            'name',
            'class',
        ], [
            'access',
        ], $itemDesc);

        $propertys = array();

        foreach ($item->children() as $child) {
            $subItemDesc = $this->createItemDesc($child);

            $this->hasAtributes($child, [
                'name',
                'column',
            ], [
                'access',
                'not-null',
                'type',
            ], $subItemDesc);

            $propertys[] = $subItemDesc;
        }

        $itemDesc->addAttribute('properties', $propertys);

        if ($attribute_valid == true) {
            return $itemDesc;
        }

        return null;
    }

    /**
     * <one-to-one name="halt" class="TaxiHaltImpl" property-ref="leistungspunkt" cascade="save-update" lazy="false" />
     */
    private function isOneToOne(SimpleXMLElement $item): ?ItemDescription
    {
        $itemDesc = $this->createItemDesc($item);

        if ($itemDesc->getNodeName() != 'one-to-one') {
            return null;
        }

        $attribute_valid = $this->hasAtributes($item, [
            'name',
            'class',
            'property-ref',
        ], [
            'cascade',
            'lazy',
            'fetch',
        ], $itemDesc);

        $childs_valid = $this->validateChildren($item, [], $itemDesc);

        if ($attribute_valid == true && $childs_valid == true) {
            return $itemDesc;
        }

        return null;
    }

    /**
     * <one-to-many class="ch.sbb.aa.bb.cc.MyObsfuscatedClass" />
     */
    private function isOneToMany(SimpleXMLElement $item): ?ItemDescription
    {
        $itemDesc = $this->createItemDesc($item);

        if ($itemDesc->getNodeName() != 'one-to-many') {
            return null;
        }

        $attribute_valid = $this->hasAtributes($item, [
            'class',
        ], [
            'fetch',
        ], $itemDesc);

        $childs_valid = $this->validateChildren($item, [], $itemDesc);

        if ($attribute_valid == true && $childs_valid == true) {
            return $itemDesc;
        }

        return null;
    }

    /**
     * <many-to-one name="antwort" class="Antwort" cascade="all" unique="true" lazy="false">
     * <column name="ANTWORT_ID" />
     * </many-to-one>
     */
    private function isManyToOne(SimpleXMLElement $item): ?ItemDescription
    {
        $itemDesc = $this->createItemDesc($item);

        if ($itemDesc->getNodeName() != 'many-to-one') {
            return null;
        }

        $attribute_valid = $this->hasAtributes($item, [
            'name',
        ], [
            'cascade',
            'column',
            'not-null',
            'class',
            'lazy',
            'unique',
            'fetch',
            'not-found',
        ], $itemDesc);

        $childs_valid = $this->validateChildren($item, [
            new ChildDescripton('column', [
                'name',
            ], [
                'unique',
                'not-null',
            ]),
        ], $itemDesc);

        if ($attribute_valid == true && $childs_valid == true) {
            return $itemDesc;
        }

        return null;
    }

    /**
     * <many-to-many class="Betriebspunkt" column="bp_id" />
     */
    private function isManyToMany(SimpleXMLElement $item): ?ItemDescription
    {
        $itemDesc = $this->createItemDesc($item);

        if ($itemDesc->getNodeName() != 'many-to-many') {
            return null;
        }

        $attribute_valid = $this->hasAtributes($item, [
            'class',
            'column',
        ], [
            'name',
        ], $itemDesc);

        $childs_valid = $this->validateChildren($item, [], $itemDesc);

        if ($attribute_valid == true && $childs_valid == true) {
            return $itemDesc;
        }

        return null;
    }

    /**
     * https://docs.jboss.org/hibernate/orm/3.3/reference/en/html/mapping.html#mapping-declaration-id-generator
     * <generator class="org.hibernate.id.TableHiLoGenerator">
     * <param name="table">uid_table</param>
     * <param name="column">next_hi_value_column</param>
     * </generator>
     */
    private function isGenerator(SimpleXMLElement $item): ?ItemDescription
    {
        $itemDesc = $this->createItemDesc($item);

        if ($itemDesc->getNodeName() != 'generator') {
            return null;
        }

        $attribute_valid = $this->hasAtributes($item, [
            'class',
        ], [], $itemDesc);

        $childs_valid = $this->validateChildren($item, [], $itemDesc);

        if ($attribute_valid == true && $childs_valid == true) {
            return $itemDesc;
        }

        return null;
    }

    /**
     * <subclass name="MassnahmeWenden" discriminator-value="WENDEN">
     * <property name="ortText" column="ORT" />
     * <property name="streckeIds" column="ORT_IDS" length="4000"/>
     * <property name="gegenZug" column="GEGEN_ZUG" />
     * <property name="gegenZugTrainId" column="GEGEN_ZUG_TRAIN_ID" type="TrainIdType" />
     * <property name="verkehrtAls" column="VERKEHRT_ALS" />
     * <property name="verkehrtAlsTrainId" column="VERKEHRT_ALS_TRAIN_ID" type="TrainIdType" />
     * <property name="ausfallBisBP"/>
     * <property name="ausfallBisStreckeIds" column="AUSFALLBISSTRECKE_IDS" length="4000"/>
     * <property name="anordnungBisBP"/>
     * <property name="anordnungBisStreckeIds" column="ANORDNUNGBISSTRECKE_IDS" length="4000"/>
     * <property name="gegenAusfallStrecke"/>
     * <property name="gegenAusfallStreckeIds" column="GEGENAUSFALLSTRECKE_IDS" length="4000"/>
     * </subclass>
     */
    private function isSubclass(SimpleXMLElement $item): ?ItemDescription
    {
        $itemDesc = $this->createItemDesc($item);

        if ($itemDesc->getNodeName() != 'subclass') {
            return null;
        }

        $attribute_valid = $this->hasAtributes($item, [
            'name',
        ], [
            'lazy',
            'discriminator-value',
            'abstract',
        ], $itemDesc);

        if ($attribute_valid == true) {
            return $itemDesc;
        }

        return null;
    }

    private function isProperties(SimpleXMLElement $item): ?ItemDescription
    {
        $itemDesc = $this->createItemDesc($item);

        if ($itemDesc->getNodeName() != 'properties') {
            return null;
        }

        $attribute_valid = $this->hasAtributes($item, [
            'name',
            'unique',
        ], [], $itemDesc);

        if ($attribute_valid == true) {
            return $itemDesc;
        }

        return null;
    }

    /**
     * https://docs.jboss.org/hibernate/orm/3.5/reference/de-DE/html/inheritance.html#inheritance-tablepersubclass-discriminator
     * <discriminator formula="MASSNAHME_TYP" type="string" />
     */
    private function isDiscriminator(SimpleXMLElement $item): ?ItemDescription
    {
        $itemDesc = $this->createItemDesc($item);

        if ($itemDesc->getNodeName() != 'discriminator') {
            return null;
        }

        $attribute_valid = $this->hasAtributes($item, [], [
            'type',
            'column',
            'formula',
            'not-null',
        ], $itemDesc);

        $childs_valid = $this->validateChildren($item, [], $itemDesc);

        if ($attribute_valid == true && $childs_valid == true) {
            return $itemDesc;
        }

        return null;
    }

    /**
     * <set name="massnahmeErledigt" table="Massnahme_Erledigt" lazy="true">
     * <key column="konzept_version_id"/>
     * <element type="string" column="massnahme_uuid" not-null="true" />
     * </set>
     *
     * <set name="flags" inverse="true" cascade="none" batch-size="10" fetch="select">
     * <key column="ANFRAGE_ID" />
     * <one-to-many class="Flag" />
     * <filter name="flagsForFunktion" condition="funktion_Id = :fid" />
     * <index column="HSI_REIHENFOLGE"/>
     * </set>
     *
     * <set name="nachbarBpEntityIdSet2" lazy="false" cascade="none" inverse="true" mutable="false">
     * <subselect>
     * <![CDATA[
     * select
     * bp.id_version fk,
     * bpv.id_betriebspunkt_bis wert
     * from
     * betriebspunkt bp,
     * bp_verbindung bpv,
     * betriebspunkt_info bpi,
     * zone z
     * where bp.id_betriebspunkt = bpv.id_betriebspunkt_von
     * and bpv.id_betriebspunkt_bis = bpi.bpi_id_betriebspunkt
     * and bpi.bpi_zon_id = z.zon_id
     * and z.zon_rel_rcsd = 'T'
     * and (bp.eff_gueltig_ab < bpv.eff_gueltig_bis
     * and bp.eff_gueltig_bis > bpv.eff_gueltig_ab)
     * ]]>
     * </subselect>
     * <key column="fk" not-null="true" />
     * <element type="DunoEntityId" column="wert" />
     * </set>
     *
     * <set name="betriebsmodus" table="FFT_BETRIEBSMODUS" inverse="false"
     * lazy="false" fetch="select" cascade="save-update, delete">
     * <key>
     * <column name="FBM_FFT_ID" not-null="true" />
     * </key>
     * <one-to-many
     * class="ch.sbb.aa.bb.cc.MyObsfuscatedClass" />
     * </set>
     *
     * <set name="positionSet" where="SPO_TABLE_ALIAS = 'SSZ'" lazy="false" mutable="false" fetch="subselect">
     * <key column="SPO_TABLE_ENTITY_ID" not-null="false"/>
     * <one-to-many class="ch.sbb.aa.bb.cc.MyObsfuscatedClass"/>
     * </set>
     *
     * <set name="positionSet" where="SPO_TABLE_ALIAS = 'SSB'" lazy="false" mutable="false" fetch="subselect" >
     * <key column="SPO_TABLE_ENTITY_ID" not-null="false"/>
     * <one-to-many class="ch.sbb.aa.bb.cc.MyObsfuscatedClass"/>
     * </set>
     */
    function isSet(SimpleXMLElement $item): ?ItemDescription
    {
        $itemDesc = $this->createItemDesc($item);

        if ($itemDesc->getNodeName() != 'set') {
            return null;
        }

        $attribute_valid = $this->hasAtributes($item, [
            'name',
        ], [
            'order-by',
            'cascade',
            'table',
            'inverse',
            'fetch',
            'lazy',
            'batch-size',
            'sort',
            'access',
            'mutable',
            'collection-type',
            'where',
        ], $itemDesc);

        $this->parseXmlSet($item, $itemDesc);

        if ($attribute_valid == true) {
            return $itemDesc;
        }

        return null;
    }

    /**
     * <list name="signaleAsList" lazy="false" inverse="true" cascade="delete">
     * <key column="HSI_HFW_ID" not-null="false"/>
     * <index column="HSI_REIHENFOLGE"></index>
     * <one-to-many class="ch.sbb.aa.bb.cc.MyObsfuscatedClass" />
     * </list>
     */
    private function isList(SimpleXMLElement $item): ?ItemDescription
    {
        $itemDesc = $this->createItemDesc($item);

        if ($itemDesc->getNodeName() != 'list') {
            return null;
        }

        $attribute_valid = $this->hasAtributes($item, [
            'name',
        ], [
            'table',
            'cascade',
            'lazy',
            'inverse',
            'access',
        ], $itemDesc);

        $this->parseXmlSet($item, $itemDesc);

        if ($attribute_valid == true) {
            return $itemDesc;
        }

        return null;
    }

    /**
     * <bag name="listMemberToBag" lazy="true" cascade="none" inverse="true" mutable="false">
     * <key column="ID_LAZY_LOADING_TEST_TO_BAG" not-null="false"/>
     * <one-to-many class="ch.sbb.aa.bb.cc.MyObsfuscatedClass"/>
     * </bag>
     *
     * <bag name="bereichDefinitionen" collection-type="ch.sbb.aa.bb.cc.MyObsfuscatedClass" lazy="false" inverse="true">
     * <key column="ZBE_ZBV_ID" not-null="false"/>
     * <one-to-many class="ch.sbb.aa.bb.cc.MyObsfuscatedClass"/>
     * </bag>
     */
    private function isBag(SimpleXMLElement $item): ?ItemDescription
    {
        $itemDesc = $this->createItemDesc($item);

        if ($itemDesc->getNodeName() != 'bag') {
            return null;
        }

        $attribute_valid = $this->hasAtributes($item, [
            'name',
        ], [
            'table',
            'lazy',
            'cascade',
            'inverse',
            'mutable',
            'fetch',
            'order-by',
            'collection-type',
        ], $itemDesc);

        $this->parseXmlSet($item, $itemDesc);

        if ($attribute_valid == true) {
            return $itemDesc;
        }

        return null;
    }

    private function isCompositeId(SimpleXMLElement $item): ?ItemDescription
    {
        $itemDesc = $this->createItemDesc($item);

        if ($itemDesc->getNodeName() != 'composite-id') {
            return null;
        }

        $attribute_valid = $this->hasAtributes($item, [], [], $itemDesc);

        if ($attribute_valid == true) {
            return $itemDesc;
        }

        return null;
    }

    public function parseXml()
    {
        foreach ($this->raw_typedefs as $typedef) {
            $this->typedefs[] = new TypeAnnotation($typedef);
        }

        $process = function (ItemDescription $itemDesc, $name = null) {
            if (empty($name)) {
                $name = $itemDesc->getNodeName();
            } else {
                $itemDesc->setNodeName($name);
            }

            $this->addFinding($name);
            $this->addItemDesc($itemDesc);
        };

        foreach ($this->class->children() as $child) {
            $item_desc = null;
            if ($item_desc = $this->isPropertyFormula($child)) {
                $process($item_desc, 'property_formula');
                continue;
            }

            $methodes = array(
                'isId',
                'isVersion',
                'isProperty',
                'isComponent',
                'isOneToOne',
                'isOneToMany',
                'isManyToOne',
                'isManyToMany',
                'isGenerator',
                'isSubclass',
                'isProperties',
                'isDiscriminator',
                'isSet',
            );

            if ($item_desc = $this->isId($child)) {
                $process($item_desc);
                continue;
            }

            if ($item_desc = $this->isVersion($child)) {
                $process($item_desc);
                continue;
            }

            if ($item_desc = $this->isProperty($child)) {
                $process($item_desc);
                continue;
            }

            if ($item_desc = $this->isComponent($child)) {
                $process($item_desc);
                continue;
            }

            if ($item_desc = $this->isOneToOne($child)) {
                $process($item_desc);
                continue;
            }

            if ($item_desc = $this->isOneToMany($child)) {
                $process($item_desc);
                continue;
            }

            if ($item_desc = $this->isManyToOne($child)) {
                $process($item_desc);
                continue;
            }

            if ($item_desc = $this->isManyToMany($child)) {
                $process($item_desc);
                continue;
            }

            if ($item_desc = $this->isGenerator($child)) {
                $process($item_desc);
                continue;
            }

            if ($item_desc = $this->isSubclass($child)) {
                $process($item_desc);
                continue;
            }

            if ($item_desc = $this->isProperties($child)) {
                $process($item_desc);
                continue;
            }

            if ($item_desc = $this->isDiscriminator($child)) {
                $process($item_desc);
                continue;
            }

            if ($item_desc = $this->isSet($child)) {
                $process($item_desc);
                continue;
            }
            if ($item_desc = $this->isList($child)) {
                $process($item_desc);
                continue;
            }

            if ($item_desc = $this->isBag($child)) {
                $process($item_desc);
                continue;
            }

            if ($item_desc = $this->isCompositeId($child)) {
                $process($item_desc);
                continue;
            }

            fwrite(STDERR, "Unknown Type \"" . $child->getName() . "\" @ " . (str_replace("\n", " ", $child->asXML())) . "\n");
        }
    }

    private function parseXmlSet(SimpleXMLElement $set, ItemDescription $itemDesc)
    {

        /*
         * <set name="nachbarBpEntityIdSet2" lazy="false" cascade="none" inverse="true" mutable="false">
         * <subselect>
         * <![CDATA[
         * select
         * bp.id_version fk,
         * bpv.id_betriebspunkt_bis wert
         * from
         * betriebspunkt bp,
         * bp_verbindung bpv,
         * betriebspunkt_info bpi,
         * zone z
         * where bp.id_betriebspunkt = bpv.id_betriebspunkt_von
         * and bpv.id_betriebspunkt_bis = bpi.bpi_id_betriebspunkt
         * and bpi.bpi_zon_id = z.zon_id
         * and z.zon_rel_rcsd = 'T'
         * and (bp.eff_gueltig_ab < bpv.eff_gueltig_bis
         * and bp.eff_gueltig_bis > bpv.eff_gueltig_ab)
         * ]]>
         * </subselect>
         * <key column="fk" not-null="true" />
         * <element type="DunoEntityId" column="wert" />
         * </set>
         *
         * <list name="leistungspunkte" cascade="save-update" lazy="false">
         * <key column="TLP_TTG_ID" not-null="true" update="false" />
         * <list-index column="TLP_LAUFNUMMER" />
         * <one-to-many class="TaxiLeistungspunktImpl" />
         * </list>
         *
         * <set name="betriebsmodus" table="FFT_BETRIEBSMODUS" inverse="false"
         * lazy="false" fetch="select" cascade="save-update, delete">
         * <key>
         * <column name="FBM_FFT_ID" not-null="true" />
         * </key>
         * <one-to-many
         * class="ch.sbb.aa.bb.cc.MyObsfuscatedClass" />
         * </set>
         *
         * <set name="positionSet" where="SPO_TABLE_ALIAS = 'SSZ'" lazy="false" mutable="false" fetch="subselect">
         * <key column="SPO_TABLE_ENTITY_ID" not-null="false"/>
         * <one-to-many class="ch.sbb.aa.bb.cc.MyObsfuscatedClass"/>
         * </set>
         */
        foreach ($set->children() as $child) {
            $nodeName = strtolower($child->getName());

            $subItemDesc = $this->createItemDesc($child);

            switch (true) {
                case $nodeName == 'key' && $this->hasAtributes($child, [], [
                        'column',
                        'not-null',
                        'update',
                        'property-ref',
                    ], $subItemDesc):
                    // <set name="flags" inverse="true" cascade="none" batch-size="10" fetch="select">
                    // <key column="ANFRAGE_ID" />
                    // <one-to-many class="Flag" />
                    // <filter name="flagsForFunktion" condition="funktion_Id = :fid" />
                    // </set>
                    $this->addFinding($nodeName);
                    break;

                case $nodeName == 'one-to-many' && $this->hasAtributes($child, [
                        'class',
                    ], [
                    ], $subItemDesc):
                    // <set name="flags" inverse="true" cascade="none" batch-size="10" fetch="select">
                    // <key column="ANFRAGE_ID" />
                    // <one-to-many class="Flag" />
                    // <filter name="flagsForFunktion" condition="funktion_Id = :fid" />
                    // </set>
                    $this->addFinding($nodeName);
                    break;

                case $nodeName == 'many-to-many' && $this->hasAtributes($child, [
                        'class',
                        'column',
                    ], [
                        'name',
                    ], $subItemDesc):
                    // <set name="funktionen" table="ANFRAGE_FUNKTION" cascade="merge"
                    // lazy="false">
                    // <key column="ANFRAGE_ID" />
                    // <many-to-many class="Funktion" column="FUNKTION_ID" />
                    // </set>
                    $this->addFinding($nodeName);
                    break;

                case $nodeName == 'filter' && $this->hasAtributes($child, [
                        'name',
                        'condition',
                    ], [
                    ], $subItemDesc):
                    // <set name="flags" inverse="true" cascade="none" batch-size="10" fetch="select">
                    // <key column="ANFRAGE_ID" />
                    // <one-to-many class="Flag" />
                    // <filter name="flagsForFunktion" condition="funktion_Id = :fid" />
                    // </set>
                    $this->addFinding($nodeName);
                    break;

                case $nodeName == 'index' && $this->hasAtributes($child, [
                        'column',
                    ], [
                    ], $subItemDesc):
                    // <list name="signaleAsList" lazy="false" inverse="true" cascade="delete">
                    // <key column="HSI_HFW_ID" not-null="false"/>
                    // <index column="HSI_REIHENFOLGE"></index>
                    // <one-to-many class="ch.sbb.aa.bb.cc.MyObsfuscatedClass" />
                    // </list>

                    $this->addFinding($nodeName);
                    break;

                case $nodeName == 'element' && $this->hasAtributes($child, [
                        'type',
                        'column',
                    ], [
                        'not-null',
                    ], $subItemDesc):
                    // <set name="massnahmeErledigt" table="Massnahme_Erledigt" lazy="true">
                    // <key column="konzept_version_id"/>
                    // <element type="string" column="massnahme_uuid" not-null="true" />
                    // </set>
                    $this->addFinding($nodeName);
                    break;

                case $nodeName == 'subselect' && $this->hasAtributes($child, [], [], $subItemDesc):
                    // <set name="nachbarBptEntityIdSet1" lazy="false"
                    // cascade="none" inverse="true" mutable="false">
                    // <subselect>
                    // <![CDATA[
                    // select
                    // bp.id_version fk,
                    // bpv.id_betriebspunkt_von wert
                    // from
                    // betriebspunkt bp,
                    // bp_verbindung bpv
                    // where
                    // bp.id_betriebspunkt = bpv.id_betriebspunkt_bis
                    // and
                    // (
                    // bp.eff_gueltig_ab < bpv.eff_gueltig_bis
                    // and bp.eff_gueltig_bis > bpv.eff_gueltig_ab
                    // )
                    // ]]>
                    // </subselect>
                    // <key column="fk" not-null="true" />
                    // <element type="DunoEntityId" column="wert" />
                    // </set>
                    $subItemDesc->addAttribute('sql', (string)$child);
                    $this->addFinding($nodeName);
                    break;

                case $nodeName == 'list-index' && $this->hasAtributes($child, [
                        'column',
                    ], [
                    ], $subItemDesc):
                    // <list name="musterBpAbfolge" table="UMLEIT_BP_MUSTER" access="property">
                    // <!-- Laut Hibernate-Doc: Hibernate does not distinguish between a null collection reference and an empty collection -->
                    // <!-- Zur Sicherheit wird im Setter eine null-Referenz auf eine leere Liste normalisiert -->
                    // <key column="UBPM_URG_ID" not-null="true" />
                    // <list-index column="UBPM_REIHENFOLGE" />
                    // <element column="UBPM_BPT_ID" type="DunoEntityId" />
                    // </list>
                    $this->addFinding($nodeName);
                    break;

                case $nodeName == 'composite-element' && $this->hasAtributes($child, [
                        'class',
                    ], [
                    ], $subItemDesc):
                    /*
                    <composite-element class="ch.sbb.aa.bb.cc.MyObsfuscatedClass">
                    <property name="hauptgleisId" column="UMH_HGL_ID" type="DunoEntityId"/>
                    <property name="betriebspunktId" column="UMH_BPT_ID" type="DunoEntityId"/>
                    </composite-element>*/
                    $this->addFinding($nodeName);
                    break;

                default:
                    fwrite(STDERR, "Unknown Type in SET " . $child->getName() . " @ " . (str_replace("\n", " ", $child->asXML())) . "\n");
            }

            $itemDesc->addAttribute($nodeName, $subItemDesc);
        }
    }

    // private function evaluateCompositeId()
    private function hasAtributes(SimpleXMLElement $child, array $requiredAttributes, array $optionalAttributes = array(), ItemDescription $itemDesc = null, $log_error = true)
    {
        foreach ($requiredAttributes as $attribut) {
            if (empty($child->attributes()->$attribut)) {
                if ($log_error == true) {
                    fwrite(STDERR, "Missing required attribute: \"$attribut\" not found @ " . $child->asXML() . "\n");
                }
                return false;
            }
        }

        $all_attributes = array_merge($requiredAttributes, $optionalAttributes);
        foreach ($child->attributes() as $key => $val) {
            if (!in_array($key, $all_attributes)) {
                if ($log_error == true) {
                    fwrite(STDERR, "Invalid attribute: \"$key\" found @ " . $child->asXML() . "\n");
                }
                return false;
            }

            if ($itemDesc) {
                if (count($val->children()) === 0) {
                    $val = (string)$val;
                }
                $itemDesc->addAttribute($key, $val);
            }
        }
        return true;
    }

    private function validateChildren(SimpleXMLElement $item, array $allowed_children = array(), ItemDescription $itemDesc = null)
    {
        /* @var $itemDesc SimpleXMLElement */
        foreach ($item->children() as $child) {
            $nodeName = strtolower($child->getName());

            $childDescs = array_filter($allowed_children, function (ChildDescripton $element) use ($nodeName) {
                return ($element->getName() === $nodeName);
            });

            if (empty($childDescs)) {
                return false;
            }

            foreach ($childDescs as $childDesc) {
                if ($this->validateChild($child, $childDesc, $nodeName, $itemDesc) !== true) {
                    return false;
                }
            }
        }
        return true;
    }

    private function validateChild(SimpleXMLElement $item, ChildDescripton $childDesc, $nodeName, ItemDescription $itemDesc)
    {
        if (!$this->hasAtributes($item, $childDesc->getRequiredAttributes(), $childDesc->getOptionalAttributes())) {
            return false;
        }
        if ($itemDesc) {
            switch ($nodeName) {
                case 'meta':
                    $itemDesc->addMeta((string)$item->attributes()->attribute, (string)$item->children()[0]);
                    break;

                default:
                    if ($childDesc instanceof ParamChildDescripton && in_array('name', $childDesc->getRequiredAttributes())) {
                        $itemDesc->addAttribute((string)$item->attributes()->name, (string)$item->children()[0]);
                    }

                    if ($childDesc instanceof ColumnChildDescription && in_array('name', $childDesc->getRequiredAttributes())) {
                        $itemDesc->addAttribute('column', (string)$item->attributes()->name);
                    }

                    if ($childDesc instanceof ValueChildDescripton) {
                        $itemDesc->addAttribute($nodeName, (string)$item);
                        return true;
                    }

                    $processAttribute = function ($a) use ($item, $itemDesc, $nodeName) {
                        if (!empty($item->attributes()->$a)) {
                            $n = ($a === 'name') ? $nodeName : $a;
                            $itemDesc->addAttribute($n, $item->attributes()->$a);
                        }

                        if ($item->count() > 0) {
                            $fist_child = trim((string)$item->children()[0]);
                            if (!empty($fist_child)) {
                                $itemDesc->addAttribute($nodeName . '__value', $fist_child);
                            }

                            // look at all children
                            foreach ($item->children() as $child) {
                                $childNodeName = $child->getName();
                                if ($childNodeName === 'param') {
                                    $paramName = (string)$child->attributes()['name'];
                                    $paramValue = (string)$child;
                                    $itemDesc->addAttribute($paramName, $paramValue);
                                }
                            }
                        }
                    };

                    foreach ($childDesc->getRequiredAttributes() as $a) {
                        $processAttribute($a);
                    }

                    foreach ($childDesc->getOptionalAttributes() as $a) {
                        $processAttribute($a);
                    }
                    break;
            }
        }

        return true;
    }

    private function addFinding($ident)
    {
        if (empty($this->findings[$ident])) {
            $this->findings[$ident] = 1;
        } else {
            $this->findings[$ident]++;
        }
    }

    private function addItemDesc(ItemDescription $itemDesc)
    {
        $this->itemDescs[] = $itemDesc;
    }

    public function getFindings(): array
    {
        return $this->findings;
    }

    /**
     * Undocumented function
     *
     * @return array Map<string token ident, Array<ItemDescription>> A map of grouped iten Descriptions by token info.
     */
    public function findTokensAndCreateAnnotations(): array
    {
        $tokenInfos = array();

        foreach ($this->itemDescs as $itemDesc) {
            $Annotation = null;
            /* @var $itemDesc ItemDescription */
            switch ($itemDesc->getNodeName()) {
                case 'property':
                case 'property_formula':
                    $Annotation = new PropertyAnnotation($itemDesc, $this);
                    break;
                case 'version':
                    $Annotation = new VersionAnnotation($itemDesc, $this);
                    break;
                case 'id':
                    $Annotation = new IdAnnotation($itemDesc, $this);
                    break;
                case 'set':
                case 'bag':
                case 'list':
                    $Annotation = new SetAnnotation($itemDesc, $this);
                    break;
                case 'many-to-one':
                    $Annotation = new ManyToOneAnnotation($itemDesc, $this);
                    break;
                case 'component':
                    $Annotation = new ComponentAnnotation($itemDesc, $this);
                    break;
                case 'subclass':
                    $this->subclassAnnotations[] = new DiscriminatorValueAnnotation($itemDesc, $this);
                    break;
                case 'discriminator':
                    $this->discriminatorAnnotations[] = new DiscriminatorAnnotation($itemDesc, $this);
                    break;
            }


            if ($Annotation != null) {
                $itemDesc->setAnnotation($Annotation);

                $tokenInfo = $this->findToken($itemDesc);

                $ident = $tokenInfo->getIdent();
                if (empty($tokenInfos[$ident])) {
                    $tokenInfos[$ident] = array();
                }
                $tokenInfos[$ident][] = $itemDesc;
            }
        }

        return $tokenInfos;
    }

    /**
     * Find tokes for items descriptions
     * and inject tokenInfo into itemDesc.
     */
    private function findToken(ItemDescription $itemDesc): TokenInfo
    {
        if ($itemDesc->getAnnotation()->getName() === null) {
            throw new Exception('This annotation dosent provide a name');
        }

        // attempt to find a token in different likely permutations (is/get prefix, different case writing)
        try {
            if($itemDesc->getAccessType() === AccessType::FIELD){
                // the hbm specifies access=field, thus we first try to find a field.
                $tokenInfo = $this->getFieldInfo($this->javaClass, $itemDesc->getAnnotation()->getName());
            }else{
                $tokenInfo = $this->getMethodeInfo($this->javaClass, $itemDesc->getAnnotation()->getMethodeName());
            }
        } catch (Exception $e1) {
            try {
                $tokenInfo = $this->getMethodeInfo($this->javaClass, $itemDesc->getAnnotation()->getMethodeName(true, true));
            } catch (Exception $e2) {
                try {
                    $tokenInfo = $this->getMethodeInfo($this->javaClass, $itemDesc->getAnnotation()->getMethodeName(false, false));
                } catch (Exception $e3) {
                    try {
                        // if it wasn't found as a method, try to find it as a field
                        $tokenInfo = $this->getFieldInfo($this->javaClass, $itemDesc->getAnnotation()->getName());
                    } catch (Exception $e4) {
                        throw $e1;
                    }
                }
            }
        }

        if ($tokenInfo->getClassFinder()->getClassName() === $this->javaClass->getClassName()) {
            //token is in this class
            $tokenInfo->setImplementedInSuperclass(false);
        } else {
            //token is not in this class
            $tokenInfo->setImplementedInSuperclass(true);
        }

        ####### important #######
        $itemDesc->setTokenInfo($tokenInfo);


        return $tokenInfo;
    }

    public function writeAnnotations(bool $countManualAnnotatiosAsSuccesfull = false)
    {
        // start to write at the bottom to not recalculate itemDesc index every time.
        usort($this->itemDescs, function (ItemDescription $a, ItemDescription $b) {
            $indexA = (empty($a->getTokenInfo())) ? 0 : $a->getTokenInfo()->getIndex();
            $indexB = (empty($b->getTokenInfo())) ? 0 : $b->getTokenInfo()->getIndex();

            return $indexB - $indexA;
        });

        foreach ($this->itemDescs as $itemDesc) {
            if ($itemDesc->getAnnotation() != null) {
                $wasWritten = $this->writeAnnotationToJavaFile($itemDesc);

                if ($wasWritten === true || ($countManualAnnotatiosAsSuccesfull && $this->hasJpaOrHibernateAnnotations($itemDesc))) {
                    $this->successFullAnnotations++;
                }
            } else {
                if ($countManualAnnotatiosAsSuccesfull && $this->hasJpaOrHibernateAnnotations($itemDesc)) {
                    $this->successFullAnnotations++;
                }

                if (!empty(COLLECT_UNSUPPORTED_ANNOTATIONS)) {
                    file_put_contents(COLLECT_UNSUPPORTED_ANNOTATIONS, $itemDesc->getXml() . "\n\n############\n", FILE_APPEND);
                }
            }
        }
    }

    public function writeClassAnnotations(): void
    {
        $fieldOverrideAnnotations = rtrim($this->javaClass->generateFinalFieldOverrideAnnotations());
        if (!empty($fieldOverrideAnnotations) &&
            strpos($this->javaClass->getAnnotations(), '@AttributeOverrides') === false &&
            strpos($this->javaClass->getAnnotations(), '@AssociationOverrides') === false) {

            $this->javaClass->addClassAnnotation($fieldOverrideAnnotations);
        }

        // entity + table annotation
        if (strpos($this->javaClass->getAnnotations(), '@Table') === false) {
            $this->javaClass->addClassAnnotation(Table::generateAnnotation($this->dbTable, $this->schema));
        }

        if (strpos($this->javaClass->getAnnotations(), '@Entity') === false && !$this->javaClass->isAbstract()) {
            $this->javaClass->addClassAnnotation('@Entity');
        } else if (strpos($this->javaClass->getAnnotations(), '@MappedSuperclass') === false && $this->javaClass->isAbstract()) {
            $this->javaClass->addClassAnnotation('@MappedSuperclass');
        }

        $discriminatorAnnotationString = rtrim($this->generateDiscriminatorAnnotations());
        if (!empty($discriminatorAnnotationString)) {
            $this->javaClass->addClassAnnotation($discriminatorAnnotationString);
        }

    }

    private function generateDiscriminatorAnnotations(): string
    {
        return implode("\n", array_unique(array_map(function (DiscriminatorAnnotation $annotation) {
            return $annotation->generateAnnotations();
        }, $this->getDiscriminatorAnnotations())));
    }

    public function writeFile(): void
    {
        if ($this->successFullAnnotations > 0) {
            $this->javaClass->writeFile();
        }
    }

    public function addSuccessFullAnnotations(int $additionalAnnotations): void
    {
        $this->successFullAnnotations += $additionalAnnotations;
    }

    public function getItemDescs(): ?array
    {
        return $this->itemDescs;
    }

    public function aggregateTablePrefix()
    {
        $tablePrefixIndex = PHP_INT_MAX;
        $lastName = '';
        /* @var $itemDesc ItemDescription */
        foreach ($this->itemDescs as $itemDesc) {
            if (!empty($itemDesc->getAttributes()['column'])) {
                $name = strtoupper(trim((string)$itemDesc->getAttributes()['column']));
                if (!empty($lastName)) {
                    $tablePrefixIndex = min($tablePrefixIndex, $this->findCommonIndex($lastName, $name));
                }
                $lastName = $name;
            }
        }

        if ($tablePrefixIndex > 0 && $tablePrefixIndex !== PHP_INT_MAX) {
            $tablePrefixCandidate = substr($lastName, 0, $tablePrefixIndex);
            // The Prefix should end with an _, should not be longer than two _ stages and shouldn't be ID_ because that's usually used for interim tables
            if (substr($tablePrefixCandidate, -1) == "_" && substr_count($tablePrefixCandidate, "_") <= 2 && $tablePrefixCandidate !== "ID_") {
                $this->tablePrefix = $tablePrefixCandidate;
            }
        }
    }

    /**
     * Finds the index of the last common character of the two Strings
     */
    private function findCommonIndex($left, $right): int
    {
        return strspn($left ^ $right, "\0");
    }

    private function writeAnnotationToJavaFile(ItemDescription $itemDesc): bool
    {
        $tokenInfo = $itemDesc->getTokenInfo();
        if (empty($tokenInfo)) {
            echo "ERROR:: Unable to find token info\n";
            echo $itemDesc->getXml() . self::$ERROR_SEPERATOR;
            return false;
        }

        $annotation = $itemDesc->getAnnotation();

        $removeTablePrefix = ($tokenInfo->isImplementedInSuperclass());
        $annotationStr = $annotation->generateAnnotations($removeTablePrefix);
        if ($annotationStr === null) {
            if (PRINT_ANNOTATION_CREATION_ERRORS) {
                echo "ERROR:: " . $annotation->getError() . "\n";
                echo $itemDesc->getXml() . self::$ERROR_SEPERATOR;
            }
            return false;
        }

        try {
            if ($tokenInfo instanceof MethodInfo && $tokenInfo->isOverwritten()) {
                throw new Exception('Methode "' . $this->javaClass->getClassName() . '"."' . $annotation->getMethodeName() . '" not allowed to set annotation for overridden methodes');
            }

            if ($annotation instanceof IdAnnotation) {
                $this->writeGeneratorAnnotation($annotation, $tokenInfo);
            }

            if ($annotation instanceof DiscriminatorAnnotation) {
                $this->writeDiscriminatorAnnotation($annotation);
            }

            if ($this->shouldOverride($tokenInfo)) {
                if ($annotation instanceof OverrideSupporting) {
                    $this->writeFieldOverrideAnnotation($annotation, $tokenInfo);
                } else {
                    $javaClassName = $this->javaClass->getClassName();
                    $methodName = $annotation->getMethodeName();
                    echo "ERROR:: " . get_class($annotation) . " dont support overwritng but needs to for $javaClassName::$methodName\n";
                }
            }

            $this->writeFieldAnnotation($annotation, $tokenInfo, $removeTablePrefix, $annotationStr);

            return true;
        } catch (Exception $e) {
            echo "ERROR:: " . $e->getMessage() . "\n";
            echo $itemDesc->getXml() . "\n" . $annotationStr . self::$ERROR_SEPERATOR;

            if (!empty(COLLECT_UNSUPPORTED_ANNOTATIONS)) {
                file_put_contents(COLLECT_UNSUPPORTED_ANNOTATIONS, $itemDesc->getXml() . "\n\n############\n", FILE_APPEND);
            }
        }

        return false;
    }

    /**
     * Checks whether an override annotation should be generated, given the config option OVERRIDE_STRATEGY.
     */
    private function shouldOverride(TokenInfo $tokenInfo) : bool
    {
        if(OVERRIDE_STRATEGY === OverrideStrategyOptions::COUNT){
            return ($tokenInfo->getUseCount() > 1);
        }else{
            return $tokenInfo->isImplementedInSuperclass();
        }
    }

    private function writeFieldOverrideAnnotation(Annotation $annotation, TokenInfo $tokenInfo): void
    {

        if ($annotation instanceof VerifyBeforeWriteAnnotation) {
            $result = $annotation->checkFieldOverrideAnnotation(
                implode("\n", $this->javaClass->getFieldOverrideAnnotations())
            );
            if ($result === false) {
                return;
            }
        }

        $this->javaClass->addFieldOverrideAnnotation($annotation->generateFieldOverrideAnnotation());
    }

    private function writeGeneratorAnnotation(IdAnnotation $annotation, TokenInfo $tokenInfo): void
    {
        $generatorAnnotation = $annotation->generateGeneratorAnnotation();
        if (!empty($generatorAnnotation)) {
            $this->javaClass->addClassAnnotation($generatorAnnotation);
        }
    }

    private function writeDiscriminatorAnnotation(DiscriminatorAnnotation $annotation): void
    {
        $discriminatorAnnotation = $annotation->generateAnnotationComponents();
        if (!empty($discriminatorAnnotation)) {
            $this->javaClass->addClassAnnotation($discriminatorAnnotation);
        }
    }

    private function writeFieldAnnotation(Annotation $annotation, TokenInfo $tokenInfo, bool $removeTablePrefix, string $annotationStr): void
    {
        if ($annotation instanceof VerifyBeforeWriteAnnotation) {
            $result = $annotation->checkAnnotation(
                $tokenInfo->getAnnotations(),
                $removeTablePrefix
            );
            if ($result === false) {
                return;
            }
        }

        $tokenClassFinder = $tokenInfo->getClassFinder();

        if($annotation instanceof IdAnnotation && $annotation->isAssigned() && $tokenClassFinder->isAbstract()){
            // ID is a special case in abstract classes. We'd rather do it by hand in the superclasses
            // This puts a to-do comment at the position where the ID related annotations would go in a superclass
            $tokenClassFinder->addAnnotation($tokenInfo, TodoAnnotation::generateIdTodoAnnotation(), false);
        }else{
            $tokenClassFinder->addAnnotation($tokenInfo, $annotationStr, true);
        }

        if ($tokenInfo->getClassFinder() !== $this->javaClass) {
            // Write file if different, because for class finder we write also.
            $tokenInfo->getClassFinder()->writeFile();
        }
    }

    /**
     * Check if the current annotations of the tokenInfo has an annoation matching:
     *  javax.persistence.*
     *  org.hibernate.annotations.*
     *
     * @param ItemDescription $itemDesc
     * @return bool
     */
    private function hasJpaOrHibernateAnnotations(ItemDescription $itemDesc): bool
    {
        $tokenInfo = $itemDesc->getTokenInfo();
        if ($tokenInfo === null) {
            $itemDesc->setAnnotation(new PlaceboAnnotation($itemDesc, $this));
            try {
                $tokenInfo = $this->findToken($itemDesc);
                if (empty($tokenInfo)) {
                    return false;
                }
            } catch (Exception $e) {
                return false;
            }
        }

        foreach ($tokenInfo->getClassFinder()->getImports() as $import) {
            if (substr($import, 0, 18) === 'javax.persistence.') {
                if (strpos($tokenInfo->getAnnotations(), '@' . substr($import, 18)) !== false) {
                    return true;
                }
            } else if (substr($import, 0, 26) === 'org.hibernate.annotations.') {
                if (strpos($tokenInfo->getAnnotations(), '@' . substr($import, 26)) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getMethodeInfo($class, string $methodeName): TokenInfo
    {
        $classFinder = ($class instanceof JavaClassFinder) ? $class : JavaClassFinder::getInstance($this->converter->getRootFilePath(), $class);

        $methodeInfo = $classFinder->getMethodeInfo($methodeName);
        if ($methodeInfo !== null) {
            return $methodeInfo;
        }

        $superClass = $classFinder->getSuperClass();
        if (empty($superClass)) {
            throw new InvalidClassException('Unable to find methode "' . $methodeName . '" in "' . $this->javaClass->getClassName() . '" or its supers');
        } else {
            return $this->getMethodeInfo($superClass, $methodeName);
        }
    }

    private function getFieldInfo($class, string $fieldName): TokenInfo
    {
        $classFinder = ($class instanceof JavaClassFinder) ? $class : JavaClassFinder::getInstance($this->converter->getRootFilePath(), $class);

        $fieldInfo = $classFinder->getFieldInfo($fieldName);
        if ($fieldInfo !== null) {
            return $fieldInfo;
        }
        $superClass = $classFinder->getSuperClass();
        if (empty($superClass)) {
            throw new InvalidClassException('Unable to find field "' . $fieldName . '" in "' . $this->javaClass->getClassName() . '" or its supers');
        } else {
            return $this->getFieldInfo($superClass, $fieldName);
        }
    }

    public function getSuccessFullAnnotations(): int
    {
        return $this->successFullAnnotations;
    }

    public function deleteHbmlFile(): void
    {
        unlink($this->hbmFile);
    }

    public function migrateHbmToClassRegistration(RegistrationMigrator $registrationMigrator): void
    {
        $registrationMigrator->migrate($this->hbmFile);
    }
}
