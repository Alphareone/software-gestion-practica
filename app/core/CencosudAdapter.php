<?php

class CencosudAdapter implements MarketplaceInterface {
    protected $db;
    protected $productsModel;
    protected $ordersModel;
    protected $apiService;

    public function __construct($db = null) {
        $this->db = $db ?: new Database();
        $this->productsModel = new CencosudProductsCacheModel($this->db);
        $this->ordersModel = new CencosudOrdersCacheModel($this->db);
        $this->apiService = new CencosudApiService($this->db);
    }

    public function getProducts($connectionId, $page, $limit, $search, $status) {
        return $this->productsModel->getProducts($connectionId, $page, $limit, $search, $status);
    }

    public function saveProduct($connectionId, $data) {
        return $this->apiService->saveProduct($connectionId, $data);
    }

    public function deleteProduct($connectionId, $productId) {
        return $this->apiService->deleteProduct($connectionId, $productId);
    }

    public function updateStock($connectionId, $productId, $qty) {
        return $this->apiService->updateStock($connectionId, $productId, $qty);
    }

    public function syncProducts($connectionId) {
        return $this->apiService->syncAllProducts($connectionId);
    }

    public function getOrders($connectionId, $page, $limit, $search) {
        return $this->ordersModel->getOrders($connectionId, $page, $limit, $search);
    }

    public function syncOrders($connectionId) {
        return $this->apiService->syncOrders($connectionId);
    }
}
