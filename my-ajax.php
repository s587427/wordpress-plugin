<?php

// 首頁ajax
// wp_ajax_nopriv_YOUR_ACTION 使用者未登錄執行
// wp_ajax_YOUR_ACTION 使用者登入執行


require __DIR__ . "/vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\IOFactory;
use Ramsey\Uuid\Uuid;


add_action("wp_ajax_uploadProductExcel", "parseProdcutExcel");

function parseProdcutExcel() {

  function connectParentWithChildren($products) {
    $sortPoducts = array();
    foreach ($products as $product) {
      // 如果父親sku不存在
      if (empty($product["ParentSKU"])) {
        $sku = $product["SKU"];
        $sortPoducts[$sku] = $product;
      } else {
        $parentSku = $product["ParentSKU"];
        // 建立父子關係
        $sortPoducts[$parentSku]["children"][] = $product;
        // 蒐集attribute
        $sortPoducts[$parentSku]["attribute"][] = $product["VariableAttributes"];
      }
    }
    return $sortPoducts;
  }

  function formatAttributes($products) {
    foreach ($products as $sku => $product) {
      $formatAttributes = array();

      if (isset($product["attribute"])) {
        // 開始將arrtibute整理成woocommerce好處理的格式
        foreach ($product["attribute"] as $attribute) {
          $combinations = explode(",",  $attribute);
          foreach ($combinations as $combination) {

            $combinationDetail = $combinations = explode(":",  $combination);

            $name = $combinationDetail[0];
            $option = $combinationDetail[1];

            if (!isset($formatAttributes[$name])) {
              $formatAttributes[$name] = array(
                "name" => $name,
                "options" => array($option)
              );
            } else {
              if (!in_array($option, $formatAttributes[$name]["options"])) {
                array_push($formatAttributes[$name]["options"], $option);
              }
            }
          }
        }
        // 加入新formatAttributes key指向整理後的attribute
        $products[$sku]["formatAttributes"] = $formatAttributes;
      }
    }

    return $products;
  }

  if (isset($_FILES["myExcel"])) {
    $reader = IOFactory::createReader("Xlsx");
    $spreadsheet = $reader->load($_FILES['myExcel']['tmp_name']);

    $worksheet = $spreadsheet->getActiveSheet();

    // 取得最大行和最大欄
    $maxRow = $worksheet->getHighestRow();
    $maxCol = $worksheet->getHighestColumn();

    $products = getProductInExcel($worksheet, $maxRow, $maxCol);
    $products = connectParentWithChildren($products);
    $products = formatAttributes($products);


    // echo json_encode($products);
    // die();

    // 先刪除所有商品以避免重複建立sku
    delProduct();

    $result = createProduct($products);

    //var_dump($products);
    echo json_encode($result);
  } else {
    echo "upload error";
  }

  die();
}

// 1. 整理資料
// 2. 判斷產品是甚麼類型
// 3. 進入相對應的接口處裡產品

function createProduct($products) {

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
    $wcProduct->set_attributes($attributes);
    $wcProduct->save();


    debug($wcProduct, "Variable's wcProduct Configuration");

    // $logger->debug('debug :' .  print_r($variations));
    // die();

    debug($product["children"], "開始loop 實例化 WC_Product_Variation");
    // handle WC_Product_Variation
    foreach ($product["children"] as $variationProduct) {
      debug($variationProduct, "Now variationProduct" . "SKU: " . $variationProduct["SKU"] . " ,Attributes: " . $variationProduct["SKU"]);
      // 解析選定的變體屬性example 顏色:red,大小:M
      $variationAttributes = array();
      $explodedVariableAttributes = explode(",", $variationProduct["VariableAttributes"]);
      foreach ($explodedVariableAttributes as $variableAttribute) {
        $exppldedAttribute = explode(":", $variableAttribute);
        $name = $exppldedAttribute[0];
        $option = $exppldedAttribute[1];
        $variationAttributes[$name] = $option;
      }

      // 實例化變體產品

      debug("\$variation Configuration");
      debug("\$variation set_sku: " . $variationProduct["SKU"]);
      debug("\$variation set_attributes: " . print_r($variationAttributes, true));
      debug("\$variation set_regular_price: " . $variationProduct["Price"]);
      debug("\$variation set_sale_price: " . $variationProduct["Saleprice"]);
      debug("\$variation set_weight: " . $variationProduct["Weight"]);
      debug("\$variation set_manage_stock: " . "true");
      debug("\$variation set_stock_quantity: " . $variationProduct["Stock"]);
      debug("\$variation set_low_stock_amount: " . $variationProduct["LowStock"]);

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
      $variation->set_sku($sku);
      $variation->save();
      // if (!checkVariationSkuExist($wcProduct, $sku, $variationAttributes)) {
      //   try {
      //     $variation->set_sku($sku);
      //     $variation->save();
      //     debug("SKU: " . $variationProduct["SKU"] . "attributes: " . $variationProduct["SKU"] . "variation 寫入成功");
      //   } catch (WC_Data_Exception $e) {
      //     debug("SKU: " . $variationProduct["SKU"] . "attributes: " . $variationProduct["SKU"] . "variation 寫入失敗, errorMessage:" . $e->getErrorCode());
      //     // $logger->debug('debug :' .  var_dump($variation, true) . $sku);
      //     // echo "error_code: " . $e->getErrorCode()  . "\n";
      //     // echo "error_data: " . "\n";
      //     // print_r($e->getErrorData());
      //   }
      // }
      echo "\n\n";
    }
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

  if (!is_array($products)) echo "CreateProudct Error";
  foreach ($products as $product) {
    if ($product["ProductType"] == "Variable") {
      createVariableProduct($product);
    } elseif ($product["ProductType"] == "Simple") {
      // createSimpleProduct($product);
    }
  }

  return "CreateProduct Success!";
}

function updateProduct($id) {
  // update_post_meta使用此函數設定商品的價格、上架狀態和庫存...操作
  // 未完成
  if ($id) {
    //設定商品價格
    update_post_meta($id, '_price', '10');
    update_post_meta($id, '_regular_price', '10');
    //將商品上架
    update_post_meta($id, '_visibility', 'visible');
    //設定庫存
    update_post_meta($id, '_stock_status', 'instock');
  }
}

function getProductInExcel($worksheet, $maxRow, $maxCol) {
  $products = array();
  $headers = array();

  // ex $headers = array("A" => "colName1", "B" => "colName2")
  for ($col = "A"; $col <= $maxCol; $col++) {
    $cell = $worksheet->getCell($col . "1");
    $headers[$cell->getColumn()] = $cell->getValue();
  }

  for ($row = 2; $row <= $maxRow; $row++) {
    $prodcutTmp = array();
    for ($col = 'A'; $col <= $maxCol; $col++) {
      $cell = $worksheet->getCell($col . $row);
      $value = $cell->getValue();
      $column = $cell->getColumn();
      $key = preg_replace("/\s+/", "", $headers[$column]);
      $prodcutTmp[$key] = $value; //刪除空白
      // switch ($column) {
      //   case "A":
      //     $prodcutTmp["productTitle"] = $value;
      //     break;
      //   case "B":
      //     $prodcutTmp["productType"] = $value;
      //     break;
      //   case "C":
      //     $prodcutTmp["sku"] = $value;
      //     break;
      //   case "D":
      //     $prodcutTmp["parentSku"] = $value;
      //     break;
      //   case "E":
      //     $prodcutTmp["price"] = $value;
      //     break;
      //   case "F":
      //     $prodcutTmp["salePrice"] = $value;
      //     break;
      //   case "D":
      //     $prodcutTmp["weight"] = $value;
      //     break;
      //   case "D":
      //     $prodcutTmp["variableAttributes"] = $value;
      //     break;
      //   case "D":
      //     $prodcutTmp["description"] = $value;
      //     break;
      //   case "D":
      //     $prodcutTmp["stock"] = $value;
      //     break;
      //   default:
      //     break;
      // }
      // echo "儲存格資料：{$value}，所在欄位：{$column}{$row}\n";
    }
    $products[] =  $prodcutTmp;
  }
  // foreach的迭代
  // foreach($worksheet->getRowIterator() as $row){
  //   foreach($row->getCellIterator() as $cell){
  //   }
  // }
  return $products;
}


function delProduct() {
  $product = new WC_Product_Query();
  foreach ($product->get_products() as $product) {
    $id = $product->get_id();
    $product->delete($id, true);
  }

  global $wpdb;

  // 找到所有的產品ID
  $product_ids = $wpdb->get_col("
    SELECT ID
    FROM {$wpdb->prefix}posts
    WHERE post_type = 'product'
    AND post_status = 'publish'
");

  // 清除所有商品的緩存
  foreach ($product_ids as $product_id) {
    wc_delete_product_transients($product_id);
  }
}
