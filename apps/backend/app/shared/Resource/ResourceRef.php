<?php

namespace app\shared\Resource;

/** 跨模块资源引用；只承载稳定类型与公开标识，不包含业务查询逻辑。 */
final class ResourceRef
{
    private string $type;
    private string $id;

    public function __construct(string $type, int|string $id)
    {
        $type = strtolower(trim($type));
        $id = trim((string) $id);

        if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/', $type)) {
            throw new \InvalidArgumentException('Invalid resource type');
        }
        if ($id === '' || strlen($id) > 128 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/', $id)) {
            throw new \InvalidArgumentException('Invalid resource id');
        }

        $this->type = $type;
        $this->id = $id;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function key(): string
    {
        return $this->type . ':' . $this->id;
    }

    /** @return array{type: string, id: string} */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }
}
