<?php
declare(strict_types=1);

use App\Application\DTO\ResourceRequest;
use App\Domain\Schema;
use App\Domain\Tenant;

test('resource requests normalize textual fields and validate a complete mine', function (): void {
    $request = ResourceRequest::from('mines', [
        'name' => '  Mina Aurora  ', 'latitude' => -15.7, 'longitude' => -47.8,
        'municipality' => '  Brasília ', 'state' => 'DF', 'status' => 'ACTIVE',
        'geometry' => ['type' => 'Polygon', 'coordinates' => [[[-47.81,-15.71],[-47.79,-15.71],[-47.79,-15.69],[-47.81,-15.69],[-47.81,-15.71]]]],
    ]);
    assertSame('Mina Aurora', $request->values['name']);
    assertSame('Brasília', $request->values['municipality']);
});

test('resource requests return all field problems together', function (): void {
    assertThrows(fn() => ResourceRequest::from('mines', ['name' => '', 'latitude' => 91, 'unknown' => true]), 422, 'Confira');
});

test('schema and tenant authorization enforce their public contracts', function (): void {
    assertSame('monitoring_areas', Schema::table('monitoring-areas'));
    assertSame(true, Schema::defaults('users')['active']);
    assertThrows(fn() => Schema::table('secrets'), 404, 'Recurso');
    assertThrows(fn() => (new Tenant(1, 1, 'VIEWER'))->allow(['ADMIN']), 403, 'permissão');
});
