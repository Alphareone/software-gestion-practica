<?php

class ActivityService {
    private $activity;

    public function __construct($db = null) {
        $this->activity = new ActivityLogModel($db);
    }

    public function log($userId, $action, $description) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return $this->activity->insert($userId, $action, $description, $ip);
    }

    public function getLogs($page = 1, $search = '', $actionFilter = '', $userId = null) {
        return $this->activity->findAll($page, 50, $search, $actionFilter, $userId);
    }

    public function getDistinctActions() {
        return $this->activity->getDistinctActions();
    }

    public function getDistinctUsers() {
        return $this->activity->getDistinctUsers();
    }

    public function purgeOld($months = 6) {
        $this->activity->purgeOld($months);
    }

    public function getTodayCount() {
        return $this->activity->getTodayCount();
    }

    public function getDateRange() {
        return $this->activity->getDateRange();
    }

    public function getTopAction() {
        return $this->activity->getTopAction();
    }
}
