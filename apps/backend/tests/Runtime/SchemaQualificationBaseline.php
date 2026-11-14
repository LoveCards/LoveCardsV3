<?php
declare(strict_types=1);

require __DIR__ . '/SchemaIdentityBaseline.php';

use app\modules\Operations\SystemLifecycle\Contract\UpgradePlanRepository;
use app\modules\Operations\SystemLifecycle\Contract\ReleasePointer;
use app\modules\Operations\SystemLifecycle\Domain\StoredUpgradePlan;
use app\modules\Operations\SystemLifecycle\Domain\UpgradePlan;
use app\modules\Operations\SystemLifecycle\Domain\UpgradeStep;
use app\modules\Operations\SystemLifecycle\Domain\MigrationProgress;
use app\modules\Operations\SystemLifecycle\Infrastructure\MigrationCatalog;
use app\modules\Operations\SystemLifecycle\Public\SchemaIdentity;

final class QualificationPlans implements UpgradePlanRepository
{
    public ?StoredUpgradePlan $active = null;
    public function unsettled(bool $lock=false): ?StoredUpgradePlan { return $this->active; }
    public function create(StoredUpgradePlan $plan): StoredUpgradePlan { return $this->active=$plan; }
    public function find(string $planId): ?StoredUpgradePlan { return $this->active; }
    public function recent(int $limit=50): array { return $this->active ? [$this->active] : []; }
    public function existsUnsettled(?string $excludingPlanId=null): bool { return $this->active !== null; }
    public function save(StoredUpgradePlan $plan, int $expectedRevision): StoredUpgradePlan { return $this->active=$plan; }
}
final class QualificationPointer implements ReleasePointer
{
    public ?string $active = 'release-target';
    public function activeReleaseId(): ?string { return $this->active; }
    public function activate(string $releaseId): void { $this->active=$releaseId; }
    public function backup(string $backupId): array { return []; }
    public function restore(string $backupPath, string $expectedChecksum): ?string { return $this->active; }
    public function recoveryState(): array { return []; }
}

$dir = sys_get_temp_dir() . '/lc-qualification-' . bin2hex(random_bytes(5));
mkdir($dir);
$path=$dir.'/20991230000001.sql';
file_put_contents($path, "-- phase: contract\nDROP TABLE fixture_obsolete;\n");
try {
    $source=new MigrationCatalog(); $ledger=new SchemaIdentityFixtureLedger();
    $descriptor=$source->discover($dir)[0]->descriptor();
    $waiting=MigrationProgress::restore(['id'=>$descriptor->id(),'checksum'=>$descriptor->checksum(),'phase'=>'contract','status'=>'waiting','revision'=>4,'attempts'=>3]);
    $ledger->rows=[$descriptor->id()=>$waiting];
    $plans=new QualificationPlans(); $pointer=new QualificationPointer();
    $identity=new SchemaIdentity($source,$ledger,$plans,$pointer);
    $baseContext=['plan_version'=>2,'migrations_verified_at'=>gmdate('c'),'target_migration_checksums'=>[$descriptor->id()=>$descriptor->checksum()]];
    $at=static function(string $step) use ($baseContext): StoredUpgradePlan {
        $plan=UpgradePlan::create('qualification','release-target');
        while ($plan->current()->name()!==$step) { $name=$plan->current()->name(); $plan=$plan->start($name)->succeed($name); }
        return new StoredUpgradePlan($plan,'release-old',false,$baseContext);
    };
    foreach ([UpgradeStep::RELOAD_RUNTIME,UpgradeStep::HEALTH_CHECK,UpgradeStep::CONTRACT_MIGRATIONS] as $step) {
        $plans->active=$at($step);
        $assert(strlen($identity->runtimeForDirectory($dir,'release-target'))===64,'verified pre-contract target may start: '.$step);
    }
    $plans->active=$at(UpgradeStep::HEALTH_CHECK);
    $plans->active=$plans->active->withPlan($plans->active->plan()->start(UpgradeStep::HEALTH_CHECK)->fail(UpgradeStep::HEALTH_CHECK,'gateway-down'));
    $assert(strlen($identity->runtimeForDirectory($dir,'release-target'))===64,'health failure retains bounded target repair qualification');
    $rejects(fn()=>$identity->runtimeForDirectory($dir,'release-old'),'old consumer must lose permission');
    $rejects(fn()=>$identity->forDirectoryReadOnly($dir),'ordinary identity must remain complete-only');
    $pointer->active='release-old';
    $rejects(fn()=>$identity->runtimeForDirectory($dir,'release-target'),'restored pointer revokes target qualification');
    $pointer->active='release-target';
    $plans->active=$at(UpgradeStep::CONTRACT_MIGRATIONS);
    $plans->active=$plans->active->withPlan($plans->active->plan()->start(UpgradeStep::CONTRACT_MIGRATIONS));
    $rejects(fn()=>$identity->runtimeForDirectory($dir,'release-target'),'contract running pauses claims');
    $plans->active=$at(UpgradeStep::HEALTH_CHECK)->withContext($baseContext+['pointer_restored_at'=>'now']);
    $rejects(fn()=>$identity->runtimeForDirectory($dir,'release-target'),'pointer restoration revokes staged qualification');
    $plans->active=$at(UpgradeStep::HEALTH_CHECK)->withContext(array_replace($baseContext,['plan_version'=>1]));
    $rejects(fn()=>$identity->runtimeForDirectory($dir,'release-target'),'legacy plan cannot silently gain phase qualification');
    $plans->active=$at(UpgradeStep::HEALTH_CHECK)->withContext(array_replace($baseContext,['target_migration_checksums'=>[]]));
    $rejects(fn()=>$identity->runtimeForDirectory($dir,'release-target'),'plan checksum mismatch revokes qualification');
    $plans->active=$at(UpgradeStep::HEALTH_CHECK);
    $ledger->rows[$descriptor->id()]=$waiting->started()->failed('sql-failed');
    $rejects(fn()=>$identity->runtimeForDirectory($dir,'release-target'),'failed migration cannot receive qualification');
    $ledger->rows[$descriptor->id()]=$waiting->started()->completed();
    $plans->active=$at(UpgradeStep::FINAL_HEALTH);
    $assert(strlen($identity->runtimeForDirectory($dir,'release-target'))===64,'complete target runs for final health');
    echo "PASS phase-qualified schema identity baseline\n";
} finally { unlink($path); rmdir($dir); }
