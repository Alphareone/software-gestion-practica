<?php

class UserService {
    private $user;

    public function __construct($db = null) {
        $this->user = new UserModel($db);
    }

    public function searchUsers($search = '', $roleFilter = '', $statusFilter = '') {
        return $this->user->search($search, $roleFilter, $statusFilter);
    }

    public function getUserForEdit($id) {
        $user = $this->user->getEditData($id);
        if (!$user) return null;

        $lastActivityAt = $user->last_activity_at ?? null;
        $isOnline = $lastActivityAt ? (time() - strtotime($lastActivityAt . ' UTC')) < USER_ONLINE_THRESHOLD : false;

        return [
            'user' => $user,
            'stats' => [
                'is_online' => $isOnline,
                'last_login_at' => $user->last_login_at ?? null,
                'last_activity_at' => $lastActivityAt,
                'created_at' => $user->created_at,
                'role' => $user->role ?? 'user',
                'is_admin' => ($user->role ?? '') === 'admin',
                'twofa_enabled' => (bool)($user->twofa_enabled ?? false),
            ],
        ];
    }

    public function getValidRoles() {
        return ['owner', 'admin', 'logistics', 'sales', 'products', 'user'];
    }

    public function sanitizeRole($role) {
        return in_array($role, $this->getValidRoles()) ? $role : 'user';
    }

    public function getValidStatuses() {
        return ['active', 'inactive'];
    }

    public function sanitizeStatus($status) {
        return in_array($status, $this->getValidStatuses()) ? $status : 'active';
    }

    public function usernameOrEmailExists($username, $email) {
        return (bool) $this->user->findByUsernameOrEmail($username, $email);
    }

    public function emailExistsExcept($email, $exceptId) {
        return (bool) $this->user->findByEmailExcept($email, $exceptId);
    }

    public function isAdminUser($id) {
        $user = $this->user->findById($id);
        return $user && $user->role === 'admin';
    }

    public function isTargetingAdmin($id) {
        $user = $this->user->getBasicInfo($id);
        return $user && $user->role === 'admin';
    }

    public function createUser($username, $email, $password, $role, $firstName = '', $lastName = '') {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $safeRole = $this->sanitizeRole($role);
        return $this->user->createUser($username, $email, $hashed, $safeRole, $firstName, $lastName);
    }

    public function updateUser($id, $email, $role, $status, $password = null, $firstName = '', $lastName = '') {
        $safeRole = $this->sanitizeRole($role);
        $safeStatus = $this->sanitizeStatus($status);

        if ($password) {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            return $this->user->updateUserWithPassword($id, $email, $safeRole, $safeStatus, $hashed, $firstName, $lastName);
        }
        return $this->user->updateUserWithoutPassword($id, $email, $safeRole, $safeStatus, $firstName, $lastName);
    }

    public function deactivateUser($id) {
        $this->user->deactivateSession($id);
        $this->user->updateStatus($id, 'inactive');
    }

    public function activateUser($id) {
        $this->user->updateStatus($id, 'active');
    }

    public function toggleStatus($id) {
        $user = $this->user->getBasicInfo($id);
        if (!$user) return null;

        if ($user->status === 'active') {
            $this->deactivateUser($id);
            return 'inactive';
        }
        $this->activateUser($id);
        return 'active';
    }

    public function deactivateWithSessionInvalidation($id) {
        $this->user->deactivateSession($id);
        $this->user->updateStatus($id, 'inactive');
    }

    public function getUserStatus($id) {
        return $this->user->getBasicInfo($id);
    }

    public function deleteUser($id) {
        return $this->user->delete($id);
    }

    public function countAdmins() {
        return $this->user->countByRole('admin');
    }
}
