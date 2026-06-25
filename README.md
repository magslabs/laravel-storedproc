# Laravel-Storedproc

**Laravel-Storedproc** is a fluent wrapper for calling stored procedures in Laravel — because not every query belongs in Eloquent.

In many real-world applications, especially enterprise systems, stored procedures are a key part of the backend. Laravel doesn’t natively support them in an elegant way, so most developers are stuck writing raw `DB::select()` queries over and over.

This package simplifies that.

## Features

- Fluent, chainable syntax for stored procedure calls (with shorter method aliases)
- Parameter binding from arrays or Laravel requests
- Optional Laravel-managed transaction support with automatic rollback on failure
- Connection-aware drivers for **MySQL** and **SQL Server** (`CALL` / `EXEC` resolved per connection)
- **Automatic existence check** before every `execute()` / `run()` (v2 default; configurable)
- Opt-in **parameter validation** against the database schema
- OUTPUT/OUT parameter support for SQL Server and MySQL stored procedures
- Built-in pagination macro for Laravel Collections
- Schema qualification (`dbo.sp_users`, `database.procedure`, or `stored_procedure_schema()`)
- Enhanced logging with dedicated log channel
- Smart result handling (datasets + OUTPUT parameters)
- Typed exceptions for missing procedures, parameter mismatches, and invalid call order

---

## Installation

You can install the package via Composer:

```bash
composer require magslabs/laravel-storedproc
```

The package will automatically register the `StoredProcedureServiceProvider` (binding), `PaginationServiceProvider` (Collection `paginate` macro), and the `StoredProcedure` facade alias.

### Configuration (optional)

Publish the config file to customize validation and schema defaults:

```bash
php artisan vendor:publish --tag=storedproc-config
```

This creates `config/storedproc.php`. You can also set these via `.env`:

| Variable | Default | Description |
| -------- | ------- | ----------- |
| `STORED_PROC_CHECK_EXISTS` | `true` | Verify the procedure exists before every `execute()` / `run()` |
| `STORED_PROC_VALIDATE` | `false` | Fully validate parameters (count, direction) before execution |
| `STORED_PROC_SCHEMA` | `dbo` | Default schema when the procedure name has no prefix (SQL Server) |
| `STORED_PROC_CHECK_SYNONYMS` | `false` | Treat SQL Server synonyms as callable procedures in existence checks |

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

// Shorter aliases (same behavior)
$result = StoredProcedure::procedure('get_user_by_id')
    ->params([':id'])
    ->values([1])
    ->run()
    ->result();
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

## Method Aliases

All original method names remain supported. Shorter aliases are available for the same behavior:

| Original | Alias |
| -------- | ----- |
| `stored_procedure()` | `procedure()` |
| `stored_procedure_connection()` | `connection()` |
| `stored_procedure_params()` | `params()` |
| `stored_procedure_values()` | `values()` |
| `stored_procedure_output_params()` | `outputs()` |
| `execute()` | `run()` |
| `stored_procedure_result()` | `result()` |
| `stored_procedure_output_results()` | `outputResults()` |

---

## Validation & Existence Checks (v2)

Starting in **v2.0.0**, every `execute()` / `run()` automatically verifies the procedure exists on the database before running it. Disable this to restore v1 behavior:

```env
STORED_PROC_CHECK_EXISTS=false
```

### Full parameter validation

Opt in per call with `validate()`, or globally via `.env`:

```env
STORED_PROC_VALIDATE=true
```

```php
$result = StoredProcedure::procedure('get_user_by_id')
    ->params([':id'])
    ->values([1])
    ->validate()  // checks existence + input/output parameter counts
    ->run()
    ->result();
```

When validation fails, a `ParameterMismatchException` is thrown with a message listing what the database expects vs. what you provided.

### Check existence without executing

```php
// Throws StoredProcedureNotFoundException if missing
StoredProcedure::procedure('get_users')->assertExists();

// Returns true/false without throwing
if (StoredProcedure::procedure('get_users')->exists()) {
    // ...
}
```

### Schema qualification

Unqualified names are resolved automatically:

- **SQL Server**: prefixed with `dbo` (or `STORED_PROC_SCHEMA`)
- **MySQL**: prefixed with the connection database name

Override explicitly:

```php
// Qualified name in the procedure itself
StoredProcedure::procedure('dbo.sp_get_users')->run()->result();

// Or set schema separately
StoredProcedure::procedure('sp_get_users')
    ->stored_procedure_schema('custom_schema')
    ->run()
    ->result();
```

On SQL Server, set `STORED_PROC_CHECK_SYNONYMS=true` if you call procedures via synonyms and want them to pass existence checks.

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

This is useful when you want to inject the instance or reuse it across multiple calls. The instance auto-resets after `result()`; call `reset()` manually if you need to clear state earlier.

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
  1. `stored_procedure()` / `procedure()` (required)
  2. `stored_procedure_connection()` / `connection()` (optional)
  3. `stored_procedure_schema()` (optional)
  4. `stored_procedure_params()` / `params()` (optional, if your proc has parameters)
  5. `stored_procedure_values()` / `values()` (required if you set params)
  6. `stored_procedure_output_params()` / `outputs()` (optional, SQL Server & MySQL)
  7. `validate()` or `assertExists()` (optional)
  8. `with_transaction()` (optional)
  9. `execute()` / `run()` (required)
  10. `stored_procedure_result()` / `result()` (required)

- All **input parameters** must be bound **by position** in the `stored_procedure_values()` array.
- **OUTPUT/OUT parameters** must be included in `stored_procedure_params()` with clean syntax:
  - SQL Server: `'@result OUTPUT'` (OUTPUT keyword required)
  - MySQL: `'@result'` (clean syntax, no OUT keyword needed)
- **OUTPUT/OUT parameters** are supported on both SQL Server and MySQL databases.
- When using OUTPUT/OUT parameters, the result will be an object with `result` and `output` properties.
- **Pagination** works on the returned Collection, so call `paginate()` after `stored_procedure_result()`.
- After calling `result()`, the fluent instance **auto-resets** so it can be reused safely via dependency injection.
- **v2 existence checks** run before execution by default. Set `STORED_PROC_CHECK_EXISTS=false` if you need v1 behavior.
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
| Existence checks (v2)   | ✅    | ✅         |
| Parameter validation    | ✅    | ✅         |
| Synonym support (check) | —     | ✅         |

---

## Upgrading from v1 to v2

**v2.0.0** is backward compatible — all v1 method names and chains still work.

Breaking-adjacent changes to be aware of:

1. **Existence checks are on by default.** Every `execute()` / `run()` verifies the procedure exists before calling it. Set `STORED_PROC_CHECK_EXISTS=false` to disable.
2. **Call-order errors** now throw `InvalidCallOrderException` (still extends `Exception`).
3. **Execution is driver-based.** `CALL` vs `EXEC` is resolved from the active connection, not a global default.

Publish config when you want to tune validation or schema defaults:

```bash
php artisan vendor:publish --tag=storedproc-config
```

See [CHANGELOG.md](CHANGELOG.md) for the full v2 release notes.

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

The package throws typed exceptions you can catch individually:

| Exception | When |
| --------- | ---- |
| `StoredProcedureNotFoundException` | Procedure missing (existence check or DB error) |
| `ParameterMismatchException` | Parameter count/direction does not match the database |
| `InvalidCallOrderException` | Fluent methods called in the wrong order |
| `UnsupportedDriverException` | Connection driver is not MySQL or SQL Server |

All extend `StoredProcedureException`, which extends PHP's `Exception`.

```php
use MagsLabs\LaravelStoredProc\Exceptions\InvalidCallOrderException;
use MagsLabs\LaravelStoredProc\Exceptions\ParameterMismatchException;
use MagsLabs\LaravelStoredProc\Exceptions\StoredProcedureNotFoundException;

try {
    $result = StoredProcedure::procedure('risky_operation')
        ->params([':data'])
        ->values([$complexData])
        ->validate()
        ->with_transaction()
        ->run()
        ->result();

    return response()->json([
        'success' => true,
        'data' => $result,
    ]);
} catch (StoredProcedureNotFoundException $e) {
    return response()->json(['message' => 'Procedure not found'], 404);
} catch (ParameterMismatchException $e) {
    return response()->json(['message' => $e->getMessage()], 422);
} catch (InvalidCallOrderException $e) {
    return response()->json(['message' => $e->getMessage()], 500);
} catch (Exception $e) {
    // Execution failures are logged to the dedicated log channel
    return response()->json([
        'success' => false,
        'message' => 'Operation failed',
        'error' => $e->getMessage(),
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

---

## License

MIT License. © [Mark Angelo Sollano / magslabs](https://github.com/magslabs)

---

## Credits

Created by [@masollano](https://github.com/masollano) — [magslabs/laravel-storedproc](https://github.com/magslabs/laravel-storedproc)
