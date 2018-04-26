<?php

/**
 * Class ControllerModuleAvantajbizde
 * @property DB db
 */
class ControllerModuleAvantajbizde extends Controller
{

    protected $params = null;

    //<editor-fold desc="Initialize Methods">

    public function __construct($registry)
    {
        parent::__construct($registry);
        $include_path = DIR_CONFIG."avantajbizde.php";
        if(file_exists($include_path)){
            /** @noinspection PhpIncludeInspection */
            include_once $include_path;
            if (!defined("AB_ACCESS_TOKEN")) {
                $this->response(null, "error", -99, "Access token not configured");
                exit;
            }
        }
        else{
            $this->response(null, "error", -99, "Access token not configured");
            exit;
        }
        if (AB_ACCESS_TOKEN !== getallheaders()["Access-Token"]) {
            $this->response(null, "error", -1, "Access token not matched");
            exit;
        }
        if (isset($_GET['params'])) {
            try {
                $this->params = json_decode(base64_decode($_GET['params']), false);
            } catch (Exception $exception) {
                $this->params = (object)[];
            }
        }
    }

    public function index()
    {
        $action_input = isset($_GET['action']) ? trim($_GET['action']) : null;
        if ($action_input == null) {
            return $this->response();
        }

        $action_input = $action_input . "Action";

        if (method_exists($this, $action_input)) {
            try {
                return $this->$action_input();
            } catch (Exception $exception) {
                return $this->response($exception, "exception", -23, "An error occurred");
            }
        }
        return $this->response(null, "error", -2, "Method not exists");
    }

    private function response($data = null, $type = "success", $code = 1, $message = null)
    {
        @header("Content-Type: application/json");
        if ($code == -23) {
            http_response_code(500);
            /** @var Exception $data */
            $data = ["code" => $data->getCode(), "file" => $data->getFile(), "line" => $data->getLine(), "message" => $data->getMessage(), "previous" => $data->getPrevious(), "trace" => $data->getTrace()];
        }
        echo json_encode(["type" => $type, "code" => $code, "message" => $message, "data" => $data]);
        exit;
    }

    //</editor-fold>

    //<editor-fold desc="Actions">

    public function listProductsAction()
    {
        $products = $this->listProducts();
        $images = $this->listProductImages();
        $options = $this->listProductOptions();
        $descriptions = $this->getProductDescription();
        $list = [];
        foreach($products as $product){
            $product["image"] = trim(HTTP_SERVER,'/')."/".trim(PRODUCTS_IMAGE_PATH,'/')."/".$product["image"];
            $product["product_category_path"] = $this->getProductCategoryPath($product["product_id"]);
            $product["options"] = $this->getByKey("product_id",$product["product_id"],$options);
            $images_data = $this->getByKey("product_id",$product["product_id"],$images);
            $product["images"] = [];
            foreach ($images_data as $image){
                $product["images"][] = trim(HTTP_SERVER,'/')."/".trim(PRODUCTS_IMAGE_PATH,'/')."/".$image["image"];
            }
            $description_data = $this->findByKey("product_id",$product["product_id"],$descriptions);
            if($description_data != null){
                $product["description"] = $description_data["description"];
            }
            $list[] = ["product" => $product];
        }
        return $this->response($list);
    }

    public function getProductAction()
    {
        return $this->response($this->params);
    }

    public function getStockAction()
    {
        $id = $this->params->id;
        return $this->response($this->listProductOptions($id));
    }

    public function updateProductOptionAction(){
        $option_value_id = $this->db->escape($this->params->option_value_id);
        $stock = $this->db->escape($this->params->stock);
        $result = $this->updateProductOptionValue($option_value_id,$stock);
        return $this->response($result);
    }

    public function updateProductStockAction(){
        $product_id = $this->db->escape($this->params->product_id);
        $stock = $this->db->escape($this->params->stock);
        $result = $this->updateProductStock($product_id,$stock);
        return $this->response($result);
    }

    //</editor-fold>

    //<editor-fold desc="Database Methods">

    private function listProductOptions($product_id = null)
    {
        $product_id = $this->db->escape($product_id);
        $query = "SELECT oc_product.product_id as product_id,
                  oc_product.model as product_model,
                  oc_product.ean as product_ean,
                  oc_product.sku as product_sku,
                  oc_product.upc as product_upc,
                  oc_product.jan as product_jan,
                  oc_product.isbn as product_isbn,
                  oc_product.mpn as product_mpn,
                  oc_product.quantity as product_quantity,
                  oc_product.price as product_price,
                  oc_product_option_value.product_option_value_id as product_option_value_id,
                  oc_product_option_value.quantity as product_option_value_quantity,
                  oc_product_option_value.subtract as product_option_value_substract,
                  oc_product_option_value.price as product_option_value_price,
                  oc_option_value_description.name as option_value_name,
                  oc_option_description.name as option_name 
                  FROM oc_product_option_value 
                  INNER JOIN oc_product 
                  ON oc_product.product_id =  oc_product_option_value.product_id 
                  INNER JOIN oc_option_value_description 
                  ON oc_option_value_description.option_value_id =  oc_product_option_value.option_value_id 
                  INNER JOIN oc_option_description 
                  ON oc_option_description.option_id =  oc_option_value_description.option_id";
        if($product_id != null){
            $query .= " WHERE oc_product.product_id = $product_id";
        }
        $query = str_replace("oc_", DB_PREFIX, $query);
        return $this->db->query($query)->rows;
    }

    private function updateProductOptionValue($id,$stock){
        $id = $this->db->escape($id);
        $stock = $this->db->escape($stock);
        $query = "UPDATE oc_product_option_value 
                  SET 
                  quantity = '$stock' 
                  WHERE oc_product_option_value.product_option_value_id = '$id'";
        $query = str_replace("oc_",DB_PREFIX,$query);
        return $this->db->query($query);
    }

    private function updateProductStock($id,$stock){
        $id = $this->db->escape($id);
        $stock = $this->db->escape($stock);
        $query = "UPDATE oc_product SET quantity = '$stock' WHERE product_id = '$id'";
        $query = str_replace("oc_",DB_PREFIX,$query);
        return $this->db->query($query);
    }

    private function listProductImages($product_id = null){
        $product_id = $this->db->escape($product_id);
        $query = "SELECT 
                  oc_product.product_id, 
                  oc_product.model, 
                  oc_product_image.image 
                  FROM oc_product_image 
                  INNER JOIN oc_product ON oc_product.product_id = oc_product_image.product_id";
        if($product_id != null){
            $query .= " WHERE oc_product.product_id = $product_id";
        }
        $query = str_replace("_oc",DB_PREFIX,$query);
        return $this->db->query($query)->rows;
    }

    private function getProductDescription($product_id = null){
        $product_id = $this->db->escape($product_id);
        $query = "SELECT
            oc_product.product_id,
            oc_product.model,
            oc_product_description.description
            FROM oc_product_description
            INNER JOIN oc_product ON oc_product.product_id = oc_product_description.product_id";
        if($product_id != null){
            $query .= " WHERE oc_product.product_id = $product_id";
        }
        $query = str_replace("oc_",DB_PREFIX,$query);
        return $this->db->query($query)->rows;
    }

    private function listProducts(){
        $query = "SELECT 
                  oc_product.*,
                  oc_category_description.category_id as product_category_id,
                  oc_category_description.name as product_category_name
                  FROM oc_product 
                  INNER JOIN oc_product_to_category 
                  ON oc_product.product_id = oc_product_to_category.product_id
                  INNER JOIN oc_category_description 
                  ON oc_product_to_category.category_id = oc_category_description.category_id";
        $query = str_replace("oc_",DB_PREFIX,$query);
        return $this->db->query($query)->rows;
    }

    private function getProductCategoryPath($product_id, $category_id = null){
        $result = [];
        if($category_id != null){
            $category_id = $this->db->escape($category_id);
            $query = "SELECT oc_category.category_id, oc_category.parent_id, oc_category_description.name
                      FROM oc_category
                      INNER JOIN oc_category_description
                      ON oc_category_description.category_id = oc_category.category_id
                      WHERE oc_category.category_id = $category_id";
            $query = str_replace("oc_",DB_PREFIX,$query);
            $category = $this->db->query($query)->row;
            $result[] = $category["name"];
            if($category["parent_id"] != 0){
                $result[] = $this->getProductCategoryPath($product_id,$category["parent_id"]);
            }
        }
        else{
            $product_id = $this->db->escape($product_id);
            $query = "SELECT oc_category.category_id, oc_category.parent_id, oc_category_description.name
                      FROM oc_product_to_category
                      INNER JOIN oc_category_description
                      ON oc_category_description.category_id = oc_product_to_category.category_id
                      INNER JOIN oc_category ON oc_category.category_id = oc_product_to_category.category_id
                      WHERE product_id = $product_id";
            $query = str_replace("oc_",DB_PREFIX,$query);
            $category = $this->db->query($query)->row;
            $result[] = $category["name"];
            if($category["parent_id"] != 0){
                $result[] = $this->getProductCategoryPath($product_id,$category["parent_id"]);
            }
        }
        return implode(" / ",array_reverse($result));
    }

    //</editor-fold>

    //<editor-fold desc="Utilities">

    private function getByKey($search_key, $search_value, $array){
        $result = [];
        foreach($array as $key => $value){
            if($value[$search_key] == $search_value) $result[] = $array[$key];
        }
        return $result;
    }

    private function findByKey($search_key, $search_value, $array){
        return isset($this->getByKey($search_key, $search_value, $array)[0]) ? $this->getByKey($search_key, $search_value, $array)[0] : null;
    }

    //</editor-fold>

}