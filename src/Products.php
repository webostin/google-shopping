<?php

namespace Webostin\Google\Shopping;


class Products extends Base
{

    private $warnings;
    private $errors;

    public function run()
    {
        if (is_null($this->session->websiteUrl)) {
            throw Exception(
                'Cannot run Products workflow on a Merchant Center account without '
                . 'a configured website URL.');
        }
    }

    public function insertProduct(\Google_Service_ShoppingContent_Product $product)
    {
        $response = $this->session->service->products->insert(
            $this->session->merchantId, $product);

        $warnings = $response->getWarnings();
        foreach ($warnings as $warning) {
            $this->warnings[] = printf(" [%s] %s\n", $warning->getReason(), $warning->getMessage());
        }
    }

    public function getProduct(\Google_Service_ShoppingContent_Product $product)
    {
        $productId = $this->buildProductId($product);
        $product = $this->session->service->products->get(
            $this->session->merchantId, $productId);
        return $product;
    }

    public function deleteProduct(\Google_Service_ShoppingContent_Product $product)
    {
        $productId = $this->buildProductId($product);
        // The response for a successful delete is empty
        $this->session->service->products->delete(
            $this->session->merchantId, $productId);
    }


    public function getWarnings()
    {
        return $this->warnings;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function hasWarnings()
    {
        return ($this->warnings);
    }

    public function hasErrors()
    {
        return ($this->errors);
    }

    private function buildProductId(\Google_Service_ShoppingContent_Product $product)
    {
        return sprintf('%s:%s:%s:%s', $product->getChannel(), $product->getContentLanguage(),
            $product->getTargetCountry(), $product->getOfferId());
    }


}