# Laravel-Storedproc

**Laravel-Storedproc** is a fluent wrapper for calling stored procedures in Laravel — because not every query belongs in Eloquent.

In many real-world applications, especially enterprise systems, stored procedures are a key part of the backend. Laravel doesn’t natively support them in an elegant way, so most developers are stuck writing raw `DB::select()` queries over and over.

This package simplifies that.

## ✨ Features

- ✅ Fluent, chainable syntax for stored procedure calls
- 🧠 Parameter binding from arrays or Laravel requests
- ♻️ Optional Laravel-managed transaction support
- 🔌 Works with MySQL and SQL Server
- 💥 Exception-safe with automatic rollback on failure

---

## 📦 Installation

You can install the package via Composer:

```bash
composer require magslabs/laravel-storedproc
```

---

## 🚀 Basic Usage

```php
use MagsLabs\LaravelStoredProc\StoredProcedure;

$result = StoredProcedure::stored_procedure('get_user_by_id')
    ->stored_procedure_params([':id'])
    ->stored_procedure_values([1])
    ->execute()
    ->stored_procedure_result();
```

Or if your stored procedure does not require parameters:

```php
$result = StoredProcedure::stored_procedure('get_all_users')
    ->execute()
    ->stored_procedure_result();
```

The result is returned as a Laravel Collection for easy chaining and manipulation.

---

## 🧩 Parameters & Values

You can pass parameters in multiple formats:

```php
// As an array
->stored_procedure_params([':id'])
->stored_procedure_values([1]);

// From a Laravel FormRequest or Request
->stored_procedure_params($request); // will automatically extract keys and format them
->stored_procedure_values([$request->id]);
```

---

## ♻️ Transaction Support

Enable Laravel-managed transactions like so:

```php
->with_transaction()
```

Laravel will automatically commit on success or roll back if the procedure throws an error.

> ⚠️ Use this **only if** your stored procedure does **not** manage its own transactions (`BEGIN`, `COMMIT`, etc.).

---

## 🧪 Example: Full Workflow

```php
use MagsLabs\LaravelStoredProc\StoredProcedure;

// Static usage with parameters and transaction
$users = StoredProcedure::stored_procedure('get_users_by_role')
    ->stored_procedure_connection('mysql') // Optional
    ->stored_procedure_params([':role'])
    ->stored_procedure_values(['admin'])
    ->with_transaction() // Optional
    ->execute()
    ->stored_procedure_result();

// Static usage without parameters and without transaction
$logs = StoredProcedure::stored_procedure('get_recent_logs')
    ->stored_procedure_connection('mysql') // Optional
    ->execute()
    ->stored_procedure_result();

// Explicitly instantiate and reuse
$storedProc = new StoredProcedure();

$users = $storedProc->stored_procedure('get_users_by_role')
    ->stored_procedure_connection('mysql') // Optional
    ->stored_procedure_params([':role'])
    ->stored_procedure_values(['admin'])
    ->with_transaction() // Optional
    ->execute()
    ->stored_procedure_result();

$logs = $storedProc->stored_procedure('get_recent_logs')
    ->stored_procedure_connection('mysql') // Optional
    ->execute()
    ->stored_procedure_result();

// Example via dependency injection in a controller
use MagsLabs\LaravelStoredProc\StoredProcedure;

class UserController extends Controller
{
    protected StoredProcedure $storedProc;

    public function __construct(StoredProcedure $storedProc)
    {
        $this->storedProc = $storedProc;
    }

    public function index()
    {
        $users = $this->storedProc->stored_procedure('get_users_by_role')
            ->stored_procedure_connection('mysql') // Optional
            ->stored_procedure_params([':role'])
            ->stored_procedure_values(['admin'])
            ->with_transaction() // Optional
            ->execute()
            ->stored_procedure_result();

        return response()->json($users);
    }

    public function logs()
    {
        $logs = $this->storedProc->stored_procedure('get_recent_logs')
            ->stored_procedure_connection('mysql') // Optional
            ->execute()
            ->stored_procedure_result();

        return response()->json($logs);
    }
}
```

This is useful when you want to inject the instance or reuse it across multiple calls.

---

## 🌐 Switching Database Connections

Need to call a stored procedure on a different connection/database?

```php
->stored_procedure_connection('your_own_connection_database_name')
```

This uses Laravel’s connection from `config/database.php`.

---

## ⚠️ Common Gotchas

- You **must** call methods in this order:

  1. `stored_procedure()` (required)
  2. `stored_procedure_params()` (optional, if your proc has parameters)
  3. `stored_procedure_values()` (required if you set params)
  4. `with_transaction()` (optional)
  5. `execute()` (required)
  6. `stored_procedure_result()` (required)

- All parameters must be bound **by position** in the `stored_procedure_values()` array.

---

## ✅ Compatibility

- Laravel 8, 9, 10, 11, 12
- MySQL, SQL Server _(Other databases are not officially supported and may not work as expected)_

<!-- ---

## 📄 License

MIT License. © [Mark Angelo Sollano / magslabs]

---

## 🙌 Credits

Created by [@masollano](https://github.com/masollano) on (https://github.com/magslabs/laravel-storedproc) -->
