<?php
declare(strict_types=1);

use App\Application\Services\MineralExplorationService;
use App\Infrastructure\Repositories\TenantRepository;

test('GeoSGB feature parser tolerates literal control characters in provider attributes', function (): void {
    $db = new PDO('sqlite::memory:');
    $service = new MineralExplorationService(new TenantRepository($db));
    $method = new ReflectionMethod($service, 'featuresFromGeoSgb');
    $features = $method->invoke($service, "{\"features\":[{\"properties\":{\"nome\":\"Serra\nDourada\"}}]}");

    assertSame('SerraDourada', $features[0]['properties']['nome']);
});

test('GeoSGB feature parser recovers legacy bytes in provider attributes', function (): void {
    $db = new PDO('sqlite::memory:');
    $service = new MineralExplorationService(new TenantRepository($db));
    $method = new ReflectionMethod($service, 'featuresFromGeoSgb');
    $features = $method->invoke($service, "{\"features\":[{\"properties\":{\"nome\":\"Cora\xE7\xE3o de Ouro\"}}]}");

    assertSame('Cora??o de Ouro', $features[0]['properties']['nome']);
});

test('classifica ouro somente pela substância oficial do RECMIN', function (): void {
    $service = new MineralExplorationService(new TenantRepository(new PDO('sqlite::memory:')));
    $method = new ReflectionMethod($service, 'gold');

    assertTrue($method->invoke($service, ['substancias' => 'Ouro, Prata']));
    assertTrue($method->invoke($service, ['substancias' => 'Au']));
    assertSame(false, $method->invoke($service, [
        'substancias' => 'Cobre',
        'descricao' => 'Amostra coletada próxima a uma antiga área de ouro.',
    ]));
});

test('preserva a data de cadastro divulgada pelo SGB', function (): void {
    $service = new MineralExplorationService(new TenantRepository(new PDO('sqlite::memory:')));
    $method = new ReflectionMethod($service, 'sourceDate');

    assertSame('2004-02-04', $method->invoke($service, ['data_cadastro' => '2004-02-04Z']));
    assertSame(null, $method->invoke($service, ['data_cadastro' => 'não informado']));
});
