<?php

namespace Webostin\Google\Shopping;


class GoogleShopping
{

    private $config;
    private $products;
    private $session;
    private $errors;
    private $warnings;

    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
    }

    public function sendProduct(ProductInterface $product, PriceInterface $price)
    {
        $this->createSession();
        $adapter = new ProductAdapter($product, $price);
        $product = $adapter->getProduct();
        $products = $this->getProduts();

        $products->insertProduct($product);

        if ($products->hasErrors()) {
            $this->errors = $products->getErrors();
            return false;
        }
        if ($products->hasWarnings()) {
            $this->warnings = $products->getWarnings();
        }

        return true;
    }

    public function retrieveProduct(ProductInterface $product)
    {
        $this->createSession();
        $adapter = new ProductAdapter($product);
        $product = $adapter->getProduct();
        $products = $this->getProduts();

        $product = $products->getProduct($product);

        if ($products->hasErrors()) {
            $this->errors = $products->getErrors();
            return false;
        }
        if ($products->hasWarnings()) {
            $this->warnings = $products->getWarnings();
        }

        return $product;
    }

    public function removeProduct(ProductInterface $product)
    {
        $this->createSession();
        $adapter = new ProductAdapter($product);
        $product = $adapter->getProduct();
        $products = $this->getProduts();

        $products->deleteProduct($product);

        if ($products->hasErrors()) {
            $this->errors = $products->getErrors();
            return false;
        }
        if ($products->hasWarnings()) {
            $this->warnings = $products->getWarnings();
        }

        return true;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getWarnings()
    {
        return $this->warnings;
    }

    private function getProduts()
    {
        if ($this->products instanceof Products) {
            return $this->products;
        } else {
            $this->products = new Products($this->session);
        }
        return $this->products;
    }

    private function createSession()
    {
        if (!($this->session instanceof ContentSession)) {
            $this->session = new ContentSession($this->config);
        }
    }
}