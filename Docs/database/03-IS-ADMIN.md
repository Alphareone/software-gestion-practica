# Eliminación de redundancia: `is_admin`

## Diagnóstico

La columna `is_admin` en `users` era redundante: su valor **siempre** se
derivaba de `role = 'admin'`. Existían 42 referencias en 17 archivos PHP.

### Dónde se usaba

| Capa | Archivos | Propósito |
|---|---|---|
| BD | `Estructura actual.sql` | Definición de columna `tinyint(1)` |
| Modelo | `UserModel.php` | 7 SQL (SELECT/INSERT/UPDATE) + método `isAdmin()` |
| Servicio | `UserService.php` | Cálculo de `$isAdmin`, guards `isAdminUser()` / `isTargetingAdmin()` |
| Auth | `AuthService.php` | Inicialización de `$_SESSION['is_admin']` |
| Controladores | `Controller.php`, `AdminController.php`, etc. | Gate `requireAdmin()`, view data |
| Vistas | `content-index.php`, `sidebar.php`, etc. | 5 referencias en PHP, 2 en JS |

### Patrón de escritura

Siempre se escribía igual:
```php
$isAdmin = $safeRole === 'admin' ? 1 : 0;   // UserService
$_SESSION['is_admin'] = !empty($user->is_admin);   // AuthService
```

---

## Estrategia aplicada

No se eliminó `is_admin` del código — se eliminó de la BD. En su lugar,
se computa desde `role` en dos niveles:

### 1. SQL (queries SELECT)

```sql
-- Antes
SELECT is_admin, role FROM users

-- Después
SELECT role, role = 'admin' AS is_admin FROM users
```

El objeto retornado por la BD sigue teniendo la propiedad `is_admin`
con valor `1` o `0`. **Las vistas no requieren cambios.**

### 2. PHP (Session & Service)

```php
// AuthService
$_SESSION['is_admin'] = ($user->role ?? '') === 'admin';   // bool

// UserService
'is_admin' => ($user->role ?? '') === 'admin',
```

### 3. PHP (guards)

```php
// Antes
return $user && !empty($user->is_admin);

// Después (más explícito)
return $user && $user->role === 'admin';
```

---

## Archivos modificados

| Archivo | Cambios |
|---|---|
| `app/Models/UserModel.php` | `isAdmin()` usa `SELECT role`; `search()`, `getEditData()`, `getBasicInfo()` usan `role = 'admin' AS is_admin`; `createUser()`, `updateUser*()` ya no reciben/guardan `is_admin` |
| `app/Services/UserService.php` | Eliminado cálculo `$isAdmin` en `createUser()` y `updateUser()`; `isAdminUser()` y `isTargetingAdmin()` comparan `role === 'admin'` |
| `app/Services/AuthService.php` | `$_SESSION['is_admin']` derivado de `$user->role` |
| `db/Estructura actual.sql` | Columna `is_admin` eliminada del CREATE TABLE |

### BD

```sql
ALTER TABLE users DROP COLUMN is_admin;
```

---

## Impacto en vistas

**Cero.** Todas las vistas que usan `$u->is_admin`, `$data['is_admin']` o
`$_SESSION['is_admin']` siguen funcionando porque:

1. Los objetos retornados por queries incluyen `is_admin` como expresión SQL
2. `$_SESSION['is_admin']` se sigue seteando en login (derivado de role)
3. Los controladores siguen pasando `'is_admin'` en el array de view data
