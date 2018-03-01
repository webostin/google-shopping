# Google shopping content (Product only)
Features:
- adds product to google shopping content
- removes product from google shopping content
- retrieves product forom google shopping content

# Usage

```
$config = new ExampleConfig();
$googleShopping = new \Webostin\Google\Shopping\GoogleShopping($config); 

$product = new ExampleProduct();
$price = new ExamplePrice();
$googleShopping->sendProduct($product, $price);

dump($googleShopping->getErrors());
// it is possible to has some warnings
dump($googleShopping->getWarnings());

```

# Config Folder structure

```
config/
    content/
        service-account.json
```

# License
Apache-2.0