This repository contains a wrapper to access the public [Basel Register of Thesauri, Ontologies & Classifications (BARTOC)](http://bartoc.org) in [JSKOS format](https://gbv.github.io/jskos/) via [Entity Lookup Microservice API (ELMA)](http://gbv.github.io/elma/).

# Background

BARTOC is the most comprehensive registry of knowledge organization systems such as classification schemes, thesauri, glossaries, and ontologies. JSKOS is a unified format for information about knowledge organization systems, based on SKOS and JSON-LD. For background information see the following publications:

* Ledl, Andreas and Voss, Jakob: Describing Knowledge Organization Systems in BARTOC and JSKOS. In: *Proceedings of International Conference on Terminology and Knowledge Engineering (TKE 2016)*. p. 168-178. ISBN 978-87-999179-0-7
    * <http://hdl.handle.net/10760/29366> (paper)
    * <http://hdl.handle.net/10760/29572> (presentation)

* Voß, Jakob; Ledl, Andreas and Balakrishnan, U.: Uniform description and access to Knowledge Organization Systems with BARTOC and JSKOS. *TOTh conference 2016*
    * <https://doi.org/10.5281/zenodo.438019> (paper)

# Requirements

Requires PHP 7, the [jskos-rdf](https://packagist.org/packages/gbv/jskos-rdf) PHP library and [Text_LanguageDetect](http://pear.php.net/package/Text_LanguageDetect).

# Installation

~~~bash
composer require gbv/bartoc-jskos
~~~

This will automatically create `composer.json` for your project (unless it already exists) and add bartoc-jskos as dependency. Composer also generates `vendor/autoload.php` to get autoloading of all dependencies.

# Usage

The wrapper can be used as instance of class `\BARTOC\JSKOS\Service`, a subclass of `\JSKOS\Service`:

~~~php
require 'vendor/autoload.php';

$service = new \BARTOC\JSKOS\Service();

$jskos = $service->queryURI("http://bartoc.org/en/node/447");
$jskos = $service->query(["uri" => "http://bartoc.org/en/node/447"]);
$jskos = $service->query(["notation" => "447"]);
~~~

See [jskos-php-examples](https://github.com/gbv/jskos-php-examples/) for an example how to use the wrapper as part of a larger PHP application.

# Contributung

Bugs and feature request are [tracked on GitHub](https://github.com/gbv/bartoc-jskos/issues).

See `CONTRIBUTING.md` of repository [jskos-php](https://packagist.org/packages/gbv/jskos) for general guidelines.

# Author and License

Jakob Voß <jakob.voss@gbv.de>

bartoc-jskos is licensed under the LGPL license (see `LICENSE` for details).

[![Latest Stable Version](https://poser.pugx.org/gbv/bartoc-jskos/v/stable)](https://packagist.org/packages/gbv/bartoc-jskos)
[![License](https://poser.pugx.org/gbv/jskos/license)](https://packagist.org/packages/gbv/bartoc-jskos)
[![Build Status](https://img.shields.io/travis/gbv/bartoc-jskos.svg)](https://travis-ci.org/gbv/bartoc-jskos)
[![Coverage Status](https://coveralls.io/repos/gbv/bartoc-jskos/badge.svg?branch=master)](https://coveralls.io/r/gbv/bartoc-jskos)
