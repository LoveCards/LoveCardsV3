import axios, { AxiosInstance, AxiosRequestConfig, AxiosError, AxiosResponse } from 'axios'
import type { RawApiResponse, RetryConfig, TokenStore, BeforeRequestHook, AfterResponseHook, OnErrorHook, ErrorContext } from '../types/api'
import { ApiError } from '../errors'
import { Deduplicator } from '../dedupe'
import { methodKey } from '../helpers/method-key'

export interface ResourceOptions {
  tokenStore: TokenStore
  debug: boolean
  retry: RetryConfig
  dedupe: Deduplicator
  hooks: {
    beforeRequest: BeforeRequestHook[]
    afterResponse: AfterResponseHook[]
    onError: OnErrorHook[]
  }
}

export abstract class BaseResource {
  protected _instance: AxiosInstance
  protected _opts: ResourceOptions

  constructor(instance: AxiosInstance, opts: ResourceOptions) {
    this._instance = instance
    this._opts = opts
  }

  // ─── 请求方法 ───

  /**
   * 序列化 GET/DELETE 参数中的数组为 JSON 字符串
   * 后端 ValidateExtend::paramsJsonToArray() 用 json_decode 接收，期望 JSON 字符串
   */
  private _serializeParams(params?: any): any {
    if (!params || typeof params !== 'object' || Array.isArray(params)) return params
    const out: any = {}
    for (const [k, v] of Object.entries(params)) {
      if (Array.isArray(v)) {
        out[k] = JSON.stringify(v)
      } else if (k === 'order_desc') {
        // 后端验证 stringBool 只接受 'true'/'false' 字符串
        out[k] = v === true || v === 'true' ? 'true' : 'false'
      } else {
        out[k] = v
      }
    }
    return out
  }

  protected async _get<T>(url: string, params?: any, signal?: AbortSignal): Promise<T> {
    const serialized = this._serializeParams(params)
    const key = methodKey('GET', url, serialized)
    return this._opts.dedupe.execute(key, () =>
      this._request<T>('GET', url, { params: serialized, signal })
    )
  }

  protected async _post<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    return this._request<T>('POST', url, { ...config, data })
  }

  protected async _patch<T>(url: string, data?: any): Promise<T> {
    return this._request<T>('PATCH', url, { data })
  }

  protected async _put<T>(url: string, data?: any): Promise<T> {
    return this._request<T>('PUT', url, { data })
  }

  protected async _delete<T>(url: string, params?: any): Promise<T> {
    return this._request<T>('DELETE', url, { params: this._serializeParams(params) })
  }

  // ─── 内部请求逻辑 ───

  private async _request<T>(method: string, url: string, config: AxiosRequestConfig): Promise<T> {
    const maxRetries = this._opts.retry.maxRetries ?? 0
    const retryOn = this._opts.retry.retryOn ?? []
    const retryDelay = this._opts.retry.retryDelay ?? 1000
    const requestId = Date.now().toString(36) + Math.random().toString(36).slice(2, 8)
    const startTime = Date.now()

    let lastError: unknown

    for (let attempt = 0; attempt <= maxRetries; attempt++) {
      const ctx = {
        requestId,
        method,
        url,
        startTime,
        retryCount: attempt,
        config: { headers: (config.headers as Record<string, string | string[] | undefined>) ?? {} },
      }

      // ─── beforeRequest（可中断，异常包装为 ApiError，不触发 onError）───
      try {
        for (const fn of [...this._opts.hooks.beforeRequest]) {
          await fn(ctx)
        }
      } catch (hookErr) {
        throw ApiError.from(hookErr)
      }

      // ─── 实际 HTTP 请求 ───
      try {
        if (this._opts.debug) {
          console.log(`[LC] ${method} ${url}`, config.data ?? config.params ?? '')
        }

        const response: AxiosResponse<RawApiResponse<T>> = await this._instance.request({
          method,
          url,
          ...config,
          headers: { ...config.headers, ...ctx.config.headers },
        })

        if (this._opts.debug) {
          console.log(`[LC] ${response.status}`, response.data)
        }

        const result = this._unwrap<T>(response)
        const elapsedMs = Date.now() - startTime

        // ─── afterResponse（只读，异常被吞）───
        for (const fn of [...this._opts.hooks.afterResponse]) {
          try { await fn({ ...ctx, status: response.status, data: result, elapsedMs }) } catch {}
        }

        return result
      } catch (err) {
        lastError = err
        const apiErr = ApiError.from(err)
        const elapsedMs = Date.now() - startTime

        if (this._opts.debug) {
          console.log(`[LC] ERROR ${apiErr.status}`, apiErr.message)
        }

        // 推断错误原因
        let reason: ErrorContext['reason'] = 'http'
        if (apiErr.status === 0) {
          if (apiErr.code === -2) reason = 'timeout'
          else if (apiErr.code === -3) reason = 'network'
          else if (apiErr.code === -4) reason = 'cancelled'
        }

        const isRetryable = retryOn.includes(apiErr.status)
        const willRetry = attempt < maxRetries && isRetryable

        // ─── onError（只读，异常被吞）───
        for (const fn of [...this._opts.hooks.onError]) {
          try {
            await fn({
              ...ctx, status: apiErr.status, message: apiErr.message,
              code: apiErr.code, elapsedMs, isRetryable, willRetry, reason,
            })
          } catch {}
        }

        if (willRetry) {
          await new Promise(r => setTimeout(r, retryDelay * (attempt + 1)))
          continue
        }

        throw apiErr
      }
    }

    throw ApiError.from(lastError)
  }

  // ─── 响应解包 ───

  private _unwrap<T>(response: AxiosResponse<RawApiResponse<T>>): T {
    const body = response.data
    const status = response.status

    // 204 No Content
    if (status === 204) return undefined as unknown as T

    // 非 JSON 响应
    if (typeof body !== 'object' || body === null) return body as unknown as T

    // 标准 ApiResponse：{ success, data, pagination? }
    if ('data' in body) {
      // 201 Created with null data → CreateResult { id: null }（审核模式）
      if (body.data === null && status === 201) {
        return { id: null } as unknown as T
      }

      // 列表响应：提取 data + pagination
      if ('pagination' in body && body.pagination) {
        return {
          data: body.data,
          pagination: body.pagination,
        } as unknown as T
      }
      // 单条/创建响应：直接返回 data
      return body.data as T
    }

    // 兜底
    return body as unknown as T
  }
}
