<?php

namespace Webostin\Google\Shopping;


class ProductAdapter
{

    private $product;
    private $productOriginal;
    private $priceOriginal;

    public function __construct(ProductInterface $product, PriceInterface $price = null)
    {
        $this->productOriginal = $product;
        $this->priceOriginal = $price;

        $productAdopted = new \Google_Service_ShoppingContent_Product();

        $productAdopted->setOfferId($product->getOfferId());
        $productAdopted->setTitle($product->getTitle());
        $productAdopted->setDescription($product->getDescription());
        $productAdopted->setLink($product->getLink());
        $productAdopted->setImageLink($product->getImageLink());
        $productAdopted->setContentLanguage($product->getContentLanguage());
        $productAdopted->setTargetCountry($product->getTargetCountry());
        $productAdopted->setChannel($product->getChannel());
        $productAdopted->setAvailability($product->getAvailability());
        $productAdopted->setCondition($product->getCondition());
        $productAdopted->setGoogleProductCategory($product->getGoogleProductCategory());
        $getin = $product->getGetin();
        if ($getin) {
            $productAdopted->setGtin($getin);
        } else {
            $productAdopted->setIdentifierExists('no');
        }
        $productAdopted->setProductType($product->getProductType());

        if ($price instanceof PriceInterface) {
            $priceAdopted = new \Google_Service_ShoppingContent_Price();
            $priceAdopted->setValue($price->getValue());
            $priceAdopted->setCurrency($price->getCurrency());

            $productAdopted->setPrice($priceAdopted);
        }


        $this->product = $productAdopted;
    }

    public function getProduct()
    {
        return $this->product;
    }
}