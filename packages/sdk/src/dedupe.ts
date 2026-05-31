export class Deduplicator {
  private _pending = new Map<string, Promise<any>>()

  execute<T>(key: string, factory: () => Promise<T>): Promise<T> {
    if (this._pending.has(key)) return this._pending.get(key)! as Promise<T>
    const promise = factory().finally(() => this._pending.delete(key))
    this._pending.set(key, promise)
    return promise
  }
}
