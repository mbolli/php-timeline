import fs from 'node:fs';
import { e2eDir } from './env';

export default function teardown(): void {
    fs.rmSync(e2eDir, { recursive: true, force: true });
}
