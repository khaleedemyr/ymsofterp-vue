<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$req = Illuminate\Http\Request::create('/api/mobile/member/brands', 'GET', ['include_fc' => '1']);
$controller = app(App\Http\Controllers\Mobile\Member\BrandController::class);
$response = $controller->index($req);
$data = json_decode($response->getContent(), true);

echo 'Total: ' . count($data['data'] ?? []) . PHP_EOL;
foreach ($data['data'] ?? [] as $o) {
    if (preg_match('/asian|melt|justus bec|justus bip|justus fcl/i', $o['name'])) {
        echo $o['name'] . PHP_EOL;
    }
}
