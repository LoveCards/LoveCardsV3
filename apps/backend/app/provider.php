<?php

use app\ExceptionHandle;
use app\modules\Platform\Auth\Contract\CapabilityProvider;
use app\modules\Platform\Auth\Contract\UserRepository;
use app\modules\Platform\Auth\Public\VisitorPolicy;
use app\modules\Platform\Files\Contract\FileRepository;
use app\modules\Platform\Files\Contract\StorageDriver;
use app\modules\Platform\Files\Contract\ChannelConfig;
use app\modules\Platform\Files\Contract\RateLimiter;
use app\modules\Platform\Auth\Infrastructure\ConfigVisitorPolicy;
use app\modules\Platform\Auth\Infrastructure\RbacCapabilityProvider;
use app\modules\Platform\Auth\Infrastructure\ThinkOrmUserRepository;
use app\modules\Platform\Files\Infrastructure\ThinkOrmFileRepository;
use app\modules\Platform\Files\Infrastructure\DefaultStorageDriver;
use app\modules\Platform\Files\Infrastructure\ConfigChannelConfig;
use app\modules\Platform\Files\Infrastructure\CacheRateLimiter;
use app\modules\Platform\Files\Contract\FileReferenceRepository;
use app\modules\Platform\Files\Infrastructure\ThinkDbFileReferenceRepository;
use app\modules\Platform\Files\Contract\FilePurgeRepository;
use app\modules\Platform\Files\Infrastructure\ThinkDbFilePurgeRepository;
use app\modules\Platform\Files\Contract\FilePurgeRequester;
use app\modules\Platform\Files\Application\RequestFilePurge;
use app\modules\Platform\Files\Contract\FilePurgePublisher;
use app\modules\Platform\Files\Infrastructure\OutboxFilePurgePublisher;
use app\modules\Platform\Auth\Contract\TokenService;
use app\modules\Platform\Auth\Infrastructure\JwtTokenService;
use app\modules\Platform\Auth\Contract\SessionRegistry;
use app\modules\Platform\Auth\Infrastructure\ThinkOrmSessionRegistry;
use app\modules\Platform\Auth\Contract\AdminSecurityRepository;
use app\modules\Platform\Auth\Infrastructure\ThinkDbAdminSecurityRepository;
use app\modules\Platform\Auth\Contract\RecoveryRepository;
use app\modules\Platform\Auth\Infrastructure\RecoveryStore;
use app\shared\Transaction\TransactionManager;
use app\shared\Infrastructure\Persistence\ThinkOrmTransactionManager;
use app\modules\Operations\Runtime\Contract\RuntimeConfigStore;
use app\modules\Operations\Runtime\Contract\SchedulerRunLedger;
use app\modules\Operations\Runtime\Infrastructure\ThinkOrmRuntimeConfigStore;
use app\modules\Operations\Runtime\Infrastructure\ThinkDbSchedulerRunLedger;
use app\modules\Platform\Social\Contract\SocialGraphRepository;
use app\modules\Platform\Social\Infrastructure\ThinkOrmSocialGraphRepository;
use app\modules\Platform\Library\Contract\LibraryRepository;
use app\modules\Platform\Library\Infrastructure\ThinkOrmLibraryRepository;
use app\modules\Platform\Safety\Contract\SafetyRepository;
use app\modules\Platform\Safety\Infrastructure\ThinkOrmSafetyRepository;
use app\modules\Platform\Safety\Contract\ReportTargetResolver;
use app\modules\Platform\Safety\Infrastructure\ThinkDbReportTargetResolver;
use app\modules\Platform\Safety\Contract\AdminReportRepository;
use app\modules\Platform\Safety\Infrastructure\ThinkDbAdminReportRepository;
use app\modules\Platform\Notifications\Contract\NotificationRepository;
use app\modules\Platform\Eventing\Contract\PolicyVersionSource;
use app\modules\Platform\Notifications\Contract\NotificationPolicyStore;
use app\modules\Platform\Notifications\Contract\RealtimePollReader;
use app\modules\Platform\Notifications\Contract\StreamTicketStore;
use app\modules\Platform\Notifications\Infrastructure\ThinkDbNotificationPolicyStore;
use app\modules\Platform\Notifications\Infrastructure\ThinkDbNotificationPolicyVersionSource;
use app\modules\Platform\Notifications\Infrastructure\ThinkDbRealtimePollReader;
use app\modules\Platform\Notifications\Infrastructure\ThinkDbStreamTicketStore;
use app\modules\Platform\Notifications\Contract\SystemNoticeIssuer;
use app\modules\Platform\Notifications\Infrastructure\ThinkOrmNotificationRepository;
use app\modules\Platform\Notifications\Public\SystemNotifications;
use app\modules\Platform\Messaging\Contract\MessagingRepository;
use app\modules\Platform\Messaging\Contract\MessagePermission;
use app\modules\Platform\Messaging\Contract\MessageNotifier;
use app\modules\Platform\Messaging\Infrastructure\ThinkDbMessagingRepository;
use app\modules\Platform\Messaging\Infrastructure\SafetyMessagePermission;
use app\modules\Platform\Messaging\Infrastructure\NotificationMessageNotifier;
use app\modules\Platform\Eventing\Contract\OutboxEventRepository;
use app\modules\Platform\Eventing\Infrastructure\ThinkDbOutboxEventRepository;
use app\modules\Platform\Eventing\Contract\DeliveryRepository;
use app\modules\Platform\Eventing\Infrastructure\ThinkDbDeliveryRepository;
use app\modules\Operations\SystemLifecycle\Contract\MigrationLedger;
use app\modules\Operations\SystemLifecycle\Infrastructure\ThinkDbMigrationLedger;
use app\modules\Operations\SystemLifecycle\Contract\MigrationSource;
use app\modules\Operations\SystemLifecycle\Infrastructure\MigrationCatalog;
use app\modules\Operations\SystemLifecycle\Contract\UpgradeLease;
use app\modules\Operations\SystemLifecycle\Infrastructure\ThinkDbUpgradeLease;
use app\modules\Operations\SystemLifecycle\Contract\UpgradePlanRepository;
use app\modules\Operations\SystemLifecycle\Infrastructure\ThinkDbUpgradePlanRepository;
use app\modules\Operations\SystemLifecycle\Contract\SystemBackupRepository;
use app\modules\Operations\SystemLifecycle\Infrastructure\ThinkDbSystemBackupRepository;
use app\modules\Operations\SystemLifecycle\Contract\BackupArtifactStore;
use app\modules\Operations\SystemLifecycle\Infrastructure\LocalBackupArtifactStore;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseSignatureVerifier;
use app\modules\Operations\SystemLifecycle\Infrastructure\OpenSslReleaseSignatureVerifier;
use app\modules\Operations\SystemLifecycle\Contract\ReleasePackageImporter;
use app\modules\Operations\SystemLifecycle\Infrastructure\SecureReleaseImporter;
use app\modules\Operations\SystemLifecycle\Contract\ReleasePackageStore;
use app\modules\Operations\SystemLifecycle\Infrastructure\LocalReleasePackageStore;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseRepository;
use app\modules\Operations\SystemLifecycle\Infrastructure\ThinkDbReleaseRepository;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseCompatibility;
use app\modules\Operations\SystemLifecycle\Application\ReleaseCompatibilityValidator;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseEnvironment;
use app\modules\Operations\SystemLifecycle\Infrastructure\NativeReleaseEnvironment;
use app\modules\Operations\SystemLifecycle\Contract\ReleasePointer;
use app\modules\Operations\SystemLifecycle\Application\RecoveryKernel;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseRuntime;
use app\modules\Operations\SystemLifecycle\Infrastructure\NativeReleaseRuntime;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseMigrationExecutor;
use app\modules\Operations\SystemLifecycle\Infrastructure\SystemLifecycleMigrationExecutor;
use app\modules\Operations\SystemLifecycle\Contract\UpgradeBackupGate;
use app\modules\Operations\SystemLifecycle\Application\PrepareUpgradeBackups;
use app\modules\Operations\SystemLifecycle\Application\UpgradeCoordinator;
use app\modules\Operations\SystemLifecycle\Infrastructure\SystemPathsFactory;
use app\shared\SystemPaths\SystemPaths;
use app\modules\Platform\Drafts\Contract\DraftRepository;
use app\modules\Platform\Drafts\Infrastructure\ThinkOrmDraftRepository;
use app\modules\Platform\Eventing\Contract\Clock;
use app\modules\Platform\Eventing\Infrastructure\SystemClock;
use app\modules\Platform\Eventing\Contract\ClaimTokenGenerator;
use app\modules\Platform\Eventing\Infrastructure\SecureClaimTokenGenerator;
use app\modules\Platform\Eventing\Contract\Sleeper;
use app\modules\Platform\Eventing\Infrastructure\NativeSleeper;
use app\modules\Operations\Runtime\Public\WorkerHeartbeatStore;
use app\modules\Operations\Runtime\Infrastructure\ThinkDbWorkerHeartbeatStore;
use app\modules\Platform\Eventing\Contract\FailureSanitizer;
use app\modules\Platform\Eventing\Infrastructure\ConservativeFailureSanitizer;
use app\modules\Platform\Eventing\Contract\WakeupTransport;
use app\modules\Platform\Eventing\Infrastructure\NullWakeupTransport;
use app\modules\Platform\Eventing\Infrastructure\OptionalRedisWakeupTransport;
use app\modules\Platform\Eventing\Infrastructure\RedisRealtimeSignalPublisher;
use app\modules\Platform\Eventing\Public\RealtimeSignalPublisher;
use app\shared\Runtime\FacilityProbe;
use app\shared\Runtime\NativeFacilityProbe;
use app\shared\Observability\AuditLogger;
use app\shared\Observability\ThinkDbAuditLogger;
use app\shared\Observability\LogContextScope;
use app\shared\Observability\LogContextProvider;

// 容器Provider定义文件
return [
    'think\exception\Handle' => ExceptionHandle::class,
    CapabilityProvider::class => RbacCapabilityProvider::class,
    UserRepository::class => ThinkOrmUserRepository::class,
    VisitorPolicy::class => ConfigVisitorPolicy::class,
    TokenService::class => JwtTokenService::class,
    SessionRegistry::class => ThinkOrmSessionRegistry::class,
    AdminSecurityRepository::class => ThinkDbAdminSecurityRepository::class,
    RecoveryRepository::class => RecoveryStore::class,
    FileRepository::class => ThinkOrmFileRepository::class,
    StorageDriver::class => DefaultStorageDriver::class,
    ChannelConfig::class => ConfigChannelConfig::class,
    RateLimiter::class => CacheRateLimiter::class,
    FileReferenceRepository::class => ThinkDbFileReferenceRepository::class,
    FilePurgeRepository::class => ThinkDbFilePurgeRepository::class,
    FilePurgeRequester::class => RequestFilePurge::class,
    FilePurgePublisher::class => OutboxFilePurgePublisher::class,
    TransactionManager::class => ThinkOrmTransactionManager::class,
    SocialGraphRepository::class => ThinkOrmSocialGraphRepository::class,
    LibraryRepository::class => ThinkOrmLibraryRepository::class,
    SafetyRepository::class => ThinkOrmSafetyRepository::class,
    ReportTargetResolver::class => ThinkDbReportTargetResolver::class,
    AdminReportRepository::class => ThinkDbAdminReportRepository::class,
    NotificationRepository::class => ThinkOrmNotificationRepository::class,
    SystemNoticeIssuer::class => SystemNotifications::class,
    MessagingRepository::class => ThinkDbMessagingRepository::class,
    MessagePermission::class => SafetyMessagePermission::class,
    MessageNotifier::class => NotificationMessageNotifier::class,
    OutboxEventRepository::class => ThinkDbOutboxEventRepository::class,
    DeliveryRepository::class => ThinkDbDeliveryRepository::class,
    MigrationLedger::class => ThinkDbMigrationLedger::class,
    MigrationSource::class => MigrationCatalog::class,
    UpgradeLease::class => ThinkDbUpgradeLease::class,
    UpgradePlanRepository::class => ThinkDbUpgradePlanRepository::class,
    SystemBackupRepository::class => ThinkDbSystemBackupRepository::class,
    BackupArtifactStore::class => LocalBackupArtifactStore::class,
    SystemPaths::class => static fn(): SystemPaths => SystemPathsFactory::create(),
    ReleaseSignatureVerifier::class => OpenSslReleaseSignatureVerifier::class,
    ReleasePackageImporter::class => SecureReleaseImporter::class,
    ReleasePackageStore::class => LocalReleasePackageStore::class,
    ReleaseRepository::class => ThinkDbReleaseRepository::class,
    ReleaseEnvironment::class => NativeReleaseEnvironment::class,
    ReleaseCompatibility::class => ReleaseCompatibilityValidator::class,
    ReleasePointer::class => RecoveryKernel::class,
    ReleaseRuntime::class => NativeReleaseRuntime::class,
    ReleaseMigrationExecutor::class => SystemLifecycleMigrationExecutor::class,
    UpgradeBackupGate::class => PrepareUpgradeBackups::class,
    UpgradeCoordinator::class => UpgradeCoordinator::class,
    DraftRepository::class => ThinkOrmDraftRepository::class,
    Clock::class => SystemClock::class,
    ClaimTokenGenerator::class => SecureClaimTokenGenerator::class,
    Sleeper::class => NativeSleeper::class,
    WorkerHeartbeatStore::class => ThinkDbWorkerHeartbeatStore::class,
    FailureSanitizer::class => ConservativeFailureSanitizer::class,
    WakeupTransport::class => OptionalRedisWakeupTransport::class,
    RealtimeSignalPublisher::class => RedisRealtimeSignalPublisher::class,
    // 通信运行时（Phase 2）：一次性 stream ticket 与 Gateway 回源读取
    StreamTicketStore::class => ThinkDbStreamTicketStore::class,
    RealtimePollReader::class => ThinkDbRealtimePollReader::class,
    // 通信运行时（Phase 3）：通知策略目录与版本冻结
    NotificationPolicyStore::class => ThinkDbNotificationPolicyStore::class,
    PolicyVersionSource::class => ThinkDbNotificationPolicyVersionSource::class,
    FacilityProbe::class => NativeFacilityProbe::class,
    AuditLogger::class => ThinkDbAuditLogger::class,
    LogContextScope::class => LogContextScope::class,
    LogContextProvider::class => LogContextScope::class,
    // 通信运行时控制面（Phase 1）
    RuntimeConfigStore::class => ThinkOrmRuntimeConfigStore::class,
    SchedulerRunLedger::class => ThinkDbSchedulerRunLedger::class,
];
