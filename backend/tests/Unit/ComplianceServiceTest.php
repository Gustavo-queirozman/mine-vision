<?php
declare(strict_types=1);

use App\Application\Services\ComplianceService;
use App\Domain\Tenant;
use App\Infrastructure\Repositories\TenantRepository;

test('risk context prioritizes an official territorial overlap and discloses the development method', function (): void {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    foreach (['001_sqlite.sql', '002_sqlite.sql', '003_sqlite.sql', '004_sqlite.sql'] as $migration) {
        $db->exec(file_get_contents(dirname(__DIR__, 2).'/database/migrations/'.$migration));
    }
    $now = gmdate('c');
    $db->exec("INSERT INTO companies(id,name,email,created_at,updated_at) VALUES(1,'Teste','teste@minevision.local','$now','$now')");
    $db->exec("INSERT INTO users(id,company_id,name,email,password_hash,role,active,created_at,updated_at) VALUES(1,1,'Admin','admin@minevision.local','x','ADMIN',1,'$now','$now')");
    $db->exec("INSERT INTO mines(id,company_id,name,status,latitude,longitude,municipality,state,geometry,created_at,updated_at) VALUES(1,1,'Mina','ACTIVE',-15,-47,'X','MG','{}','$now','$now')");
    $area = json_encode(['type'=>'Polygon','coordinates'=>[[[-47.01,-15.01],[-46.99,-15.01],[-46.99,-14.99],[-47.01,-14.99],[-47.01,-15.01]]]]);
    $layer = json_encode(['type'=>'Polygon','coordinates'=>[[[-47.005,-15.005],[-46.995,-15.005],[-46.995,-14.995],[-47.005,-14.995],[-47.005,-15.005]]]]);
    $db->prepare('INSERT INTO monitoring_areas(company_id,mine_id,name,geometry,area_hectares,monitoring_enabled,created_at,updated_at) VALUES(1,1,?, ?,1,1,?,?)')->execute(['Área',$area,$now,$now]);
    $db->prepare('INSERT INTO compliance_layers(company_id,name,source,category,external_id,geometry,created_at,updated_at) VALUES(1,?,?,?,?,?,?,?)')->execute(['UC teste','ICMBIO','PROTECTED_AREA','UC-1',$layer,$now,$now]);
    $context = (new ComplianceService(new TenantRepository($db)))->contextForArea(1, new Tenant(1,1,'ANALYST'));
    assertSame('CRITICAL', $context['priority']);
    assertSame('BBOX_DEVELOPMENT_ONLY', $context['method']);
    assertSame('UC teste', $context['matches'][0]['name']);
    assertTrue($context['matches'][0]['overlap_hectares'] > 0);
});
