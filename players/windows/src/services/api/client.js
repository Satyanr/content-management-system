export class ApiError extends Error {
    constructor(code, status = 0, retryAfter = 0) { super(code); this.code = code; this.status = status; this.retryAfter = retryAfter; }
}
export function cmsOrigin(value, allowLocalHttp = false) {
    let url;
    try { url = new URL(value); } catch { throw new ApiError('cms_url_invalid'); }
    const local = ['localhost', '127.0.0.1', '[::1]'].includes(url.hostname);
    if (url.username || url.password || url.search || url.hash || !['', '/'].includes(url.pathname) || (url.protocol !== 'https:' && !(allowLocalHttp && local && url.protocol === 'http:'))) throw new ApiError('cms_url_invalid');
    return url.origin;
}
export class DeviceApi {
    constructor(origin, { token = null, fetcher = fetch, allowLocalHttp = false, onUnauthorized = () => {} } = {}) {
        this.origin = cmsOrigin(origin, allowLocalHttp); Object.assign(this, { token, fetcher, onUnauthorized });
    }
    async request(endpoint, { method = 'GET', headers = {}, body, signal, authenticated = true } = {}) {
        if (!/^\/api\/device\/(?:activate|heartbeat|playback-events|playback-state|manifest|media\/[0-9]+)$/.test(endpoint)) throw new ApiError('api_path_invalid');
        const safeHeaders = { 'Accept': 'application/json', 'X-Player-Schema-Version': '2', ...headers };
        if (authenticated) { if (!this.token) throw new ApiError('invalid_token', 401); safeHeaders.Authorization = 'Bearer ' + this.token; }
        if (body !== undefined) safeHeaders['Content-Type'] = 'application/json';
        let response;
        try { response = await this.fetcher(this.origin + endpoint, { method, headers: safeHeaders, body: body === undefined ? undefined : JSON.stringify(body), signal: signal ? AbortSignal.any([signal, AbortSignal.timeout(120000)]) : AbortSignal.timeout(120000), redirect: 'manual' }); }
        catch (error) { if (signal?.aborted) throw new ApiError('cancelled'); throw new ApiError('cms_unreachable'); }
        if (response.status >= 300 && response.status < 400 && response.status !== 304) { await response.body?.cancel(); throw new ApiError('redirect_rejected', response.status); }
        if (response.status === 304 || response.ok) return response;
        let code = 'http_' + response.status;
        const retry = response.headers.get('retry-after');
        const retryAfter = /^\d+$/.test(retry ?? '') ? Number(retry) * 1000 : Math.max(0, Date.parse(retry ?? '') - Date.now()) || 0;
        // Never propagate arbitrary server strings or HTML into logs/renderer.
        try { const data = await response.json(); if (/^[a-z_]{1,60}$/.test(data.error?.code)) code = data.error.code; } catch {}
        if (response.status === 401) { this.token = null; this.onUnauthorized(code); }
        throw new ApiError(code, response.status, retryAfter);
    }
    async json(endpoint, options) {
        const response = await this.request(endpoint, options);
        if (response.status === 304) return { response, data: null };
        if (Number(response.headers.get('content-length')) > 16 * 1024 * 1024) throw new ApiError('manifest_too_large');
        const reader = response.body.getReader(); const chunks = []; let total = 0;
        try { for (;;) { const { done, value } = await reader.read(); if (done) break; total += value.length; if (total > 16 * 1024 * 1024) throw new ApiError('manifest_too_large'); chunks.push(Buffer.from(value)); } }
        finally { await reader.cancel().catch(() => {}); }
        try { return { response, data: JSON.parse(Buffer.concat(chunks).toString('utf8')) }; } catch { throw new ApiError('response_invalid'); }
    }
}
export function backoff(attempt, retryAfter = 0, random = Math.random) { return Math.max(Math.min(retryAfter, 86400000), Math.min(900000, 2000 * 2 ** Math.min(attempt, 9)) * (0.8 + random() * 0.4)); }
