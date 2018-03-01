<?php

namespace Webostin\Google\Shopping;


abstract class Base
{
    protected $content;

    public function __construct(ContentSession $session)
    {
        $this->session = $session;
    }

    abstract public function run();
}