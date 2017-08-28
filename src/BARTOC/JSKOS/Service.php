<?php

/**
 * Basic JSKOS concept schemes endpoint for BARTOC.org.
 */

namespace BARTOC\JSKOS;

use JSKOS\Concept;
use JSKOS\ConceptScheme;
use JSKOS\Registry;
use JSKOS\Result;
use JSKOS\RDF\Mapper;
use JSKOS\URISpaceService;
use BARTOC\LanguageDetector;
use JSKOS\ConfiguredService;
use Symfony\Component\Yaml\Yaml;

class Service extends ConfiguredService
{
    use LanguageDetector;

    protected $supportedParameters = ['notation','search'];

    private $languages = [];
    private $licenses  = [];
    private $kostypes  = [];
    private $urispace;
 
    public function __construct(array $config=[]) {
        $config = Yaml::parse(file_get_contents(__DIR__."/Service.yaml"));
        $this->configure($config);

        $this->mapper = new Mapper($config);
        $this->urispace = new URISpaceService($config['_uriSpace']);

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

    public function query(array $query=[], string $path = ''): Result
    {
        $jskos = $this->urispace->query($query, $path);
        if (count($jskos)) {
            return new Result( [$this->queryUri($jskos[0]->uri)] );
        } elseif (isset($query['search'])) {
            return new Result( $this->search($query['search']) );
        } else {
            return;
        }
    }

    public function queryUri($uri): ConceptScheme {
        $rdf = Mapper::loadRDF($uri); # TODO
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
        $uris = Mapper::getURIs($rdf, 'rdf:type');
        if (!in_array("http://schema.org/Dataset", $uris)) {
            return;
        }

        if ( empty(Mapper::getURIs($rdf, 'dc:language')) ) {
            $jskos = new Registry(['uri' => $uri ]);
            if (!$jskos->type) $jskos->type = [];
            
            # registry properties (TODO: fix wrong RDF at BARTOC instead)
            $uris = Mapper::getURIs($rdf, 'rdfs:label');
            if (in_array("http://bartoc.org/en/Full-Repository/Full-terminology-repository-provides-terminology-content",$uris)) {
                $type = 'http://purl.org/dc/dcmitype/Collection';
            } else {
                $type = 'http://purl.org/cld/cdtype/CatalogueOrIndex';
            }
            $jskos->type[] = $type;
        } else {
            $jskos = new ConceptScheme(['uri' => $uri]);
        }

        $this->mapper->applyAtResource($rdf, $jskos); 


        # map BARTOC license to License URI
        if ($jskos->license) {
            $jskos->license = array_filter(array_map(
                function ($uri) { return $this->licenses[$uri] ?? null; },
                $jskos->license->map(function($m){return $m->uri;})
            ));
        }

        # map BARTOC topic URIs to Eurovoc and DDC URIs
        if (!empty($jskos->subject)) {
            foreach ( $jskos->subject as $i => $subject ) {
                $id = $subject->uri;
                $uri = null;
                $label = null;
                if (preg_match('!^http://bartoc.org/en/DDC/23/([0-9]+)!', $id, $match)) {
                    $uri = "http://dewey.info/class/{$match[1]}/e23/";                    
                } elseif (preg_match('!^http://bartoc.org/en/EuroVoc/([0-9]+)!', $id, $match)) {
                    $uri = "http://eurovoc.europa.eu/".$match[1];
                }
                if ($uri) {
                    $concept = new Concept([ 
                        'uri' => $uri, 
                        'identifier' => [$id],
                        'notation' => [$match[1]], 
                    ]);
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
        
        # map languages to ISO 639-2 (primary) or ISO 639-2/T (Terminology, three letter)
        $languages = Mapper::getURIs($rdf, 'dc:language');
        if (count($languages)) {
            $jskos->languages = [];
            foreach ($languages as $lang) {
                if (isset($this->languages[$lang])) {
                    $jskos->languages[] = $this->languages[$lang]['iana'];
                } else {
                    error_log("Unknown language: $lang");
                }
            }
        }

        # try to find out names and languages

        $names = [];
        $prefLabel = [];
        $altLabel = [];
        foreach ($rdf->allLiterals('schema:name') as $name) {
            $value = $name->getValue();
            if (preg_match('/^[A-Z]{2,5}$/', $value)) {
                $jskos->notation = [ $value ];
            } elseif( $name->getLang() ) {
                $prefLabel[ $name->getLang() ] = $value;
            } elseif( $name->getDatatypeUri() == "http://id.loc.gov/vocabulary/iso639-2/eng" ) {
                $prefLabel['en'] = $value;
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
            $prefLabel[$defaultLanguage] = $prefLabels[0];
        } else {
            $names = array_unique(array_merge($names,$prefLabels));
        }

        if (count($jskos->languages) == 1 && count($names) == 1) {
            $prefLabel[ $jskos->languages[0] ] = $names[0];
        } else {
            if (count($names) == 1) {
                $prefLabel['und'] = $names[0];
            } elseif (count($names)) {
                $altLabel['und'] = $names;
            }
        }

        # try to detect language
        if (isset($prefLabel['und'])) {
            $label = $prefLabel['und'];
            $candidates = $jskos->languages 
                        ? $jskos->languages->map(function($m){return $m;}) : [];
            $guess = $this->detectLanguage( $label, $candidates );
            if ($guess) {
                $prefLabel[$guess] = $label;
                unset($prefLabel['und']);
            } else {
                # remove if same label in known language exists
                unset($prefLabel['und']);
                if (!in_array($label, $prefLabel)) {
                    $prefLabel['und'] = $label;
                }
            }
        }

        if (isset($altLabel['und'])) {
            $und = [];
            foreach ( $altLabel['und'] as $label ) {
                $candidates = $jskos->languages 
                            ? $jskos->languages->map(function($m){return $m;}) : [];
                $guess = $this->detectLanguage( $label, $candidates );
                if ($guess) {
                    $altLabel[$guess][] = $label;
                } else {
                    $und[] = $label;
                }
            }
            if (count($und)) {
                $altLabel['und'] = $und;
            } else {
                unset($altLabel['und']);
            }
        }

        if (count($prefLabel)) $jskos->prefLabel = $prefLabel;
        if (count($altLabel)) $jskos->altLabel = $altLabel;

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

 
