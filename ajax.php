<?php

// 首頁ajax
// wp_ajax_nopriv_YOUR_ACTION 使用者未登錄執行
// wp_ajax_YOUR_ACTION 使用者登入執行

require __DIR__ . "/vendor/autoload.php";
require __DIR__ . "/prodcut.php";

use PhpOffice\PhpSpreadsheet\IOFactory;

add_action("wp_ajax_uploadProductExcel", "parseProdcutExcel");

function parseProdcutExcel() {

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

    // die(print_r($products, true));
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
