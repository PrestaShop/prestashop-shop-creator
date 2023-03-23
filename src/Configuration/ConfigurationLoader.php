<?php

namespace ShopGenerator\Configuration;

use Symfony\Component\Yaml\Yaml;

class ConfigurationLoader
{
    public function loadConfig(string $filename): array
    {
        $configuration = Yaml::parse(file_get_contents($filename))['parameters'];

        return [
            'shop_id' => $configuration['shop_id'],
            'langs' => $configuration['langs'],
            'customer' => $configuration['customers'],
            'manufacturer' => $configuration['manufacturers'],
            'supplier' => $configuration['suppliers'],
            'address' => $configuration['addresses'],
            'aliases' => $configuration['aliases'],
            'category' => $configuration['categories'],
            'warehouse' => $configuration['warehouses'],
            'carrier' => $configuration['carriers'],
            'specific_price' => $configuration['specific_prices'],
            'attribute_group' => $configuration['attribute_groups'],
            'product' => $configuration['products'],
            'attribute' => $configuration['attributes'],
            'cart' => $configuration['carts'],
            'cart_rule' => $configuration['cart_rules'],
            'customization' => $configuration['customizations'],
            'feature' => $configuration['features'],
            'feature_value' => $configuration['feature_values'],
            'order' => $configuration['orders'],
            'guest' => $configuration['guests'],
            'order_historie' => $configuration['order_histories'],
            'range_price' => $configuration['range_prices'],
            'range_weight' => $configuration['range_weights'],
            'product_attribute' => $configuration['product_attributes'],
            'image' => $configuration['images'],
            'order_message' => $configuration['order_messages'],
            'delivery' => $configuration['deliveries'],
            'connection' => $configuration['connections'],
            'product_supplier' => $configuration['product_suppliers'],
            'order_carrier' => $configuration['order_carriers'],
            'order_detail' => $configuration['order_details'],
            'feature_product' => $configuration['feature_products'],
            'store' => $configuration['stores'],
            'profile' => $configuration['profiles'],
            'stock_available' => $configuration['stock_availables'],
        ];
    }
}
