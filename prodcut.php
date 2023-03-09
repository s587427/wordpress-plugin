<?php

// 1. 整理資料
// 2. 判斷產品是甚麼類型
// 3. 進入相對應的接口處裡產品

use Ramsey\Uuid\Uuid;

function createProduct($products) {

    if (!is_array($products)) echo "CreateProudct Error";
    foreach ($products as $product) {
        if ($product["ProductType"] == "Variable") {
            createVariableProduct($product);
        } elseif ($product["ProductType"] == "Simple") {
            createSimpleProduct($product);
        }
    }

    return "CreateProduct Success!";
}

function createVariableProduct($product) {

    $logger = wc_get_logger();
    $wcProduct =  new WC_Product_Variable();
    $wcProduct->set_name($product["ProductTitle"]);
    $wcProduct->set_description($product["Description"]);
    $wcProduct->set_image_id("");
    $wcProduct->set_sku($product["SKU"]);


    // 建立可變產品的相關屬性
    $attributes = array();
    foreach ($product["formatAttributes"] as $attribute) {
        $wcAttribute = new WC_Product_Attribute();
        $wcAttribute->set_name($attribute["name"]);
        $wcAttribute->set_options($attribute["options"]);
        $wcAttribute->set_position(0);
        $wcAttribute->set_visible(true);
        $wcAttribute->set_variation(true);
        $attributes[] = $wcAttribute;
    }

    // 將產品寫入屬性
    // debug($attributes, "將產品寫入屬性");
    $wcProduct->set_attributes($attributes);
    $wcProduct->save();

    // debug($wcProduct, "Variable's wcProduct Configuration");
    // debug($product["children"], "開始loop 實例化 WC_Product_Variation");
    // handle WC_Product_Variation

    $attributeEnglishToChinese = array(
        "顏色" =>  "color",
        "大小" => "size",
    );
    foreach ($product["children"] as $variationProduct) {
        // debug($variationProduct, "Now variationProduct" . "SKU: " . $variationProduct["SKU"] . " ,Attributes: " . $variationProduct["SKU"]);
        // 解析選定的變體屬性example 顏色:red,大小:M
        $variationAttributes = array();
        $explodedVariableAttributes = explode(",", $variationProduct["VariableAttributes"]);
        foreach ($explodedVariableAttributes as $variableAttribute) {
            $exppldedAttribute = explode(":", $variableAttribute);
            $name = strtolower(urlencode($exppldedAttribute[0]));
            //$name = $attributeEnglishToChinese[$exppldedAttribute[0]];
            $option = $exppldedAttribute[1];
            $variationAttributes[$name] = $option;
        }

        // 實例化變體產品

        // debug("\$variation Configuration");
        // debug("\$variation set_sku: " . $variationProduct["SKU"]);
        // debug("\$variation set_attributes: " . print_r($variationAttributes, true));
        // debug("\$variation set_regular_price: " . $variationProduct["Price"]);
        // debug("\$variation set_sale_price: " . $variationProduct["Saleprice"]);
        // debug("\$variation set_weight: " . $variationProduct["Weight"]);
        // debug("\$variation set_manage_stock: " . "true");
        // debug("\$variation set_stock_quantity: " . $variationProduct["Stock"]);
        // debug("\$variation set_low_stock_amount: " . $variationProduct["LowStock"]);

        $variation = new WC_Product_Variation();
        $variation->set_parent_id($wcProduct->get_id());
        $variation->set_attributes($variationAttributes);
        $variation->set_regular_price($variationProduct["Price"]);
        if (!empty($variationProduct["Saleprice"])) $variation->set_sale_price($variationProduct["Saleprice"]);
        $variation->set_weight($variationProduct["Weight"]);
        // 庫存管理
        $variation->set_manage_stock(true);
        $variation->set_stock_quantity($variationProduct["Stock"]);
        $variation->set_low_stock_amount($variationProduct["LowStock"]); //低庫存臨界值
        // $variation->set_image_id(""); 圖片處理尚未知道
        // 檢查sku是否重複
        $sku = (false) ? Uuid::uuid4() : $variationProduct["SKU"];
        // $variation->set_sku($sku);
        // $variation->save();
        if (!checkVariationSkuExist($wcProduct, $sku, $variationAttributes)) {
            try {
                $variation->set_sku($sku);
                $variation->save();
                // debug("SKU: " . $variationProduct["SKU"] . "attributes: " . $variationProduct["SKU"] . "variation 寫入成功");
            } catch (WC_Data_Exception $e) {
                debug("SKU: " . $variationProduct["SKU"] . "attributes: " . $variationProduct["SKU"] . "variation 寫入失敗, errorMessage:" . $e->getErrorCode());
                // $logger->debug('debug :' .  var_dump($variation, true) . $sku);
                // echo "error_code: " . $e->getErrorCode()  . "\n";
                // echo "error_data: " . "\n";
                // print_r($e->getErrorData());
            }
        }
    }
}

function checkVariationSkuExist($prodcutVariable, $sku, $variationAttributes) {
    $isExist = false;
    if ($prodcutVariable instanceof WC_Product_Variable) {
        $variations = $prodcutVariable->get_available_variations();
        /*
      Array (
        [0] => Array (
          [attributes] => Array (
            [attribute_pa_color] => coral
            [attribute_magical] => Yes
          )
      
          [dimensions] => Array (
            [length] => 
            [width] => 
            [height] => 
          )
          [display_price] => 40
          [display_regular_price] => 40
          [price_html] => £40,00
          [sku] => 
          [weight] => 
          [...]
        )
      
        [1] => Array ( [...] )
        [2] => Array ( [...] )
        [...]
      )
      */
        foreach ($variations as $variation) {
            if (
                $variation['sku'] == $sku ||
                !array_diff_assoc($variationAttributes, $variation["attributes"])
            ) {
                $isExist = true;
                echo "變體id: " . $variation['variation_id'] . "出現重複的sku問題";
                break;
            }
        }
    } else {
        echo  "\$prodcutVariable is not an instance of WC_Product_Variable";
    }

    return $isExist;
}

function createSimpleProduct($product) {
    $wcProduct = new WC_Product_Simple();

    $wcProduct->set_name($product["ProductTitle"]);

    $wcProduct->set_regular_price($product["Price"]);
    if (!empty($product["Saleprice"])) $wcProduct->set_sale_price($product["Saleprice"]);

    // you can also add a full product description
    $wcProduct->set_description($product["Description"]);
    // $wcProduct->set_image_id("");

    // 庫存設定
    $wcProduct->set_sku($product["SKU"]); // set 庫存編號

    // 單存顯示有沒有存貨如果要更深入的產品層面庫存管理下面一行可以省略
    // $product->set_stock_status('instock'); // 'instock', 'outofstock' or 'onbackorder'

    // 產品層面的庫存管理
    $wcProduct->set_manage_stock(true);
    $wcProduct->set_stock_quantity($product["Stock"]);
    $wcProduct->set_low_stock_amount($product["LowStock"]); //低庫存臨界值


    // 產品此寸和運送
    $wcProduct->set_weight($product["Weight"]);
    // $product->set_length(50);
    // $product->set_width(50);
    // $product->set_height(30);

    $wcProduct->save();
}

function delProduct() {
    $product = new WC_Product_Query();
    foreach ($product->get_products() as $product) {
        $id = $product->get_id();
        $product->delete($id, true);
    }
}
