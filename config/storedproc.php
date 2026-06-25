<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Check Existence Before Execute (v2 default)
    |--------------------------------------------------------------------------
    |
    | When true, every execute()/run() call verifies the procedure exists in
    | the database before running it. Set to false to restore v1 behaviour.
    |
    */

    'check_exists_before_execute' => filter_var(
        env('STORED_PROC_CHECK_EXISTS', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    |--------------------------------------------------------------------------
    | Validate Parameters Before Execute
    |--------------------------------------------------------------------------
    |
    | When true, every stored procedure call is fully validated against the
    | database (existence + parameter count/direction) before execution.
    | ->validate() in the chain always runs full validation regardless.
    |
    */

    'validate_before_execute' => filter_var(
        env('STORED_PROC_VALIDATE', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    |--------------------------------------------------------------------------
    | Default Schema
    |--------------------------------------------------------------------------
    |
    | Used when a procedure name has no schema prefix (e.g. "sp_users" instead
    | of "dbo.sp_users"). SQL Server defaults to "dbo"; MySQL uses the
    | connection database.
    |
    */

    'default_schema' => env('STORED_PROC_SCHEMA', 'dbo'),

    /*
    |--------------------------------------------------------------------------
    | Include Synonyms in Existence Check (SQL Server)
    |--------------------------------------------------------------------------
    |
    | When false (default), only real stored procedures match the existence
    | check. When true, synonyms are treated as callable procedures too.
    |
    */

    'check_synonyms' => filter_var(
        env('STORED_PROC_CHECK_SYNONYMS', false),
        FILTER_VALIDATE_BOOLEAN
    ),

];
