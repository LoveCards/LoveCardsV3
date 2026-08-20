<?php
declare(strict_types=1);

namespace app\api\controller\Platform;

use app\api\controller\BaseController;
use app\modules\Platform\Social\Public\Social;
use app\modules\Platform\Library\Public\Library;
use app\modules\Platform\Notifications\Public\Notifications;
use app\modules\Platform\Safety\Public\Safety;
use app\modules\Platform\Safety\Domain\PrivacyPreferences;
use app\shared\Resource\ResourceRef;
use app\modules\Platform\Drafts\Public\Drafts;
use app\modules\Platform\Messaging\Public\Messaging;
use app\modules\Platform\Safety\Domain\ReportSubmission;
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

    public function follow(int $id) { return $this->respond(['following' => Social::follow($this->uid(), $id)]); }
    public function profile(string $handle) { $profile=Social::profile($handle); if($profile===null) throw new \think\exception\HttpException(404, '用户不存在'); return $this->respond($profile); }
    public function unfollow(int $id) { Social::unfollow($this->uid(), $id); return $this->respond(); }
    public function followers(int $id) { $p=$this->validated(); return $this->respond(Social::followers($id, $p['cursor'] ?? null, (int)($p['limit'] ?? 20))); }
    public function following(int $id) { $p=$this->validated(); return $this->respond(Social::following($id, $p['cursor'] ?? null, (int)($p['limit'] ?? 20))); }
    public function relation(int $id) { return $this->respond(['following' => Social::isFollowing($this->uid(), $id), 'counts' => Social::counts($id)]); }

    public function save() { $p=$this->validated(); Library::save($this->uid(), $this->ref($p)); return $this->respond(); }
    public function remove() { $p=$this->validated(); Library::remove($this->uid(), $this->ref($p)); return $this->respond(); }
    public function library() { $p=$this->validated(); return $this->respond(Library::saved($this->uid(), $p['cursor'] ?? null, (int)($p['limit'] ?? 20))); }
    public function recordHistory() { $p=$this->validated(); Library::recordView($this->uid(), $this->ref($p)); return $this->respond(); }
    public function history() { $p=$this->validated(); return $this->respond(Library::history($this->uid(), $p['cursor'] ?? null, (int)($p['limit'] ?? 20))); }
    public function clearHistory() { Library::clearHistory($this->uid()); return $this->respond(); }

    public function notifications() { $p=$this->validated(); return $this->respond(Notifications::list($this->uid(), $p['cursor'] ?? null, (int)($p['limit'] ?? 20), $this->boolValue($p['unread_only'] ?? null))); }
    public function unread() { return $this->respond(['unread' => Notifications::unreadCount($this->uid())]); }
    public function read() { $p=$this->validated(); $ids = $p['ids'] ?? []; if (is_string($ids)) $ids = json_decode($ids, true) ?: []; Notifications::markRead($this->uid(), array_map('intval', (array)$ids)); return $this->respond(); }
    public function readAll() { Notifications::markAllRead($this->uid()); return $this->respond(); }

    public function preferences() { return $this->respond(Safety::preferences($this->uid())->toArray()); }
    public function updatePreferences() { $p=$this->validated(); Safety::savePreferences($this->uid(), new PrivacyPreferences((string)($p['profile_visibility'] ?? 'public'), (string)($p['messages_from'] ?? 'everyone'), $this->boolValue($p['show_relations'] ?? null, true))); return $this->respond(); }
    public function block(int $id) { Safety::addRelation($this->uid(), $id, 'block'); return $this->respond(); }
    public function unblock(int $id) { Safety::removeRelation($this->uid(), $id, 'block'); return $this->respond(); }
    public function drafts() { $p=$this->validated(); return $this->respond(Drafts::list($this->uid(), $p['cursor'] ?? null, (int)($p['limit'] ?? 20))); }
    public function draft(int $id) { return $this->respond(Drafts::find($this->uid(), $id)); }
    public function saveDraft(?int $id = null) { $p=$this->validated(); $payload = $p['payload'] ?? []; if (is_string($payload)) $payload = json_decode($payload, true) ?: []; $new=Drafts::save($this->uid(), $id, (string)($p['title'] ?? ''), (string)($p['content'] ?? ''), (array)$payload); return $this->respond(['id'=>$new]); }
    public function deleteDraft(int $id) { Drafts::delete($this->uid(), $id); return $this->respond(); }
    public function conversations() { return $this->respond(Messaging::conversations($this->uid())); }
    public function messages(int $id) { $p=$this->validated(); $page=Messaging::messages($this->uid(),$id,$p['cursor']??null,(int)($p['limit']??30)); return $this->respond(CursorPage::make($page['items'], $page['next_cursor'], $page['has_more'])); }
    public function sendMessage() { $p=$this->validated(); return $this->respond(['id'=>Messaging::send($this->uid(),(int)$p['recipient_id'],(string)$p['body'],$p['client_message_id']??null)]); }
    public function report() { $p=$this->validated(); return $this->respond(['id'=>Safety::report(new ReportSubmission($this->uid(), $this->ref($p), (string)$p['reason'], (string)($p['details'] ?? '')))]); }
}
