<?php

/**
 * Basic JSKOS concept schemes endpoint for BARTOC.org.
 */

namespace BARTOC\JSKOS;

use JSKOS\Concept;
use JSKOS\ConceptScheme;
use JSKOS\Registry;
use JSKOS\Page;
use JSKOS\RDF\RDFMapping;
use BARTOC\LanguageDetector;


class Service extends \JSKOS\RDF\RDFMappingService {
    public static $CONFIG_DIR = __DIR__;

    use LanguageDetector;

    protected $supportedParameters = ['notation','search'];

    private $languages = [];
    private $licenses  = [];
    private $kostypes  = [];
 
    public function __construct() {
        parent::__construct();
        foreach (['languages','licenses','kostypes'] as $name) {
            $rows = array_map('str_getcsv', file(__DIR__."/$name.csv"));
            $keys = array_shift($rows);
            foreach( $rows as $row ) {
                $row = array_combine($keys, $row);
                $rows[$row['bartoc']] = $row;
            }
            $this->$name = $rows;
        }
    }

    public function query($query) {
        $jskos = $this->queryUriSpace($query);
        if ($jskos) {
            return $this->queryUri($jskos->uri);
        } elseif (isset($query['search'])) {
            return new Page( $this->search($query['search']) );
        } else {
            return;
        }
    }

    public function queryUri($uri) {
        $rdf = RDFMapping::loadRDF($uri);
        if (!$rdf || empty($rdf->getGraph()->propertyUris($uri))) return;

        // FIXME: There is a bug in Drupal RDFa output. This is a dirty hack to repair.
        foreach ( ['dct:subject', 'dct:type', 'dct:language', 'dct:format', 'schema:license'] 
            as $predicate) {
            foreach( $rdf->allResources($predicate) as $bnode ) {
                if ($bnode->isBNode()) {
                    foreach ($bnode->properties() as $p) {
                        foreach ($bnode->allResources($p) as $o) {
                            $rdf->add($predicate,$o);
                        }
                    }   
                }
            }
        }

        # echo $rdf->getGraph()->dump('turtle');
        
        // neither concept schemes nor registry
        $uris = RDFMapping::getURIs($rdf, 'rdf:type');
        if (!in_array("http://schema.org/Dataset", $uris)) {
            return;
        }

        if ( empty(RDFMapping::getURIs($rdf, 'dct:subject')) ) {
            $jskos = new Registry(['uri' => $uri ]);
            
            # registry properties (TODO: fix wrong RDF at BARTOC instead)
            $uris = RDFMapping::getURIs($rdf, 'rdfs:label');
            if (in_array("http://bartoc.org/de/Full-Repository/Full-terminology-repository-provides-terminology-content",$uris)) {
                var_dump($uris);
                $jskos->type = ['http://purl.org/dc/dcmitype/Collection'];
            } else {
                $jskos->type = ['http://purl.org/cld/cdtype/CatalogueOrIndex'];
            }
        } else {
            $jskos = new ConceptScheme(['uri' => $uri]);
        }

        $this->applyRDFMapping($rdf, $jskos); 


        $api = $rdf->allLiterals("nkos:serviceOffered");
        if (!empty($api)) {
            # TODO: support custom JSKOS fields so we can add this
            $jskos->subjectOf = [ [ 
                "url" => (string)$api[0],
                "prefLabel" => [ "en" => "API" ] 
            ] ];
        }

        # map license (TODO: move to RDFMapper)
        foreach ( RDFMapping::getURIs($rdf, 'schema:license') as $license ) {
            if (isset($this->licenses[$license])) {
                $jskos->license = [
                    $this->licenses[$license]
                ];
            }
        }

        # map BARTOC topic URIs to Eurovoc and DDC URIs
        if (!empty($jskos->subject)) {
            foreach ( $jskos->subject as $i => $subject ) {
                $id = $subject->uri;
                $uri = null;
                $label = null;
                if (preg_match('!^http://bartoc.org/en/DDC/23/(\d{3})!', $id, $match)) {
                    $uri = "http://dewey.info/class/{$match[1]}/e23/";                    
                } elseif (preg_match('!^http://bartoc.org/en/EuroVoc/(\d{3})!', $id, $match)) {
                    $uri = "http://eurovoc.europa.eu/".$match[1];
                }
                if ($uri) {
                    $concept = new Concept([ 'uri' => $uri, 'identifier' => [$id] ]);
                    if ($label) $concept->prefLabel = ['en' => $label];
                    $jskos->subject[$i] = $concept;
                }
            }
        }

        # map bartoc type URIs to NKOS type URIs
        if (!empty($jskos->type)) {
            foreach ( $jskos->type as $i => $type ) {
                if (isset($this->kostypes[$type])) {
                    $jskos->type[$i] = $this->kostypes[$type]['nkos'];
                }
            }
        }
        
        # ISO 639-2 (primary) or ISO 639-2/T (Terminology, three letter)
        foreach ( RDFMapping::getURIs($rdf, 'dc:language') as $language ) {
            if (isset($this->languages[$language])) {
                $jskos->languages[] = $this->languages[$language]['iana'];
            } else {
                error_log("Unknown language: $language");
            }
        }

        # try to find out names and languages

        $names = [];
        foreach ($rdf->allLiterals('schema:name') as $name) {
            $value = $name->getValue();
            if (preg_match('/^[A-Z]{2,5}$/', $value)) {
                $jskos->notation = [ $value ];
            } elseif( $name->getDatatypeUri() == "http://id.loc.gov/vocabulary/iso639-2/eng" ) {
                $jskos->prefLabel['en'] = $value;
            } else {
                $names[] = $value;
            }
        }

        $defaultLanguage = count($jskos->languages) == 1 ? $jskos->languages[0] : 'und';

        $prefLabels = [];
        # TODO: if multiple prefLabels, they could still be distinguished
        # For instance http://localhost:8080/BARTOC.php?uri=http%3A%2F%2Fbartoc.org%2Fen%2Fnode%2F2008
        foreach ($rdf->allLiterals('skos:prefLabel') as $name) {
           $prefLabels[] = $name->getValue();
        }
        if (count($prefLabels) == 1) {
            $jskos->prefLabel[$defaultLanguage] = $prefLabel[0];
        } else {
            $names = array_unique(array_merge($names,$prefLabels));
        }

        if (count($jskos->languages) == 1 && count($names) == 1) {
            $jskos->prefLabel[ $jskos->languages[0] ] = $names[0];
        } else {
            # error_log("Languages: ". implode(", ",$jskos->languages));
            if (count($names) == 1) {
                $jskos->prefLabel['und'] = $names[0];
            } elseif (count($names)) {
                $jskos->altLabel['und'] = $names;
            }
        }

        # try to detect language
        if (isset($jskos->prefLabel['und'])) {
            $label = $jskos->prefLabel['und'];
            $guess = $this->detectLanguage( $label, $jskos->languages );
            if ($guess) {
                $jskos->prefLabel[$guess] = $label;
                unset($jskos->prefLabel['und']);
            } else {
                # remove if same label in known language exists
                unset($jskos->prefLabel['und']);
                if (!in_array($label, $jskos->prefLabel)) {
                    $jskos->prefLabel['und'] = $label;
                }
            }
        }

        if (isset($jskos->altLabel['und'])) {
            $und = [];
            foreach ( $jskos->altLabel['und'] as $text ) {
                $guess = $this->detectLanguage( $text, $jskos->languages );
                if ($guess) {
                    $jskos->altLabel[$guess][] = $text;
                } else {
                    $und[] = $text;
                }
            }
            if (count($und)) {
                $jskos->altLabel['und'] = $und;
            } else {
                unset($jskos->altLabel['und']);
            }
        }

        return $jskos;
    }


    /**
     * Basic search as proof of concept. Of little use without a Drupal search API.
     */
    private function search($search) {
        $query = 'en/autocomplete_filter/title/title_finder/page/0/'.$search;
        $url = 'http://bartoc.org/index.php?' . http_build_query(['q'=>$query]);
        try {
            $json = @json_decode( @file_get_contents($url) );
            $schemas = [];
            foreach ( $json as $key => $value ) {
                # unfortunately IDs are not included in the result!
                $schemas[] = new ConceptScheme(['prefLabel' => ['en' => $key]]);
            }
            return $schemas;
        } catch (Exception $e) {
            error_log($e);
            return [];
        }
    } 
}

 
