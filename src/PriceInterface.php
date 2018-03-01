<?php

namespace Webostin\Google\Shopping;


interface PriceInterface
{
    public function getValue();

    public function getCurrency();
}