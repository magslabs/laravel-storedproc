# Laravel-Storedproc

**Laravel-Storedproc** is a fluent wrapper for calling stored procedures in Laravel — because not every query belongs in Eloquent.

In many real-world applications, especially enterprise systems, stored procedures are a key part of the backend. Laravel doesn’t natively support them in an elegant way, so most developers are stuck writing raw `DB::select()` queries over and over.

This package simplifies that.

## Features

- Fluent, chainable syntax for stored procedure calls
- Parameter binding from arrays or Laravel requests
- Optional Laravel-managed transaction support
- Works with MySQL and SQL Server
- Exception-safe with automatic rollback on failure
- **NEW:** OUTPUT parameter support for SQL Server and MySQL stored procedures
- **NEW:** Built-in pagination macro for Laravel Collections
- Enhanced logging with dedicated log channel
- Smart result handling (datasets + OUTPUT parameters)

---

## Installation

You can install the package via Composer:

```bash
composer require magslabs/laravel-storedproc
```

The package will automatically register the `StoredProcedureServiceProvider` (binding), `PaginationServiceProvider` (Collection `paginate` macro), and the `StoredProcedure` facade alias.

---

## Basic Usage

You can use the **Facade** (when installed in a Laravel app) or the **class** directly:

```php
// Using the Facade (alias registered automatically)
use StoredProcedure;

$result = StoredProcedure::stored_procedure('get_user_by_id')
    ->stored_procedure_params([':id'])
    ->stored_procedure_values([1])
    ->execute()
    ->stored_procedure_result();
```

Or use the class directly:

```php
use MagsLabs\LaravelStoredProc\StoredProcedure;

$result = StoredProcedure::stored_procedure('get_user_by_id')
    ->stored_procedure_params([':id'])
    ->stored_procedure_values([1])
    ->execute()
    ->stored_procedure_result();
```

Stored procedure **without parameters**:

```php
$result = StoredProcedure::stored_procedure('get_all_users')
    ->execute()
    ->stored_procedure_result();
```

### With Pagination

```php
// Get paginated results
$users = StoredProcedure::stored_procedure('get_all_users')
    ->execute()
    ->stored_procedure_result()
    ->paginate(15); // 15 items per page
```

### With OUTPUT Parameters (SQL Server & MySQL)

**SQL Server Example:**

```php
// SQL Server stored procedure with OUTPUT parameters
$result = StoredProcedure::stored_procedure('sp_get_user_stats')
    ->stored_procedure_params([
        ':user_id',
        '@total_count OUTPUT'
    ])
    ->stored_procedure_values([123]) // Only input values
    ->stored_procedure_output_params(['@total_count' => 'INT'])
    ->execute()
    ->stored_procedure_result();

// Access both dataset and OUTPUT parameters
$users = $result->result;        // Laravel Collection
$totalCount = $result->output;   // Scalar value
```

**MySQL Example:**

```php
// MySQL stored procedure with OUT parameters
$result = StoredProcedure::stored_procedure('get_user_stats')
    ->stored_procedure_params([
        ':user_id',
        '@total_count'
    ])
    ->stored_procedure_values([123]) // Only input values
    ->stored_procedure_output_params(['@total_count' => 'INT'])
    ->execute()
    ->stored_procedure_result();

// Access both dataset and OUT parameters
$users = $result->result;        // Laravel Collection
$totalCount = $result->output;   // Scalar value
```

The result is returned as a Laravel Collection for easy chaining and manipulation, or as an object with `result` and `output` properties when OUTPUT parameters are used.

---

## Parameters & Values

You can pass parameters in multiple formats:

```php
// As an array
->stored_procedure_params([':id'])
->stored_procedure_values([1]);

// From a Laravel Request or FormRequest
->stored_procedure_params($request); // extracts keys and formats as placeholders
->stored_procedure_values([$request->id]);
```

---

## Transaction Support

Enable Laravel-managed transactions like so:

```php
->with_transaction()
```

Laravel will automatically commit on success or roll back if the procedure throws an error.

> **Warning:** Use this **only if** your stored procedure does **not** manage its own transactions (`BEGIN`, `COMMIT`, etc.).

---

## OUTPUT Parameters (SQL Server & MySQL)

For stored procedures that return OUTPUT/OUT parameters, you can capture them using the `stored_procedure_output_params()` method:

### SQL Server OUTPUT Parameters

```php
// Define OUTPUT parameters in stored_procedure_params with OUTPUT keyword
$result = StoredProcedure::stored_procedure('sp_get_user_stats')
    ->stored_procedure_params([
        ':user_id',
        '@total_users OUTPUT',
        '@active_users OUTPUT',
        '@message OUTPUT'
    ])
    ->stored_procedure_values([123]) // Only input values, OUTPUT params are handled automatically
    ->stored_procedure_output_params([
        '@total_users' => 'INT',
        '@active_users' => 'INT',
        '@message' => 'VARCHAR(255)'
    ])
    ->execute()
    ->stored_procedure_result();

// Access the results
echo $result->output->total_users;  // OUTPUT parameter value
echo $result->output->active_users; // OUTPUT parameter value
echo $result->output->message;      // OUTPUT parameter value
echo $result->result;               // Regular dataset (if any)
```

### MySQL OUT Parameters

```php
// Define OUT parameters in stored_procedure_params (clean syntax)
$result = StoredProcedure::stored_procedure('get_user_stats')
    ->stored_procedure_params([
        ':user_id',
        '@total_users',
        '@active_users',
        '@message'
    ])
    ->stored_procedure_values([123]) // Only input values, OUT params are handled automatically
    ->stored_procedure_output_params([
        '@total_users' => 'INT',
        '@active_users' => 'INT',
        '@message' => 'VARCHAR(255)'
    ])
    ->execute()
    ->stored_procedure_result();

// Access the results
echo $result->output->total_users;  // OUT parameter value
echo $result->output->active_users; // OUT parameter value
echo $result->output->message;      // OUT parameter value
echo $result->result;               // Regular dataset (if any)
```

### OUTPUT/OUT Parameter Usage

**Step 1:** Include OUTPUT/OUT parameters in `stored_procedure_params()` with the appropriate keyword:

**For SQL Server:**

```php
->stored_procedure_params([
    ':api_service_id',
    ':statuscode',
    '@result OUTPUT',
    '@message OUTPUT'
])
```

**For MySQL:**

```php
->stored_procedure_params([
    ':api_service_id',
    ':statuscode',
    '@result',
    '@message'
])
```

> 💡 **Example from your use case:**
>
> ```php
> ->stored_procedure_params([
>     ':api_service_id',
>     ':statuscode',
>     '@result OUTPUT',
> ])
> ```

**Step 2:** Define SQL types in `stored_procedure_output_params()`:

```php
// Simple array (defaults to BIT type for SQL Server, INT for MySQL)
->stored_procedure_output_params(['@result', '@status'])

// Associative array with specific types
->stored_procedure_output_params([
    '@result' => 'INT',
    '@message' => 'VARCHAR(255)',
    '@success' => 'BIT',        // SQL Server
    '@success' => 'TINYINT',    // MySQL equivalent
    '@created_date' => 'DATETIME'
])
```

**Step 3:** Only provide input values in `stored_procedure_values()`:

```php
->stored_procedure_values([$apiServiceId, $statusCode]) // OUTPUT/OUT params handled automatically
```

### Smart Result Handling

The package automatically detects if OUTPUT/OUT parameters are used and returns an object with both `result` (dataset) and `output` (parameters):

```php
$response = StoredProcedure::stored_procedure('sp_complex_operation')
    ->stored_procedure_output_params(['@status' => 'BIT'])
    ->execute()
    ->stored_procedure_result();

// Single OUTPUT parameter → scalar value
$status = $response->output; // Direct scalar value

// Multiple OUTPUT parameters → object
$status = $response->output->status;
$message = $response->output->message;

// Regular dataset
$data = $response->result; // Laravel Collection
```

---

## Pagination Support

The package includes a built-in pagination macro for Laravel Collections, making it easy to paginate stored procedure results:

```php
// Get paginated results from a stored procedure
$users = StoredProcedure::stored_procedure('get_users_paginated')
    ->stored_procedure_params([':page', ':per_page'])
    ->stored_procedure_values([1, 15])
    ->execute()
    ->stored_procedure_result()
    ->paginate(15); // 15 items per page

// Use in your controller
return view('users.index', compact('users'));
```

### Pagination Features

- **Automatic URL handling**: Uses current request URL and query parameters
- **Laravel pagination**: Full compatibility with Laravel's pagination system
- **Customizable**: Set custom page name and per-page count
- **Blade integration**: Works seamlessly with Laravel's pagination views

```php
// Custom pagination options
$results = $storedProcResult->paginate(
    $perPage = 20,        // Items per page
    $page = 2,            // Current page (optional)
    $pageName = 'page'    // Page parameter name (optional)
);

// In your Blade template
{{ $results->links() }}
```

---

## Example: Full Workflow

```php
use MagsLabs\LaravelStoredProc\StoredProcedure;

// Basic usage with parameters and transaction
$users = StoredProcedure::stored_procedure('get_users_by_role')
    ->stored_procedure_connection('mysql') // Optional
    ->stored_procedure_params([':role'])
    ->stored_procedure_values(['admin'])
    ->with_transaction() // Optional
    ->execute()
    ->stored_procedure_result();

// With pagination
$paginatedUsers = StoredProcedure::stored_procedure('get_users_by_role')
    ->stored_procedure_params([':role'])
    ->stored_procedure_values(['admin'])
    ->execute()
    ->stored_procedure_result()
    ->paginate(20); // 20 items per page

// SQL Server with OUTPUT parameters
$stats = StoredProcedure::stored_procedure('sp_get_user_statistics')
    ->stored_procedure_connection('sqlsrv') // SQL Server connection
    ->stored_procedure_params([':user_id'])
    ->stored_procedure_values([123])
    ->stored_procedure_output_params([
        '@total_posts' => 'INT',
        '@is_active' => 'BIT',
        '@last_login' => 'DATETIME'
    ])
    ->execute()
    ->stored_procedure_result();

// MySQL with OUT parameters
$stats = StoredProcedure::stored_procedure('get_user_statistics')
    ->stored_procedure_connection('mysql') // MySQL connection
    ->stored_procedure_params([':user_id'])
    ->stored_procedure_values([123])
    ->stored_procedure_output_params([
        '@total_posts' => 'INT',
        '@is_active' => 'TINYINT',
        '@last_login' => 'DATETIME'
    ])
    ->execute()
    ->stored_procedure_result();

// Access results
$userData = $stats->result;           // Dataset as Collection
$totalPosts = $stats->output->total_posts;  // OUTPUT/OUT parameter
$isActive = $stats->output->is_active;      // OUTPUT/OUT parameter
$lastLogin = $stats->output->last_login;    // OUTPUT/OUT parameter

// Example via dependency injection in a controller
class UserController extends Controller
{
    protected StoredProcedure $storedProc;

    public function __construct(StoredProcedure $storedProc)
    {
        $this->storedProc = $storedProc;
    }

    public function index(Request $request)
    {
        $users = $this->storedProc->stored_procedure('get_users_by_role')
            ->stored_procedure_connection('mysql')
            ->stored_procedure_params([':role'])
            ->stored_procedure_values([$request->role])
            ->with_transaction()
            ->execute()
            ->stored_procedure_result()
            ->paginate(15);

        return view('users.index', compact('users'));
    }

    public function stats($userId)
    {
        $stats = $this->storedProc->stored_procedure('sp_get_user_statistics')
            ->stored_procedure_connection('sqlsrv')
            ->stored_procedure_params([
                ':user_id',
                '@total_posts OUTPUT'
            ])
            ->stored_procedure_values([$userId])
            ->stored_procedure_output_params(['@total_posts' => 'INT'])
            ->execute()
            ->stored_procedure_result();

        return response()->json([
            'user_data' => $stats->result,
            'total_posts' => $stats->output
        ]);
    }
}
```

This is useful when you want to inject the instance or reuse it across multiple calls.

---

## Switching Database Connections

Need to call a stored procedure on a different connection/database?

```php
->stored_procedure_connection('your_own_connection_database_name')
```

This uses Laravel’s connection from `config/database.php`.

---

## Common Gotchas

- You **must** call methods in this order:
  1. `stored_procedure()` (required)
  2. `stored_procedure_connection()` (optional)
  3. `stored_procedure_params()` (optional, if your proc has parameters)
  4. `stored_procedure_values()` (required if you set params)
  5. `stored_procedure_output_params()` (optional, SQL Server only)
  6. `with_transaction()` (optional)
  7. `execute()` (required)
  8. `stored_procedure_result()` (required)

- All **input parameters** must be bound **by position** in the `stored_procedure_values()` array.
- **OUTPUT/OUT parameters** must be included in `stored_procedure_params()` with clean syntax:
  - SQL Server: `'@result OUTPUT'` (OUTPUT keyword required)
  - MySQL: `'@result'` (clean syntax, no OUT keyword needed)
- **OUTPUT/OUT parameters** are supported on both SQL Server and MySQL databases.
- When using OUTPUT/OUT parameters, the result will be an object with `result` and `output` properties.
- **Pagination** works on the returned Collection, so call `paginate()` after `stored_procedure_result()`.
- The **StoredProcedureServiceProvider** registers the `StoredProcedure` binding; the **PaginationServiceProvider** registers the `paginate()` macro on `Collection`, so both are available after installation.
- The **`StoredProcedure`** facade alias is registered automatically so you can use `StoredProcedure::stored_procedure('name')` statically in your app.
- **Logging:** Bound values and output params are logged only at `debug` level to avoid exposing sensitive data in production logs.

---

## Compatibility

- **PHP**: 8.0, 8.1, 8.2, 8.3, 8.4 (Laravel 13 requires PHP 8.3+)
- **Laravel**: 9.x, 10.x, 11.x, 12.x, 13.x (backward compatible; 13.x is the default target)
- **Databases**:
  - MySQL (5.7+, 8.0+)
  - SQL Server (2016+, 2019+, 2022+)
  - _Other databases are not officially supported and may not work as expected_

### Feature Support by Database

| Feature                 | MySQL | SQL Server |
| ----------------------- | ----- | ---------- |
| Basic stored procedures | ✅    | ✅         |
| Parameters & Values     | ✅    | ✅         |
| Transactions            | ✅    | ✅         |
| OUTPUT/OUT Parameters   | ✅    | ✅         |
| Pagination              | ✅    | ✅         |
| Logging                 | ✅    | ✅         |

---

## Advanced Usage

### Complex Stored Procedure with Multiple OUTPUT/OUT Parameters

**SQL Server Example:**

```php
// SQL Server stored procedure with multiple OUTPUT parameters
$result = StoredProcedure::stored_procedure('sp_complex_user_operation')
    ->stored_procedure_connection('sqlsrv')
    ->stored_procedure_params([
        ':user_id',
        ':action',
        '@rows_affected OUTPUT',
        '@success OUTPUT',
        '@message OUTPUT',
        '@execution_time OUTPUT'
    ])
    ->stored_procedure_values([123, 'update_profile']) // Only input values
    ->stored_procedure_output_params([
        '@rows_affected' => 'INT',
        '@success' => 'BIT',
        '@message' => 'VARCHAR(500)',
        '@execution_time' => 'FLOAT'
    ])
    ->with_transaction()
    ->execute()
    ->stored_procedure_result();

// Access all results
$userData = $result->result;                    // Dataset
$rowsAffected = $result->output->rows_affected; // OUTPUT parameter
$success = $result->output->success;            // OUTPUT parameter
$message = $result->output->message;            // OUTPUT parameter
$executionTime = $result->output->execution_time; // OUTPUT parameter
```

**MySQL Example:**

```php
// MySQL stored procedure with multiple OUT parameters
$result = StoredProcedure::stored_procedure('complex_user_operation')
    ->stored_procedure_connection('mysql')
    ->stored_procedure_params([
        ':user_id',
        ':action',
        '@rows_affected',
        '@success',
        '@message',
        '@execution_time'
    ])
    ->stored_procedure_values([123, 'update_profile']) // Only input values
    ->stored_procedure_output_params([
        '@rows_affected' => 'INT',
        '@success' => 'TINYINT',
        '@message' => 'VARCHAR(500)',
        '@execution_time' => 'DECIMAL(10,3)'
    ])
    ->with_transaction()
    ->execute()
    ->stored_procedure_result();

// Access all results
$userData = $result->result;                    // Dataset
$rowsAffected = $result->output->rows_affected; // OUT parameter
$success = $result->output->success;            // OUT parameter
$message = $result->output->message;            // OUT parameter
$executionTime = $result->output->execution_time; // OUT parameter
```

### Pagination with Custom Options

```php
// Advanced pagination with custom options
$users = StoredProcedure::stored_procedure('get_users_with_filters')
    ->stored_procedure_params([':role', ':status', ':search'])
    ->stored_procedure_values(['admin', 'active', $searchTerm])
    ->execute()
    ->stored_procedure_result()
    ->paginate(
        $perPage = 25,           // Items per page
        $page = $request->page,   // Current page
        $pageName = 'users_page' // Custom page parameter name
    );

// In Blade template
{{ $users->appends(request()->query())->links('custom.pagination') }}
```

### Error Handling and Logging

```php
try {
    $result = StoredProcedure::stored_procedure('risky_operation')
        ->stored_procedure_params([':data'])
        ->stored_procedure_values([$complexData])
        ->with_transaction()
        ->execute()
        ->stored_procedure_result();

    // Success handling
    return response()->json([
        'success' => true,
        'data' => $result
    ]);

} catch (Exception $e) {
    // Error is automatically logged to the dedicated log channel
    return response()->json([
        'success' => false,
        'message' => 'Operation failed',
        'error' => $e->getMessage()
    ], 500);
}
```

### Reusing StoredProcedure Instances

```php
class UserService
{
    protected StoredProcedure $storedProc;

    public function __construct(StoredProcedure $storedProc)
    {
        $this->storedProc = $storedProc;
    }

    public function getUserStats($userId)
    {
        return $this->storedProc
            ->stored_procedure('sp_get_user_stats')
            ->stored_procedure_params([
                ':user_id',
                '@total_posts OUTPUT'
            ])
            ->stored_procedure_values([$userId])
            ->stored_procedure_output_params(['@total_posts' => 'INT'])
            ->execute()
            ->stored_procedure_result();
    }

    public function updateUserProfile($userId, $data)
    {
        return $this->storedProc
            ->stored_procedure('sp_update_user_profile')
            ->stored_procedure_params([':user_id', ':name', ':email'])
            ->stored_procedure_values([$userId, $data['name'], $data['email']])
            ->with_transaction()
            ->execute()
            ->stored_procedure_result();
    }
}
```

---

## Logging Stored Procedure Executions

This package includes built-in logging to help trace and debug stored procedure execution.

### Enable a Custom Log File

To log all stored procedure operations into a dedicated log file, add the following channel to your Laravel app’s `config/logging.php`:

```php
'channels' => [

    // other log channels...

    'magslabs_laravel_stored_proc' => [
        'driver' => 'single',
        'path' => storage_path('logs/magslabs_laravel_stored_proc.log'),
        'level' => 'debug',
    ],
],
```

<!-- ---

## 📄 License

MIT License. © [Mark Angelo Sollano / magslabs]

---

## 🙌 Credits

Created by [@masollano](https://github.com/masollano) on (https://github.com/magslabs/laravel-storedproc) -->
