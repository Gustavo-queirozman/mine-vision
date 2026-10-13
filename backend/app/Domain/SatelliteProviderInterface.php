<?php
declare(strict_types=1);
namespace App\Domain;
interface SatelliteProviderInterface
{
 public function searchImages(array $area,string $start,string $end,array $options=[]): array;
 public function getMetadata(array $image): array;
 public function downloadImage(array $image): array;
 public function getPreview(array $image): string;
 public function getBands(array $image): array;
}
