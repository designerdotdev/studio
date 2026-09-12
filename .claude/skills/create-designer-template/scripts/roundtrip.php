<?php
/*
    roundtrip.php <lab-dir> <slug> — the reader/writer invariant.

    Installs the template (replace), hashes every file under resources/designer,
    flushes the mirror, and compares; then forces a re-read and flushes again.
    Any changed file means the writer would rewrite an untouched template on
    the first publish. Run only inside a throwaway lab app.
*/
[$script, $lab, $slug] = $argv + [null, null, null];
if (!$lab || !$slug || !is_file("$lab/artisan")) {
    fwrite(STDERR, "usage: php roundtrip.php <lab-dir> <slug>\n");
    exit(2);
}
if (str_starts_with(realpath($lab), getenv('HOME') . '/Sites/designer')) {
    fwrite(STDERR, "refusing to run against the host app\n");
    exit(2);
}
chdir($lab);
require "$lab/vendor/autoload.php";
$app = require "$lab/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$root = "$lab/resources/designer";
$hash = function () use ($root) {
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $out[substr($f->getPathname(), strlen($root) + 1)] = md5_file($f->getPathname());
    }
    ksort($out);
    return $out;
};
$diff = function (array $a, array $b) {
    $changed = [];
    foreach ($b as $k => $h) {
        if (!isset($a[$k])) { $changed[] = "+ $k"; } elseif ($a[$k] !== $h) { $changed[] = "~ $k"; }
    }
    foreach ($a as $k => $h) {
        if (!isset($b[$k])) { $changed[] = "- $k"; }
    }
    return $changed;
};

$installer = $app->make(Designer\Studio\Services\Site\SiteInstaller::class);
$mirror = $app->make(Designer\Studio\Services\Site\SiteMirror::class);

$installer->install($slug, true);
$mirror->sync();
$before = $hash();

$notes = $mirror->flush();
$after = $hash();
$c1 = $diff($before, $after);
echo "flush after install: " . count($c1) . " changed\n";
foreach ($c1 as $l) echo "  $l\n";
foreach ($notes as $n) echo "  note: $n\n";

$mirror->refresh(true);
$mirror->flush();
$c2 = $diff($before, $hash());
echo "flush after forced re-read: " . count($c2) . " changed\n";
foreach ($c2 as $l) echo "  $l\n";

exit($c1 || $c2 ? 1 : 0);
