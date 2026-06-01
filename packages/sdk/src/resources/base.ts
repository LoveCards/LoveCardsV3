import axios, { AxiosInstance, AxiosRequestConfig, AxiosError, AxiosResponse } from 'axios'
import type { RawApiResponse, RetryConfig, TokenStore } from '../types/api'
import { ApiError } from '../errors'
import { Deduplicator } from '../dedupe'
import { methodKey } from '../helpers/method-key'

export interface ResourceOptions {
  tokenStore: TokenStore
  debug: boolean
  retry: RetryConfig
  dedupe: Deduplicator
}

export abstract class BaseResource {
  protected _instance: AxiosInstance
  protected _opts: ResourceOptions

  constructor(instance: AxiosInstance, opts: ResourceOptions) {
    this._instance = instance
    this._opts = opts
  }

  // ─── 请求方法 ───

  protected async _get<T>(url: string, params?: any, signal?: AbortSignal): Promise<T> {
    const key = methodKey('GET', url, params)
    return this._opts.dedupe.execute(key, () =>
      this._request<T>('GET', url, { params, signal })
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
    return this._request<T>('DELETE', url, { params })
  }

  // ─── 内部请求逻辑 ───

  private async _request<T>(method: string, url: string, config: AxiosRequestConfig): Promise<T> {
    const maxRetries = this._opts.retry.maxRetries ?? 0
    const retryOn = this._opts.retry.retryOn ?? []
    const retryDelay = this._opts.retry.retryDelay ?? 1000

    let lastError: unknown

    for (let attempt = 0; attempt <= maxRetries; attempt++) {
      try {
        if (this._opts.debug) {
          console.log(`[LC] ${method} ${url}`, config.data ?? config.params ?? '')
        }

        const response: AxiosResponse<RawApiResponse<T>> = await this._instance.request({
          method,
          url,
          ...config,
        })

        if (this._opts.debug) {
          console.log(`[LC] ${response.status}`, response.data)
        }

        return this._unwrap<T>(response)
      } catch (err) {
        lastError = err
        const apiErr = ApiError.from(err)

        if (this._opts.debug) {
          console.log(`[LC] ERROR ${apiErr.status}`, apiErr.message)
        }

        if (attempt < maxRetries && retryOn.includes(apiErr.status)) {
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
