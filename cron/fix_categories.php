<?php

try {
    require('../includes/configure.php');
    ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
    chdir(DIR_FS_CATALOG);
    require_once('../includes/application_top.php');
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

global $db;
$categories_query = "SELECT c.categories_id, c.parent_id, c.categories_status, p.products_id, p.products_status 
                     FROM " . TABLE_CATEGORIES . " c
                     LEFT JOIN " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c ON c.categories_id = p2c.categories_id
                     LEFT JOIN " . TABLE_PRODUCTS . " p ON p2c.products_id = p.products_id";

try {
    $categories_result = $db->Execute($categories_query);
    if (!$categories_result) {
        throw new Exception("Error executing query: " . $db->ErrorMsg());
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

$categories = [];
$active_products_in_category = [];

while (!$categories_result->EOF) {
    $category_id = $categories_result->fields['categories_id'];
    $parent_id = $categories_result->fields['parent_id'];
    $category_status = $categories_result->fields['categories_status'];
    $product_id = $categories_result->fields['products_id'];
    $product_status = $categories_result->fields['products_status'];

    $categories[$category_id] = [
        'parent_id' => $parent_id,
        'category_status' => $category_status,
        'active_products' => [],
        'sub_categories' => [],
    ];

    if ($product_id && $product_status == 1) {
        $categories[$category_id]['active_products'][] = $product_id;
    }

    if ($parent_id > 0) {
        $categories[$parent_id]['sub_categories'][] = $category_id;
    }

    $categories_result->MoveNext();
}

foreach ($categories as $category_id => $category) {
    $has_active_products = !empty($category['active_products']);
    $has_active_sub_categories = false;

    foreach ($category['sub_categories'] as $sub_category_id) {
        if (!empty($categories[$sub_category_id]['active_products'])) {
            $has_active_sub_categories = true;
            break;
        }
    }

    if (!$has_active_products && !$has_active_sub_categories) {
        try {
            $db->Execute("UPDATE " . TABLE_CATEGORIES . " 
                          SET categories_status = 0 
                          WHERE categories_id = ?", [$category_id]);
            if (!$db->Affected_Rows()) {
                throw new Exception("Error disabling category: " . $db->ErrorMsg());
            }

            echo "Category ID $category_id disabled.\n";
        } catch (Exception $e) {
            echo "Error disabling category $category_id: " . $e->getMessage();
        }
    }
}

echo "Category disabling process completed.\n";
?>