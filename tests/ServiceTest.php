<?php

namespace BARTOC\JSKOS;

use JSKOS\Concept;

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
    }
}
