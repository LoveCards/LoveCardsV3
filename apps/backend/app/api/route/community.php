<?php
use think\facade\Route;
use app\api\middleware\JwtAuthCheck;
use app\api\middleware\OptionalJwtAuth;
use app\shared\Transport\Http\ApiResource;
use app\modules\Platform\Library\Transport\Api\Library as LibraryValidate;
use app\modules\Platform\Community\Transport\Api\Community as CommunityValidate;
use app\modules\Platform\Notifications\Transport\Api\NotificationCursorPageResource;
use app\modules\Platform\Notifications\Transport\Api\NotificationUnreadResource;
use app\modules\Operations\Runtime\Transport\Api\CommunicationSettingsResource;
use app\modules\Platform\Notifications\Transport\Api\StreamTicketResource;
use app\shared\Transport\Http\CursorPageResource;
use app\modules\Platform\Safety\Transport\Api\PrivacyPreferencesResource;
use app\modules\Platform\Safety\Transport\Api\BlockedUserCursorPageResource;
use app\modules\Platform\User\Transport\Api\PublicUserProfileResource;
use app\modules\Platform\Notifications\Transport\Api\NotificationPreferencesResource;
use app\modules\Platform\Social\Transport\Api\SocialRelationResource;

$auth = ['auth' => 'required'];
$item = ['resource' => ApiResource::class, 'shape' => 'item', 'status' => 200];
$collection = ['resource' => ApiResource::class, 'shape' => 'collection', 'status' => 200];
$cursorPage = ['resource' => CursorPageResource::class, 'shape' => 'item', 'status' => 200];
$notificationCursorPage = ['resource' => NotificationCursorPageResource::class, 'shape' => 'item', 'status' => 200];
$void = ['resource' => null, 'shape' => 'void', 'status' => 204];
$get = static function (string $path, string $handler, string $name, array $response, array $meta = []) use ($auth): void {
    Route::get($path, $handler)->name($name)->setOption('contract', ['request' => null, 'response' => $response])->setOption('meta', ['name' => $name, 'group' => '社区', 'access' => $auth + $meta])->middleware(JwtAuthCheck::class);
};
$post = static function (string $path, string $handler, string $name, array $response, array $meta = []) use ($auth): void {
    Route::post($path, $handler)->name($name)->setOption('contract', ['request' => null, 'response' => $response])->setOption('meta', ['name' => $name, 'group' => '社区', 'access' => $auth + $meta])->middleware(JwtAuthCheck::class);
};

$post('users/:uid/follow', 'Platform.Community/follow', 'social.follow', $item);
Route::get('users/:uid/profile', 'Platform.Community/profileByUid')->name('social.profile')->setOption('contract',['request'=>null,'response'=>['resource'=>PublicUserProfileResource::class,'shape'=>'item','status'=>200]])->setOption('meta',['name'=>'公开用户主页','group'=>'社区','access'=>['auth'=>'public']])->middleware(OptionalJwtAuth::class);
Route::get('users/by-handle/:handle', 'Platform.Community/profile')
    ->name('social.profileByHandle')
    // User handles accept the same Unicode/chDash values as registration;
    // the default route pattern (\\w/.) is too narrow for those handles.
    ->pattern(['handle' => '[^/]+'])
    ->setOption('contract',['request'=>null,'response'=>['resource'=>PublicUserProfileResource::class,'shape'=>'item','status'=>200]])
    ->setOption('meta',['name'=>'公开用户主页','group'=>'社区','access'=>['auth'=>'public']])
    ->middleware(OptionalJwtAuth::class);
Route::delete('users/:uid/follow', 'Platform.Community/unfollow')->name('social.unfollow')->setOption('contract', ['request'=>null,'response'=>$void])->setOption('meta', ['name'=>'social.unfollow','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
// Cursor-based community lists expose their query contract explicitly.
Route::get('users/:uid/followers', 'Platform.Community/followers')->name('social.followers')->setOption('contract',['request'=>['validate'=>CommunityValidate::class,'scene'=>'cursor'],'response'=>$cursorPage])->setOption('meta',['name'=>'social.followers','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::get('users/:uid/following', 'Platform.Community/following')->name('social.following')->setOption('contract',['request'=>['validate'=>CommunityValidate::class,'scene'=>'cursor'],'response'=>$cursorPage])->setOption('meta',['name'=>'social.following','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
$get('users/:uid/relation', 'Platform.Community/relation', 'social.relation', ['resource'=>SocialRelationResource::class,'shape'=>'item','status'=>200]);

Route::post('me/library', 'Platform.Community/save')->name('library.save')->setOption('contract',['request'=>['validate'=>LibraryValidate::class,'scene'=>'resource'],'response'=>$void])->setOption('meta',['name'=>'library.save','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::delete('me/library', 'Platform.Community/remove')->name('library.remove')->setOption('contract',['request'=>['validate'=>LibraryValidate::class,'scene'=>'resource'],'response'=>$void])->setOption('meta',['name'=>'library.remove','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::get('me/library', 'Platform.Community/library')->name('library.list')->setOption('contract',['request'=>['validate'=>CommunityValidate::class,'scene'=>'cursor'],'response'=>$cursorPage])->setOption('meta',['name'=>'library.list','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::post('me/history', 'Platform.Community/recordHistory')->name('library.history.record')->setOption('contract',['request'=>['validate'=>LibraryValidate::class,'scene'=>'resource'],'response'=>$void])->setOption('meta',['name'=>'library.history.record','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::get('me/history', 'Platform.Community/history')->name('library.history')->setOption('contract',['request'=>['validate'=>CommunityValidate::class,'scene'=>'cursor'],'response'=>$cursorPage])->setOption('meta',['name'=>'library.history','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::delete('me/history', 'Platform.Community/clearHistory')->name('library.history.clear')->setOption('contract',['request'=>null,'response'=>$void])->setOption('meta',['name'=>'library.history.clear','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);

Route::get('me/notifications', 'Platform.Community/notifications')->name('notifications.list')->setOption('contract',['request'=>['validate'=>CommunityValidate::class,'scene'=>'notifications'],'response'=>$notificationCursorPage])->setOption('meta',['name'=>'notifications.list','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::get('me/notifications/unread', 'Platform.Community/unread')->name('notifications.unread')->setOption('contract',['request'=>['validate'=>CommunityValidate::class,'scene'=>'notificationUnread'],'response'=>['resource'=>NotificationUnreadResource::class,'shape'=>'item','status'=>200]])->setOption('meta',['name'=>'notifications.unread','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::get('me/communication/settings', 'Platform.Community/communicationSettings')->name('communication.settings')->setOption('contract',['request'=>null,'response'=>['resource'=>CommunicationSettingsResource::class,'shape'=>'item','status'=>200]])->setOption('meta',['name'=>'通信节奏快照','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::post('me/notifications/stream-ticket', 'Platform.Community/streamTicket')->name('notifications.streamTicket')->setOption('contract',['request'=>null,'response'=>['resource'=>StreamTicketResource::class,'shape'=>'item','status'=>200]])->setOption('meta',['name'=>'通知实时流凭证','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::post('me/notifications/read', 'Platform.Community/read')->name('notifications.read')->setOption('contract',['request'=>['validate'=>CommunityValidate::class,'scene'=>'read'],'response'=>$void])->setOption('meta',['name'=>'notifications.read','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::post('me/notifications/read-all', 'Platform.Community/readAll')->name('notifications.readAll')->setOption('contract',['request'=>['validate'=>CommunityValidate::class,'scene'=>'notificationReadAll'],'response'=>$void])->setOption('meta',['name'=>'notifications.readAll','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::get('me/notifications/preferences', 'Platform.Community/notificationPreferences')->name('notifications.preferences')->setOption('contract',['request'=>null,'response'=>['resource'=>NotificationPreferencesResource::class,'shape'=>'item','status'=>200]])->setOption('meta',['name'=>'notifications.preferences','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::post('me/notifications/preferences', 'Platform.Community/updateNotificationPreferences')->name('notifications.updatePreferences')->setOption('contract',['request'=>['validate'=>CommunityValidate::class,'scene'=>'notificationPreferences'],'response'=>['resource'=>NotificationPreferencesResource::class,'shape'=>'item','status'=>200]])->setOption('meta',['name'=>'notifications.updatePreferences','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
$get('me/privacy', 'Platform.Community/preferences', 'safety.preferences', ['resource'=>PrivacyPreferencesResource::class,'shape'=>'item','status'=>200]);
Route::post('me/privacy', 'Platform.Community/updatePreferences')->name('safety.preferences.update')->setOption('contract',['request'=>['validate'=>CommunityValidate::class,'scene'=>'preferences'],'response'=>['resource'=>PrivacyPreferencesResource::class,'shape'=>'item','status'=>200]])->setOption('meta',['name'=>'safety.preferences.update','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::get('me/blocked-users', 'Platform.Community/blockedUsers')->name('safety.listBlocked')->setOption('contract',['request'=>['validate'=>CommunityValidate::class,'scene'=>'blockedUsers'],'response'=>['resource'=>BlockedUserCursorPageResource::class,'shape'=>'item','status'=>200]])->setOption('meta',['name'=>'safety.listBlocked','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
$post('users/:uid/block', 'Platform.Community/block', 'safety.block', $void);
Route::post('reports', 'Platform.Community/report')->name('safety.report')->setOption('contract',['request'=>['validate'=>CommunityValidate::class,'scene'=>'report'],'response'=>$item])->setOption('meta',['name'=>'safety.report','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::delete('users/:uid/block', 'Platform.Community/unblock')->name('safety.unblock')->setOption('contract',['request'=>null,'response'=>$void])->setOption('meta',['name'=>'safety.unblock','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);

Route::get('me/drafts', 'Platform.Community/drafts')->name('drafts.list')->setOption('contract',['request'=>['validate'=>CommunityValidate::class,'scene'=>'cursor'],'response'=>$collection])->setOption('meta',['name'=>'drafts.list','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
$get('me/drafts/:id', 'Platform.Community/draft', 'drafts.get', $item);
Route::post('me/drafts', 'Platform.Community/saveDraft')->name('drafts.create')->setOption('contract',['request'=>['validate'=>CommunityValidate::class,'scene'=>'draft'],'response'=>$item])->setOption('meta',['name'=>'drafts.create','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::patch('me/drafts/:id', 'Platform.Community/saveDraft')->name('drafts.update')->setOption('contract',['request'=>['validate'=>CommunityValidate::class,'scene'=>'draft'],'response'=>$item])->setOption('meta',['name'=>'drafts.update','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::delete('me/drafts/:id', 'Platform.Community/deleteDraft')->name('drafts.delete')->setOption('contract',['request'=>null,'response'=>$void])->setOption('meta',['name'=>'drafts.delete','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
$get('me/conversations', 'Platform.Community/conversations', 'messages.conversations', $collection);
Route::get('me/conversations/:id/messages', 'Platform.Community/messages')->name('messages.list')->setOption('contract',['request'=>['validate'=>CommunityValidate::class,'scene'=>'cursor'],'response'=>$cursorPage])->setOption('meta',['name'=>'messages.list','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
Route::post('me/messages', 'Platform.Community/sendMessage')->name('messages.send')->setOption('contract',['request'=>['validate'=>CommunityValidate::class,'scene'=>'messageSend'],'response'=>$item])->setOption('meta',['name'=>'messages.send','group'=>'社区','access'=>$auth])->middleware(JwtAuthCheck::class);
