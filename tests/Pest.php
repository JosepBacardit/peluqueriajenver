<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Runs $callback and returns the SQL of every query it ran, with row locks
 * visible. SQLite, the test database, drops FOR UPDATE from the SQL it
 * runs, so a plain query log cannot tell a locking read from a plain one.
 * This swaps in a grammar that writes the lock as an SQL comment (which
 * SQLite ignores). It proves that lockForUpdate() was called on a query
 * and in which order; it cannot prove how MySQL then locks, which rests on
 * the reasoning documented in the actions.
 *
 * @return list<string>
 */
function sqlWithVisibleLocks(Closure $callback): array
{
    $connection = Illuminate\Support\Facades\DB::connection();
    $originalGrammar = $connection->getQueryGrammar();
    $recording = true;
    $queries = [];

    $connection->setQueryGrammar(new class($connection) extends Illuminate\Database\Query\Grammars\SQLiteGrammar
    {
        protected function compileLock(Illuminate\Database\Query\Builder $query, $value)
        {
            return match (true) {
                $value === true => '/* for update */',
                $value === false => '/* lock in share mode */',
                default => is_string($value) ? '/* '.$value.' */' : '',
            };
        }
    });
    $connection->listen(function ($query) use (&$queries, &$recording) {
        if ($recording) {
            $queries[] = $query->sql;
        }
    });

    try {
        $callback();
    } finally {
        $recording = false;
        $connection->setQueryGrammar($originalGrammar);
    }

    return $queries;
}
