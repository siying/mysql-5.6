<?php
// Copyright 2013-present Facebook.  All rights reserved.
// @author: rahulgulati (rahulgulati@fb.com)

class FacebookMySQLLintEngine extends ArcanistLintEngine {

  public function buildLinters() {
    $linters = array();
    $paths = $this->getPaths();

    foreach ($paths as $key => $path) {
      if (!Filesystem::pathExists($this->getFilePathOnDisk($path))) {
        unset($paths[$key]);
      }
    }

    $text_extensions = (
      '/\.(cpp|cxx|c|cc|h|hpp|hxx|tcc|txt|py|sh|cmake'.
      '|css|sql|inc|pl|php|json|java|html|i|ic|yy)$/'
    );

    // All paths that have a recognizable text extension
    $text_paths = preg_grep($text_extensions, $paths);

    $cpp_extensions = ('/\.(cpp|cxx|c|cc|h|hpp|hxx|tcc|i|ic)$/');

    // All paths that have a C++ extension
    $all_cpp_paths = preg_grep($cpp_extensions, $paths);

    // ArcanistGeneratedLinter stops other linters from running on generated
    // code.
    $linters[] = id(new ArcanistGeneratedLinter())->setPaths($text_paths);

    // ArcanistNoLintLinter stops other linters from running on code marked
    // with a nolint annotation.
    $linters[] = id(new ArcanistNoLintLinter())->setPaths($text_paths);

    // FacebookMySqlLinter enforces the following lint checks: max line length
    // is 80 characters, use Unix newlines instead of DOS newlines, Files
    // should end in a new line, and, lines containing trailing whitespace.
    $linters[] = id(new FacebookMySQLLinter())->setPaths($text_paths);

    // ArcanistCpplintLinter runs cpplint.py
    // Run on all C++ files that are in MyRocks (include 'storage/rocksdb')
    $myrocks_cpp_paths = preg_grep('/storage\/rocksdb/', $all_cpp_paths);
    $linters[] = id(new ArcanistCpplintLinter())->setPaths($myrocks_cpp_paths);

    // Currently we can't run flint (FbcodeCppLinter) in commit hook mode,
    // because it depends on having access to the working directory.
    if (!$this->getCommitHookMode()) {
      // FbcodeCppLinter runs flint
      // Run on all C++ files that are in MyRocks (include 'storage/rocksdb')
      $linters[] = id(new FbcodeCppLinter())->setPaths($myrocks_cpp_paths);
    }

    // This linter calls git diff to see the old data and gives warnings about
    // lines that only have whitespace changes to avoid rebase problems later
    // Run on all C++ paths
    $linters[] = id(new FacebookMySQLWhitespaceLinter())
        ->setPaths($all_cpp_paths);

    // This linter looks to see if any changes in InnoDB use tabs as most of
    // the files there expect tabs instead of spaces.
    // Run on all C++ files that are in InnoDB (include 'storage/innobase')
    $innodb_cpp_paths = preg_grep('/storage\/innobase/', $all_cpp_paths);
    $linters[] = id(new FacebookInnoDBTabLinter())
        ->setPaths($innodb_cpp_paths);

    // ArcanistSpellingLinter enforces basic spelling. A blacklisted set of
    // words that are commonly spelled incorrectly are used.
    $spelling_linter = new ArcanistSpellingLinter();
    $spelling_linter->setCustomSeverityMap(
      array(
        ArcanistSpellingLinter::LINT_SPELLING_PICKY
          => ArcanistLintSeverity::SEVERITY_WARNING,
        ArcanistSpellingLinter::LINT_SPELLING_IMPORTANT
          => ArcanistLintSeverity::SEVERITY_WARNING,
      )
    );
    $spelling_linter->setPaths($text_paths);
    $linters[] = $spelling_linter;

    // ArcanistFilenameLinter stifles creativity in choosing imaginative file
    // names.
    $linters[] = id(new ArcanistFilenameLinter())->setPaths($paths);

    $linters[] = id(new FacebookMySQLPrintfLinter())
        ->setPaths($all_cpp_paths);

    $linters[] = id(new FacebookMySQLAssertUsageLinter())
        ->setPaths($myrocks_cpp_paths);

    //
    // If SKIP_HOWTOEVEN is specified then don't run Howtoeven linter.
    // Readability note: strcmp() returns a non-zero value if strings aren't
    // equal.
    //
    if (strcmp(getenv("SKIP_HOWTOEVEN"), "1")) {
      // Advanced static analysis will be only applied to MyRocks because the
      // existing MySQL codebase differs too much from our requirements.
      $linters[] = id(new FacebookMySQLHowtoevenLinter())
          ->setPaths($myrocks_cpp_paths);
    }

    // All paths in mysql-test that have a .test or .result extension
    $mysql_test_paths = preg_grep('/mysql-test\/.*\.(test|result)$/', $paths);

    // This linter validates that new .test files (in mysql-test/) have
    // have matchine .result files
    $linters[] = id(new FacebookMySQLTestResultLinter())
        ->setPaths($mysql_test_paths);

    return $linters;
  }
}
