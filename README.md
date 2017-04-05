# JSKOS-BARTOC

# Description

This repository contains a wrapper to access the public [Basel Register of Thesauri, Ontologies & Classifications (BARTOC)](http://bartoc.org) in [JSKOS format](https://gbv.github.io/jskos/) via [Entity Lookup Microservice API (ELMA)](http://gbv.github.io/elma/).

# Requirements

Requires PHP 7, the [jskos-rdf](https://packagist.org/packages/gbv/jskos-rdf) PHP library and [Text_LanguageDetect](http://pear.php.net/package/Text_LanguageDetect).

# Installation

~~~bash
composer require gbv/jskos-bartoc
~~~

This will automatically create `composer.json` for your project (unless it already exists) and add jskos-bartoc as dependency. Composer also generates `vendor/autoload.php` to get autoloading of all dependencies: 

~~~php
require_once __DIR__ . '/vendor/autoload.php';

$service = new \BARTOC\JSKOS\Service();
~~~

# Contributung

Bugs and feature request are [tracked on GitHub](https://github.com/gbv/jskos-bartoc/issues).

See `CONTRIBUTING.md` of repository [jskos-php](https://packagist.org/packages/gbv/jskos) for contributing guidelines.

# Author and License

Jakob Voß <jakob.voss@gbv.de>

JSKOS-BARTOC is licensed under the LGPL license (see `LICENSE` for details).

[![Latest Stable Version](https://poser.pugx.org/gbv/jskos-bartoc/v/stable)](https://packagist.org/packages/gbv/jskos-bartoc)
[![License](https://poser.pugx.org/gbv/jskos/license)](https://packagist.org/packages/gbv/jskos-bartoc)
[![Build Status](https://img.shields.io/travis/gbv/jskos-bartoc.svg)](https://travis-ci.org/gbv/jskos-bartoc)
[![Coverage Status](https://coveralls.io/repos/gbv/jskos-bartoc/badge.svg?branch=master)](https://coveralls.io/r/gbv/jskos-bartoc)
