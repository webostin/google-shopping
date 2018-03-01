<?php

namespace Webostin\Google\Shopping;


interface ProductInterface
{
    public function getOfferId();

    public function getTitle();

    public function getDescription();

    public function getLink();

    public function getImageLink();

    public function getContentLanguage();

    public function getTargetCountry();

    public function getChannel();

    public function getAvailability();

    public function getCondition();

    public function getGoogleProductCategory();

    public function getGetin();

    public function getProductType();
}