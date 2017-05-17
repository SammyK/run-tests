# Refactoring run-tests.php

The `run-tests.php` script is a black-box testing tool that is a crucial pillar to the continued development of the PHP language.

**GOAL: Refactor [run-tests.php](https://github.com/php/php-src/blob/master/run-tests.php) so that it is manageable & unit tested. Then we can add concurrency, prettier output & more.**

## The problem

Run-tests is a legacy script that has a number of bugs that are hard to diagnose due its "spaghetti-code" nature. This also makes it extremely difficult to add badly-needed features like concurrency to this critical tool.

## The solution

We should treat run-tests like any other legacy application and refactor it in little bits at a time. Over time the code will become much more manageable so that squashing bugs and adding features will become much more achievable. 

The drawback to refactoring in this way (a little at a time) in the open-source context is that it will take ages, especially pull requests don't get merged in quickly. To speed up development **we need two dedicated volunteers with push-access to the php-src repo who will commit to regularly reviewing and testing pull requests for run-tests**. The goal would be to have a pretty reasonable turn-around time for each run-tests PR (about 1-3 days).

## In it for the long haul

The run-tests refactor will take many months and probably years to "complete". The good news is that with every PR, run-tests will be a little bit better than it was before.

## Proposed refactoring path

Luckily, the run-tests script is just under 3,000 lines of code which sounds like a lot, but it is not too difficult to wrap your head around it with a few hours of auditing. I (@SammyK) have scanned the whole script and made notes of my discoveries in the [Audit Notes](#audit-notes-wip) section below.

After my audit, I propose we kick-off the refactor of run-tests as follows.

- [ ] Create a `run-tests` folder where all the PSR-4 autoloaded & manually included code will live.
- [ ] Move this `README.md` to the `run-tests` folder and keep it updated as run-tests API documentation while it gets refactored. #DocumentationDrivenDevelopment The [.phpt docs](http://qa.php.net/write-test.php) can stay where they are (although there are a few undocumented features like phpdbg that should be added to those docs).
- [ ] Add a `CONTRIBUTING.md` to the `run-tests` folder to encourage others to get involved with the refactoring effort. The document would include tips such as: "PR's for run-tests should be prefixed with the tag [run-tests]." And: "With each refactor, add unit tests and run the whole `phpt` test suite as an 'end-to-end' test to ensure that you didn't break anything."
- [ ] Install PHPUnit with composer **as a dev dependency so that `composer install` won't be required to execute run tests**. You'll only need to run `composer install` if you want to run the unit tests against the run-tests tool. We'll also be using our own autoloader for "production", not the composer one.
- [ ] Keep `run-tests.php` where it is (for forever) and start refactoring the code into the autoloaded classes and included functions from the `run-tests` folder. The ultimate goal would be to have `run-tests.php` just include the bootstrap file and fire off the run-tests app.

Each refactor should take us closer to the following goals:

- [ ] Add more unit tests to the run-tests codebase
- [ ] "Prettify" the run-tests output (with colors for consoles that support it)
- [ ] Add concurrency for faster test execution (big topic to tackle & we probably won't be able to address fully until we get a lot of the code refactored)

This list it by no means comprehensive and is expected to grow as refactoring gets underway.

-----

# Audit Notes (WIP)

These are my notes after auditing the `run-tests.php` script.

## Supported SAPI's

- [ ] CLI
    - Default flags: 
- [ ] PHPDBG
    - Default flags: `-qIb` (`q` do not print banner on startup; `I` ignore .phpdbginit; `b` boring - no colors)
- [ ] CGI
    - Default flags: `-C` (don't chdir to the script directory)

## Full extensions & config required

To ensure run-tests is able to run a comprehensively as possible, make sure you configure with at least the following flags.

```
--enable-cli --enable-phpdbg --enable-cgi --with-zlib
```

## Test section classifications

The [sections in each test file](http://qa.php.net/phpt_details.php) can be organized in the following categories.

- Metadata
    - [ ] `--TEST--`: :star: Short description of test (MUST be first line of the test file)
    - [ ] `--DESCRIPTION--`: Longer description of test
    - [ ] `--CREDITS--`: Author of test
    - [ ] `--XFAIL--`: Explanation as why the test is expected to fail; will mark the test as warned if it passes; will output `XFAIL REASON: {reason}` next to test output line
- Configuration
    - [ ] `--INI--`: The ini settings that should be set with the test; each ini config on its own line, e.g. `default_charset=UTF-8\n zend.assertions=1`; can use `{PWD}` to be converted to present working dir
    - [ ] `--ARGS--`: (??Only PHP CLI??) Command line flags to pass to the SAPI
    - [ ] `--ENV--`: A key/value, new-line-delimited string that is `eval()`'d to set the ENV vars; has access to the following variables:
        - [ ] `$filename`: Full native path to file, will become PATH_TRANSLATED
        - [ ] `$filepath`: Same as `dirname($filename)`
        - [ ] `$scriptname`: What will become `SCRIPT_NAME` unless overwritten
        - [ ] `$docroot`: Same as `DOCUMENT_ROOT` under Apache
        - [ ] `$cwd`: Directory that the test is being executed from
        - [ ] `$this->conf`: All server-tests configuration vars
        - [ ] `$this->env`: All environment variables that will get passed to the test
    - [ ] `--EXTENSIONS--`: The extensions that should be loaded with the test; put each required extension on a new line: `bcmath\n pdo_mysql\n etc..`
    - [ ] ~~`--CGI--`~~: (ignored - see below)
- Input
    - HTTP Input: One and only one of the POST or PUT sections will be processed and will be checked in the following order:
        - [ ] `--GET--`: Sets `QUERY_STRING` to the supplied value
        - [ ] `--COOKIE--`: Sets `HTTP_COOKIE` to the supplied value
        - [ ] `--POST_RAW--`: A raw pseudo-HTTP request sent via the POST method; if the `CONTENT_TYPE` has not been set yet (I don't know how it could be set before this point), it will be set to the value of the `Content-Type` header; if the raw request contains no body, the test will fail as `BORKED`
        - [ ] `--PUT--`: Same as `--POST_RAW--` but sets `REQUEST_METHOD` to `PUT`
        - [ ] `--POST--`: POST data to send to the test; automatically sets the `Content-Type` to `application/x-www-form-urlencoded` 
        - [ ] `--GZIP_POST--`: Same as `--POST--` but data will be encoded via `gzencode()` (GZIP file format); requires `ext/zlib`; will skip test if not installed
        - [ ] `--DEFLATE_POST--`: Same as `--POST--` but data will be encoded via `gzcompress()` (ZLIB format); this is weird because there is a `gzdeflate()` which is what you'd think this section does; requires `ext/zlib`; will skip test if not installed
    - [ ] `--STDIN--`: Sends data to STDIN
    - [ ] `--PHPDBG--`: :star: Input for phpdbg SAPI; gets aliased to `--STDIN--` if it is not already set; test will skip if PHPGB SAPI not enabled; _(For PHPDBG SAPI tests only)_
    - [ ] `--CAPTURE_STDIO--`: Can be set to `STDIN`, `STDOUT`, and/or `STDERR`
- Output
    - [ ] `--EXPECT--`: The literal expected output; compared to actual output with `strcmp()` after all new lines normalized to `\n`
    - [ ] `--EXPECTF--`: Same as `--EXPECT--` but with substitution characters
    - [ ] `--EXPECTREGEX--`: Same as `--EXPECT--` but with regex that is executed with `preg_match()`
    - [ ] `--EXPECT_EXTERNAL--`: Same as `--EXPECT--` but loaded from an external file; :exclamation: not used anywhere internally yet
    - [ ] `--EXPECTF_EXTERNAL--`: Same as `--EXPECTF--` but loaded from an external file; :exclamation: not used anywhere internally yet
    - [ ] `--EXPECTREGEX_EXTERNAL--`: Same as `--EXPECTREGEX--` but loaded from an external file; :exclamation: not used anywhere internally yet
    - [ ] `--EXPECTHEADERS--`: HTTP response headers that must exist; new-line-delimited list of plain-text headers; headers that are not listed in this section will be ignored
- Executable
    - [ ] `--FILE--`: :star: The PHP code to test
    - [ ] `--FILEEOF--`: :star: Removes any `\r\n`'s from end of code and treats as a `--FILE--`
    - [ ] `--FILE_EXTERNAL--`: :star: Same as `--FILE--` but loaded from external file; :exclamation: not used anywhere internally yet
    - [ ] `--REDIRECTTEST--`: :star: MUST return array with `TEST` key (string) and MAY contain `ENV` key (array); not valid with `--EXPECT--` section; cannot refer to other tests that have `--REDIRECTTEST--` section; always "passes" which is weird since it's not really a test
    - [ ] `--CLEAN--`: Code that is executed after the test has run
    - [ ] `--SKIPIF--`: Conditional; if output starts with the word `skip`, the test will be skipped; (some tests include this section as an empty section); the following keywords are supported:
        - [ ] `skip`: will skip the test & output `reason: {any copy after the word "skip"}`
        - [ ] `info`: won't skip test, but will add `(info: {any copy after the word "info"}})` :exclamation: Does not work
        - [ ] `warn`: won't skip test, but will add `(warn: {any copy after the word "warn"}})` :exclamation: Does not work
        - [ ] `xfail`: Acts like adding a `--XFAIL--` section
- Other
    - [ ] `===DONE===`: Anything after this line up to the next tag will be ignored by run-tests; only valid within the `--FILE--`, `--FILEEOF--`, & `--FILE_EXTERNAL--` contexts

> **Note:** All the `*_EXTERNAL` sections will use `file_get_contents()` to load the file; usage of `..` will get stripped.

The following sections are only available via `run-server.php`.

- [ ] `--REQUEST--`
- [ ] `--HEADERS--`
- [ ] `--CGI--`: This exists in some of the run-tests, but run-tests will ignore it. The test will automatically be run from the CGI SAPI if any of the following sections are present: `GET`, `POST`, `GZIP_POST`, `DEFLATE_POST`, `POST_RAW`, `PUT`, `COOKIE`, `EXPECTHEADERS`.

## Environment variables accessed from `getenv()`

- [ ] `TEST_PHP_SRCDIR`: 
- [ ] `SystemRoot`: 
- [ ] `TEST_PHP_EXECUTABLE`: 
- [ ] `TEST_PHP_CGI_EXECUTABLE`: Full path to CGI SAPI executable
- [ ] `TEST_PHPDBG_EXECUTABLE`: Full path to PHPDBG SAPI executable
- [ ] `TEST_PHP_LOG_FORMAT`: 
- [ ] `TEST_PHP_DETAILED`: 
- [ ] `SHOW_ONLY_GROUPS`: Comma-separated list of test states to show in output (see test states below) and/or `REDIRECT`; can be sent via the `-g` flag to run-tests.
- [ ] `TEST_PHP_USER`: 
- [ ] `TRAVIS`: 
- [ ] `NO_INTERACTION`: 
- [ ] `PHP_AUTOCONF`: 
- [ ] `CC`: 
- [ ] `TEST_PHP_ARGS`: 
- [ ] `REPORT_EXIT_STATUS`: 
- [ ] `http_proxy`: 
- [ ] `TEST_PHP_ERROR_STYLE`: 
- [ ] `NO_PHPTEST_SUMMARY`: 
- [ ] `TEST_PHP_JUNIT`: Path to junit log file

## Environment variables set from the CLI flags

- [ ] `TEST_TIMEOUT`: Seconds to run each test before timing out (default: `60` unless memory leak check is enabled by using the `-m` flag, then timeout is set to `300` and `TEST_TIMEOUT` value is ignored)

## Test states

Some statuses can also contain a message/reason for the test being that status.

- [ ] `PASS`: 
- [ ] `FAIL`: 
- [ ] `XFAIL`: 
- [ ] `SKIP`: 
- [ ] `BORK`: Not a valid test file
- [ ] `WARN`: Warning
    - [ ] When a test is expected to fail but passes
- [ ] `LEAK`: Memory Leak (only detectable when passed the `-m` flag)

## Output files

- When tests fail
    - [ ] `{name}.diff`: Diff of expected vs actual output
    - [ ] `{name}.exp`: What the expected output was
    - [ ] `{name}.log`:
    - [ ] `{name}.out`: What the actual output was
    - [ ] `{name}.php`: The PHP code that code executed for the test
    - [ ] `{name}.sh`: Bash script to run the script again with the same exact configuration as it was run in run-tests (with executable perms on file)
- When tests complete
    - [ ] `php_test_results_{Ymd_Hi}.txt`: 

## Temporary files

## junit integrations

@TODO Figure out why all this stuff is here

## Broken/old crap

- [ ] For all `file_get_contents()` and `file_put_contents()` operations using [`FILE_BINARY`](http://php.net/manual/en/filesystem.constants.php#constant.file-binary), those have no effect.
- LOLz
    - [Debug your regex](https://github.com/php/php-src/blob/041652bd4186a469d1e0074f87a9b4f7e0970fe8/run-tests.php#L2124-L2128)

## Refactoring considerations

- [ ] Disambiguate "environment": run-tests environment, target test env vars, etc
- [ ] Normalizing new lines (`$foo = preg_replace('/\r\n/',"\n", $foo);`) happens a lot
- [ ] There seems to be a lot of places that convert plain-text key/value formats into an array with different implementations
- [ ] Prefixing output of a `--SKIPIF--` section with `warn` or `info` does not send the rest of the output to STDOUT
- [ ] There is a lot of creating and deleting files throughout. Perhaps we only write to file if user explicitly wants it (like `$test_skipif` file)
- [ ] Perhaps refactor `--EXPECTF--` with a proper parser? (Mainly for refactoring the `%r` support: "Any string enclosed between two `%r` will be treated as a regular expression")
- [ ] The diff file generation could be improved

## Desired new features

- [ ] Create a best-practices doc to expand on [naming conventions](http://qa.php.net/write-test.php#naming-conventions) and include test titles, handling skip tests, etc.
- [ ] Add unit tests
- [ ] And cleaner command-line output (with colors!)
    - [ ] Support "themes" with a few defaults
- [ ] Add concurrency *(@TODO expand this topic)*
- [ ] Add new output file to failed test `{name}-gdb.sh` with bash script to run `gdb` on php file
- [ ] Specify what failed test output files you want `--failed-out=diff,php,sh`
- [ ] Make clean API for test files to make things like this easier: `php-src/ext/pdo/tests/pdo_test.inc` (trying to grab the env vars from the `REDIRECTTEST` section).
    - Make `$testFile` available in each execution block? Or at least `REDIRECTTEST` section? Or just the env vars for the `REDIRECTTEST` section?
- [ ] Raise warning when `--FILE--` section doesn't end in closing `?>` tag

## Considerations for run-server

What is this and why was the last meaningful commit like [a decade ago](https://github.com/php/php-src/commit/65399be52787288d387b0a4dd0a4298b86843d88#diff-81c3ff5bfe1d6b23e1831b13fbed83f0)? Perhaps we can just use the built-in web server to run server tests?
