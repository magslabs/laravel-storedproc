# Changelog

## 2.0.0

### Added

- Connection-aware drivers (`MySqlDriver`, `SqlServerDriver`) — `CALL` / `EXEC` resolved from the active connection, not the default.
- **Automatic existence check** on every `execute()` / `run()` (disable with `STORED_PROC_CHECK_EXISTS=false`).
- Opt-in full parameter validation via `->validate()` or `STORED_PROC_VALIDATE=true`.
- `assertExists()`, `exists()`, and `stored_procedure_schema()`.
- Shorter method aliases: `procedure()`, `connection()`, `params()`, `values()`, `outputs()`, `run()`, `result()`, `outputResults()` (original names unchanged).
- Publishable config: `php artisan vendor:publish --tag=storedproc-config`.
- Custom exceptions: `StoredProcedureNotFoundException`, `ParameterMismatchException`, `InvalidCallOrderException`, `UnsupportedDriverException`.

### Changed

- Call-order errors now throw `InvalidCallOrderException` (still extends `Exception`).
- Execution logic extracted into driver classes; introspection via `MySqlIntrospector` / `SqlServerIntrospector`.

### Upgrade notes

- **v2 checks that procedures exist before running them.** If you relied on calling non-existent procedures (unlikely), set `STORED_PROC_CHECK_EXISTS=false` in `.env`.
- All v1 method names and chains remain supported.

## 1.x

Initial release: fluent stored procedure calls, OUTPUT parameters, transactions, and Collection pagination.
