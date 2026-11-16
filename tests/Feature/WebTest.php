<?php

it('keeps the web welcome page available', function () {
    $this->get('/')->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8');
});
