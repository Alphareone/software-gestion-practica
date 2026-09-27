<?php

interface MarketplaceInterface {
    public function getProducts($connectionId, $page, $limit, $search, $status);
    public function saveProduct($connectionId, $data);
    public function deleteProduct($connectionId, $productId);
    public function updateStock($connectionId, $productId, $qty);
    public function syncProducts($connectionId);
    public function getOrders($connectionId, $page, $limit, $search);
    public function syncOrders($connectionId);
}
