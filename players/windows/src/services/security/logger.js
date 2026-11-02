import { mkdir, stat, rename, appendFile, rm } from 'node:fs/promises';
import path from 'node:path';
export class SafeLogger {
    constructor(directory) { this.directory = directory; this.queue = Promise.resolve(); }
    log(code) {
        const safe = /^[a-z_0-9]{1,80}$/.test(code) ? code : 'unexpected_error';
        this.queue = this.queue.then(async () => {
            await mkdir(this.directory, { recursive: true }); const file = path.join(this.directory, 'player.log');
            if (await stat(file).then(s => s.size > 1024 * 1024).catch(() => false)) {
                await rm(file + '.2', { force: true });
                await rename(file + '.1', file + '.2').catch(() => {}); await rename(file, file + '.1');
            }
            await appendFile(file, JSON.stringify({ at: new Date().toISOString(), code: safe }) + '\n');
        }).catch(() => {});
    }
}
