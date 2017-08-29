<?php

namespace BARTOC\JSKOS;

use JSKOS\ConceptScheme;
use JSKOS\Registry;

class ServiceTest extends \PHPUnit\Framework\TestCase
{

    public function testLanguageDetector()
    {
        $service = new Service();
        $text = "The quick brown fox";

        $lang = $service->detectLanguage($text, ['en','it','zh']);
        $this->assertEquals('en', $lang);

        $lang = $service->detectLanguage($text, ['it','zh']);
        $this->assertEquals('und', $lang);

        $lang = $service->detectLanguage($text, null);
        $this->assertEquals('und', $lang);
    }

    public function testExamples()
    {
        $service = new Service();

        $jskos = $service->queryURI("http://bartoc.org/en/node/18600");
        $expect = json_decode(file_get_contents('tests/18600.json'), true);
        $expect = new ConceptScheme($expect);
        $this->assertEquals("$expect", "$jskos");

        $jskos = $service->queryURI("http://bartoc.org/en/node/2054");
        $expect = json_decode(file_get_contents('tests/2054.json'), true);
        $expect = new Registry($expect);
        $this->assertEquals($expect, $jskos);
    }
}
