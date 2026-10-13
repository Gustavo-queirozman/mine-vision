<?php
declare(strict_types=1);

use App\Application\Services\MonitoringService;
use App\Domain\SatelliteProviderInterface;
use App\Domain\Tenant;
use App\Infrastructure\Repositories\TenantRepository;

test('map includes alerts and inspections using the geometry of their linked context', function (): void {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    foreach (['001_sqlite.sql', '002_sqlite.sql', '003_sqlite.sql', '004_sqlite.sql'] as $migration) {
        $db->exec(file_get_contents(dirname(__DIR__, 2).'/database/migrations/'.$migration));
    }
    $now = gmdate('c');
    $db->exec("INSERT INTO companies(id,name,email,created_at,updated_at) VALUES(1,'Teste','teste@minevision.local','$now','$now')");
    $db->exec("INSERT INTO users(id,company_id,name,email,password_hash,role,active,created_at,updated_at) VALUES(1,1,'Admin','admin@minevision.local','x','ADMIN',1,'$now','$now')");
    $db->exec("INSERT INTO mines(id,company_id,name,status,latitude,longitude,municipality,state,created_at,updated_at) VALUES(1,1,'Mina','ACTIVE',-15,-47,'X','MG','$now','$now')");
    $area = json_encode(['type'=>'Polygon','coordinates'=>[[[-47.01,-15.01],[-46.99,-15.01],[-46.99,-14.99],[-47.01,-14.99],[-47.01,-15.01]]]]);
    $db->prepare('INSERT INTO monitoring_areas(id,company_id,mine_id,name,geometry,area_hectares,monitoring_enabled,created_at,updated_at) VALUES(1,1,1,?, ?,1,1,?,?)')->execute(['Área',$area,$now,$now]);
    $db->prepare('INSERT INTO alerts(id,company_id,mine_id,monitoring_area_id,title,type,severity,status,created_at,updated_at) VALUES(1,1,1,1,?,?,?,?,?,?)')->execute(['Alerta','ALTERATION','HIGH','OPEN',$now,$now]);
    $db->prepare('INSERT INTO inspections(id,company_id,mine_id,alert_id,inspector_user_id,status,created_at,updated_at) VALUES(1,1,1,1,1,?,?,?)')->execute(['SCHEDULED',$now,$now]);

    $provider = new class implements SatelliteProviderInterface {
        public function searchImages(array $area, string $start, string $end, array $options = []): array { return []; }
        public function getMetadata(array $image): array { return []; }
        public function downloadImage(array $image): array { return []; }
        public function getPreview(array $image): string { return ''; }
        public function getBands(array $image): array { return []; }
    };
    $map = (new MonitoringService(new TenantRepository($db), $provider))->map(new Tenant(1, 1, 'ADMIN'));

    assertSame(1, count($map['alerts']['features']));
    assertSame(1, count($map['inspections']['features']));
    assertSame('Polygon', $map['alerts']['features'][0]['geometry']['type']);
    assertSame('Polygon', $map['inspections']['features'][0]['geometry']['type']);
});
