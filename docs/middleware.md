# Middleware

Spatie's middleware is registered automatically by the package. No manual setup needed.

```php
// All work out of the box — wildcards included:
Route::middleware('role:admin')
Route::middleware('permission:admin.*')
Route::middleware('role_or_permission:editor|admin.*')
Route::middleware('permission:posts.*|comments.*')
```

To disable auto-registration and register manually:

```php
// config/permission-extended.php
'register_middleware' => false,
```

Then register in `bootstrap/app.php` yourself if needed.
