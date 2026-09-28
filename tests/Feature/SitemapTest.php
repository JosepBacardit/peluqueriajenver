<?php

test('the sitemap is valid xml starting with the xml declaration', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');

    $content = $response->getContent();

    // Guards against short_open_tag=On (the case in the Docker php-fpm
    // image used for local development, see docker/php/Dockerfile on
    // chore/docker-local) compiling a leading literal "<?xml" as a PHP
    // open tag instead of emitting it as text, which 500s with
    // "syntax error, unexpected identifier \"version\"". Production has
    // short_open_tag=Off and never showed this, so this only reproduces
    // with a container whose PHP config enables it.
    expect($content)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>');
    expect(simplexml_load_string($content))->not->toBeFalse();
});
