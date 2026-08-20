<?php

namespace app\modules\Platform\Safety\Domain;

final class PrivacyPreferences
{
    private const PROFILE = ['public', 'followers', 'private'];
    private const MESSAGES = ['everyone', 'following', 'none'];

    public function __construct(
        private string $profileVisibility = 'public',
        private string $messagesFrom = 'everyone',
        private bool $showRelations = true
    ) {
        if (!in_array($profileVisibility, self::PROFILE, true)) {
            throw new \InvalidArgumentException('主页可见范围无效');
        }
        if (!in_array($messagesFrom, self::MESSAGES, true)) {
            throw new \InvalidArgumentException('私信接收范围无效');
        }
    }

    /** @return array{profile_visibility: string, messages_from: string, show_relations: bool} */
    public function toArray(): array
    {
        return [
            'profile_visibility' => $this->profileVisibility,
            'messages_from' => $this->messagesFrom,
            'show_relations' => $this->showRelations,
        ];
    }
}
