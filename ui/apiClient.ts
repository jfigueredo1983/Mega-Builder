/**
 * Mega Builder — Stage 4 Typed JSON REST/RPC API Client with CSRF Shield & Idempotency
 */

import type { ApiEnvelope } from './types';

export async function callMegaBuilderApi<T = Record<string, any>>(
  ajaxUrl: string,
  csrfToken: string,
  action: string,
  payload: Record<string, any> = {}
): Promise<ApiEnvelope<T>> {
  const sep = ajaxUrl.includes('?') ? '&' : '?';
  const endpoint = `${ajaxUrl}${sep}ajax=1&mb_action=${encodeURIComponent(action)}`;

  const idempotencyKey = `mb_${action}_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;

  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-MB-CSRF-Token': csrfToken,
        'X-MB-Idempotency-Key': idempotencyKey,
      },
      body: JSON.stringify({
        action,
        csrf_token: csrfToken,
        ...payload,
      }),
    });

    const json = (await response.json()) as ApiEnvelope<T>;
    return json;
  } catch (err: any) {
    return {
      ok: false,
      code: 'NETWORK_ERROR',
      message: err?.message || 'Network communication failed.',
      data: {} as T,
      errors: [err?.message || 'Network communication failed.'],
    };
  }
}
