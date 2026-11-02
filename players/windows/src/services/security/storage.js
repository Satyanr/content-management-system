import { readFile, open, rename, mkdir, rm } from 'node:fs/promises';
import path from 'node:path';
import { randomUUID, scryptSync, timingSafeEqual, randomBytes } from 'node:crypto';

export async function atomicWrite(file, data) {
    await mkdir(path.dirname(file), { recursive: true });
    const temporary = file + '.' + randomUUID() + '.tmp';
    const handle = await open(temporary, 'wx', 0o600);
    try {
        try { await handle.writeFile(data); await handle.sync(); } finally { await handle.close(); }
        await rename(temporary, file);
    } catch (error) { await rm(temporary, { force: true }); throw error; }
}
export async function readJson(file, fallback = null) {
    try { return JSON.parse(await readFile(file, 'utf8')); } catch (error) {
        if (error.code === 'ENOENT' || error instanceof SyntaxError) return fallback;
        throw error;
    }
}
export class CredentialVault {
    constructor(directory, safeStorage) { this.file = path.join(directory, 'credentials.bin'); this.safe = safeStorage; }
    async available() {
        if (typeof this.safe.isAsyncEncryptionAvailable !== 'function' || !await this.safe.isAsyncEncryptionAvailable()) throw new Error('secure_storage_unavailable');
        if (this.safe.getSelectedStorageBackend?.() === 'basic_text') throw new Error('secure_storage_unavailable');
    }
    async save(identity) { await this.available(); await atomicWrite(this.file, await this.safe.encryptStringAsync(JSON.stringify(identity))); }
    async load() {
        let encrypted;
        try { encrypted = await readFile(this.file); } catch (error) { if (error.code === 'ENOENT') return null; throw error; }
        await this.available();
        try {
            const decrypted = await this.safe.decryptStringAsync(encrypted);
            const identity = JSON.parse(decrypted.result);
            if (decrypted.shouldReEncrypt) await this.save(identity);
            return identity;
        } catch { throw new Error('credential_storage_unreadable'); }
    }
}
export class OperatorLock {
    constructor(saved = null, now = () => Date.now()) { this.saved = saved; this.now = now; this.failures = 0; this.blockedUntil = 0; this.unlockedUntil = 0; }
    get unlocked() { return !this.saved || this.unlockedUntil > this.now(); }
    unlock(pin) {
        if (this.blockedUntil > this.now()) throw new Error('operator_rate_limited');
        if (!this.saved || timingSafeEqual(Buffer.from(this.saved.hash, 'hex'), scryptSync(pin, this.saved.salt, 32))) {
            this.failures = 0; this.unlockedUntil = this.now() + 300000; return true;
        }
        if (++this.failures >= 5) { this.blockedUntil = this.now() + 60000; this.failures = 0; }
        throw new Error('operator_pin_invalid');
    }
    set(pin) {
        if (!this.unlocked) throw new Error('operator_locked');
        if (!/^\d{6,12}$/.test(pin)) throw new Error('operator_pin_format');
        const salt = randomBytes(16).toString('hex');
        this.saved = { salt, hash: scryptSync(pin, salt, 32).toString('hex') }; this.unlockedUntil = this.now() + 300000;
        return this.saved;
    }
}
