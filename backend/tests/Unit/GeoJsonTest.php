<?php
declare(strict_types=1);

use App\Domain\GeoJson;

$square = ['type' => 'Polygon', 'coordinates' => [[[-47.0, -15.0], [-46.99, -15.0], [-46.99, -15.01], [-47.0, -15.01], [-47.0, -15.0]]]];

test('GeoJSON accepts a valid polygon and calculates an area', function () use ($square): void {
    assertSame($square, GeoJson::validate($square));
    assertTrue(GeoJson::area($square) > 100, 'A área esperada deve ser superior a 100 ha.');
});

test('GeoJSON accepts a mine point', function (): void {
    assertSame(['type' => 'Point', 'coordinates' => [-46.5, -15.5]], GeoJson::validate(['type' => 'Point', 'coordinates' => [-46.5, -15.5]], true));
});

test('GeoJSON rejects non-closed, self-intersecting, and invalid coordinate geometries', function (): void {
    assertThrows(fn() => GeoJson::validate(['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 1]]]]), 422, 'fechados');
    assertThrows(fn() => GeoJson::validate(['type' => 'Polygon', 'coordinates' => [[[0, 0], [2, 2], [0, 2], [2, 0], [0, 0]]]]), 422, 'auto-interseção');
    assertThrows(fn() => GeoJson::validate(['type' => 'Point', 'coordinates' => [200, 0]], true), 422, 'WGS84');
});
