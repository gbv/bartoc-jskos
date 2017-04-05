<?php

namespace BARTOC;

use Text_LanguageDetect;

trait LanguageDetector
{
	private $ld;

    public function detectLanguage(string $text, array $languages)
    {
        if (!$this->ld) {
            $this->ld = new Text_LanguageDetect();
            $this->ld->setNameMode(2);
        }

        $lang = $this->ld->detectSimple($text);
        if ($lang and in_array($lang, $languages)) {
            return $lang;
        }

        return 'und';
    }
}
