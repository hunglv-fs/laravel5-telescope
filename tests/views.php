<?php
/**
 * Compiles every dashboard view with the application's own Laravel 5.0 Blade
 * compiler and lints the result. Run it with:
 *
 *     php packages/telescope/tests/views.php
 */

// Works both standalone (vendor/ in the package) and vendored inside an app.
$autoloaders = [__DIR__.'/../vendor/autoload.php', __DIR__.'/../../../vendor/autoload.php'];

foreach ($autoloaders as $autoloader) {
    if (file_exists($autoloader)) { require $autoloader; break; }
}

if (!class_exists('Illuminate\View\Compilers\BladeCompiler')) {
    fwrite(STDERR, "illuminate/view is not installed; run composer install first.\n");
    exit(1);
}

$out = sys_get_temp_dir().'/telescope-view-check';
@mkdir($out, 0777, true);

$files = new Illuminate\Filesystem\Filesystem;
$blade = new Illuminate\View\Compilers\BladeCompiler($files, $out);

$fail = 0;
foreach (glob(__DIR__.'/../resources/views/*.blade.php') as $view) {
    // compile() is what CompilerEngine calls; it resets the extends footer.
    $blade->compile($view);

    $target = $out.'/'.md5($view);
    $name = basename($view);

    $lines = []; $code = 0;
    exec('php -l '.escapeshellarg($target).' 2>&1', $lines, $code);

    $footers = substr_count(file_get_contents($target), "make('telescope::layout'");
    $expected = strpos(file_get_contents($view), '@extends') !== false ? 1 : 0;

    if ($code === 0 && $footers === $expected) {
        echo "  ok   $name -> valid PHP, $footers layout footer\n";
    } else {
        $fail++;
        echo "  FAIL $name (footers: $footers, expected $expected)\n        ".implode("\n        ", $lines)."\n";
    }
}
echo $fail === 0 ? "\nAll views compile.\n" : "\n$fail view(s) broken.\n";
exit($fail === 0 ? 0 : 1);
