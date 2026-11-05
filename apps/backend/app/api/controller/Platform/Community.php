<?php
declare(strict_types=1);

namespace app\api\controller\Platform;

use app\api\controller\BaseController;
use app\modules\Platform\Social\Public\Social;
use app\modules\Platform\Library\Public\Library;
use app\modules\Platform\Notifications\Public\Notifications;
use app\modules\Platform\Safety\Public\Safety;
use app\shared\Resource\ResourceRef;
use app\modules\Platform\Drafts\Public\Drafts;
use app\modules\Platform\Messaging\Public\Messaging;
use app\modules\Platform\Eventing\Contract\RealtimeTicketIssuer;
use app\modules\Platform\User\Public\UidResolver;
use app\modules\Platform\Safety\Domain\ReportSubmission;
use app\modules\Content\Cards\Service\CardVisibilityPolicy;
use app\shared\Transport\Http\CursorPage;

final class Community extends BaseController
{
    private function uid(): int { return (int) request()->auth->uid(); }
    private function ref(array $params): ResourceRef { return new ResourceRef((string)($params['resource_type'] ?? 'card'), (string)($params['resource_id'] ?? '')); }
    private function boolValue($value, bool $default = false): bool
    {
        if ($value === null) return $default;
        if (is_bool($value)) return $value;
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
    private function optionalUid(): int
    {
        $request = request();
        return isset($request->auth) ? (int) $request->auth->uid() : 0;
    }

    public function follow(string $uid) { return $this->respond(['following' => Social::follow($this->uid(), UidResolver::id($uid))]); }
    public function profileByUid(string $uid) { $profile=Social::profileByUid($uid, $this->optionalUid()); if($profile===null) throw new \think\exception\HttpException(404, '用户不存在'); return $this->respond($profile); }
    public function profile(string $handle) { $profile=Social::profile($handle, $this->optionalUid()); if($profile===null) throw new \think\exception\HttpException(404, '用户不存在'); return $this->respond($profile); }
    public function unfollow(string $uid) { Social::unfollow($this->uid(), UidResolver::id($uid)); return $this->respond(); }
    public function followers(string $uid) { $p=$this->validated(); return $this->respond(Social::followers(UidResolver::id($uid), $this->uid(), $p['cursor'] ?? null, (int)($p['limit'] ?? 20))); }
    public function following(string $uid) { $p=$this->validated(); return $this->respond(Social::following(UidResolver::id($uid), $this->uid(), $p['cursor'] ?? null, (int)($p['limit'] ?? 20))); }
    public function relation(string $uid) { return $this->respond(Social::relation($this->uid(), UidResolver::id($uid))); }

    public function save() { $p=$this->validated(); $ref=$this->ref($p); if ($ref->type() === 'card') CardVisibilityPolicy::assertReadable((int) $ref->id(), $this->uid()); Library::save($this->uid(), $ref); return $this->respond(); }
    public function remove() { $p=$this->validated(); Library::remove($this->uid(), $this->ref($p)); return $this->respond(); }
    public function library() { $p=$this->validated(); return $this->respond(Library::saved($this->uid(), $p['cursor'] ?? null, (int)($p['limit'] ?? 20))); }
    public function recordHistory() { $p=$this->validated(); $ref=$this->ref($p); if ($ref->type() === 'card') CardVisibilityPolicy::assertReadable((int) $ref->id(), $this->uid()); Library::recordView($this->uid(), $ref); return $this->respond(); }
    public function history() { $p=$this->validated(); return $this->respond(Library::history($this->uid(), $p['cursor'] ?? null, (int)($p['limit'] ?? 20))); }
    public function clearHistory() { Library::clearHistory($this->uid()); return $this->respond(); }

    public function notifications() { $p=$this->validated(); return $this->respond(Notifications::list($this->uid(), $p['cursor'] ?? null, (int)($p['limit'] ?? 20), $this->boolValue($p['unread_only'] ?? null), $p['type'] ?? null, $p['category'] ?? null)); }
    public function unread() { $p=$this->validated(); return $this->respond(['unread' => Notifications::unreadCount($this->uid(), $p['category'] ?? null)]); }
    public function read() { $p=$this->validated(); $ids = $p['ids'] ?? []; if (is_string($ids)) $ids = json_decode($ids, true) ?: []; Notifications::markRead($this->uid(), array_map('intval', (array)$ids)); return $this->respond(); }
    public function readAll() { $p=$this->validated(); Notifications::markAllRead($this->uid(), $p['category'] ?? null); return $this->respond(); }
    public function notificationPreferences() { return $this->respond(Notifications::preferences($this->uid())); }
    public function updateNotificationPreferences() { return $this->respond(Notifications::updatePreferences($this->uid(), $this->validated())); }
    public function realtimeTicket() { return $this->respond(app(RealtimeTicketIssuer::class)->issue($this->uid())); }

    public function preferences() { return $this->respond(Safety::preferences($this->uid())->toArray()); }
    public function updatePreferences() { $p=$this->validated(); if (array_key_exists('show_relations', $p)) $p['show_relations'] = $this->boolValue($p['show_relations']); return $this->respond(Safety::savePreferences($this->uid(), $p, request()->ip())->toArray()); }
    public function blockedUsers() { $p=$this->validated(); return $this->respond(Safety::listBlocked($this->uid(), $p['cursor'] ?? null, (int)($p['limit'] ?? 20), $p['query'] ?? null)); }
    public function block(string $uid) { Safety::addRelation($this->uid(), UidResolver::id($uid), 'block'); return $this->respond(); }
    public function unblock(string $uid) { Safety::removeRelation($this->uid(), UidResolver::id($uid), 'block'); return $this->respond(); }
    public function drafts() { $p=$this->validated(); return $this->respond(Drafts::list($this->uid(), $p['cursor'] ?? null, (int)($p['limit'] ?? 20))); }
    public function draft(int $id) { return $this->respond(Drafts::find($this->uid(), $id)); }
    public function saveDraft(?int $id = null) { $p=$this->validated(); $payload = $p['payload'] ?? []; if (is_string($payload)) $payload = json_decode($payload, true) ?: []; $new=Drafts::save($this->uid(), $id, (string)($p['title'] ?? ''), (string)($p['content'] ?? ''), (array)$payload); return $this->respond(['id'=>$new]); }
    public function deleteDraft(int $id) { Drafts::delete($this->uid(), $id); return $this->respond(); }
    public function conversations() { return $this->respond(Messaging::conversations($this->uid())); }
    public function messages(int $id) { $p=$this->validated(); $page=Messaging::messages($this->uid(),$id,$p['cursor']??null,(int)($p['limit']??30)); return $this->respond(CursorPage::make($page['items'], $page['next_cursor'], $page['has_more'])); }
    public function sendMessage() { $p=$this->validated(); return $this->respond(Messaging::send($this->uid(),UidResolver::id((string)$p['recipient_uid']),(string)$p['body'],$p['client_message_id']??null)); }
    public function report() { $p=$this->validated(); return $this->respond(['id'=>Safety::report(new ReportSubmission($this->uid(), $this->ref($p), (string)$p['reason'], (string)($p['details'] ?? '')))]); }
}
