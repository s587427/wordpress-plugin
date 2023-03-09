<?php

/**
 * Plugin Name: excelUpload
 * Description: Excel新增Woocommerce商品
 * Version: 0.1.0
 **/

require_once __DIR__ . "/utility.php";
require_once __DIR__ . "/ajax.php";

// 選單
add_action("admin_menu", "uploadExcel");
function uploadExcel() {
  add_menu_page("選單標題", "Excel", "manage_options", "選單ID", getUploadExcelPages());
}

function getUploadExcelPages($ver = "v2") {
  if ($ver == "v1") {
    return function () {
      echo "<div class='container' style='margin-top:1rem'>
        <label for='upload'>Excel上傳:</label>
          <input id='upload' type='file'/>ph
          <button type='button' id='btn-product'>測試新增產品</button>
      </div>";
      loadScriptAndStylesheet();
    };
  } elseif ($ver == "v2") {
    return function () {
      echo "<div class='container' style='margin-top:1rem'>
          <form class='dropzone'></form>
      </div>";
      loadScriptAndStylesheet();
      // getSkus();
      // delProducts(getProducts());
      // test();
    };
  }
}




function updateVariation($variationId) {
  $variation = new WC_Product_Variation($variationId);
  $variation->set_attributes(array("顏色" => "blue", "大小" => "S"));
  $variation->save();
}


function test() {
  $product = new WC_Product_Query();

  foreach ($product->get_products() as $product) {
    if ($product instanceof WC_Product_Variable) {
      $variations = $product->get_available_variations();
      foreach ($variations as $variation) {
        $variation_product = wc_get_product($variation['variation_id']);
        echo "商品id: " . $variation_product->get_id() . " sku: " . $variation_product->get_sku() . "<br/>";
        // echo $variation_product->readArr(get_class_methods($variation_product));
      }
    }
  }
}


// function delProducts($products) {
//   foreach ($products as $product) {
//     $id = $product->get_id();
//     $product->delete($id);
//   }
// }

// function getProducts() {
//   $product = new WC_Product_Query();
//   // readArr($product->get_products());
//   return $product->get_products();
// }


function getSkus() {
  $options = array(
    'post_type' => 'product_variation',
    'posts_per_page' => -1,
  );
  $variations = new WC_Product_Query($options);

  $products = $variations->get_products();

  readArr($variations);

  foreach ($variations->get_products() as $variation) {
    $sku = $variation->get_sku();
    if ($sku) {
      // 處理 SKU
      // ...
      echo $sku . '<br>';
    }
  }
}


function loadScriptAndStylesheet() {
  // 載入dropzine相關依賴
  wp_enqueue_script("dropzone", "https://unpkg.com/dropzone@6.0.0-beta.1/dist/dropzone-min.js", array("jquery"));
  wp_enqueue_style("dropzone", "https://unpkg.com/dropzone@6.0.0-beta.1/dist/dropzone.css");
  // 自己寫的依賴
  wp_enqueue_style("mycss", plugin_dir_url(__FILE__) . "css/index.css");
  // wp_enqueue_script("xlsx", "https://cdn.jsdelivr.net/npm/xlsx@0.17.0/dist/xlsx.full.min.js", array( 'jquery' ), '0.17.0', false);
  wp_enqueue_script('index', plugin_dir_url(__FILE__) . 'js/index.js', array('jquery'), '1.0', true);
  // 等同於給定了window.SERVER_DATA = {SERVER_URL: admim-ajax的url}, !!重點是放在全域之中
  wp_localize_script(
    "index",
    "SERVER_DATA",
    array(
      "SERVER_URL" => admin_url("admin-ajax.php")
    )
  );


  // wp_enqueue_script("test", plugin_dir_url(__FILE__). "js/testLoadSetp.js");
}



















function register_js_files() {
  wp_enqueue_script("home", plugin_dir_url(__FILE__) . "js/home.js", array("jquery"), "1.0", true);
  // wp_enqueue_style()
  wp_add_inline_script(
    "home",
    "const PHPVARS = " . json_encode(
      array(
        "ajaxurl" => admin_url("admin-ajax.php"),
        "another_var" => get_bloginfo("name")
      )
    ),
    "before"
  );
  // wp_add_inline_script 它允许你在一个已经注册的脚本的内部添加自定义的 JavaScript 代码, 它允许你向已经存在的脚本添加一些必要的功能，而不需要修改这些脚本本身
}

// 在前端頁面加載相關css和js
add_action("wp_enqueue_scripts", "register_js_files");



// 管理員區域js注入
// add_action("admin_enqueue_scripts", function(){
//   wp_enqueue_script("adminTest", plugin_dir_url(__FILE__)."js/admin.js");
// });






// register_activation_hook("")

// active hook
// register_activation_hook("插件的文件名，包括路徑", "掛鉤到動作的函數")

// cancel hook
// register_deactivation_hook()

//
// register_uninstall_hook()



// /plugin-name
//      plugin-name.php
//      uninstall.php
//      /languages
//      /includes
//      /admin
//           /js
//           /css
//           /images
//      /public
//           /js
//           /css
//           /images