<?php
declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Tenant;
use App\Infrastructure\Repositories\TenantRepository;
use PDO;

/**
 * Inicializa apenas locais de referência reais. Não cria imagens, alertas ou
 * detecções sintéticas: esses registros devem nascer do fluxo operacional.
 */
final class Seeder
{
    public function __construct(private PDO $db) {}

    public function run(): void
    {
        if (getenv('APP_ENV') === 'production') throw new \RuntimeException('Seed de referência desabilitado em produção.');
        $repo = new TenantRepository($this->db);
        $repo->transaction(function () use ($repo): void {
            if ($repo->pg()) $this->db->exec('SELECT pg_advisory_xact_lock(712808)');
            if ($this->db->query('SELECT id FROM companies LIMIT 1')->fetch()) return;

            $company = $repo->create('companies', [
                'name' => 'MineVision — Referências de Ouro',
                'document' => 'BASE-REFERENCIA',
                'email' => 'contato@minevision.local',
                'phone' => 'Não aplicável',
            ], new Tenant(0, 0, 'ADMIN'));

            $tenant = new Tenant((int)$company['id'], 0, 'ADMIN');
            $password = getenv('DEMO_PASSWORD') ?: 'MineVision!2026';
            $users = [];
            foreach (['admin' => 'ADMIN', 'analyst' => 'ANALYST', 'field' => 'FIELD_TECHNICIAN', 'viewer' => 'VIEWER'] as $name => $role) {
                $users[$name] = $repo->create('users', [
                    'name' => ucfirst($name).' MineVision',
                    'email' => $name.'@minevision.local',
                    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    'role' => $role,
                    'active' => true,
                ], $tenant);
            }
            $tenant = new Tenant((int)$company['id'], (int)$users['admin']['id'], 'ADMIN');

            // Coordenadas aproximadas dos centros das operações, em WGS84.
            // Fontes públicas: relatórios técnicos dos respectivos operadores.
            $sites = [
                [
                    'name' => 'Mina Paracatu (Morro do Ouro)',
                    'owner' => 'Kinross Brasil Mineração S.A.',
                    'latitude' => -17.220833, 'longitude' => -46.875000,
                    'municipality' => 'Paracatu', 'state' => 'MG',
                    'description' => 'Operação aurífera a céu aberto em Paracatu. Coordenada aproximada do centro da propriedade descrita no relatório técnico NI 43-101 da Kinross.',
                ],
                [
                    'name' => 'Complexo Cuiabá',
                    'owner' => 'AngloGold Ashanti',
                    'latitude' => -19.862500, 'longitude' => -43.733333,
                    'municipality' => 'Sabará', 'state' => 'MG',
                    'description' => 'Complexo aurífero subterrâneo Cuiabá/Lamego no Quadrilátero Ferrífero. Coordenada aproximada da planta Cuiabá divulgada em relatório da AngloGold Ashanti.',
                ],
                [
                    'name' => 'Mina Aurizona (Piaba)',
                    'owner' => 'Operação brasileira do grupo CMOC',
                    'latitude' => -1.300000, 'longitude' => -45.750000,
                    'municipality' => 'Godofredo Viana', 'state' => 'MA',
                    'description' => 'Operação aurífera de Piaba, em Aurizona. Coordenada aproximada do centro da mina divulgada em relatório técnico; operação brasileira adquirida pelo grupo CMOC em 2026.',
                ],
            ];

            foreach ($sites as $site) {
                $mine = $repo->create('mines', [
                    'name' => $site['name'],
                    'description' => $site['description'].' Operador: '.$site['owner'].'.',
                    'status' => 'ACTIVE',
                    'latitude' => $site['latitude'],
                    'longitude' => $site['longitude'],
                    'municipality' => $site['municipality'],
                    'state' => $site['state'],
                    'country' => 'Brasil',
                    'mineral' => 'ouro',
                    'geometry' => ['type' => 'Point', 'coordinates' => [$site['longitude'], $site['latitude']]],
                ], $tenant);
                $geometry = $this->monitoringSquare((float)$site['longitude'], (float)$site['latitude']);
                $repo->create('monitoring_areas', [
                    'mine_id' => $mine['id'],
                    'name' => 'Área operacional de referência',
                    'description' => 'Polígono inicial de referência. Delimite-o conforme a licença, plano de lavra e autorização aplicáveis antes de qualquer decisão operacional.',
                    'geometry' => $geometry,
                    'area_hectares' => $repo->area($geometry),
                    'monitoring_enabled' => true,
                ], $tenant);
            }
        });
    }

    private function monitoringSquare(float $longitude, float $latitude): array
    {
        $offset = 0.006;
        return ['type' => 'Polygon', 'coordinates' => [[
            [$longitude - $offset, $latitude - $offset],
            [$longitude + $offset, $latitude - $offset],
            [$longitude + $offset, $latitude + $offset],
            [$longitude - $offset, $latitude + $offset],
            [$longitude - $offset, $latitude - $offset],
        ]]];
    }
}
