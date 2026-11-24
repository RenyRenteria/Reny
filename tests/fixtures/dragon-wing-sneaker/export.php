<?php

// Offline QA export only: never register this script as a web route or deploy it.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

if (PHP_SAPI !== 'cli' || $argc !== 2) {
    echo "Usage: php tests/fixtures/dragon-wing-sneaker/export.php /tmp/dragon-wing-preview.html\n";
    exit(1);
}

$root = dirname(__DIR__, 3);
if (file_exists($root.'/.env') || file_exists($root.'/bootstrap/cache/config.php')) {
    fwrite(STDERR, "Use a clean worktree without .env or cached configuration.\n");
    exit(1);
}
foreach (['APP_ENV' => 'testing', 'APP_URL' => 'http://localhost', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array', 'YOYCOL_ENABLED' => 'false'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Http::preventStrayRequests();

$dataUri = static function (string $path): string {
    if (! is_file($path)) {
        throw new RuntimeException('Missing preview asset: '.$path);
    }
    $mime = str_ends_with($path, '.ttf') ? 'font/ttf' : mime_content_type($path);

    return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
};
$dragon = json_decode(file_get_contents(__DIR__.'/catalog.json'), true, flags: JSON_THROW_ON_ERROR);
$photos = array_map(static fn (array $photo): array => [...$photo, 'src' => $dataUri(__DIR__.'/images/'.$photo['file'])], $dragon['photos']);
$app['view']->getFinder()->prependLocation(__DIR__.'/views');
$html = view('newlanding', ['dragon' => $dragon, 'dragonPhotos' => $photos])->render();

$publicAssets = $root.'/public/newlanding-assets/';
$css = file_get_contents($publicAssets.'styles.css');
$css = preg_replace_callback("~url\('([^']+)'\)~", static fn (array $match): string => "url('".$dataUri($publicAssets.$match[1])."')", $css);
$css .= "\n".file_get_contents(__DIR__.'/preview.css');
$script = file_get_contents($publicAssets.'script.js')."\n".file_get_contents(__DIR__.'/preview.js');
$html = preg_replace('~<link rel="stylesheet"[^>]+>~', '<style>'.$css.'</style>', $html);
$html = preg_replace('~<script src="/newlanding-assets/[^>]+></script>~', '<script>'.$script.'</script>', $html);
$html = preg_replace_callback('~\b(src|srcset)="(/newlanding-assets/[^"?]+)"~', static fn (array $match): string => $match[1].'="'.$dataUri($root.'/public'.$match[2]).'"', $html);

// Keep this file self-contained; none of the homepage purchase links may leave it.
$html = preg_replace('~href="(?!#|data:)[^"]+"~', 'href="#preview-status"', $html);
$html = str_replace([' target="_blank"', ' download'], '', $html);
$html = str_replace('href="#preview-status">Shop', 'href="#coleccion">Shop', $html);
$html = str_replace('Reny Renteria Takeover · Nuevo inicio', 'Dragon Wing Sneaker · Ensayo sin pagos', $html);
$html = str_replace('Las nueve piezas de esta página son una propuesta visual. Consulta los productos disponibles en', 'Esta vista permite revisar el Dragon Wing Sneaker, sus fotos y tallas. El pago de prueba sigue pendiente. Vuelve a', $html);
$html = str_replace('<body id="top">', '<body id="top"><p class="rehearsal-status" id="preview-status">VISTA DE ENSAYO · Sin cobros ni pedidos. Los enlaces de compra están desactivados. <a href="#coleccion">Ver Dragon Wing Sneaker ↓</a></p>', $html);
$html = str_replace('<head>', '<head><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; font-src data:; style-src \'unsafe-inline\'; script-src \'unsafe-inline\'; connect-src \'none\'; form-action \'none\'; base-uri \'none\'">', $html);
if (file_put_contents($argv[1], $html) === false) {
    throw new RuntimeException('Could not write preview.');
}
fwrite(STDOUT, "Offline preview written. No database writes or provider calls were made.\n");
